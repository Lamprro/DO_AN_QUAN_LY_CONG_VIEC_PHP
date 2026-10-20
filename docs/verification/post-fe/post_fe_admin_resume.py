from pathlib import Path
s=(Path(__file__).parent/'post_fe_admin.py').read_text(encoding='utf-8');exec(s.split('# Fresh discard')[0]);journal=json.loads((OUT/'flow-admin.json').read_text());
for row in journal:
 if 'path' in row:row['path']=re.sub(r'(/invitations/)[^/]+',r'\1[redacted]',row['path'])
log('resume member form',reason='Helper omitted native hidden status=active; resumed from rendered form. No application contract was changed.')
role=rows("select id from roles where workspace_id=? and name='Member'",(state['wid'],))[0]['id'];wm=rows("select * from workspace_members where workspace_id=? and user_id=(select id from users where email='demo4@example.test')",(state['wid'],))[0]
admin_source=(Path(__file__).parent/'post_fe_admin.py').read_text(encoding='utf-8');exec(admin_source[admin_source.index("form(owner,pp+'/members','project-member-create'"):])
