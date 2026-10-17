"""Focused real HTTP checks after final permission/validation refinements."""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
source=(ROOT/'docs/verification/stage03/http-walkthrough.py').read_text(encoding='utf-8')
exec(source.split("observed('seed owner login'")[0])
state=json.loads(PRIVATE.joinpath('state.json').read_text());previous=json.loads(OUT.joinpath('http-flow.json').read_text(encoding='utf-8'))[-1]
def log(label,**facts):
    journal.append({'step':label,**facts});OUT.joinpath('targeted-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
wid=state['workspace_id'];pid=state['project_id'];tid=previous['task_id'];stamp=state['stamp'];uids=state['users'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';detail=tpath+'/'+tid
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
owner_id=rows("select id from users where email='demo1@example.test'")[0]['id']
for c,email,password in [(owner,'demo1@example.test','Demo@123456'),(member,'phase3.member.'+stamp+'@example.test',PASSWORD),(viewer,'phase3.viewer.'+stamp+'@example.test',PASSWORD)]:
    observed('login for focused verification',submit(c,'/login','login',{'email':email,'password':password}))
# The old ID was selected before this refinement, so first move to a qualified reviewer.
# Keeping an unchanged historical assignment does not count as a new grant.
observed('assign qualified reviewer',submit(owner,detail+'/edit','task-edit',task_values('Phase3 Flow 03 final',code='P3-'+stamp,priority='urgent',reviewer_id=owner_id)))
verify('qualified reviewer persisted',rows('select reviewer_id from tasks where id=?',(tid,))[0]['reviewer_id']==owner_id)
observed('reject new grant to Viewer reviewer',submit(owner,detail+'/edit','task-edit',task_values('Rejected reviewer',code='P3-'+stamp,reviewer_id=uids['viewer'])))
verify('invalid reviewer grant not persisted',rows('select reviewer_id,title from tasks where id=?',(tid,))[0]=={'reviewer_id':owner_id,'title':'Phase3 Flow 03 final'})
observed('Vietnamese numeric validation',submit(owner,tpath+'/create','task-create',task_values('Rejected negative',estimate_minutes='-1')))
observed('Vietnamese prohibited field validation',submit(owner,tpath+'/create','task-create',task_values('Rejected state',status='DONE')))
b=observed('literal zero search',call(owner,tpath+'?q=0&sort=title&direction=asc'))
expected=rows("select id from tasks where project_id=? and archived_at is null and (title like '%0%' or code like '%0%') order by title asc,id asc limit 10",(pid,))
verify('literal zero search matches database',re.findall(r'data-task-id="([^"]+)"',b)==[r['id'] for r in expected])
child=rows('select id from projects where parent_project_id=?',(pid,))[0]['id']
pm=rows('select id from project_members where project_id=? and workspace_member_id=(select id from workspace_members where workspace_id=? and user_id=?)',(pid,wid,uids['member']))[0]['id']
observed('project member from another project rejected',raw_post(owner,wpath+'/projects/'+child+'/members/'+pm,{'_method':'PATCH','role_id':roles['Member'],'status':'active'}),expected=404)
wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,uids['viewer']))[0]['id']
limited_perms=['workspace.view','project.create']
observed('create role with create but no project view',submit(owner,wpath+'/roles/create','role-create',{'name':'P3 Create Without View','permissions[]':limited_perms}))
limited=rows("select id from roles where workspace_id=? and name='P3 Create Without View'",(wid,))[0]['id']
observed('assign limited workspace role',raw_post(owner,wpath+'/members/'+wm,{'_method':'PATCH','role_id':limited,'status':'active'}))
try:
    observed('create form requires project view as well as create',call(viewer,wpath+'/projects/create'),expected=403)
    observed('direct project creation same boundary',raw_post(viewer,wpath+'/projects',project_values('Must not create without view')),expected=403)
    verify('no orphan project created',not rows("select id from projects where workspace_id=? and name='Must not create without view'",(wid,)))
finally:
    observed('restore workspace Viewer role',raw_post(owner,wpath+'/members/'+wm,{'_method':'PATCH','role_id':roles['Viewer'],'status':'active'}))
observed('create delegated project role',submit(owner,wpath+'/roles/create','role-create',{'name':'P3 Project Delegate','permissions[]':['workspace.view','project.view','project.manage_members','task.view']}))
delegate=rows("select id from roles where workspace_id=? and name='P3 Project Delegate'",(wid,))[0]['id']
observed('assign delegate in project',raw_post(owner,ppath+'/members/'+pm,{'_method':'PATCH','role_id':delegate,'status':'active'}))
vpm=rows('select id from project_members where project_id=? and workspace_member_id=?',(pid,wm))[0]['id']
try:
    observed('delegated project management allowed',call(member,ppath+'/members'))
    observed('delegate cannot grant Manager',raw_post(member,ppath+'/members/'+vpm,{'_method':'PATCH','role_id':roles['Manager'],'status':'active'}))
    verify('target Viewer role preserved',rows('select role_id from project_members where id=?',(vpm,))[0]['role_id']==roles['Viewer'])
    observed('delegate cannot change own project role',raw_post(member,ppath+'/members/'+pm,{'_method':'PATCH','role_id':delegate,'status':'active'}))
finally:
    observed('restore project Member role',raw_post(owner,ppath+'/members/'+pm,{'_method':'PATCH','role_id':roles['Member'],'status':'active'}))

# Reuse real pre-existing seeded history; no fabricated comment/history HTTP endpoint.
historical=rows("select t.id,t.workspace_id,t.project_id from tasks t join workspaces w on w.id=t.workspace_id where w.owner_id=? and t.status='TODO' and t.archived_at is null and t.project_id is not null and exists(select 1 from task_activities a where a.task_id=t.id) limit 1",(owner_id,))
if historical:
    h=historical[0];hp='/workspaces/'+h['workspace_id']+'/projects/'+h['project_id']+'/tasks/'+h['id']
    observed('deletion blocked for existing history',submit(owner,hp,'task-delete',{}))
    verify('historical task retained',bool(rows('select id from tasks where id=?',(h['id'],))))
else:log('history deletion boundary not exercised',reason='No eligible existing TODO fixture with activity')
verify('final FK integrity',not rows('pragma foreign_key_check'))
log('targeted walkthrough complete')
