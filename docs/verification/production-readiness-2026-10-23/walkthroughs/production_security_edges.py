from pathlib import Path
exec((Path(__file__).parent/'production_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
stage='security-edges';journal=[];PHP=os.environ.get('WM_PHP','php');original=(R/'.env').read_bytes()
user=rows("select * from users where email like 'changedaccount%@example.test' order by created_at desc limit 1")[0];email=user['email'];uid=user['id']
def configure(**values):
 text=(R/'.env').read_text(encoding='utf-8')
 for k,v in values.items():text=re.sub(r'^'+re.escape(k)+r'=.*\n?', '',text,flags=re.M)+'\n'+k+'='+v+'\n'
 (R/'.env').write_text(text,encoding='utf-8');subprocess.run([PHP,'artisan','config:clear'],cwd=R,capture_output=True,check=True);time.sleep(2)
def sql(q,args):
 assert R in DB.parents
 c=sqlite3.connect(DB);c.execute(q,args);c.commit();c.close()
try:
 # Force a real transport failure on loopback; never transmit messages to external servers.
 configure(MAIL_MAILER='smtp',MAIL_HOST='127.0.0.1',MAIL_PORT='1',MAIL_USERNAME='null',MAIL_PASSWORD='null',MAIL_URL='null')
 c=client();_,body=form(c,'/forgot-password','forgot-password',{'email':email})
 verify('SMTP outage returns generic response and removes unusable reset token','Nếu email thuộc tài khoản đang hoạt động' in body and not rows('select * from password_reset_tokens where email=?',(email,)))
 logtext=(R/'storage/logs/laravel.log').read_text(encoding='utf-8');safe_line=[line for line in logtext.splitlines() if 'account_mail.delivery_failed' in line][-1]
 verify('mail failure diagnostic excludes address and reset URL',email not in safe_line and '/reset-password/' not in safe_line)
 sql("update users set status='disabled' where id=?",(uid,))
 _,body=form(c,'/forgot-password','forgot-password',{'email':email})
 verify('disabled account cannot obtain reset token','Nếu email thuộc tài khoản đang hoạt động' in body and not rows('select * from password_reset_tokens where email=?',(email,)))
 sql("update users set status='active' where id=?",(uid,))
 configure(MAIL_MAILER='log')
 form(c,'/forgot-password','forgot-password',{'email':email});raw=(R/'storage/logs/laravel.log').read_text(encoding='utf-8');url=html.unescape(re.findall(r'href="(http://127\.0\.0\.1:8128/reset-password/[^"\s]+)"',raw)[-1]);t=urllib.parse.urlparse(url).path.split('/')[-1]
 sql("update users set status='disabled' where id=?",(uid,));before=rows('select password from users where id=?',(uid,))
 _,body=call(c,'/forgot-password');call(c,'/reset-password',{'_token':token(body),'email':email,'token':t,'password':'NoWrite@123456','password_confirmation':'NoWrite@123456'})
 verify('token issued before disabling account cannot change password',before==rows('select password from users where id=?',(uid,)))
 sql("update users set status='active' where id=?",(uid,))
 # Deliberately broken DB path on the isolated installation proves the error page has no DB dependency.
 configure(APP_ENV='production',APP_DEBUG='false',DB_DATABASE='"'+str(R/'database/missing-for-outage.sqlite').replace('\\','/')+'"')
 try:resp=urllib.request.urlopen(BASE+'/login',timeout=25)
 except urllib.error.HTTPError as e:resp=e
 body=resp.read().decode('utf-8',errors='replace');verify('database outage renders safe Vietnamese 500 without stack trace',resp.code==500 and 'Hệ thống đang gặp sự cố' in body and 'SQLSTATE' not in body and 'Stack trace' not in body)
 restore=R.parent/'restored';check=restore/'storage/app/private/key-check.php';check.write_text("<?php require dirname(__DIR__,3).'/vendor/autoload.php';$app=require dirname(__DIR__,3).'/bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$n=0;foreach(App\\Models\\IntegrationConnection::whereNotNull('credentials_encrypted')->cursor() as $c){$data=$c->credentials_encrypted;if(is_array($data)&&!empty($data))$n++;}if($n<1)exit(1);echo 'decryption verified';",encoding='utf-8')
 proc=subprocess.run([PHP,str(check)],cwd=restore,capture_output=True,text=True,timeout=30);verify('restored APP_KEY actually decrypts existing integration credentials',proc.returncode==0 and 'decryption verified' in proc.stdout)
 log('COMPLETE',external_mail_sent=False)
finally:
 (R/'.env').write_bytes(original);subprocess.run([PHP,'artisan','config:clear'],cwd=R,capture_output=True)
 sql("update users set status='active' where id=?",(uid,))
