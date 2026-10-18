from pathlib import Path
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
exec((R/'docs/verification/p0/p0_http_walkthrough.py').read_text(encoding='utf-8').split("owner=client('owner')")[0])
OUT=R/'docs/verification/p1-observation';OUT.mkdir(parents=True,exist_ok=True);PRIVATE=R/'storage/app/private/p1-observation';PRIVATE.mkdir(parents=True,exist_ok=True);journal=[]
old=json.loads((R/'storage/app/private/p0-review/state.json').read_text());boundary=json.loads((R/'storage/app/private/p0-review/boundary-state.json').read_text());wid=old['workspace_id'];pid=old['project_id'];uid=old['owner_id'];wp='/workspaces/'+wid;pp=wp+'/projects/'+pid;cp=wp+'/calendar';state={};env=hashlib.sha256((R/'.env').read_bytes()).hexdigest()
def checkpoint(): (PRIVATE/'state.json').write_text(json.dumps(state),encoding='utf-8')
for f in (R/'storage/framework/views').glob('*.php'):
 p=subprocess.run([r'C:\xampp\php\php.exe','-l',str(f)],capture_output=True,text=True)
 if p.returncode:raise RuntimeError(p.stdout+p.stderr)
log('compiled Blade syntax checked',files=len(list((R/'storage/framework/views').glob('*.php'))))
owner=client('owner');member=client('member');viewer=client('viewer');outsider=client('outsider')
for c,email in [(owner,'demo1@example.test'),(member,'demo3@example.test'),(viewer,'demo2@example.test'),(outsider,'demo5@example.test')]:observed('login '+email,submit_live(c,'/login','login',{'email':email,'password':'Demo@123456'}))
def created(label,r,table):
 observed(label,r);key=r[1].rstrip('/').split('/')[-1];verify(label+' SQL exists',bool(rows('select id from '+table+' where id=?',(key,))));return key
tid=created('create P1 own WORK',submit_live(owner,pp+'/tasks/create','task-create',{'title':'P1 calendar/report '+stamp,'task_type':'WORK','priority':'high','estimate_minutes':'60','assignee_id':uid,'reviewer_id':uid}),'tasks');state.update(task_id=tid,workspace_id=wid,project_id=pid);checkpoint()
body=observed('task-to-calendar prefill',call(owner,cp+'/create?project_id='+pid+'&task_id='+tid));verify('prefill uses own eligible task','value="'+tid+'" selected' in body or re.search(r'value="'+tid+r'"[^>]*selected',body) is not None)
event={'title':'P1 visible block '+stamp,'event_type':'TASK_BLOCK','task_id':tid,'project_id':pid,'timezone':'Asia/Ho_Chi_Minh','starts_at':'2027-05-17T08:00','ends_at':'2027-05-17T09:00','all_day':'0','visibility':'project','is_busy':'1','status':'planned'}
eid=created('save own real TASK_BLOCK',submit_live(owner,cp+'/create?project_id='+pid+'&task_id='+tid,'calendar-save',event),'calendar_events');state['event_id']=eid;checkpoint()
secret={'title':'P1 PRIVATE SENTINEL '+stamp,'description':'P1 PRIVATE CONTENT','event_type':'MEETING','project_id':pid,'timezone':'Asia/Ho_Chi_Minh','starts_at':'2027-05-17T10:00','ends_at':'2027-05-17T11:00','all_day':'0','visibility':'private','is_busy':'1','status':'planned'}
private=created('Member creates own private meeting',submit_live(member,cp+'/create','calendar-save',secret),'calendar_events');state['private_event_id']=private;checkpoint()
for view in ['day','week','month','list']:
 page=cp+'?view='+view+'&date=2027-05-17&project_id='+pid
 body=observed('calendar '+view,call(owner,page));verify('private projection '+view,'P1 PRIVATE SENTINEL' not in body and 'P1 PRIVATE CONTENT' not in body and 'data-event-id="'+private+'"' not in body and 'Bận (riêng tư)' in body)
 verify('own authorized block rendered '+view,'data-event-id="'+eid+'"' in body and 'Asia/Ho_Chi_Minh' in body)
 if view=='day':
  links=re.findall(r'href="([^"]+)"[^>]*>(?:← Trước|Sau →|Hôm nay)</a>',body);verify('navigation preserves project scope',len(links)==3 and all(pid in html.unescape(x) for x in links))
body=observed('day right edge excluded',call(owner,cp+'?view=day&date=2027-05-18&project_id='+pid));verify('no previous day events',eid not in body and 'Không có sự kiện' in body)
body=observed('custom range navigation',call(owner,cp+'?view=list&from=2027-05-17&to=2027-05-19&project_id='+pid));verify('next custom period keeps duration','from=2027-05-19' in body and 'to=2027-05-21' in body)
body=observed('timeline clipped real slot',call(owner,pp+'/timeline?view=day&date=2027-05-17'));verify('axis and accessible list','timeline-axis' in body and 'Danh sách tương đương' in body and 'P1 visible block' in body and 'P1 PRIVATE' not in body)
body=observed('other project filter excludes first project events',call(owner,cp+'?view=day&date=2027-05-17&project_id='+boundary['private_project_id']));verify('calendar project scope actually filtered','P1 visible block' not in body and 'Bận (riêng tư)' not in body)
observed('outsider calendar denied',call(outsider,cp+'?view=day&date=2027-05-17&project_id='+pid),403)
observed('Viewer cannot prefill someone else task',call(viewer,cp+'/create?project_id='+pid+'&task_id='+tid),403)
before=business();body=observed('overlarge calendar range refused',call(owner,cp+'?view=list&from=2027-01-01&to=2027-06-01&project_id='+pid));verify('range error has no business writes',before==business() and 'tối đa 93 ngày' in body)
# Calendar conflict still rolls back and adjacent events remain legal.
clash={**event,'title':'P1 MUST NOT SAVE overlap','starts_at':'2027-05-17T08:59','ends_at':'2027-05-17T09:15'}
before=business();observed('real overlap refused',submit_live(owner,cp+'/create','calendar-save',clash));verify('overlap not saved',before==business())
adj={**event,'title':'P1 adjacent block '+stamp,'starts_at':'2027-05-17T09:00','ends_at':'2027-05-17T09:30'};created('adjacent TASK_BLOCK allowed',submit_live(owner,cp+'/create','calendar-save',adj),'calendar_events')
# Snapshot source May: no historical actuals fabricated from planned slots.
filters={'from':'2027-05-01','to':'2027-06-01','project_id':pid,'include_archived':'0'};dash=wp+'/dashboard?'+urllib.parse.urlencode(filters)
body=observed('dashboard real aggregates',call(owner,dash));verify('charts show source units and null actual','data-planned-actual' in body and 'data-minutes="null"' in body and '90' in body)
r=submit_live(owner,dash,'report-create',{});snapshot=created('save immutable report',r,'report_snapshots');state['snapshot_id']=snapshot;checkpoint();snap=rows('select metrics_json from report_snapshots where id=?',(snapshot,))[0]['metrics_json'];m=json.loads(snap)
verify('snapshot SQL period source',m['period_metrics']['planned_minutes']==90 and m['period_metrics']['actual_minutes'] is None,planned_minutes=m['period_metrics']['planned_minutes'],actual_minutes=m['period_metrics']['actual_minutes'])
body=observed('snapshot metadata visible',call(owner,wp+'/reports/'+snapshot));verify('snapshot creator/fixed label','Số liệu đã lưu' in body and 'Người tạo: Owner' in body and 'null' in body)
observed('snapshot print',call(owner,wp+'/reports/'+snapshot+'/print'));csv=observed('snapshot CSV',call(owner,wp+'/reports/'+snapshot+'/export'))
import csv as csvlib,io
csvrows=list(csvlib.reader(io.StringIO(csv.lstrip('\ufeff'))));verify('CSV preserves raw minute units',any(r[0]=='Giờ dự kiến (phút)' and float(r[1])==90 for r in csvrows) and 'Chưa có dữ liệu' in csv)
observed('mutate source after snapshot',submit_live(owner,pp+'/tasks/'+tid+'/edit','task-edit',{'title':'P1 task edited after snapshot '+stamp,'task_type':'WORK','priority':'urgent','estimate_minutes':'75','assignee_id':uid,'reviewer_id':uid}));verify('stored metrics immutable',rows('select metrics_json from report_snapshots where id=?',(snapshot,))[0]['metrics_json']==snap)
observed('other user cannot read Owner snapshot',call(member,wp+'/reports/'+snapshot),403)
# Work time is produced by actual start/wait/stop, never SQL timestamp edits.
observed('start WORK via actual form',submit_live(owner,pp+'/tasks/'+tid,'task-status',{'status':'IN_PROGRESS'}));observed('start timer',post(owner,wp+'/timer/'+tid,{},pp+'/tasks/'+tid));time.sleep(2);observed('stop own global timer',submit_live(owner,pp+'/tasks/'+tid,'global-timer-stop',{}))
october=wp+'/dashboard?from=2026-10-01&to=2026-11-01&project_id='+pid;body=observed('actual duration in current recorded period',call(owner,october));verify('ended session seconds displayed','giây' in body and 'data-minutes="null"' not in re.search(r'<table[^>]*data-planned-actual.*?</table>',body,re.S)[0])
observed('Member comments to notify Owner',submit_live(member,pp+'/tasks/'+tid,'comment-create',{'content':'P1 real notification '+stamp}));nid=rows('select id from notifications where task_id=? and recipient_id=? order by created_at desc,id desc',(tid,uid))[0]['id'];state['notification_id']=nid;checkpoint()
body=observed('notification type + unread filter',call(owner,'/notifications?filter=unread&type=task.comment_added'));verify('new notice in correct filter',nid in body)
observed('read rendered notification form',post(owner,'/notifications/'+nid+'/read',{'_method':'PATCH','unread':'0'},'/notifications'));verify('read_at saved',rows('select read_at from notifications where id=?',(nid,))[0]['read_at'] is not None)
body=observed('read-filter includes notice',call(owner,'/notifications?filter=read&type=task.comment_added'));verify('read filter server query',nid in body)
observed('foreign recipient cannot read notice',post(member,'/notifications/'+nid+'/read',{'_method':'PATCH','unread':'0'},'/notifications'),404)
body=observed('GitHub state without enabling connection',call(owner,pp+'/github'));verify('no fake network success','tắt' in body and ('Chưa có lượt nhận' in body or 'data-latest-delivery' in body))
# A real database queue worker handles ONLY the one newly created job.
verify('queue empty before own request',not rows('select id from jobs'))
ai=wp+'/ai-runs/create?'+urllib.parse.urlencode(filters);body=observed('AI configuration state',call(owner,ai));verify('disabled config truthfully visible','data-ai-configured="0"' in body)
run=created('queue real report request',submit_live(owner,ai,'ai-run-create',{**filters,'user_prompt':'P1 read-only report review '+stamp}), 'ai_runs');state['ai_run_id']=run;checkpoint();verify('only newly requested job pending',len(rows('select id from jobs'))==1)
worker=subprocess.run([r'C:\xampp\php\php.exe','artisan','queue:work','database','--once','--queue=work-management-ai','--tries=1'],cwd=R,capture_output=True,text=True,timeout=60);log('real worker once',exit_code=worker.returncode,output=worker.stdout+worker.stderr);verify('worker handled configuration failure',worker.returncode==0 and rows('select status from ai_runs where id=?',(run,))[0]['status']=='failed')
body=observed('failed AI safe state + attempts',call(owner,wp+'/ai-runs/'+run));verify('no fake success and retry context explained','Thất bại' in body and 'context cũ' in body and 'Đã dùng 1/3 lượt' in body)
verify('env/key/flags unchanged',env==hashlib.sha256((R/'.env').read_bytes()).hexdigest());verify('foreign keys valid',not rows('pragma foreign_key_check'));state['complete']=True;checkpoint();log('P1 observation flow complete',no_test_classes=True,live_http=True)
