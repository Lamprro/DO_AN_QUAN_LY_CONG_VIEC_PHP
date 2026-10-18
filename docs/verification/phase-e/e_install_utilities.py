from pathlib import Path
import subprocess,json,sqlite3,re,time,urllib.request,urllib.parse,http.cookiejar
M=Path(__file__).parent;R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');O=R/'docs/verification/phase-e';state=json.loads((M/'release-validation-state.json').read_text());P=Path(state['directory']);log=[]
def record(step,**data):
 log.append(dict(step=step,**data));(O/'isolated-utilities.json').write_text(json.dumps(log,ensure_ascii=False,indent=2),encoding='utf-8');print(step,flush=True)
db=Path(re.search(r'^DB_DATABASE="([^"]+)"',(P/'.env').read_text(),re.M)[1]);c=sqlite3.connect(db);c.row_factory=sqlite3.Row
uid=c.execute("select id from users where email='demo1@example.test'").fetchone()[0];personal=c.execute("select id from workspaces where owner_id=? and kind='personal'",(uid,)).fetchone()[0];project=c.execute('select id,workspace_id from projects limit 1').fetchone()
base='http://127.0.0.1:8125';jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar));serverlog=open(Path(state['work'])/'utility-server.log','w');proc=subprocess.Popen([r'C:\xampp\php\php.exe','scripts/serve-local.php','8125'],cwd=P,stdout=serverlog,stderr=subprocess.STDOUT)
def request(path,data=None):
 resp=opener.open(base+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=15);body=resp.read().decode();assert resp.status==200;return resp,body
try:
 for attempt in range(30):
  try:res,body=request('/login');break
  except OSError:time.sleep(.2)
 else:raise RuntimeError('No isolated server')
 token=re.search(r'name="_token"\s+value="([^"]+)"',body)[1];request('/login',{'_token':token,'email':'demo1@example.test','password':'Demo@123456'});record('isolated login',http=200)
 for wp,tp in [('/workspaces/'+personal,'/workspaces/'+personal+'/tasks'),('/workspaces/'+project['workspace_id'],'/workspaces/'+project['workspace_id']+'/projects/'+project['id']+'/tasks')]:
  for path in [tp+'?preset=mine',tp+'?preset=review',tp+'?preset=blocked',tp+'?preset=overdue',wp+'/reminders',wp+'/operations']:
   res,body=request(path);record('fresh utility page',path=path,http=res.status)
  res,body=request(tp);token=re.search(r'name="_token"\s+value="([^"]+)"',body)[1];filterpath=tp.removesuffix('/tasks')+'/task-filters'
  res,body=request(filterpath,{'_token':token,'name':'isolated E filter','query[preset]':'mine','query[per_page]':'10'});record('isolated save filter',http=res.status,redirect=res.url)
  row=c.execute('select id from saved_task_filters where user_id=? and workspace_id=? order by created_at desc',(uid,wp.split('/')[-1])).fetchone();res,body=request(filterpath+'/'+row['id']+'/open');assert 'preset=mine' in res.url;record('isolated open scoped filter',http=res.status,redirect=res.url)
  token=re.search(r'name="_token"\s+value="([^"]+)"',body)[1];request(filterpath+'/'+row['id'],{'_token':token,'_method':'DELETE'});assert not c.execute('select id from saved_task_filters where id=?',(row['id'],)).fetchone();record('isolated owner removes filter',http=200)
 assert not list(c.execute('pragma foreign_key_check'));record('COMPLETE',personal_and_project=True,no_main_db_writes=True)
finally:
 subprocess.run(['taskkill','/PID',str(proc.pid),'/T','/F'],capture_output=True);serverlog.close();c.close()
