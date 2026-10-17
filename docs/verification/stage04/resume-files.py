from pathlib import Path
import shutil
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
s=(R/'docs/verification/stage04/files-final-walkthrough.py').read_text(encoding='utf-8')
exec(s.split('page=tpath')[0])
shutil.copy2(OUT/'files-final-flow.json',OUT/'files-before-404-view-fix.json')
journal=json.loads((OUT/'files-final-flow.json').read_text(encoding='utf-8'))[:-1]
log('layout error boundary fixed',detail='404 routes without web middleware no longer require an errors variable.')
page=tpath+'/'+c
meta=json.loads(rows("select data from task_activities where task_id=? and content='fixture.xlsx' order by created_at desc limit 1",(c,))[0]['data'])
exec(s[s.index("guest=client('guest-final-"):])
