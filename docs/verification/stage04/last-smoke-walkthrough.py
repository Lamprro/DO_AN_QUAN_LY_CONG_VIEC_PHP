from pathlib import Path
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
s=(R/'docs/verification/stage04/files-final-walkthrough.py').read_text(encoding='utf-8');exec(s.split('page=tpath')[0]);exec(ext[ext.index('def multipart'):ext.index('content=b')])
def log(label,**facts):
 journal.append({'step':label,**facts});OUT.joinpath('last-smoke.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
target=rows("select id from tasks where project_id=? and title='P4 Cancel and reopen' order by created_at desc limit 1",(pid,))[0]['id'];blocker=rows('select id from task_blockers where task_id=?',(target,))[0]['id']
for actor,status in [(worker,'open'),(reviewer,'resolved')]:
 observed('resolve policy action '+status,raw_post(actor,tpath+'/'+target+'/blockers/'+blocker,{'_method':'PATCH','status':status}));truth('resolver policy DB '+status,rows('select status from task_blockers where id=?',(blocker,))[0]['status']==status)
observed('Viewer cannot resolve',raw_post(viewer,tpath+'/'+target+'/blockers/'+blocker,{'_method':'PATCH','status':'open'}),expected=403)
page=tpath+'/'+c;content='Tên Unicode, bytes thật.\n'.encode('utf-8')
observed('Unicode filename upload',multipart(worker,page,'báo cáo.txt',content))
row=rows("select id,data from task_activities where task_id=? and type='ATTACHMENT' and content='báo cáo.txt' and deleted_at is null order by created_at desc limit 1",(c,))[0];meta=json.loads(row['data'])
code,url,data,headers=download(viewer,page+'/attachments/'+row['id']);truth('Unicode file exact download',code==200 and data==content and meta['original_name']=='báo cáo.txt',http=code)
observed('remove Unicode file',raw_post(worker,page+'/attachments/'+row['id'],{'_method':'DELETE'}))
body=observed('DONE view final smoke',call(owner,page));truth('DONE hides metadata edit and invalid physical delete','data-form="task-delete"' not in body and 'href="'+BASE+page+'/edit"' not in body)
truth('final FK integrity',not rows('pragma foreign_key_check'))
log('final smoke complete')
