"""Procedural live HTTP walkthrough; no TestCase, mocks or database reset.
Runs against the real Laravel server. Cookie jars stay in private local storage.
"""
from pathlib import Path
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, json, html, sqlite3, time

ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
PRIVATE=ROOT/'storage/app/private/stage02-review'
PRIVATE.mkdir(parents=True,exist_ok=True)
OUT=ROOT/'docs/verification/stage02'
OUT.mkdir(parents=True,exist_ok=True)
BASE='http://127.0.0.1:8123'
DB=ROOT/'database/work_management.sqlite'
stamp=str(int(time.time()))
PASSWORD='Review@123456'
journal=[]

def rows(sql,args=()):
    con=sqlite3.connect(f'file:{DB.as_posix()}?mode=ro',uri=True)
    con.row_factory=sqlite3.Row
    try: return [dict(row) for row in con.execute(sql,args).fetchall()]
    finally: con.close()

def log(label, **facts):
    entry={'step':label,**facts}
    journal.append(entry)
    OUT.joinpath('http-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(entry,ensure_ascii=True),flush=True)

def client(name):
    jar=http.cookiejar.LWPCookieJar(str(PRIVATE/(name+'.cookies')))
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    return opener,jar

def call(c,path, data=None):
    url=path if path.startswith('http') else BASE+path
    payload=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
    req=urllib.request.Request(url,data=payload,headers={'Accept':'text/html','User-Agent':'Local-Phase2-Flow-Review','Referer':getattr(c[0],'last_url',BASE+'/')})
    try:
        response=c[0].open(req,timeout=20)
    except urllib.error.HTTPError as e:
        response=e
    body=response.read().decode('utf-8',errors='replace')
    c[0].last_url=response.geturl()
    c[1].save(ignore_discard=True,ignore_expires=True)
    return response.code,response.geturl(),body

def token(body):
    match=re.search(r'name="_token"\s+value="([^"]+)"',body)
    if not match: raise RuntimeError('Rendered form has no CSRF field')
    return html.unescape(match[1])

def submit(c,page,marker,values):
    status,url,body=call(c,page)
    if status!=200: raise RuntimeError(f'Cannot load form {marker}: HTTP {status}, {url}')
    match=re.search(r'<form\b(?=[^>]*data-form="'+re.escape(marker)+r'")[^>]*action="([^"]+)"[^>]*>(.*?)</form>',body,re.S)
    if not match: raise RuntimeError(f'Form {marker} missing from rendered HTML at {url}')
    payload={'_token':token(match[2])}
    method=re.search(r'name="_method"\s+value="([^"]+)"',match[2])
    if method: payload['_method']=method[1]
    payload.update(values)
    return call(c,html.unescape(match[1]),payload)

def raw_post(c,path,values):
    code,url,body=call(c,'/workspaces')
    values={'_token':token(body),**values}
    return call(c,path,values)

def observed(label,response,expected=200,**facts):
    code,url,body=response
    safe_url=re.sub(r'(/invitations/)[A-Za-z0-9]{64}',r'\1[TOKEN]',url)
    errors=re.findall(r'<li>([^<]+)</li>',body)
    log(label,http=code,url=safe_url,errors=[html.unescape(x) for x in errors[:5]],**facts)
    if code!=expected:
        PRIVATE.joinpath('last-error.html').write_text(body,encoding='utf-8')
        raise RuntimeError(f'Flow stopped at {label}: HTTP {code} (body saved privately)')
    return body

owner=client('owner-'+stamp)
member=client('member-'+stamp)
outsider=client('outsider-'+stamp)
member_email='phase2.member.'+stamp+'@example.test'
outsider_email='phase2.outsider.'+stamp+'@example.test'
state={'stamp':stamp,'member_email':member_email,'outsider_email':outsider_email}
PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')

observed('guest redirected to login',call(owner,'/workspaces'))
observed('login with seed owner form',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
observed('register member through Blade form',submit(member,'/register','register',{'name':'Phase2 Member '+stamp,'email':member_email.upper(),'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh','status':'disabled'}))
user=rows('select id,email,status from users where email=?',(member_email,))[0]
personal=rows("select w.id,w.kind,m.status,r.name as role from workspaces w join workspace_members m on m.workspace_id=w.id join roles r on r.id=m.role_id where w.owner_id=? and w.kind='personal'",(user['id'],))
log('registration database read-back',user=user,personal_workspace=personal,settings=rows('select settings from user_settings where user_id=?',(user['id'],)))
observed('register outsider',submit(outsider,'/register','register',{'name':'Phase2 Outsider '+stamp,'email':outsider_email,'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}))

response=submit(owner,'/workspaces/create','workspace-create',{'name':'Phase2 HTTP Review '+stamp,'timezone':'Asia/Ho_Chi_Minh','owner_id':user['id'],'kind':'personal'})
observed('create workspace through real form',response)
wid=response[1].rstrip('/').split('/')[-1]
path='/workspaces/'+wid
state['workspace_id']=wid
PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
log('workspace database read-back',workspace=rows('select id,name,owner_id,kind from workspaces where id=?',(wid,)),roles=rows('select id,name,is_system from roles where workspace_id=?',(wid,)),members=rows('select user_id,status from workspace_members where workspace_id=?',(wid,)))

observed('CSRF missing rejected',call(owner,'/workspaces',{'name':'MUST NOT BE CREATED','timezone':'Asia/Ho_Chi_Minh'}),expected=419)
log('database after missing CSRF',created_count=rows("select count(*) as n from workspaces where name='MUST NOT BE CREATED'"))
observed('workspace form validation errors',submit(owner,'/workspaces/create','workspace-create',{'name':'','timezone':'not-a-zone'}))
observed('rename workspace',submit(owner,path+'/edit','workspace-edit',{'name':'Phase2 HTTP Reviewed '+stamp,'timezone':'Asia/Ho_Chi_Minh'}))
observed('outsider cannot access workspace',call(outsider,path),expected=403)
observed('member cannot access before invitation',call(member,path),expected=403)

perms=['workspace.view','workspace.manage_roles','workspace.manage_members']
observed('create delegated role',submit(owner,path+'/roles/create','role-create',{'name':'Delegated Review','permissions[]':perms}))
custom=rows('select id,name,permissions from roles where workspace_id=? and name=?',(wid,'Delegated Review'))[0]
custom_id=custom['id']
observed('rename custom role',submit(owner,path+'/roles/'+custom_id+'/edit','role-edit',{'name':'Delegated Reviewed','permissions[]':perms}))
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
observed('system Owner role edit rejected',call(owner,path+'/roles/'+roles['Owner']+'/edit'),expected=403)
observed('foreign role nested binding rejected',call(owner,path+'/roles/'+rows('select id from roles where workspace_id<>? limit 1',(wid,))[0]['id']+'/edit'),expected=404)
observed('owner role cannot be assigned by invitation',submit(owner,path+'/members','invitation-create',{'email':member_email,'role_id':roles['Owner']}))

inv=submit(owner,path+'/members','invitation-create',{'email':member_email,'role_id':custom_id})
body=observed('issue invitation through real form',inv)
invite_url=html.unescape(re.search(r'data-invitation-url="([^"]+)"',body)[1])
state['member_invitation']=invite_url
PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
log('membership not created before acceptance',count=rows('select count(*) as n from workspace_members where workspace_id=? and user_id=?',(wid,user['id'])))
observed('wrong email invitation acceptance rejected',raw_post(outsider,invite_url+'/accept',{}))
observed('view invitation as intended recipient',call(member,invite_url))
observed('accept invitation through real form',submit(member,invite_url,'invitation-accept',{}))
observed('replay consumed invitation rejected',raw_post(member,invite_url+'/accept',{}),expected=410)
membership=rows('select id,user_id,status,role_id from workspace_members where workspace_id=? and user_id=?',(wid,user['id']))[0]
mid=membership['id']
log('membership accepted once',membership=membership,count=rows('select count(*) as n from workspace_members where workspace_id=? and user_id=?',(wid,user['id'])))

observed('delegated actor cannot grant higher permission',submit(member,path+'/roles/create','role-create',{'name':'MUST NOT ESCALATE','permissions[]':['workspace.view','task.review']}))
log('database after escalation attempt',roles=rows("select id from roles where workspace_id=? and name='MUST NOT ESCALATE'",(wid,)))
observed('delegated actor can grant subset role',submit(member,path+'/roles/create','role-create',{'name':'Subset role','permissions[]':['workspace.view']}))
subset_id=rows("select id from roles where workspace_id=? and name='Subset role'",(wid,))[0]['id']
observed('delete unused custom role',raw_post(owner,path+'/roles/'+subset_id,{'_method':'DELETE'}))
observed('delete assigned role rejected',raw_post(owner,path+'/roles/'+custom_id,{'_method':'DELETE'}))
log('assigned role remains',roles=rows('select id from roles where id=?',(custom_id,)))

observed('suspend member',raw_post(owner,path+'/members/'+mid,{'_method':'PATCH','role_id':custom_id,'status':'suspended'}))
observed('suspended session blocked by middleware',call(member,path),expected=403)
observed('reactivate member',raw_post(owner,path+'/members/'+mid,{'_method':'PATCH','role_id':custom_id,'status':'active'}))
observed('reactivated member can access again',call(member,path))
owner_mid=rows('select id from workspace_members where workspace_id=? and user_id=(select owner_id from workspaces where id=?)',(wid,wid))[0]['id']
observed('owner membership cannot be removed',raw_post(owner,path+'/members/'+owner_mid,{'_method':'DELETE'}))
log('owner protected read-back',member=rows('select status,role_id from workspace_members where id=?',(owner_mid,)))

body=observed('create revocable invite',submit(owner,path+'/members','invitation-create',{'email':outsider_email,'role_id':roles['Viewer']}))
revoke_url=html.unescape(re.search(r'data-invitation-url="([^"]+)"',body)[1])
revoke_id=re.search(r'action="([^"]+/invitations/[a-f0-9-]{36})"[^>]*method="POST"',body)[1]
observed('revoke invitation',raw_post(owner,html.unescape(revoke_id),{'_method':'DELETE'}))
observed('revoked link gone',call(outsider,revoke_url),expected=410)

observed('remove member keeps history row',raw_post(owner,path+'/members/'+mid,{'_method':'DELETE'}))
log('membership left read-back',rows=rows('select id,status from workspace_members where id=?',(mid,)))
observed('removed member session blocked',call(member,path),expected=403)
body=observed('reinvite former member',submit(owner,path+'/members','invitation-create',{'email':member_email,'role_id':custom_id}))
rejoin_url=html.unescape(re.search(r'data-invitation-url="([^"]+)"',body)[1])
observed('rejoin through real form',submit(member,rejoin_url,'invitation-accept',{}))
log('rejoin reused membership',rows=rows('select id,status from workspace_members where workspace_id=? and user_id=?',(wid,user['id'])))

observed('change profile name',submit(member,'/profile','profile',{'name':'Phase2 Reviewed Member '+stamp,'email':member_email,'timezone':'Asia/Ho_Chi_Minh'}))
other_member_session=client('member-second-'+stamp)
observed('second session login before password change',submit(other_member_session,'/login','login',{'email':member_email,'password':PASSWORD}))
new_password='ReviewChanged@123456'
observed('change password through real profile form',submit(member,'/profile','password',{'current_password':PASSWORD,'password':new_password,'password_confirmation':new_password}))
observed('other session invalidated after password change',call(other_member_session,'/profile'))
observed('old password no longer logs in',submit(other_member_session,'/login','login',{'email':member_email,'password':PASSWORD}))
observed('new password logs in',submit(other_member_session,'/login','login',{'email':member_email,'password':new_password}))

observed('owner cannot leave without transfer',raw_post(owner,path+'/leave',{}))
observed('transfer ownership through actual form',submit(owner,path,'owner-transfer',{'member_id':mid}))
log('ownership transfer database read-back',workspace=rows('select owner_id from workspaces where id=?',(wid,)),members=rows('select m.user_id,m.status,r.name as role from workspace_members m join roles r on r.id=m.role_id where m.workspace_id=?',(wid,)))
observed('former owner no longer manages roles',call(owner,path+'/roles'),expected=403)
observed('new owner manages roles',call(member,path+'/roles'))

empty=submit(member,'/workspaces/create','workspace-create',{'name':'Disposable Phase2 '+stamp,'timezone':'Asia/Ho_Chi_Minh'})
observed('create empty disposable workspace',empty)
empty_path=urllib.parse.urlparse(empty[1]).path
empty_id=empty_path.rstrip('/').split('/')[-1]
observed('delete empty disposable workspace through form',submit(member,empty_path,'workspace-delete',{}))
log('disposable workspace deletion database read-back',workspace=rows('select id from workspaces where id=?',(empty_id,)))
observed('logout',submit(outsider,'/workspaces','logout',{}))
observed('profile after logout redirected to login',call(outsider,'/profile'))
log('final foreign key check',rows=rows('PRAGMA foreign_key_check'))
state.update({'member_id':user['id'],'membership_id':mid,'custom_role_id':custom_id,'new_password':new_password,'completed':True})
PRIVATE.joinpath('state.json').write_text(json.dumps(state),encoding='utf-8')
log('walkthrough complete',workspace_id=wid,member_email=member_email)
