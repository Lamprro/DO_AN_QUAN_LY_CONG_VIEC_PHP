from pathlib import Path
import subprocess,shutil,zipfile,json,hashlib,time,os,sqlite3,re,urllib.request,urllib.parse,http.cookiejar,tempfile
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');OUT=R/'docs/verification/phase-e'
MIRROR=Path(r'C:\Users\maiva\.codex\.chatgpt-projects\g-p-6ac9f1ab7ff88191b43033b8bc45d9d6\output')
PHP=r'C:\xampp\php\php.exe';stamp=str(int(time.time()));work=Path(tempfile.mkdtemp(prefix='wm-release-'))
report=[]
def record(label,**data):
 report.append({'step':label,**data});(OUT/'release-install.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(report[-1],ensure_ascii=True),flush=True)
def run(args,cwd=work):
 p=subprocess.run(args,cwd=cwd,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=180)
 record('command',command=' '.join(str(a) for a in args),exit_code=p.returncode,output=(p.stdout+p.stderr)[-6500:])
 if p.returncode:raise RuntimeError(' '.join(args))
 return p.stdout
active_env=hashlib.sha256((R/'.env').read_bytes()).hexdigest();active_db=str((R/'database/work_management.sqlite').resolve())
files=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard'],cwd=R).decode('utf-8').splitlines()
archive=work/'source.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
 for filename in sorted(set(files)):
  p=R/filename;parts=Path(filename).parts
  if not p.is_file() or filename=='.env' or (filename.startswith('.env.') and filename!='.env.example') or parts[0] in ['.git','vendor','node_modules','dist'] or '.sqlite' in filename or filename.endswith(('.cookies','.key','.log')):continue
  if parts[0]=='storage' and p.name!='.gitignore':continue
  if filename.startswith('bootstrap/cache/') and p.name!='.gitignore':continue
  z.write(p,filename)
source=work/'project';source.mkdir()
with zipfile.ZipFile(archive) as z:z.extractall(source)
record('source archive extracted',directory=str(source),files=len(list(source.rglob('*'))),contains_env=(source/'.env').exists(),contains_live_db=(source/'database/work_management.sqlite').exists())
record('copy installed dependencies for isolated check',note='Uses already installed vendor on same host; not a fresh network download or second physical computer.')
shutil.copytree(R/'vendor',source/'vendor')
run([PHP,'D:/Composer/composer.phar','validate','--no-check-publish'],source)
run([PHP,'scripts/setup-local.php','--demo'],source)
env=(source/'.env').read_text(encoding='utf-8');key=re.search(r'^APP_KEY=(.*)$',env,re.M)[1];dbpath=Path(re.search(r'^DB_DATABASE="([^"]+)"',env,re.M)[1])
assert key and dbpath.resolve()!=Path(active_db) and str(dbpath.resolve()).startswith(str(work.resolve()))
run([PHP,'scripts/setup-local.php','--demo'],source)
assert re.search(r'^APP_KEY=(.*)$',(source/'.env').read_text(encoding='utf-8'),re.M)[1]==key
con=sqlite3.connect(dbpath);counts={t:con.execute('select count(*) from '+t).fetchone()[0] for t in ['users','workspaces','projects','tasks','calendar_events']};fk=con.execute('pragma foreign_key_check').fetchall();con.close();assert not fk and counts['users']==5 and counts['tasks']==40
record('fresh migrations + repeated demo seed + key preserved',counts=counts,fk_errors=fk,second_run_preserves_key=True,database_isolated=True)
serverlog=open(work/'server.log','w',encoding='utf-8')
proc=subprocess.Popen([PHP,'scripts/serve-local.php','8125'],cwd=source,stdout=serverlog,stderr=subprocess.STDOUT)
try:
 base='http://127.0.0.1:8125';jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
 for attempt in range(30):
  try:resp=opener.open(base+'/login',timeout=5);body=resp.read().decode();break
  except OSError:time.sleep(.2)
 else:raise RuntimeError('Extracted source server did not start')
 token=re.search(r'name="_token"\s+value="([^"]+)"',body)[1]
 resp=opener.open(base+'/login',urllib.parse.urlencode({'_token':token,'email':'demo1@example.test','password':'Demo@123456'}).encode(),timeout=10);body=resp.read().decode();assert resp.status==200 and '/workspaces' in resp.url
 record('isolated HTTP login using installed source',http=resp.status,redirect=resp.url,real_database=True)
 con=sqlite3.connect(dbpath);wid,pid=con.execute("select workspace_id,id from projects limit 1").fetchone();con.close()
 for suffix in ['/workspaces/'+wid+'/projects/'+pid,'/workspaces/'+wid+'/plans?project_id='+pid,'/timers','/workspaces/'+wid+'/dashboard']:
  resp=opener.open(base+suffix,timeout=15);resp.read();assert resp.status==200;record('isolated page',path=suffix,http=resp.status)
finally:
 # On Windows proc_open creates a server child; terminate only this helper's process tree.
 subprocess.run(['taskkill','/PID',str(proc.pid),'/T','/F'],capture_output=True)
 serverlog.close()
assert hashlib.sha256((R/'.env').read_bytes()).hexdigest()==active_env
record('COMPLETE',active_env_unchanged=True,active_db_not_reset=True,isolated_source_directory=str(source),vendor_reused=True,second_physical_machine_tested=False)
(MIRROR/'release-validation-state.json').write_text(json.dumps({'directory':str(source),'work':str(work)}),encoding='utf-8')
