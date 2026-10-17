from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
source=(ROOT/'docs/verification/stage05/http-walkthrough.py').read_text(encoding='utf-8')
exec(source.split("observed('owner login'")[0])
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));stamp=state['stamp']
wid=state['workspace_id'];pid=state['project_id'];tid=state['task_id'];uids=state['uids'];wms=state['wms'];pms=state['pms']
wpath='/workspaces/'+wid;cpath=wpath+'/calendar';ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';detail=tpath+'/'+tid
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))};uid_owner=rows("select id from users where email='demo1@example.test'")[0]['id']
for label in ['owner','worker','reviewer','outsider']:
    c=client(label+'-'+stamp);c[1].load(ignore_discard=True,ignore_expires=True);globals()[label]=c
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('boundaries.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def checkpoint():PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
exec(source[source.index('events={}'):source.index("busy=create_event(")])
events=state['events'];query='?view=list&from=2026-12-07&to=2026-12-14'
observed('guest calendar redirects to login',call(client('guest-'+stamp),cpath))
observed('calendar CSRF required',call(owner,cpath,event_values('NO CSRF')),expected=419)
r=submit(owner,tpath+'/create','task-create',{'title':'P5 Timeline Active WORK','priority':'medium','task_type':'WORK','estimate_minutes':'60','assignee_id':uids['worker'],'reviewer_id':uids['reviewer']});observed('create active timeline work',r)
newtask=r[1].rstrip('/').split('/')[-1];state['active_task_id']=newtask;checkpoint()
slot=create_event(worker,'P5 Active Timeline Slot',event_type='TASK_BLOCK',project_id=pid,task_id=newtask,visibility='project',starts_at='2026-12-07T13:00',ends_at='2026-12-07T14:00')
state['active_slot_id']=slot;checkpoint()
body=observed('timeline with calculated bars',call(owner,ppath+'/timeline'+query))
verify('timeline bar and task navigation visible','timeline-bar' in body and slot in body and newtask in body)
event_before=rows('select starts_at,ends_at from calendar_events where id=?',(slot,))[0]
update_event(worker,slot,starts_at='2026-12-07T10:30',ends_at='2026-12-07T11:30')
verify('reschedule conflict rolls back',rows('select starts_at,ends_at from calendar_events where id=?',(slot,))[0]==event_before)
# Reassignment uses the existing TaskService and creates one notice for the new recipient.
before=count('notifications','task_id=? and recipient_id=?',(newtask,uids['reviewer']))
values={'title':'P5 Timeline Active WORK','priority':'medium','task_type':'WORK','estimate_minutes':'60','assignee_id':uids['reviewer'],'reviewer_id':uids['reviewer']}
observed('reassign real task',submit(owner,tpath+'/'+newtask+'/edit','task-edit',values))
verify('new assignee receives exactly one notice',count('notifications','task_id=? and recipient_id=?',(newtask,uids['reviewer']))==before+1)
update_event(worker,slot,starts_at='2026-12-07T14:00',ends_at='2026-12-07T15:00')
verify('old assignee cannot reschedule',rows('select starts_at,ends_at from calendar_events where id=?',(slot,))[0]==event_before)
observed('restore task assignment',submit(owner,tpath+'/'+newtask+'/edit','task-edit',{**values,'assignee_id':uids['worker']}))
body=observed('event content escaped',call(worker,cpath+'/'+slot));verify('event description escaped','&lt;script&gt;unsafe&lt;/script&gt;' in body and '<script>unsafe</script>' not in body)
# Profile timezone is the display source; event UTC data must remain unchanged.
email=rows('select email from users where id=?',(uids['worker'],))[0]['email']
observed('change display timezone via profile',submit(worker,'/profile','profile',{'name':'Phase5 worker','email':email,'timezone':'UTC'}))
body=observed('event renders in UTC',call(worker,cpath+'/'+slot));verify('same instant different timezone','07/12/2026 06:00' in body and 'UTC' in body and rows('select starts_at,ends_at from calendar_events where id=?',(slot,))[0]==event_before)
observed('restore display timezone',submit(worker,'/profile','profile',{'name':'Phase5 worker','email':email,'timezone':'Asia/Ho_Chi_Minh'}))
# Project calendar permissions remain independent of the workspace role.
observed('create workspace-only custom role',submit(owner,wpath+'/roles/create','role-create',{'name':'P5 Workspace View Only','permissions[]':['workspace.view']}))
custom=rows('select id from roles where workspace_id=? and name=?',(wid,'P5 Workspace View Only'))[0]['id']
observed('workspace role view-only',raw_post(owner,wpath+'/members/'+wms['reviewer'],{'_method':'PATCH','role_id':custom,'status':'active'}))
body=observed('project calendar via independent project permissions',call(reviewer,cpath+query));verify('workspace-only content hidden but project slot shown',slot in body and 'P5 shared workspace note' not in body)
body=observed('project permission allows event form',call(reviewer,cpath+'/create'));verify('create form remains available through project rights','data-form="calendar-save"' in body and pid in body)
observed('restore workspace Manager',raw_post(owner,wpath+'/members/'+wms['reviewer'],{'_method':'PATCH','role_id':roles['Manager'],'status':'active'}))
# Private project event must be absent from Timeline, including its ID and task link.
private_slot=create_event(worker,'P5 Secret Task Slot',event_type='TASK_BLOCK',project_id=pid,task_id=newtask,starts_at='2026-12-08T13:00',ends_at='2026-12-08T14:00')
body=observed('private task block hidden from timeline',call(owner,ppath+'/timeline'+query));verify('timeline private block no ID/title',private_slot not in body and 'P5 Secret Task Slot' not in body)
# Cross-workspace owner conflicts are checked, even if the existing event is private elsewhere.
personal=rows("select id from workspaces where owner_id=? and kind='personal' limit 1",(uid_owner,))[0]['id'];personal_cal='/workspaces/'+personal+'/calendar'
existing=count('calendar_events','user_id=?',(uid_owner,))
observed('busy conflicts across workspaces',submit(owner,personal_cal+'/create','calendar-save',event_values('CROSS WORKSPACE CONFLICT',event_type='BUSY',starts_at='2026-12-07T15:30',ends_at='2026-12-07T16:30')))
verify('cross-workspace conflict inserted nothing',count('calendar_events','user_id=?',(uid_owner,))==existing)
# Input restrictions do not silently mutate the event scope/type.
old=dict(events[slot]);version=field(observed('version for scope/type guard',call(worker,cpath+'/'+slot+'/edit')),'version')
observed('type change denied',raw_post(worker,cpath+'/'+slot,{**old,'event_type':'NOTE','task_id':'','_method':'PATCH','version':version}))
verify('type/task relation unchanged',rows('select event_type,task_id from calendar_events where id=?',(slot,))[0]=={'event_type':'TASK_BLOCK','task_id':newtask})
reject_event(worker,'too many participants',**{'participants[]':[uid_owner]*21})
reject_event(worker,'TASK_BLOCK all-day',event_type='TASK_BLOCK',project_id=pid,task_id=newtask,all_day='1',starts_at='2026-12-17',ends_at='2026-12-18')
observed('archive project',submit(owner,ppath,'project-archive',{}))
observed('archived project event edit forbidden',call(worker,cpath+'/'+slot+'/edit'),expected=403)
observed('archived project create forbidden',submit(worker,cpath+'/create','calendar-save',event_values('ARCHIVED MUST NOT PERSIST',project_id=pid,starts_at='2026-12-17T09:00',ends_at='2026-12-17T10:00')),expected=403)
observed('restore project',submit(owner,ppath,'project-archive',{}))
browser_event=rows("select id,starts_at,ends_at,is_busy,source from calendar_events where workspace_id=? and title='P5 Browser Review Meeting'",(wid,))
verify('browser form persisted real UTC row',len(browser_event)==1 and browser_event[0]['starts_at']=='2026-12-07 08:00:00' and browser_event[0]['ends_at']=='2026-12-07 09:00:00' and browser_event[0]['is_busy']==1,event=browser_event)
verify('FK integrity after boundary flows',not rows('pragma foreign_key_check'))
state['events']=events;checkpoint();log('Stage 5 boundaries complete',workspace_id=wid,project_id=pid)
