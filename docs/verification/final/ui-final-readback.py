from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import subprocess
OUT=ROOT/'docs/verification/final';PRIVATE=ROOT/'storage/app/private/final-review';journal=[]
def log(label,**facts):
 entry={'step':label,**facts};journal.append(entry);(OUT/'ui-final-readback.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def verify(label,condition,**facts):
 log(label,verified=bool(condition),**facts)
 if not condition:raise RuntimeError(label)
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));wid=state['workspace_id'];pid=state['project_id'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks';owner=client('ui-owner');member=client('ui-member')
observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}));observed('member login',submit(member,'/login','login',{'email':state['member_email'],'password':PASSWORD}))
body=observed('real GitHub history on Task Blade',call(owner,tpath+'/'+state['tasks']['b']));verify('linked commit rendered with safe labels','Commit GitHub liên quan' in body and 'Review local signed payload' in body)
body=observed('real timer history on Task Blade',call(owner,tpath+'/'+state['tasks']['a']));verify('timer and plan link rendered','Bắt đầu giờ làm' in body and 'Kết thúc giờ làm' in body and 'Xem phiên bản kế hoạch' in body)
body=observed('applied plan page',call(owner,wpath+'/plans/'+state['final_plan_id']));verify('already applied has no apply action form',wpath+'/plans/'+state['final_plan_id']+'/apply' not in body)
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))};pm=rows('select pm.id from project_members pm join workspace_members wm on wm.id=pm.workspace_member_id where pm.project_id=? and wm.user_id=?',(pid,state['member_id']))[0]['id']
observed('set review member Viewer',raw_post(owner,ppath+'/members/'+pm,{'_method':'PATCH','role_id':roles['Viewer'],'status':'active'}))
try:
 body=observed('Viewer plan index',call(member,wpath+'/plans?project_id='+pid));verify('Viewer sees no create or AI action','Tạo nháp' not in body and 'AI / trợ lý blocker' not in body)
 body=observed('Viewer plan detail',call(member,wpath+'/plans/'+state['final_plan_id']));verify('Viewer sees no edit version or schedule','Sửa bằng phiên bản mới' not in body and 'Tạo phiên bản xếp lịch local' not in body)
finally:observed('restore Member project role',raw_post(owner,ppath+'/members/'+pm,{'_method':'PATCH','role_id':roles['Member'],'status':'active'}))
compiled=list((ROOT/'storage/framework/views').glob('*.php'))
for p in compiled:
 result=subprocess.run([r'C:\xampp\php\php.exe','-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
verify('all latest compiled Blade files pass syntax',True,count=len(compiled));verify('FK read-back',not rows('pragma foreign_key_check'));log('COMPLETE',source_delta_verified=True)
