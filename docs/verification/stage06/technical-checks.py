from pathlib import Path
import subprocess,json,sqlite3
r=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');out=r/'docs/verification/stage06';report=[]
def run(args):
 p=subprocess.run(args,cwd=r,capture_output=True,text=True,encoding='utf-8',errors='replace');report.append('> '+' '.join(args)+'\n'+p.stdout+p.stderr+'\nExit code: '+str(p.returncode));(out/'technical-checks.txt').write_text('\n\n'.join(report),encoding='utf-8')
 if p.returncode:raise RuntimeError(' '.join(args))
 return p.stdout
paths=['app/Contracts','app/Data','app/Exceptions','app/Jobs','app/Support/ReportAccess.php','app/Services/ReportService.php','app/Services/AiRunService.php','app/Services/UnconfiguredAiProvider.php','app/Policies/ReportSnapshotPolicy.php','app/Policies/AiRunPolicy.php','app/Http/Controllers/DashboardController.php','app/Http/Controllers/ReportController.php','app/Http/Controllers/AiRunController.php','app/Http/Requests/ReportRequest.php','app/Http/Requests/AiRunRequest.php','app/Providers/AppServiceProvider.php','app/Observers/IntegrityObserver.php','config/ai.php','config/work_management.php','routes/stage06.php','routes/web.php','routes/console.php']
run(['php','vendor/bin/pint','--test',*paths])
sources=[p for d in ['app','config','routes','database','bootstrap'] for p in (r/d).rglob('*.php') if 'cache' not in p.parts]
for p in sources:
 result=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
report.append(f'PHP syntax: {len(sources)} source files passed.');print(report[-1],flush=True)
run(['php','D:/Composer/composer.phar','validate','--no-check-publish']);run(['php','artisan','view:cache','--no-ansi'])
compiled=list((r/'storage/framework/views').glob('*.php'))
for p in compiled:
 result=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
report.append(f'Compiled Blade PHP syntax: {len(compiled)} files passed.');print(report[-1],flush=True)
run(['php','artisan','route:cache','--no-ansi']);run(['php','artisan','config:cache','--no-ansi']);run(['php','artisan','route:clear','--no-ansi']);run(['php','artisan','config:clear','--no-ansi'])
routes=json.loads(run(['php','artisan','route:list','--json']));(out/'routes.json').write_text(json.dumps(routes,ensure_ascii=False,indent=2),encoding='utf-8');report.append(f'Route count: {len(routes)}')
run(['git','diff','--check'])
db=sqlite3.connect(f'file:{(r/"database/work_management.sqlite").as_posix()}?mode=ro',uri=True)
errors=db.execute('pragma foreign_key_check').fetchall();running=db.execute("select count(*) from ai_runs where status='running'").fetchone()[0];jobs=db.execute("select count(*) from jobs where queue='work-management-ai'").fetchone()[0];db.close()
if errors:raise RuntimeError(str(errors))
report.append(f'Foreign key errors: {errors}; stage queue pending: {jobs}; AI running: {running}.')
if running==0:run(['php','artisan','ai:recover-stale','--no-ansi'])
run(['php','-r','echo "pcntl_alarm=".(function_exists("pcntl_alarm") ? "yes" : "no").PHP_EOL;'])
report.append('No TestCase/PHPUnit/mocks/test suite added or executed. Pint --test only checks formatting. No migration/reset/APP_KEY/.env writes. Provider remains unconfigured, AI_ENABLED false by default. Real worker configuration/error/cancel/retry/permission paths were verified; external provider success/HTTP timeout/transient automatic retry and hard-kill recovery are not verified. Windows has no pcntl_alarm, so do not claim the queue timeout was hard-enforced on this host.')
(out/'technical-checks.txt').write_text('\n\n'.join(report),encoding='utf-8');print('Technical checks passed, routes',len(routes),flush=True)
