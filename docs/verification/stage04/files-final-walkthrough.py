from pathlib import Path
import base64,io,zipfile,hashlib
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
ext=(R/'docs/verification/stage04/extended-walkthrough.py').read_text(encoding='utf-8')
exec(ext.split('# Real one-minute FS wait')[0])
exec(ext[ext.index('def multipart'):ext.index('content=b')])
def log(label,**facts):
 journal.append({'step':label,**facts});OUT.joinpath('files-final-flow.json').write_text(json.dumps(journal,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(journal[-1],ensure_ascii=True),flush=True)
lag_pre=state['lag_pre'];lag_next=state['lag_next']
page=tpath+'/'+c
bad=rows("select id from task_activities where task_id=? and type='ATTACHMENT' and content='fake.png' and deleted_at is null",(c,))
for old in bad:observed('remove earlier disguised PNG through real endpoint',raw_post(worker,page+'/attachments/'+old['id'],{'_method':'DELETE'}))
before=rows("select count(*) n from task_activities where task_id=? and type='ATTACHMENT'",(c,))[0]['n']
for name,payload,mime in [('bad.png',b'not image','image/png'),('bad.php',b'<?php echo 1; ?>','text/plain'),('fake.docx',b'not office','application/zip'),('oversize.txt',b'x'*(5*1024*1024+1),'text/plain')]:
    body=observed('strict rejection '+name,multipart(worker,page,name,payload,mime));truth('error displayed for '+name,'notice errors' in body)
truth('invalid files created no rows',rows("select count(*) n from task_activities where task_id=? and type='ATTACHMENT'",(c,))[0]['n']==before)
def office(kind):
    b=io.BytesIO()
    with zipfile.ZipFile(b,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>')
        z.writestr('word/document.xml' if kind=='docx' else 'xl/workbook.xml','<?xml version="1.0"?><root>Local Office validation fixture</root>')
    return b.getvalue()
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY9kAAAAASUVORK5CYII=')
for name,payload,mime in [('final.txt',b'Final authorized TXT bytes\n','text/plain'),('tiny.png',png,'image/png'),('fixture.docx',office('docx'),'application/zip'),('fixture.xlsx',office('xlsx'),'application/zip')]:
    body=observed('valid upload '+name,multipart(worker,page,name,payload,mime))
    row=rows("select id,data from task_activities where task_id=? and type='ATTACHMENT' and content=? and deleted_at is null order by created_at desc limit 1",(c,name))
    truth('file row exists '+name,bool(row));record=row[0];meta=json.loads(record['data']);aid=record['id'];path=page+'/attachments/'+aid
    code,url,data,headers=download(viewer,path)
    truth('byte-for-byte authorized download '+name,code==200 and data==payload and meta['sha256']==hashlib.sha256(payload).hexdigest(),http=code,sha256=meta['sha256'],nosniff=headers.get('X-Content-Type-Options'))
    code,_,_,_=download(owner,tpath+'/'+a+'/attachments/'+aid);truth('cross-task attachment scope '+name,code==404)
    observed('uploader removes '+name,raw_post(worker,path,{'_method':'DELETE'}));code,_,_,_=download(owner,path);truth('removed download blocked '+name,code==404)
    truth('audit bytes retained '+name,(ROOT/'storage/app/private/task-files'/meta['path']).exists() and rows('select deleted_at from task_activities where id=?',(aid,))[0]['deleted_at'] is not None)
observed('Viewer upload still forbidden',multipart(viewer,page,'viewer.txt',b'x'),expected=403)
guest=client('guest-final-'+stamp);observed('private disk route unavailable',call(guest,'/storage/task-files/'+meta['path']),expected=404)
truth('FK integrity after upload flows',not rows('pragma foreign_key_check'))
log('file verification complete')
# Resume the remaining tree checks; prior failed evidence remains untouched.
exec(ext[ext.index("g2=create('P4 Depth Root'"):])
