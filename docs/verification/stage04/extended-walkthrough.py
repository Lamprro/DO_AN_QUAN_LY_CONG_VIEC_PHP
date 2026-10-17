from pathlib import Path
import hashlib,uuid
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((R/'docs/verification/stage04/http-walkthrough.py').read_text(encoding='utf-8').split("observed('owner login'")[0])
state=json.loads(PRIVATE.joinpath('state.json').read_text());stamp=state['stamp'];wid=state['workspace_id'];pid=state['project_id'];group=state['group'];nested=state['nested'];a=state['a'];b=state['b'];m=state['milestone'];c=state['c'];uids=state['uids'];wpath='/workspaces/'+wid;ppath=wpath+'/projects/'+pid;tpath=ppath+'/tasks'
def restore(label):
 pair=client(label+'-'+stamp);pair[1].load(ignore_discard=True,ignore_expires=True);return pair
owner=restore('owner');worker=restore('worker');reviewer=restore('reviewer');viewer=restore('viewer')
def log(label,**facts):
 journal.append({'step':label,**facts});OUT.joinpath('extended-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)

# Real one-minute FS wait; no changed clock or SQL timestamp mutation.
lag_pre=create('P4 Timed Predecessor');lag_next=create('P4 Timed Successor')
for target in ['IN_PROGRESS','REVIEW','DONE']:truth('timed prerequisite '+target,transition(owner,lag_pre,target)==target)
observed('one minute FS dependency',submit(owner,tpath+'/'+lag_next,'dependency-create',{'predecessor_task_id':lag_pre,'lag_minutes':'1'}))
truth('real lag blocks immediately',transition(owner,lag_next,'IN_PROGRESS')=='TODO')
state.update(lag_pre=lag_pre,lag_next=lag_next);PRIVATE.joinpath('state.json').write_text(json.dumps(state))

def multipart(pair,page,filename,payload,mime='text/plain'):
 code,url,body=call(pair,page);csrf=token(body)
 boundary='----P4Boundary'+uuid.uuid4().hex
 data=(f'--{boundary}\r\nContent-Disposition: form-data; name="_token"\r\n\r\n{csrf}\r\n--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode()+payload+f'\r\n--{boundary}--\r\n'.encode()
 req=urllib.request.Request(BASE+page+'/attachments',data=data,headers={'Content-Type':'multipart/form-data; boundary='+boundary,'Referer':BASE+page,'Accept':'text/html'})
 try:r=pair[0].open(req,timeout=30)
 except urllib.error.HTTPError as e:r=e
 pair[1].save(ignore_discard=True,ignore_expires=True)
 return r.code,r.geturl(),r.read().decode('utf-8',errors='replace')
def download(pair,path):
 try:r=pair[0].open(BASE+path,timeout=20)
 except urllib.error.HTTPError as e:r=e
 return r.code,r.geturl(),r.read(),dict(r.headers)
content=b'Stage 04 real upload and authorized download.\nNo credentials.\n'
page=tpath+'/'+c
observed('multipart upload valid TXT',multipart(worker,page,'../../phase4-flow.txt',content))
attachment=rows("select id,data from task_activities where task_id=? and type='ATTACHMENT' and deleted_at is null order by created_at desc limit 1",(c,))[0];aid=attachment['id'];meta=json.loads(attachment['data'])
truth('private file metadata and bytes stored',meta['disk']=='task_files' and meta['sha256']==hashlib.sha256(content).hexdigest() and (ROOT/'storage/app/private/task-files'/meta['path']).exists(),original_name=meta['original_name'],size=meta['size'],sha256=meta['sha256'])
path=page+'/attachments/'+aid
code,url,data,headers=download(viewer,path);truth('authorized Viewer downloads exact bytes',code==200 and data==content,http=code,nosniff=headers.get('X-Content-Type-Options'),content_disposition=headers.get('Content-Disposition'))
observed('upload Viewer forbidden',multipart(viewer,page,'forbidden.txt',b'not allowed'),expected=403)
observed('PHP filename rejected despite text content',multipart(worker,page,'malicious.php',b'<?php echo 1; ?>'))
observed('PNG with text bytes rejected',multipart(worker,page,'fake.png',b'this is not a PNG','image/png'))
observed('over 5MB rejected',multipart(worker,page,'oversize.txt',b'x'*(5*1024*1024+2048)))
truth('invalid files created no attachment rows',rows("select count(*) n from task_activities where task_id=? and type='ATTACHMENT'",(c,))[0]['n']==1)
code,_,_,_=download(owner,tpath+'/'+a+'/attachments/'+aid);truth('file ID under different task forbidden',code==404,http=code)
guest=client('guest-'+stamp);code,url,data,headers=download(guest,path);truth('guest redirected to login',url.endswith('/login'),http=code,final_url=url)
observed('private storage URL not exposed',call(guest,'/storage/task-files/'+meta['path']),expected=404)
observed('other actor cannot remove uploader file',raw_post(worker,tpath+'/'+a+'/attachments/'+aid,{'_method':'DELETE'}),expected=404)
observed('uploader removes file',raw_post(worker,page+'/attachments/'+aid,{'_method':'DELETE'}))
truth('attachment soft deleted, private bytes retained',rows('select deleted_at from task_activities where id=?',(aid,))[0]['deleted_at'] is not None and (ROOT/'storage/app/private/task-files'/meta['path']).exists())
code,_,_,_=download(owner,path);truth('removed attachment cannot download',code==404,http=code)

g2=create('P4 Depth Root','GROUP');g3=create('P4 Depth Middle','GROUP',g2);g4=create('P4 Depth Third','GROUP',g3)
r=submit(owner,tpath+'/create','task-create',{'title':'P4 Depth Four Forbidden','priority':'medium','task_type':'WORK','parent_task_id':g4,'estimate_minutes':'10'});body=observed('fourth level under GROUP rejected',r)
truth('real depth limit message and DB rollback','tối đa 3 cấp' in body and not rows("select id from tasks where project_id=? and title='P4 Depth Four Forbidden'",(pid,)))
rootwork=create('P4 Movable',estimate_minutes='')
observed('reparent and reorder TODO task',submit(owner,tpath+'/'+rootwork+'/edit','task-edit',{'title':'P4 Moved','priority':'medium','task_type':'WORK','parent_task_id':g3,'sort_order':'7','estimate_minutes':''}))
truth('parent/order persisted',rows('select parent_task_id,sort_order from tasks where id=?',(rootwork,))[0]=={'parent_task_id':g3,'sort_order':7})
tree=observed('fallback progress tree',call(owner,tpath+'/tree'));truth('fallback count method visible','Dùng số lượng' in tree)
observed('WORK estimate conversion to GROUP rejected',submit(owner,page+'/edit','task-edit',{'title':'P4 Cannot convert','priority':'medium','task_type':'GROUP','estimate_minutes':''}))
truth('original WORK/estimate retained',rows('select task_type,estimate_minutes from tasks where id=?',(c,))[0]=={'task_type':'WORK','estimate_minutes':60})
observed('cannot delete task containing immutable create history',raw_post(owner,page,{'_method':'DELETE'}));truth('task with create audit retained',bool(rows('select id from tasks where id=?',(c,))))
observed('activity filter pagination',call(owner,page+'?activity_type=HISTORY'))
observed('forged COMMENT type ignored',submit(worker,page,'comment-create',{'content':'P4 must remain COMMENT','type':'HISTORY','source':'ai','actor_id':uids['reviewer'],'data[admin]':'yes'}))
truth('server owns activity type/source/actor',rows("select type,source,actor_id from task_activities where task_id=? and content='P4 must remain COMMENT'",(c,))[0]=={'type':'COMMENT','source':'user','actor_id':uids['worker']})
truth('final FK check',not rows('pragma foreign_key_check'))
log('extended walkthrough complete',lag_next=lag_next,prerequisite_completed_at=rows('select completed_at from tasks where id=?',(lag_pre,))[0]['completed_at'])
