from pathlib import Path
M=Path(__file__).parent;s=(M/'p1_observation_flow.py').read_text(encoding='utf-8');exec(s.split("for f in (R/'storage/framework/views')")[0]);state=json.loads((PRIVATE/'state.json').read_text());journal=[]
def log(step,**facts):
 journal.append({'step':step,**facts});(OUT/'boundaries.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
owner=client('boundary-owner');observed('login Owner',submit_live(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}));tid=state['task_id'];snapshot=state['snapshot_id'];run=state['ai_run_id']
event={'title':'P1 cross-day clip '+stamp,'event_type':'TASK_BLOCK','task_id':tid,'project_id':pid,'timezone':'Asia/Ho_Chi_Minh','starts_at':'2027-05-16T23:30','ends_at':'2027-05-17T00:30','all_day':'0','visibility':'project','is_busy':'1','status':'planned'}
r=submit_live(owner,cp+'/create','calendar-save',event);observed('save real cross-day block',r);cross=r[1].split('/')[-1];assert rows('select id from calendar_events where id=?',(cross,))
body=observed('timeline clips left boundary',call(owner,pp+'/timeline?view=day&date=2027-05-17'));verify('half-hour visible from full-hour source','left:0%;width:2.0833%' in body,source_minutes=60,visible_minutes=30)
note={**event,'title':'P1 exclusive all-day '+stamp,'event_type':'NOTE','task_id':'','starts_at':'2027-05-19','ends_at':'2027-05-20','all_day':'1','is_busy':'0'}
r=submit_live(owner,cp+'/create?all_day=1','calendar-save',note);observed('save all-day NOTE',r);all_day=r[1].split('/')[-1];assert rows('select id from calendar_events where id=?',(all_day,))
body=observed('all-day start present',call(owner,cp+'?view=day&date=2027-05-19&project_id='+pid));verify('all-day day included',('data-event-id="'+all_day+'"') in body)
body=observed('all-day exclusive end',call(owner,cp+'?view=day&date=2027-05-20&project_id='+pid));verify('all-day end not repeated',('data-event-id="'+all_day+'"') not in body)
cancel={**note,'title':'P1 cancelled note '+stamp,'all_day':'0','starts_at':'2027-05-17T12:00','ends_at':'2027-05-17T12:30','status':'cancelled'}
r=submit_live(owner,cp+'/create','calendar-save',cancel);observed('save cancelled event fixture',r);cancel_id=r[1].split('/')[-1];assert rows('select id from calendar_events where id=?',(cancel_id,))
body=observed('cancelled hidden by default',call(owner,cp+'?view=day&date=2027-05-17&project_id='+pid));verify('no cancelled record in default HTML',('data-event-id="'+cancel_id+'"') not in body)
body=observed('cancelled explicit filter',call(owner,cp+'?view=day&date=2027-05-17&include_cancelled=1&project_id='+pid));verify('cancelled labeled and styled',('data-event-id="'+cancel_id+'"') in body and 'event-cancelled' in body and 'Đã hủy' in body)
metrics=json.loads(rows('select metrics_json from report_snapshots where id=?',(snapshot,))[0]['metrics_json']);verify('existing snapshot remains ninety planned minutes',metrics['period_metrics']['planned_minutes']==90)
path=wp+'/ai-runs/'+run;observed('retry actual failed run',submit_live(owner,path,'ai-run-retry',{}));verify('retry queued same snapshot',rows('select status from ai_runs where id=?',(run,))[0]['status']=='queued')
observed('cancel rendered queued run form',submit_live(owner,path,'ai-run-cancel',{}));verify('cancel saved canonical state',rows('select status from ai_runs where id=?',(run,))[0]['status']=='cancelled')
assert len(rows('select id from jobs'))==1
p=subprocess.run([r'C:\xampp\php\php.exe','artisan','queue:work','database','--once','--queue=work-management-ai','--tries=1'],cwd=R,capture_output=True,text=True,timeout=60);verify('worker acknowledges cancelled job without provider',p.returncode==0 and not rows('select id from jobs') and len(rows('select id from ai_calls where ai_run_id=?',(run,)))==1 and not rows('select id from plans where ai_run_id=?',(run,)))
verify('foreign keys still clean',not rows('pragma foreign_key_check'));verify('env unchanged',env==hashlib.sha256((R/'.env').read_bytes()).hexdigest());state.update(cross_day_id=cross,all_day_id=all_day,cancelled_event_id=cancel_id);checkpoint();log('boundary complete',real_database=True)
