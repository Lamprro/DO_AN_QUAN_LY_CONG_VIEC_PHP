from pathlib import Path
M=Path(__file__).parent;s=(M/'p0_boundary_flow.py').read_text(encoding='utf-8')
exec(s.split('roles={r')[0])
journal=json.loads((OUT/'boundary-flow.json').read_text(encoding='utf-8')) if False else journal
# Earlier invocation stopped at a helper URL typo (404); actual route is /timers/{entry}/stop.
log('resume after helper route typo',correct_route='/timers/{entry}/stop',application_change=False)
roles={r['name']:r['id'] for r in rows('select id,name from roles where workspace_id=?',(wid,))}
muid=rows("select id from users where email='demo3@example.test'")[0]['id'];wm=rows('select id from workspace_members where workspace_id=? and user_id=?',(wid,muid))[0]['id']
mt=rows("select id from tasks where workspace_id=? and title='P0 private running task'",(wid,))[0]['id'];entry=rows('select id from time_entries where user_id=? and ended_at is null',(muid,))[0]['id']
exec(s[s.index("observed('Owner cannot stop another user clock'"):])
