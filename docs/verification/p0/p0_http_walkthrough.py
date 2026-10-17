"""Live MVC walkthrough: real sessions/CSRF, rendered forms, SQLite read-back. No test classes."""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import hashlib,copy,subprocess
OUT=ROOT/'docs/verification/p0';OUT.mkdir(parents=True,exist_ok=True);PRIVATE=ROOT/'storage/app/private/p0-review';PRIVATE.mkdir(parents=True,exist_ok=True);journal=[];state={}
def verify(label,condition,**facts):
 log(label,verified=bool(condition),**facts)
 if not condition:raise RuntimeError(label)
def checkpoint(): (PRIVATE/'state.json').write_text(json.dumps(state),encoding='utf-8')
def attrs(tag):return {k:html.unescape(v) for k,v in re.findall(r'([a-zA-Z_:-]+)="([^"]*)"',tag)}
def formdata(body,marker):
 match=re.search(r'<form\b(?=[^>]*data-form="'+re.escape(marker)+r'")[^>]*>(.*?)</form>',body,re.S)
 if not match:raise RuntimeError('Missing real form '+marker)
 payload={}
 for tag in re.findall(r'<input\b[^>]*>',match[1]):
  a=attrs(tag)
  if a.get('type')=='hidden' and 'name' in a:payload[a['name']]=a.get('value','')
 return html.unescape(attrs(match[0])['action']),payload
def submit_live(c,page,marker,values):
 status,url,body=call(c,page)
 if status!=200:raise RuntimeError('Cannot open '+page+' HTTP '+str(status))
 action,data=formdata(body,marker);data.update(values);return call(c,action,data)
def post(c,path,values,page=None):
 code,url,body=call(c,page or '/workspaces')
 if code!=200:raise RuntimeError('Cannot read CSRF page')
 return call(c,path,{'_token':token(body),**values})
def business():
 result={}
 for table in ['tasks','projects','plans','task_dependencies','calendar_events','task_activities','notifications','time_entries']:
  data=rows('select * from '+table+' order by id');result[table]={'count':len(data),'sha256':hashlib.sha256(json.dumps(data,sort_keys=True).encode()).hexdigest()}
 return result
owner=client('owner');other_tab=client('owner-tab-b');viewer=client('viewer');outsider=client('outsider')
env_hash=hashlib.sha256((ROOT/'.env').read_bytes()).hexdigest();existing_task_ids={x['id'] for x in rows('select id from tasks')};existing_digest=hashlib.sha256(json.dumps(rows('select * from tasks order by id'),sort_keys=True).encode()).hexdigest()
for p in (ROOT/'storage/framework/views').glob('*.php'):
 r=subprocess.run([r'C:\xampp\php\php.exe','-l',str(p)],capture_output=True,text=True)
 if r.returncode:raise RuntimeError(str(p)+r.stdout+r.stderr)
log('compiled Blade syntax checked',files=len(list((ROOT/'storage/framework/views').glob('*.php'))))
for c,email in [(owner,'demo1@example.test'),(other_tab,'demo1@example.test'),(viewer,'demo2@example.test'),(outsider,'demo5@example.test')]:observed('login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
uid=rows("select id from users where email='demo1@example.test'")[0]['id']
r=submit_live(owner,'/workspaces/create','workspace-create',{'name':'P0 UX Review '+stamp,'timezone':'Asia/Ho_Chi_Minh'});observed('create workspace',r);wid=r[1].rstrip('/').split('/')[-1];wpath='/workspaces/'+wid
r=submit_live(owner,wpath+'/projects/create','project-create',{'name':'P0 Project '+stamp,'status':'draft','start_date':'2026-10-01','deadline':'2027-12-31'});observed('create project',r);pid=r[1].rstrip('/').split('/')[-1];ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';state.update(workspace_id=wid,project_id=pid,owner_id=uid,stamp=stamp);checkpoint()

# Real two-tab edits with revisions independent of timestamp granularity.
body=observed('tab B opens project editor',call(other_tab,ppath+'/edit'));_,bform=formdata(body,'project-edit');verify('initial revision field present',bform.get('expected_revision')=='1')
observed('tab A saves project',submit_live(owner,ppath+'/edit','project-edit',{'name':'P0 Project changed A','status':'draft','start_date':'2026-10-01','deadline':'2027-12-31'}))
r=call(other_tab,ppath,{**bform,'name':'MUST NOT overwrite project','status':'draft'});body=observed('stale B project save',r)
verify('stale project refused, input preserved','Dữ liệu đã được cập nhật' in body and 'MUST NOT overwrite project' in body and rows('select name,revision from projects where id=?',(pid,))[0]=={'name':'P0 Project changed A','revision':2})
observed('open fresh project state',call(other_tab,ppath+'/edit?fresh=1'))
r=submit_live(owner,tpath+'/create','task-create',{'title':'P0 metadata task','priority':'medium','task_type':'WORK','estimate_minutes':'45','assignee_id':uid,'reviewer_id':uid});observed('create metadata task',r);tid=r[1].rstrip('/').split('/')[-1];state['metadata_task_id']=tid;checkpoint()
body=observed('tab B opens task editor',call(other_tab,tpath+'/'+tid+'/edit'));_,bt=formdata(body,'task-edit')
observed('A metadata save first',submit_live(owner,tpath+'/'+tid+'/edit','task-edit',{'title':'P0 metadata first A','priority':'high','task_type':'WORK','estimate_minutes':'60','assignee_id':uid,'reviewer_id':uid}))
body=observed('B metadata save stale',call(other_tab,tpath+'/'+tid,{**bt,'title':'P0 second B must stay in form','priority':'medium','task_type':'WORK','estimate_minutes':'90'}))
verify('task revision and old input preserved',rows('select title,estimate_minutes,revision from tasks where id=?',(tid,))[0]=={'title':'P0 metadata first A','estimate_minutes':60,'revision':2} and 'P0 second B must stay in form' in body and 'Dữ liệu đã được cập nhật' in body)
observed('fresh task restores latest value',call(other_tab,tpath+'/'+tid+'/edit?fresh=1'))

# Visual-editor payload is submitted to the same native MVC form endpoint.
editor_page=wpath+'/plans/create?project_id='+pid
body=observed('visual plan editor',call(owner,editor_page));verify('no JSON/UUID required as main interface','id="editor-tasks"' in body and 'Thêm công việc' in body and 'Người duyệt' in body)
payload={'schema_version':1,'tasks':[{'ref':'g','title':'P0 GROUP','task_type':'GROUP','priority':'medium'}, {'ref':'a','title':'P0 WORK A','task_type':'WORK','parent_ref':'g','priority':'high','estimate_minutes':60,'assignee_id':uid,'reviewer_id':uid}, {'ref':'b','title':'P0 WORK B','task_type':'WORK','parent_ref':'g','priority':'medium','estimate_minutes':30,'assignee_id':uid,'reviewer_id':uid}], 'dependencies':[{'predecessor':'a','successor':'b','lag_minutes':0}], 'slots':[{'task_ref':'a','starts_at':'2027-04-05T08:00','ends_at':'2027-04-05T09:00'},{'task_ref':'b','starts_at':'2027-04-05T09:00','ends_at':'2027-04-05T09:30'}]}
invalid=copy.deepcopy(payload);invalid['tasks'][1]['title']='';before=business()
body=observed('editor inline validation',post(owner,wpath+'/plans',{'name':'Bad editor input','project_id':pid,'input_mode':'editor','editor_enabled':'1','payload':json.dumps(invalid)},editor_page))
verify('field error maps to stable row and input kept','editor.tasks.1.title' in body and 'P0 WORK B' in body and before==business())
malformed=copy.deepcopy(payload);malformed['tasks']=['wrong row scalar']
body=observed('malformed editor draft fallback',post(owner,wpath+'/plans',{'name':'Bad structure retained','project_id':pid,'input_mode':'editor','editor_enabled':'1','payload':json.dumps(malformed)},editor_page))
verify('malformed draft retained without 500','wrong row scalar' in body and 'Cấu trúc bản nhập' in body and before==business())
r=post(owner,wpath+'/plans',{'name':'P0 visual-editor proposal','project_id':pid,'input_mode':'editor','editor_enabled':'1','payload':json.dumps(payload)},editor_page);observed('save plan from editor contract',r);plan=r[1].rstrip('/').split('/')[-1];state['plan_id']=plan;checkpoint()
verify('draft only, no task/event writes',rows('select status from plans where id=?',(plan,))[0]['status']=='draft' and business()['tasks']==before['tasks'] and business()['calendar_events']==before['calendar_events'])
before=business();body=observed('preview draft through real apply simulation',call(owner,wpath+'/plans/'+plan+'/preview'))
verify('preview valid and fully rolled back',before==business() and 'Mô phỏng hợp lệ' in body and 'P0 WORK A' in body,counts={k:v['count'] for k,v in before.items()})
for action,status in [('propose','draft'),('approve','proposed')]:observed('plan '+action,post(owner,wpath+'/plans/'+plan+'/'+action,{'expected_status':status},wpath+'/plans/'+plan))
before=business();body=observed('approved preview has real apply form',call(owner,wpath+'/plans/'+plan+'/preview'));verify('preview still no persisted changes',before==business() and 'data-form="plan-apply"' in body)
observed('apply plan',submit_live(owner,wpath+'/plans/'+plan+'/preview','plan-apply',{}));p=rows('select * from plans where id=?',(plan,))[0];mapped=json.loads(p['data'])['applied_task_ids'];state['tasks']=mapped;checkpoint()
verify('real apply tree dependency and order',p['is_active']==1 and rows('select parent_task_id,sort_order from tasks where id=?',(mapped['a'],))[0]=={'parent_task_id':mapped['g'],'sort_order':1} and len(rows('select id from task_dependencies where predecessor_task_id=? and successor_task_id=?',(mapped['a'],mapped['b'])))==1)
before=business();observed('replay apply stays idempotent',post(owner,wpath+'/plans/'+plan+'/apply',{'expected_status':'approved'},wpath+'/plans/'+plan));verify('apply replay creates no duplicates',before==business())

# Existing tasks receive internally supplied revisions; preview's revision/history changes roll back.
revision=copy.deepcopy(payload)
for row in revision['tasks']:row['task_id']=mapped[row['ref']]
revision['tasks'][1]['title']='P0 WORK A revised';revision['tasks'][1]['estimate_minutes']=75
r=post(owner,wpath+'/plans',{'name':'P0 revision existing tasks','project_id':pid,'base_id':plan,'payload':json.dumps(revision)},editor_page);observed('revision draft',r);rev=r[1].rstrip('/').split('/')[-1];state['revision_id']=rev;checkpoint()
body=observed('human readable revision diff',call(owner,wpath+'/plans/'+rev));verify('diff shows before after by task identity','P0 WORK A revised' in body and 'Ước lượng (phút)' in body and 'Có thay đổi' in body)
before=business();body=observed('preview existing task update',call(owner,wpath+'/plans/'+rev+'/preview'));verify('existing revisions/history not persisted by preview',before==business() and 'Mô phỏng hợp lệ' in body)
for action,status in [('propose','draft'),('approve','proposed'),('apply','approved')]:observed('revision '+action,post(owner,wpath+'/plans/'+rev+'/'+action,{'expected_status':status},wpath+'/plans/'+rev))
verify('real revision reuses UUID and increments edit revision',rows('select title,revision from tasks where id=?',(mapped['a'],))[0]['title']=='P0 WORK A revised' and rows('select revision from tasks where id=?',(mapped['a'],))[0]['revision']>1 and len(rows('select id from tasks where workspace_id=?',(wid,)))==4)

# Blockers/timers/dependencies are explained before and enforced during mutation.
observed('create blocker',post(owner,tpath+'/'+mapped['a']+'/blockers',{'reason':'P0 real blocker'},tpath+'/'+mapped['a']))
body=observed('blocked task action explains blocker',call(owner,tpath+'/'+mapped['a']));verify('blocked start is unavailable','Cần giải quyết blocker' in body and re.search(r'value="IN_PROGRESS"[^>]*disabled',body) is not None)
observed('forged blocked start still refused',post(owner,tpath+'/'+mapped['a']+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'},tpath+'/'+mapped['a']));verify('blocked task remains TODO',rows('select status from tasks where id=?',(mapped['a'],))[0]['status']=='TODO')
blocker=rows("select id from task_blockers where task_id=? and status='open'",(mapped['a'],))[0]['id']
observed('resolve blocker',post(owner,tpath+'/'+mapped['a']+'/blockers/'+blocker,{'_method':'PATCH','status':'resolved'},tpath+'/'+mapped['a']))
observed('start real work',post(owner,tpath+'/'+mapped['a']+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'},tpath+'/'+mapped['a']))
observed('start real HTTP timer',post(owner,wpath+'/timer/'+mapped['a'],{},tpath+'/'+mapped['a']))
entry=rows('select * from time_entries where user_id=? and ended_at is null',(uid,))[0];state['time_entry_id']=entry['id'];checkpoint()
body=observed('global timer on a different page',call(owner,ppath));verify('global clock uses server start time','data-global-timer' in body and entry['id']+'/stop' in body and 'P0 WORK A revised' in body)
body=observed('timer blocks review action',call(owner,tpath+'/'+mapped['a']));verify('running clock explained before submit','Hãy dừng timer' in body and re.search(r'value="REVIEW"[^>]*disabled',body) is not None)
time.sleep(2);observed('global timer stop through native form',submit_live(owner,ppath,'global-timer-stop',{}));verify('real timer ended',rows('select ended_at from time_entries where id=?',(entry['id'],))[0]['ended_at'] is not None)
for status,expected in [('REVIEW','IN_PROGRESS'),('DONE','REVIEW')]:observed('workflow '+status,post(owner,tpath+'/'+mapped['a']+'/status',{'_method':'PATCH','expected_status':expected,'status':status},tpath+'/'+mapped['a']))
observed('successor may start after FS completed',post(owner,tpath+'/'+mapped['b']+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'},tpath+'/'+mapped['b']))
verify('real FS successor started',rows('select status from tasks where id=?',(mapped['b'],))[0]['status']=='IN_PROGRESS')

# Reviewer/Viewer/outsider boundaries and scope-aware navigation.
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
body=observed('invite Viewer',submit_live(owner,wpath+'/members','invitation-create',{'email':'demo2@example.test','role_id':roles['Viewer']}));invite=html.unescape(re.search(r'data-invitation-url="([^"]+)"',body)[1]);observed('Viewer accepts',submit_live(viewer,invite,'invitation-accept',{}))
vuid=rows("select id from users where email='demo2@example.test'")[0]['id'];wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,vuid))[0]['id'];observed('project Viewer membership',submit_live(owner,ppath+'/members','project-member-create',{'workspace_member_id':wm,'role_id':roles['Viewer'],'status':'active'}))
body=observed('Viewer task and shell',call(viewer,tpath+'/'+mapped['a']));verify('Viewer cannot see write/admin controls','data-form="task-status"' not in body and 'Thành viên workspace' not in body and 'Tạo task' not in body)
observed('Viewer cannot open plan editor',call(viewer,editor_page),403);observed('Viewer cannot simulate privileged apply',call(viewer,wpath+'/plans/'+rev+'/preview'),403);observed('outsider cannot open workspace',call(outsider,wpath),403)
observed('missing task expected revision rejected',post(owner,tpath+'/'+tid,{'_method':'PUT','title':'No revision overwrite','priority':'medium'},tpath+'/'+tid+'/edit'))
verify('missing revision cannot bypass guard',rows('select title from tasks where id=?',(tid,))[0]['title']=='P0 metadata first A')
verify('migration did not modify existing task rows',hashlib.sha256(json.dumps([r for r in rows('select * from tasks order by id') if r['id'] in existing_task_ids],sort_keys=True).encode()).hexdigest()==existing_digest)
verify('APP_KEY/env unchanged',hashlib.sha256((ROOT/'.env').read_bytes()).hexdigest()==env_hash)
verify('foreign key integrity',not rows('pragma foreign_key_check'));state['complete']=True;checkpoint();log('P0 COMPLETE',real_http=True,no_test_classes=True)
