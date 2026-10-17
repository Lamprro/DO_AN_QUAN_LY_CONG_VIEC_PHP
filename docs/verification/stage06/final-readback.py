from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
flow_source=(ROOT/'docs/verification/stage06/http-walkthrough.py').read_text(encoding='utf-8');exec(flow_source.split("observed('owner login'")[0])
import uuid
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));stamp=state['stamp'];wid=state['workspace_id'];pid=state['project_id'];uids=state['uids'];sid=state['snapshot_id'];rid=state['run_id']
for label in ['owner','worker','reviewer','outsider']:
    cc=client(label+'-'+stamp);cc[1].load(ignore_discard=True,ignore_expires=True);globals()[label]=cc
wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;filters={'from':'2026-10-01','to':'2026-11-01','project_id':pid,'subject_user_id':'','include_archived':'0'}
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('final-readback.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
browser_report='01a14a03-251a-718b-817b-d1fbc42ea07a';browser_run='01a14a03-5d93-735d-81ff-bc2d0b138c01'
browser_metrics=json.loads(rows('select metrics_json from report_snapshots where id=?',(browser_report,))[0]['metrics_json'])
verify('browser saved a real frozen snapshot',browser_metrics['inventory']['estimate_minutes']==240 and browser_metrics['period_metrics']['actual_minutes'] is None and browser_metrics['period_metrics']['planned_minutes']==120)
br=rows('select status,error_message from ai_runs where id=?',(browser_run,))[0]
verify('browser request processed by worker and honestly failed',br['status']=='failed' and count('ai_calls','ai_run_id=?',(browser_run,))==1 and count('jobs',"queue='work-management-ai'")==0,run=br)
observed('final workspace with report navigation',call(owner,wpath));observed('final project with report navigation',call(owner,ppath))
body=observed('new AI list filters render',call(owner,wpath+'/ai-runs?status=failed&created_from=2026-10-17&created_to=2026-10-18'))
verify('status filter excludes cancelled and preserves visible failed runs',rid in body and browser_run in body and 'data-form="ai-run-filter"' in body and 'cancelled</a>' not in body)
observed('report list date filter',call(owner,wpath+'/reports?created_from=2026-10-17&created_to=2026-10-18'))
observed('Member cannot open AI creation form',call(worker,wpath+'/ai-runs/create?'+urllib.parse.urlencode(filters)),expected=403)
before=count('ai_runs','workspace_id=?',(wid,));observed('Member cannot post AI requests',raw_post(worker,wpath+'/ai-runs',{**filters,'idempotency_token':str(uuid.uuid4()),'user_prompt':'Not allowed'}),expected=403);verify('no unauthorized run',count('ai_runs','workspace_id=?',(wid,))==before)
observed('another requester cannot read AI context',call(owner,wpath+'/ai-runs/'+state['revoked_run_id']),expected=403)
foreign=rows("select id from workspaces where owner_id=(select id from users where email='demo1@example.test') and id<>? limit 1",(wid,))[0]['id']
observed('cross workspace AI URL denied',call(owner,'/workspaces/'+foreign+'/ai-runs/'+rid),expected=404);observed('cross workspace report URL denied',call(owner,'/workspaces/'+foreign+'/reports/'+sid),expected=404)
observed('AI CSRF required',call(owner,wpath+'/ai-runs',{**filters,'idempotency_token':str(uuid.uuid4()),'user_prompt':'No CSRF'}),expected=419)
original=rows('select metrics_json from report_snapshots where id=?',(sid,))[0]['metrics_json'];verify('initial report still byte-identical',original==state['original_metrics'],sha256=hashlib.sha256(original.encode()).hexdigest())
actual=json.loads(rows('select metrics_json from report_snapshots where id=?',(state['actual_snapshot_id'],))[0]['metrics_json']);verify('captured real actual entry still reads 0.05 minutes',actual['period_metrics']['actual_minutes']==0.05 and state['time_entry_id'] in actual['sources']['time_entry_ids'])
verify('review scope restored, actual session ended, no test-generated timer running',all(x['status']=='active' for x in rows('select status from project_members where project_id=?',(pid,))) and rows('select status,archived_at from tasks where id=?',(state['tasks']['b'],))[0]=={'status':'TODO','archived_at':None} and count('time_entries','user_id=? and ended_at is null',(uids['worker'],))==0)
verify('FK final check',not rows('pragma foreign_key_check'))
state['browser_report_id']=browser_report;state['browser_run_id']=browser_run;checkpoint();log('Stage 6 final read-back complete',workspace_id=wid,actual_snapshot_id=state['actual_snapshot_id'])
