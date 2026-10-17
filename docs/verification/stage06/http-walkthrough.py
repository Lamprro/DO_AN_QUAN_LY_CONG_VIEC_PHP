"""Real MVC forms + SQLite read-back + actual Laravel queue worker. No TestCase/mocks/reset."""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import subprocess,hashlib,csv,io
OUT=ROOT/'docs/verification/stage06';OUT.mkdir(parents=True,exist_ok=True)
PRIVATE=ROOT/'storage/app/private/stage06-review';PRIVATE.mkdir(parents=True,exist_ok=True)
journal=[];state={};owner=client('owner-'+stamp);worker=client('worker-'+stamp);reviewer=client('reviewer-'+stamp);outsider=client('outsider-'+stamp)
def verify(label,condition,**facts):
    log(label,verified=bool(condition),**facts)
    if not condition:raise RuntimeError(label)
def checkpoint():PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
def field(body,name):return html.unescape(re.search(r'name="'+re.escape(name)+r'"\s+value="([^"]*)"',body)[1])
def count(table,where='',args=()):return rows('select count(*) n from '+table+(' where '+where if where else ''),args)[0]['n']
observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
r=submit(owner,'/workspaces/create','workspace-create',{'name':'Phase6 Report Review '+stamp,'timezone':'Asia/Ho_Chi_Minh'});observed('create workspace',r)
wid=r[1].rstrip('/').split('/')[-1];wpath='/workspaces/'+wid
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
r=submit(owner,wpath+'/projects/create','project-create',{'name':'P6 Visible Project '+stamp,'status':'draft','start_date':'2026-10-01','deadline':'2026-12-31'});observed('create project',r)
pid=r[1].rstrip('/').split('/')[-1];ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';uids={};wms={};pms={}
for c,label,role in [(worker,'worker','Member'),(reviewer,'reviewer','Manager')]:
    email='phase6.'+label+'.'+stamp+'@example.test'
    observed('register '+label,submit(c,'/register','register',{'name':'=P6 Worker' if label=='worker' else 'P6 Reviewer','email':email,'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}))
    uids[label]=rows('select id from users where email=?',(email,))[0]['id']
    b=observed('invite '+label,submit(owner,wpath+'/members','invitation-create',{'email':email,'role_id':roles[role]}));link=html.unescape(re.search(r'data-invitation-url="([^"]+)"',b)[1])
    observed('accept '+label,submit(c,link,'invitation-accept',{}));wms[label]=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,uids[label]))[0]['id']
    observed('project member '+label,submit(owner,ppath+'/members','project-member-create',{'workspace_member_id':wms[label],'role_id':roles[role],'status':'active'}))
    pms[label]=rows('select id from project_members where project_id=? and workspace_member_id=?',(pid,wms[label]))[0]['id']
observed('outsider login',submit(outsider,'/login','login',{'email':'demo5@example.test','password':'Demo@123456'}))
state={'stamp':stamp,'workspace_id':wid,'project_id':pid,'uids':uids,'wms':wms,'pms':pms};checkpoint()
def create_task(title,**extra):
    values={'title':title,'priority':'medium','task_type':'WORK','estimate_minutes':'60','assignee_id':uids['worker'],'reviewer_id':uids['reviewer'],**extra}
    r=submit(owner,tpath+'/create','task-create',values);observed('create '+title,r);tid=r[1].rstrip('/').split('/')[-1]
    verify('task exists '+title,bool(rows('select id from tasks where id=?',(tid,))));return tid
group=create_task('P6 GROUP',task_type='GROUP',estimate_minutes='',assignee_id='',reviewer_id='')
a=create_task('P6 Work A',parent_task_id=group);b=create_task('P6 Work B',estimate_minutes='120');c=create_task('P6 Work Unknown',estimate_minutes='',assignee_id='',deadline_at='2026-01-01T09:00');cancelled=create_task('P6 Cancelled',estimate_minutes='500');milestone=create_task('P6 Milestone',task_type='MILESTONE',estimate_minutes='')
current='TODO';observed('cancel actual task',raw_post(owner,tpath+'/'+cancelled+'/status',{'_method':'PATCH','expected_status':current,'status':'CANCELLED','reason':'Excluded from active workload'}))
slots=[]
for title,task,start,end,visibility in [('P6 Public A',a,'2026-10-20T23:30','2026-10-21T00:30','project'),('P6 Public B',b,'2026-10-21T00:30','2026-10-21T01:30','project'),('P6 Private B',b,'2026-10-21T13:00','2026-10-21T14:00','private')]:
    r=submit(worker,wpath+'/calendar/create','calendar-save',{'title':title,'event_type':'TASK_BLOCK','project_id':pid,'task_id':task,'starts_at':start,'ends_at':end,'timezone':'Asia/Ho_Chi_Minh','all_day':'0','is_busy':'1','visibility':visibility,'status':'planned'});observed('create slot '+title,r);slots.append(r[1].rstrip('/').split('/')[-1])
for actor,to in [(worker,'IN_PROGRESS'),(worker,'REVIEW'),(reviewer,'DONE')]:
    status=rows('select status from tasks where id=?',(a,))[0]['status'];observed('status A '+to,raw_post(actor,tpath+'/'+a+'/status',{'_method':'PATCH','status':to,'expected_status':status,'reason':''}))
state.update(tasks={'a':a,'b':b,'c':c,'cancelled':cancelled,'group':group,'milestone':milestone},slots=slots);checkpoint()
filters={'from':'2026-10-01','to':'2026-11-01','project_id':pid,'subject_user_id':'','include_archived':'0'}
dash=wpath+'/dashboard?'+urllib.parse.urlencode(filters)
body=observed('dashboard real form and current metrics',call(owner,dash));verify('WORK only inventory','data-work-count="4"' in body)
r=submit(owner,dash,'report-create',filters);observed('snapshot real POST',r);sid=r[1].rstrip('/').split('/')[-1];state['snapshot_id']=sid;checkpoint()
saved=rows('select * from report_snapshots where id=?',(sid,))[0];m=json.loads(saved['metrics_json']);original=saved['metrics_json'];state['original_metrics']=original;checkpoint()
verify('server metrics match known data',m['inventory']['work_count']==4 and m['inventory']['status_counts']=={'TODO':2,'IN_PROGRESS':0,'REVIEW':0,'DONE':1,'CANCELLED':1} and m['inventory']['estimate_minutes']==180 and m['inventory']['estimate_unknown']==1 and m['inventory']['overdue']==1 and m['period_metrics']['completed_work']==1 and m['period_metrics']['planned_minutes']==120 and m['period_metrics']['actual_minutes'] is None,inventory=m['inventory'],period=m['period_metrics'])
verify('no GROUP/MILESTONE/cancel estimate double count',set(m['sources']['task_ids'])=={a,b,c,cancelled} and m['inventory']['progress']['method']=='count' and m['inventory']['progress']['percent']==33.3)
verify('private calendar source excluded for Owner',slots[2] not in m['sources']['calendar_event_ids'] and len(m['sources']['calendar_event_ids'])==2)
observed('member sees live dashboard',call(worker,dash));observed('member cannot create snapshot',raw_post(worker,wpath+'/reports',filters),expected=403)
observed('another recipient cannot view snapshot',call(reviewer,wpath+'/reports/'+sid),expected=403)
observed('outside workspace cannot view dashboard',call(outsider,dash),expected=403)
observed('print view',call(owner,wpath+'/reports/'+sid+'/print'))
export=call(owner,wpath+'/reports/'+sid+'/export');observed('CSV real export',export);csv_initial=export[2]
csv_rows=list(csv.reader(io.StringIO(csv_initial.lstrip('\ufeff'))))
verify('CSV prevents formula evaluation',any(row and row[0]=="'=P6 Worker" for row in csv_rows) and ['WORK DONE','1'] in csv_rows)
observed('snapshot unsupported PATCH',raw_post(owner,wpath+'/reports/'+sid,{'_method':'PATCH','metrics_json':'{}'}),expected=405)
before=count('report_snapshots','workspace_id=?',(wid,));observed('client metrics injection rejected',raw_post(owner,wpath+'/reports',{**filters,'metrics_json':'{}'}));verify('injection no snapshot',count('report_snapshots','workspace_id=?',(wid,))==before)
# Half-open period intersects only 30 minutes of A; B begins outside the requested day.
clip={**filters,'from':'2026-10-20','to':'2026-10-21'}
r=raw_post(owner,wpath+'/reports',clip);observed('clipped period snapshot',r);clip_id=r[1].rstrip('/').split('/')[-1];clip_metrics=json.loads(rows('select metrics_json from report_snapshots where id=?',(clip_id,))[0]['metrics_json']);verify('UTC half-open clipping correct',clip_metrics['period_metrics']['planned_minutes']==30,period=clip_metrics['period'])
observed('change source estimate through task form',submit(owner,tpath+'/'+b+'/edit','task-edit',{'title':'P6 Work B revised','priority':'medium','task_type':'WORK','estimate_minutes':'180','assignee_id':uids['worker'],'reviewer_id':uids['reviewer']}))
verify('snapshot JSON byte-for-byte unchanged after source mutation',rows('select metrics_json from report_snapshots where id=?',(sid,))[0]['metrics_json']==original)
verify('CSV export unchanged after source mutation',call(owner,wpath+'/reports/'+sid+'/export')[2]==csv_initial)
r=raw_post(owner,wpath+'/reports',filters);observed('new snapshot reflects new data',r);new_sid=r[1].rstrip('/').split('/')[-1];verify('new estimate 240',json.loads(rows('select metrics_json from report_snapshots where id=?',(new_sid,))[0]['metrics_json'])['inventory']['estimate_minutes']==240)
state['new_snapshot_id']=new_sid;checkpoint()
# A project not shared with worker must not leak into their workspace aggregate.
r=submit(owner,wpath+'/projects/create','project-create',{'name':'P6 SECRET PROJECT','status':'draft'});observed('create separate private project',r);secret_pid=r[1].rstrip('/').split('/')[-1]
r=submit(owner,wpath+'/projects/'+secret_pid+'/tasks/create','task-create',{'title':'P6 Secret Work','priority':'medium','task_type':'WORK','estimate_minutes':'777'});observed('create secret work',r)
worker_dash=wpath+'/dashboard?from=2026-10-01&to=2026-11-01';body=observed('aggregate scoped by current project permissions',call(worker,worker_dash));verify('hidden project name and task absent','P6 SECRET PROJECT' not in body and '777' not in body and 'data-work-count="4"' in body)
observed('explicit forbidden project',call(worker,worker_dash+'&project_id='+secret_pid),expected=403)
foreign_project=rows('select id from projects where workspace_id<>? limit 1',(wid,))[0]['id'];observed('cross workspace filter project',call(owner,worker_dash+'&project_id='+foreign_project),expected=404)
# Queue foundation: real unconfigured failure, retries, cancellation, and reauthorization.
def request_ai(actor,prompt,token=None):
    body=observed('AI request form',call(actor,wpath+'/ai-runs/create?'+urllib.parse.urlencode(filters)))
    value=token or field(body,'idempotency_token');r=submit(actor,wpath+'/ai-runs/create?'+urllib.parse.urlencode(filters),'ai-run-create',{**filters,'idempotency_token':value,'user_prompt':prompt});observed('queue AI report request',r)
    rid=r[1].rstrip('/').split('/')[-1];return rid,value
def work(label):
    p=subprocess.run(['php','artisan','queue:work','database','--queue=work-management-ai','--stop-when-empty','--sleep=1','--tries=3','--timeout=45','--no-ansi'],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=55)
    log(label,exit_code=p.returncode,worker_output=p.stdout+p.stderr);verify(label+' worker exited',p.returncode==0)
before_tasks=count('tasks','workspace_id=?',(wid,));before_events=count('calendar_events','workspace_id=?',(wid,));before_plans=count('plans','workspace_id=?',(wid,));failed_before=count('failed_jobs')
rid,itoken=request_ai(owner,'P6 Nhận xét số liệu, chưa có cấu hình nhà cung cấp');state['run_id']=rid;state['idempotency_token']=itoken;checkpoint()
verify('AiRun and queue job committed, no synchronous provider call',rows('select status from ai_runs where id=?',(rid,))[0]['status']=='queued' and count('ai_calls','ai_run_id=?',(rid,))==0 and count('jobs',"queue='work-management-ai'")==1)
repeat=raw_post(owner,wpath+'/ai-runs',{**filters,'idempotency_token':itoken,'user_prompt':'P6 Nhận xét số liệu, chưa có cấu hình nhà cung cấp'});observed('repeat same submission',repeat)
verify('idempotency reused run and single job',repeat[1].endswith('/'+rid) and count('jobs',"queue='work-management-ai'")==1)
observed('same token different request rejected',raw_post(owner,wpath+'/ai-runs',{**filters,'idempotency_token':itoken,'user_prompt':'DIFFERENT'}));verify('different payload did not enqueue',count('jobs',"queue='work-management-ai'")==1)
work('worker consumes unconfigured request')
run=rows('select status,error_message,started_at,finished_at from ai_runs where id=?',(rid,))[0];calls=rows('select attempt_no,status,input_tokens,output_tokens,response_payload from ai_calls where ai_run_id=?',(rid,))
verify('honest terminal error and call audit, no fabricated tokens/result',run['status']=='failed' and run['started_at'] is not None and run['finished_at'] is not None and len(calls)==1 and calls[0]['status']=='failed' and calls[0]['input_tokens'] is None and calls[0]['output_tokens'] is None and calls[0]['response_payload'] is None,run=run,calls=calls)
body=observed('AI failed status Blade',call(owner,wpath+'/ai-runs/'+rid));verify('error visible and no success text','data-ai-status="failed"' in body and 'chưa gọi nhà cung cấp' in body.lower())
for attempt in [2,3]:
    observed('user retry '+str(attempt),submit(owner,wpath+'/ai-runs/'+rid,'ai-run-retry',{'expected_status':'failed'}));work('retry worker '+str(attempt));verify('global attempt bounded '+str(attempt),count('ai_calls','ai_run_id=?',(rid,))==attempt)
observed('retry after global cap rejected',raw_post(owner,wpath+'/ai-runs/'+rid+'/retry',{'expected_status':'failed'}));verify('no job beyond cap',count('jobs',"queue='work-management-ai'")==0 and count('ai_calls','ai_run_id=?',(rid,))==3)
cancel_id,_=request_ai(owner,'P6 Cancel before processing');observed('cancel queued through form',submit(owner,wpath+'/ai-runs/'+cancel_id,'ai-run-cancel',{'expected_status':'queued'}));work('cancelled job no-op')
verify('cancelled run has no provider attempt',rows('select status from ai_runs where id=?',(cancel_id,))[0]['status']=='cancelled' and count('ai_calls','ai_run_id=?',(cancel_id,))==0)
revoked_id,_=request_ai(reviewer,'P6 Permission recheck');state['revoked_run_id']=revoked_id;checkpoint()
observed('revoke project while queued',raw_post(owner,ppath+'/members/'+pms['reviewer'],{'_method':'PATCH','role_id':roles['Manager'],'status':'suspended'}));work('worker rechecks revoked permission')
verify('revoked request failed before provider attempt',rows('select status from ai_runs where id=?',(revoked_id,))[0]['status']=='failed' and count('ai_calls','ai_run_id=?',(revoked_id,))==0)
observed('revoked requester cannot open stored context',call(reviewer,wpath+'/ai-runs/'+revoked_id),expected=403)
observed('restore project membership',raw_post(owner,ppath+'/members/'+pms['reviewer'],{'_method':'PATCH','role_id':roles['Manager'],'status':'active'}))
verify('AI foundation did not mutate business or create plan',count('tasks','workspace_id=?',(wid,))==before_tasks and count('calendar_events','workspace_id=?',(wid,))==before_events and count('plans','workspace_id=?',(wid,))==before_plans)
verify('expected domain errors acknowledged, queue empty and no failed_jobs increment',count('jobs',"queue='work-management-ai'")==0 and count('failed_jobs')==failed_before)
verify('foreign key integrity',not rows('pragma foreign_key_check'))
checkpoint();log('Stage 6 HTTP worker walkthrough complete',workspace_id=wid,project_id=pid,snapshot_id=sid,run_id=rid)
