from pathlib import Path
exec((Path(__file__).parent/'full_flow.py').read_text(encoding='utf-8').split("\nowner=login('demo1@example.test')")[0])
stage='edges';journal=[];owner=login('demo1@example.test');other=login('demo4@example.test');member=login('demo3@example.test');viewer=login('demo5@example.test');wp,pp,tp,pid=state['wp'],state['pp'],state['tp'],state['pid'];uid,oid=state['uid'],state['oid'];suffix=str(int(time.time()))
wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(state['wid'],oid))[0]['id'];rid=rows("select id from roles where workspace_id=? and name='Member'",(state['wid'],))[0]['id']
membership=rows('select id,status,role_id from project_members where project_id=? and workspace_member_id=?',(pid,wm))
if not membership or membership[0]['status']!='active':post(owner,pp+'/members',{'workspace_member_id':wm,'role_id':rid,'status':'active'},pp+'/members')
elif membership[0]['role_id']!=rid:post(owner,pp+'/members/'+membership[0]['id'],{'_method':'PATCH','role_id':rid,'status':'active'},pp+'/members')
day=(datetime.date(2027,10,4)+datetime.timedelta(days=int(suffix)%1000)).isoformat();tomorrow=(datetime.date.fromisoformat(day)+datetime.timedelta(days=1)).isoformat()
page=wp+'/team-calendar?project_id='+pid
task=work(owner,tp,prefix+' scoped calendar task '+suffix,assignee_id=oid,reviewer_id=uid,estimate_minutes=30)
values={'project_id':pid,'recipient_id':oid,'title':prefix+' PRIVATE TASK PROPOSAL '+suffix,'event_type':'TASK_BLOCK','task_id':task,'visibility':'private','timezone':'Asia/Ho_Chi_Minh','starts_at':day+'T09:00','ends_at':day+'T10:00'}
form(owner,page,'calendar-proposal',values);proposal=rows('select * from calendar_proposals where title=?',(values['title'],))[0]
# Keep calendar permissions while removing task.view; task-linked proposal and inbox must both disappear.
name=prefix+' Calendar Only '+suffix
form(owner,wp+'/roles/create','role-create',{'name':name,'permissions[]':['workspace.view','project.view','calendar.view','calendar.create','calendar.update']})
role=rows('select id from roles where workspace_id=? and name=?',(state['wid'],name))[0]['id'];pm=rows('select pm.id from project_members pm join workspace_members wm on wm.id=pm.workspace_member_id where pm.project_id=? and wm.user_id=?',(pid,oid))[0]['id']
post(owner,pp+'/members/'+pm,{'_method':'PATCH','role_id':role,'status':'active'},pp+'/members')
_,body=call(other,page);verify('calendar-only role cannot see task proposal id/title',proposal['id'] not in body and values['title'] not in body and task not in body)
_,body=call(other,'/notifications');verify('task proposal notice hidden after task permission revoked',values['title'] not in body and task not in body)
post(other,wp+'/team-calendar/'+proposal['id']+'/accept',{'revision':1},page,expected=403)
meeting={**values,'task_id':'','event_type':'MEETING','title':prefix+' calendar-only meeting '+suffix,'starts_at':day+'T13:00','ends_at':day+'T14:00'}
form(owner,page,'calendar-proposal',meeting);p=rows('select id from calendar_proposals where title=?',(meeting['title'],))[0]['id'];form(other,page,'proposal-accept-'+p,{});verify('calendar-only role can consent to own meeting',rows('select status from calendar_proposals where id=?',(p,))[0]['status']=='accepted')
rid=rows("select id from roles where workspace_id=? and name='Member'",(state['wid'],))[0]['id'];post(owner,pp+'/members/'+pm,{'_method':'PATCH','role_id':rid,'status':'active'},pp+'/members')
# Sender revoked after proposing: recipient cannot book based on obsolete authorization.
post(other,wp+'/team-calendar/'+p+'/accept',{'revision':1},page) # Already accepted, harmless stale replay.
form(other,page,'calendar-proposal',{**meeting,'title':prefix+' revoked sender '+suffix,'recipient_id':uid,'starts_at':tomorrow+'T13:00','ends_at':tomorrow+'T14:00'});pr=rows('select id from calendar_proposals where title=?',(prefix+' revoked sender '+suffix,))[0]['id']
post(owner,pp+'/members/'+pm,{'_method':'DELETE'},pp+'/members');before=len(rows('select id from calendar_events'));post(owner,wp+'/team-calendar/'+pr+'/accept',{'revision':1},page,expected=403);verify('removed sender cannot authorize a new booking',before==len(rows('select id from calendar_events')))
# Both removed assignees and permissions are checked at generation; partial graph writes roll back.
tpage=wp+'/task-templates?project_id='+pid;yesterday=(datetime.date.today()-datetime.timedelta(days=1)).isoformat()
base={'project_id':pid,'name':prefix+' permission failure '+suffix,'frequency':'daily','interval':1,'timezone':'Asia/Ho_Chi_Minh','starts_on':yesterday,'local_time':'08:00','task_ids[]':[state['group'],state['a'],state['b'],state['member_task']]}
u,_=form(owner,tpage,'template-create',base);tid=u.split('/')[-1];before_tasks=rows('select id from tasks');before_history=rows('select id from task_activities');proc=subprocess.run([os.environ.get('WM_PHP','php'),'artisan','tasks:generate-recurring'],cwd=R,capture_output=True,text=True,timeout=90)
run=rows('select * from task_template_runs where task_template_id=?',(tid,));verify('invalid assignment rolls back all generated tasks and history',proc.returncode==0 and before_tasks==rows('select id from tasks') and before_history==rows('select id from task_activities') and len(run)==1 and run[0]['status']=='failed')
verify('failed period has no fabricated task map',run[0]['task_map'] is None and bool(rows('select last_error from task_templates where id=?',(tid,))[0]['last_error']))
# Native saved schedule models used to calculate month-end and DST edge cases.
created={}
for label,freq,date,zone,clock in [('monthly','monthly','2027-01-31','Asia/Ho_Chi_Minh','09:00'),('spring','daily','2027-03-13','America/New_York','02:30'),('fall','daily','2027-11-06','America/New_York','01:30')]:
 u,_=form(owner,tpage,'template-create',{**base,'name':prefix+' '+label+' '+suffix,'frequency':freq,'starts_on':date,'timezone':zone,'local_time':clock,'task_ids[]':[state['group'],state['a'],state['b']]});created[label]=u.split('/')[-1]
php=PRIVATE/'calendar-edges.php';php.write_text('''<?php require dirname(__DIR__,4).'/vendor/autoload.php';$app=require dirname(__DIR__,4).'/bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$ids=json_decode('''+repr(json.dumps(created))+''',true);$service=app(App\\Services\\TaskTemplateService::class);$out=[];foreach(['monthly'=>['2027-01-31T03:00:00Z','2027-02-28T03:00:00Z'],'spring'=>['2027-03-13T07:30:00Z'],'fall'=>['2027-11-06T05:30:00Z']] as $label=>$dates){$t=App\\Models\\TaskTemplate::findOrFail($ids[$label]);foreach($dates as $date)$out[$label][]=$service->advance($t,Carbon\\CarbonImmutable::parse($date))->setTimezone($t->timezone)->format('Y-m-d H:i');}echo json_encode($out);''',encoding='utf-8')
p=subprocess.run([os.environ.get('WM_PHP','php'),str(php)],cwd=R,capture_output=True,text=True,timeout=30);assert p.returncode==0,p.stderr;dates=json.loads(p.stdout)
verify('monthly cadence keeps Jan31 anchor across February',dates['monthly']==['2027-02-28 09:00','2027-03-31 09:00'])
verify('nonexistent and repeated DST wall times skipped explicitly',dates['spring']==['2027-03-15 02:30'] and dates['fall']==['2027-11-08 01:30'])
call(viewer,wp+'/task-templates/'+state['recurring']);post(viewer,wp+'/task-templates/'+state['recurring']+'/pause',{'revision':1},wp+'/task-templates/'+state['recurring'],expected=403)
verify('foreign keys remain valid',not rows('pragma foreign_key_check'));log('COMPLETE',recurrence_dates='Real domain calculation using models created via forms; no future worker execution claimed')
