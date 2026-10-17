from pathlib import Path
import subprocess,json,sqlite3,hashlib,shutil
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');OUT=R/'docs/verification/final';PHP=r'C:\xampp\php\php.exe';report=[]
def run(args):
 p=subprocess.run(args,cwd=R,capture_output=True,text=True,encoding='utf-8',errors='replace');report.append('> '+' '.join(args)+'\n'+p.stdout+p.stderr+'\nExit: '+str(p.returncode));(OUT/'technical-checks.txt').write_text('\n\n'.join(report),encoding='utf-8')
 if p.returncode:raise RuntimeError(' '.join(args))
 return p.stdout
run([PHP,'vendor/bin/pint','--test','app','config','routes','database','bootstrap/app.php','bootstrap/providers.php','scripts'])
sources=[p for folder in ['app','config','routes','database','bootstrap','scripts'] for p in (R/folder).rglob('*.php') if 'cache' not in p.parts]
for p in sources:
 result=subprocess.run([PHP,'-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
report.append(f'PHP syntax: {len(sources)} source files passed.');print(report[-1],flush=True)
run([PHP,'D:/Composer/composer.phar','validate','--no-check-publish']);run([PHP,'artisan','view:cache','--no-ansi'])
compiled=list((R/'storage/framework/views').glob('*.php'))
for p in compiled:
 result=subprocess.run([PHP,'-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise RuntimeError(str(p)+result.stdout+result.stderr)
report.append(f'Compiled Blade syntax: {len(compiled)} files passed.');print(report[-1],flush=True)
try:
 run([PHP,'artisan','route:cache','--no-ansi']);run([PHP,'artisan','config:cache','--no-ansi'])
finally:
 run([PHP,'artisan','route:clear','--no-ansi']);run([PHP,'artisan','config:clear','--no-ansi'])
routejson=run([PHP,'artisan','route:list','--json']);routes=json.loads(routejson);(OUT/'routes.json').write_text(json.dumps(routes,ensure_ascii=False,indent=2),encoding='utf-8')
run(['git','diff','--check'])
con=sqlite3.connect(f'file:{(R/"database/work_management.sqlite").as_posix()}?mode=ro',uri=True);fk=con.execute('pragma foreign_key_check').fetchall();dupactive=con.execute('select plan_group_id,count(*) from plans where is_active=1 group by plan_group_id having count(*)>1').fetchall();running=con.execute('select user_id,count(*) from time_entries where ended_at is null group by user_id having count(*)>1').fetchall();pending=con.execute("select count(*) from jobs where queue='work-management-ai'").fetchone()[0];con.close();assert not fk and not dupactive and not running
summary={'php_sources':len(sources),'compiled_views':len(compiled),'routes':len(routes),'fk_errors':fk,'duplicate_active_plan_groups':dupactive,'duplicate_running_timers':running,'pending_ai_jobs':pending,'new_test_classes':0,'test_suite_executed':False}
report.append(json.dumps(summary,ensure_ascii=False,indent=2));(OUT/'technical-checks.txt').write_text('\n\n'.join(report),encoding='utf-8');(OUT/'technical-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(summary),flush=True)
