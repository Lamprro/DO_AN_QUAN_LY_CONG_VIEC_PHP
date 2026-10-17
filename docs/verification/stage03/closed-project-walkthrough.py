"""Focused live requests for closed Project write protection."""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage03/http-walkthrough.py').read_text(encoding='utf-8').split("observed('seed owner login'")[0])
facts=json.loads(OUT.joinpath('http-flow.json').read_text(encoding='utf-8'))[-1]
def log(label,**data):
    journal.append({'step':label,**data});OUT.joinpath('closed-project-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
wid=facts['workspace_id'];pid=facts['project_id'];tid=facts['task_id'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;detail=ppath+'/tasks/'+tid
observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
observed('cancel review project',submit(owner,ppath+'/edit','project-edit',project_values('Phase3 Reviewed 1792210619',status='cancelled')))
try:
    observed('cancelled project read still allowed',call(owner,ppath))
    observed('cancelled project task edit forbidden',call(owner,detail+'/edit'),expected=403)
    observed('cancelled project task POST forbidden',raw_post(owner,ppath+'/tasks',task_values('No task in cancelled project')),expected=403)
    observed('cancelled project task archive forbidden',raw_post(owner,detail+'/archive',{}),expected=403)
    verify('cancelled project invalid writes absent',not rows("select id from tasks where project_id=? and title='No task in cancelled project'",(pid,)))
finally:
    observed('reopen project to draft',submit(owner,ppath+'/edit','project-edit',project_values('Phase3 Reviewed 1792210619')))
observed('task edit allowed after reopen',call(owner,detail+'/edit'))
completed=create_project('Phase3 Completed Empty '+stamp,status='completed')
observed('completed project blocks task create',call(owner,wpath+'/projects/'+completed+'/tasks/create'),expected=403)

owner_id=rows("select id from users where email='demo1@example.test'")[0]['id']
history_only=rows("""select t.id,t.workspace_id,t.project_id from tasks t join workspaces w on w.id=t.workspace_id join projects p on p.id=t.project_id
where w.owner_id=? and p.status not in ('completed','cancelled') and p.archived_at is null and t.status='TODO' and t.archived_at is null and t.started_at is null and t.completed_at is null
and exists(select 1 from task_activities a where a.task_id=t.id)
and not exists(select 1 from tasks c where c.parent_task_id=t.id)
and not exists(select 1 from task_blockers b where b.task_id=t.id)
and not exists(select 1 from task_dependencies d where d.predecessor_task_id=t.id or d.successor_task_id=t.id)
and not exists(select 1 from calendar_events e where e.task_id=t.id)
and not exists(select 1 from time_entries e where e.task_id=t.id)
and not exists(select 1 from notifications n where n.task_id=t.id)
and not exists(select 1 from github_logs g where g.task_id=t.id) limit 1""",(owner_id,))
if history_only:
    h=history_only[0];hp='/workspaces/'+h['workspace_id']+'/projects/'+h['project_id']+'/tasks/'+h['id']
    observed('existing activity alone blocks deletion',submit(owner,hp,'task-delete',{}))
    verify('task and activity retained',bool(rows('select id from tasks where id=?',(h['id'],))) and bool(rows('select id from task_activities where task_id=?',(h['id'],))))
else:log('activity-only deletion not exercised',reason='No pre-existing fixture without other related data')
verify('final FK check',not rows('pragma foreign_key_check'))
log('closed-project walkthrough complete')
