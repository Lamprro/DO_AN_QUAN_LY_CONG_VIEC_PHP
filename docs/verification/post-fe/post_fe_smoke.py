from pathlib import Path
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,sqlite3
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');O=R/'docs/verification/post-fe';O.mkdir(parents=True,exist_ok=True);B='http://127.0.0.1:8123';log=[]
op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,data=None):
 try:r=op.open(B+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=30)
 except urllib.error.HTTPError as e:r=e
 body=r.read().decode(errors='replace');log.append({'path':path,'status':r.code});(O/'main-pages.json').write_text(json.dumps(log,ensure_ascii=False,indent=2),encoding='utf-8');print(path,r.code,flush=True);assert r.code==200,path;return body
body=call('/login');token=re.search(r'name="_token"\s+value="([^"]+)"',body)[1];call('/login',{'_token':token,'email':'demo1@example.test','password':'Demo@123456'})
c=sqlite3.connect(R/'database/work_management.sqlite');c.row_factory=sqlite3.Row
p=c.execute("select * from projects where name='Demo Work Management' limit 1").fetchone();w='/workspaces/'+p['workspace_id'];pr=w+'/projects/'+p['id'];t=c.execute("select id from tasks where project_id=? and task_type='WORK' and archived_at is null order by status limit 1",(p['id'],)).fetchone()[0]
paths=['/workspaces','/workspaces/create',w,w+'/edit',w+'/members',w+'/roles',w+'/roles/create',w+'/operations',w+'/reminders',w+'/projects',w+'/projects/create',pr,pr+'/edit',pr+'/members',pr+'/github',pr+'/timeline',pr+'/tasks',pr+'/tasks?view=board',pr+'/tasks/create',pr+'/tasks/tree',pr+'/tasks/'+t,'/timers','/notifications','/profile','/settings','/search','/search?q=Demo',w+'/dashboard',w+'/reports',w+'/ai-runs',w+'/ai-runs/create',w+'/plans?project_id='+p['id'],w+'/plans/create?project_id='+p['id'],w+'/calendar',w+'/calendar/create']
for path in paths:call(path)
plan=c.execute("select id from plans where project_id=? and name='Demo proposal (fixture)' limit 1",(p['id'],)).fetchone()
if plan:
 call(w+'/plans/'+plan[0]);body=call(w+'/plans/'+plan[0]+'/preview');assert 'Kế hoạch thiếu dữ liệu chuẩn' in body and 'Mô phỏng hợp lệ tại thời điểm kiểm tra!' not in body
print('Main read-only page smoke complete',len(paths),flush=True)
