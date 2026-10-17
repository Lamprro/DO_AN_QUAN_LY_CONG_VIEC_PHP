from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import secrets,hmac,hashlib,uuid,subprocess
OUT=ROOT/'docs/verification/final';PRIVATE=ROOT/'storage/app/private/final-review';journal=[]
def log(label,**facts):
 entry={'step':label,**facts};journal.append(entry);(OUT/'final-readback.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def verify(label,condition,**facts):
 log(label,verified=bool(condition),**facts)
 if not condition:raise RuntimeError(label)
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));wid=state['workspace_id'];pid=state['project_id'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid
owner=client('final-readback-owner');observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
r=raw_post(owner,wpath+'/reports',{'project_id':pid,'from':'2026-01-01','to':'2027-01-01'});observed('store final report from HTTP timer data',r);report_id=r[1].rstrip('/').split('/')[-1]
data=json.loads(rows('select metrics_json from report_snapshots where id=?',(report_id,))[0]['metrics_json']);actual=data['period_metrics']['actual_minutes']
verify('report includes elapsed HTTP timers',actual is not None and actual>0,actual_minutes=actual,period_metrics=data['period_metrics'])
state['final_report_id']=report_id;(PRIVATE/'state.json').write_text(json.dumps(state),encoding='utf-8')
observed('final report CSV export',call(owner,wpath+'/reports/'+report_id+'/export'))
observed('final report print Blade',call(owner,wpath+'/reports/'+report_id+'/print'))
observed('active final plan detail',call(owner,wpath+'/plans/'+state['final_plan_id']))
verify('browser draft is real, active version immutable mapping',rows("select count(*) n from plans where workspace_id=? and name='Final Browser — phiên bản sửa giao diện'",(wid,))[0]['n']==1 and rows('select is_active,applied_at from plans where id=?',(state['final_plan_id'],))[0]['is_active']==1)

# Malformed signed commit IDs are rejected as validation errors, not server crashes.
BASE='http://127.0.0.1:8124';hookowner=client('hook-input-boundary');observed('secondary login',submit(hookowner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}));secret=secrets.token_hex(32)
observed('enable local signed boundary fixture',raw_post(hookowner,ppath+'/github',{'repository_id':'123456789','webhook_secret':secret,'enabled':'1'}))
before=rows('select count(*) n from github_logs where project_id=?',(pid,))[0]['n'];raw=json.dumps({'repository':{'id':123456789},'commits':[{'id':[],'message':'wrong ID type'}]},separators=(',',':')).encode()
req=urllib.request.Request(BASE+'/hooks/github/'+pid,data=raw,headers={'Content-Type':'application/json','Accept':'application/json','X-GitHub-Delivery':str(uuid.uuid4()),'X-GitHub-Event':'push','X-Hub-Signature-256':'sha256='+hmac.new(secret.encode(),raw,hashlib.sha256).hexdigest()})
try:
 try:response=urllib.request.urlopen(req,timeout=20)
 except urllib.error.HTTPError as e:response=e
 response.read();verify('signed malformed ID gets 422 with no log',response.code==422 and rows('select count(*) n from github_logs where project_id=?',(pid,))[0]['n']==before,http=response.code)
finally:observed('disable connection after signed boundary',raw_post(hookowner,ppath+'/github',{'repository_id':'123456789','webhook_secret':'','enabled':'0'}))
BASE='http://127.0.0.1:8123'
verify('main environment integrations remain disabled',call(owner,'/hooks/github/'+pid,{})[0]==503)
verify('all current final FKs valid',not rows('pragma foreign_key_check'))
verify('no pending AI jobs',rows("select count(*) n from jobs where queue='work-management-ai'")[0]['n']==0)
log('COMPLETE',external_provider_called=False,csrf_session_http_database=True)
