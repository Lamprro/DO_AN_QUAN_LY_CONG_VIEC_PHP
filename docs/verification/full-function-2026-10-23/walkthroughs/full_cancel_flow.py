from pathlib import Path
exec((Path(__file__).parent/'full_flow.py').read_text(encoding='utf-8').split("\nowner=login('demo1@example.test')")[0])
stage='cancel-history';journal=[];owner=login('demo1@example.test');member=login('demo3@example.test');wp,pp,pid=state['wp'],state['pp'],state['pid'];page=wp+'/team-calendar?project_id='+pid;suffix=str(int(time.time()))
role=rows("select id from roles where workspace_id=? and name='Member'",(state['wid'],))[0]['id']
for uid in [state['mid'],state['oid']]:
 wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(state['wid'],uid))[0]['id'];pm=rows('select id,status from project_members where project_id=? and workspace_member_id=?',(pid,wm))
 if not pm or pm[0]['status']!='active':post(owner,pp+'/members',{'workspace_member_id':wm,'role_id':role,'status':'active'},pp+'/members')
vals={'project_id':pid,'recipient_id':state['mid'],'title':prefix+' historical attendee '+suffix,'event_type':'MEETING','visibility':'private','timezone':'Asia/Ho_Chi_Minh','starts_at':'2030-01-10T09:00','ends_at':'2030-01-10T10:00'}
form(owner,page,'calendar-proposal',vals);p=rows('select id from calendar_proposals where title=?',(vals['title'],))[0]['id'];form(member,page,'proposal-accept-'+p,{})
event=rows('select calendar_event_id from calendar_proposals where id=?',(p,))[0]['calendar_event_id'];ep=wp+'/calendar/'+event+'/edit'
form(member,ep,'calendar-save',{**vals,'task_id':'','status':'planned','all_day':'0','is_busy':'1','participants[]':[state['oid']]})
verify('event participant saved while eligible',json.loads(rows('select participants from calendar_events where id=?',(event,))[0]['participants'])==[state['oid']])
pm=rows('select pm.id from project_members pm join workspace_members wm on wm.id=pm.workspace_member_id where pm.project_id=? and wm.user_id=?',(pid,state['oid']))[0]['id'];post(owner,pp+'/members/'+pm,{'_method':'DELETE'},pp+'/members')
form(member,page,'proposal-cancel-'+p,{});row=rows('select status,participants from calendar_events where id=?',(event,))[0];verify('cancellation still works after attendee left without erasing history',row['status']=='cancelled' and json.loads(row['participants'])==[state['oid']])
verify('same accepted event cancelled, not a second event created',len(rows('select id from calendar_events where title=?',(vals['title'],)))==1 and rows('select status from calendar_proposals where id=?',(p,))[0]['status']=='cancelled')
verify('foreign keys valid',not rows('pragma foreign_key_check'));log('COMPLETE')
