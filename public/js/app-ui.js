(() => {
 'use strict';
 const toggle=document.querySelector('#nav-toggle'),aside=document.querySelector('#app-sidebar'),backdrop=document.querySelector('.nav-backdrop');
 const closeNav=()=>{document.body.classList.remove('nav-open');toggle?.setAttribute('aria-expanded','false');if(backdrop)backdrop.hidden=true;toggle?.focus();};
 toggle?.addEventListener('click',()=>{if(document.body.classList.contains('nav-open'))return closeNav();document.body.classList.add('nav-open');toggle.setAttribute('aria-expanded','true');backdrop.hidden=false;(aside.querySelector('[data-close-nav]')||aside.querySelector('a'))?.focus();});
 backdrop?.addEventListener('click',closeNav);
 document.querySelector('[data-close-nav]')?.addEventListener('click',closeNav);
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('nav-open'))closeNav();if(e.key==='Tab'&&document.body.classList.contains('nav-open')){const items=[...aside.querySelectorAll('a,button')];if(e.shiftKey&&document.activeElement===items[0]){e.preventDefault();items.at(-1)?.focus();}else if(!e.shiftKey&&document.activeElement===items.at(-1)){e.preventDefault();items[0]?.focus();}}});
 const names={TODO:'Chưa bắt đầu',IN_PROGRESS:'Đang thực hiện',REVIEW:'Chờ duyệt',DONE:'Hoàn thành',CANCELLED:'Đã hủy',GROUP:'Nhóm việc',WORK:'Công việc',MILESTONE:'Mốc',low:'Thấp',medium:'Vừa',high:'Cao',urgent:'Khẩn cấp'};
 document.querySelectorAll('select[name="status"] option,select[name="priority"] option,select[name="task_type"] option').forEach(o=>{if(names[o.value]&&o.textContent.trim()===o.value)o.textContent=names[o.value];});
 const dot=name=>name.replace(/\[([^\]]+)\]/g,'.$1');
 const locate=key=>[...document.querySelectorAll('input[name],select[name],textarea[name]')].find(i=>dot(i.name)===key&&i.type!=='hidden'&&!i.disabled);
 document.querySelectorAll('[data-error-key]').forEach(link=>{
  const key=link.dataset.errorKey,target=locate(key);if(target){target.setAttribute('aria-invalid','true');const hasMessage=target.getAttribute('aria-describedby')&&document.getElementById(target.getAttribute('aria-describedby'));const msg=document.createElement('small');msg.className='error';msg.textContent=link.textContent;msg.id=(target.id||key.replaceAll('.','-'))+'-server-error';if(!hasMessage){target.setAttribute('aria-describedby',msg.id);target.after(msg);}link.href='#'+target.id;}
  link.addEventListener('click',e=>{e.preventDefault();const t=locate(key);if(t){for(let p=t.parentElement;p;p=p.parentElement)if(p.tagName==='DETAILS')p.open=true;t.focus();t.scrollIntoView({block:'center',behavior:'auto'});}else document.querySelector('#error-summary')?.focus();});
 });
 if(document.querySelector('#error-summary'))document.querySelector('#error-summary').focus();
 document.querySelectorAll('input[type=password]').forEach(input=>{const button=document.createElement('button');button.type='button';button.className='secondary password-toggle';button.textContent='Hiện mật khẩu';button.setAttribute('aria-pressed','false');button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Ẩn mật khẩu':'Hiện mật khẩu';button.setAttribute('aria-pressed',String(show));});input.after(button);});
 const dirtyForms=new Set();for(const event of ['input','change'])document.addEventListener(event,e=>{const form=e.target.closest('[data-dirty-form]');if(form&&!form.closest('#page-editor'))dirtyForms.add(form);});
 window.addEventListener('beforeunload',e=>{if(window.self===window.top&&dirtyForms.size){e.preventDefault();e.returnValue='';}});
 document.addEventListener('submit',e=>{const f=e.target;if(!(f instanceof HTMLFormElement)||e.defaultPrevented||f.closest('#task-panel'))return;
  if(f.dataset.confirm&&!window.confirm(f.dataset.form==='owner-transfer'?f.dataset.confirm+' Người nhận: '+(f.querySelector('[name=member_id]')?.selectedOptions[0]?.textContent||'Chưa chọn'):f.dataset.confirm)){e.preventDefault();return;}
  if((f.method||'get').toLowerCase()==='get'){f.querySelector('[name=page]')?.remove();return;}
  if(f.dataset.submitting==='1'){e.preventDefault();return;}f.dataset.submitting='1';dirtyForms.delete(f);f.setAttribute('aria-busy','true');
  if(e.submitter?.name){const h=document.createElement('input');h.type='hidden';h.dataset.submitterCopy='1';h.name=e.submitter.name;h.value=e.submitter.value;f.append(h);}
  f.querySelectorAll('button[type=submit],button:not([type])').forEach(b=>{b.dataset.wasDisabled=String(b.disabled);b.disabled=true;b.dataset.label=b.textContent;b.textContent='Đang gửi…';});
 });
 window.addEventListener('pageshow',()=>{document.querySelectorAll('form[data-submitting]').forEach(f=>{delete f.dataset.submitting;f.removeAttribute('aria-busy');f.querySelectorAll('[data-submitter-copy]').forEach(i=>i.remove());f.querySelectorAll('button[data-label]').forEach(b=>{b.disabled=b.dataset.wasDisabled==='true';delete b.dataset.wasDisabled;b.textContent=b.dataset.label;delete b.dataset.label;});});});

 function mountWorkPage(root=document){
 const zone=root.querySelector('#timezone'),zoneSearch=root.querySelector('#timezone-search'),zoneExample=root.querySelector('#timezone-example');
 const zoneTime=()=>{if(!zone||!zoneExample)return;try{zoneExample.textContent='Giờ hiện tại: '+new Intl.DateTimeFormat('vi-VN',{timeZone:zone.value,dateStyle:'medium',timeStyle:'short'}).format(new Date());}catch{zoneExample.textContent='Múi giờ: '+zone.value;}};
 zone?.addEventListener('change',zoneTime);zoneTime();zoneSearch?.addEventListener('input',()=>{const q=zoneSearch.value.toLowerCase();[...zone.options].forEach(o=>o.hidden=!o.text.toLowerCase().includes(q)&&!o.selected);});
 root.querySelectorAll('input[type=file]:not(.task-file-input)').forEach(input=>{const text=document.createElement('p');text.className='muted';text.setAttribute('aria-live','polite');input.after(text);input.addEventListener('change',()=>{const file=input.files[0];text.textContent=file?file.name+' · '+(file.size/1024).toFixed(1)+' KB':'';});});
 root.querySelectorAll('form[data-form="task-status"] button[name=status]').forEach(b=>b.addEventListener('click',()=>{const reason=b.form.querySelector('[name=reason]');if(reason)reason.required=['CANCELLED','TODO'].includes(b.value)||b.dataset.reason==='1';}));
 const type=root.querySelector('select[name=task_type]');if(type){const sync=()=>{const work=type.value==='WORK',group=type.value==='GROUP';for(const name of ['estimate_minutes','assignee_id','reviewer_id']){const input=type.form.querySelector(`[name="${name}"]`);if(!input)continue;const off=name==='estimate_minutes'?!work:group;input.closest('.field')?.classList.toggle('unsupported-field',off);input.disabled=off;}};type.addEventListener('change',sync);sync();}
 root.querySelectorAll('form[data-form="task-search"],form[data-form="project-search"]').forEach(f=>{
  f.classList.add('filter-bar');const active=[...f.elements].filter(el=>el.name&&!['page','per_page','sort','direction','view'].includes(el.name)&&el.value&&!(el.name==='preset'&&el.value==='all')&&!(el.name==='archived'&&el.value==='active'));
  if(active.length){const note=document.createElement('p');note.className='filter-active';note.textContent='Bộ lọc: '+active.map(el=>(el.dataset.filterLabel||f.querySelector('label[for="'+el.id+'"]')?.textContent||el.name)+': '+(el.dataset.filterValue||(el.tagName==='SELECT'?el.selectedOptions[0]?.textContent:el.value))).join(' · ');f.prepend(note);}
  if(!f.querySelector('[data-filter-reset]')&&![...f.querySelectorAll('a')].some(a=>a.href===f.action)){const a=document.createElement('a');a.href=f.action;a.textContent='Xóa bộ lọc';a.className='button secondary';f.append(a);}
 });
  const settingsForm=root.querySelector('form[data-form="settings-update"]');
  settingsForm?.addEventListener('submit',()=>{if(settingsForm.checkValidity())window.WorkTheme?.set(settingsForm.elements.theme.value);});
  const bulk=root.querySelector('#bulk-tasks');
  if(bulk){
    const boxes=[...root.querySelectorAll('input[type="checkbox"][form="bulk-tasks"][name^="items["]')];
    const count=root.querySelector('#bulk-count'),all=root.querySelector('#bulk-select-page'),action=bulk.elements.action;
    // The bar appears only while rows are ticked (or after a server error on the bulk form).
    const sync=()=>{const n=boxes.filter(b=>b.checked).length;if(count)count.textContent=`Đã chọn ${n} việc`;bulk.hidden=n===0&&bulk.dataset.hasErrors!=='1';if(all){all.disabled=boxes.length===0;all.checked=boxes.length>0&&n===boxes.length;all.indeterminate=n>0&&n<boxes.length;}for(const name of ['priority','assignee_id']){const input=bulk.elements[name];if(!input)continue;const relevant=(name==='priority'&&action.value==='priority')||(name==='assignee_id'&&action.value==='assign');input.disabled=!relevant;input.closest('div').hidden=!relevant;}};
    boxes.forEach(b=>b.addEventListener('change',sync));all?.addEventListener('change',()=>{boxes.forEach(b=>b.checked=all.checked);sync();});action.addEventListener('change',sync);
    bulk.querySelector('[data-bulk-clear]')?.addEventListener('click',()=>{boxes.forEach(b=>b.checked=false);delete bulk.dataset.hasErrors;sync();});sync();
    bulk.addEventListener('submit',e=>{if(!boxes.some(b=>b.checked)){e.preventDefault();count.textContent='Hãy chọn ít nhất một công việc ở bảng bên dưới.';count.focus();}},true);
  }
  const calendarForm=root.querySelector('form[data-form="calendar-save"]');
  if(calendarForm){const type=calendarForm.elements.event_type,project=calendarForm.elements.project_id,task=calendarForm.elements.task_id;
    if(type&&project&&task&&!task.disabled){const sync=()=>{const block=type.value==='TASK_BLOCK';task.required=block;task.disabled=!block;[...task.options].forEach(o=>{const excluded=!!o.value&&o.dataset.projectId!==project.value;o.hidden=excluded;o.disabled=excluded;});if(task.selectedOptions[0]?.disabled)task.value='';if(!block)task.value='';};type.addEventListener('change',sync);project.addEventListener('change',sync);sync();}
  }
  // --- Enhanced Edit & Field Management UX ---
  // 1. Quick Presets for Estimate Minutes & Live Human-Readable Formatter
  root.querySelectorAll('input[name="estimate_minutes"]').forEach(input => {
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
  root.querySelectorAll('input[name="deadline_at"]').forEach(input => {
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
  root.querySelectorAll('select[name="priority"]').forEach(select => {
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

  // 5. Auto-expanding Textareas
  root.querySelectorAll('textarea').forEach(ta => {
    const resize = () => {
      ta.style.height = 'auto';
      ta.style.height = Math.max(70, ta.scrollHeight + 4) + 'px';
    };
    ta.addEventListener('input', resize);
    if (ta.value) resize();
  });

  // 6. Role Permissions Select All / Deselect All / Search
  root.querySelectorAll('fieldset .permission-group-actions').forEach(bar => {
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

  const permSearch = root.querySelector('#permission-search');
  if (permSearch) {
    permSearch.addEventListener('input', () => {
      const q = permSearch.value.toLowerCase().trim();
      root.querySelectorAll('fieldset.card label').forEach(lbl => {
        if (!lbl.querySelector('input[name="permissions[]"]')) return;
        const text = lbl.textContent.toLowerCase();
        lbl.style.display = text.includes(q) ? 'flex' : 'none';
      });
    });
  }

  // 8. Tree WBS Expand All / Collapse All
  root.querySelector('#btn-tree-expand-all')?.addEventListener('click', e => {
    e.preventDefault();
    root.querySelectorAll('.task-tree details').forEach(d => d.open = true);
  });
  root.querySelector('#btn-tree-collapse-all')?.addEventListener('click', e => {
    e.preventDefault();
    root.querySelectorAll('.task-tree details').forEach(d => d.open = false);
  });

 }
 window.WorkUI={mount:mountWorkPage,hasDraft:()=>[...dirtyForms].some(f=>f.isConnected)};mountWorkPage();
  document.querySelectorAll('#theme-toggle,#theme-toggle-guest').forEach(btn=>btn.addEventListener('click',()=>window.WorkTheme?.toggle()));
 const tick=()=>document.querySelectorAll('[data-elapsed-start]').forEach(out=>{let s=Math.max(0,Math.floor((Date.now()-Date.parse(out.dataset.elapsedStart))/1000));out.textContent=String(Math.floor(s/3600)).padStart(2,'0')+':'+String(Math.floor(s/60)%60).padStart(2,'0')+':'+String(s%60).padStart(2,'0');});tick();if(document.querySelector('[data-elapsed-start]'))setInterval(tick,1000);
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

  // 5. Jira Global Shortcuts: c for Quick Create, / or Ctrl+K for Global Search
  document.addEventListener('keydown', e => {
    const tag = (e.target?.tagName || '').toLowerCase();
    const isEditable = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target?.isContentEditable;

    if ((e.key === 'c' || e.key === 'C') && !isEditable && !e.ctrlKey && !e.metaKey && !e.altKey) {
      const createBtn = document.querySelector('.jira-quick-create-btn');
      if (createBtn) {
        e.preventDefault();
        createBtn.click();
      }
    }

    if (((e.key === '/' && !isEditable) || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) && !e.defaultPrevented) {
      const searchInput = document.querySelector('#global-query') || document.querySelector('#q');
      if (searchInput) {
        e.preventDefault();
        searchInput.focus();
        searchInput.select?.();
      }
    }
  });

  // 9. Modern Toast Notification System
  window.showToast = (message, type = 'success') => {
    let container = document.querySelector('#toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'true');
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `app-toast toast-${type}`;
    const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : (type === 'warning' ? '⚠️' : 'ℹ️'));
    const iconEl = document.createElement('span');
    iconEl.className = 'toast-icon';
    iconEl.textContent = icon;
    const messageEl = document.createElement('span');
    messageEl.className = 'toast-message';
    messageEl.textContent = String(message);
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Đóng thông báo');
    closeBtn.innerHTML = '&times;';
    closeBtn.onclick = () => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 250);
    };
    toast.append(iconEl, messageEl, closeBtn);
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 350);
    }, 4500);
  };

  // Trigger flash messages & validation error summaries as toasts on load
  const flashItems = document.querySelectorAll('[data-flash-message]');
  if (flashItems.length > 0) {
    flashItems.forEach((el, index) => {
      const msg = el.dataset.flashMessage;
      const typ = el.dataset.flashType || 'success';
      if (msg) {
        setTimeout(() => {
          if (window.showToast) window.showToast(msg, typ);
        }, 150 + index * 200);
      }
    });

    // Auto-focus first invalid input for convenient correction
    setTimeout(() => {
      const firstInvalid = document.querySelector('input[aria-invalid="true"], select[aria-invalid="true"], textarea[aria-invalid="true"]');
      if (firstInvalid) firstInvalid.focus();
    }, 300);
  }

  // 10. Jira Task Hierarchy Toggle (Groups & Subtasks)
  document.addEventListener('click', e => {
    // 10a. Toggle Subtasks of a Task
    const subtaskBtn = e.target.closest('.btn-toggle-subtasks');
    if (subtaskBtn) {
      e.preventDefault();
      e.stopPropagation();
      const taskId = subtaskBtn.getAttribute('data-toggle-task');
      if (!taskId) return;
      const isCurrentlyCollapsed = subtaskBtn.getAttribute('data-collapsed') === 'true' || subtaskBtn.getAttribute('aria-expanded') === 'false';
      const rows = document.querySelectorAll('tr[data-subtask-owner="' + taskId + '"]');

      if (isCurrentlyCollapsed) {
        subtaskBtn.setAttribute('data-collapsed', 'false');
        subtaskBtn.setAttribute('aria-expanded', 'true');
        subtaskBtn.textContent = '▼';
        rows.forEach(r => r.style.display = '');
      } else {
        subtaskBtn.setAttribute('data-collapsed', 'true');
        subtaskBtn.setAttribute('aria-expanded', 'false');
        subtaskBtn.textContent = '▶';
        rows.forEach(r => r.style.display = 'none');
      }
      return;
    }

    // 10b. Toggle Group (Epic)
    const groupBtn = e.target.closest('.btn-toggle-group');
    if (groupBtn) {
      e.preventDefault();
      e.stopPropagation();
      const groupId = groupBtn.getAttribute('data-toggle-group');
      if (!groupId) return;
      const isGroupCollapsed = groupBtn.getAttribute('data-collapsed') === 'true' || groupBtn.getAttribute('aria-expanded') === 'false';
      const groupRows = document.querySelectorAll('tr[data-group-owner="' + groupId + '"]');

      if (isGroupCollapsed) {
        groupBtn.setAttribute('data-collapsed', 'false');
        groupBtn.setAttribute('aria-expanded', 'true');
        groupBtn.textContent = '▼';
        groupRows.forEach(r => {
          const subtaskOwner = r.getAttribute('data-subtask-owner');
          if (!subtaskOwner) {
            r.style.display = '';
          } else {
            const parentBtn = document.querySelector('.btn-toggle-subtasks[data-toggle-task="' + subtaskOwner + '"]');
            if (!parentBtn || parentBtn.getAttribute('data-collapsed') !== 'true') {
              r.style.display = '';
            } else {
              r.style.display = 'none';
            }
          }
        });
      } else {
        groupBtn.setAttribute('data-collapsed', 'true');
        groupBtn.setAttribute('aria-expanded', 'false');
        groupBtn.textContent = '▶';
        groupRows.forEach(r => r.style.display = 'none');
      }
      return;
    }

    // 10c. Expand All / Collapse All
    const expandAll = e.target.closest('#btn-expand-all');
    if (expandAll) {
      e.preventDefault();
      document.querySelectorAll('tr[data-group-owner], tr[data-subtask-owner]').forEach(r => r.style.display = '');
      document.querySelectorAll('.btn-toggle-group, .btn-toggle-subtasks').forEach(b => {
        b.setAttribute('data-collapsed', 'false');
        b.setAttribute('aria-expanded', 'true');
        b.textContent = '▼';
      });
      return;
    }

    const collapseAll = e.target.closest('#btn-collapse-all');
    if (collapseAll) {
      e.preventDefault();
      document.querySelectorAll('tr[data-group-owner], tr[data-subtask-owner]').forEach(r => r.style.display = 'none');
      document.querySelectorAll('.btn-toggle-group, .btn-toggle-subtasks').forEach(b => {
        b.setAttribute('data-collapsed', 'true');
        b.setAttribute('aria-expanded', 'false');
        b.textContent = '▶';
      });
      return;
    }
  });

  // 11. Global Inline Quick Edit System for Task Tables (Status, Priority, Assignee, Deadline)
  let popoverEl = document.getElementById('inline-quick-popover');
  if (!popoverEl) {
    popoverEl = document.createElement('div');
    popoverEl.id = 'inline-quick-popover';
    popoverEl.hidden = true;
    document.body.appendChild(popoverEl);
  }

  const closeQuickPopover = () => {
    if (popoverEl) {
      popoverEl.hidden = true;
      popoverEl.innerHTML = '';
      delete popoverEl.dataset.activeTaskId;
      delete popoverEl.dataset.activeField;
    }
  };

  const positionPopover = (trigger) => {
    if (!popoverEl || popoverEl.hidden) return;
    const rect = trigger.getBoundingClientRect();
    const popoverWidth = Math.min(320, window.innerWidth - 32);
    popoverEl.style.width = `${popoverWidth}px`;

    const popoverHeight = popoverEl.offsetHeight || 260;

    let top = rect.bottom + window.scrollY + 6;
    let left = rect.left + window.scrollX;

    if (rect.left + popoverWidth > window.innerWidth - 16) {
      left = window.innerWidth - popoverWidth - 16 + window.scrollX;
    }
    if (left < window.scrollX + 16) {
      left = window.scrollX + 16;
    }

    if (rect.bottom + popoverHeight + 10 > window.innerHeight && rect.top > popoverHeight + 10) {
      top = rect.top + window.scrollY - popoverHeight - 6;
    }

    popoverEl.style.top = `${top}px`;
    popoverEl.style.left = `${left}px`;
  };

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && popoverEl && !popoverEl.hidden) {
      closeQuickPopover();
    }
  });

  window.addEventListener('resize', () => {
    if (popoverEl && !popoverEl.hidden) {
      closeQuickPopover();
    }
  });

  const sendInlinePatch = async (url, data) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(url, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(data),
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
      const errorMsg = Object.values(json.errors || {}).flat().join('; ') || json.message || 'Lỗi khi cập nhật dữ liệu.';
      throw new Error(errorMsg);
    }
    return json;
  };

  const updateGroupProgress = (groupId) => {
    if (!groupId) return;
    const childRows = [...document.querySelectorAll(`tr[data-group-owner="${groupId}"]`)].filter(r => !r.classList.contains('jira-epic-cluster-header'));
    if (!childRows.length) return;

    const total = childRows.length;
    const done = childRows.filter(r => r.dataset.status === 'DONE').length;
    const percent = Math.round((done / total) * 100);

    const wrapper = document.querySelector(`.group-progress-wrapper[data-group-progress="${groupId}"]`);
    if (wrapper) {
      const doneEl = wrapper.querySelector('.done-count');
      const totalEl = wrapper.querySelector('.total-count');
      const percentEl = wrapper.querySelector('.percent-val');
      const barFill = wrapper.querySelector('.progress-bar-fill');
      if (doneEl) doneEl.textContent = done;
      if (totalEl) totalEl.textContent = total;
      if (percentEl) percentEl.textContent = percent;
      if (barFill) barFill.style.width = `${percent}%`;
    }
  };

  const getWorkspaceMembers = () => {
    const script = document.getElementById('workspace-members-data');
    if (script && script.textContent.trim()) {
      try {
        return JSON.parse(script.textContent);
      } catch (e) {
        console.warn('Cannot parse workspace-members-data', e);
      }
    }
    return [];
  };

  const getDeadlinePresets = () => {
    const script = document.getElementById('deadline-presets-data');
    if (script && script.textContent.trim()) {
      try {
        return JSON.parse(script.textContent);
      } catch (e) {
        console.warn('Cannot parse deadline-presets-data', e);
      }
    }
    return [];
  };

  document.addEventListener('click', async e => {
    const trigger = e.target.closest('.inline-edit-trigger');
    if (!trigger) {
      if (popoverEl && !popoverEl.hidden && !popoverEl.contains(e.target)) {
        closeQuickPopover();
      }
      return;
    }

    e.preventDefault();
    e.stopPropagation();

    const row = trigger.closest('tr[data-task-id]') || trigger.closest('[data-task-id]');
    if (!row) return;

    const taskId = row.dataset.taskId;
    const field = trigger.dataset.inlineField;

    // Toggle close if clicking the same trigger again
    if (!popoverEl.hidden && popoverEl.dataset.activeTaskId === taskId && popoverEl.dataset.activeField === field) {
      closeQuickPopover();
      return;
    }

    popoverEl.dataset.activeTaskId = taskId;
    popoverEl.dataset.activeField = field;
    popoverEl.innerHTML = '';
    popoverEl.hidden = false;

    const revision = row.dataset.revision || row.querySelector('input[name^="items["]')?.value || '1';
    const currentStatus = row.dataset.status || 'TODO';
    const currentPriority = row.dataset.priority || 'medium';
    const currentAssigneeId = row.dataset.assigneeId || '';
    const currentDeadline = row.dataset.deadline || '';
    const statusUrl = row.dataset.statusUrl;
    const updateUrl = row.dataset.updateUrl;

    // Render Field UI
    if (field === 'priority') {
      const options = [
        { key: 'low', label: 'Thấp', dot: 'priority-low' },
        { key: 'medium', label: 'Vừa', dot: 'priority-medium' },
        { key: 'high', label: 'Cao', dot: 'priority-high' },
        { key: 'urgent', label: 'Khẩn', dot: 'priority-urgent' }
      ];

      popoverEl.innerHTML = `
        <div class="inline-popover-header">
          <span class="inline-popover-title">Đổi độ ưu tiên</span>
          <button type="button" class="inline-popover-close" title="Đóng">✕</button>
        </div>
        <div class="inline-popover-list">
          ${options.map(opt => `
            <button type="button" class="inline-popover-option ${opt.key === currentPriority ? 'is-active' : ''}" data-priority-val="${opt.key}">
              <span>
                <span class="inline-priority-indicator ${opt.dot}">●</span>
                ${opt.label}
              </span>
              ${opt.key === currentPriority ? '<span>✓</span>' : ''}
            </button>
          `).join('')}
        </div>
      `;

      positionPopover(trigger);
      popoverEl.querySelector('.inline-popover-close')?.addEventListener('click', closeQuickPopover);

      popoverEl.querySelectorAll('[data-priority-val]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const newPriority = btn.dataset.priorityVal;
          if (newPriority === currentPriority) {
            closeQuickPopover();
            return;
          }

          btn.disabled = true;
          btn.style.opacity = '0.5';

          try {
            const res = await sendInlinePatch(updateUrl, {
              edit_surface: 'inline',
              expected_revision: revision,
              priority: newPriority,
            });

            row.dataset.priority = res.task.priority;
            row.dataset.revision = res.task.revision;
            const cb = row.querySelector('input[name^="items["]');
            if (cb) cb.value = res.task.revision;

            const ind = trigger.querySelector('.inline-priority-indicator');
            if (ind) {
              ind.className = `inline-priority-indicator priority-${res.task.priority.toLowerCase()}`;
            }
            const txt = trigger.querySelector('.inline-priority-text');
            if (txt) txt.textContent = res.task.priority_label;

            closeQuickPopover();
            if (window.showToast) window.showToast(`Đã đổi độ ưu tiên: ${res.task.priority_label}`, 'success');
          } catch (err) {
            btn.disabled = false;
            btn.style.opacity = '';
            if (window.showToast) window.showToast(err.message, 'error');
          }
        });
      });
    } else if (field === 'status') {
      const statuses = [
        { key: 'TODO', label: 'Chưa bắt đầu', slug: 'todo' },
        { key: 'IN_PROGRESS', label: 'Đang thực hiện', slug: 'in-progress' },
        { key: 'REVIEW', label: 'Chờ duyệt', slug: 'review' },
        { key: 'DONE', label: 'Hoàn thành', slug: 'done' },
        { key: 'CANCELLED', label: 'Đã hủy', slug: 'cancelled' }
      ];

      const workflowGraph = {
        'TODO': ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS': ['REVIEW', 'CANCELLED'],
        'REVIEW': ['DONE', 'IN_PROGRESS', 'CANCELLED'],
        'DONE': ['TODO'],
        'CANCELLED': ['TODO']
      };

      const validTransitions = workflowGraph[currentStatus] || [];

      popoverEl.innerHTML = `
        <div class="inline-popover-header">
          <span class="inline-popover-title">Đổi trạng thái</span>
          <button type="button" class="inline-popover-close" title="Đóng">✕</button>
        </div>
        <div class="inline-popover-list">
          ${statuses.map(st => {
            const isCurrent = st.key === currentStatus;
            const isValid = validTransitions.includes(st.key);
            let actionHint = '';
            let disabledAttr = '';
            let optionTitle = '';

            if (isCurrent) {
              actionHint = '<span style="font-size: 11px; color: var(--primary); font-weight: 700;">✓ Hiện tại</span>';
            } else if (isValid) {
              actionHint = '<span style="font-size: 11px; color: #10b981; font-weight: 600;">Khả dụng →</span>';
            } else {
              actionHint = '<span style="font-size: 10px; color: var(--text-muted); opacity: 0.65;">(Khác luồng)</span>';
              disabledAttr = 'disabled';
              optionTitle = 'Cần thực hiện tuần tự theo luồng công việc';
            }

            return `
              <button type="button" class="inline-popover-option ${isCurrent ? 'is-active' : ''}" data-status-val="${st.key}" ${disabledAttr} title="${optionTitle}">
                <span class="status-pill status-${st.slug}">${st.label}</span>
                ${actionHint}
              </button>
            `;
          }).join('')}
        </div>
        <div id="inline-status-reason-box" style="display: none; margin-top: 8px; border-top: 1px dashed var(--border); padding-top: 8px;">
          <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">
            Lý do thay đổi (bắt buộc khi hủy, mở lại hoặc yêu cầu sửa):
          </label>
          <input type="text" class="inline-popover-reason" id="inline-reason-input" placeholder="Nhập lý do thay đổi..." />
          <div style="display: flex; justify-content: flex-end; gap: 6px;">
            <button type="button" class="button secondary compact" id="inline-status-cancel-btn" style="min-height: 28px; font-size: 11px;">Hủy</button>
            <button type="button" class="button primary compact" id="inline-status-confirm-btn" style="min-height: 28px; font-size: 11px;">Xác nhận</button>
          </div>
        </div>
      `;

      positionPopover(trigger);
      popoverEl.querySelector('.inline-popover-close')?.addEventListener('click', closeQuickPopover);

      let pendingTargetStatus = null;

      const executeStatusChange = async (targetStatus, reason) => {
        try {
          const res = await sendInlinePatch(statusUrl, {
            status: targetStatus,
            expected_status: currentStatus,
            reason: reason || undefined,
          });

          row.dataset.status = res.status;
          if (res.task?.revision) {
            row.dataset.revision = res.task.revision;
            const cb = row.querySelector('input[name^="items["]');
            if (cb) cb.value = res.task.revision;
          }

          const pill = trigger.querySelector('.status-pill');
          if (pill) {
            pill.className = `status-pill status-${res.status.toLowerCase().replace('_', '-')}`;
            pill.textContent = res.status_label;
          }

          if (row.dataset.groupOwner) {
            updateGroupProgress(row.dataset.groupOwner);
          }

          closeQuickPopover();
          if (window.showToast) window.showToast(res.message || `Đã chuyển sang ${res.status_label}`, 'success');
        } catch (err) {
          if (window.showToast) window.showToast(err.message, 'error');
        }
      };

      popoverEl.querySelectorAll('[data-status-val]:not(:disabled)').forEach(btn => {
        btn.addEventListener('click', async () => {
          const targetStatus = btn.dataset.statusVal;
          if (targetStatus === currentStatus) {
            closeQuickPopover();
            return;
          }

          const needsReason = ['CANCELLED', 'TODO'].includes(targetStatus) || (currentStatus === 'REVIEW' && targetStatus === 'IN_PROGRESS');

          if (needsReason) {
            pendingTargetStatus = targetStatus;
            const reasonBox = popoverEl.querySelector('#inline-status-reason-box');
            const reasonInput = popoverEl.querySelector('#inline-reason-input');
            if (reasonBox) {
              reasonBox.style.display = 'block';
              positionPopover(trigger);
              reasonInput?.focus();
            }
            return;
          }

          btn.disabled = true;
          btn.style.opacity = '0.5';
          await executeStatusChange(targetStatus);
        });
      });

      popoverEl.querySelector('#inline-status-cancel-btn')?.addEventListener('click', () => {
        const reasonBox = popoverEl.querySelector('#inline-status-reason-box');
        if (reasonBox) reasonBox.style.display = 'none';
        positionPopover(trigger);
        pendingTargetStatus = null;
      });

      popoverEl.querySelector('#inline-status-confirm-btn')?.addEventListener('click', async () => {
        if (!pendingTargetStatus) return;
        const reasonInput = popoverEl.querySelector('#inline-reason-input');
        const reason = reasonInput?.value?.trim() || '';
        if (!reason) {
          if (window.showToast) window.showToast('Vui lòng nhập lý do thay đổi trạng thái', 'warning');
          reasonInput?.focus();
          return;
        }

        const confirmBtn = popoverEl.querySelector('#inline-status-confirm-btn');
        if (confirmBtn) {
          confirmBtn.disabled = true;
          confirmBtn.textContent = 'Đang lưu...';
        }
        await executeStatusChange(pendingTargetStatus, reason);
      });

    } else if (field === 'assignee') {
      const members = getWorkspaceMembers();

      popoverEl.innerHTML = `
        <div class="inline-popover-header">
          <span class="inline-popover-title">Phân công người thực hiện</span>
          <button type="button" class="inline-popover-close" title="Đóng">✕</button>
        </div>
        <input type="search" class="inline-popover-search" id="inline-assignee-search" placeholder="Tìm tên hoặc email..." />
        <div class="inline-popover-list" id="inline-assignee-list">
          <button type="button" class="inline-popover-option ${!currentAssigneeId ? 'is-active' : ''}" data-assignee-val="">
            <span style="display: inline-flex; align-items: center; gap: 8px;">
              <span class="inline-assignee-avatar is-empty">👤</span>
              <em>Chưa phân công</em>
            </span>
            ${!currentAssigneeId ? '<span>✓</span>' : ''}
          </button>
          ${members.map(m => {
            const isCurrent = m.id === currentAssigneeId;
            const initial = (m.name || '?').charAt(0).toUpperCase();
            return `
              <button type="button" class="inline-popover-option ${isCurrent ? 'is-active' : ''}" data-assignee-val="${m.id}" data-search-text="${(m.name + ' ' + m.email).toLowerCase()}">
                <span style="display: inline-flex; align-items: center; gap: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <span class="inline-assignee-avatar">${initial}</span>
                  <span>
                    <strong style="display: block; font-size: 12.5px;">${m.name}</strong>
                    <small style="color: var(--text-muted); font-size: 11px;">${m.email || ''}</small>
                  </span>
                </span>
                ${isCurrent ? '<span>✓</span>' : ''}
              </button>
            `;
          }).join('')}
        </div>
      `;

      positionPopover(trigger);
      popoverEl.querySelector('.inline-popover-close')?.addEventListener('click', closeQuickPopover);

      const searchInput = popoverEl.querySelector('#inline-assignee-search');
      searchInput?.focus();
      searchInput?.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();
        popoverEl.querySelectorAll('#inline-assignee-list [data-assignee-val]').forEach(btn => {
          if (!btn.dataset.assigneeVal) {
            btn.style.display = query ? 'none' : '';
            return;
          }
          const text = btn.dataset.searchText || '';
          btn.style.display = text.includes(query) ? '' : 'none';
        });
        positionPopover(trigger);
      });

      popoverEl.querySelectorAll('[data-assignee-val]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const newAssigneeId = btn.dataset.assigneeVal || null;
          if (newAssigneeId === currentAssigneeId) {
            closeQuickPopover();
            return;
          }

          btn.disabled = true;
          btn.style.opacity = '0.5';

          try {
            const res = await sendInlinePatch(updateUrl, {
              edit_surface: 'inline',
              expected_revision: revision,
              assignee_id: newAssigneeId,
            });

            row.dataset.assigneeId = res.task.assignee_id || '';
            row.dataset.revision = res.task.revision;
            const cb = row.querySelector('input[name^="items["]');
            if (cb) cb.value = res.task.revision;

            const nameEl = trigger.querySelector('.inline-assignee-name');
            if (nameEl) nameEl.textContent = res.task.assignee_name;

            const avEl = trigger.querySelector('.inline-assignee-avatar');
            if (avEl) {
              if (res.task.assignee_id) {
                avEl.classList.remove('is-empty');
                avEl.innerHTML = `<span class="assignee-initials">${(res.task.assignee_name || '?').charAt(0).toUpperCase()}</span>`;
              } else {
                avEl.classList.add('is-empty');
                avEl.innerHTML = `<span class="assignee-empty-icon">👤</span>`;
              }
            }

            closeQuickPopover();
            if (window.showToast) {
              window.showToast(res.task.assignee_id ? `Đã phân công cho ${res.task.assignee_name}` : 'Đã bỏ phân công công việc', 'success');
            }
          } catch (err) {
            btn.disabled = false;
            btn.style.opacity = '';
            if (window.showToast) window.showToast(err.message, 'error');
          }
        });
      });

    } else if (field === 'deadline') {
      const presets = getDeadlinePresets();

      popoverEl.innerHTML = `
        <div class="inline-popover-header">
          <span class="inline-popover-title">Hạn hoàn thành (Deadline)</span>
          <button type="button" class="inline-popover-close" title="Đóng">✕</button>
        </div>
        <div class="inline-popover-chips">
          ${presets.map(p => `
            <button type="button" class="inline-popover-chip" data-preset-val="${p.value || ''}">
              ${p.label}
            </button>
          `).join('')}
          <button type="button" class="inline-popover-chip is-clear" data-preset-val="">✕ Bỏ hạn</button>
        </div>
        <div style="margin-bottom: 8px;">
          <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Chọn ngày giờ cụ thể:</label>
          <input type="datetime-local" class="inline-popover-date" id="inline-date-picker" value="${currentDeadline}" />
        </div>
        <div style="margin-bottom: 10px;">
          <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Lý do thay đổi hạn (ghi log):</label>
          <input type="text" class="inline-popover-reason" id="inline-deadline-reason" value="Điều chỉnh kế hoạch" placeholder="Lý do đổi hạn..." />
        </div>
        <div class="inline-popover-actions">
          <button type="button" class="button secondary compact" id="inline-deadline-cancel-btn" style="min-height: 28px; font-size: 12px;">Hủy</button>
          <button type="button" class="button primary compact" id="inline-deadline-save-btn" style="min-height: 28px; font-size: 12px;">Lưu thay đổi</button>
        </div>
      `;

      positionPopover(trigger);

      popoverEl.querySelector('.inline-popover-close')?.addEventListener('click', closeQuickPopover);
      popoverEl.querySelector('#inline-deadline-cancel-btn')?.addEventListener('click', closeQuickPopover);

      const datePicker = popoverEl.querySelector('#inline-date-picker');
      const reasonInput = popoverEl.querySelector('#inline-deadline-reason');
      const saveBtn = popoverEl.querySelector('#inline-deadline-save-btn');

      popoverEl.querySelectorAll('[data-preset-val]').forEach(chip => {
        chip.addEventListener('click', () => {
          if (datePicker) datePicker.value = chip.dataset.presetVal || '';
        });
      });

      saveBtn?.addEventListener('click', async () => {
        const newDeadline = datePicker?.value || null;
        const reason = reasonInput?.value?.trim() || 'Điều chỉnh kế hoạch';

        saveBtn.disabled = true;
        saveBtn.textContent = 'Đang lưu...';

        try {
          const res = await sendInlinePatch(updateUrl, {
            edit_surface: 'inline',
            expected_revision: revision,
            deadline_at: newDeadline,
            deadline_reason: reason,
          });

          row.dataset.deadline = res.task.deadline_at || '';
          row.dataset.revision = res.task.revision;
          const cb = row.querySelector('input[name^="items["]');
          if (cb) cb.value = res.task.revision;

          const deadlineTextEl = trigger.querySelector('.inline-deadline-text');
          if (deadlineTextEl) {
            deadlineTextEl.textContent = res.task.deadline_formatted || 'Chưa đặt hạn';
            // Check overdue
            if (res.task.deadline_at && !['DONE', 'CANCELLED'].includes(currentStatus)) {
              const d = new Date(res.task.deadline_at);
              if (d < new Date()) {
                deadlineTextEl.classList.add('task-overdue');
              } else {
                deadlineTextEl.classList.remove('task-overdue');
              }
            } else {
              deadlineTextEl.classList.remove('task-overdue');
            }
          }

          closeQuickPopover();
          if (window.showToast) {
            window.showToast(res.task.deadline_formatted ? `Đã cập nhật hạn: ${res.task.deadline_formatted}` : 'Đã bỏ hạn hoàn thành', 'success');
          }
        } catch (err) {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Lưu thay đổi';
          if (window.showToast) window.showToast(err.message, 'error');
        }
      });
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && popoverEl && !popoverEl.hidden) {
      closeQuickPopover();
    }
  });

  // 12. Interactive Task File Attachments Dropzone & Staging Queue
  const initAttachmentDropzones = (root = document) => {
    root.querySelectorAll('form[data-form="attachment-create"]').forEach(form => {
      const fileInput = form.querySelector('.task-file-input');
      const dropzone = form.querySelector('.file-dropzone');
      const queue = form.querySelector('.selected-files-queue');
      const queueList = form.querySelector('#queue-list');
      const queueCount = form.querySelector('.queue-count');
      const queueSubmitCount = form.querySelector('.queue-submit-count');
      const queueTotalSize = form.querySelector('#queue-total-size');
      const clearBtn = form.querySelector('#btn-clear-selected-files');
      const submitBtn = form.querySelector('#btn-submit-upload');

      if (!fileInput || !queue || !queueList) return;

      let selectedFiles = [];

      const formatBytes = (bytes) => {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
      };

      const syncInputFiles = () => {
        try {
          const dt = new DataTransfer();
          selectedFiles.forEach(file => dt.items.add(file));
          fileInput.files = dt.files;
        } catch (e) {
          console.warn('DataTransfer not fully supported', e);
        }
      };

      const renderQueue = () => {
        if (selectedFiles.length === 0) {
          queue.style.display = 'none';
          fileInput.value = '';
          return;
        }

        queue.style.display = 'block';
        queueList.innerHTML = '';

        let totalBytes = 0;
        const maxFileBytes = 10 * 1024 * 1024; // 10MB limit

        selectedFiles.forEach((file, index) => {
          totalBytes += file.size;
          const isImage = file.type.startsWith('image/');
          const ext = (file.name.split('.').pop() || '').toLowerCase();
          const isOverSize = file.size > maxFileBytes;

          const item = document.createElement('div');
          item.className = `queue-item ${isOverSize ? 'is-invalid' : ''}`;

          const left = document.createElement('div');
          left.className = 'queue-item-left';

          const thumb = document.createElement('div');
          thumb.className = 'queue-item-thumb';

          if (isImage) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            thumb.appendChild(img);
          } else {
            let icon = '📁';
            if (ext === 'pdf') icon = '📕';
            else if (['doc', 'docx'].includes(ext)) icon = '📘';
            else if (['xls', 'xlsx', 'csv'].includes(ext)) icon = '📗';
            else if (ext === 'txt') icon = '📄';
            thumb.innerHTML = `<span style="font-size: 20px;">${icon}</span>`;
          }

          const meta = document.createElement('div');
          meta.className = 'queue-item-meta';

          const nameEl = document.createElement('div');
          nameEl.className = 'queue-item-name';
          nameEl.textContent = file.name;
          nameEl.title = file.name;

          const sub = document.createElement('div');
          sub.className = 'queue-item-sub';
          sub.innerHTML = `
            <span>${formatBytes(file.size)}</span>
            ${isOverSize ? '<span style="color: var(--danger); font-weight: 700;">(Quá 10MB)</span>' : '<span style="color: #10b981;">✓ Sẵn sàng</span>'}
          `;

          meta.append(nameEl, sub);
          left.append(thumb, meta);

          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'queue-item-remove';
          removeBtn.innerHTML = '✕ Xóa bỏ';
          removeBtn.title = `Bỏ "${file.name}" khỏi danh sách tải lên`;
          removeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            selectedFiles.splice(index, 1);
            syncInputFiles();
            renderQueue();
          });

          item.append(left, removeBtn);
          queueList.appendChild(item);
        });

        if (queueCount) queueCount.textContent = selectedFiles.length;
        if (queueSubmitCount) queueSubmitCount.textContent = selectedFiles.length;
        if (queueTotalSize) queueTotalSize.textContent = formatBytes(totalBytes);
        if (submitBtn) submitBtn.disabled = selectedFiles.length === 0;
      };

      const addFiles = (newFiles) => {
        const incoming = Array.from(newFiles);
        if (!incoming.length) return;

        incoming.forEach(f => {
          const exists = selectedFiles.some(existing => existing.name === f.name && existing.size === f.size);
          if (!exists) {
            if (selectedFiles.length < 10) {
              selectedFiles.push(f);
            } else {
              if (window.showToast) window.showToast('Chỉ có thể tải lên tối đa 10 tệp cùng lúc.', 'warning');
            }
          }
        });

        syncInputFiles();
        renderQueue();
      };

      fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
      });

      clearBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        selectedFiles = [];
        syncInputFiles();
        renderQueue();
      });

      if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
          dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('is-dragover');
          });
        });

        ['dragleave', 'drop'].forEach(eventName => {
          dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');
          });
        });

        dropzone.addEventListener('drop', (e) => {
          const dt = e.dataTransfer;
          if (dt && dt.files && dt.files.length) {
            addFiles(dt.files);
          }
        });
      }

      form.addEventListener('submit', (e) => {
        if (selectedFiles.length === 0) {
          e.preventDefault();
          if (window.showToast) window.showToast('Vui lòng chọn ít nhất 1 tệp để tải lên.', 'warning');
          return;
        }
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = `⏳ Đang tải lên ${selectedFiles.length} tệp...`;
        }
      });
    });
  };

  initAttachmentDropzones();

})();
