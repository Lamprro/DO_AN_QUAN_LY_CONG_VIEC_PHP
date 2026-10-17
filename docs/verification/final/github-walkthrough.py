from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import hmac,hashlib,secrets,uuid
OUT=ROOT/'docs/verification/final';PRIVATE=ROOT/'storage/app/private/final-review';BASE='http://127.0.0.1:8124'
journal=[]
def log(label,**facts):
 entry={'step':label,**facts};journal.append(entry);(OUT/'github-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def verify(label,condition,**facts):
 log(label,verified=bool(condition),**facts)
 if not condition:raise RuntimeError(label)
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));wid=state['workspace_id'];pid=state['project_id'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid
owner=client('github-owner');observed('secondary local server login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
secret=secrets.token_hex(32)
observed('enable review connection via actual config route',raw_post(owner,ppath+'/github',{'repository_id':'123456789','webhook_secret':secret,'enabled':'1'}))
con=rows('select i.id,i.credentials_encrypted,i.status from integration_connections i join projects p on p.github_connection_id=i.id where p.id=?',(pid,))[0]
verify('secret encrypted at rest',secret not in con['credentials_encrypted'] and con['status']=='active')
code=rows('select code from tasks where id=?',(state['tasks']['b'],))[0]['code'];before=rows('select status from tasks where id=?',(state['tasks']['b'],))[0]['status']
delivery=str(uuid.uuid4());payload={'repository':{'id':123456789},'commits':[{'id':'a'*40,'message':'Review local signed payload ['+code+']'}]}
def hook(obj,signature=True,delivery_id=delivery):
 raw=json.dumps(obj,ensure_ascii=False,separators=(',',':')).encode();headers={'Content-Type':'application/json','Accept':'text/html','X-GitHub-Delivery':delivery_id,'X-GitHub-Event':'push'}
 if signature:headers['X-Hub-Signature-256']='sha256='+hmac.new(secret.encode(),raw,hashlib.sha256).hexdigest()
 req=urllib.request.Request(BASE+'/hooks/github/'+pid,data=raw,headers=headers)
 try:r=urllib.request.urlopen(req,timeout=20)
 except urllib.error.HTTPError as e:r=e
 body=r.read().decode(errors='replace');log('hook response',http=r.code,delivery_id=delivery_id)
 return r.code
verify('unsigned webhook rejected',hook(payload,False)==403)
verify('signed local delivery accepted',hook(payload)==204)
verify('duplicate delivery accepted idempotently',hook(payload)==204)
verify('one log and one linked immutable activity',len(rows('select id from github_logs where delivery_id=?',(delivery,)))==1 and len(rows("select id from task_activities where task_id=? and event_type='github.commit'",(state['tasks']['b'],)))==1)
verify('commit never changes task status',rows('select status from tasks where id=?',(state['tasks']['b'],))[0]['status']==before)
wrong={'repository':{'id':999},'commits':payload['commits']};verify('foreign repository rejected',hook(wrong,True,str(uuid.uuid4()))==422)
observed('disable review connection after verification',raw_post(owner,ppath+'/github',{'repository_id':'123456789','webhook_secret':'','enabled':'0'}))
verify('disabled connection rejects signed webhook',hook(payload,True,str(uuid.uuid4()))==403)
verify('no FK errors',not rows('pragma foreign_key_check'))
log('COMPLETE',real_github_network=False,local_signed_data=True,secret_logged=False,connection_restored_disabled=True)
