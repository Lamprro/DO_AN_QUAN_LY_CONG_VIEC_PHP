from pathlib import Path
M=Path(__file__).parent;s=(M/'e_flow.py').read_text(encoding='utf-8');exec(s.split("for f in (R/'storage/framework/views')")[0]);journal=json.loads((OUT/'http-flow.json').read_text());state=json.loads((PRIVATE/'state.json').read_text());ids=state['tasks'];sid=state['saved_filter_id'];prefix=state['prefix'];stamp=prefix.removeprefix('Eutil')
log('resume with correct negative-flow expectation',reason='REVIEW row preflight is forbidden403, not validation redirect200; adding a separate late domain rollback check.')
owner=client('resume2-owner');tab_b=client('resume2-b');member=client('resume2-member');viewer=client('resume2-viewer')
for c,email in [(owner,'demo1@example.test'),(tab_b,'demo1@example.test'),(member,'demo3@example.test'),(viewer,'demo2@example.test')]:observed('resume login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
env=hashlib.sha256((R/'.env').read_bytes()).hexdigest();pre_tasks=rows('select * from tasks order by id');pre_ids={r['id'] for r in pre_tasks if r['id'] not in ids};pre_digest=hashlib.sha256(json.dumps([r for r in pre_tasks if r['id'] in pre_ids],sort_keys=True).encode()).hexdigest();now=datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(hours=7)
exec(s[s.index("before=business();observed('mixed priority batch"):])
