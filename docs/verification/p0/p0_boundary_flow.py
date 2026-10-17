from pathlib import Path
M=Path(__file__).parent
exec((M/'p0_http_walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner')")[0])
state=json.loads((PRIVATE/'state.json').read_text());journal=[]
def log(label,**facts):
 journal.append({'step':label,**facts});(OUT/'boundary-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
owner=client('boundary-owner');viewer=client('boundary-viewer');member=client('boundary-member')
for c,email in [(owner,'demo1@example.test'),(viewer,'demo2@example.test'),(member,'demo3@example.test')]:observed('login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
wid=state['workspace_id'];pid=state['project_id'];uid=state['owner_id'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
env_hash=hashlib.sha256((ROOT/'.env').read_bytes()).hexdigest()
for f in (ROOT/'storage/framework/views').glob('*.php'):
 p=subprocess.run([r'C:\xampp\php\php.exe','-l',str(f)],capture_output=True,text=True)
 if p.returncode:raise RuntimeError(p.stdout+p.stderr)
log('compiled Blade PHP syntax',files=len(list((ROOT/'storage/framework/views').glob('*.php'))))
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
body=observed('invite local Member fixture',submit_live(owner,wpath+'/members','invitation-create',{'email':'demo3@example.test','role_id':roles['Member']}));invite=html.unescape(re.search(r'data-invitation-url="([^"]+)"',body)[1]);observed('Member accepts real invitation',submit_live(member,invite,'invitation-accept',{}))
muid=rows("select id from users where email='demo3@example.test'")[0]['id'];wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,muid))[0]['id']
observed('assign Member to project',submit_live(owner,ppath+'/members','project-member-create',{'workspace_member_id':wm,'role_id':roles['Member'],'status':'active'}))
r=submit_live(owner,tpath+'/create','task-create',{'title':'P0 private running task','task_type':'WORK','priority':'medium','estimate_minutes':'30','assignee_id':muid,'reviewer_id':uid});observed('create assigned timer task',r);mt=r[1].split('/')[-1]
observed('Member starts task',submit_live(member,tpath+'/'+mt,'task-status',{'status':'IN_PROGRESS'}))
observed('Member starts timer',post(member,wpath+'/timer/'+mt,{},tpath+'/'+mt));entry=rows('select id from time_entries where user_id=? and ended_at is null',(muid,))[0]['id']
observed('suspend Member workspace scope',post(owner,wpath+'/members/'+wm,{'_method':'PATCH','role_id':roles['Member'],'status':'suspended'},wpath+'/members'))
body=observed('own clock after losing task access',call(member,'/workspaces'))
verify('no task title/link leaked but own stop retained','data-global-timer' in body and 'P0 private running task' not in body and entry+'/stop' in body and ('/tasks/'+mt) not in body)
observed('suspended member task denied',call(member,tpath+'/'+mt),403)
observed('Owner cannot stop another user clock',post(owner,'/timers/'+entry+'/stop',{},'/workspaces'),403)
time.sleep(2);observed('own global stop after membership loss',submit_live(member,'/workspaces','global-timer-stop',{}))
verify('actual ended_at persisted after membership loss',rows('select ended_at from time_entries where id=?',(entry,))[0]['ended_at'] is not None)
observed('restore fixture membership',post(owner,wpath+'/members/'+wm,{'_method':'PATCH','role_id':roles['Member'],'status':'active'},wpath+'/members'))

r=submit_live(owner,wpath+'/projects/create','project-create',{'name':'P0 confidential project owner only','status':'draft'});observed('private project fixture',r);secret=r[1].split('/')[-1]
body=observed('Viewer workspace counts after hidden project',call(viewer,wpath));verify('only permitted project counted','1 project được xem' in body and 'P0 confidential project' not in body)
body=observed('Viewer project list',call(viewer,wpath+'/projects?view=table'));verify('table view scoped','P0 confidential project' not in body and 'mobile-data-table' in body)
body=observed('grouped role editor',call(owner,wpath+'/roles/create'));verify('canonical permission values and human grouping','name="permissions[]"' in body and 'value="task.view"' in body and '<fieldset' in body)

editor=wpath+'/plans/create?project_id='+pid
body=observed('source search real web GET',call(owner,editor+'&source_q=P0%20metadata'))
data=json.loads(re.search(r'<script[^>]*id="plan-editor-data"[^>]*>(.*?)</script>',body,re.S)[1]);verify('source search bounded and scoped',any(t['id']==state['metadata_task_id'] for t in data['tasks']) and all(t['id'] not in [secret] for t in data['tasks']),results=len(data['tasks']))
body=observed('searched child includes actual ancestor',call(owner,editor+'&source_q=P0%20WORK%20B'))
data=json.loads(re.search(r'<script[^>]*id="plan-editor-data"[^>]*>(.*?)</script>',body,re.S)[1]);verify('source child and group context retained',set([state['tasks']['g'],state['tasks']['b']]).issubset({t['id'] for t in data['tasks']}))

# Conflict occurs after domain task creation has started inside the preview transaction.
payload={'schema_version':1,'tasks':[{'ref':'late','title':'P0 rollback after task creation','task_type':'WORK','priority':'medium','estimate_minutes':30,'assignee_id':uid,'reviewer_id':uid}], 'dependencies':[], 'slots':[{'task_ref':'late','starts_at':'2027-04-05T09:00','ends_at':'2027-04-05T09:30'}]}
r=post(owner,wpath+'/plans',{'name':'P0 late slot conflict','project_id':pid,'input_mode':'json','json_payload':json.dumps(payload)},editor);observed('advanced JSON native fallback save',r);conflict=r[1].split('/')[-1]
verify('advanced fallback created a draft',len(rows('select id from plans where id=?',(conflict,)))==1)
before=business();body=observed('conflict preview rolls all domain writes back',call(owner,wpath+'/plans/'+conflict+'/preview'));verify('conflict invalid and no task/history/calendar/notification persisted','Mô phỏng hợp lệ' not in body and before==business())

# New standalone plan uses rendered propose / approve / apply forms, including hidden enum fields.
payload['slots']=[];payload['tasks'][0]['title']='P0 native protocol task'
r=post(owner,wpath+'/plans',{'name':'P0 native form protocol','project_id':pid,'input_mode':'editor','editor_enabled':'1','payload':json.dumps(payload)},editor);observed('new clean plan',r);plan=r[1].split('/')[-1];path=wpath+'/plans/'+plan
for action,expected in [('propose','draft'),('approve','proposed')]:
 body=observed('read '+action+' native form',call(owner,path));_,data=formdata(body,'plan-'+action);verify('expected_status stays raw '+expected,data['expected_status']==expected)
 observed('submit rendered '+action,submit_live(owner,path,'plan-'+action,{}))
before=business();body=observed('approved clean plan preview',call(owner,path+'/preview'));verify('exact preview valid without durable writes',before==business() and 'Mô phỏng hợp lệ' in body and 'P0 native protocol task' in body)
observed('apply rendered preview form',submit_live(owner,path+'/preview','plan-apply',{}));verify('real apply created exactly one task',len(rows("select id from tasks where workspace_id=? and title='P0 native protocol task'",(wid,)))==1)
before=business();observed('duplicate apply same actual endpoint',post(owner,path+'/apply',{'expected_status':'approved'},path));verify('no duplicate task or history',before==business())

# A source edit invalidates the next snapshot; apply/preview cannot bypass it.
r=post(owner,wpath+'/plans',{'name':'P0 stale snapshot','project_id':pid,'payload':json.dumps(payload)},editor);observed('create prechange snapshot',r);stale=r[1].split('/')[-1]
body=observed('open source for actual edit',call(owner,tpath+'/'+state['metadata_task_id']+'/edit'));_,data=formdata(body,'task-edit')
observed('actual source metadata update',call(owner,tpath+'/'+state['metadata_task_id'],{**data,'title':'P0 metadata after stale check','task_type':'WORK','priority':'high','estimate_minutes':'60','assignee_id':uid,'reviewer_id':uid}))
before=business();body=observed('stale snapshot preview',call(owner,wpath+'/plans/'+stale+'/preview'));verify('stale source explained and rollback complete','Dữ liệu nguồn đã thay đổi' in body and before==business())
verify('env including existing APP_KEY untouched',env_hash==hashlib.sha256((ROOT/'.env').read_bytes()).hexdigest())
verify('foreign key check empty',not rows('pragma foreign_key_check'));log('boundary complete',real_sessions=True,no_test_classes=True)
(PRIVATE/'boundary-state.json').write_text(json.dumps({'timer_task_id':mt,'member_id':muid,'private_project_id':secret,'native_plan_id':plan,'conflict_plan_id':conflict,'stale_plan_id':stale}),encoding='utf-8')
