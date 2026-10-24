from pathlib import Path
exec((Path(__file__).parent/'production_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
import quopri
stage='account-security';journal=[];PHP=os.environ.get('WM_PHP','php')
original=(R/'.env').read_bytes();pwd='ReviewAccount@123456';newpwd='ReviewAccount@654321';email='account'+str(int(time.time()))+'@example.test'
def envset(**values):
 text=(R/'.env').read_text(encoding='utf-8')
 for k,v in values.items():
  text=re.sub(r'^'+re.escape(k)+r'=.*\n?', '',text,flags=re.M)+'\n'+k+'='+v+'\n'
 (R/'.env').write_text(text,encoding='utf-8')
 subprocess.run([PHP,'artisan','config:clear'],cwd=R,capture_output=True,check=True)
 time.sleep(2)
 for _ in range(30):
  try:urllib.request.urlopen(BASE+'/login',timeout=3).read();return
  except OSError:time.sleep(.2)
 raise RuntimeError('server restart')
def mailurl(kind):
 raw=(R/'storage/logs/laravel.log').read_bytes();body=raw.decode('utf-8',errors='replace')
 # Laravel LogTransport renders a decoded body even when MIME headers name quoted-printable.
 matches=re.findall(r'href="(http://127\.0\.0\.1:8128/'+kind+r'[^"\s]+)"',body)
 assert matches,'mail link was not delivered to local log transport'
 return html.unescape(matches[-1])
def safeget(c,url,expected=200):
 try:resp=c.open(url,timeout=30)
 except urllib.error.HTTPError as e:resp=e
 body=resp.read().decode('utf-8',errors='replace');c.last=resp.geturl()
 log('HTTP private account link',status=resp.code,token_redacted=True)
 assert resp.code==expected,resp.code;return resp.geturl(),body
def reset(c,url,password):
 _,body=safeget(c,url)
 value=html.unescape(re.search(r'name="token" value="([^"]+)"',body)[1])
 return call(c,'/reset-password',{'_token':token(body),'email':email,'token':value,'password':password,'password_confirmation':password})
def sql(q,args=()):
 assert R!=MAIN and R in DB.parents
 c=sqlite3.connect(DB);c.execute(q,args);c.commit();c.close()
try:
 envset(REQUIRE_VERIFIED_EMAIL='true')
 c=client();url,body=form(c,'/register','register',{'name':'Account readiness review','email':email.upper(),'password':pwd,'password_confirmation':pwd,'timezone':'Asia/Ho_Chi_Minh'})
 user=rows('select * from users where email=?',(email,))[0];uid=user['id']
 verify('registration requires verification',url.endswith('/email/verify') and user['email_verified_at'] is None)
 url,_=call(c,'/workspaces');verify('unverified cannot enter business pages',url.endswith('/email/verify'))
 signed=mailurl('email/verify/');safeget(c,signed.replace('signature=','signature=x'),403)
 verify('tampered signature changes no verification',rows('select email_verified_at from users where id=?',(uid,))[0]['email_verified_at'] is None)
 other=client();form(other,'/login','login',{'email':'demo2@example.test','password':'Demo@123456'});safeget(other,signed,403)
 verify('another account cannot verify this email',rows('select email_verified_at from users where id=?',(uid,))[0]['email_verified_at'] is None)
 url,_=safeget(c,signed);verify('signed link verifies actual user row',url.endswith('/workspaces') and rows('select email_verified_at from users where id=?',(uid,))[0]['email_verified_at'] is not None)
 oldtab=client();form(oldtab,'/login','login',{'email':email,'password':pwd})
 recover=client();_,b=form(recover,'/forgot-password','forgot-password',{'email':'missing'+email});generic='Nếu email thuộc tài khoản đang hoạt động'
 verify('unknown email receives generic response',generic in b and not rows('select email from password_reset_tokens where email=?',('missing'+email,)))
 _,b=form(recover,'/forgot-password','forgot-password',{'email':email.upper()});url=mailurl('reset-password/');row=rows('select * from password_reset_tokens where email=?',(email,))[0]
 rawtoken=urllib.parse.urlparse(url).path.split('/')[-1]
 verify('known email same response and token hashed at rest',generic in b and row['token']!=rawtoken and len(row['token'])>40)
 sql("update password_reset_tokens set created_at=datetime('now','-61 minutes') where email=?",(email,))
 before=rows('select password from users where id=?',(uid,));_,b=reset(recover,url,newpwd)
 verify('expired token rejects password mutation',before==rows('select password from users where id=?',(uid,)) and 'hết hạn' in b)
 form(recover,'/forgot-password','forgot-password',{'email':email});url=mailurl('reset-password/')
 nexturl,b=reset(recover,url,newpwd)
 verify('valid reset consumes token and invalidates all existing sessions',nexturl.endswith('/login') and not rows('select email from password_reset_tokens where email=?',(email,)) and not rows('select id from sessions where user_id=?',(uid,)))
 oldurl,_=call(oldtab,'/workspaces');verify('old browser actually redirected to login',oldurl.endswith('/login'))
 before=rows('select password,remember_token from users where id=?',(uid,));_,b=reset(recover,url,pwd)
 verify('single-use token replay rejected',before==rows('select password,remember_token from users where id=?',(uid,)) and 'đã dùng' in b)
 nexturl,_=form(recover,'/login','login',{'email':email,'password':pwd});verify('old password cannot login',nexturl.endswith('/login'))
 nexturl,_=form(recover,'/login','login',{'email':email,'password':newpwd});verify('new password authenticates',nexturl.endswith('/workspaces'))
 # A reset link issued before a profile password change must no longer work.
 guest=client();form(guest,'/forgot-password','forgot-password',{'email':email});url=mailurl('reset-password/')
 post(recover,'/password',{'_method':'PUT','current_password':newpwd,'password':pwd,'password_confirmation':pwd},'/profile')
 verify('profile password change revokes outstanding reset links',not rows('select email from password_reset_tokens where email=?',(email,)))
 # Wrong CSRF has no side effect.
 before=rows('select password from users where id=?',(uid,));call(guest,'/reset-password',{'email':email,'token':rawtoken,'password':newpwd,'password_confirmation':newpwd},expected=419)
 verify('CSRF rejection retains password',before==rows('select password from users where id=?',(uid,)))
 # Changing email requires proof of the current password and requires new verification.
 changed='changed'+email
 post(recover,'/profile',{'_method':'PATCH','name':'Account readiness review','email':changed,'timezone':'Asia/Ho_Chi_Minh','current_password':pwd},'/profile')
 u=rows('select * from users where id=?',(uid,))[0];verify('email change revokes verification and blocks business access',u['email']==changed and u['email_verified_at'] is None and call(recover,'/workspaces')[0].endswith('/email/verify'))
 safeget(recover,signed,403);verify('old email verification link invalid after email change',rows('select email_verified_at from users where id=?',(uid,))[0]['email_verified_at'] is None)
 form(recover,'/email/verify','verification-send',{});signed2=mailurl('email/verify/');safeget(recover,signed2)
 verify('new email can be verified through real mail link',rows('select email_verified_at from users where id=?',(uid,))[0]['email_verified_at'] is not None)
 response=urllib.request.urlopen(BASE+'/forgot-password');headers=response.headers;response.read()
 verify('sensitive pages no cache or referrer and cannot be framed externally',all([headers.get('Referrer-Policy')=='no-referrer','no-store' in headers.get('Cache-Control',''),headers.get('X-Frame-Options')=='SAMEORIGIN',headers.get('X-Content-Type-Options')=='nosniff']))
 verify('no foreign key errors after account flows',not rows('pragma foreign_key_check'))
 log('COMPLETE',mail_transport='log in private local installation; actual external delivery remains unverified',no_test_classes=True)
finally:
 (R/'.env').write_bytes(original);subprocess.run([PHP,'artisan','config:clear'],cwd=R,capture_output=True)
