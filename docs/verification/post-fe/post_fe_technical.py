from pathlib import Path
import subprocess,json,hashlib,sqlite3,re,shutil
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');M=Path(__file__).parent;O=R/'docs/verification/post-fe';PHP=r'C:\xampp\php\php.exe';journal=[]
def log(step,**data):
 journal.append(dict(step=step,**data));(O/'technical.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(step,flush=True)
def run(args):
 p=subprocess.run(args,cwd=R,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=180);log('command',command=' '.join(args),exit_code=p.returncode,output=(p.stdout+p.stderr)[-3000:]);assert not p.returncode
names=subprocess.check_output(['git','ls-files','--modified','--others','--exclude-standard'],cwd=R).decode().splitlines();php=sorted({n for n in names if n.endswith('.php') and not n.endswith('.blade.php')})
run([PHP,'vendor/bin/pint',*php])
for n in php:
 p=subprocess.run([PHP,'-l',n],cwd=R,capture_output=True,text=True);assert not p.returncode,(n,p.stdout)
log('PHP source lint',files=len(php),all_passed=True)
for n in ['public/js/app-ui.js','public/js/theme.js','public/js/plan-editor.js']:run(['node','--check',n])
try:
 for cmd in ['view:cache','route:cache','config:cache']:run([PHP,'artisan',cmd])
 compiled=list((R/'storage/framework/views').glob('*.php'))
 for f in compiled:
  p=subprocess.run([PHP,'-l',str(f)],cwd=R,capture_output=True,text=True);assert not p.returncode,(str(f),p.stdout)
 log('compiled Blade lint',files=len(compiled),all_passed=True)
 run([PHP,'artisan','schedule:list'])
finally:
 for cmd in ['config:clear','route:clear','view:clear']:run([PHP,'artisan',cmd])
# Compare application data before and after every review flow: main database is not the walkthrough target.
baseline=json.loads((R/'storage/app/private/post-fe-review/baseline.json').read_text());assert hashlib.sha256((R/'.env').read_bytes()).hexdigest()==baseline['env_sha256'];c=sqlite3.connect(R/'database/work_management.sqlite');c.row_factory=sqlite3.Row
for t,b in baseline['business'].items():
 data=[dict(x) for x in c.execute('select * from '+t+' order by id')];ok=len(data)==b['count'] and hashlib.sha256(json.dumps(data,sort_keys=True).encode()).hexdigest()==b['sha256'];log('main business unchanged '+t,verified=ok,count=len(data));assert ok
assert not c.execute('pragma foreign_key_check').fetchall();c.close();log('main env/key and FK preserved',verified=True)
runtime=subprocess.check_output([PHP,'-r',"require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();echo json_encode(['ai'=>config('work_management.ai_enabled'),'integrations'=>config('work_management.integrations_enabled')]);"],cwd=R).decode();assert json.loads(runtime)=={'ai':False,'integrations':False};log('provider flags unchanged',flags=json.loads(runtime))
# Preserve prior reports; save this review separately. Token-bearing paths are redacted.
for f in O.glob('*.json'):
 s=f.read_text(encoding='utf-8');s=re.sub(r'(/invitations/)[A-Za-z0-9_-]+',r'\1[redacted]',s);f.write_text(s,encoding='utf-8')
for name in ['post_fe_flow.py','post_fe_admin.py','post_fe_admin_resume.py','post_fe_smoke.py','post_fe_technical.py']:shutil.copy2(M/name,O/name)
log('COMPLETE',no_test_classes=True,main_business_not_mutated=True)
