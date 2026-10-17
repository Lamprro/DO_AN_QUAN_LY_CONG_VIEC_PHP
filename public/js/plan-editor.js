(() => {
 'use strict';
 const form=document.querySelector('#plan-editor');if(!form)return;
 const data=JSON.parse(document.querySelector('#plan-editor-data').textContent),taskBox=document.querySelector('#editor-tasks'),depBox=document.querySelector('#editor-dependencies'),slotBox=document.querySelector('#editor-slots'),template=document.querySelector('#plan-task-template'),mode=document.querySelector('#input-mode'),raw=document.querySelector('#payload'),packed=document.querySelector('#payload-packed'),errors=document.querySelector('#editor-errors');
 form.querySelector('[name=editor_enabled]').value='1';let serial=taskBox.children.length,counter=0;
 const rows=()=>[...taskBox.querySelectorAll('[data-task-row]')];
 const field=(row,name)=>row?.querySelector(`[data-field="${name}"]`);
 const val=(row,name)=>field(row,name)?.value||null;
 const set=(row,name,value)=>{const el=field(row,name);if(!el)return;if(el.tagName==='SELECT'&&value&&!Array.from(el.options).some(o=>o.value===String(value)))el.add(new Option('Giữ giá trị cũ / cần kiểm tra',value));el.value=value??'';};
 const message=items=>{errors.replaceChildren();if(!items.length){errors.hidden=true;return;}const ul=document.createElement('ul');items.forEach(text=>{const li=document.createElement('li');li.textContent=text;ul.append(li);});errors.append(ul);errors.hidden=false;errors.focus();};
 const rowData=row=>{const r={};for(const name of ['ref','task_id','title','description','task_type','parent_ref','priority','estimate_minutes','assignee_id','reviewer_id'])r[name]=val(row,name);if(r.estimate_minutes!==null)r.estimate_minutes=Number(r.estimate_minutes);return r;};
 const options=(select,items,keep=select.value)=>{select.replaceChildren(new Option('Chọn…',''));items.forEach(r=>select.add(new Option(r.label,r.ref)));if(keep&&!items.some(r=>r.ref===keep))select.add(new Option(keep+' (tham chiếu cần sửa)',keep));select.value=keep||'';};
 const refresh=()=>{
  const all=rows().map(rowData),groups=all.filter(r=>r.task_type==='GROUP').map(r=>({ref:r.ref,label:r.ref+' — '+r.title})),leaves=all.filter(r=>r.task_type!=='GROUP').map(r=>({ref:r.ref,label:r.ref+' — '+r.title})),works=all.filter(r=>r.task_type==='WORK').map(r=>({ref:r.ref,label:r.ref+' — '+r.title}));
  rows().forEach(row=>{const r=rowData(row),parent=field(row,'parent_ref');options(parent,groups.filter(g=>g.ref!==r.ref),parent.value||parent.dataset.current);delete parent.dataset.current;row.querySelector('[data-row-ref]').textContent=r.ref;
   const estimate=field(row,'estimate_minutes');const hideEstimate=r.task_type!=='WORK';estimate.disabled=hideEstimate;estimate.closest('[data-work-only]').hidden=hideEstimate;
   row.querySelectorAll('[data-assignment]').forEach(box=>{box.hidden=r.task_type==='GROUP';box.querySelector('select').disabled=r.task_type==='GROUP';});
   let depth=0,p=r.parent_ref,seen=new Set([r.ref]);while(p&&!seen.has(p)&&depth<3){seen.add(p);depth++;p=all.find(x=>x.ref===p)?.parent_ref;}row.style.marginLeft=(depth*8)+'px';
  });
  depBox.querySelectorAll('select').forEach(s=>options(s,leaves));slotBox.querySelectorAll('select').forEach(s=>options(s,works));
  const work=all.filter(r=>r.task_type==='WORK');document.querySelector('#editor-summary').textContent=`${all.length} dòng · ${all.filter(r=>r.task_type==='GROUP').length} nhóm · ${work.length} công việc · ${work.filter(r=>r.estimate_minutes===null).length} chưa ước lượng · ${work.reduce((n,r)=>n+(Number(r.estimate_minutes)||0),0)} phút dự kiến. Đây là số liệu bản nháp.`;
 };
 const freshRef=()=>{let ref;do{ref='w_'+(++counter);}while(rows().some(r=>val(r,'ref')===ref));return ref;};
 const fillSource=(row,source,seen=new Set())=>{
  if(seen.has(source.id))return message(['Nguồn có vòng nhóm cha; không thể tự thêm.']);seen.add(source.id);
  for(const key of ['task_id','title','description','task_type','priority','estimate_minutes','assignee_id','reviewer_id'])set(row,key,key==='task_id'?source.id:source[key]);
  row.dataset.previousType=source.task_type;
  if(source.parent_task_id){let parent=rows().find(r=>val(r,'task_id')===source.parent_task_id);if(!parent){const sourceParent=data.tasks.find(t=>t.id===source.parent_task_id);if(!sourceParent?.editable)return message(['Nhóm cha của nguồn không còn sửa được. Hãy rà soát nhóm cha trước khi lưu.']);parent=addTask(false);if(!parent)return;fillSource(parent,sourceParent,seen);}refresh();set(row,'parent_ref',val(parent,'ref'));}else set(row,'parent_ref',null);
  refresh();
 };
 const sourceOptions=row=>{const select=field(row,'task_id'),keep=select.value;select.replaceChildren(new Option('Tạo công việc mới',''));data.tasks.forEach(t=>{const option=new Option(t.label+(t.editable?'':' (không sửa được)'),t.id);option.disabled=!t.editable&&t.id!==keep;select.add(option);});if(keep&&!data.tasks.some(t=>t.id===keep))select.add(new Option('Nguồn đã chọn cần kiểm tra lại',keep));select.value=keep;};
 const wireTask=row=>{
  sourceOptions(row);
  row.dataset.previousType=val(row,'task_type');
  row.addEventListener('input',refresh);
  row.addEventListener('change',e=>{if(e.target===field(row,'task_type')){
   const type=val(row,'task_type'),before=row.dataset.previousType;const names=type==='GROUP'?['estimate_minutes','assignee_id','reviewer_id']:type==='MILESTONE'?['estimate_minutes']:[];
   if(names.some(k=>val(row,k)!==null)&&!window.confirm('Đổi loại sẽ bỏ các ước lượng/phân công không áp dụng trong bản nháp. Tiếp tục?')){set(row,'task_type',before);return refresh();}
   names.forEach(k=>set(row,k,null));row.dataset.previousType=type;
  }
  if(e.target===field(row,'task_id')){const src=data.tasks.find(t=>t.id===val(row,'task_id'));if(src)fillSource(row,src);}
  refresh();});
  row.querySelectorAll('[data-move]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.move==='up'&&row.previousElementSibling)taskBox.insertBefore(row,row.previousElementSibling);if(button.dataset.move==='down'&&row.nextElementSibling)taskBox.insertBefore(row.nextElementSibling,row);form.dispatchEvent(new Event('input',{bubbles:true}));refresh();}));
  row.querySelector('[data-remove-task]').addEventListener('click',()=>{const ref=val(row,'ref');if(rows().some(r=>val(r,'parent_ref')===ref)||[...depBox.querySelectorAll('select'),...slotBox.querySelectorAll('select')].some(s=>s.value===ref))return message(['Dòng '+ref+' đang được dùng làm cha/dependency/slot. Xử lý các tham chiếu trước khi bỏ dòng.']);if(!window.confirm('Bỏ dòng khỏi đề xuất? Công việc đã lưu không bị xóa.'))return;row.remove();form.dispatchEvent(new Event('input',{bubbles:true}));refresh();});
 };
 function addTask(focus=true){if(rows().length>=data.limits.tasks){message(['Tối đa '+data.limits.tasks+' dòng công việc.']);return null;}const fragment=template.content.cloneNode(true),row=fragment.querySelector('[data-task-row]'),index=serial++;row.querySelectorAll('[name],[id],[for]').forEach(el=>{for(const attr of ['name','id','for'])if(el.hasAttribute(attr))el.setAttribute(attr,el.getAttribute(attr).replaceAll('__INDEX__',String(index)));});set(row,'ref',freshRef());taskBox.append(fragment);wireTask(row);refresh();if(focus)field(row,'title').focus();form.dispatchEvent(new Event('input',{bubbles:true}));return row;}
 rows().forEach(wireTask);
 let linkSerial=0;
 const addLink=(kind,values={})=>{
  const box=kind==='dependencies'?depBox:slotBox;if(box.children.length>=data.limits[kind])return message(['Đã đạt giới hạn '+data.limits[kind]+' dòng.']);
  const row=document.createElement('div');row.className='editor-link-row';row.dataset.linkRow=kind;
  const fields=kind==='dependencies'?[['predecessor','Việc phải xong trước','select'],['successor','Việc chờ sau','select'],['lag_minutes','Chờ thêm (phút)','number']]:[['task_ref','Công việc','select'],['starts_at','Bắt đầu','datetime-local'],['ends_at','Kết thúc','datetime-local']];
  const idx=linkSerial++;
  fields.forEach(([name,label,type])=>{const wrap=document.createElement('div'),l=document.createElement('label'),input=document.createElement(type==='select'?'select':'input');input.dataset.field=name;input.name=`editor[${kind}][${idx}][${name}]`;input.id=`${kind}-${idx}-${name}`;input.required=true;l.htmlFor=input.id;l.textContent=label;
   if(type==='select')options(input,rows().map(rowData).filter(r=>type==='select'&&(kind==='slots'?r.task_type==='WORK':r.task_type!=='GROUP')).map(r=>({ref:r.ref,label:r.ref+' — '+r.title})),values[name]);
   else{input.type=type;input.value=values[name]??(type==='number'?'0':'');if(type==='number'){input.min=0;input.max=525600;}}
   wrap.append(l,input);row.append(wrap);
  });
  const remove=document.createElement('button');remove.type='button';remove.className='danger';remove.textContent='Bỏ dòng';remove.addEventListener('click',()=>{row.remove();form.dispatchEvent(new Event('input',{bubbles:true}));});row.append(remove);box.append(row);refresh();
 };
 (data.payload.dependencies||[]).forEach(r=>addLink('dependencies',r));(data.payload.slots||[]).forEach(r=>addLink('slots',r));
 document.querySelector('#add-plan-task').addEventListener('click',()=>addTask());document.querySelector('#add-plan-dependency').addEventListener('click',()=>addLink('dependencies'));document.querySelector('#add-plan-slot').addEventListener('click',()=>addLink('slots'));

 document.querySelector('#source-search')?.addEventListener('submit',async e=>{
  e.preventDefault();const search=e.currentTarget,button=search.querySelector('button'),status=document.querySelector('#source-search-result');button.disabled=true;search.setAttribute('aria-busy','true');status.textContent='Đang tìm công việc nguồn…';
  const url=new URL(search.action);for(const [k,v] of new FormData(search))if(v)url.searchParams.set(k,v);
  rows().map(r=>val(r,'task_id')).filter(Boolean).forEach(id=>url.searchParams.append('source_selected[]',id));
  try{const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'text/html'}});if(!response.ok)throw Error('Không thể tìm nguồn; kiểm tra phiên đăng nhập và quyền.');const doc=new DOMParser().parseFromString(await response.text(),'text/html'),node=doc.querySelector('#plan-editor-data');if(!node)throw Error('Phiên hoặc quyền đã thay đổi; tải lại trang để tiếp tục.');const incoming=JSON.parse(node.textContent);data.tasks=incoming.tasks;
   rows().forEach(sourceOptions);
   status.textContent=`Đã nhận ${incoming.tasks.length} công việc nguồn${incoming.source_limit_reached?' (còn kết quả; tìm cụ thể hơn)':''}. Bản đang soạn được giữ nguyên.`;
  }catch(error){status.textContent=error.message;}finally{button.disabled=false;search.removeAttribute('aria-busy');}
 });
 const collect=()=>({schema_version:1,tasks:rows().map(rowData),dependencies:[...depBox.children].map(row=>({predecessor:val(row,'predecessor'),successor:val(row,'successor'),lag_minutes:Number(val(row,'lag_minutes')||0)})),slots:[...slotBox.children].map(row=>({task_ref:val(row,'task_ref'),starts_at:val(row,'starts_at'),ends_at:val(row,'ends_at')}))});
 const check=payload=>{
  const fail=[],refs=new Map(payload.tasks.map(r=>[r.ref,r]));if(!payload.tasks.length)fail.push('Cần ít nhất một công việc.');if(refs.size!==payload.tasks.length)fail.push('Ref bị trùng.');
  for(const r of payload.tasks){let p=r.parent_ref,seen=new Set([r.ref]),depth=1;while(p){if(seen.has(p)||!refs.has(p)||refs.get(p).task_type!=='GROUP'){fail.push('Nhóm cha không hợp lệ hoặc có vòng tại '+r.ref);break;}seen.add(p);depth++;p=refs.get(p).parent_ref;}if(depth>data.limits.depth)fail.push('Cây tối đa 3 tầng: '+r.ref);}
  const graph=new Map();for(const edge of payload.dependencies){if(!refs.has(edge.predecessor)||!refs.has(edge.successor)||edge.predecessor===edge.successor||refs.get(edge.predecessor)?.task_type==='GROUP'||refs.get(edge.successor)?.task_type==='GROUP')fail.push('Quan hệ FS không hợp lệ.');if(!graph.has(edge.predecessor))graph.set(edge.predecessor,[]);graph.get(edge.predecessor).push(edge.successor);}
  const color=new Map();const visit=node=>{if(color.get(node)===1)throw Error('Dependency có vòng lặp.');if(color.get(node)===2)return;color.set(node,1);for(const next of graph.get(node)||[])visit(next);color.set(node,2);};try{for(const key of refs.keys())visit(key);}catch(e){fail.push(e.message);}
  for(const slot of payload.slots)if(refs.get(slot.task_ref)?.task_type!=='WORK'||!slot.starts_at||!slot.ends_at||slot.ends_at<=slot.starts_at)fail.push('Slot cần WORK và kết thúc sau bắt đầu.');return [...new Set(fail)];
 };
 const switchMode=()=>{const visual=mode.value==='editor';document.querySelector('#visual-editor').hidden=!visual;document.querySelector('#json-panel').open=!visual;form.querySelectorAll('[data-task-row] input,[data-task-row] select,[data-task-row] textarea,[data-link-row] input,[data-link-row] select').forEach(el=>{el.disabled=!visual;});if(visual)refresh();raw.required=!visual;};mode.addEventListener('change',switchMode);refresh();switchMode();
 form.addEventListener('submit',e=>{
  let payload;if(mode.value==='editor'){payload=collect();const issues=check(payload);if(issues.length){e.preventDefault();message(issues);return;}}
  else{try{payload=JSON.parse(raw.value);}catch{e.preventDefault();message(['JSON không hợp lệ.']);document.querySelector('#visual-editor').hidden=false;return;}}
  const json=JSON.stringify(payload);if(json.length>120000){e.preventDefault();message(['Nội dung vượt giới hạn 120.000 ký tự; chia nhỏ kế hoạch.']);return;}
  packed.value=json;
  // Pack only the editor controls. Keep CSRF, scope, mode, base and named submit actions intact.
  form.querySelectorAll('[name^="editor["]').forEach(el=>el.removeAttribute('name'));
 });
 // Map stable server row indices to the current editor controls before the shared error handler runs.
 const links=[...document.querySelectorAll('[data-error-key]')];links.forEach(link=>{const m=link.dataset.errorKey.match(/^editor\.(tasks|dependencies|slots)\.(\d+)\.(\w+)$/);if(!m)return;const boxes=m[1]==='tasks'?rows():[...(m[1]==='dependencies'?depBox:slotBox).children];const input=field(boxes[Number(m[2])],m[3]);if(!input)return;input.setAttribute('aria-invalid','true');const msg=document.createElement('small');msg.className='error';msg.textContent=link.textContent;input.after(msg);link.href='#'+input.id;link.addEventListener('click',e=>{e.preventDefault();input.focus();input.scrollIntoView({block:'center'});});});
})();
