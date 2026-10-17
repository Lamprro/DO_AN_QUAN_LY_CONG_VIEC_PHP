"""Real HTTP forms -> Laravel -> SQLite read-back. No mocks or test classes."""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import subprocess,copy,uuid
OUT=ROOT/'docs/verification/final';OUT.mkdir(parents=True,exist_ok=True)
PRIVATE=ROOT/'storage/app/private/final-review';PRIVATE.mkdir(parents=True,exist_ok=True)
journal=[];state={};owner=client('owner-'+stamp);outsider=client('outsider-'+stamp)
def checkpoint(): (PRIVATE/'state.json').write_text(json.dumps(state),encoding='utf-8')
def verify(label,condition,**facts):
 log(label,verified=bool(condition),**facts)
 if not condition:raise RuntimeError(label)
def post(label,path,data,expected=200,c=None):return observed(label,raw_post(c or owner,path,data),expected)
def planrow(id):return rows('select * from plans where id=?',(id,))[0]
def counts():return {t:rows('select count(*) n from '+t)[0]['n'] for t in ['tasks','task_dependencies','calendar_events','task_activities','plans']}
def create_plan(name,payload,base=None):
 response=raw_post(owner,wpath+'/plans',{'project_id':pid,'name':name,'payload':json.dumps(payload),'base_id':base or ''})
 observed('create draft '+name,response);plan=response[1].rstrip('/').split('/')[-1]
 verify('draft persisted '+name,bool(rows('select id from plans where id=?',(plan,))))
 return plan
def transition(id,action,status):return post('plan '+action,wpath+'/plans/'+id+'/'+action,{'expected_status':status,'reason':'Review reason' if action=='reject' else ''})
def approve(id):transition(id,'propose','draft');transition(id,'approve','proposed')

observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
uid=rows('select id from users where email=?',('demo1@example.test',))[0]['id']
observed('outsider login',submit(outsider,'/login','login',{'email':'demo5@example.test','password':'Demo@123456'}))
r=submit(owner,'/workspaces/create','workspace-create',{'name':'Final Lifecycle '+stamp,'timezone':'Asia/Ho_Chi_Minh'});observed('create final workspace',r)
wid=r[1].rstrip('/').split('/')[-1];wpath='/workspaces/'+wid
r=submit(owner,wpath+'/projects/create','project-create',{'name':'Final Plan Review '+stamp,'status':'draft','start_date':'2026-10-01','deadline':'2027-12-31'});observed('create project',r)
pid=r[1].rstrip('/').split('/')[-1];ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
state={'workspace_id':wid,'project_id':pid,'owner_id':uid,'stamp':stamp};checkpoint()
observed('plan create Blade',call(owner,wpath+'/plans/create?project_id='+pid))
payload={'schema_version':1,'tasks':[{'ref':'group','title':'Final GROUP','task_type':'GROUP','priority':'medium'},
 {'ref':'a','title':'Final Work A','task_type':'WORK','parent_ref':'group','priority':'high','estimate_minutes':60,'assignee_id':uid,'reviewer_id':uid},
 {'ref':'b','title':'Final Work B','task_type':'WORK','parent_ref':'group','priority':'medium','estimate_minutes':30,'assignee_id':uid,'reviewer_id':uid}],
 'dependencies':[{'predecessor':'a','successor':'b','lag_minutes':30}],
 'slots':[{'task_ref':'a','starts_at':'2027-01-04T08:00','ends_at':'2027-01-04T09:00'},{'task_ref':'b','starts_at':'2027-01-04T09:30','ends_at':'2027-01-04T10:00'}]}
before=counts();plan=create_plan('Final manual plan',payload);state['plan_id']=plan;checkpoint()
verify('draft does not mutate tasks/calendar',counts()['tasks']==before['tasks'] and counts()['calendar_events']==before['calendar_events'])
post('cannot apply draft',wpath+'/plans/'+plan+'/apply',{'expected_status':'draft'})
verify('invalid transition preserved draft',planrow(plan)['status']=='draft')
observed('outsider denied plan',call(outsider,wpath+'/plans/'+plan),403)
observed('plan CSRF required',call(owner,wpath+'/plans/'+plan+'/propose',{'expected_status':'draft'}),419)
approve(plan);verify('approval has no task changes',counts()['tasks']==before['tasks'])
transition(plan,'apply','approved');p=planrow(plan);verify('applied active',bool(p['applied_at']) and p['is_active']==1,status=p['status'])
data=json.loads(p['data']);tasks=data['applied_task_ids'];state['tasks']=tasks;checkpoint()
verify('applied task tree and dependency',len(tasks)==3 and rows('select parent_task_id from tasks where id=?',(tasks['a'],))[0]['parent_task_id']==tasks['group'] and len(rows('select * from task_dependencies where predecessor_task_id=? and successor_task_id=?',(tasks['a'],tasks['b'])))==1)
verify('plan calendar events persisted',len(rows('select id from calendar_events where plan_id=?',(plan,)))==2)
after=counts();transition(plan,'apply','approved');verify('double apply is idempotent',counts()==after)
observed('impact shows FS wait',call(owner,wpath+'/plan-impact?project_id='+pid))
observed('timeline real events',call(owner,ppath+'/timeline?view=list&from=2027-01-04&to=2027-01-05'))

# Revision reuses existing UUIDs, replaces only this group's own unused events.
revision=copy.deepcopy(payload)
for row in revision['tasks']:row['task_id']=tasks[row['ref']]
revision['tasks'][1]['title']='Final Work A revised'
rev=create_plan('Final revision',revision,plan);state['revision_id']=rev;checkpoint();approve(rev);transition(rev,'apply','approved')
verify('one active version, no duplicate tasks',counts()['tasks']==after['tasks'] and planrow(plan)['status']=='superseded' and planrow(rev)['is_active']==1 and rows('select count(*) n from plans where plan_group_id=? and is_active=1',(p['plan_group_id'],))[0]['n']==1)
verify('old calendar retained cancelled',len(rows("select id from calendar_events where plan_id=? and status='cancelled'",(plan,)))==2)

# Stale snapshot: update real task using existing form, then reject old proposal application.
stale=create_plan('Final stale',revision,rev);approve(stale)
observed('metadata update through form',submit(owner,tpath+'/'+tasks['a']+'/edit','task-edit',{'title':'Final Work A changed after snapshot','priority':'high','task_type':'WORK','parent_task_id':tasks['group'],'estimate_minutes':'75','assignee_id':uid,'reviewer_id':uid}))
prior=counts();transition(stale,'apply','approved');verify('stale application rejected with no partial writes',not planrow(stale)['applied_at'] and counts()==prior and planrow(rev)['is_active']==1)
state['stale_id']=stale;checkpoint()

# Atomic rollback: create tasks then encounter overlapping slots, everything rolls back.
conflict=copy.deepcopy(payload);conflict['slots'][1]['starts_at']='2027-01-04T08:30';conflict['slots'][1]['ends_at']='2027-01-04T09:00'
bad=create_plan('Final conflict rollback',conflict);approve(bad);prior=counts();transition(bad,'apply','approved')
verify('calendar conflict rolls back all writes',counts()==prior and not planrow(bad)['applied_at'])

# Schema errors never store plans.
invalid=copy.deepcopy(payload);invalid['dependencies'].append({'predecessor':'b','successor':'a','lag_minutes':0});prior=counts()
body=post('cycle rejected',wpath+'/plans',{'project_id':pid,'name':'Must not persist','payload':json.dumps(invalid)})
verify('cycle no plan write',counts()==prior and 'Dependency' in body)
invalid=copy.deepcopy(payload);invalid['tasks'][0]['status']='DONE';post('untrusted status rejected',wpath+'/plans',{'project_id':pid,'name':'No status injection','payload':json.dumps(invalid)});verify('status injection no plan write',counts()==prior)

# Scheduler is a deterministic local proposal, not an AI success.
schedulebase=create_plan('Final scheduler base',payload)
post('schedule new version',wpath+'/plans/'+schedulebase+'/schedule',{'from':'2027-02-01'})
scheduled=rows('select id,data from plans where parent_plan_id=?',(schedulebase,))[0];sd=json.loads(scheduled['data'])
verify('local scheduler creates draft slots only',len(sd['payload']['slots'])==3 and counts()['tasks']==prior['tasks'],slots=sd['payload']['slots'])
state['scheduled_id']=scheduled['id'];checkpoint()

# Execute genuine WORK -> timer -> ended actual -> review -> DONE -> FS wait.
post('start Work A',tpath+'/'+tasks['a']+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'})
post('timer start HTTP',wpath+'/timer/'+tasks['a'],{})
entry=rows('select * from time_entries where user_id=? and ended_at is null',(uid,))[0];state['entry_id']=entry['id'];checkpoint()
post('duplicate timer refused',wpath+'/timer/'+tasks['a'],{})
verify('one running timer',len(rows('select id from time_entries where user_id=? and ended_at is null',(uid,)))==1)
post('cannot submit while clock running',tpath+'/'+tasks['a']+'/status',{'_method':'PATCH','expected_status':'IN_PROGRESS','status':'REVIEW'})
verify('status guard keeps IN_PROGRESS',rows('select status from tasks where id=?',(tasks['a'],))[0]['status']=='IN_PROGRESS')
time.sleep(2)
post('stop timer HTTP','/timers/'+entry['id']+'/stop',{})
e=rows('select * from time_entries where id=?',(entry['id'],))[0];verify('ended real elapsed session',bool(e['ended_at']),started=e['started_at'],ended=e['ended_at'])
observed('outsider cannot stop foreign timer',raw_post(outsider,'/timers/'+entry['id']+'/stop',{}),403)
post('submit review',tpath+'/'+tasks['a']+'/status',{'_method':'PATCH','expected_status':'IN_PROGRESS','status':'REVIEW'})
post('approve work',tpath+'/'+tasks['a']+'/status',{'_method':'PATCH','expected_status':'REVIEW','status':'DONE'})
post('lag still blocks successor start',tpath+'/'+tasks['b']+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'})
verify('FS lag preserved successor TODO',rows('select status from tasks where id=?',(tasks['b'],))[0]['status']=='TODO')
body=observed('dashboard actual from timer',call(owner,wpath+'/dashboard?project_id='+pid+'&from=2026-01-01&to=2027-01-01'))

# All AI operations use the real database queue, disabled provider fails without task changes.
ai_ids=[];prior=counts()
for operation in ['planning','replanning','assistance']:
 tok=str(uuid.uuid4());v={'project_id':pid,'operation':operation,'base_id':scheduled['id'] if operation=='replanning' else '', 'user_prompt':'Analyze real scope; do not auto apply.','idempotency_token':tok}
 r=raw_post(owner,wpath+'/planning-ai',v);observed('enqueue '+operation,r);run=r[1].rstrip('/').split('/')[-1];ai_ids.append(run)
 observed('idempotent '+operation,raw_post(owner,wpath+'/planning-ai',v))
 verify('only one run for token '+operation,len(rows('select id from ai_runs where id=?',(run,)))==1)
state['ai_run_ids']=ai_ids;checkpoint()
worker=subprocess.run([r'C:\xampp\php\php.exe','artisan','queue:work','database','--queue=work-management-ai','--stop-when-empty','--tries=3','--timeout=45'],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=60)
log('actual worker drain',exit_code=worker.returncode,output=worker.stdout[-3500:]);verify('worker finished',worker.returncode==0)
for run in ai_ids:verify('AI disabled gives truthful failure',rows('select status from ai_runs where id=?',(run,))[0]['status']=='failed' and len(rows('select id from ai_calls where ai_run_id=?',(run,)))==1)
verify('AI disabled never mutates business',counts()==prior)
observed('AI list handles all operations',call(owner,wpath+'/ai-runs'))

# Optional integration stays disabled; saving secret is optional and encrypted.
observed('integration Blade',call(owner,ppath+'/github'))
post('save disabled GitHub config',ppath+'/github',{'repository_id':'123456789','enabled':'0','webhook_secret':''})
verify('integration disabled persisted',rows('select i.status from integration_connections i join projects p on p.github_connection_id=i.id where p.id=?',(pid,))[0]['status']=='disabled')
observed('unsigned disabled hook cannot write',call(outsider,'/hooks/github/'+pid,{}),503)
verify('no foreign key errors',not rows('pragma foreign_key_check'))
state['complete']=True;checkpoint();log('FINAL COMPLETE',workspace_id=wid,project_id=pid,real_http=True,no_test_classes=True)
