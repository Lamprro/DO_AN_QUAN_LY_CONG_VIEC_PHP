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
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('timeline-inbox-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def checkpoint():PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
exec(source[source.index('events={}'):source.index("busy=create_event(")])
events=state['events']
for variable,title in [('busy','P5 secret BUSY'),('note','P5 overlapping NOTE'),('slot','P5 task block')]:globals()[variable]=next(k for k,v in events.items() if v['title']==title)
query='?view=list&from=2026-12-07&to=2026-12-14'
log('Resume after adding task navigation to Timeline rows')
exec(source[source.index("body=observed('Timeline actual task blocks'"):])
