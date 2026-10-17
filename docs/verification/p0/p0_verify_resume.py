from pathlib import Path
M=Path(__file__).parent;s=(M/'p0_verify_delivery.py').read_text(encoding='utf-8');exec(s.split('changed=')[0])
result=json.loads((OUT/'technical-checks.json').read_text(encoding='utf-8'))
for p in ['resources/views/projects/index.blade.php','resources/views/tasks/workflow.blade.php']:
 f=R/p;f.write_text('\n'.join(line.rstrip() for line in f.read_text(encoding='utf-8').splitlines())+'\n',encoding='utf-8')
record('fixed whitespace reported by git diff check',files=2,logic_changed=False)
run(['git','diff','--check'])
changed=subprocess.check_output(['git','diff','--name-only'],cwd=R,stderr=subprocess.DEVNULL).decode().splitlines()+subprocess.check_output(['git','ls-files','--others','--exclude-standard'],cwd=R).decode().splitlines()
exec(s[s.index('db=sqlite3.connect'):])
# Store the actual procedural checks, not a TestCase.
import shutil
shutil.copyfile(M/'p0_verify_delivery.py',OUT/'p0_verify_delivery.py');shutil.copyfile(M/'p0_verify_resume.py',OUT/'p0_verify_resume.py')
