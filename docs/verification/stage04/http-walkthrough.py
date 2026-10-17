"""Real form/HTTP/SQLite walkthrough for Stage 4. No TestCase or mocked services."""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage03/http-walkthrough.py').read_text(encoding='utf-8').split("observed('seed owner login'")[0])
OUT=ROOT/'docs/verification/stage04';OUT.mkdir(parents=True,exist_ok=True)
PRIVATE=ROOT/'storage/app/private/stage04-review';PRIVATE.mkdir(parents=True,exist_ok=True)
owner=client('owner-'+stamp);worker=client('worker-'+stamp);reviewer=client('reviewer-'+stamp);viewer=client('viewer-'+stamp)
def create(title,kind='WORK',parent='',**extra):
    r=submit(owner,tpath+'/create','task-create',{'title':title,'code':'','priority':'medium','task_type':kind,'parent_task_id':parent,'sort_order':'0','estimate_minutes':'60' if kind=='WORK' else '',**extra})
    observed('create '+title,r);tid=r[1].rstrip('/').split('/')[-1]
    if not rows('select id from tasks where id=?',(tid,)):raise RuntimeError('Task not created: '+title)
    return tid
def transition(c,tid,to,reason='',expect=200,expected=None):
    before=rows('select status from tasks where id=?',(tid,))[0]['status']
    r=raw_post(c,tpath+'/'+tid+'/status',{'_method':'PATCH','expected_status':expected or before,'status':to,'reason':reason});observed('status '+before+' to '+to,r,expected=expect)
    return rows('select status from tasks where id=?',(tid,))[0]['status']
def truth(label,test,**facts):verify(label,test,**facts)

observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
r=submit(owner,'/workspaces/create','workspace-create',{'name':'Phase4 HTTP Review '+stamp,'timezone':'Asia/Ho_Chi_Minh'});observed('create workspace',r)
wid=r[1].rstrip('/').split('/')[-1];wpath='/workspaces/'+wid
roles={x['name']:x['id'] for x in rows('select id,name from roles where workspace_id=?',(wid,))};uids={};wms={}
pid=create_project('Phase4 Workflow '+stamp);ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
for c,label,role in [(worker,'worker','Member'),(reviewer,'reviewer','Manager'),(viewer,'viewer','Viewer')]:
    email='phase4.'+label+'.'+stamp+'@example.test'
    observed('register '+label,submit(c,'/register','register',{'name':'Phase4 '+label,'email':email,'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}))
    uids[label]=rows('select id from users where email=?',(email,))[0]['id']
    b=observed('workspace invite '+label,submit(owner,wpath+'/members','invitation-create',{'email':email,'role_id':roles[role]}));url=html.unescape(re.search(r'data-invitation-url="([^"]+)"',b)[1])
    observed('accept invite '+label,submit(c,url,'invitation-accept',{}));wms[label]=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,uids[label]))[0]['id']
    observed('project membership '+label,submit(owner,ppath+'/members','project-member-create',{'workspace_member_id':wms[label],'role_id':roles[role],'status':'active'}))
group=create('P4 Group','GROUP');nested=create('P4 Nested','GROUP',group)
a=create('P4 Predecessor',parent=nested,assignee_id=uids['worker'],reviewer_id=uids['reviewer'])
b=create('P4 Successor',parent=group,assignee_id=uids['worker'],reviewer_id=uids['reviewer'])
m=create('P4 Milestone','MILESTONE',group,assignee_id=uids['worker'],reviewer_id=uids['reviewer'])
state={'stamp':stamp,'workspace_id':wid,'project_id':pid,'group':group,'nested':nested,'a':a,'b':b,'milestone':m,'uids':uids};PRIVATE.joinpath('state.json').write_text(json.dumps(state))
truth('tree and create history read-back',len(rows("select id from task_activities where task_id=? and type='HISTORY' and event_type='task.created'",(a,)))==1,tasks=rows('select id,parent_task_id,task_type,status,estimate_minutes from tasks where project_id=?',(pid,)))
observed('tree page',call(owner,tpath+'/tree'))
observed('maximum depth rejects fourth level',submit(owner,tpath+'/create','task-create',{'title':'P4 Too Deep','priority':'medium','task_type':'WORK','parent_task_id':a,'estimate_minutes':'10'}))
observed('cycle tree rejected',submit(owner,tpath+'/'+group+'/edit','task-edit',{'title':'Cycle must not persist','priority':'medium','task_type':'GROUP','parent_task_id':nested,'estimate_minutes':''}))
truth('cycle rollback',rows('select parent_task_id,title from tasks where id=?',(group,))[0]=={'parent_task_id':None,'title':'P4 Group'})
observed('non-WORK estimate rejected',submit(owner,tpath+'/create','task-create',{'title':'P4 Bad Milestone','priority':'medium','task_type':'MILESTONE','estimate_minutes':'5'}))
observed('dependency added',submit(owner,tpath+'/'+b,'dependency-create',{'predecessor_task_id':a,'lag_minutes':'0'}))
edge=rows('select id from task_dependencies where predecessor_task_id=? and successor_task_id=?',(a,b))[0]['id']
observed('dependency cycle rejected',submit(owner,tpath+'/'+a,'dependency-create',{'predecessor_task_id':b,'lag_minutes':'0'}))
truth('dependency cycle absent',not rows('select id from task_dependencies where predecessor_task_id=? and successor_task_id=?',(b,a)))
truth('FS blocks successor before predecessor DONE',transition(worker,b,'IN_PROGRESS')=='TODO')
transition(viewer,a,'IN_PROGRESS',expect=403)
truth('worker starts assigned task',transition(worker,a,'IN_PROGRESS')=='IN_PROGRESS')
truth('GROUP derived IN_PROGRESS',rows('select status from tasks where id=?',(group,))[0]['status']=='IN_PROGRESS')
observed('report blocker',submit(worker,tpath+'/'+a,'blocker-create',{'reason':'P4 Waiting for source data'}))
blocker=rows('select id from task_blockers where task_id=?',(a,))[0]['id']
truth('blocker stops submit REVIEW',transition(worker,a,'REVIEW')=='IN_PROGRESS')
observed('resolve blocker through form',submit(worker,tpath+'/'+a,'blocker-update',{'status':'resolved'}))
truth('blocker resolution database',rows('select status,resolved_by from task_blockers where id=?',(blocker,))[0]=={'status':'resolved','resolved_by':uids['worker']})
truth('submit review',transition(worker,a,'REVIEW')=='REVIEW')
observed('REVIEW metadata locked',call(worker,tpath+'/'+a+'/edit'),expected=403)
transition(worker,a,'DONE',expect=403)
truth('reviewer requests revision',transition(reviewer,a,'IN_PROGRESS','Please add evidence')=='IN_PROGRESS')
truth('resubmit review',transition(worker,a,'REVIEW')=='REVIEW')
truth('reviewer approves',transition(reviewer,a,'DONE')=='DONE')
truth('completed timestamp stored',rows('select completed_at from tasks where id=?',(a,))[0]['completed_at'] is not None)
truth('stale expected status rejected',transition(owner,a,'TODO','Old form',expected='REVIEW')=='DONE')
truth('successor starts after FS done',transition(worker,b,'IN_PROGRESS')=='IN_PROGRESS')
truth('cannot reopen prerequisite with active successor',transition(reviewer,a,'TODO','Need correction')=='DONE')
truth('submit successor',transition(worker,b,'REVIEW')=='REVIEW');truth('approve successor',transition(reviewer,b,'DONE')=='DONE')
truth('milestone starts',transition(worker,m,'IN_PROGRESS')=='IN_PROGRESS');truth('milestone submits',transition(worker,m,'REVIEW')=='REVIEW');truth('milestone approves',transition(reviewer,m,'DONE')=='DONE')
truth('GROUP status becomes DONE',rows('select status from tasks where id=?',(group,))[0]['status']=='DONE')
html_tree=observed('progress tree after completion',call(owner,tpath+'/tree'));truth('WORK progress 100 percent','100%' in html_tree)
c=create('P4 Comment File',assignee_id=uids['worker'],reviewer_id=uids['reviewer'])
observed('comment added',submit(worker,tpath+'/'+c,'comment-create',{'content':'<script>alert("comment")</script> P4 note'}))
comment=rows("select id from task_activities where task_id=? and type='COMMENT'",(c,))[0]['id']
body=observed('comment escaped in HTML',call(owner,tpath+'/'+c));truth('script comment escaped','&lt;script&gt;' in body and '<script>alert(' not in body)
observed('another actor cannot edit comment',raw_post(reviewer,tpath+'/'+c+'/comments/'+comment,{'_method':'PATCH','content':'Not mine'}),expected=403)
observed('author edits comment',submit(worker,tpath+'/'+c,'comment-update',{'content':'P4 edited note'}))
observed('author deletes comment',submit(worker,tpath+'/'+c,'comment-delete',{}));truth('comment soft deleted',rows('select deleted_at from task_activities where id=?',(comment,))[0]['deleted_at'] is not None)
history=rows("select id from task_activities where task_id=? and type='HISTORY' limit 1",(c,))[0]['id']
observed('HISTORY cannot be edited through comments endpoint',raw_post(owner,tpath+'/'+c+'/comments/'+history,{'_method':'PATCH','content':'Overwrite audit'}),expected=404)
observed('HISTORY cannot be deleted through comments endpoint',raw_post(owner,tpath+'/'+c+'/comments/'+history,{'_method':'DELETE'}),expected=404)
state['c']=c;state['comment']=comment;PRIVATE.joinpath('state.json').write_text(json.dumps(state))
truth('final foreign key integrity',not rows('pragma foreign_key_check'))
log('core walkthrough complete',workspace_id=wid,project_id=pid,task_id=c)
