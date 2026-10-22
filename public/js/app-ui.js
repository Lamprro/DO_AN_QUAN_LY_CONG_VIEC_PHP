(() => {
 'use strict';
 const toggle=document.querySelector('#nav-toggle'),aside=document.querySelector('#app-sidebar'),backdrop=document.querySelector('.nav-backdrop');
 const closeNav=()=>{document.body.classList.remove('nav-open');toggle?.setAttribute('aria-expanded','false');if(backdrop)backdrop.hidden=true;toggle?.focus();};
 toggle?.addEventListener('click',()=>{if(document.body.classList.contains('nav-open'))return closeNav();document.body.classList.add('nav-open');toggle.setAttribute('aria-expanded','true');backdrop.hidden=false;aside.querySelector('a')?.focus();});
 backdrop?.addEventListener('click',closeNav);
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('nav-open'))closeNav();if(e.key==='Tab'&&document.body.classList.contains('nav-open')){const items=[...aside.querySelectorAll('a,button')];if(e.shiftKey&&document.activeElement===items[0]){e.preventDefault();items.at(-1)?.focus();}else if(!e.shiftKey&&document.activeElement===items.at(-1)){e.preventDefault();items[0]?.focus();}}});
 const names={TODO:'Chưa bắt đầu',IN_PROGRESS:'Đang thực hiện',REVIEW:'Chờ duyệt',DONE:'Hoàn thành',CANCELLED:'Đã hủy',GROUP:'Nhóm việc',WORK:'Công việc',MILESTONE:'Mốc',low:'Thấp',medium:'Vừa',high:'Cao',urgent:'Khẩn cấp'};
 document.querySelectorAll('select[name="status"] option,select[name="priority"] option,select[name="task_type"] option').forEach(o=>{if(names[o.value]&&o.textContent.trim()===o.value)o.textContent=names[o.value];});
 const quickEditModal=document.querySelector('#quick-edit-modal');
 let quickTrigger;
 const openQuick=trigger=>{if(!quickEditModal)return;quickTrigger=trigger||document.querySelector('[data-open-quick-edit]');if(!quickEditModal.open)quickEditModal.showModal();document.body.classList.add('quick-edit-open');(quickEditModal.querySelector('[data-quick-errors]')||quickEditModal.querySelector('[name=title]'))?.focus();};
 document.querySelectorAll('[data-open-quick-edit]').forEach(btn=>btn.addEventListener('click',()=>openQuick(btn)));
 quickEditModal?.querySelectorAll('[data-close-quick-edit]').forEach(btn=>btn.addEventListener('click',()=>quickEditModal.close()));
 quickEditModal?.addEventListener('close',()=>{document.body.classList.remove('quick-edit-open');quickTrigger?.focus();});
 quickEditModal?.addEventListener('keydown',e=>{
  if(e.key!=='Tab')return;
  const items=[...quickEditModal.querySelectorAll('button:not(:disabled),input:not(:disabled):not([type="hidden"]),select:not(:disabled),textarea:not(:disabled),a[href],[tabindex="0"]')].filter(el=>el.getClientRects().length);
  const first=items[0],last=items.at(-1);
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}
 });
 if(quickEditModal?.dataset.reopen==='1')openQuick();
 const dot=name=>name.replace(/\[([^\]]+)\]/g,'.$1');
 const locate=key=>[...document.querySelectorAll('input[name],select[name],textarea[name]')].find(i=>dot(i.name)===key&&i.type!=='hidden'&&!i.disabled);
 document.querySelectorAll('[data-error-key]').forEach(link=>{
  const key=link.dataset.errorKey,target=locate(key);if(target){target.setAttribute('aria-invalid','true');const hasMessage=target.getAttribute('aria-describedby')&&document.getElementById(target.getAttribute('aria-describedby'));const msg=document.createElement('small');msg.className='error';msg.textContent=link.textContent;msg.id=(target.id||key.replaceAll('.','-'))+'-server-error';if(!hasMessage){target.setAttribute('aria-describedby',msg.id);target.after(msg);}link.href='#'+target.id;}
  link.addEventListener('click',e=>{e.preventDefault();const t=locate(key);if(t){if(t.closest('dialog')===quickEditModal&&!quickEditModal.open)openQuick();for(let p=t.parentElement;p;p=p.parentElement)if(p.tagName==='DETAILS')p.open=true;t.focus();t.scrollIntoView({block:'center',behavior:'auto'});}else document.querySelector('#error-summary')?.focus();});
 });
 if(document.querySelector('#error-summary')&&!quickEditModal?.open)document.querySelector('#error-summary').focus();
 document.querySelectorAll('input[type=password]').forEach(input=>{const button=document.createElement('button');button.type='button';button.className='secondary password-toggle';button.textContent='Hiện mật khẩu';button.setAttribute('aria-pressed','false');button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Ẩn mật khẩu':'Hiện mật khẩu';button.setAttribute('aria-pressed',String(show));});input.after(button);});
 const dirtyForms=new Set();document.querySelectorAll('[data-dirty-form]').forEach(f=>{f.addEventListener('input',()=>dirtyForms.add(f));f.addEventListener('change',()=>dirtyForms.add(f));});
 window.addEventListener('beforeunload',e=>{if(dirtyForms.size){e.preventDefault();e.returnValue='';}});
 document.addEventListener('submit',e=>{const f=e.target;if(!(f instanceof HTMLFormElement)||e.defaultPrevented)return;
  if(f.dataset.confirm&&!window.confirm(f.dataset.form==='owner-transfer'?f.dataset.confirm+' Người nhận: '+(f.querySelector('[name=member_id]')?.selectedOptions[0]?.textContent||'Chưa chọn'):f.dataset.confirm)){e.preventDefault();return;}
  if((f.method||'get').toLowerCase()==='get'){f.querySelector('[name=page]')?.remove();return;}
  if(f.dataset.submitting==='1'){e.preventDefault();return;}f.dataset.submitting='1';dirtyForms.delete(f);f.setAttribute('aria-busy','true');
  if(e.submitter?.name){const h=document.createElement('input');h.type='hidden';h.dataset.submitterCopy='1';h.name=e.submitter.name;h.value=e.submitter.value;f.append(h);}
  f.querySelectorAll('button[type=submit],button:not([type])').forEach(b=>{b.dataset.wasDisabled=String(b.disabled);b.disabled=true;b.dataset.label=b.textContent;b.textContent='Đang gửi…';});
 });
 window.addEventListener('pageshow',()=>{document.querySelectorAll('form[data-submitting]').forEach(f=>{delete f.dataset.submitting;f.removeAttribute('aria-busy');f.querySelectorAll('[data-submitter-copy]').forEach(i=>i.remove());f.querySelectorAll('button[data-label]').forEach(b=>{b.disabled=b.dataset.wasDisabled==='true';delete b.dataset.wasDisabled;b.textContent=b.dataset.label;delete b.dataset.label;});});});

 const zone=document.querySelector('#timezone'),zoneSearch=document.querySelector('#timezone-search'),zoneExample=document.querySelector('#timezone-example');
 const zoneTime=()=>{if(!zone||!zoneExample)return;try{zoneExample.textContent='Giờ hiện tại: '+new Intl.DateTimeFormat('vi-VN',{timeZone:zone.value,dateStyle:'medium',timeStyle:'short'}).format(new Date());}catch{zoneExample.textContent='Múi giờ: '+zone.value;}};
 zone?.addEventListener('change',zoneTime);zoneTime();zoneSearch?.addEventListener('input',()=>{const q=zoneSearch.value.toLowerCase();[...zone.options].forEach(o=>o.hidden=!o.text.toLowerCase().includes(q)&&!o.selected);});
 const tick=()=>document.querySelectorAll('[data-elapsed-start]').forEach(out=>{let s=Math.max(0,Math.floor((Date.now()-Date.parse(out.dataset.elapsedStart))/1000));out.textContent=String(Math.floor(s/3600)).padStart(2,'0')+':'+String(Math.floor(s/60)%60).padStart(2,'0')+':'+String(s%60).padStart(2,'0');});tick();if(document.querySelector('[data-elapsed-start]'))setInterval(tick,1000);
 document.querySelectorAll('input[type=file]').forEach(input=>{const text=document.createElement('p');text.className='muted';text.setAttribute('aria-live','polite');input.after(text);input.addEventListener('change',()=>{const file=input.files[0];text.textContent=file?file.name+' · '+(file.size/1024).toFixed(1)+' KB':'';});});
 document.querySelectorAll('form[data-form="task-status"] button[name=status]').forEach(b=>b.addEventListener('click',()=>{const reason=b.form.querySelector('[name=reason]');if(reason)reason.required=['CANCELLED','TODO'].includes(b.value)||b.dataset.reason==='1';}));
 const type=document.querySelector('select[name=task_type]');if(type){const sync=()=>{const work=type.value==='WORK',group=type.value==='GROUP';for(const name of ['estimate_minutes','assignee_id','reviewer_id']){const input=type.form.querySelector(`[name="${name}"]`);if(!input)continue;const off=name==='estimate_minutes'?!work:group;input.closest('.field')?.classList.toggle('unsupported-field',off);input.disabled=off;}};type.addEventListener('change',sync);sync();}
 document.querySelectorAll('form[data-form="task-search"],form[data-form="project-search"]').forEach(f=>{
  f.classList.add('filter-bar');const active=[...f.elements].filter(el=>el.name&&!['page','per_page','sort','direction','view'].includes(el.name)&&el.value&&!(el.name==='preset'&&el.value==='all')&&!(el.name==='archived'&&el.value==='active'));
  if(active.length){const note=document.createElement('p');note.className='filter-active';note.textContent='Bộ lọc: '+active.map(el=>(f.querySelector('label[for="'+el.id+'"]')?.textContent||el.name)+': '+(el.tagName==='SELECT'?el.selectedOptions[0]?.textContent:el.value)).join(' · ');f.prepend(note);}
  if(![...f.querySelectorAll('a')].some(a=>a.href===f.action)){const a=document.createElement('a');a.href=f.action;a.textContent='Xóa bộ lọc';a.className='button secondary';f.append(a);}
 });
  document.querySelectorAll('#theme-toggle,#theme-toggle-guest').forEach(btn=>btn.addEventListener('click',()=>window.WorkTheme?.toggle()));
  const settingsForm=document.querySelector('form[data-form="settings-update"]');
  settingsForm?.addEventListener('submit',()=>{if(settingsForm.checkValidity())window.WorkTheme?.set(settingsForm.elements.theme.value);});
  const bulk=document.querySelector('#bulk-tasks');
  if(bulk){
    const boxes=[...document.querySelectorAll('input[type="checkbox"][form="bulk-tasks"][name^="items["]')];
    const count=document.querySelector('#bulk-count'),all=document.querySelector('#bulk-select-page'),action=bulk.elements.action;
    const sync=()=>{const n=boxes.filter(b=>b.checked).length;if(count)count.textContent=`Đã chọn ${n} công việc trên trang này`;if(all){all.checked=boxes.length>0&&n===boxes.length;all.indeterminate=n>0&&n<boxes.length;}for(const name of ['priority','assignee_id']){const input=bulk.elements[name];const relevant=(name==='priority'&&action.value==='priority')||(name==='assignee_id'&&action.value==='assign');input.disabled=!relevant;input.closest('div').hidden=!relevant;}};
    boxes.forEach(b=>b.addEventListener('change',sync));all?.addEventListener('change',()=>{boxes.forEach(b=>b.checked=all.checked);sync();});action.addEventListener('change',sync);sync();
    bulk.addEventListener('submit',e=>{if(!boxes.some(b=>b.checked)){e.preventDefault();count.textContent='Hãy chọn ít nhất một công việc ở bảng bên dưới.';count.focus();}},true);
  }
  const calendarForm=document.querySelector('form[data-form="calendar-save"]');
  if(calendarForm){const type=calendarForm.elements.event_type,project=calendarForm.elements.project_id,task=calendarForm.elements.task_id;
    if(type&&project&&task&&!task.disabled){const sync=()=>{const block=type.value==='TASK_BLOCK';task.required=block;task.disabled=!block;[...task.options].forEach(o=>{const excluded=!!o.value&&o.dataset.projectId!==project.value;o.hidden=excluded;o.disabled=excluded;});if(task.selectedOptions[0]?.disabled)task.value='';if(!block)task.value='';};type.addEventListener('change',sync);project.addEventListener('change',sync);sync();}
  }
  // --- Enhanced Edit & Field Management UX ---
  // 1. Quick Presets for Estimate Minutes & Live Human-Readable Formatter
  document.querySelectorAll('input[name="estimate_minutes"]').forEach(input => {
    const field = input.closest('[data-edit-field]') || input.closest('.field') || input.parentElement;
    const label = field?.querySelector('label');
    const badge = document.createElement('span');
    badge.className = 'live-preview-badge';
    badge.hidden = true;
    label?.appendChild(badge);

    const updatePreview = () => {
      const v = parseInt(input.value, 10);
      if (!v || v <= 0) {
        badge.hidden = true;
        return;
      }
      badge.hidden = false;
      const h = Math.floor(v / 60);
      const m = v % 60;
      let text = '≈ ';
      if (h > 0) text += `${h}h `;
      if (m > 0 || h === 0) text += `${m}p`;
      badge.textContent = text;
    };
    input.addEventListener('input', updatePreview);
    updatePreview();

    const presetsContainer = field?.querySelector('.estimate-presets');
    if (presetsContainer) {
      presetsContainer.querySelectorAll('.preset-chip').forEach(chip => {
        chip.addEventListener('click', e => {
          e.preventDefault();
          if (input.disabled) return;
          const add = parseInt(chip.dataset.add, 10);
          const set = parseInt(chip.dataset.set, 10);
          if (chip.dataset.clear) {
            input.value = '';
          } else if (!isNaN(set)) {
            input.value = set;
          } else if (!isNaN(add)) {
            const current = parseInt(input.value, 10) || 0;
            input.value = current + add;
          }
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    }
  });

  // 2. Quick Presets for Deadline Picker
  document.querySelectorAll('input[name="deadline_at"]').forEach(input => {
    const field = input.closest('[data-edit-field]') || input.closest('.field') || input.parentElement;
    const presetsContainer = field?.querySelector('.deadline-presets');
    if (presetsContainer) {
      presetsContainer.querySelectorAll('.preset-chip').forEach(chip => {
        chip.addEventListener('click', e => {
          e.preventDefault();
          if (input.disabled) return;
          input.value = chip.dataset.value || '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));

          const reasonField = input.form?.querySelector('[name="deadline_reason"]');
          if (reasonField) {
            reasonField.classList.add('pulse-attention');
            reasonField.focus();
            setTimeout(() => reasonField.classList.remove('pulse-attention'), 3000);
          }
        });
      });
    }
  });

  // 3. Interactive Segmented Priority Control
  document.querySelectorAll('select[name="priority"]').forEach(select => {
    const parent = select.closest('.field') || select.parentElement;
    const selector = parent?.querySelector('.priority-selector');
    if (selector) {
      const syncButtons = () => {
        selector.querySelectorAll('.priority-btn').forEach(btn => {
          btn.classList.toggle('selected', btn.dataset.priority === select.value);
          btn.setAttribute('aria-pressed',String(btn.dataset.priority === select.value));
        });
      };
      selector.querySelectorAll('.priority-btn').forEach(btn => {
        btn.addEventListener('click', e => {
          e.preventDefault();
          select.value = btn.dataset.priority;
          syncButtons();
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
      select.addEventListener('change', syncButtons);
      syncButtons();
    }
  });

  // 4. Ctrl + Enter Keyboard Shortcut to Submit Form
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      const form = e.target.closest('form');
      if (form && ['task-create','task-edit','task-quick-edit','project-create','project-edit','role-create','role-edit'].includes(form.dataset.form) && !e.defaultPrevented) {
        const submitBtn = form.querySelector('button[type="submit"], button:not([type])');
        if (submitBtn && !submitBtn.disabled) {
          e.preventDefault();
          submitBtn.click();
        }
      }
    }
  });

  // 5. Auto-expanding Textareas
  document.querySelectorAll('textarea').forEach(ta => {
    const resize = () => {
      ta.style.height = 'auto';
      ta.style.height = Math.max(70, ta.scrollHeight + 4) + 'px';
    };
    ta.addEventListener('input', resize);
    if (ta.value) resize();
  });

  // 6. Role Permissions Select All / Deselect All / Search
  document.querySelectorAll('fieldset .permission-group-actions').forEach(bar => {
    const fieldset = bar.closest('fieldset');
    bar.querySelector('.btn-select-all')?.addEventListener('click', e => {
      e.preventDefault();
      fieldset.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(cb => {cb.checked = true;cb.dispatchEvent(new Event('change',{bubbles:true}));});
    });
    bar.querySelector('.btn-deselect-all')?.addEventListener('click', e => {
      e.preventDefault();
      fieldset.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(cb => {cb.checked = false;cb.dispatchEvent(new Event('change',{bubbles:true}));});
    });
  });

  const permSearch = document.querySelector('#permission-search');
  if (permSearch) {
    permSearch.addEventListener('input', () => {
      const q = permSearch.value.toLowerCase().trim();
      document.querySelectorAll('fieldset.card label').forEach(lbl => {
        if (!lbl.querySelector('input[name="permissions[]"]')) return;
        const text = lbl.textContent.toLowerCase();
        lbl.style.display = text.includes(q) ? 'flex' : 'none';
      });
    });
  }

  // 8. Tree WBS Expand All / Collapse All
  document.querySelector('#btn-tree-expand-all')?.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('.task-tree details').forEach(d => d.open = true);
  });
  document.querySelector('#btn-tree-collapse-all')?.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('.task-tree details').forEach(d => d.open = false);
  });
})();
