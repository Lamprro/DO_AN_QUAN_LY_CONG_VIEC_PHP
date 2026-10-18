from pathlib import Path
import subprocess,json,sqlite3,hashlib,shutil
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP');O=R/'docs/verification/phase-e';PHP=r'C:\xampp\php\php.exe';report=[]
def record(step,**data):
 report.append(dict(step=step,**data));(O/'technical.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8');print(step,flush=True)
def run(args):
 p=subprocess.run(args,cwd=R,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=120);record('command',command=' '.join(args),exit_code=p.returncode,output=(p.stdout+p.stderr)[-6500:]);assert p.returncode==0
files=subprocess.check_output(['git','ls-files','--modified','--others','--exclude-standard'],cwd=R).decode().splitlines()
phpfiles=sorted(set(f for f in files if f.endswith('.php') and not f.endswith('.blade.php')))
run([PHP,'vendor/bin/pint',*phpfiles])
for f in phpfiles:
 p=subprocess.run([PHP,'-l',f],cwd=R,capture_output=True,text=True);assert p.returncode==0,(f,p.stdout,p.stderr)
record('PHP source lint',files=len(phpfiles),all_pass=True)
try:
 run([PHP,'artisan','view:cache']);run([PHP,'artisan','route:cache']);run([PHP,'artisan','config:cache'])
 compiled=list((R/'storage/framework/views').glob('*.php'))
 for f in compiled:
  p=subprocess.run([PHP,'-l',str(f)],cwd=R,capture_output=True,text=True);assert not p.returncode,(str(f),p.stdout,p.stderr)
 record('compiled Blade lint',files=len(compiled),all_pass=True)
 run([PHP,'artisan','schedule:list']);run([PHP,'artisan','migrate:status'])
finally:
 for clear in ['config:clear','route:clear','view:clear']:run([PHP,'artisan',clear])
con=sqlite3.connect(R/'database/work_management.sqlite');con.row_factory=sqlite3.Row
state=json.loads((R/'storage/app/private/phase-e/state.json').read_text());uid=con.execute('select id from users where email=?',('demo1@example.test',)).fetchone()[0];wid,pid=con.execute('select workspace_id,project_id from tasks where id=?',(state['tasks'][0],)).fetchone()
queries=[('assignee scope','select id from tasks where workspace_id=? and project_id=? and assignee_id=? and status=?',(wid,pid,uid,'TODO')),('open blocker','select id from task_blockers where task_id=? and status=?',(state['tasks'][0],'open')),('overlap','select id from time_entries where user_id=? and started_at<? and (ended_at is null or ended_at>?)',(uid,'2026-10-19','2026-10-18'))]
for label,q,args in queries:record('query plan '+label,sql=q,plan=[dict(x) for x in con.execute('explain query plan '+q,args)])
audit=[dict(x) for x in con.execute('select before,after,reason from time_entry_adjustments where time_entry_id=? order by created_at,id',(state['entries'][0],))];assert len(audit)==2
latest=audit[-1];a,b=json.loads(latest['before']),json.loads(latest['after']);assert a['started_at']==b['started_at'] and a['ended_at']==b['ended_at'] and a['note']!=b['note']
record('browser correction SQL read-back',audit_count=len(audit),only_note_changed=True,reason=latest['reason'])
assert not list(con.execute('pragma foreign_key_check'));record('FK integrity',errors=0);con.close()
guard=R/'storage/app/private/phase-e/audit-guard.php';guard.write_text('''<?php
require __DIR__.'/../../../../vendor/autoload.php';
$app=require __DIR__.'/../../../../bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
Illuminate\\Support\\Facades\\DB::beginTransaction();
try{$a=App\\Models\\TimeEntryAdjustment::firstOrFail();try{$a->update(['reason'=>'must be rejected']);throw new RuntimeException('Update was not blocked');}catch(LogicException $e){echo "Update blocked by model guard\\n";}try{$a->delete();throw new RuntimeException('Delete was not blocked');}catch(LogicException $e){echo "Delete blocked by model guard\\n";}}
finally{Illuminate\\Support\\Facades\\DB::rollBack();}
''',encoding='utf-8');run([PHP,str(guard)])
for name in ['e_flow.py','e_resume.py','e_resume2.py','e_resume3.py','e_boundary.py','e_technical.py']:
 shutil.copy2(Path(__file__).parent/name,O/name)
for name in ['e-saved-filter.png','e-time-adjustment.png']:shutil.copy2(Path(__file__).parent/name,O/name)
record('COMPLETE',no_test_classes_added=True,no_main_db_reset=True)
