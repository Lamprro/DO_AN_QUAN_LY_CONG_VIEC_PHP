"""Live Laravel HTTP walkthrough. No TestCase, mocks, DB reset or fake routes."""
from pathlib import Path
import subprocess
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
OUT=ROOT/'docs/verification/stage03';OUT.mkdir(parents=True,exist_ok=True)
PRIVATE=ROOT/'storage/app/private/stage03-review';PRIVATE.mkdir(parents=True,exist_ok=True)
owner=client('owner-'+stamp);member=client('member-'+stamp);viewer=client('viewer-'+stamp)
def verify(label, condition, **facts):
    log(label,verified=bool(condition),**facts)
    if not condition: raise RuntimeError('Database/HTML read-back failed: '+label)
def project_values(name,**extra): return {'name':name,'description':'Stage 03 live flow','status':'draft','parent_project_id':'','start_date':'2026-10-17','deadline':'2026-11-17',**extra}
def task_values(title,**extra): return {'title':title,'description':'<script>alert("escaped")</script>','code':'','priority':'high','estimate_minutes':'90','deadline_at':'2026-10-22T10:30',**extra}
def create_project(name,**extra):
    r=submit(owner,wpath+'/projects/create','project-create',project_values(name,**extra));observed('create project '+name,r);return r[1].rstrip('/').split('/')[-1]
def member_form(role,status='active'):
    return {'role_id':roles[role],'status':status}

observed('seed owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
for c,label in [(member,'member'),(viewer,'viewer')]:
    observed('register '+label,submit(c,'/register','register',{'name':'Phase3 '+label+' '+stamp,'email':'phase3.'+label+'.'+stamp+'@example.test','password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}))
r=submit(owner,'/workspaces/create','workspace-create',{'name':'Phase3 HTTP Review '+stamp,'timezone':'Asia/Ho_Chi_Minh'});observed('create workspace',r)
wid=r[1].rstrip('/').split('/')[-1];wpath='/workspaces/'+wid
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
uids={};wms={}
for c,label,role in [(member,'member','Member'),(viewer,'viewer','Viewer')]:
    email='phase3.'+label+'.'+stamp+'@example.test';uids[label]=rows('select id from users where email=?',(email,))[0]['id']
    b=observed('invite workspace '+label,submit(owner,wpath+'/members','invitation-create',{'email':email,'role_id':roles[role]}))
    link=html.unescape(re.search(r'data-invitation-url="([^"]+)"',b)[1]);observed('accept workspace '+label,submit(c,link,'invitation-accept',{}))
    wms[label]=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,uids[label]))[0]['id']
pid=create_project('Phase3 Parent '+stamp)
ppath=wpath+'/projects/'+pid
PRIVATE.joinpath('state.json').write_text(json.dumps({'workspace_id':wid,'project_id':pid,'stamp':stamp,'users':uids}),encoding='utf-8')
verify('creator project membership read-back',len(rows('select id from project_members where project_id=?',(pid,)))==1,project=rows('select id,name,status,created_by from projects where id=?',(pid,)))
observed('workspace membership alone does not allow project',call(member,ppath),expected=403)
index=observed('project list hides unassigned project',call(member,wpath+'/projects'))
verify('project hidden from member list',pid not in index)
for label,role in [('member','Member'),('viewer','Viewer')]:
    observed('add project '+label,submit(owner,ppath+'/members','project-member-create',{'workspace_member_id':wms[label],**member_form(role)}))
pm={label:rows('select id from project_members where project_id=? and workspace_member_id=?',(pid,wms[label]))[0]['id'] for label in wms}
observed('explicit member can view project',call(member,ppath))
child=create_project('Phase3 Child '+stamp,parent_project_id=pid)
observed('child project does not inherit membership',call(member,wpath+'/projects/'+child),expected=403)
observed('cross-workspace project binding',call(owner,'/workspaces/'+rows('select id from workspaces where id<>? limit 1',(wid,))[0]['id']+'/projects/'+pid),expected=404)
b=observed('reject project parent cycle',submit(owner,ppath+'/edit','project-edit',project_values('Cycle must not persist',parent_project_id=child)))
verify('cycle did not update project',rows('select parent_project_id,name from projects where id=?',(pid,))[0]['name']=='Phase3 Parent '+stamp,errors='Không được' in b or 'chu trình' in b)
observed('invalid project date range',submit(owner,ppath+'/edit','project-edit',project_values('Invalid date',start_date='2026-12-01',deadline='2026-11-01')))
observed('project metadata update',submit(owner,ppath+'/edit','project-edit',project_values('Phase3 Reviewed '+stamp)))
verify('project metadata persisted',rows('select name from projects where id=?',(pid,))[0]['name']=='Phase3 Reviewed '+stamp)
observed('viewer cannot edit project',call(viewer,ppath+'/edit'),expected=403)
observed('member cannot manage project members',call(member,ppath+'/members'),expected=403)
observed('reject owner role grant',raw_post(owner,ppath+'/members/'+pm['member'],{'_method':'PATCH',**member_form('Owner')}))
verify('owner grant rejected in DB',rows('select role_id from project_members where id=?',(pm['member'],))[0]['role_id']==roles['Member'])
observed('suspend project member',raw_post(owner,ppath+'/members/'+pm['member'],{'_method':'PATCH',**member_form('Member','suspended')}))
observed('suspended member current session forbidden',call(member,ppath),expected=403)
observed('reactivate project member',raw_post(owner,ppath+'/members/'+pm['member'],{'_method':'PATCH',**member_form('Member')}))
observed('member active again',call(member,ppath))
observed('suspend workspace membership',raw_post(owner,wpath+'/members/'+wms['member'],{'_method':'PATCH',**member_form('Member','suspended')}))
observed('workspace suspension overrides project active',call(member,ppath),expected=403)
observed('restore workspace membership',raw_post(owner,wpath+'/members/'+wms['member'],{'_method':'PATCH',**member_form('Member')}))

tpath=ppath+'/tasks'
r=submit(owner,tpath+'/create','task-create',task_values('Phase3 Flow 03 task',code='p3-'+stamp,assignee_id=uids['member'],reviewer_id=rows("select id from users where email='demo1@example.test'")[0]['id']));observed('create and assign task via Blade',r)
tid=r[1].rstrip('/').split('/')[-1];detail=tpath+'/'+tid
t=rows('select id,code,title,assignee_id,reviewer_id,status,task_type,deadline_at from tasks where id=?',(tid,))[0]
verify('task database read-back including UTC conversion',t['status']=='TODO' and t['task_type']=='WORK' and t['assignee_id']==uids['member'] and '03:30' in t['deadline_at'],task=t)
observed('foreign project task binding',call(owner,wpath+'/projects/'+child+'/tasks/'+tid),expected=404)
observed('project task hidden from personal namespace',call(owner,wpath+'/tasks/'+tid),expected=404)
observed('viewer reads task',call(viewer,detail))
observed('viewer cannot edit task',call(viewer,detail+'/edit'),expected=403)
observed('viewer cannot create task',call(viewer,tpath+'/create'),expected=403)
observed('duplicate code validation',submit(owner,tpath+'/create','task-create',task_values('Duplicate must not persist',code='p3-'+stamp)))
verify('duplicate code not created',rows('select count(*) n from tasks where workspace_id=? and code=?',(wid,'P3-'+stamp))[0]['n']==1)
observed('invalid estimate input',submit(owner,tpath+'/create','task-create',task_values('Negative must not persist',estimate_minutes='-5')))
observed('reject direct status bypass',submit(owner,tpath+'/create','task-create',task_values('DONE must not persist',status='DONE')))
verify('invalid task writes absent',rows("select count(*) n from tasks where workspace_id=? and title in ('Duplicate must not persist','Negative must not persist','DONE must not persist')",(wid,))[0]['n']==0)
observed('unprivileged member cannot reassign task',submit(member,detail+'/edit','task-edit',task_values('Unauthorized reassignment',code='P3-'+stamp,assignee_id=uids['viewer'])),expected=403)
observed('member edits metadata without assign fields',submit(member,detail+'/edit','task-edit',task_values('Phase3 Flow 03 revised',code='P3-'+stamp,priority='urgent')))
verify('metadata update preserves assignment/status',rows('select title,assignee_id,status from tasks where id=?',(tid,))[0]=={'title':'Phase3 Flow 03 revised','assignee_id':uids['member'],'status':'TODO'})
b=observed('escaped description rendering',call(owner,detail));verify('untrusted HTML escaped','&lt;script&gt;' in b and '<script>alert(' not in b)
observed('task CSRF rejection',call(owner,tpath,task_values('No CSRF')),expected=419)
outsider=rows("select id from users where id not in (?,?) and email='demo5@example.test'",(uids['member'],uids['viewer']))[0]['id']
observed('foreign assignee rejected',submit(owner,detail+'/edit','task-edit',task_values('Bad assignee',code='P3-'+stamp,assignee_id=outsider)))
verify('foreign assignee not persisted',rows('select assignee_id from tasks where id=?',(tid,))[0]['assignee_id']==uids['member'])
for n in range(12):
    r=submit(owner,tpath+'/create','task-create',task_values('Phase3 Flow 03 row '+str(n)));observed('create auto-code pagination row '+str(n),r)
codes=rows('select code from tasks where project_id=?',(pid,));verify('automatic task codes unique',len({x['code'] for x in codes})==len(codes),count=len(codes))
b=observed('task filtered sorted page 1',call(owner,tpath+'?q=Flow+03&status=TODO&sort=title&direction=asc&per_page=10'))
verify('pagination preserves filter query','page=2' in b and 'q=Flow' in b,visible_rows=len(re.findall(r'data-task-id=',b)))
b=observed('task filtered sorted page 2',call(owner,tpath+'?q=Flow+03&status=TODO&sort=title&direction=asc&per_page=10&page=2'))
verify('page 2 has 3 rows',len(re.findall(r'data-task-id=',b))==3)
b=observed('priority urgent filter',call(owner,tpath+'?priority=urgent'));verify('priority filter returns edited task only',re.findall(r'data-task-id="([^"]+)"',b)==[tid])
observed('reject arbitrary sort SQL',call(owner,tpath+'?sort=title%20desc%3Bdrop%20table%20tasks'),expected=200)
observed('archive task',submit(owner,detail,'task-archive',{}));verify('archive persisted',rows('select archived_at from tasks where id=?',(tid,))[0]['archived_at'] is not None)
observed('archived task edit forbidden',call(owner,detail+'/edit'),expected=403)
observed('restore task',submit(owner,detail,'task-archive',{}))
observed('project completion with unfinished tasks rejected',submit(owner,ppath+'/edit','project-edit',project_values('Incomplete close',status='completed')))
verify('project not marked complete',rows('select status from projects where id=?',(pid,))[0]['status']=='draft')
observed('delete nonempty project rejected',submit(owner,ppath,'project-delete',{}));verify('nonempty project retained',len(rows('select id from projects where id=?',(pid,)))==1)
observed('archive project',submit(owner,ppath,'project-archive',{}))
observed('archived project task create forbidden',call(owner,tpath+'/create'),expected=403)
observed('archived project member management forbidden',call(owner,ppath+'/members'),expected=403)
observed('archived project existing task edit forbidden',call(owner,detail+'/edit'),expected=403)
observed('restore project',submit(owner,ppath,'project-archive',{}))
disposable=create_project('Phase3 Disposable '+stamp);observed('delete empty draft project',submit(owner,wpath+'/projects/'+disposable,'project-delete',{}));verify('draft project physically deleted',not rows('select id from projects where id=?',(disposable,)))
last=rows('select id from tasks where project_id=? and id<>? order by created_at desc limit 1',(pid,tid))[0]['id']
observed('delete untouched TODO task',submit(owner,tpath+'/'+last,'task-delete',{}));verify('draft task physically deleted',not rows('select id from tasks where id=?',(last,)))
personal=rows("select id from workspaces where kind='personal' and owner_id=(select id from users where email='demo1@example.test') limit 1")[0]['id'];personal_path='/workspaces/'+personal+'/tasks'
r=submit(owner,personal_path+'/create','task-create',task_values('Phase3 Personal '+stamp));observed('personal task create without project',r);personal_tid=r[1].rstrip('/').split('/')[-1]
observed('personal task edit',submit(owner,personal_path+'/'+personal_tid+'/edit','task-edit',task_values('Phase3 Personal revised '+stamp)))
verify('personal task scope persisted',rows('select project_id,workspace_id from tasks where id=?',(personal_tid,))[0]=={'project_id':None,'workspace_id':personal})
observed('personal task deletion',submit(owner,personal_path+'/'+personal_tid,'task-delete',{}))
observed('remove project member',raw_post(owner,ppath+'/members/'+pm['member'],{'_method':'DELETE'}));observed('left project member no access',call(member,detail),expected=403)
observed('rejoin project member',submit(owner,ppath+'/members','project-member-create',{'workspace_member_id':wms['member'],**member_form('Member')}));verify('rejoin reused membership UUID',rows('select id from project_members where project_id=? and workspace_member_id=?',(pid,wms['member']))==[{'id':pm['member']}])
observed('project member allowed after rejoin',call(member,detail))
verify('final foreign key integrity',not rows('pragma foreign_key_check'))
log('walkthrough complete',workspace_id=wid,project_id=pid,task_id=tid)
