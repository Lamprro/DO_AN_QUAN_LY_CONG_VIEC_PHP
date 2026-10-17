from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
source=(ROOT/'docs/verification/stage05/http-walkthrough.py').read_text(encoding='utf-8')
exec(source.split("observed('owner login'")[0])
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));stamp=state['stamp'];wid=state['workspace_id'];pid=state['project_id'];tid=state['task_id'];uids=state['uids'];slot=state['active_slot_id']
wpath='/workspaces/'+wid;cpath=wpath+'/calendar';ppath=wpath+'/projects/'+pid
for label in ['owner','worker','reviewer']:
    c=client(label+'-'+stamp);c[1].load(ignore_discard=True,ignore_expires=True);globals()[label]=c
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('final-readback.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
event=rows("select id,title,description,starts_at,ends_at,event_type,is_busy,source from calendar_events where workspace_id=? and title='P5 Browser Review Meeting'",(wid,))[0]
verify('browser update persisted and event scope/UTC retained',event['description']=='Đã tạo và cập nhật qua form Blade; chờ duyệt phần 5.' and event['event_type']=='MEETING' and event['starts_at']=='2026-12-07 08:00:00' and event['ends_at']=='2026-12-07 09:00:00' and event['is_busy']==1,event=event)
notices=rows('select id,read_at,deliveries from notifications where workspace_id=? and recipient_id=(select id from users where email=?)',(wid,'demo1@example.test'))
verify('browser marked exactly one of two status notices read',len(notices)==2 and sum(x['read_at'] is not None for x in notices)==1,notifications=notices)
body=observed('inbox final server render',call(owner,'/notifications'));verify('persisted unread badge matches browser','data-unread-count>1<' in body)
body=observed('final timeline',call(owner,ppath+'/timeline?view=list&from=2026-12-07&to=2026-12-14'));verify('timeline still displays real slot, bar and task link',slot in body and 'timeline-bar' in body and state['active_task_id'] in body and 'P5 Secret Task Slot' not in body)
body=observed('final edit form hidden scope contract',call(worker,cpath+'/'+slot+'/edit'))
verify('immutable scope selects disabled but hidden fields submitted',all(re.search(r'<select[^>]*id="'+key+r'"[^>]*disabled',body) for key in ['event_type','project_id','task_id']) and all(re.search(r'<input[^>]*type="hidden"[^>]*name="'+key+r'"',body) for key in ['event_type','project_id','task_id','version']))
body=observed('calendar final privacy projection',call(owner,cpath+'?view=list&from=2026-12-07&to=2026-12-14'));verify('calendar retains privacy and shared entries','Bận (riêng tư)' in body and 'P5 Secret Task Slot' not in body and 'P5 Browser Review Meeting' in body)
verify('all actors restored active and worker timezone restored',all(x['status']=='active' for x in rows('select status from workspace_members where workspace_id=?',(wid,))) and all(x['status']=='active' for x in rows('select status from project_members where project_id=?',(pid,))) and rows('select timezone from users where id=?',(uids['worker'],))[0]['timezone']=='Asia/Ho_Chi_Minh')
verify('no new test classes',not any((ROOT/'tests').rglob('*Calendar*')) and not any((ROOT/'tests').rglob('*Notification*')))
verify('FK final read-back',not rows('pragma foreign_key_check'))
log('Stage 5 final read-back complete',workspace_id=wid,project_id=pid,active_slot_id=slot)
