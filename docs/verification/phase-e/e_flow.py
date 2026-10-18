from pathlib import Path
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((R/'docs/verification/p0/p0_http_walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner')")[0])
import datetime,copy
OUT=R/'docs/verification/phase-e';OUT.mkdir(parents=True,exist_ok=True);PRIVATE=R/'storage/app/private/phase-e';PRIVATE.mkdir(parents=True,exist_ok=True);journal=[];state={}
old=json.loads((R/'storage/app/private/p0-review/state.json').read_text());wid=old['workspace_id'];pid=old['project_id'];uid=old['owner_id'];wp='/workspaces/'+wid;pp=wp+'/projects/'+pid;tp=pp+'/tasks';prefix='Eutil'+stamp
def checkpoint(): (PRIVATE/'state.json').write_text(json.dumps(state),encoding='utf-8')
def current(tid):return rows('select * from tasks where id=?',(tid,))[0]
def items(ids):return {'items['+i+']':current(i)['revision'] for i in ids}
def list_ids(body):return re.findall(r'<tr[^>]*data-task-id="([^"]+)"',body)
def worker(queue):
 p=subprocess.run([r'C:\xampp\php\php.exe','artisan','queue:work','database','--queue='+queue,'--stop-when-empty','--tries=3'],cwd=R,capture_output=True,text=True,timeout=60);log('real queue worker '+queue,exit_code=p.returncode,output=p.stdout+p.stderr);verify('worker exited cleanly '+queue,p.returncode==0)
for f in (R/'storage/framework/views').glob('*.php'):
 p=subprocess.run([r'C:\xampp\php\php.exe','-l',str(f)],capture_output=True,text=True)
 if p.returncode:raise RuntimeError(p.stdout+p.stderr)
owner=client('owner');tab_b=client('tab-b');member=client('member');viewer=client('viewer');outsider=client('outsider')
for c,email in [(owner,'demo1@example.test'),(tab_b,'demo1@example.test'),(member,'demo3@example.test'),(viewer,'demo2@example.test'),(outsider,'demo5@example.test')]:observed('login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
env=hashlib.sha256((R/'.env').read_bytes()).hexdigest();pre_tasks=rows('select * from tasks order by id');pre_ids={r['id'] for r in pre_tasks};pre_digest=hashlib.sha256(json.dumps(pre_tasks,sort_keys=True).encode()).hexdigest()
state.update(workspace_id=wid,project_id=pid,prefix=prefix,owner_id=uid);checkpoint()
ids=[];now=datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(hours=7)
for n in range(12):
 values={'title':prefix+' WORK '+str(n),'task_type':'WORK','priority':'medium','estimate_minutes':'45','assignee_id':uid,'reviewer_id':uid}
 if n<3:values['deadline_at']=(now+datetime.timedelta(hours=-24 if n<2 else 12)).strftime('%Y-%m-%dT%H:%M')
 r=submit_live(owner,tp+'/create','task-create',values);observed('create scoped real WORK '+str(n),r);tid=r[1].split('/')[-1];assert rows('select id from tasks where id=?',(tid,));ids.append(tid);state['tasks']=ids;checkpoint()
query='?q='+prefix+'&preset=mine&per_page=10'
b1=observed('mine page1',call(owner,tp+query));b2=observed('mine page2',call(owner,tp+query+'&page=2'));verify('server preset covers multiple pages',len(list_ids(b1))==10 and len(list_ids(b2))==2 and set(list_ids(b1)+list_ids(b2))==set(ids))
observed('real blocker',post(owner,tp+'/'+ids[0]+'/blockers',{'reason':prefix+' blocked input'},tp+'/'+ids[0]));body=observed('blocked preset',call(owner,tp+'?q='+prefix+'&preset=blocked'));verify('blocked query correct',list_ids(body)==[ids[0]])
body=observed('overdue preset',call(owner,tp+'?q='+prefix+'&preset=overdue'));verify('overdue source WORK only',set(list_ids(body))==set(ids[:2]))
for expected,status in [('TODO','IN_PROGRESS'),('IN_PROGRESS','REVIEW')]:observed('real review lifecycle '+status,post(owner,tp+'/'+ids[3]+'/status',{'_method':'PATCH','expected_status':expected,'status':status},tp+'/'+ids[3]))
body=observed('review preset Owner',call(owner,tp+'?q='+prefix+'&preset=review'));verify('review actor query',list_ids(body)==[ids[3]])
body=observed('review preset Member lacking review permission',call(member,tp+'?q='+prefix+'&preset=review'));verify('no unauthorized review result',not list_ids(body))
# Saved filters hold only canonical query values and belong to one actor/scope.
post(owner,pp+'/task-filters',{'name':prefix+' mine','query[q]':prefix,'query[preset]':'mine','query[per_page]':'10','query[password]':'MUST-NOT-STORE'},tp+query)
saved=rows('select * from saved_task_filters where user_id=? and name=?',(uid,prefix+' mine'))[0];sid=saved['id'];state['saved_filter_id']=sid;checkpoint();verify('saved whitelist excludes credentials/unknown fields','password' not in json.loads(saved['query']))
body=observed('open own saved filter',call(owner,pp+'/task-filters/'+sid+'/open'));verify('saved filter reuses server list',len(list_ids(body))==10)
observed('other actor cannot open private filter',call(member,pp+'/task-filters/'+sid+'/open'),404)
secret=json.loads((R/'storage/app/private/p0-review/boundary-state.json').read_text())['private_project_id'];observed('wrong project filter rejected',call(owner,wp+'/projects/'+secret+'/task-filters/'+sid+'/open'),404)
before=len(rows('select id from saved_task_filters'));observed('invalid saved sort refused',post(owner,pp+'/task-filters',{'name':prefix+' bad','query[sort]':'DROP TABLE tasks'},tp));verify('invalid query not stored',len(rows('select id from saved_task_filters'))==before)
# Bulk atomicity uses real posted revisions. Review task update fails late, earlier row rolls back.
before=business();observed('mixed priority batch fails as unit',post(owner,tp+'/bulk',{'action':'priority','priority':'urgent',**items([ids[4],ids[3]])},tp),403);verify('no partial history/task/notifications on failed batch',before==business())
group_response=submit_live(owner,tp+'/create','task-create',{'title':prefix+' GROUP','task_type':'GROUP','priority':'medium'});observed('create GROUP rollback fixture',group_response);group=group_response[1].split('/')[-1];state['group_id']=group;checkpoint()
muid=rows("select id from users where email='demo3@example.test'")[0]['id'];before=business();observed('late invalid GROUP assignment rolls back earlier WORK',post(owner,tp+'/bulk',{'action':'assign','assignee_id':muid,**items([ids[4],group])},tp));verify('late domain failure rolls back task/history/notification',before==business())
observed('priority batch succeeds',post(owner,tp+'/bulk',{'action':'priority','priority':'high',**items([ids[4],ids[5]])},tp));verify('both real rows updated',all(current(i)['priority']=='high' for i in ids[4:6]))
stale=items([ids[4],ids[5]]);observed('advance one revision',post(owner,tp+'/bulk',{'action':'priority','priority':'urgent',**items([ids[4]])},tp));before=business();observed('stale bulk refused',post(owner,tp+'/bulk',{'action':'priority','priority':'low',**stale},tp));verify('stale item rolls back entire batch',before==business())
observed('Viewer bulk denied',post(viewer,tp+'/bulk',{'action':'priority','priority':'low',**items([ids[4]])},tp),403)
muid=rows("select id from users where email='demo3@example.test'")[0]['id'];observed('bulk assign existing eligible member',post(owner,tp+'/bulk',{'action':'assign','assignee_id':muid,**items([ids[6],ids[7]])},tp));verify('assignment via domain service',all(current(i)['assignee_id']==muid for i in ids[6:8]))
if not state.get('foreign_task_id'):
 r=submit_live(owner,wp+'/projects/'+secret+'/tasks/create','task-create',{'title':prefix+' outside selected project','task_type':'WORK','priority':'medium'});observed('create actual cross-project fixture',r);state['foreign_task_id']=r[1].split('/')[-1];assert rows('select id from tasks where id=?',(state['foreign_task_id'],));checkpoint()
before=business();observed('bulk archive rejects foreign scope',post(owner,tp+'/bulk',{'action':'archive',**items([ids[8],old['metadata_task_id']]),'items['+state['foreign_task_id']+']':current(state['foreign_task_id'])['revision']},tp),404);verify('foreign scope cannot partially archive',before==business())
observed('archive own selected row',post(owner,tp+'/bulk',{'action':'archive',**items([ids[8]])},tp));verify('archive domain persisted',current(ids[8])['archived_at'] is not None)
# Deadline reason is mandatory and a separate immutable history event is created.
v={'title':current(ids[2])['title'],'task_type':'WORK','priority':'medium','estimate_minutes':'45','assignee_id':uid,'reviewer_id':uid,'deadline_at':(now+datetime.timedelta(hours=6)).strftime('%Y-%m-%dT%H:%M')}
before=business();observed('deadline change without reason refused',submit_live(owner,tp+'/'+ids[2]+'/edit','task-edit',v));verify('deadline rejection no changes',before==business())
v['deadline_reason']=prefix+' actual deadline decision';observed('deadline with reason',submit_live(owner,tp+'/'+ids[2]+'/edit?fresh=1','task-edit',v));verify('deadline audit recorded',len(rows("select id from task_activities where task_id=? and event_type='task.deadline_changed'",(ids[2],)))==1)
body=observed('deadline insight source and history',call(owner,tp+'/'+ids[2]));verify('human deadline audit visible',v['deadline_reason'] in body and 'điều kiện' in body)
# Only new reminder jobs are queued here; no existing backlog is drained.
assert not rows("select id from jobs where queue='work-management-reminders'")
cmd=[r'C:\xampp\php\php.exe','artisan','tasks:remind','--workspace='+wid,'--user='+uid,'--review-after=0']
p=subprocess.run(cmd,cwd=R,capture_output=True,text=True,timeout=60);log('real immediate-review scan',exit_code=p.returncode,output=p.stdout+p.stderr);assert p.returncode==0
r_before=rows('select * from task_reminders where workspace_id=? and user_id=?',(wid,uid));j_before=rows("select id from jobs where queue='work-management-reminders'");verify('due/review real source queued',any(r['task_id']==ids[0] and r['kind']=='due' for r in r_before) and any(r['task_id']==ids[3] and r['kind']=='review' for r in r_before))
p=subprocess.run(cmd,cwd=R,capture_output=True,text=True,timeout=60);verify('repeat scan dedupe',len(rows('select id from task_reminders where workspace_id=? and user_id=?',(wid,uid)))==len(r_before) and len(rows("select id from jobs where queue='work-management-reminders'"))==len(j_before))
observed('cancel due source before worker',post(owner,tp+'/'+ids[1]+'/status',{'_method':'PATCH','expected_status':'TODO','status':'CANCELLED','reason':prefix+' cancel pending reminder'},tp+'/'+ids[1]));worker('work-management-reminders')
verify('cancelled source skipped',rows('select status from task_reminders where task_id=?',(ids[1],))[0]['status']=='skipped');verify('valid due/review delivered',all(rows('select status from task_reminders where task_id=?',(i,))[0]['status']=='sent' for i in [ids[0],ids[2],ids[3]]))
before_notice=rows("select id from notifications where type like 'task.reminder_%'");subprocess.run(cmd,cwd=R,capture_output=True,text=True,timeout=60);verify('repeat completed scan cannot duplicate notifications',rows("select id from notifications where type like 'task.reminder_%'")==before_notice and not rows("select id from jobs where queue='work-management-reminders'"))
observed('own reminder page',call(owner,wp+'/reminders'));observed('reminder notification filter',call(owner,'/notifications?type=task.reminder_due&filter=unread'))
# Ops is explicitly permission gated and only own source-authorized metadata.
observed('Member cannot open operations',call(member,wp+'/operations'),403)
ai_page=wp+'/ai-runs/create?from=2026-10-01&to=2026-11-01&project_id='+pid
r=submit_live(owner,ai_page,'ai-run-create',{'from':'2026-10-01','to':'2026-11-01','project_id':pid,'user_prompt':prefix+' SECRET PROMPT MUST NOT APPEAR IN OPS'});observed('queue own AI observation source',r);run=r[1].split('/')[-1];state['ai_run_id']=run;checkpoint()
body=observed('Owner operation live job projection',call(owner,wp+'/operations'));verify('ops hides prompt and shows own real pending job','SECRET PROMPT' not in body and 'Job AI đang lưu' in body and 'Chưa được giữ' in body and run in body)
assert len(rows("select id from jobs where queue='work-management-ai'"))==1;worker('work-management-ai');body=observed('operations after worker',call(owner,wp+'/operations'));verify('domain failure not fake success',rows('select status from ai_runs where id=?',(run,))[0]['status']=='failed' and 'Thất bại' in body)
# F10 starts/stops two real sessions, then edits only those newly created review records.
clock_task=ids[11];observed('start correction WORK',post(owner,tp+'/'+clock_task+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'},tp+'/'+clock_task))
entries=[]
for n in range(2):
 observed('real timer start '+str(n),post(owner,wp+'/timer/'+clock_task,{},tp+'/'+clock_task));active=rows('select * from time_entries where task_id=? and ended_at is null',(clock_task,))[0];observed('running entry cannot be adjusted',call(owner,'/timers/'+active['id']+'/adjust'),403);time.sleep(2);observed('real timer stop '+str(n),submit_live(owner,tp+'/'+clock_task,'global-timer-stop',{}));entries.append(active['id']);state['entries']=entries;checkpoint();time.sleep(1)
entry=rows('select * from time_entries where id=?',(entries[0],))[0];second=rows('select * from time_entries where id=?',(entries[1],))[0];path='/timers/'+entries[0]+'/adjust'
def localstamp(raw):return (datetime.datetime.fromisoformat(raw.replace('Z','+00:00')).replace(tzinfo=None)+datetime.timedelta(hours=7)).strftime('%Y-%m-%dT%H:%M:%S')
body=observed('B opens audit form',call(tab_b,path));_,bdata=formdata(body,'time-adjust');base={'started_at':localstamp(entry['started_at']),'ended_at':localstamp(entry['ended_at']),'timezone':'Asia/Ho_Chi_Minh','note':prefix+' adjustment audit','reason':prefix+' verify own captured timer correction'}
before=rows('select * from time_entries where id=?',(entries[0],));audit_count=len(rows('select id from time_entry_adjustments'))
observed('overlap correction refused',submit_live(owner,path,'time-adjust',{**base,'started_at':localstamp(second['started_at']),'ended_at':localstamp(second['ended_at'])}));verify('overlap no timer/audit change',rows('select * from time_entries where id=?',(entries[0],))==before and len(rows('select id from time_entry_adjustments'))==audit_count)
observed('foreign actor cannot open own-hour editor',call(member,path),403)
dash=wp+'/dashboard?from=2026-10-01&to=2026-11-01&project_id='+pid;r=submit_live(owner,dash,'report-create',{});observed('snapshot before timer correction',r);snapshot=r[1].split('/')[-1];raw=rows('select metrics_json from report_snapshots where id=?',(snapshot,))[0]['metrics_json'];state['snapshot_id']=snapshot;checkpoint()
reduced=datetime.datetime.fromisoformat(entry['ended_at'])-datetime.timedelta(seconds=1);base['ended_at']=localstamp(reduced.isoformat(' '));observed('A valid audited correction',submit_live(owner,path+'?fresh=1','time-adjust',base));verify('one append-only audit and source changed',len(rows('select id from time_entry_adjustments'))==audit_count+1 and rows('select ended_at from time_entries where id=?',(entries[0],))[0]['ended_at']!=entry['ended_at'])
body=observed('B stale correction refused',call(tab_b,'/timers/'+entries[0]+'/adjust',{**bdata,**base,'note':prefix+' MUST NOT OVERWRITE','_method':'PATCH'}));verify('stale keeps input without extra audit','Phiên đã thay đổi' in body and 'MUST NOT OVERWRITE' in body and len(rows('select id from time_entry_adjustments'))==audit_count+1)
verify('old snapshot immutable after correction',rows('select metrics_json from report_snapshots where id=?',(snapshot,))[0]['metrics_json']==raw)
r=submit_live(owner,dash,'report-create',{});observed('new metrics after correction',r);after=json.loads(rows('select metrics_json from report_snapshots where id=?',(r[1].split('/')[-1],))[0]['metrics_json']);verify('new actual follows corrected source',after['period_metrics']['actual_minutes']<json.loads(raw)['period_metrics']['actual_minutes'])
verify('old tasks unchanged by utility review',hashlib.sha256(json.dumps([r for r in rows('select * from tasks order by id') if r['id'] in pre_ids],sort_keys=True).encode()).hexdigest()==pre_digest)
verify('env/key/flags unchanged',env==hashlib.sha256((R/'.env').read_bytes()).hexdigest());verify('foreign key check',not rows('pragma foreign_key_check'));state['complete']=True;checkpoint();log('phase E complete',real_http=True,no_test_classes=True)
