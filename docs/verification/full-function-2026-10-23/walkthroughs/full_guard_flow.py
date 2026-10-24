from pathlib import Path
exec((Path(__file__).parent/'full_flow.py').read_text(encoding='utf-8').split("\nowner=login('demo1@example.test')")[0])
stage='delete-guard';journal=[];owner=login('demo1@example.test');other=login('demo4@example.test');wp=state['wp'];suffix=str(int(time.time()))
# Use a brand-new draft with no task/event/notification; only the new proposal relation prevents deletion.
u,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' proposal-only draft '+suffix,'status':'draft'});pid=u.split('/')[-1];pp=wp+'/projects/'+pid;page=wp+'/team-calendar?project_id='+pid
wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(state['wid'],state['oid']))[0]['id'];role=rows("select id from roles where workspace_id=? and name='Member'",(state['wid'],))[0]['id'];post(owner,pp+'/members',{'workspace_member_id':wm,'role_id':role,'status':'active'},pp+'/members')
try:
 form(owner,'/settings','settings-update',{'language':'vi','theme':'light','notifications[in_app]':'0'})
 form(other,page,'calendar-proposal',{'project_id':pid,'recipient_id':state['uid'],'title':prefix+' proposal-only '+suffix,'event_type':'MEETING','visibility':'private','timezone':'Asia/Ho_Chi_Minh','starts_at':'2029-01-12T10:00','ends_at':'2029-01-12T11:00'})
 verify('draft has proposal but no old blocking relations',bool(rows('select id from calendar_proposals where project_id=?',(pid,))) and not rows('select id from tasks where project_id=?',(pid,)) and not rows('select id from notifications where project_id=?',(pid,)) and not rows('select id from calendar_events where project_id=?',(pid,)))
 _,body=post(owner,pp,{'_method':'DELETE'},pp);verify('delete returns business feedback and keeps proposal history',bool(rows('select id from projects where id=?',(pid,))) and 'Project có dữ liệu liên quan' in body and 'SQLSTATE' not in body)
finally:form(owner,'/settings','settings-update',{'language':'vi','theme':'light','notifications[in_app]':'1'})
verify('foreign keys valid',not rows('pragma foreign_key_check'));log('COMPLETE')
