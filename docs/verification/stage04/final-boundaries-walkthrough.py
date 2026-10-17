from pathlib import Path
import datetime
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
s=(R/'docs/verification/stage04/files-final-walkthrough.py').read_text(encoding='utf-8');exec(s.split('page=tpath')[0]);page=tpath+'/'+c
journal=json.loads((OUT/'files-final-flow.json').read_text(encoding='utf-8'))
log('file and tree verification complete')
# Independent final cases, with their own log.
journal=[]
def log(label,**facts):
 journal.append({'step':label,**facts});OUT.joinpath('final-boundaries.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
lag=state['lag_next'];pre=state['lag_pre'];completed=rows('select completed_at from tasks where id=?',(pre,))[0]['completed_at']
ready=datetime.datetime.fromisoformat(completed).replace(tzinfo=datetime.timezone.utc).timestamp()+60
truth('natural FS minute elapsed',time.time()>=ready,observed_epoch=int(time.time()),ready_epoch=int(ready))
truth('real one minute lag now permits start',transition(owner,lag,'IN_PROGRESS')=='IN_PROGRESS')
truth('stored start after lag threshold',datetime.datetime.fromisoformat(rows('select started_at from tasks where id=?',(lag,))[0]['started_at']).replace(tzinfo=datetime.timezone.utc).timestamp()>=ready)
foreign=rows('select id from tasks where project_id is not null and project_id<>? limit 1',(pid,))[0]['id']
observed('dependency foreign project rejected',submit(owner,page,'dependency-create',{'predecessor_task_id':foreign,'lag_minutes':'0'}))
truth('foreign dependency not written',not rows('select id from task_dependencies where predecessor_task_id=? and successor_task_id=?',(foreign,c)))
foreign_blocker=rows('select id from task_blockers where task_id=?',(a,))[0]['id']
observed('blocker id under wrong task rejected',raw_post(owner,page+'/blockers/'+foreign_blocker,{'_method':'PATCH','status':'resolved'}),expected=404)
observed('workflow CSRF missing',call(owner,page+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'}),expected=419)
truth('invalid direct TODO to DONE transition denied',transition(owner,c,'DONE')=='TODO')
g2=rows("select id from tasks where project_id=? and title='P4 Depth Root' order by created_at desc limit 1",(pid,))[0]['id'];g3=rows('select id from tasks where parent_task_id=?',(g2,))[0]['id'];g4=rows("select id from tasks where parent_task_id=? and task_type='GROUP'",(g3,))[0]['id']
movable=rows("select id from tasks where parent_task_id=? and task_type='WORK'",(g3,))[0]['id']
observed('GROUP cannot archive before active children',submit(owner,tpath+'/'+g2,'task-archive',{}));truth('GROUP remains active',rows('select archived_at from tasks where id=?',(g2,))[0]['archived_at'] is None)
for tid in [g4,movable,g3,g2]:observed('archive bottom-up',submit(owner,tpath+'/'+tid,'task-archive',{}))
observed('cannot restore child before archived parent',raw_post(owner,tpath+'/'+g4+'/restore',{}),expected=403)
for tid in [g2,g3,g4,movable]:observed('restore top-down',submit(owner,tpath+'/'+tid,'task-archive',{}))
personal=rows("select id from workspaces where owner_id=(select id from users where email='demo1@example.test') and kind='personal' limit 1")[0]['id'];personalpath='/workspaces/'+personal+'/tasks'
response=submit(owner,personalpath+'/create','task-create',{'title':'P4 Personal state flow','priority':'low','task_type':'WORK','estimate_minutes':'15'});observed('personal task workflow fixture',response);ptid=response[1].split('/')[-1]
for target in ['IN_PROGRESS','REVIEW','DONE']:
    status=rows('select status from tasks where id=?',(ptid,))[0]['status'];observed('personal status '+target,raw_post(owner,personalpath+'/'+ptid+'/status',{'_method':'PATCH','expected_status':status,'status':target}));truth('personal state persisted '+target,rows('select status from tasks where id=?',(ptid,))[0]['status']==target)
observed('personal tree page',call(owner,personalpath+'/tree'))
truth('final FK check',not rows('pragma foreign_key_check'))
log('all final boundaries complete',workspace_id=wid,project_id=pid,task_id=c)
