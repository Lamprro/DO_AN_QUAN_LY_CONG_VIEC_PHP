from pathlib import Path
ROOT=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((ROOT/'docs/verification/stage02/http-walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner-'+stamp)")[0])
import copy,uuid
OUT=ROOT/'docs/verification/final';PRIVATE=ROOT/'storage/app/private/final-review';journal=[]
def log(label,**facts):
 entry={'step':label,**facts};journal.append(entry);(OUT/'boundaries.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(entry,ensure_ascii=True),flush=True)
def verify(label,condition,**facts):
 log(label,verified=bool(condition),**facts)
 if not condition:raise RuntimeError(label)
state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'));wid=state['workspace_id'];pid=state['project_id'];uid=state['owner_id'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
owner=client('bound-owner');member=client('bound-member');observed('owner login',submit(owner,'/login','login',{'email':'demo1@example.test','password':'Demo@123456'}))
def post(label,path,data,c=owner,expected=200):return observed(label,raw_post(c,path,data),expected)
def counts():return {t:rows('select count(*) n from '+t)[0]['n'] for t in ['tasks','task_dependencies','calendar_events','task_activities','plans']}
def create(name,payload,c=owner,base=''):
 r=raw_post(c,wpath+'/plans',{'project_id':pid,'name':name,'payload':json.dumps(payload),'base_id':base});observed('create '+name,r);id=r[1].rstrip('/').split('/')[-1];verify('stored draft '+name,bool(rows('select id from plans where id=?',(id,))));return id
def action(id,name,status,c=owner):return post('action '+name,wpath+'/plans/'+id+'/'+name,{'expected_status':status,'reason':'Reject for review'},c)
payload={'schema_version':1,'tasks':[{'ref':'a','title':'Boundary A','task_type':'WORK','priority':'medium','estimate_minutes':60,'assignee_id':uid},{'ref':'b','title':'Boundary B','task_type':'WORK','priority':'medium','estimate_minutes':30,'assignee_id':uid}],'dependencies':[],'slots':[{'task_ref':'a','starts_at':'2027-03-01T08:00','ends_at':'2027-03-01T09:00'},{'task_ref':'b','starts_at':'2027-03-01T08:30','ends_at':'2027-03-01T09:00'}]}
id=create('Rollback after first slot insert',payload);action(id,'propose','draft');action(id,'approve','proposed');before=counts();action(id,'apply','approved');verify('all writes rolled back after one slot would insert',counts()==before and rows('select applied_at from plans where id=?',(id,))[0]['applied_at'] is None)
invalid=copy.deepcopy(payload);invalid['dependencies']=[{'predecessor':'a','successor':'b','lag_minutes':30}];before=counts();body=post('FS schedule invalid rejected before draft',wpath+'/plans',{'project_id':pid,'name':'Invalid FS','payload':json.dumps(invalid)});verify('FS no partial draft',counts()==before and 'lag FS' in body)
invalid=copy.deepcopy(payload);invalid['tasks'][0]['task_type']='GROUP';invalid['tasks'][0]['estimate_minutes']=0;invalid['slots']=[];post('GROUP estimate zero rejected',wpath+'/plans',{'project_id':pid,'name':'Invalid GROUP','payload':json.dumps(invalid)});verify('zero estimate GROUP no write',counts()==before)
valid=copy.deepcopy(payload);valid['slots']=[]
reject=create('Rejected proposal',valid);action(reject,'propose','draft');action(reject,'reject','proposed');verify('reject keeps reason and no business mutation',rows('select status,change_summary from plans where id=?',(reject,))[0]=={'status':'rejected','change_summary':'Reject for review'})

# Real account, invitation, project membership and member permissions.
email='final.member.'+stamp+'@example.test'
observed('register member',submit(member,'/register','register',{'name':'Final review member','email':email,'password':PASSWORD,'password_confirmation':PASSWORD,'timezone':'Asia/Ho_Chi_Minh'}));mid=rows('select id from users where email=?',(email,))[0]['id'];role=rows("select id from roles where workspace_id=? and name='Member'",(wid,))[0]['id']
body=observed('invite member',submit(owner,wpath+'/members','invitation-create',{'email':email,'role_id':role}));link=html.unescape(re.search(r'data-invitation-url="([^"]+)"',body)[1]);observed('accept invitation',submit(member,link,'invitation-accept',{}))
wmid=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,mid))[0]['id']
observed('project membership',submit(owner,ppath+'/members','project-member-create',{'workspace_member_id':wmid,'role_id':role,'status':'active'}))
own={'schema_version':1,'tasks':[{'ref':'memberwork','title':'Member owns timer boundary','task_type':'WORK','priority':'medium','estimate_minutes':45,'assignee_id':mid,'reviewer_id':uid}],'dependencies':[],'slots':[]}
mp=create('Member draft',own,member)
post('Member cannot propose own draft',wpath+'/plans/'+mp+'/propose',{'expected_status':'draft'},member,403)
post('Member cannot approve own draft',wpath+'/plans/'+mp+'/approve',{'expected_status':'draft'},member,403)
action(mp,'propose','draft');action(mp,'approve','proposed');action(mp,'apply','approved')
mt=json.loads(rows('select data from plans where id=?',(mp,))[0]['data'])['applied_task_ids']['memberwork'];state['member_task_id']=mt;state['member_id']=mid;state['member_plan_id']=mp;state['member_email']=email
post('member starts own WORK',tpath+'/'+mt+'/status',{'_method':'PATCH','expected_status':'TODO','status':'IN_PROGRESS'},member)
post('member timer start',wpath+'/timer/'+mt,{},member)
entry=rows('select id from time_entries where user_id=? and ended_at is null',(mid,))[0]['id']
post('suspend member while clock running',wpath+'/members/'+wmid,{'_method':'PATCH','role_id':role,'status':'suspended'})
observed('suspended member task hidden',call(member,tpath+'/'+mt),403)
body=observed('own clock accessible without hidden task',call(member,'/timers'));verify('timer page hides lost task title','Member owns timer boundary' not in body and 'không còn quyền xem' in body)
post('owner cannot stop member clock','/timers/'+entry+'/stop',{},owner,403)
post('member can stop own clock after membership loss','/timers/'+entry+'/stop',{},member)
verify('revoked stop persisted actual time',rows('select ended_at from time_entries where id=?',(entry,))[0]['ended_at'] is not None)
post('restore member',wpath+'/members/'+wmid,{'_method':'PATCH','role_id':role,'status':'active'})
post('Member cannot manage integration',ppath+'/github',{'repository_id':'123','enabled':'0'},member,403)
post('Member cannot mutate applied payload via unexpected action',wpath+'/plans/'+mp+'/overwrite',{'data':'anything'},member,404)

# Browser-written draft is real DB state; apply through HTTP after latest changes.
browser=rows("select * from plans where workspace_id=? and name='Final Browser — phiên bản sửa giao diện'",(wid,))[0];bid=browser['id']
# Membership edits deliberately made this snapshot stale: create another latest version with current data.
bid2=create('Final browser revision current snapshot',json.loads(browser['data'])['payload'],owner,bid)
action(bid2,'propose','draft');action(bid2,'approve','proposed');action(bid2,'apply','approved');c=counts();action(bid2,'apply','approved');verify('new guards preserve real success and idempotency',counts()==c and rows('select is_active from plans where id=?',(bid2,))[0]['is_active']==1)
state['final_plan_id']=bid2;(PRIVATE/'state.json').write_text(json.dumps(state),encoding='utf-8')
verify('foreign key integrity',not rows('pragma foreign_key_check'));log('COMPLETE',no_test_classes=True)
