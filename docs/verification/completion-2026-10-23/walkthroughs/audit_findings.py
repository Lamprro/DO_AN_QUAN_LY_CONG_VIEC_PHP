from pathlib import Path
exec((Path(__file__).parent/'audit_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
stage='findings-before';journal=[];owner=login('demo1@example.test');tp=state['tp'];wp=state['wp'];pp=state['pp']
_,body=call(owner,tp+'?'+urllib.parse.urlencode({'q':'%_','per_page':'100'}))
log('BUG literal wildcard search in task list',observed_unrelated_task=state['tasks'][0] in body,expected=False)
url,_=form(owner,wp+'/projects/create','project-create',{'name':prefix+' literal%_ project','status':'draft'})
special=url.split('/')[-1];_,body=call(owner,wp+'/projects?'+urllib.parse.urlencode({'q':'%_'}))
log('BUG literal wildcard search in project list',observed_unrelated_project=state['pid'] in body,expected=False)
uid=state['uid'];tid=work(owner,tp,prefix+' literal history',assignee_id=uid,reviewer_id=uid)
form(owner,tp+'/'+tid+'/edit','task-edit',{'title':'low','description':'DONE','task_type':'WORK','priority':'high'})
_,body=call(owner,tp+'/'+tid)
log('BUG audit text translated as enum',title_translated=bool(re.search(r'<strong>Thấp</strong>',body)),description_translated=bool(re.search(r'<strong>Hoàn thành</strong>',body)),expected=False)
state['history_task']=tid;state['literal_project']=special;checkpoint()
