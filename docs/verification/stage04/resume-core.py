from pathlib import Path
import shutil
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
p=R/'docs/verification/stage04/http-walkthrough.py'
s=p.read_text(encoding='utf-8').replace("submit(worker,tpath+'/'+a,'blocker-update',{})", "submit(worker,tpath+'/'+a,'blocker-update',{'status':'resolved'})")
p.write_text(s,encoding='utf-8')
exec(s.split("observed('owner login'")[0])
state=json.loads(PRIVATE.joinpath('state.json').read_text());stamp=state['stamp'];wid=state['workspace_id'];pid=state['project_id'];group=state['group'];nested=state['nested'];a=state['a'];b=state['b'];m=state['milestone'];uids=state['uids']
wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
def restore(name):
 c=client(name+'-'+stamp);c[1].load(ignore_discard=True,ignore_expires=True);return c
owner=restore('owner');worker=restore('worker');reviewer=restore('reviewer');viewer=restore('viewer')
shutil.copy2(OUT/'http-flow.json',OUT/'core-before-helper-fix.json')
journal=json.loads(OUT.joinpath('http-flow.json').read_text(encoding='utf-8'))[:-2]
log('verification helper corrected',detail='The HTML hidden status field is now explicitly submitted; no application bypass.')
blocker=rows('select id from task_blockers where task_id=?',(a,))[0]['id']
exec(s[s.index("observed('resolve blocker through form'"):])
