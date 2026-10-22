from pathlib import Path
exec((Path(__file__).parent/'audit_flow.py').read_text(encoding='utf-8').split("owner=login('demo1@example.test')")[0])
stage='pages';journal=[];owner=login('demo1@example.test');w=state['wp'];pr=state['pp'];tp=state['tp'];t=state['tasks'][3]
paths=['/','/workspaces','/workspaces/create',w,w+'/edit',w+'/members',w+'/roles',w+'/roles/create',w+'/operations',w+'/reminders',w+'/projects',w+'/projects/create',pr,pr+'/edit',pr+'/members',pr+'/github',pr+'/timeline',pr+'/timeline?view=list',tp,tp+'?view=board',tp+'/create',tp+'/tree',tp+'/'+t,tp+'/'+t+'/edit','/timers','/notifications','/profile','/settings','/search','/search?q=Completion',w+'/dashboard',w+'/reports',w+'/ai-runs',w+'/ai-runs/create',w+'/plans?project_id='+state['pid'],w+'/plans/create?project_id='+state['pid'],w+'/calendar',w+'/calendar?view=week',w+'/calendar?view=list',w+'/calendar/create']
for p in paths:
 _,body=call(owner,p)
 # Public assets actually referenced by the current layout must exist and respond.
 for asset in set(re.findall(r'(?:src|href)="('+re.escape(BASE)+r'/(?:css|js)/[^"]+)"',body)):
  r=owner.open(asset);assert r.status==200;r.read()
verify('all module pages and referenced assets served',True,pages=len(paths));verify('FK integrity',not rows('pragma foreign_key_check'));log('COMPLETE')
