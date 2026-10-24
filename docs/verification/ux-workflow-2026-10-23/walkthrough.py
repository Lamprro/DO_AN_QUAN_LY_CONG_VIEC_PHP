"""Actual MVC HTTP walkthrough against an isolated installation; no test classes or mocks."""
from pathlib import Path
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,html,sqlite3,time,hashlib,subprocess,datetime,sys
import os
M=Path(__file__).parent;MAIN=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
R=Path(os.environ['WM_REVIEW_ROOT']).resolve();assert R!=MAIN.resolve() and (R/'artisan').is_file()
DB=Path(re.search(r'^DB_DATABASE="([^"]+)"',(R/'.env').read_text(),re.M)[1]).resolve();assert DB.is_relative_to(R) and DB!=MAIN/'database/work_management.sqlite'
BASE=os.environ.get('WM_REVIEW_URL','http://127.0.0.1:8126').rstrip('/');assert urllib.parse.urlparse(BASE).hostname in ['127.0.0.1','localhost']
assert 'SESSION_DRIVER=database' in (R/'.env').read_text()
OUT=R/'storage/app/private/ux-replay';OUT.mkdir(parents=True,exist_ok=True);PRIVATE=OUT;stage='experience';journal=[];state={'prefix':'UX'+str(int(time.time()))};prefix=state['prefix']

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
 c=client();form(c,'/login','login',{'email':email,'password':'Demo@123456'});return c
def business():
 return {t:hashlib.sha256(json.dumps(rows('select * from '+t+' order by id'),sort_keys=True).encode()).hexdigest() for t in ['tasks','projects','plans','calendar_events','task_activities','notifications','time_entries','time_entry_adjustments']}
def cur(t):return rows('select * from tasks where id=?',(t,))[0]
def work(c,tp,title,**extra):
 url,body=form(c,tp+'/create','task-create',{'task_type':'WORK','priority':'medium','title':title,**extra});tid=url.rstrip('/').split('/')[-1];verify('task persisted '+title,bool(rows('select id from tasks where id=?',(tid,))));return tid
owner=login('demo1@example.test'); viewer=login('demo5@example.test')
uid=rows("select id from users where email='demo1@example.test'")[0]['id']
url,_=form(owner,'/workspaces/create','workspace-create',{'name':prefix+' Không gian triển khai','timezone':'Asia/Ho_Chi_Minh'})
wid=url.split('/')[-1];wp='/workspaces/'+wid
url,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' Ra mắt sản phẩm','status':'active','start_date':'2026-10-01','deadline':'2027-12-31'})
pid=url.split('/')[-1];pp=wp+'/projects/'+pid;tp=pp+'/tasks'
state.update(wid=wid,pid=pid,wp=wp,pp=pp,tp=tp,uid=uid);checkpoint()
_,body=call(owner,pp)
guide=re.search(r'<section[^>]*data-page="project-guide".*?</section>',body,re.S)[0]
verify('project guide renders four real steps and permission-aware links',len(re.findall('<li>',guide))==4 and '/members' in guide and '/tasks/create' in guide and 'preset=mine' in guide)
first=work(owner,tp,'Chuẩn bị nội dung ra mắt',assignee_id=uid,reviewer_id=uid,estimate_minutes=60,deadline_at='2027-11-18T17:30')
second=work(owner,tp,'Kiểm tra trải nghiệm đăng ký',assignee_id=uid,reviewer_id=uid,estimate_minutes=90)
third=work(owner,tp,'Đối soát dữ liệu và báo cáo',assignee_id=uid,reviewer_id=uid,estimate_minutes=30)
url,_=form(owner,tp+'/create','task-create',{'task_type':'GROUP','title':'Nhóm chuẩn bị phát hành','priority':'medium'});group=url.split('/')[-1]
state.update(tasks=[first,second,third],group=group,browser_task=second);checkpoint()
verify('flow is connected to isolated SQL database',len(rows('select id from tasks where project_id=?',(pid,)))==4)

def active(body):
 side=re.search(r'<aside\b.*?</aside>',body,re.S)[0]
 return [html.unescape(x[1]).strip() for x in re.findall(r'<a\b(?=[^>]*aria-current="page")[^>]*href="([^"]*)"[^>]*>(.*?)</a>',side,re.S)]
for path,label in [(pp,'Tổng quan dự án'),(tp,'Công việc'),(tp+'/'+first,'Công việc'),(tp+'/'+first+'/edit','Công việc'),(tp+'/tree','Cây công việc'),(pp+'/members','Thành viên dự án'),(wp+'/plans/create?project_id='+pid,'Kế hoạch'),(wp+'/calendar/create?project_id='+pid,'Lịch')]:
 _,body=call(owner,path);verify('one active sidebar section: '+label,active(body)==[label],active=active(body))

query={'q':'Chuẩn bị','priority':'medium','view':'board'}
_,body=call(owner,tp+'?'+urllib.parse.urlencode(query))
verify('Kanban filter matches SQL leaf rows and workspace timezone',re.findall(r'data-task-id="([^"]+)"',body)==[first] and '18/11/2027 17:30' in body and 'Asia/Ho_Chi_Minh' in body)
nav=re.search(r'<nav[^>]*class="view-switcher".*?</nav>',body,re.S)[0]
listlink=html.unescape(re.search(r'href="([^"]+)"[^>]*>Danh sách',nav)[1])
_,body=call(owner,listlink);verify('list preserves search and priority when switching',set(re.findall(r'data-task-id="([^"]+)"',body))=={first,group} and 'q=Chu' in listlink and 'priority=medium' in listlink)
_,body=call(owner,tp+'?view=board&q=khongtimthayux');verify('empty board shows zero count and no task cards',not re.findall(r'data-task-id="([^"]+)"',body) and '<strong>0</strong> công việc và cột mốc phù hợp' in body)
_,body=call(owner,tp+'/tree');verify('tree gives unfiltered context notice and current view', 'aria-current="page" >Cây công việc' in body and group in body and first in body)
call(viewer,tp+'?view=board',expected=403)

taskpage=tp+'/'+first
post(owner,taskpage+'/blockers',{'reason':'Cần xác nhận nội dung trước khi bắt đầu'},taskpage)
_,body=call(owner,taskpage)
journey=re.search(r'<section[^>]*data-page="task-journey".*?</section>',body,re.S)[0]
verify('journey explains actual blocker and links to its section','Cần giải quyết blocker đang mở trước.' in journey and 'Vấn đề đang chặn (1)' in journey)
verify('start action is disabled in actual form',bool(re.search(r'value="IN_PROGRESS"[^>]*disabled',body,re.S)))
before=cur(first);post(owner,taskpage+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'},taskpage)
verify('forged transition cannot bypass blocker',cur(first)==before)
bid=rows('select id from task_blockers where task_id=?',(first,))[0]['id']
post(owner,taskpage+'/blockers/'+bid,{'_method':'PATCH','status':'resolved'},taskpage)
for previous,status in [('TODO','IN_PROGRESS'),('IN_PROGRESS','REVIEW'),('REVIEW','DONE')]:
 post(owner,taskpage+'/status',{'_method':'PATCH','status':status,'expected_status':previous},taskpage)
 _,body=call(owner,taskpage)
 labels={'IN_PROGRESS':'Thực hiện','REVIEW':'Chờ duyệt','DONE':'Hoàn thành'}
 current=re.search(r'<li\s+aria-current="step"[^>]*>(.*?)</li>',body,re.S)
 verify('real transition and journey agree: '+status,cur(first)['status']==status and current and labels[status] in current[1])
before=cur(first);post(owner,taskpage+'/status',{'_method':'PATCH','status':'TODO','expected_status':'DONE'},taskpage)
verify('reopen still requires reason',cur(first)==before)
post(owner,taskpage+'/status',{'_method':'PATCH','status':'TODO','expected_status':'DONE','reason':'Bổ sung nội dung sau buổi duyệt'},taskpage)
verify('reopen persisted with audit reason',cur(first)['status']=='TODO' and any('Bổ sung' in (json.loads(r['data'] or '{}').get('reason') or '') for r in rows('select data from task_activities where task_id=?',(first,))))
_,body=call(owner,tp+'/'+group);verify('group uses automatic progress explanation, no manual state stepper','Nhóm tổng hợp trạng thái' in body and 'class="workflow-steps"' not in body)

# Viewer belongs to the seeded demo project; do not grant permissions just for a UI check.
candidate=rows("select p.id,p.workspace_id from projects p join project_members pm on pm.project_id=p.id join workspace_members wm on wm.id=pm.workspace_member_id join users u on u.id=wm.user_id where u.email='demo5@example.test' limit 1")[0]
vp='/workspaces/'+candidate['workspace_id']+'/projects/'+candidate['id'];_,body=call(viewer,vp)
guide=re.search(r'<section[^>]*data-page="project-guide".*?</section>',body,re.S)[0]
verify('viewer guide never offers member management or create/review links','/members' not in guide and '/tasks/create' not in guide and 'preset=review' not in guide)
vt=rows('select id from tasks where project_id=? and archived_at is null limit 1',(candidate['id'],))[0]['id'];_,body=call(viewer,vp+'/tasks/'+vt)
verify('viewer gets explanation without write forms','data-form="task-status"' not in body and 'data-form="task-quick-edit"' not in body and 'data-page="task-journey"' in body)

# Existing edit revision / conflict logic must remain active under the new navigation.
page=tp+'/'+third;tab=login('demo1@example.test');action,stale,_=form(tab,page,'task-quick-edit')
values={'title':'Đối soát dữ liệu đã cập nhật','priority':'high','estimate_minutes':'45','deadline_at':'','assignee_id':uid,'reviewer_id':uid}
form(owner,page,'task-quick-edit',values);verify('quick update really writes revision and values',cur(third)['title']==values['title'] and cur(third)['estimate_minutes']==45)
before=cur(third);_,body=call(tab,action,{**stale,**values,'title':'Bản cũ không được ghi đè'},expected=409)
verify('stale quick edit still offers conflict comparison and preserves DB',cur(third)==before and 'Bản cũ không được ghi đè' in body)
state['browser_url']=BASE+tp+'/'+second;checkpoint()
log('COMPLETE',isolated_database=True,no_test_classes=True,assertions=sum(x.get('verified') is True for x in journal),requests=sum(x['step']=='HTTP' for x in journal))
