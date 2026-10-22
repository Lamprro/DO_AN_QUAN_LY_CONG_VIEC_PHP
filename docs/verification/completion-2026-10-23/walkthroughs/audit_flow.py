"""Actual MVC HTTP walkthrough against an isolated installation; no test classes or mocks."""
from pathlib import Path
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,html,sqlite3,time,hashlib,subprocess,datetime,sys
import os,base64
M=Path(__file__).parent;MAIN=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
R=Path(os.environ['WM_REVIEW_ROOT']).resolve();assert R!=MAIN.resolve() and (R/'artisan').is_file()
DB=Path(re.search(r'^DB_DATABASE="([^"]+)"',(R/'.env').read_text(),re.M)[1]).resolve();assert DB.is_relative_to(R) and DB!=MAIN/'database/work_management.sqlite'
BASE=os.environ.get('WM_REVIEW_URL','http://127.0.0.1:8126').rstrip('/');assert urllib.parse.urlparse(BASE).hostname in ['127.0.0.1','localhost']
OUT=R/'storage/app/private/completion-replay';OUT.mkdir(parents=True,exist_ok=True);PRIVATE=OUT;stage=sys.argv[1] if len(sys.argv)>1 else 'core';journal=[];state=json.loads((PRIVATE/'flow-state.json').read_text()) if (PRIVATE/'flow-state.json').exists() else {'prefix':'Completion'+str(int(time.time()))};prefix=state['prefix']

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
 c=client();a,d,_=form(c,'/login','login');assert any(d['_token'].encode() in base64.b64decode(row['payload']) for row in rows('select payload from sessions')), 'Server does not use review DB';call(c,a,{**d,'email':email,'password':'Demo@123456'});return c
def business():
 return {t:hashlib.sha256(json.dumps(rows('select * from '+t+' order by id'),sort_keys=True).encode()).hexdigest() for t in ['tasks','projects','plans','calendar_events','task_activities','notifications','time_entries','time_entry_adjustments']}
def cur(t):return rows('select * from tasks where id=?',(t,))[0]
def work(c,tp,title,**extra):
 url,body=form(c,tp+'/create','task-create',{'task_type':'WORK','priority':'medium','title':title,**extra});tid=url.rstrip('/').split('/')[-1];verify('task persisted '+title,bool(rows('select id from tasks where id=?',(tid,))));return tid
owner=login('demo1@example.test');tab=login('demo1@example.test');member=login('demo3@example.test');viewer=login('demo5@example.test');manager=login('demo2@example.test');uid=rows("select id from users where email='demo1@example.test'")[0]['id'];mid=rows("select id from users where email='demo3@example.test'")[0]['id']
if stage=='core':
 url,_=form(owner,'/workspaces/create','workspace-create',{'name':prefix+' private workspace','timezone':'Asia/Ho_Chi_Minh'});wid=url.split('/')[-1];wp='/workspaces/'+wid
 url,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' project','status':'active','start_date':'2026-10-01','deadline':'2027-12-31'});pid=url.split('/')[-1];pp=wp+'/projects/'+pid;tp=pp+'/tasks';state.update(wid=wid,pid=pid,wp=wp,pp=pp,tp=tp,uid=uid);checkpoint()
 ids=[]
 for n in range(12):ids.append(work(owner,tp,prefix+' task '+str(n),assignee_id=uid,reviewer_id=uid,estimate_minutes=30))
 state['tasks']=ids;checkpoint()
 _,body=call(owner,tp+'?view=board&q='+prefix);visible=re.findall(r'data-task-id="([^"]+)"',body);verify('board first page ten actual tasks',len(visible)==10 and set(visible)<=set(ids))
 _,body=call(owner,tp+'?view=board&q='+prefix+'&page_todo=2');visible2=re.findall(r'data-task-id="([^"]+)"',body);verify('board next page independent and complete',len(visible2)==2 and not set(visible)&set(visible2))
 call(viewer,tp+'?view=board',expected=403)
 other=rows("select id,workspace_id from projects where name='Demo Isolated Project'")[0];secret=work(manager,'/workspaces/'+other['workspace_id']+'/projects/'+other['id']+'/tasks',prefix+' secret outside scope')
 _,body=call(owner,'/search?q='+prefix);verify('search does not leak foreign title or id',prefix+' secret outside scope' not in body and secret not in body and '(12)' in body)
 _,body=call(member,'/search?q='+prefix);verify('member search has no private workspace rows',not any(i in body for i in ids) and prefix+' project' not in body)
 call(owner,'/search?q='+prefix+'&workspace_id='+other['workspace_id'],expected=403)
 literal=work(owner,tp,prefix+' literal%_ and <script>alert(1)</script>',assignee_id=uid,reviewer_id=uid);state['literal']=literal;checkpoint()
 _,body=call(owner,'/search?'+urllib.parse.urlencode({'q':'%_'}));verify('search treats SQL wildcard characters literally and escapes markup',literal in body and all(i not in body for i in ids) and '<script>alert(1)</script>' not in body)
 url,_=post(owner,pp+'/task-filters',{'name':prefix+' board','query[preset]':'mine','query[view]':'board','query[q]':prefix,'query[password]':'SHOULD_NOT_SAVE'},tp)
 saved=rows('select * from saved_task_filters where name=?',(prefix+' board',))[0];verify('filter saved whitelist and board',json.loads(saved['query'])=={'preset':'mine','view':'board','q':prefix})
 url,_=call(owner,pp+'/task-filters/'+saved['id']+'/open');verify('saved filter opens actual board','view=board' in url);call(member,pp+'/task-filters/'+saved['id']+'/open',expected=403)
 def items(seq):return {'items['+i+']':cur(i)['revision'] for i in seq}
 post(owner,tp+'/bulk',{'action':'priority','priority':'high',**items(ids[:2])},tp);verify('bulk persisted both selected priorities',all(cur(i)['priority']=='high' for i in ids[:2]))
 before=business();post(owner,tp+'/bulk',{'action':'priority','priority':'urgent','items['+ids[0]+']':1,'items['+ids[1]+']':cur(ids[1])['revision']},tp);verify('stale bulk all-or-nothing',before==business())
 post(owner,tp+'/'+ids[0]+'/blockers',{'reason':'Review real blocked workflow'},tp+'/'+ids[0]);before=business();post(owner,tp+'/'+ids[0]+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'},tp+'/'+ids[0]);verify('blocker cannot be bypassed',before==business())
 bid=rows('select id from task_blockers where task_id=?',(ids[0],))[0]['id'];post(owner,tp+'/'+ids[0]+'/blockers/'+bid,{'_method':'PATCH','status':'resolved'},tp+'/'+ids[0])
 post(owner,tp+'/'+ids[1]+'/dependencies',{'predecessor_task_id':ids[0],'lag_minutes':0},tp+'/'+ids[1]);post(owner,tp+'/'+ids[1]+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'},tp+'/'+ids[1]);verify('FS blocks successor before predecessor done',cur(ids[1])['status']=='TODO')
 post(owner,tp+'/'+ids[0]+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'},tp+'/'+ids[0]);verify('work actually started',cur(ids[0])['status']=='IN_PROGRESS')
 post(owner,wp+'/timer/'+ids[0],{},tp+'/'+ids[0]);entry=rows('select * from time_entries where user_id=? and ended_at is null',(uid,))[0];state['entry']=entry['id'];checkpoint();time.sleep(3)
 post(owner,tp+'/'+ids[0]+'/status',{'_method':'PATCH','status':'REVIEW','expected_status':'IN_PROGRESS'},tp+'/'+ids[0]);verify('running timer stops premature review',cur(ids[0])['status']=='IN_PROGRESS')
 form(owner,tp+'/'+ids[0],'global-timer-stop',{});verify('real timer ended',rows('select ended_at from time_entries where id=?',(entry['id'],))[0]['ended_at'] is not None)
 for to,expected in [('REVIEW','IN_PROGRESS'),('DONE','REVIEW')]:post(owner,tp+'/'+ids[0]+'/status',{'_method':'PATCH','status':to,'expected_status':expected},tp+'/'+ids[0])
 verify('reviewer completed work',cur(ids[0])['status']=='DONE')
 post(owner,tp+'/'+ids[1]+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'},tp+'/'+ids[1]);verify('FS successor now allowed',cur(ids[1])['status']=='IN_PROGRESS')
 form(owner,tp+'/'+ids[1],'comment-create',{'content':prefix+' real comment <script>unsafe()</script>'});verify('comment stored and escaped',bool(rows('select id from task_activities where task_id=? and type=?',(ids[1],'COMMENT'))) and '<script>unsafe()</script>' not in call(owner,tp+'/'+ids[1])[1])
 checkpoint();log('COMPLETE',isolated_database=True,no_test_classes=True)
elif stage=='calendar-plan':
 wp,pp,tp,pid=state['wp'],state['pp'],state['tp'],state['pid'];ids=state['tasks']
 data={'title':prefix+' event','event_type':'NOTE','project_id':pid,'task_id':'','status':'planned','visibility':'private','timezone':'Asia/Ho_Chi_Minh','all_day':'0','is_busy':'1','starts_at':'2027-06-15T09:00','ends_at':'2027-06-15T10:00'}
 url,_=form(owner,wp+'/calendar/create','calendar-save',data);event=url.split('/')[-1];state['event']=event;checkpoint();ep=wp+'/calendar/'+event
 action,stale,_=form(tab,ep+'/edit','calendar-save');form(owner,ep+'/edit','calendar-save',{**data,'title':prefix+' event changed A'})
 oldrow=rows('select * from calendar_events where id=?',(event,));_,body=call(tab,action,{**stale,**data,'title':'MUST NOT OVERWRITE B'});verify('calendar stale keeps version and text',oldrow==rows('select * from calendar_events where id=?',(event,)) and stale['version'] in body and 'MUST NOT OVERWRITE B' in body)
 errorform=re.search(r'<form\b(?=[^>]*data-form="calendar-save")[^>]*>(.*?)</form>',body,re.S);stale2={attrs(tag)['name']:attrs(tag).get('value','') for tag in re.findall(r'<input\b[^>]*>',errorform[1]) if attrs(tag).get('type')=='hidden' and 'name' in attrs(tag)}
 _,body=call(tab,action,{**stale2,**data,'title':'SECOND STALE MUST FAIL'});verify('repeated stale submit still cannot overwrite',oldrow==rows('select * from calendar_events where id=?',(event,)) and stale2['version']==stale['version'])
 form(tab,ep+'/edit?fresh=1','calendar-save',{**data,'title':prefix+' event fresh save'});verify('explicit fresh allows current version save',rows('select title from calendar_events where id=?',(event,))[0]['title']==prefix+' event fresh save')
 before=business();form(owner,wp+'/calendar/create','calendar-save',{**data,'title':'conflict must not save'});verify('busy conflict rejected without new event',before==business())
 planpage=wp+'/plans/create?project_id='+pid;payload={'schema_version':1,'tasks':[{'ref':'work1','title':prefix+' planned work','task_type':'WORK','priority':'high','estimate_minutes':30,'assignee_id':uid,'reviewer_id':uid}],'dependencies':[],'slots':[{'task_ref':'work1','starts_at':'2027-06-14T13:00','ends_at':'2027-06-14T13:30'}]}
 url,_=form(owner,planpage,'plan-create',{'name':prefix+' actual plan','input_mode':'editor','editor_enabled':'1','payload':json.dumps(payload)});plan=url.split('/')[-1];state['plan']=plan;checkpoint();planpath=wp+'/plans/'+plan
 verify('draft persisted only',rows('select status from plans where id=?',(plan,))[0]['status']=='draft' and not rows('select id from tasks where title=?',(prefix+' planned work',)))
 before=business();_,body=call(owner,planpath+'/preview');verify('preview validates real apply and rolls back all writes','Mô phỏng hợp lệ' in body and before==business())
 for action,expected in [('propose','draft'),('approve','proposed')]:post(owner,planpath+'/'+action,{'expected_status':expected},planpath)
 form(owner,planpath+'/preview','plan-apply',{});p=rows('select * from plans where id=?',(plan,))[0];verify('apply creates real task/event once',p['is_active']==1 and len(rows('select id from tasks where title=?',(prefix+' planned work',)))==1 and len(rows('select id from calendar_events where plan_id=?',(plan,)))==1)
 before=business();post(owner,planpath+'/apply',{'expected_status':'approved'},planpath);verify('repeat apply idempotent',before==business())
 fixture=rows("select id,workspace_id from plans where name='Demo proposal (fixture)'")[0];before=business();_,body=call(owner,'/workspaces/'+fixture['workspace_id']+'/plans/'+fixture['id']+'/preview');verify('legacy empty fixture never falsely valid','Kế hoạch thiếu dữ liệu chuẩn' in body and 'Mô phỏng hợp lệ tại thời điểm kiểm tra!' not in body and before==business())
 before=business();form(owner,planpage,'plan-create',{'name':'empty invalid plan','input_mode':'json','payload':json.dumps({'schema_version':1,'tasks':[],'dependencies':[],'slots':[]})});verify('empty plan HTTP rejected without writes',before==business())
 checkpoint();log('COMPLETE',real_calendar_versions=True,real_plan_lifecycle=True)
elif stage=='reports-queue':
 wp,tp,pid=state['wp'],state['tp'],state['pid'];ids=state['tasks'];entry=state['entry'];ep='/timers/'+entry+'/adjust';now=datetime.datetime.now();start=now.replace(day=1).strftime('%Y-%m-%d');end=(now.replace(day=28)+datetime.timedelta(days=4)).replace(day=1).strftime('%Y-%m-%d');dash=wp+'/dashboard?'+urllib.parse.urlencode({'from':start,'to':end,'project_id':pid})
 url,_=form(owner,dash,'report-create',{});snap=url.split('/')[-1];raw=rows('select metrics_json from report_snapshots where id=?',(snap,))[0]['metrics_json'];state['snapshot']=snap;checkpoint()
 # Native field contract is read from the actual correction form; marker verified below.
 _,body=call(owner,ep+'?fresh=1');marker=re.search(r'data-form="([^"]+)"',body[body.index('<main'):])
 matches=re.findall(r'<form\b[^>]*data-form="([^"]+)"[^>]*>',body);marker=next(x for x in matches if x!='logout')
 action,fields,_=form(owner,ep+'?fresh=1',marker);_,tabfields,_=form(tab,ep+'?fresh=1',marker)
 row=rows('select * from time_entries where id=?',(entry,))[0];fmt=lambda s:(datetime.datetime.strptime(s[:19],'%Y-%m-%d %H:%M:%S')+datetime.timedelta(hours=7)).strftime('%Y-%m-%dT%H:%M:%S');values={'started_at':fmt(row['started_at']),'ended_at':fmt(row['ended_at']),'note':'Review corrected captured time','reason':'Correct real captured timer by one second with original preserved'};values['ended_at']=(datetime.datetime.fromisoformat(values['ended_at'])-datetime.timedelta(seconds=1)).isoformat()
 before_audits=len(rows('select id from time_entry_adjustments where time_entry_id=?',(entry,)));call(owner,action,{**fields,**values});verify('own time correction persists audit',len(rows('select id from time_entry_adjustments where time_entry_id=?',(entry,)))==before_audits+1)
 before=business();_,body=call(tab,action,{**tabfields,**values,'note':'STALE TAB MUST NOT SAVE'});verify('correction stale preserves entry and audit',before==business() and 'STALE TAB MUST NOT SAVE' in body)
 verify('saved report remains byte-identical',raw==rows('select metrics_json from report_snapshots where id=?',(snap,))[0]['metrics_json'])
 url,_=form(owner,dash,'report-create',{});new=json.loads(rows('select metrics_json from report_snapshots where id=?',(url.split('/')[-1],))[0]['metrics_json']);verify('fresh report follows actual corrected time',new['period_metrics']['actual_minutes']<json.loads(raw)['period_metrics']['actual_minutes'])
 call(owner,wp+'/reports/'+snap+'/export');call(owner,wp+'/reports/'+snap+'/print')
 url,_=form(owner,wp+'/ai-runs/create?'+urllib.parse.urlencode({'from':start,'to':end,'project_id':pid}),'ai-run-create',{'from':start,'to':end,'project_id':pid,'user_prompt':prefix+' AI currently unconfigured'});run=url.split('/')[-1];verify('actual AI request queued',rows('select status from ai_runs where id=?',(run,))[0]['status']=='queued')
 proc=subprocess.run([r'C:\xampp\php\php.exe','artisan','queue:work','database','--queue=work-management-ai','--stop-when-empty','--tries=3'],cwd=R,capture_output=True,text=True,timeout=60);verify('actual worker leaves disabled provider failed not fabricated',proc.returncode==0 and rows('select status from ai_runs where id=?',(run,))[0]['status']=='failed',worker_output=proc.stdout[-2000:])
 call(owner,wp+'/operations');call(member,wp+'/operations',expected=403)
 due=datetime.datetime.now()+datetime.timedelta(hours=8);due_id=work(owner,tp,prefix+' due reminder',assignee_id=uid,reviewer_id=uid,deadline_at=due.strftime('%Y-%m-%dT%H:%M'));proc=subprocess.run([r'C:\xampp\php\php.exe','artisan','tasks:remind','--workspace='+state['wid'],'--user='+uid],cwd=R,capture_output=True,text=True,timeout=60);verify('reminder ledger queued',proc.returncode==0 and bool(rows('select id from task_reminders where task_id=?',(due_id,))))
 proc=subprocess.run([r'C:\xampp\php\php.exe','artisan','queue:work','database','--queue=work-management-reminders','--stop-when-empty'],cwd=R,capture_output=True,text=True,timeout=60);verify('actual reminder sent in-app',proc.returncode==0 and rows('select status from task_reminders where task_id=?',(due_id,))[0]['status']=='sent')
 before=rows('select id from notifications where task_id=?',(due_id,));subprocess.run([r'C:\xampp\php\php.exe','artisan','tasks:remind','--workspace='+state['wid'],'--user='+uid],cwd=R,capture_output=True,timeout=60);verify('repeat scan does not duplicate reminders',before==rows('select id from notifications where task_id=?',(due_id,)))
 call(owner,'/notifications?type=task.reminder_due');checkpoint();log('COMPLETE',real_timer_and_worker=True)
else:raise ValueError(stage)
verify('foreign key integrity',not rows('pragma foreign_key_check'))
