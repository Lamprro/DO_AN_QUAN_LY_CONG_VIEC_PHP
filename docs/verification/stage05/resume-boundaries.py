from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
boundary_source=(ROOT/'docs/verification/stage05/boundary-walkthrough.py').read_text(encoding='utf-8')
exec(boundary_source.split("observed('guest calendar")[0])
newtask=state['active_task_id'];slot=state['active_slot_id']
values={'title':'P5 Timeline Active WORK','priority':'medium','task_type':'WORK','estimate_minutes':'60','assignee_id':uids['reviewer'],'reviewer_id':uids['reviewer']}
journal=[]
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('boundaries-final.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
verify('old assignee reschedule truly left original UTC values',rows('select starts_at,ends_at from calendar_events where id=?',(slot,))[0]=={'starts_at':'2026-12-07 06:00:00','ends_at':'2026-12-07 07:00:00'})
event_before={'starts_at':'2026-12-07 06:00:00','ends_at':'2026-12-07 07:00:00'}
exec(boundary_source[boundary_source.index("observed('restore task assignment'"):])
