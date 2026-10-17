from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
source=(ROOT/'docs/verification/stage05/final-readback.py').read_text(encoding='utf-8')
exec(source.split("event=rows(")[0])
journal=[]
def log(label,**facts):
    entry={'step':label,**facts};journal.append(entry);OUT.joinpath('schema-scope-final.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
columns=[x['name'] for x in rows('pragma table_info(workspaces)')]
verify('workspace schema has no archived_at; removed only invalid no-op attribute reference','archived_at' not in columns,columns=columns)
observed('archive actual project through its form',submit(owner,ppath,'project-archive',{}))
try:
    observed('actual project archive still forbids calendar edit',call(worker,cpath+'/'+slot+'/edit'),expected=403)
finally:
    observed('restore actual project through form',submit(owner,ppath,'project-archive',{}))
body=observed('calendar edit available after project restore',call(worker,cpath+'/'+slot+'/edit'));verify('real scope checks retained','data-form="calendar-save"' in body)
verify('foreign keys final',not rows('pragma foreign_key_check'))
log('Schema and project scope final verification complete')
