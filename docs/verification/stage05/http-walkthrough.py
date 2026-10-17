"""Real HTML forms -> Laravel middleware/controllers/services -> SQLite read-back.
No TestCase, mock, model shortcut for writes, clock changes or DB reset.
Creates separate review users/workspace; keeps all previous data.
"""
from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
OUT=ROOT/'docs/verification/stage05';OUT.mkdir(parents=True,exist_ok=True)
PRIVATE=ROOT/'storage/app/private/stage05-review';PRIVATE.mkdir(parents=True,exist_ok=True)
journal=[]
owner=client('owner-'+stamp);worker=client('worker-'+stamp);reviewer=client('reviewer-'+stamp);outsider=client('outsider-'+stamp)
def verify(label,condition,**facts):
    log(label,verified=bool(condition),**facts)
    if not condition: raise RuntimeError(label)
def count(table,where='',params=()):return rows('select count(*) n from '+table+(' where '+where if where else ''),params)[0]['n']
def field(body,name):return html.unescape(re.search(r'name="'+re.escape(name)+r'"\s+value="([^"]*)"',body)[1])
observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
r=submit(owner,'/workspaces/create','workspace-create',{'name':'Phase5 Calendar Review '+stamp,'timezone':'Asia/Ho_Chi_Minh'});observed('create workspace',r)
wid=r[1].rstrip('/').split('/')[-1];wpath='/workspaces/'+wid;cpath=wpath+'/calendar'
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
r=submit(owner,wpath+'/projects/create','project-create',{'name':'Phase5 Timeline '+stamp,'status':'draft','description':'Stage 5 live review','start_date':'2026-12-01','deadline':'2026-12-31'});observed('create project',r)
pid=r[1].rstrip('/').split('/')[-1];ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
uids={};wms={};pms={}
for c,label,role in [(worker,'worker','Member'),(reviewer,'reviewer','Manager')]:
    email='phase5.'+label+'.'+stamp+'@example.test'
    observed('register '+label,submit(c,'/register','register',{'name':'Phase5 '+label,'email':email,'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}))
    uids[label]=rows('select id from users where email=?',(email,))[0]['id']
    b=observed('invite '+label,submit(owner,wpath+'/members','invitation-create',{'email':email,'role_id':roles[role]}));url=html.unescape(re.search(r'data-invitation-url="([^"]+)"',b)[1])
    observed('accept '+label,submit(c,url,'invitation-accept',{}));wms[label]=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,uids[label]))[0]['id']
    observed('project member '+label,submit(owner,ppath+'/members','project-member-create',{'workspace_member_id':wms[label],'role_id':roles[role],'status':'active'}))
    pms[label]=rows('select id from project_members where project_id=? and workspace_member_id=?',(pid,wms[label]))[0]['id']
observed('outsider login',submit(outsider,'/login','login',{'email':'demo5@example.test','password':'Demo@123456'}))
uid_owner=rows("select id from users where email='demo1@example.test'")[0]['id']
state={'stamp':stamp,'workspace_id':wid,'project_id':pid,'uids':uids,'wms':wms,'pms':pms}
def checkpoint():PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
checkpoint()
r=submit(owner,tpath+'/create','task-create',{'title':'P5 Calendar WORK','priority':'medium','task_type':'WORK','estimate_minutes':'90','assignee_id':uids['worker'],'reviewer_id':uids['reviewer']});observed('assign task real form',r)
tid=r[1].rstrip('/').split('/')[-1];detail=tpath+'/'+tid;state['task_id']=tid;checkpoint()
n=rows('select * from notifications where task_id=? and recipient_id=?',(tid,uids['worker']))
verify('assignment creates exactly one notification',len(n)==1,notifications=[{k:x[k] for k in ('id','recipient_id','type','deliveries','read_at')} for x in n])
first_notification=n[0]['id']
body=observed('worker inbox',call(worker,'/notifications'));verify('inbox and unread badge reflect persisted assignment',tid in body and 'data-unread-count>1<' in body)
observed('mark one read',raw_post(worker,'/notifications/'+first_notification+'/read',{'_method':'PATCH'}))
row=rows('select read_at,deliveries from notifications where id=?',(first_notification,))[0]
verify('read_at changes without changing deliveries',row['read_at'] is not None and row['deliveries']==n[0]['deliveries'])
observed('foreign recipient cannot mark read',raw_post(reviewer,'/notifications/'+first_notification+'/read',{'_method':'PATCH'}),expected=404)
observed('mark unread',raw_post(worker,'/notifications/'+first_notification+'/read',{'_method':'PATCH','unread':'1'}))
verify('mark unread persisted',rows('select read_at from notifications where id=?',(first_notification,))[0]['read_at'] is None)
settings={'language':'vi','theme':'dark','notifications[in_app]':'0'}
observed('settings actual form',submit(worker,'/settings','settings-update',settings))
stored=json.loads(rows('select settings from user_settings where user_id=?',(uids['worker'],))[0]['settings'])
verify('strict settings persisted',stored=={'language':'vi','theme':'dark','notifications':{'in_app':False,'email':False,'telegram':False}},settings=stored)
body=observed('dark setting applied to shell',call(worker,'/settings'));verify('theme hook actually rendered','data-theme="dark"' in body)
before=count('notifications','task_id=? and recipient_id=?',(tid,uids['worker']))
observed('comment while in-app disabled',submit(owner,detail,'comment-create',{'content':'P5 preference disabled'}))
verify('disabled preference prevents new worker notification',count('notifications','task_id=? and recipient_id=?',(tid,uids['worker']))==before)
observed('reject nested preference injection',submit(worker,'/settings','settings-update',{**settings,'notifications[token]':'bad'}))
observed('reject privilege injection',submit(worker,'/settings','settings-update',{**settings,'permissions[]':['task.assign']}))
observed('reject external channel enable',submit(worker,'/settings','settings-update',{**settings,'notifications[email]':'1'}))
verify('invalid settings left row unchanged',json.loads(rows('select settings from user_settings where user_id=?',(uids['worker'],))[0]['settings'])==stored)
observed('enable in-app',submit(worker,'/settings','settings-update',{**settings,'notifications[in_app]':'1','theme':'light'}))
observed('comment enabled',submit(owner,detail,'comment-create',{'content':'P5 enabled comment'}))
verify('enabled preference produces one row',count('notifications','task_id=? and recipient_id=?',(tid,uids['worker']))==before+1)
task_values={'title':'P5 Calendar WORK updated','priority':'medium','task_type':'WORK','estimate_minutes':'90','assignee_id':uids['worker'],'reviewer_id':uids['reviewer']}
before=count('notifications','task_id=?',(tid,))
observed('metadata save same assignment',submit(owner,detail+'/edit','task-edit',task_values))
verify('metadata update does not duplicate assignment notice',count('notifications','task_id=?',(tid,))==before)
events={}
def event_values(title,**extra):return {'title':title,'description':'P5 private text <script>unsafe</script>','event_type':'NOTE','project_id':'','task_id':'','starts_at':'2026-12-07T09:00','ends_at':'2026-12-07T10:00','timezone':'Asia/Ho_Chi_Minh','all_day':'0','is_busy':'0','visibility':'private','status':'planned',**extra}
def create_event(c,title,**extra):
    vals=event_values(title,**extra);r=submit(c,cpath+'/create'+('?all_day=1' if vals['all_day']=='1' else ''),'calendar-save',vals);observed('create event '+title,r)
    found=rows('select id from calendar_events where workspace_id=? and title=?',(wid,title))
    verify('event persisted '+title,len(found)==1)
    eid=found[0]['id'];events[eid]=vals;state['events']=events;checkpoint();return eid
def update_event(c,eid,**extra):
    body=observed('load event edit',call(c,cpath+'/'+eid+'/edit'))
    vals={**events[eid],**extra,'version':field(body,'version'),'_method':'PATCH'}
    r=raw_post(c,cpath+'/'+eid,vals);observed('save event '+eid,r)
    return vals
def reject_event(c,label,**extra):
    before=count('calendar_events','workspace_id=?',(wid,));body=observed(label,submit(c,cpath+'/create','calendar-save',event_values('REJECT '+label,**extra)))
    verify(label+' no row',count('calendar_events','workspace_id=?',(wid,))==before,has_error='role="alert"' in body)
busy=create_event(worker,'P5 secret BUSY',event_type='BUSY',is_busy='0',**{"participants[]":[uids['reviewer']]})
row=rows('select starts_at,ends_at,is_busy,user_id from calendar_events where id=?',(busy,))[0]
verify('UTC persisted and busy derived server-side',row=={'starts_at':'2026-12-07 02:00:00','ends_at':'2026-12-07 03:00:00','is_busy':1,'user_id':uids['worker']},event=row)
note=create_event(worker,'P5 overlapping NOTE',starts_at='2026-12-07T09:15',ends_at='2026-12-07T09:45')
extra=create_event(worker,'P5 extra capacity',event_type='EXTRA_AVAILABLE',is_busy='1',starts_at='2026-12-07T09:00',ends_at='2026-12-07T18:00')
verify('EXTRA_AVAILABLE cannot erase busy',rows('select is_busy from calendar_events where id=?',(extra,))[0]['is_busy']==0 and rows('select is_busy from calendar_events where id=?',(busy,))[0]['is_busy']==1)
reject_event(worker,'busy overlap',event_type='MEETING',starts_at='2026-12-07T09:30',ends_at='2026-12-07T10:30')
adjacent=create_event(worker,'P5 adjacent meeting',event_type='MEETING',starts_at='2026-12-07T10:00',ends_at='2026-12-07T11:00')
slot=create_event(worker,'P5 task block',event_type='TASK_BLOCK',project_id=pid,task_id=tid,visibility='project',starts_at='2026-12-07T13:00',ends_at='2026-12-07T14:00')
verify('calendar task history persisted',count('task_activities',"task_id=? and event_type='calendar.saved'",(tid,))==1)
reject_event(owner,'another assignee task block',event_type='TASK_BLOCK',project_id=pid,task_id=tid,visibility='project',starts_at='2026-12-08T13:00',ends_at='2026-12-08T14:00')
reject_event(worker,'task block missing task',event_type='TASK_BLOCK',project_id=pid)
reject_event(worker,'NOTE with task',project_id=pid,task_id=tid)
reject_event(worker,'project details workspace publication',project_id=pid,visibility='workspace')
reject_event(worker,'foreign participant',**{'participants[]':[rows("select id from users where email='demo5@example.test'")[0]['id']]})
reject_event(worker,'negative time range',ends_at='2026-12-07T08:00')
reject_event(worker,'timezone invalid',timezone='Mars/Invalid')
reject_event(worker,'owner spoof',user_id=uid_owner)
reject_event(worker,'spring DST gap',timezone='America/New_York',starts_at='2026-03-08T02:30',ends_at='2026-03-08T03:30')
reject_event(worker,'fall DST repeated hour',timezone='America/New_York',starts_at='2026-11-01T01:30',ends_at='2026-11-01T02:30')
all_day=create_event(worker,'P5 all day',all_day='1',starts_at='2026-12-09',ends_at='2026-12-10')
verify('all-day end-exclusive UTC',rows('select starts_at,ends_at,all_day from calendar_events where id=?',(all_day,))[0]=={'starts_at':'2026-12-08 17:00:00','ends_at':'2026-12-09 17:00:00','all_day':1})
overnight=create_event(worker,'P5 midnight',starts_at='2026-12-11T23:30',ends_at='2026-12-12T00:30')
shared=create_event(worker,'P5 shared workspace note',visibility='workspace',starts_at='2026-12-08T09:00',ends_at='2026-12-08T10:00')
query='?view=list&from=2026-12-07&to=2026-12-14'
body=observed('owner calendar sees only freebusy for private',call(owner,cpath+query))
verify('private titles IDs descriptions absent; busy interval present','P5 secret BUSY' not in body and busy not in body and 'P5 overlapping NOTE' not in body and 'P5 private text' not in body and 'Bận (riêng tư)' in body and 'P5 shared workspace note' in body)
observed('Owner cannot open private event',call(owner,cpath+'/'+busy),expected=403)
observed('Owner cannot edit another owner shared event',call(owner,cpath+'/'+shared+'/edit'),expected=403)
observed('outsider workspace calendar denied',call(outsider,cpath+query),expected=403)
other=rows('select id from workspaces where owner_id=? and id<>? limit 1',(uid_owner,wid))[0]['id']
observed('cross workspace event ID denied',call(owner,'/workspaces/'+other+'/calendar/'+busy),expected=404)
body=observed('Timeline actual task blocks',call(owner,ppath+'/timeline'+query));verify('timeline uses persisted task block',slot in body and tid in body and 'P5 task block' in body and 'P5 secret BUSY' not in body)
body=observed('week view',call(worker,cpath+'?view=week&date=2026-12-07'));verify('week has 7 day headings',body.count('<h2>2026-12-')==7)
body=observed('month view',call(worker,cpath+'?view=month&date=2026-12-07'));verify('month has 31 day headings',body.count('<h2>2026-12-')==31)
observed('oversized date range invalid',call(worker,cpath+'?from=2026-01-01&to=2027-01-01'))
old=field(observed('version before concurrent stale form',call(worker,cpath+'/'+note+'/edit')),'version')
vals=update_event(worker,note,title='P5 note revised');events[note].update(title='P5 note revised')
observed('stale update rejected',raw_post(worker,cpath+'/'+note,{**events[note],'title':'STALE MUST NOT PERSIST','_method':'PATCH','version':old}))
verify('stale form left row unchanged',rows('select title from calendar_events where id=?',(note,))[0]['title']=='P5 note revised')
observed('Member calendar.delete permission enforced',raw_post(worker,cpath+'/'+note,{'_method':'DELETE','version':old}),expected=403)
vals=update_event(worker,busy,status='cancelled');events[busy]['status']='cancelled'
replacement=create_event(worker,'P5 released slot reused',event_type='BUSY')
body=observed('cancelled default hidden',call(worker,cpath+query));verify('cancelled event omitted by default',busy not in body)
body=observed('cancelled explicit included',call(worker,cpath+query+'&include_cancelled=1'));verify('cancelled event opt-in',busy in body)
temp=create_event(owner,'P5 owner disposable',starts_at='2026-12-15T01:00',ends_at='2026-12-15T02:00')
body=observed('owner delete form',call(owner,cpath+'/'+temp));observed('owner deletes own event',submit(owner,cpath+'/'+temp,'calendar-delete',{'version':field(body,'version')}));verify('event deleted',not rows('select id from calendar_events where id=?',(temp,)))
# Real task status transitions must drive review notifications without changing calendar into actual time.
for c,to in [(worker,'IN_PROGRESS'),(worker,'REVIEW'),(reviewer,'DONE')]:
    current=rows('select status from tasks where id=?',(tid,))[0]['status']
    observed('status '+to,raw_post(c,detail+'/status',{'_method':'PATCH','status':to,'expected_status':current,'reason':''}))
    verify('status persisted '+to,rows('select status from tasks where id=?',(tid,))[0]['status']==to)
verify('review request recipient persisted',count('notifications',"task_id=? and recipient_id=? and type='task.status_changed'",(tid,uids['reviewer']))==1)
vals=update_event(worker,slot,status='cancelled');events[slot]['status']='cancelled'
verify('closed task schedule can be cancelled without changing task or actual',rows('select status from calendar_events where id=?',(slot,))[0]['status']=='cancelled' and rows('select status from tasks where id=?',(tid,))[0]['status']=='DONE' and count('time_entries','task_id=?',(tid,))==0)
before=count('notifications','task_id=?',(tid,))
observed('no-op status retry',raw_post(reviewer,detail+'/status',{'_method':'PATCH','status':'DONE','expected_status':'DONE'}))
verify('same status retry no notification duplication',count('notifications','task_id=?',(tid,))==before)
observed('suspend project reviewer',raw_post(owner,ppath+'/members/'+pms['reviewer'],{'_method':'PATCH','role_id':roles['Manager'],'status':'suspended'}))
body=observed('revoked project inbox hides historical titles',call(reviewer,'/notifications'));verify('revoked inbox and unread badge safe',tid not in body and 'P5 Calendar WORK' not in body and 'data-unread-count>0<' in body)
review_notice=rows("select id from notifications where task_id=? and recipient_id=? limit 1",(tid,uids['reviewer']))[0]['id']
observed('revoked project cannot mutate old inbox row',raw_post(reviewer,'/notifications/'+review_notice+'/read',{'_method':'PATCH'}),expected=404)
observed('restore project reviewer',raw_post(owner,ppath+'/members/'+pms['reviewer'],{'_method':'PATCH','role_id':roles['Manager'],'status':'active'}))
observed('mark all visible read',submit(worker,'/notifications','notification-read-all',{}))
verify('mark-all persisted while deliveries retained',count('notifications','recipient_id=? and read_at is null',(uids['worker'],))==0)
verify('notification dedupe keys unique',not rows('select dedupe_key,count(*) n from notifications group by dedupe_key having count(*)>1'))
verify('no external channel falsely sent',all(all(d['channel']=='in_app' for d in json.loads(x['deliveries'])) for x in rows('select deliveries from notifications where workspace_id=?',(wid,))))
verify('foreign key integrity',not rows('pragma foreign_key_check'))
state['events']=events;state['review_notification']=review_notice;checkpoint()
log('Stage 5 real HTTP walkthrough complete',workspace_id=wid,project_id=pid,task_id=tid)
