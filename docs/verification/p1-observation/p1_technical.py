from pathlib import Path
import subprocess,json,hashlib,sqlite3,shutil
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');OUT=R/'docs/verification/p1-observation';PHP=r'C:\xampp\php\php.exe';records=[]
def record(step,**facts):
 records.append({'step':step,**facts});(OUT/'technical.json').write_text(json.dumps(records,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(records[-1],ensure_ascii=True),flush=True)
def run(args):
 p=subprocess.run(args,cwd=R,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=120);record('command',command=' '.join(args),exit_code=p.returncode,output=(p.stdout+p.stderr)[-2500:]);assert p.returncode==0
env=hashlib.sha256((R/'.env').read_bytes()).hexdigest()
names=subprocess.check_output(['git','diff','--name-only'],cwd=R,stderr=subprocess.DEVNULL).decode().splitlines()+subprocess.check_output(['git','ls-files','--others','--exclude-standard'],cwd=R).decode().splitlines();php=sorted({p for p in names if p.startswith('app/') and p.endswith('.php')})
run([PHP,'vendor/bin/pint',*php]);run([PHP,'artisan','view:cache'])
files=[R/p for p in php]+list((R/'storage/framework/views').glob('*.php'))
for p in files:
 check=subprocess.run([PHP,'-l',str(p)],capture_output=True,text=True);assert check.returncode==0,check.stdout+check.stderr
record('source and compiled Blade syntax',files=len(files),passed=True)
run([PHP,'-r',r'''require 'app/Support/MetricDisplay.php'; foreach([null,0,0.11,90] as $m)echo json_encode([$m,App\Support\MetricDisplay::minutes($m)],JSON_UNESCAPED_UNICODE).PHP_EOL;'''])
run(['git','diff','--check'])
c=sqlite3.connect(f'file:{(R/"database/work_management.sqlite").as_posix()}?mode=ro',uri=True);assert not c.execute('pragma foreign_key_check').fetchall();assert not c.execute('select id from jobs').fetchall();c.close()
assert hashlib.sha256((R/'.env').read_bytes()).hexdigest()==env
record('final integrity',foreign_key_errors=0,queue_empty=True,env_preserved=True,no_test_classes_added=not any(p.startswith('tests/') for p in names),migration_added=any(p.startswith('database/migrations/') for p in names))
shutil.copyfile(Path(__file__),OUT/'p1_technical.py')
