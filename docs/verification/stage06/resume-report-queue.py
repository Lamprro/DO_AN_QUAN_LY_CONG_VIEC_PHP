from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
flow_source=(ROOT/'docs/verification/stage06/http-walkthrough.py').read_text(encoding='utf-8')
exec(flow_source.split("observed('owner login'")[0])
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));stamp=state['stamp'];wid=state['workspace_id'];pid=state['project_id'];uids=state['uids'];wms=state['wms'];pms=state['pms'];sid=state['snapshot_id'];original=state['original_metrics'];slots=state['slots']
for label in ['owner','worker','reviewer','outsider']:
    cclient=client(label+'-'+stamp);cclient[1].load(ignore_discard=True,ignore_expires=True);globals()[label]=cclient
for label,tid in state['tasks'].items():globals()[label]=tid
wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
filters={'from':'2026-10-01','to':'2026-11-01','project_id':pid,'subject_user_id':'','include_archived':'0'};dash=wpath+'/dashboard?'+urllib.parse.urlencode(filters)
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('report-queue-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
csv_initial=observed('Resume CSV actual response',call(owner,wpath+'/reports/'+sid+'/export'))
csv_rows=list(csv.reader(io.StringIO(csv_initial.lstrip('\ufeff'))))
verify('CSV quoting parsed correctly and formula-safe',any(row and row[0]=="'=P6 Worker" for row in csv_rows) and ['WORK DONE','1'] in csv_rows)
exec(flow_source[flow_source.index("observed('snapshot unsupported PATCH'"):])
