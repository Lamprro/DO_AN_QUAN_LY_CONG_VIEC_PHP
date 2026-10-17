from pathlib import Path
import subprocess,json,sqlite3
r=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');out=r/'docs/verification/stage05';report=[]
def run(args):
 result=subprocess.run(args,cwd=r,capture_output=True,text=True,encoding='utf-8',errors='replace');report.append('> '+' '.join(args)+'\n'+result.stdout+result.stderr+'\nExit code: '+str(result.returncode));(out/'technical-checks.txt').write_text('\n\n'.join(report),encoding='utf-8')
 if result.returncode:raise RuntimeError('Failed: '+' '.join(args))
 return result.stdout
paths=['app/Services/SettingService.php','app/Services/CalendarService.php','app/Services/CalendarViewService.php','app/Services/NotificationService.php','app/Services/TaskHistory.php','app/Http/Requests/SettingRequest.php','app/Http/Requests/CalendarEventRequest.php','app/Http/Controllers/SettingController.php','app/Http/Controllers/CalendarEventController.php','app/Http/Controllers/TimelineController.php','app/Http/Controllers/NotificationController.php','app/Policies/CalendarEventPolicy.php','app/Providers/AppServiceProvider.php','routes/calendar.php','routes/stage05.php','routes/web.php']
run(['php','vendor/bin/pint','--test',*paths])
sources=[p for folder in ['app','config','routes','database','bootstrap'] for p in (r/folder).rglob('*.php') if 'cache' not in p.parts]
for p in sources:
 result=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
report.append(f'PHP syntax: {len(sources)} source files passed.');print(report[-1])
run(['php','D:/Composer/composer.phar','validate','--no-check-publish'])
run(['php','artisan','view:cache','--no-ansi'])
compiled=list((r/'storage/framework/views').glob('*.php'))
for p in compiled:
 result=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
report.append(f'Compiled Blade PHP syntax: {len(compiled)} files passed.');print(report[-1])
run(['php','artisan','route:cache','--no-ansi']);run(['php','artisan','config:cache','--no-ansi'])
run(['php','artisan','config:clear','--no-ansi']);run(['php','artisan','route:clear','--no-ansi'])
route_text=run(['php','artisan','route:list','--json']);routes=json.loads(route_text);(out/'routes.json').write_text(json.dumps(routes,ensure_ascii=False,indent=2),encoding='utf-8');report.append(f'Route count: {len(routes)}')
run(['git','diff','--check'])
con=sqlite3.connect(f'file:{(r/"database/work_management.sqlite").as_posix()}?mode=ro',uri=True);errors=con.execute('pragma foreign_key_check').fetchall();con.close()
if errors:raise RuntimeError(str(errors))
report.append('Foreign key errors: []. No migrations/reset/key changes. No TestCase, PHPUnit, mocks or test suite executed. Pint --test checks formatting only. Composer is not on PATH; validated with the existing D:/Composer/composer.phar. Business evidence is real HTTP/browser and SQL read-back in the accompanying journals.')
(out/'technical-checks.txt').write_text('\n\n'.join(report),encoding='utf-8');print('All technical checks passed; routes',len(routes))
