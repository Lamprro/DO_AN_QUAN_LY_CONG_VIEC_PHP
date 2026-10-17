from pathlib import Path
import subprocess,json,sqlite3,hashlib,time
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');OUT=R/'docs/verification/p0';PHP=r'C:\xampp\php\php.exe';result=[]
def record(step,**facts):
 result.append({'step':step,**facts});(OUT/'technical-checks.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(result[-1],ensure_ascii=True),flush=True)
def run(args):
 p=subprocess.run(args,cwd=R,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=180);record('command',command=' '.join(args),exit_code=p.returncode,output=(p.stdout+p.stderr)[-5000:]);assert p.returncode==0,args
env=hashlib.sha256((R/'.env').read_bytes()).hexdigest()
changed=subprocess.check_output(['git','diff','--name-only'],cwd=R).decode().splitlines()+subprocess.check_output(['git','ls-files','--others','--exclude-standard'],cwd=R).decode().splitlines()
phpfiles=sorted(set(p for p in changed if p.startswith('app/') and p.endswith('.php')))
run([PHP,'vendor/bin/pint',*phpfiles])
run([PHP,'artisan','view:cache'])
files=[]
for folder in ['app','bootstrap','config','database','routes','scripts','storage/framework/views']:
 for p in (R/folder).rglob('*.php'):
  if folder=='bootstrap' and 'cache' in p.relative_to(R/folder).parts:continue
  files.append(p)
for p in files:
 check=subprocess.run([PHP,'-l',str(p)],capture_output=True,text=True)
 assert check.returncode==0,(str(p),check.stdout,check.stderr)
record('all source and compiled Blade PHP syntax',files=len(files),passed=True)
for p in ['public/js/app-ui.js','public/js/plan-editor.js']:run(['node','--check',p])
run([PHP,'D:/Composer/composer.phar','validate','--no-check-publish'])
run([PHP,'artisan','migrate:status'])
try:
 run([PHP,'artisan','route:cache']);run([PHP,'artisan','config:cache'])
finally:run([PHP,'artisan','optimize:clear'])
run(['git','diff','--check'])
db=sqlite3.connect(f'file:{(R/"database/work_management.sqlite").as_posix()}?mode=ro',uri=True);db.row_factory=sqlite3.Row
def rows(s,args=()):return [dict(r) for r in db.execute(s,args)]
state=json.loads((R/'storage/app/private/p0-review/state.json').read_text());wid=state['workspace_id'];boundary=json.loads((R/'storage/app/private/p0-review/boundary-state.json').read_text())
browser=[]
for pid in ['01a14a70-896d-712a-82f9-7538e4d467ca','01a14a7d-ec86-724e-a43d-329ffd2578e4','01a14a7f-5840-7247-8ed9-b840c9c192fd']:
 p=rows('select id,name,status,applied_at,data from plans where id=?',(pid,))[0];data=json.loads(p.pop('data'));p['payload']=data['payload'];browser.append(p);assert p['status']=='draft' and p['applied_at'] is None
assert [r['ref'] for r in browser[0]['payload']['tasks']]==['work1','w_2','w_1']
assert browser[0]['payload']['dependencies'][0]['predecessor']=='w_1' and len(browser[0]['payload']['slots'])==2
three=browser[1]['payload']['tasks'];assert three[1]['parent_ref']=='work1' and three[2]['parent_ref']=='w_1' and three[2]['title']=='Công việc tầng ba'
native=rows('select id from tasks where workspace_id=? and title=?',(wid,'P0 native protocol task'))[0]['id'];assert browser[2]['payload']['tasks'][1]['task_id']==native
meta=rows('select title,status,revision from tasks where id=?',(state['metadata_task_id'],))[0];assert meta['status']=='IN_PROGRESS' and meta['revision']>=3
clock=rows('select id,task_id,started_at,ended_at from time_entries where task_id=?',(boundary['timer_task_id'],));assert clock and all(e['ended_at'] for e in clock)
assert not rows('pragma foreign_key_check');assert not rows('select id from tasks where revision<1');assert not rows('select id from projects where revision<1')
assert hashlib.sha256((R/'.env').read_bytes()).hexdigest()==env
readback={'browser_created_plans':browser,'native_button_task':meta,'stopped_member_timer':clock,'browser_observations':{'menu_open_focused_first_link':True,'escape_returns_focus':'nav-toggle','viewport_width':319,'document_width_at_task':304,'source_search_preserved_typed_title':True,'new_row_source_picker_matches_query_after_fix':True,'three_level_tree_saved':True},'fk_errors':[],'env_preserved':True,'no_test_classes_added':not any(p.startswith('tests/') for p in changed)}
(OUT/'browser-readback.json').write_text(json.dumps(readback,ensure_ascii=False,indent=2),encoding='utf-8');record('real browser SQL read-back',plans=len(browser),metadata_task=meta,timers_ended=len(clock),fk_errors=0,env_preserved=True)
record('delivery checks complete',test_classes_added=False)
