from pathlib import Path
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
s=(R/'docs/verification/stage04/files-final-walkthrough.py').read_text(encoding='utf-8');exec(s.split('page=tpath')[0])
def log(label,**facts):
 journal.append({'step':label,**facts});OUT.joinpath('state-edges.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
fresh=create('P4 Cancel and reopen',assignee_id=uids['worker'],reviewer_id=uids['reviewer'])
truth('cancel requires reason',transition(worker,fresh,'CANCELLED')=='TODO')
truth('cancel with reason',transition(worker,fresh,'CANCELLED','Work no longer needed')=='CANCELLED')
truth('reopen requires reason',transition(worker,fresh,'TODO')=='CANCELLED')
truth('reopen cancelled task',transition(worker,fresh,'TODO','Requirements restored')=='TODO')
truth('reopen clears execution timestamps',rows('select started_at,completed_at from tasks where id=?',(fresh,))[0]=={'started_at':None,'completed_at':None})
truth('reviewer reopens completed milestone',transition(reviewer,m,'TODO','Revise acceptance criteria')=='TODO')
truth('completion timestamp cleared',rows('select completed_at from tasks where id=?',(m,))[0]['completed_at'] is None)
for actor,target in [(worker,'IN_PROGRESS'),(worker,'REVIEW'),(reviewer,'DONE')]:truth('milestone follow full workflow '+target,transition(actor,m,target)==target)
freshpage=tpath+'/'+fresh
observed('add removable dependency',submit(owner,freshpage,'dependency-create',{'predecessor_task_id':a,'lag_minutes':'0'}))
edge=rows('select id from task_dependencies where predecessor_task_id=? and successor_task_id=?',(a,fresh))[0]['id']
observed('duplicate dependency rejected',submit(owner,freshpage,'dependency-create',{'predecessor_task_id':a,'lag_minutes':'0'}))
truth('one dependency row',rows('select count(*) n from task_dependencies where predecessor_task_id=? and successor_task_id=?',(a,fresh))[0]['n']==1)
observed('remove dependency through actual form',submit(owner,freshpage,'dependency-delete',{}));truth('dependency removed and audit retained',not rows('select id from task_dependencies where id=?',(edge,)) and bool(rows("select id from task_activities where task_id=? and event_type='task.dependency_removed'",(fresh,))))
observed('report and resolve blocker',submit(worker,freshpage,'blocker-create',{'reason':'Need access restored'}));blocker=rows('select id from task_blockers where task_id=?',(fresh,))[0]['id']
for status in ['resolved','open','resolved']:
    observed('blocker '+status,raw_post(worker,freshpage+'/blockers/'+blocker,{'_method':'PATCH','status':status}));truth('blocker status persisted '+status,rows('select status from task_blockers where id=?',(blocker,))[0]['status']==status)
no_reviewer=create('P4 Reviewer required',assignee_id=uids['worker'])
truth('start without reviewer allowed',transition(worker,no_reviewer,'IN_PROGRESS')=='IN_PROGRESS')
truth('worker cannot submit without reviewer',transition(worker,no_reviewer,'REVIEW')=='IN_PROGRESS')
truth('FK integrity',not rows('pragma foreign_key_check'))
log('state edge verification complete')
