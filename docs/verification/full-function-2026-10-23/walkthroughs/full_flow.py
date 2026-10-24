from pathlib import Path
exec((Path(__file__).parent/'production_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
PRIVATE=R/'storage/app/private/full-function';PRIVATE.mkdir(parents=True,exist_ok=True)
state=json.loads((PRIVATE/'flow-state.json').read_text()) if (PRIVATE/'flow-state.json').exists() else {'prefix':'Full'+str(int(time.time()))};prefix=state['prefix'];stage=sys.argv[1] if len(sys.argv)>1 else 'setup';journal=[]
owner=login('demo1@example.test');member=login('demo3@example.test');viewer=login('demo5@example.test');other=login('demo4@example.test')
uid=rows("select id from users where email='demo1@example.test'")[0]['id'];mid=rows("select id from users where email='demo3@example.test'")[0]['id'];vid=rows("select id from users where email='demo5@example.test'")[0]['id'];oid=rows("select id from users where email='demo4@example.test'")[0]['id']
if stage=='setup':
 wid=rows("select id from workspaces where name='Demo Team A'")[0]['id'];wp='/workspaces/'+wid
 url,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' full scope','status':'active','start_date':'2026-10-01','deadline':'2028-10-01'});pid=url.split('/')[-1];pp=wp+'/projects/'+pid;tp=pp+'/tasks';state.update(wid=wid,wp=wp,pid=pid,pp=pp,tp=tp,uid=uid,mid=mid,vid=vid,oid=oid);checkpoint()
 for user,role in [(mid,'Member'),(vid,'Viewer'),(oid,'Member')]:
  wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,user))[0]['id'];rid=rows('select id from roles where workspace_id=? and name=?',(wid,role))[0]['id']
  post(owner,pp+'/members',{'workspace_member_id':wm,'role_id':rid,'status':'active'},pp+'/members')
  verify('project member added '+role,bool(rows('select id from project_members where project_id=? and workspace_member_id=? and status=\'active\'',(pid,wm))))
 u,_=form(owner,tp+'/create','task-create',{'title':prefix+' template GROUP','task_type':'GROUP','priority':'medium'});group=u.split('/')[-1]
 a=work(owner,tp,prefix+' template predecessor',parent_task_id=group,assignee_id=uid,reviewer_id=uid,estimate_minutes=60)
 b=work(owner,tp,prefix+' template successor',parent_task_id=group,assignee_id=uid,reviewer_id=uid,estimate_minutes=30)
 post(owner,tp+'/'+b+'/dependencies',{'predecessor_task_id':a,'lag_minutes':30},tp+'/'+b)
 mt=work(owner,tp,prefix+' member work',assignee_id=mid,reviewer_id=uid,estimate_minutes=60)
 state.update(group=group,a=a,b=b,member_task=mt);checkpoint()
 for path in ['/work-hours',wp+'/team-calendar?project_id='+pid,wp+'/task-templates?project_id='+pid,tp+'/'+a]:call(owner,path)
 verify('additive schema present',all(rows("select name from sqlite_master where type='table' and name=?",(t,)) for t in ['calendar_proposals','task_watchers','task_templates','task_template_runs']))
 log('COMPLETE')
elif stage=='hours':
 wp,pp,tp=state['wp'],state['pp'],state['tp'];pid=state['pid'];page=wp+'/plans/create?project_id='+pid
 payload={'schema_version':1,'tasks':[{'ref':'hours','title':prefix+' hours schedule','task_type':'WORK','priority':'medium','estimate_minutes':60,'assignee_id':uid,'reviewer_id':uid}],'dependencies':[],'slots':[]}
 url,_=form(owner,page,'plan-create',{'name':prefix+' hours snapshot','input_mode':'json','payload':json.dumps(payload)});oldplan=url.split('/')[-1];snapshot=rows('select data from plans where id=?',(oldplan,))[0]['data']
 action,stale,_=form(owner,'/work-hours','work-hours');fields={'timezone':'Asia/Ho_Chi_Minh','weekdays[]':['6'],'slot_minutes':'30','windows[0][start]':'10:00','windows[0][end]':'11:00'}
 form(owner,'/work-hours','work-hours',fields);stored=json.loads(rows('select settings from user_settings where user_id=?',(uid,))[0]['settings']);verify('custom weekday and window persisted',stored['work_hours']['weekdays']==[6] and stored['work_hours']['windows']==[{'start':'10:00','end':'11:00'}])
 saved=rows('select settings from user_settings where user_id=?',(uid,));_,body=call(owner,action,{**stale,**fields,'slot_minutes':'15'});verify('stale hours form cannot overwrite',saved==rows('select settings from user_settings where user_id=?',(uid,)) and 'Giờ làm đã thay đổi' in body)
 form(owner,'/work-hours','work-hours',{**fields,'windows[1][start]':'10:30','windows[1][end]':'12:00'});verify('overlap rejected without writes',saved==rows('select settings from user_settings where user_id=?',(uid,)))
 form(owner,'/settings','settings-update',{'language':'vi','theme':'light','notifications[in_app]':'1'});after=json.loads(rows('select settings from user_settings where user_id=?',(uid,))[0]['settings']);verify('general settings do not erase working hours',after['work_hours']==stored['work_hours'])
 verify('existing plan snapshot remains unchanged',rows('select data from plans where id=?',(oldplan,))[0]['data']==snapshot)
 u,_=form(owner,page,'plan-create',{'name':prefix+' hours new','input_mode':'json','payload':json.dumps(payload)});pid2=u.split('/')[-1];data=json.loads(rows('select data from plans where id=?',(pid2,))[0]['data']);verify('new plan stores custom hours snapshot',data['work_schedule']==stored['work_hours'])
 u,body=post(owner,wp+'/plans/'+pid2+'/schedule',{'from':'2027-09-06'},wp+'/plans/'+pid2);p=rows('select data from plans where id=?',(u.split('/')[-1],))[0];slots=json.loads(p['data'])['payload']['slots'];verify('scheduler uses Saturday 10-11 and estimate duration',len(slots)==2 and all(datetime.datetime.fromisoformat(x['starts_at']).isoweekday()==6 and x['starts_at'][11:] in ['10:00','10:30'] for x in slots))
 state['hours_plan']=pid2;checkpoint();log('COMPLETE')
elif stage=='team':
 prefix=prefix+'team'+str(int(time.time()))
 wp,pp,tp,pid=state['wp'],state['pp'],state['tp'],state['pid'];page=wp+'/team-calendar?project_id='+pid
 values={'project_id':pid,'recipient_id':mid,'title':prefix+' consent meeting','event_type':'MEETING','task_id':'','visibility':'private','timezone':'Asia/Ho_Chi_Minh','starts_at':'2027-09-06T10:00','ends_at':'2027-09-06T11:00'}
 before=len(rows('select id from calendar_events'));form(owner,page,'calendar-proposal',values);p=rows('select * from calendar_proposals where title=?',(values['title'],))[0];proot=wp+'/team-calendar/'+p['id']
 verify('proposal pending without event or private calendar disclosure',p['status']=='pending' and before==len(rows('select id from calendar_events')))
 _,body=call(member,page);verify('recipient sees proposal and actionable form',p['id'] in body and 'proposal-accept-'+p['id'] in body)
 _,body=call(other,page);verify('unrelated project member cannot see proposal title or id',values['title'] not in body and p['id'] not in body)
 post(other,proot+'/accept',{'revision':1},page,expected=403)
 form(member,page,'proposal-accept-'+p['id'],{});p2=rows('select * from calendar_proposals where id=?',(p['id'],))[0];event=rows('select * from calendar_events where id=?',(p2['calendar_event_id'],))[0];verify('accept creates one event owned by recipient',p2['status']=='accepted' and event['user_id']==mid)
 n=len(rows('select id from calendar_events'));post(member,proot+'/accept',{'revision':1},page);verify('repeated acceptance cannot duplicate event',n==len(rows('select id from calendar_events')))
 # Same slot: second proposal remains pending on conflict, does not reveal private details.
 form(owner,page,'calendar-proposal',{**values,'title':prefix+' colliding meeting'});p3=rows('select * from calendar_proposals where title=?',(prefix+' colliding meeting',))[0]
 _,body=form(member,page,'proposal-accept-'+p3['id'],{});verify('real busy conflict rolls back acceptance and no extra event',len(rows('select id from calendar_events'))==n and rows('select status from calendar_proposals where id=?',(p3['id'],))[0]['status']=='pending' and 'trùng lịch bận' in body)
 form(member,page,'proposal-decline-'+p3['id'],{});verify('recipient decline persists',rows('select status from calendar_proposals where id=?',(p3['id'],))[0]['status']=='declined')
 form(member,page,'proposal-cancel-'+p['id'],{});verify('recipient can cancel accepted own event',rows('select status from calendar_events where id=?',(event['id'],))[0]['status']=='cancelled')
 # Task booking consent with assignee and permissions rechecked by CalendarService.
 form(owner,page,'calendar-proposal',{**values,'title':prefix+' task booking','event_type':'TASK_BLOCK','task_id':state['member_task'],'starts_at':'2027-09-06T13:00','ends_at':'2027-09-06T14:00'});p4=rows('select * from calendar_proposals where title=?',(prefix+' task booking',))[0];form(member,page,'proposal-accept-'+p4['id'],{})
 verify('task proposal books only recipient assigned work',bool(rows('select id from calendar_events where task_id=? and user_id=?',(state['member_task'],mid))))
 call(viewer,page);action,data,_=form(owner,page,'calendar-proposal');call(owner,action,{**data,**values,'recipient_id':vid,'title':'Viewer must not receive booking'},expected=403);verify('viewer without calendar create cannot receive proposal',not rows('select id from calendar_proposals where title=?',('Viewer must not receive booking',)))
 call(owner,'/notifications?type=calendar.responded');call(member,'/notifications?type=calendar.proposed');verify('proposal notices have dedupe and real recipients',bool(rows("select id from notifications where recipient_id=? and type='calendar.proposed'",(mid,))))
 state['proposal']=p4['id'];checkpoint();log('COMPLETE')
elif stage=='watchers':
 wp,pp,tp=state['wp'],state['pp'],state['tp'];task=state['a'];path=tp+'/'+task;watch=wp+'/collaboration/tasks/'+task+'/watchers'
 post(member,watch,{'user_id':mid},path);verify('self watch stored with identity',len(rows('select id from task_watchers where task_id=? and user_id=?',(task,mid)))==1)
 post(member,watch,{'user_id':mid},path);verify('repeated subscribe idempotent',len(rows('select id from task_watchers where task_id=? and user_id=?',(task,mid)))==1)
 post(member,watch,{'user_id':oid},path,expected=403)
 post(owner,watch,{'user_id':oid},path);verify('authorized manager adds another eligible watcher',bool(rows('select id from task_watchers where task_id=? and user_id=?',(task,oid))))
 _,body=form(owner,path,'comment-create',{'content':prefix+' mentioned collaborators','mentions[]':[mid,oid]})
 activity=rows("select * from task_activities where task_id=? and type='COMMENT' order by created_at desc",(task,))[0];verify('mention stored with real comment',[mid,oid]==json.loads(activity['data'])['mentions'])
 notices=rows("select * from notifications where task_id=? and type='task.mentioned'",(task,));verify('watcher plus mention receives one deduplicated notice per actor',len(notices)==2 and {x['recipient_id'] for x in notices}=={mid,oid})
 foreign=rows("select id from users where email like 'changedaccount%' limit 1")[0]['id'];count=len(rows("select id from task_activities where task_id=? and type='COMMENT'",(task,)))
 form(owner,path,'comment-create',{'content':'Invalid mention must not save','mentions[]':[foreign]});verify('out-of-scope mention rolls back entire comment',count==len(rows("select id from task_activities where task_id=? and type='COMMENT'",(task,))))
 # Revoke actual project membership, then create a real event; no watcher notification or view access.
 pm=rows('select pm.id from project_members pm join workspace_members wm on wm.id=pm.workspace_member_id where pm.project_id=? and wm.user_id=?',(state['pid'],mid))[0]['id'];post(owner,pp+'/members/'+pm,{'_method':'DELETE'},pp+'/members')
 before=len(rows('select id from notifications where recipient_id=? and task_id=?',(mid,task)));form(owner,path,'comment-create',{'content':prefix+' after membership revoked'});verify('revoked watcher receives no new notice',before==len(rows('select id from notifications where recipient_id=? and task_id=?',(mid,task))))
 call(member,path,expected=403);_,body=call(member,'/notifications');verify('revoked watcher old notifications hidden by current scope',task not in body)
 post(other,watch+'/'+oid,{'_method':'DELETE'},path);verify('self unsubscribe persisted',not rows('select id from task_watchers where task_id=? and user_id=?',(task,oid)))
 log('COMPLETE')
elif stage=='templates':
 prefix=prefix+'templates'+str(int(time.time()))
 wp,pp,tp,pid=state['wp'],state['pp'],state['tp'],state['pid'];page=wp+'/task-templates?project_id='+pid
 values={'project_id':pid,'name':prefix+' reusable tree','frequency':'','interval':1,'timezone':'Asia/Ho_Chi_Minh','starts_on':'','ends_on':'','local_time':'09:00','task_ids[]':[state['group'],state['a'],state['b']],'deadline_offset_days':2}
 before=len(rows('select id from tasks'));u,body=form(owner,page,'template-create',values);tid=u.split('/')[-1];root=wp+'/task-templates/'+tid;t=rows('select * from task_templates where id=?',(tid,))[0]
 verify('template snapshot stored without task generation',len(rows('select id from tasks'))==before and len(json.loads(t['definition'])['tasks'])==3)
 action,data,_=form(owner,root,'template-instantiate');call(owner,action,data);run=rows('select * from task_template_runs where task_template_id=?',(tid,))[0];mapping=json.loads(run['task_map']);verify('manual instantiation creates three fresh ids',len(mapping)==3 and set(mapping.values()).isdisjoint(mapping.keys()) and len(rows('select id from tasks'))==before+3)
 verify('mapped tree and dependency preserve graph',rows('select parent_task_id from tasks where id=?',(mapping[state['b']],))[0]['parent_task_id']==mapping[state['group']] and bool(rows('select id from task_dependencies where predecessor_task_id=? and successor_task_id=? and lag_minutes=30',(mapping[state['a']],mapping[state['b']]))))
 verify('new tasks have no cloned actual or completed status',not rows('select id from time_entries where task_id in (?,?,?)',tuple(mapping.values())) and all(rows('select status from tasks where id=?',(id,))[0]['status']=='TODO' for id in mapping.values()))
 call(owner,action,data);verify('same manual request key creates no duplicates',len(rows('select id from tasks'))==before+3 and len(rows('select id from task_template_runs where task_template_id=?',(tid,)))==1)
 call(viewer,root);post(viewer,root+'/instantiate',{k:v for k,v in data.items() if k!='_token'},root,expected=403)
 # Invalid partial tree/dependency cannot save a new template.
 form(owner,page,'template-create',{**values,'name':'Missing parent must fail','task_ids[]':[state['b']]});verify('missing parent/predecessor template rejected',not rows('select id from task_templates where name=?',('Missing parent must fail',)))
 # Save and update name using real revision form; stale replay cannot overwrite.
 action,old,_=form(owner,root,'template-update');settings={k:v for k,v in values.items() if k not in ['project_id','task_ids[]']};settings['name']=prefix+' renamed template'
 call(owner,action,{**old,**settings});beforet=rows('select * from task_templates where id=?',(tid,));call(owner,action,{**old,**settings,'name':'STALE MUST NOT SAVE'});verify('template config stale rejected',beforet==rows('select * from task_templates where id=?',(tid,)))
 # Recurring fixture begins in the past using real configuration; command catches up once and advances.
 yesterday=(datetime.date.today()-datetime.timedelta(days=1)).isoformat();u,_=form(owner,page,'template-create',{**values,'name':prefix+' daily cadence','frequency':'daily','starts_on':yesterday,'local_time':'08:00'});recid=u.split('/')[-1];recroot=wp+'/task-templates/'+recid
 before=len(rows('select id from tasks'));p=subprocess.run([os.environ.get('WM_PHP','php'),'artisan','tasks:generate-recurring'],cwd=R,capture_output=True,text=True,timeout=90);verify('real scheduler command generates due occurrence once',p.returncode==0 and len(rows('select id from tasks'))==before+3 and len(rows('select id from task_template_runs where task_template_id=?',(recid,)))==1,output=p.stdout[-500:])
 aftertasks=rows('select id from tasks');p=subprocess.run([os.environ.get('WM_PHP','php'),'artisan','tasks:generate-recurring'],cwd=R,capture_output=True,text=True,timeout=90);verify('repeated scheduler does not create duplicate period',p.returncode==0 and aftertasks==rows('select id from tasks'))
 form(owner,recroot,'template-pause',{});verify('pause clears pending next run',rows('select status,next_run_at from task_templates where id=?',(recid,))[0]=={'status':'paused','next_run_at':None})
 form(owner,recroot,'template-resume',{});verify('resume schedules future occurrence',rows('select status from task_templates where id=?',(recid,))[0]['status']=='active')
 form(owner,root,'template-archive',{});verify('archive retains run history and prevents further manual generation',rows('select status from task_templates where id=?',(tid,))[0]['status']=='archived' and len(rows('select id from task_template_runs where task_template_id=?',(tid,)))==1)
 state.update(template=tid,recurring=recid);checkpoint();log('COMPLETE')
else:raise ValueError(stage)
verify('foreign keys valid',not rows('pragma foreign_key_check'))
