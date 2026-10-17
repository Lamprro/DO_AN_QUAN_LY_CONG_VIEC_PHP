from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
flow_source=(ROOT/'docs/verification/stage06/http-walkthrough.py').read_text(encoding='utf-8')
exec(flow_source.split("observed('owner login'")[0])
from datetime import datetime
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));stamp=state['stamp'];wid=state['workspace_id'];pid=state['project_id'];uids=state['uids'];wms=state['wms'];pms=state['pms'];sid=state['snapshot_id'];b=state['tasks']['b'];c=state['tasks']['c']
for label in ['owner','worker','reviewer','outsider']:
    cc=client(label+'-'+stamp);cc[1].load(ignore_discard=True,ignore_expires=True);globals()[label]=cc
wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
filters={'from':'2026-10-01','to':'2026-11-01','project_id':pid,'subject_user_id':'','include_archived':'0'}
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('boundaries.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def snapshot(actor,values=None):
    r=raw_post(actor,wpath+'/reports',values or filters);observed('save boundary snapshot',r);rid=r[1].rstrip('/').split('/')[-1]
    return rid,json.loads(rows('select metrics_json from report_snapshots where id=?',(rid,))[0]['metrics_json'])
def session(action,eid=None):
    p=subprocess.run(['php','docs/verification/stage06/elapsed-session.php',action,*([eid] if eid else [])],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',errors='replace')
    log('CLI existing TimeEntry model '+action,exit_code=p.returncode)
    if p.returncode:raise RuntimeError(p.stderr+p.stdout)
    return json.loads(p.stdout)
observed('guest dashboard requires login',call(client('guest-'+stamp),wpath+'/dashboard'))
observed('report CSRF required',call(owner,wpath+'/reports',filters),expected=419)
entry=session('start');eid=entry['id'];state['time_entry_id']=eid;checkpoint()
running_id,running=snapshot(owner)
verify('running actual entry is not invented as elapsed work',running['period_metrics']['actual_minutes'] is None and running['period_metrics']['running_entries_excluded']==1)
observed('existing TaskWorkflow prevents cancellation while timer running',raw_post(owner,tpath+'/'+b+'/status',{'_method':'PATCH','expected_status':'TODO','status':'CANCELLED','reason':'P6 running entry guard'}))
verify('task still TODO under running guard',rows('select status from tasks where id=?',(b,))[0]['status']=='TODO')
ended=session('end',eid)
stored=rows('select started_at,ended_at from time_entries where id=?',(eid,))[0]
seconds=(datetime.fromisoformat(stored['ended_at'])-datetime.fromisoformat(stored['started_at'])).total_seconds();expected=round(seconds/60,2)
verify('real elapsed interval, no clock changes',seconds>0,entry_id=eid,seconds=seconds)
actual_id,actual=snapshot(owner);verify('actual report matches existing model interval',actual['period_metrics']['actual_minutes']==expected and actual['period_metrics']['running_entries_excluded']==0,actual=actual['period_metrics']['actual_minutes'],expected=expected)
observed('cancel task after ended real session',raw_post(owner,tpath+'/'+b+'/status',{'_method':'PATCH','expected_status':'TODO','status':'CANCELLED','reason':'P6 preserve real spent time'}))
cancel_id,cancel=snapshot(owner);verify('cancel removes plan/estimate but retains measured actual',cancel['period_metrics']['actual_minutes']==expected and cancel['period_metrics']['planned_minutes']==60 and cancel['inventory']['estimate_minutes']==60)
observed('restore task after cancellation verification',raw_post(owner,tpath+'/'+b+'/status',{'_method':'PATCH','expected_status':'CANCELLED','status':'TODO','reason':'P6 restore review state'}))
# Positive blocker and archived/member filters through existing native Task forms.
observed('create blocker through existing flow',submit(worker,tpath+'/'+b,'blocker-create',{'reason':'P6 Report source blocker'}))
blocker_id,blocker=snapshot(owner);verify('blocker metric from real source',blocker['inventory']['open_blockers']==1 and blocker['inventory']['blocked_tasks']==1)
observed('resolve existing blocker',submit(worker,tpath+'/'+b,'blocker-update',{'status':'resolved'}))
verify('blocker snapshot stays fixed after resolve',json.loads(rows('select metrics_json from report_snapshots where id=?',(blocker_id,))[0]['metrics_json'])['inventory']['open_blockers']==1)
observed('archive unestimated task',submit(owner,tpath+'/'+c,'task-archive',{}));default_id,default=snapshot(owner);all_id,all_work=snapshot(owner,{**filters,'include_archived':'1'})
verify('archive filter and progress rule are explicit',default['inventory']['work_count']==3 and default['inventory']['estimate_unknown']==0 and default['inventory']['progress']['method']=='estimate' and all_work['inventory']['work_count']==4 and all_work['inventory']['progress']['method']=='count')
observed('restore unestimated task',submit(owner,tpath+'/'+c,'task-archive',{}))
member_id,member=snapshot(owner,{**filters,'subject_user_id':uids['worker']});verify('current assignee filter',member['inventory']['work_count']==3 and member['inventory']['estimate_unknown']==0)
foreign_user=rows("select id from users where email='demo5@example.test'")[0]['id'];before=count('report_snapshots','workspace_id=?',(wid,))
observed('foreign subject rejected',raw_post(owner,wpath+'/reports',{**filters,'subject_user_id':foreign_user}));observed('reverse dates rejected',raw_post(owner,wpath+'/reports',{**filters,'from':'2026-11-01','to':'2026-10-01'}));observed('too long period rejected',raw_post(owner,wpath+'/reports',{**filters,'from':'2025-01-01','to':'2026-11-01'}));verify('invalid requests created no rows',count('report_snapshots','workspace_id=?',(wid,))==before)
# A saved report must not bypass newly revoked calendar permissions.
review_id,review=snapshot(reviewer);role_permissions=json.loads(rows('select permissions from roles where id=?',(roles['Manager'],))[0]['permissions']);permissions=[p for p in role_permissions if p!='calendar.view']
observed('create role keeping reports but revoking calendar view',submit(owner,wpath+'/roles/create','role-create',{'name':'P6 Report Without Calendar','permissions[]':permissions}))
custom=rows('select id from roles where workspace_id=? and name=?',(wid,'P6 Report Without Calendar'))[0]['id'];observed('apply restricted project role',raw_post(owner,ppath+'/members/'+pms['reviewer'],{'_method':'PATCH','role_id':custom,'status':'active'}))
observed('old report with calendar source denied after permission change',call(reviewer,wpath+'/reports/'+review_id),expected=403)
body=observed('list excludes no-longer-readable snapshot',call(reviewer,wpath+'/reports'));verify('no protected ID leaked from own report list',review_id not in body)
new_review_id,no_calendar=snapshot(reviewer);verify('new permitted report omits calendar sources',no_calendar['period_metrics']['planned_minutes']==0 and no_calendar['sources']['calendar_event_ids']==[])
observed('restore Manager project role',raw_post(owner,ppath+'/members/'+pms['reviewer'],{'_method':'PATCH','role_id':roles['Manager'],'status':'active'}));observed('restored role reopens unchanged snapshot',call(reviewer,wpath+'/reports/'+review_id))
verify('original baseline snapshot unchanged',rows('select metrics_json from report_snapshots where id=?',(sid,))[0]['metrics_json']==state['original_metrics'])
state.update(actual_snapshot_id=actual_id,blocker_snapshot_id=blocker_id,review_snapshot_id=review_id);checkpoint()
verify('FK integrity after live source updates',not rows('pragma foreign_key_check'));log('Stage 6 boundary walkthrough complete',workspace_id=wid,time_entry_id=eid,actual_minutes=expected)
