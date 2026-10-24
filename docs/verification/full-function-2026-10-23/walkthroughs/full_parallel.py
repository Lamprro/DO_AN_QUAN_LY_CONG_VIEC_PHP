from pathlib import Path
exec((Path(__file__).parent/'full_flow.py').read_text(encoding='utf-8').split("\nowner=login('demo1@example.test')")[0])
stage='parallel';journal=[];owner=login('demo1@example.test');wp,pid=state['wp'],state['pid'];page=wp+'/task-templates?project_id='+pid;suffix=str(int(time.time()))
values={'project_id':pid,'name':prefix+' parallel '+suffix,'frequency':'daily','interval':1,'timezone':'Asia/Ho_Chi_Minh','starts_on':(datetime.date.today()-datetime.timedelta(days=1)).isoformat(),'local_time':'07:00','task_ids[]':[state['group'],state['a'],state['b']]}
url,_=form(owner,page,'template-create',values);template=url.split('/')[-1];before=rows('select id from tasks')
procs=[subprocess.Popen([os.environ.get('WM_PHP','php'),'artisan','tasks:generate-recurring'],cwd=R,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for _ in range(2)]
results=[]
for p in procs:
 out,err=p.communicate(timeout=90);results.append({'exit':p.returncode,'stdout':out.strip(),'stderr':err[-400:]})
runs=rows('select * from task_template_runs where task_template_id=?',(template,));verify('two actual PHP processes generate one occurrence and three tasks',all(r['exit']==0 for r in results) and len(runs)==1 and runs[0]['status']=='succeeded' and len(rows('select id from tasks'))==len(before)+3,processes=results)
verify('parallel generation creates no duplicate task UUIDs',len(set(json.loads(runs[0]['task_map']).values()))==3)
verify('foreign keys valid after parallel workers',not rows('pragma foreign_key_check'));log('COMPLETE',php_processes=2,shared_real_database=True,no_mock=True)
