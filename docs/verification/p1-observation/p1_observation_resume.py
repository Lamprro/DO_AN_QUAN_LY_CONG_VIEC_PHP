from pathlib import Path
M=Path(__file__).parent;s=(M/'p1_observation_flow.py').read_text(encoding='utf-8');exec(s.split("for f in (R/'storage/framework/views')")[0])
journal=json.loads((OUT/'http-flow.json').read_text(encoding='utf-8'));state=json.loads((PRIVATE/'state.json').read_text(encoding='utf-8'))
log('CSV check helper corrected',reason='Use CSV parser for quoted fields, not raw substring; application export unchanged.')
owner=client('resume-owner');member=client('resume-member')
for c,email in [(owner,'demo1@example.test'),(member,'demo3@example.test')]:observed('resume login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
exec(s[s.index('def created('):s.index("tid=created(")])
tid=state['task_id'];snapshot=state['snapshot_id'];snap=rows('select metrics_json from report_snapshots where id=?',(snapshot,))[0]['metrics_json'];filters={'from':'2027-05-01','to':'2027-06-01','project_id':pid,'include_archived':'0'}
exec(s[s.index("observed('snapshot print'"):])
