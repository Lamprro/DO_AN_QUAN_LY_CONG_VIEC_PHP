from pathlib import Path
import subprocess

ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
helpers=(ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0]
exec(helpers)

def log(label, **facts):
    entry={'step':label,**facts}
    journal.append(entry)
    OUT.joinpath('targeted-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(entry,ensure_ascii=True),flush=True)

def artisan(code):
    run=subprocess.run(['php','artisan','tinker','--execute='+code,'--no-ansi'],cwd=ROOT,capture_output=True,text=True)
    if run.returncode: raise RuntimeError(run.stderr or run.stdout)

state=json.loads(PRIVATE.joinpath('state.json').read_text())
email=state['outsider_email']
actor=client('targeted-'+stamp)
guest=client('targeted-guest-'+stamp)
count=rows('select count(*) as n from users where email=?',(email,))[0]['n']
body=observed('duplicate registration rejected',submit(guest,'/register','register',{'name':'Duplicate','email':email.upper(),'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}))
if rows('select count(*) as n from users where email=?',(email,))[0]['n']!=count: raise RuntimeError('Duplicate user created')
observed('login review outsider',submit(actor,'/login','login',{'email':email,'password':PASSWORD}))
uid=rows('select id from users where email=?',(email,))[0]['id']
personal=rows("select id from workspaces where owner_id=? and kind='personal'",(uid,))[0]['id']
viewer=rows("select id from roles where workspace_id=? and name='Viewer'",(personal,))[0]['id']
body=observed('personal workspace invite rejected',raw_post(actor,'/workspaces/'+personal+'/invitations',{'email':'phase2.personal@example.test','role_id':viewer}))
body=observed('personal workspace deletion rejected',raw_post(actor,'/workspaces/'+personal,{'_method':'DELETE'}))
log('personal workspace retained',workspace=rows('select id,kind from workspaces where id=?',(personal,)))
before=rows('select password,email from users where id=?',(uid,))[0]
observed('wrong current password rejected',submit(actor,'/profile','password',{'current_password':'wrong','password':'NeverSaved@123456','password_confirmation':'NeverSaved@123456'}))
observed('array password inputs rejected without server error',submit(actor,'/profile','password',{'current_password[]':['wrong'],'password[]':['bad'],'password_confirmation[]':['bad']}))
observed('array current_password on email change rejected',submit(actor,'/profile','profile',{'name':'Review outsider','email':'phase2.changed.'+stamp+'@example.test','timezone':'Asia/Ho_Chi_Minh','current_password[]':['bad']}))
after=rows('select password,email from users where id=?',(uid,))[0]
if before!=after: raise RuntimeError('Invalid request changed credentials')
log('invalid credential changes not persisted',unchanged=True)
new_email='phase2.changed.'+stamp+'@example.test'
observed('email change with current password',submit(actor,'/profile','profile',{'name':'Review outsider','email':new_email,'timezone':'Asia/Ho_Chi_Minh','current_password':PASSWORD}))
log('email update database read-back',user=rows('select id,email,email_verified_at,status from users where id=?',(uid,)))
try:
    artisan("App\\Models\\User::findOrFail('"+uid+"')->update(['status'=>'disabled']);")
    observed('disabled user existing session blocked',call(actor,'/profile'),expected=403)
    observed('disabled user session ended',call(actor,'/profile'))
finally:
    artisan("App\\Models\\User::findOrFail('"+uid+"')->update(['status'=>'active']);")
for n in range(6):
    body=observed('bad login attempt '+str(n+1),submit(guest,'/login','login',{'email':'phase2.limit.'+stamp+'@example.test','password':'wrong'}))
if 'Đăng nhập sai nhiều lần' not in body: raise RuntimeError('Login throttling message missing')
log('login limiter reached',attempts=6)
log('foreign key check',rows=rows('pragma foreign_key_check'))
log('targeted walkthrough complete')
