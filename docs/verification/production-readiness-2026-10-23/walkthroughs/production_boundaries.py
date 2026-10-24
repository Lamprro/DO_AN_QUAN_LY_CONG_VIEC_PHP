from pathlib import Path
exec((Path(__file__).parent/'production_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
stage='boundaries';journal=[];owner=login('demo1@example.test');wp,pp,tp=state['wp'],state['pp'],state['tp'];uid=state['uid']
if 'literal_project' not in state:
 url,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' literal%_ project','status':'draft'})
 state['literal_project']=url.split('/')[-1]
 tid=work(owner,tp,prefix+' literal history',assignee_id=uid,reviewer_id=uid)
 form(owner,tp+'/'+tid+'/edit','task-edit',{'title':'low','description':'DONE','task_type':'WORK','priority':'high'})
 state['history_task']=tid;checkpoint()

def unchanged(label,fn):
 before=business();fn();verify(label,before==business())
_,body=call(owner,tp+'?'+urllib.parse.urlencode({'q':'%_','per_page':'50'}))
verify('literal task list search only matching rows',state['literal'] in body and not any(t in body for t in state['tasks']))
_,body=call(owner,tp+'?'+urllib.parse.urlencode({'q':'%_','view':'board'}))
verify('literal Kanban search only matching cards',state['literal'] in body and not any(t in body for t in state['tasks']))
_,body=call(owner,wp+'/projects?'+urllib.parse.urlencode({'q':'%_'}));main=body.split('<main')[1]
verify('literal project search',state['literal_project'] in main and state['pid'] not in main)
url,_=form(owner,'/workspaces/create','workspace-create',{'name':prefix+' literal%_ workspace','timezone':'Asia/Ho_Chi_Minh'});specialw=url.split('/')[-1]
_,body=call(owner,'/workspaces?'+urllib.parse.urlencode({'q':'%_'}));main=body.split('<main')[1];verify('literal workspace search',specialw in main and state['wid'] not in main)
_,body=call(owner,tp+'/'+state['history_task']);verify('audit preserves literal low/DONE and translates priority only','<strong>low</strong>' in body and '<strong>DONE</strong>' in body and '<strong>Cao</strong>' in body)
_,body=call(owner,wp+'/plans/create?'+urllib.parse.urlencode({'project_id':state['pid'],'source_q':'%_'}));verify('plan source search still renders',state['literal'] in body)
# True auth lifecycle on a newly registered, disposable local account.
c=client();email='completion'+str(int(time.time()))+'@example.test';pwd='AuditOnly@123456'
form(c,'/register','register',{'name':'ProductionReview account','email':email.upper(),'password':pwd,'password_confirmation':pwd,'timezone':'Asia/Ho_Chi_Minh'})
user=rows('select * from users where email=?',(email,))[0];verify('register normalized email and hashed password',user['password']!=pwd and len(user['password'])>40)
verify('register creates personal workspace and owner membership',len(rows("select w.id from workspaces w join workspace_members m on m.workspace_id=w.id where w.owner_id=? and w.kind='personal' and m.user_id=? and m.status='active'",(user['id'],user['id'])))==1)
form(c,'/profile','profile',{'name':'ProductionReview profile changed','email':email,'timezone':'Pacific/Kiritimati'});verify('profile changes saved',rows('select name,timezone from users where id=?',(user['id'],))[0]=={'name':'ProductionReview profile changed','timezone':'Pacific/Kiritimati'})
second=client();form(second,'/login','login',{'email':email,'password':pwd});newpwd='AuditChanged@123456'
form(c,'/profile','password',{'current_password':pwd,'password':newpwd,'password_confirmation':newpwd});url,_=call(second,'/profile');verify('password change invalidates other DB sessions','/login' in url)
form(c,'/profile','logout',{});url,_=call(c,'/profile');verify('logout requires login','/login' in url)
form(c,'/login','login',{'email':email,'password':pwd});verify('old password rejected','/login' in c.last)
form(c,'/login','login',{'email':email,'password':newpwd});url,body=call(c,'/profile');verify('new password logs in and intended profile accessible','/profile' in url and 'ProductionReview profile changed' in body)
call(client(),wp,expected=200) # guest follows redirect to login
call(owner,tp+'/00000000-0000-0000-0000-000000000000',expected=404)
_,b=call(owner,tp+'/create');call(owner,tp,{'_token':'invalid','title':'must not save'},expected=419)
# Tree integrity, cycle, weighted progress, status reasons and archive guards.
def group(title,parent=None):
 u,_=form(owner,tp+'/create','task-create',{'task_type':'GROUP','priority':'medium','title':title,'parent_task_id':parent or ''});return u.split('/')[-1]
g=group(prefix+' root');g2=group(prefix+' child group',g)
unchanged('tree cycle rejected atomically',lambda:form(owner,tp+'/'+g+'/edit','task-edit',{'task_type':'GROUP','title':prefix+' root','priority':'medium','parent_task_id':g2}))
a=work(owner,tp,prefix+' grouped A',parent_task_id=g2,assignee_id=uid,reviewer_id=uid,estimate_minutes=30)
b=work(owner,tp,prefix+' grouped B',parent_task_id=g2,assignee_id=uid,reviewer_id=uid,estimate_minutes=60)
post(owner,tp+'/'+b+'/dependencies',{'predecessor_task_id':a,'lag_minutes':1},tp+'/'+b)
count=len(rows('select id from task_dependencies'));post(owner,tp+'/'+a+'/dependencies',{'predecessor_task_id':b,'lag_minutes':0},tp+'/'+a);verify('dependency cycle rejected',count==len(rows('select id from task_dependencies')))
post(owner,tp+'/'+a+'/status',{'_method':'PATCH','status':'DONE','expected_status':'TODO'},tp+'/'+a);verify('cannot skip review',cur(a)['status']=='TODO')
for dest,src in [('IN_PROGRESS','TODO'),('REVIEW','IN_PROGRESS')]:post(owner,tp+'/'+a+'/status',{'_method':'PATCH','status':dest,'expected_status':src},tp+'/'+a)
unchanged('review rejection requires reason',lambda:post(owner,tp+'/'+a+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'REVIEW'},tp+'/'+a))
post(owner,tp+'/'+a+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'REVIEW','reason':'Additional actual work needed'},tp+'/'+a)
for dest,src in [('REVIEW','IN_PROGRESS'),('DONE','REVIEW')]:post(owner,tp+'/'+a+'/status',{'_method':'PATCH','status':dest,'expected_status':src},tp+'/'+a)
post(owner,tp+'/'+b+'/status',{'_method':'PATCH','status':'IN_PROGRESS','expected_status':'TODO'},tp+'/'+b);verify('positive FS lag blocks immediate successor',cur(b)['status']=='TODO')
verify('ancestor status aggregated through two levels',cur(g)['status']=='IN_PROGRESS' and cur(g2)['status']=='IN_PROGRESS')
_,body=call(owner,tp+'/'+g2);verify('group weighted progress visible','33.3' in body)
unchanged('parent archive blocked while active children exist',lambda:post(owner,tp+'/'+g2+'/archive',{},tp+'/'+g2))
post(owner,tp+'/'+b+'/archive',{},tp+'/'+b);verify('archive hides leaf',cur(b)['archived_at'] is not None)
post(owner,tp+'/'+b+'/restore',{},tp+'/'+b);verify('restore returns leaf',cur(b)['archived_at'] is None)
unchanged('history prevents destructive task delete',lambda:post(owner,tp+'/'+b,{'_method':'DELETE'},tp+'/'+b))
# Scheduling creates a draft version only; stored payload must account for estimates.
page=wp+'/plans/create?project_id='+state['pid'];payload={'schema_version':1,'tasks':[{'ref':'local1','title':prefix+' local scheduled','task_type':'WORK','priority':'medium','estimate_minutes':60,'assignee_id':uid,'reviewer_id':uid}],'dependencies':[],'slots':[]}
u,_=form(owner,page,'plan-create',{'name':prefix+' local planner','input_mode':'json','payload':json.dumps(payload)});plan=u.split('/')[-1]
before=rows('select id from tasks');u,body=post(owner,wp+'/plans/'+plan+'/schedule',{'from':'2027-06-21'},wp+'/plans/'+plan);new=rows('select * from plans where id=?',(u.split('/')[-1],))[0];data=json.loads(new['data']);verify('local scheduling creates draft without changing tasks',new['status']=='draft' and rows('select id from tasks')==before and bool(data['payload']['slots']))
# Stale plan source must never write partially after approval.
payload['tasks'][0]['task_id']=state['tasks'][5];payload['tasks'][0]['title']='Stale plan must not write'
u,_=form(owner,page,'plan-create',{'name':prefix+' stale plan','input_mode':'json','payload':json.dumps(payload)});plan=u.split('/')[-1];path=wp+'/plans/'+plan
for act,expected in [('propose','draft'),('approve','proposed')]:post(owner,path+'/'+act,{'expected_status':expected},path)
form(owner,tp+'/'+state['tasks'][5]+'/edit','task-edit',{'task_type':'WORK','priority':'high','title':prefix+' updated source after approval'})
unchanged('stale plan apply rolls back all business writes',lambda:post(owner,path+'/apply',{'expected_status':'approved'},path))
verify('foreign key integrity',not rows('pragma foreign_key_check'));log('COMPLETE',database=str(DB),no_test_classes=True)
