from pathlib import Path
exec((Path(__file__).parent/'audit_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
import hmac,secrets,uuid
stage='github';journal=[];owner=login('demo1@example.test');pp=state['pp'];pid=state['pid'];tid=state['tasks'][2]
original=(R/'.env').read_bytes();secret=secrets.token_hex(32)
try:
 (R/'.env').write_bytes(original.replace(b'INTEGRATIONS_ENABLED=false',b'INTEGRATIONS_ENABLED=true'))
 subprocess.run([r'C:\xampp\php\php.exe','artisan','config:clear'],cwd=R,capture_output=True,check=True)
 time.sleep(2) # artisan serve restarts its development server when .env changes.
 for attempt in range(20):
  try:urllib.request.urlopen(BASE+'/login',timeout=3).read();break
  except OSError:time.sleep(.25)
 post(owner,pp+'/github',{'repository_id':'123456789','webhook_secret':secret,'enabled':'1'},pp+'/github')
 con=rows('select i.* from integration_connections i join projects p on p.github_connection_id=i.id where p.id=?',(pid,))[0]
 verify('review connection encrypted',con['status']=='active' and secret not in con['credentials_encrypted'])
 payload={'repository':{'id':123456789},'commits':[{'id':'a'*40,'message':'Local verification ['+cur(tid)['code']+']'}]};delivery=str(uuid.uuid4())
 def hook(obj,signed=True,did=delivery):
  raw=json.dumps(obj,separators=(',',':')).encode();headers={'Content-Type':'application/json','Accept':'text/html','X-GitHub-Delivery':did,'X-GitHub-Event':'push'}
  if signed:headers['X-Hub-Signature-256']='sha256='+hmac.new(secret.encode(),raw,hashlib.sha256).hexdigest()
  try:r=urllib.request.urlopen(urllib.request.Request(BASE+'/hooks/github/'+pid,data=raw,headers=headers),timeout=25)
  except urllib.error.HTTPError as e:r=e
  r.read();return r.code
 verify('unsigned local webhook denied',hook(payload,False)==403)
 before=cur(tid);verify('signed delivery accepted',hook(payload)==204);verify('duplicate delivery idempotent',hook(payload)==204)
 verify('one log and one activity, task unchanged',len(rows('select id from github_logs where delivery_id=?',(delivery,)))==1 and len(rows("select id from task_activities where task_id=? and event_type='github.commit'",(tid,)))==1 and before==cur(tid))
 verify('foreign repository denied',hook({'repository':{'id':999},'commits':payload['commits']},did=str(uuid.uuid4()))==422)
 post(owner,pp+'/github',{'repository_id':'123456789','webhook_secret':'','enabled':'0'},pp+'/github')
 verify('disabled connection denied',hook(payload,did=str(uuid.uuid4()))==403)
finally:
 (R/'.env').write_bytes(original);subprocess.run([r'C:\xampp\php\php.exe','artisan','config:clear'],cwd=R,capture_output=True,check=True)
verify('review env restored',original==(R/'.env').read_bytes());verify('FK integrity',not rows('pragma foreign_key_check'));log('COMPLETE',real_github_network=False,local_signed_payload=True,main_configuration_not_touched=True)
