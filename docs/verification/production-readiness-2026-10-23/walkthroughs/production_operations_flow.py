from pathlib import Path
exec((Path(__file__).parent/'production_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
import shutil,os,tempfile
stage='operations';journal=[];PHP=os.environ.get('WM_PHP','php');original=(R/'.env').read_bytes();work=R.parent
def artisan(*args,expected=0,cwd=R):
 p=subprocess.run([PHP,'artisan',*args],cwd=cwd,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=120)
 log('Artisan',command=' '.join(args),exit_code=p.returncode,verified=p.returncode==expected)
 if p.returncode!=expected:raise RuntimeError((args,p.stdout[-2000:],p.stderr[-2000:]))
 return p.stdout
def configure(**values):
 text=(R/'.env').read_text(encoding='utf-8')
 for k,v in values.items():text=re.sub(r'^'+re.escape(k)+r'=.*\n?', '',text,flags=re.M)+'\n'+k+'='+v+'\n'
 (R/'.env').write_text(text,encoding='utf-8');artisan('config:clear');artisan('config:cache');time.sleep(2)
def dbhash(db):
 c=sqlite3.connect(db);c.row_factory=sqlite3.Row;out={}
 for t, in c.execute("select name from sqlite_master where type='table' and name not like 'sqlite_%'"):
  values=sorted(json.dumps(dict(r),sort_keys=True) for r in c.execute('select * from "'+t+'"'))
  out[t]=hashlib.sha256(json.dumps(values).encode()).hexdigest()
 c.close();return out
try:
 pre=json.loads(artisan('app:preflight','--json'));verify('local preflight verifies DB/cache/lock/session wiring',pre['configuration_passed'])
 prod=json.loads(artisan('app:preflight','--production','--json',expected=1));(OUT/'preflight-production.json').write_text(json.dumps(prod,ensure_ascii=False,indent=2),encoding='utf-8')
 verify('unsafe local configuration cannot claim production readiness',not prod['configuration_passed'] and any(c['check']=='no_demo_accounts' and not c['passed'] for c in prod['checks']))
 # Remove only review environment heartbeat keys to prove failure on a stopped installation.
 p=R/'storage/app/private/heartbeat-reset.php';p.write_text("<?php require dirname(__DIR__, 3).'/vendor/autoload.php'; $app=require dirname(__DIR__, 3).'/bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); foreach(['scheduler','worker:database:work-management-ai','worker:database:work-management-reminders'] as $name) Illuminate\\Support\\Facades\\Cache::forget('ops:heartbeat:'.$name);",encoding='utf-8')
 subprocess.run([PHP,str(p)],cwd=R,capture_output=True,check=True)
 stopped=json.loads(artisan('app:preflight','--runtime','--json',expected=1));verify('missing scheduler and workers detected',sum(c['check'].startswith('heartbeat.') and not c['passed'] for c in stopped['checks'])==3)
 artisan('schedule:run')
 for q in ['work-management-ai','work-management-reminders']:artisan('queue:work','database','--queue='+q,'--stop-when-empty','--tries=3')
 ready=json.loads(artisan('app:preflight','--runtime','--json'));verify('actual scheduler and worker invocations produce fresh heartbeats',ready['configuration_passed'])
 schedule=artisan('schedule:list');verify('recovery and token cleanup registered in actual schedule',all(n in schedule for n in ['ai:recover-stale','auth:clear-resets','tasks:remind','ops:heartbeat']))
 # Real production config in the isolated installation; it is deliberately NOT production-certified.
 configure(APP_ENV='production',APP_DEBUG='false',REQUIRE_VERIFIED_EMAIL='false')
 bad=urllib.request.Request(BASE+'/login',headers={'Host':'untrusted.example.test'})
 try:res=urllib.request.urlopen(bad,timeout=20)
 except urllib.error.HTTPError as e:res=e
 content=res.read().decode('utf-8',errors='replace');verify('production rejects hostile Host without debug details',res.code==400 and 'vendor/' not in content and 'Stack trace' not in content)
 before=dbhash(DB);artisan('db:seed','--force',expected=1);verify('production demo seeding refuses without writes',before==dbhash(DB))
 p=subprocess.run([PHP,'scripts/setup-local.php'],cwd=R,capture_output=True,text=True,timeout=60);verify('local setup refuses production before mutations',p.returncode==1 and 'disabled outside' in p.stderr and before==dbhash(DB))
 # Restore local profile, then exercise backup/restore using this installation only.
 (R/'.env').write_bytes(original);artisan('config:clear');artisan('config:cache');time.sleep(2)
 destination=work/'private-snapshot'
 artisan('app:backup-sqlite',str(destination),'--workers-stopped',expected=1)
 artisan('down','--retry=60')
 try:
  res=urllib.request.urlopen(BASE+'/login',timeout=20)
 except urllib.error.HTTPError as e:res=e
 content=res.read().decode('utf-8',errors='replace');verify('maintenance gives standalone Vietnamese 503',res.code==503 and 'Hệ thống đang bảo trì' in content and 'SQLSTATE' not in content)
 artisan('app:backup-sqlite',str(R/'public/unsafe-backup'),'--workers-stopped',expected=1)
 verify('backup refuses web/source destination',not (R/'public/unsafe-backup').exists())
 before=dbhash(DB);artisan('app:backup-sqlite',str(destination),'--workers-stopped')
 manifest=json.loads((destination/'manifest.json').read_text());verify('snapshot matches every table and private attachment hash',before==dbhash(destination/'database.sqlite') and all(hashlib.sha256((destination/n).read_bytes()).hexdigest()==h for n,h in manifest['files'].items()) and len(manifest['files'])>1)
 verify('backup contains no env or key',not (destination/'.env').exists() and not manifest['app_key_included'])
 restore=work/'restored';restore.mkdir()
 for folder in ['app','config','database/migrations','resources','routes','public','lang','vendor']:
  if (R/folder).is_dir():shutil.copytree(R/folder,restore/folder,ignore=shutil.ignore_patterns('__pycache__'))
 for filename in ['artisan','composer.json','composer.lock','bootstrap/app.php','bootstrap/providers.php']:
  dest=restore/filename;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(R/filename,dest)
 for folder in ['bootstrap/cache','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs']:(restore/folder).mkdir(parents=True,exist_ok=True)
 restored_db=restore/'database/restored.sqlite';shutil.copy2(destination/'database.sqlite',restored_db);shutil.copytree(destination/'task-files',restore/'storage/app/private/task-files')
 text=original.decode('utf-8');text=re.sub(r'^DB_DATABASE=.*$', 'DB_DATABASE="'+restored_db.as_posix()+'"',text,flags=re.M);text=re.sub(r'^APP_URL=.*$', 'APP_URL=http://127.0.0.1:8129',text,flags=re.M);(restore/'.env').write_text(text,encoding='utf-8')
 artisan('config:cache',cwd=restore);artisan('route:cache',cwd=restore);artisan('view:cache',cwd=restore)
 log_file=open(work/'restored-server.log','w',encoding='utf-8');server=subprocess.Popen([PHP,'artisan','serve','--host=127.0.0.1','--port=8129'],cwd=restore,stdout=log_file,stderr=subprocess.STDOUT,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
 try:
  for _ in range(40):
   try:urllib.request.urlopen('http://127.0.0.1:8129/login',timeout=3).read();break
   except OSError:time.sleep(.25)
  savedbase=BASE;BASE='http://127.0.0.1:8129';c=login('demo1@example.test');call(c,state['pp']);call(c,state['tp']+'/'+state['tasks'][1]);call(c,state['wp']+'/reports/'+state['snapshot'])
  activity=rows("select * from task_activities where task_id=? and type='ATTACHMENT'",(state['tasks'][1],))[0];data=json.loads(activity['data'])
  res=c.open(BASE+state['tp']+'/'+state['tasks'][1]+'/attachments/'+activity['id']);verify('restored private attachment downloads original bytes through policy route',hashlib.sha256(res.read()).hexdigest()==data['sha256'])
  restored_preflight=json.loads(artisan('app:preflight','--json',cwd=restore));verify('restored application database/cache configuration valid',restored_preflight['configuration_passed'])
  log('restore COMPLETE',database_and_private_files=True,app_key_restored_from_separate_private_environment=True,second_physical_host=False)
 finally:
  BASE=savedbase;subprocess.run(['taskkill','/PID',str(server.pid),'/T','/F'],capture_output=True);log_file.close()
 artisan('up');verify('original isolated install available after drill',urllib.request.urlopen(BASE+'/login',timeout=20).status==200)
 log('COMPLETE',main_database_untouched=True,production_deployed=False)
finally:
 (R/'.env').write_bytes(original);artisan('config:clear');artisan('up')
