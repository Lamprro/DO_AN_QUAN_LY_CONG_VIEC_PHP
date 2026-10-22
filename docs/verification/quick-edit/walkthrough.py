"""Actual MVC HTTP walkthrough against an isolated installation; no test classes or mocks."""
from pathlib import Path
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,html,sqlite3,time,hashlib,subprocess,datetime,sys
import argparse,shutil,base64
args=argparse.ArgumentParser();args.add_argument('--review-root',required=True);args.add_argument('--base-url',required=True);opt=args.parse_args()
R=Path(opt.review_root).resolve();MAIN=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');assert R!=MAIN.resolve() and (R/'artisan').is_file()
DB=Path(re.search(r'^DB_DATABASE="([^\"]+)"',(R/'.env').read_text(),re.M)[1]);assert DB.resolve()!= (MAIN/'database/work_management.sqlite').resolve()
OUT=R/'storage/app/private/quick-edit-replay';OUT.mkdir(parents=True,exist_ok=True);PRIVATE=OUT;BASE=opt.base_url.rstrip('/');assert urllib.parse.urlparse(BASE).hostname in ['127.0.0.1','localhost'];stage='http';journal=[];state={'prefix':'QuickEdit'+str(int(time.time()))};prefix=state['prefix']

def checkpoint():(PRIVATE/'flow-state.json').write_text(json.dumps(state),encoding='utf-8')
def log(step,**data):
 journal.append(dict(step=step,**data));(OUT/('flow-'+stage+'.json')).write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(step,flush=True)
def verify(step,ok,**data):
 log(step,verified=bool(ok),**data);assert ok,step
def rows(q,args=()):
 c=sqlite3.connect('file:'+DB.as_posix()+'?mode=ro',uri=True);c.row_factory=sqlite3.Row
 try:return [dict(x) for x in c.execute(q,args)]
 finally:c.close()
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None,expected=200):
 url=path if path.startswith('http') else BASE+path;raw=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
 try:r=c.open(urllib.request.Request(url,data=raw,headers={'Accept':'text/html','Referer':getattr(c,'last',BASE+'/')}),timeout=30)
 except urllib.error.HTTPError as e:r=e
 body=r.read().decode('utf-8',errors='replace');c.last=r.geturl();safe_path=re.sub(r'(/invitations/)[^/]+',r'\1[redacted]',urllib.parse.urlparse(url).path);log('HTTP',method='POST' if data is not None else 'GET',path=safe_path,status=r.code);assert r.code==expected,(safe_path,r.code);return r.geturl(),body
def token(body):return html.unescape(re.search(r'name="_token"\s+value="([^"]+)"',body)[1])
def attrs(tag):return {k:html.unescape(v) for k,v in re.findall(r'([\w:-]+)="([^"]*)"',tag)}
def form(c,page,marker,values=None):
 url,body=call(c,page);f=re.search(r'<form\b(?=[^>]*data-form="'+re.escape(marker)+r'")[^>]*>(.*?)</form>',body,re.S);assert f,marker;a=attrs(f[0]);data={}
 for tag in re.findall(r'<input\b[^>]*>',f[1]):
  x=attrs(tag)
  if x.get('type')=='hidden' and 'name' in x:data[x['name']]=x.get('value','')
 if values is None:return a['action'],data,body
 data.update(values);return call(c,a['action'],data)
def post(c,path,data,page=None,expected=200):
 _,body=call(c,page or '/workspaces');return call(c,path,{'_token':token(body),**data},expected)
def login(email):
 c=client();action,fields,body=form(c,'/login','login')
 # Verify this HTTP server writes its sessions to the explicitly selected review DB before business writes.
 assert any(fields['_token'].encode() in base64.b64decode(r['payload']) for r in rows('select payload from sessions')), 'HTTP server does not match review database; stopped before login/business writes'
 call(c,action,{**fields,'email':email,'password':'Demo@123456'});return c
def business():
 return {t:hashlib.sha256(json.dumps(rows('select * from '+t+' order by id'),sort_keys=True).encode()).hexdigest() for t in ['tasks','projects','plans','calendar_events','task_activities','notifications','time_entries','time_entry_adjustments']}
def cur(t):return rows('select * from tasks where id=?',(t,))[0]
def work(c,tp,title,**extra):
 url,body=form(c,tp+'/create','task-create',{'task_type':'WORK','priority':'medium','title':title,**extra});tid=url.rstrip('/').split('/')[-1];verify('task persisted '+title,bool(rows('select id from tasks where id=?',(tid,))));return tid

def captured(body,marker):
 f=re.search(r'<form\b(?=[^>]*data-form="'+re.escape(marker)+r'")[^>]*>(.*?)</form>',body,re.S);assert f,marker
 data={attrs(t)['name']:attrs(t).get('value','') for t in re.findall(r'<input\b[^>]*>',f[1]) if attrs(t).get('type')=='hidden' and 'name' in attrs(t)}
 return attrs(f[0])['action'],data,f[1]
def choices(body,**override):
 a,d,f=captured(body,'task-conflict')
 d.update({name:'server' for name in re.findall(r'name="(choices\[[^\]]+\])"',f)})
 d.update({'choices['+k+']':v for k,v in override.items()});return a,d
def quick(c,page,**values):
 a,d,b=form(c,page,'task-quick-edit');t=cur(page.split('/')[-1]);d.update(title=t['title'],priority=t['priority']);d.update(values);return a,d,b
owner=login('demo1@example.test');other=login('demo1@example.test');viewer=login('demo5@example.test')
uid=rows("select id from users where email='demo1@example.test'")[0]['id']
url,_=form(owner,'/workspaces/create','workspace-create',{'name':prefix,'timezone':'Asia/Ho_Chi_Minh'});wid=url.split('/')[-1];wp='/workspaces/'+wid
url,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' project','status':'active'});pid=url.split('/')[-1];tp=wp+'/projects/'+pid+'/tasks'
url,_=form(owner,tp+'/create','task-create',{'task_type':'GROUP','title':prefix+' group','priority':'medium'});gid=url.split('/')[-1]
tid=work(owner,tp,prefix+' original',description='Keep description <script>never execute</script>',parent_task_id=gid,group_label='Keep group',sort_order=23,assignee_id=uid,reviewer_id=uid,estimate_minutes=60)
page=tp+'/'+tid;before=cur(tid);a,d,_=quick(owner,page,title=prefix+' draft',priority='urgent',estimate_minutes='120',deadline_at='2027-06-18T17:30',assignee_id=uid,reviewer_id=uid)
hashes=business();_,body=call(owner,a,d);_,saved,f=captured(body,'task-quick-edit')
verify('deadline reason rejection keeps draft, old revision and reopens modal',business()==hashes and 'data-reopen="1"' in body and prefix+' draft' in f and 'value="urgent" selected' in f and saved['expected_revision']==d['expected_revision'])
# Resubmit exactly the returned failed form with its preserved revision.
call(owner,a,{**d,**saved,'deadline_reason':'Real reschedule reason'})
t=cur(tid);verify('quick PATCH persists metadata and preserves description/tree',t['title']==prefix+' draft' and t['priority']=='urgent' and t['estimate_minutes']==120 and t['deadline_at'].startswith('2027-06-18 10:30') and all(t[k]==before[k] for k in ['description','parent_task_id','group_label','sort_order','assignee_id','reviewer_id','code']))
a,d,_=quick(owner,page,title='',priority='high');hashes=business();_,body=call(owner,a,d)
verify('FormRequest required title reopens quick form and keeps blank input',hashes==business() and 'data-reopen="1"' in body and 'id="quick_title"' in body and re.search(r'id="quick_title"[^>]*value=""',body) is not None)
# A real second browser/session has an older form while owner saves.
action,stale,_=quick(other,page,title=prefix+' B <script>unsafe()</script>',priority='low')
a,d,_=quick(owner,page,title=prefix+' A',priority='high');call(owner,a,d);hashes=business()
_,conf=call(other,action,stale,409);verify('stale quick edit shows escaped comparisons without writes',hashes==business() and 'data-page="task-conflict"' in conf and '&lt;script&gt;unsafe()&lt;/script&gt;' in conf and '<script>unsafe()</script>' not in conf)
resolve,payload=choices(conf,title='mine',priority='server')
bad={**payload,'expected_revision':int(payload['expected_revision'])+1};call(other,resolve,bad,422);verify('forged comparison revision rejected',hashes==business())
bad={**payload,'choices[status]':'mine'};call(other,resolve,bad,422);verify('injected comparison field rejected',hashes==business())
bad={k:v for k,v in payload.items() if k!='choices[title]'};call(other,resolve,bad,422);verify('missing field selection rejected',hashes==business())
post(owner,resolve,{k:v for k,v in payload.items() if k!='_token'},expected=404);verify('comparison token cannot cross sessions',hashes==business())
post(other,tp+'/'+gid+'/resolve-edit',payload,expected=404);verify('comparison token cannot cross tasks',hashes==business())
a,d,_=quick(owner,page,title=prefix+' C',priority='urgent');call(owner,a,d);hashes=business()
_,conf2=call(other,resolve,payload,409);verify('third edit during comparison causes another conflict without writes',hashes==business() and 'data-page="task-conflict"' in conf2)
resolve,payload2=choices(conf2,title='mine',priority='server');n=len(rows('select id from task_activities where task_id=?',(tid,)));rev=cur(tid)['revision']
call(other,resolve,payload2);t=cur(tid)
verify('explicit mixed choices save with one revision and history',t['title']==prefix+' B <script>unsafe()</script>' and t['priority']=='urgent' and t['revision']==rev+1 and len(rows('select id from task_activities where task_id=?',(tid,)))==n+1)
hashes=business();call(other,resolve,payload2,404);verify('successful token replay rejected',hashes==business())
call(other,resolve,payload,404);verify('replaced token replay rejected',hashes==business())
# Full form uses the same revision+conflict flow, including a real description change.
a,stale,_=form(other,page+'/edit','task-edit');stale.update(title=prefix+' full B',priority='low',description='Full edit B')
a2,d,_=quick(owner,page,title=prefix+' full A',priority='high');call(owner,a2,d);hashes=business();_,fullconf=call(other,a,stale,409)
verify('full form stale uses comparison and preserves server',hashes==business() and 'Full edit B' in fullconf)
resolve,p=choices(fullconf,title='server',priority='mine',description='mine');call(other,resolve,p)
verify('full edit chosen fields persisted',cur(tid)['description']=='Full edit B' and cur(tid)['priority']=='low' and cur(tid)['title']==prefix+' full A')
# Viewer can view demo scope but cannot forge metadata writes.
shared=rows("select id,workspace_id from projects where name='Demo Work Management'")[0];sharedtp='/workspaces/'+shared['workspace_id']+'/projects/'+shared['id']+'/tasks';sharedid=work(owner,sharedtp,prefix+' viewer target');sharedpage=sharedtp+'/'+sharedid
_,b=call(viewer,sharedpage);hashes=business();call(viewer,sharedpage,{'_token':token(b),'_method':'PATCH','edit_surface':'quick','expected_revision':cur(sharedid)['revision'],'title':'FORGED','priority':'urgent'},403);verify('Viewer forged PATCH denied with no writes',hashes==business())
_,b=call(owner,page);hashes=business();call(owner,page,{'_method':'PATCH','title':'CSRF FORGED','priority':'high','expected_revision':cur(tid)['revision']},419);verify('CSRF enforced on quick PATCH',hashes==business())
_,body=call(owner,tp+'/'+gid);f=captured(body,'task-quick-edit')[2];verify('GROUP quick form omits assignment and estimate',not re.search(r'name="(?:assignee_id|reviewer_id|estimate_minutes)"',f))
# Unauthorized assignment rejected; state and selection are preserved.
foreign=rows("select id from users where email='demo2@example.test'")[0]['id'];a,d,_=quick(owner,page,title='Bad assignment',assignee_id=foreign);hashes=business();_,body=call(owner,a,d)
verify('ineligible assignment rejected, retained for correction',hashes==business() and 'data-reopen="1"' in body and 'Lựa chọn đã nhập' in body and foreign in captured(body,'task-quick-edit')[2])
# Both timezone dates and stored UTC are checked from actual HTTP forms.
php=PRIVATE/'clock.php';php.write_text("<?php require __DIR__.'/../../../../vendor/autoload.php'; $d=new App\\Services\\TaskEditData; echo json_encode(['friday'=>$d->forDate(Carbon\\CarbonImmutable::parse('2026-10-23','Asia/Ho_Chi_Minh')),'saturday'=>$d->forDate(Carbon\\CarbonImmutable::parse('2026-10-24','Asia/Ho_Chi_Minh')),'zones'=>array_map(fn($z)=>['zone'=>$z,'now'=>Carbon\\CarbonImmutable::now($z)->format('Y-m-d'),'presets'=>$d->forDate(Carbon\\CarbonImmutable::now($z))],['Pacific/Kiritimati','Pacific/Pago_Pago'])]);",encoding='utf-8')
clock=json.loads(subprocess.check_output([r'C:\xampp\php\php.exe',str(php)],cwd=R))
verify('Friday includes today; Saturday selects following Friday from calendar date data',clock['friday'][2]['value']=='2026-10-23T17:30' and clock['saturday'][2]['value']=='2026-10-30T17:30')
for z in clock['zones']:
 url,_=form(owner,'/workspaces/create','workspace-create',{'name':prefix+' '+z['zone'],'timezone':z['zone']});zw=url.split('/')[-1]
 url,_=form(owner,'/workspaces/'+zw+'/projects/create','project-create',{'name':prefix+' timezone project','status':'active'});zpid=url.split('/')[-1];ztp='/workspaces/'+zw+'/projects/'+zpid+'/tasks';ztid=work(owner,ztp,prefix+' zoned task');zp=ztp+'/'+ztid
 a,d,body=quick(owner,zp);expected=z['presets'][0]['value'];verify('server preset matches workspace date '+z['zone'],f'data-value="{expected}"' in captured(body,'task-quick-edit')[2] and z['zone'] in body)
 call(owner,a,{**d,'deadline_at':expected,'deadline_reason':'Timezone real flow'})
 # UTC conversion independently from the numeric zone offsets (UTC+14 and UTC-11).
 dt=datetime.datetime.fromisoformat(expected)-datetime.timedelta(hours=14 if z['zone']=='Pacific/Kiritimati' else -11)
 verify('actual saved UTC matches local preset '+z['zone'],cur(ztid)['deadline_at'].startswith(dt.strftime('%Y-%m-%d %H:%M')))
verify('isolated foreign key integrity',not rows('pragma foreign_key_check'))
state.update(wid=wid,pid=pid,tp=tp,tid=tid,page=page,uid=uid,group=gid);checkpoint();log('COMPLETE',no_test_classes=True,real_http_sessions=True,real_database=True,calendar_date_rules='explicit dates only; server clock unchanged')
