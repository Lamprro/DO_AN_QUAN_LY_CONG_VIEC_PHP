from pathlib import Path
M=Path(__file__).parent;s=(M/'e_flow.py').read_text(encoding='utf-8');exec(s.split("for f in (R/'storage/framework/views')")[0]);journal=json.loads((OUT/'http-flow.json').read_text());state=json.loads((PRIVATE/'state.json').read_text());ids=state['tasks'];prefix=state['prefix'];stamp=prefix.removeprefix('Eutil');secret=json.loads((R/'storage/app/private/p0-review/boundary-state.json').read_text())['private_project_id']
log('resume existing fixture context',reason='Resume helper lacked secret-project variable; no application error or mutation at that step.')
owner=client('resume3-owner');tab_b=client('resume3-b');member=client('resume3-member');viewer=client('resume3-viewer')
for c,email in [(owner,'demo1@example.test'),(tab_b,'demo1@example.test'),(member,'demo3@example.test'),(viewer,'demo2@example.test')]:observed('resume login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
env=hashlib.sha256((R/'.env').read_bytes()).hexdigest();pre_tasks=rows('select * from tasks order by id');pre_ids={r['id'] for r in pre_tasks if r['id'] not in ids};pre_digest=hashlib.sha256(json.dumps([r for r in pre_tasks if r['id'] in pre_ids],sort_keys=True).encode()).hexdigest();now=datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(hours=7)
exec(s[s.index("if not state.get('foreign_task_id')"):])
