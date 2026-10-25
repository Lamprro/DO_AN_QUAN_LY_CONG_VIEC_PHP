(() => {
  'use strict';
  const dialog = document.querySelector('#task-panel');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const body = dialog.querySelector('[data-panel-body]');
  const errors = dialog.querySelector('[data-panel-error]');
  const discard = dialog.querySelector('[data-panel-discard]');
  let trigger, request, pendingAction, dirty = false, saving = false, currentUrl;
  function message(text, fields = {}) {
    errors.replaceChildren();
    const title = document.createElement('strong'); title.textContent = text; errors.append(title);
    for (const [key, values] of Object.entries(fields)) {
      const field = [...body.querySelectorAll('[name]')].find(el => el.name.replace(/\[([^\]]+)\]/g,'.$1') === key);
      for (const value of [].concat(values)) {
        const item = document.createElement(field ? 'a' : 'p'); item.textContent = value;
        if (field) { field.setAttribute('aria-invalid','true'); item.href = '#'+field.id; item.addEventListener('click', e => {e.preventDefault(); field.closest('details')?.setAttribute('open','');field.focus();}); }
        errors.append(item);
      }
    }
    errors.hidden = false; errors.focus();
  }
  function clearErrors() { errors.hidden = true; errors.replaceChildren(); body.querySelectorAll('[aria-invalid]').forEach(el=>el.removeAttribute('aria-invalid')); }
  function guard(action) {
    if(saving) return;
    if(!dirty) return action();
    pendingAction=action; discard.hidden=false; discard.querySelector('button').focus();
  }
  function close() { request?.abort(); dialog.close(); }
  function mount(html) {
    const parsed = new DOMParser().parseFromString(html,'text/html');
    const content = parsed.querySelector('[data-panel-content], [data-page="task-conflict"]');
    if(!content) throw new Error('unexpected-html');
    body.replaceChildren(document.importNode(content,true));
    const heading = body.querySelector('h2,h1'); if(heading) heading.id='task-panel-title';
    clearErrors(); window.WorkUI?.mount(body);
    (body.querySelector('[autofocus]') || body.querySelector('input:not([type="hidden"]),select,textarea') || dialog.querySelector('[data-panel-close]')).focus();
  }
  async function open(url, source, field) {
    const target=new URL(url,location.href); if(target.origin!==location.origin)return;
    trigger=source || trigger; currentUrl=target.href;target.searchParams.set('panel','1');request?.abort();request=new AbortController();dirty=false;discard.hidden=true;clearErrors();
    body.replaceChildren();const loading=document.createElement('p');loading.id='task-panel-title';loading.textContent='Đang mở công việc…';body.append(loading);
    if(!dialog.open)dialog.showModal();document.body.classList.add('quick-edit-open');dialog.setAttribute('aria-busy','true');
    try {
      const response=await fetch(target,{signal:request.signal,headers:{Accept:'text/html'},cache:'no-store'});
      if(response.redirected && new URL(response.url).pathname.endsWith('/login'))throw new Error('session');
      if(!response.ok)throw new Error(String(response.status));
      mount(await response.text());
      if(field)body.querySelector('[name="'+CSS.escape(field)+'"]')?.focus();
    } catch(error) {
      if(error.name==='AbortError')return;
      message(error.message==='session'?'Phiên đăng nhập đã hết. Đóng cửa sổ và đăng nhập lại.':error.message==='403'?'Bạn không còn quyền thao tác công việc này.':error.message==='404'?'Không tìm thấy công việc trong phạm vi này.':'Không tải được nội dung. Kiểm tra kết nối rồi thử lại.');
      const retry=document.createElement('button');retry.type='button';retry.textContent='Thử lại';retry.addEventListener('click',()=>open(currentUrl,trigger));errors.append(retry);
    } finally {dialog.removeAttribute('aria-busy');}
  }
  async function refreshPage() {
    if(window.WorkUI?.hasDraft())throw new Error('background-draft');
    const main=document.querySelector('#main-content');
    const scroll={x:scrollX,y:scrollY};
    const selected=[...main.querySelectorAll('input[form="bulk-tasks"]:checked')].map(x=>x.name);
    const expanded=[...main.querySelectorAll('details')].map(x=>x.open);
    const boardScroll=main.querySelector('.board-scroll')?.scrollLeft;
    const response=await fetch(location.href,{headers:{Accept:'text/html'},cache:'no-store'});
    if(!response.ok || response.redirected)throw new Error('refresh');
    const parsed=new DOMParser().parseFromString(await response.text(),'text/html'); const next=parsed.querySelector('#main-content');if(!next)throw new Error('refresh');
    main.replaceChildren(...[...next.childNodes].map(n=>document.importNode(n,true)));
    main.querySelectorAll('input[form="bulk-tasks"]').forEach(el=>el.checked=selected.includes(el.name));
    main.querySelectorAll('details').forEach((el,i)=>{if(expanded[i]!==undefined)el.open=expanded[i];});
    window.WorkUI?.mount(main);
    if(boardScroll!==undefined&&main.querySelector('.board-scroll'))main.querySelector('.board-scroll').scrollLeft=boardScroll;
    const count=parsed.querySelector('[data-unread-count]');if(count&&document.querySelector('[data-unread-count]'))document.querySelector('[data-unread-count]').textContent=count.textContent;
    window.scrollTo(scroll.x,scroll.y);
  }
  document.addEventListener('click',e=>{
    const link=e.target.closest('[data-task-panel]');if(!link||e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;
    const url=link.getAttribute('href')||link.dataset.panelUrl;if(!url)return;e.preventDefault();guard(()=>open(url,link,link.dataset.panelField));
  });
  dialog.addEventListener('click',e=>{
    if(e.target.closest('[data-panel-close]'))guard(close);
    if(e.target.closest('[data-panel-keep]')){discard.hidden=true;pendingAction=null;body.querySelector('input:not([type="hidden"]),textarea,select')?.focus();}
    if(e.target.closest('[data-panel-discard-confirm]')){dirty=false;discard.hidden=true;const action=pendingAction;pendingAction=null;action?.();}
    if(e.target===dialog){const rect=dialog.getBoundingClientRect();if(e.clientX<rect.left||e.clientX>rect.right||e.clientY<rect.top||e.clientY>rect.bottom)guard(close);}
    const status=e.target.closest('button[name="status"]');if(status){const reason=status.form?.elements.reason;if(reason)reason.required=status.dataset.reason==='1';}
  });
  dialog.addEventListener('cancel',e=>{e.preventDefault();guard(close);});
  dialog.addEventListener('close',()=>{document.body.classList.remove('quick-edit-open');dirty=false;pendingAction=null;if(trigger?.isConnected)trigger.focus();else document.querySelector('#main-content')?.focus();});
  for(const event of ['input','change'])dialog.addEventListener(event,e=>{if(e.target.closest('form'))dirty=true;});
  window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
  dialog.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){const form=e.target.closest('form');if(form){e.preventDefault();form.requestSubmit(form.querySelector('button[type="submit"]:not(:disabled)'));}}});
  dialog.addEventListener('submit',async e=>{
    const form=e.target;if(!(form instanceof HTMLFormElement))return;e.preventDefault();e.stopPropagation();if(saving)return;
    clearErrors();const data=new FormData(form);if(e.submitter?.name)data.append(e.submitter.name,e.submitter.value);
    saving=true;dialog.setAttribute('aria-busy','true');const buttons=[...dialog.querySelectorAll('button')];const disabled=buttons.map(b=>b.disabled);buttons.forEach(b=>b.disabled=true);
    try {
      const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:data});
      if(response.redirected)throw new Error('session');
      const result=await response.json();
      if(response.ok&&result.success){
        dirty=false;
        try {await refreshPage();close();window.showToast?.(result.message||'Đã lưu thay đổi.');}
        catch {message('Đã lưu trên máy chủ, nhưng chưa tải lại được danh sách. Đóng cửa sổ và tải lại trang để xem kết quả.');form.querySelectorAll('input,textarea,select').forEach(el=>el.disabled=true);form.dataset.saved='1';}
      } else if(response.status===409&&result.html){mount(result.html);dirty=true;window.showToast?.('Có thay đổi từ phiên khác. Hãy đối chiếu trước khi lưu.','info');}
      else message(response.status===419?'Phiên làm việc hết hạn. Bản nhập vẫn còn ở đây; đăng nhập lại trong tab khác rồi mở lại công việc.':response.status===403?'Bạn không còn quyền lưu thay đổi này.':result.message||'Chưa lưu được thay đổi.', result.errors||{});
    } catch(error){message(error.message==='session'?'Phiên đăng nhập đã hết. Bản nhập được giữ trong cửa sổ này.':'Chưa nhận được kết quả lưu. Kiểm tra kết nối; mở dữ liệu mới trước khi gửi lại để tránh tạo trùng.');}
    finally {saving=false;dialog.removeAttribute('aria-busy');buttons.forEach((b,i)=>{if(b.isConnected)b.disabled=disabled[i]||(!!form.dataset.saved&&b.type==='submit');});}
  });
})();
