(() => {
  'use strict';

  // Full Blade pages keep their validation, scripts and ordinary HTTP actions.
  // Keeping a closed frame alive also keeps its unsaved fields and file inputs.
  const embedded = window.parent !== window && window.frameElement?.hasAttribute('data-editor-frame');
  if (embedded) {
    document.documentElement.classList.add('editor-document');
    const notify = (type) => window.parent.postMessage({ workEditor: type }, location.origin);
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        event.preventDefault();
        notify('close');
      }
    });
    document.addEventListener('submit', event => {
      if (!event.defaultPrevented && event.target.method.toLowerCase() !== 'get') notify('submitting');
    });
    document.addEventListener('click', event => {
      if (event.target.closest('a[data-editor-cancel]')) {
        event.preventDefault();
        notify('close');
      }
    });
    for (const type of ['input', 'change']) {
      document.addEventListener(type, event => { if (event.target.closest('form')) notify('dirty'); });
    }
    return;
  }

  const dialog = document.querySelector('#page-editor');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const frames = dialog.querySelector('[data-editor-frames]');
  const heading = dialog.querySelector('#page-editor-title');
  const status = dialog.querySelector('[data-editor-status]');
  const save = dialog.querySelector('[data-editor-save]');
  const standalone = dialog.querySelector('[data-editor-standalone]');
  const sessions = new Map();
  const maxSessions = 5;
  let active = null;
  let trigger = null;
  const hasDirtySession = () => [...sessions.values()].some(session => session.dirty && !session.pending);

  function evictIdleSessions() {
    for (const [key, session] of sessions) {
      if (sessions.size <= maxSessions) return;
      if (session === active || session.dirty || session.pending) continue;
      session.frame.remove();
      sessions.delete(key);
    }
  }
  const formUrl = form => new URL(form.getAttribute('action') || form.ownerDocument.URL, form.ownerDocument.URL).href;

  const inlineForms = {
    'member-update': 'Chỉnh sửa thành viên',
    'invitation-create': 'Mời thành viên',
    'project-member-create': 'Thêm thành viên dự án',
    'project-member-update': 'Chỉnh sửa thành viên dự án',
    'template-create': 'Tạo mẫu công việc',
    'template-update': 'Sửa tên và lịch lặp',
    'calendar-proposal': 'Gửi đề xuất lịch',
    'owner-transfer': 'Chuyển chủ sở hữu',
    'github-settings': 'Cấu hình GitHub',
    'plan-schedule': 'Xếp lịch kế hoạch',
    'comment-create': 'Viết bình luận',
    'comment-update': 'Chỉnh sửa bình luận',
    'blocker-create': 'Báo trở ngại',
    'blocker-update': 'Xử lý trở ngại',
    'dependency-create': 'Thêm quan hệ phụ thuộc',
    'attachment-create': 'Thêm tệp đính kèm',
    'watcher-add': 'Thêm người theo dõi',
    'task-status': 'Cập nhật trạng thái',
    'filter-save': 'Lưu bộ lọc hiện tại',
    'task-bulk': 'Cập nhật các việc đã chọn'
  };

  function mountLaunchers(root = document) {
    for (const [name, label] of Object.entries(inlineForms)) {
      for (const form of root.querySelectorAll(`form[data-form="${name}"]`)) {
        if (form.dataset.editorLauncher || !form.querySelector('input:not([type="hidden"]), textarea, select')) continue;
        form.dataset.editorLauncher = '1';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'secondary editor-launcher';
        button.textContent = label;
        button.setAttribute('aria-haspopup', 'dialog');
        const row = form.closest('tr');
        const subject = row?.querySelector('td')?.textContent.trim().replace(/\s+/g, ' ');
        button.addEventListener('click', () => {
          const selected = name === 'task-bulk' ? [...document.querySelectorAll('input[form="bulk-tasks"]:checked')].map(input => ({ name: input.name, value: input.value })) : [];
          if (name === 'task-bulk' && !selected.length) {
            window.showToast?.('Hãy chọn công việc ở bảng trước khi cập nhật.', 'info');
            return;
          }
          open(location.href, button, {
            name, action: formUrl(form), title: subject ? `${label} · ${subject}` : label, selected
          });
        });
        form.before(button);
        form.hidden = true;
      }
    }
  }

  function close() { if (dialog.open) dialog.close(); }

  function updateToolbar(session) {
    if (session !== active) return;
    heading.textContent = session.title;
    status.textContent = session.pending ? 'Đang lưu…' : session.dirty
      ? 'Chưa lưu · Đóng để giữ bản nhập trong trang này' : 'Đóng bằng ×, phím Esc hoặc vùng bên ngoài';
    save.hidden = !session.form;
    save.disabled = session.pending;
  }

  function prepare(session) {
    const doc = session.frame.contentDocument;
    if (!doc?.querySelector('#main-content')) {
      if (session === active) status.textContent = 'Không tải được form. Hãy mở trang riêng để kiểm tra.';
      return;
    }
    doc.documentElement.classList.add('editor-document');
    const main = doc.querySelector('#main-content');
    const focusForm = session.selection && [...main.querySelectorAll('form')].find(form =>
      form.dataset.form === session.selection.name && formUrl(form) === session.selection.action);
    if (focusForm) {
      const errors = main.querySelector('#error-summary');
      const card = doc.createElement('section');
      card.className = 'card editor-focused-form';
      const title = doc.createElement('h1');
      title.textContent = session.selection.title;
      card.append(title, focusForm);
      if (session.selection.name === 'task-bulk') {
        const selected = session.selection.selected;
        for (const input of main.querySelectorAll('input[form="bulk-tasks"]')) {
          input.checked = selected.some(item => item.name === input.name);
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        for (const item of selected) {
          const input = doc.createElement('input');
          input.type = 'hidden'; input.name = item.name; input.value = item.value;
          focusForm.append(input);
        }
        const summary = doc.createElement('p');
        summary.textContent = `${selected.length} công việc đã chọn. Toàn bộ nhóm được kiểm quyền và phiên bản trước khi lưu.`;
        title.after(summary);
      }
      for (const [name, label] of Object.entries({ role_id: 'Vai trò', status: 'Trạng thái' })) {
        const control = focusForm.querySelector(`[name="${name}"]`);
        if (control && !control.labels?.length && !control.hasAttribute('aria-label')) {
          const fieldLabel = doc.createElement('label');
          fieldLabel.textContent = label;
          control.before(fieldLabel);
          fieldLabel.append(control);
        }
      }
      focusForm.hidden = false;
      main.replaceChildren(...(errors ? [errors] : []), card);
    }
    const editable = [...main.querySelectorAll('form')].filter(form =>
      form.method.toLowerCase() !== 'get' &&
      form.querySelector('input:not([type="hidden"]):not([type="submit"]), textarea, select') &&
      !form.dataset.confirm && !/delete|cancel|logout|status|comment|attachment/.test(form.dataset.form || ''));
    session.form = focusForm?.dataset.form === 'task-status' ? null : focusForm || (editable.length === 1 ? editable[0] : null);
    session.title = session.selection?.title || main.querySelector('h1,h2')?.textContent.trim() || 'Chỉnh sửa';
    updateToolbar(session);
    if (session === active && dialog.open) {
      const target = main.querySelector('#error-summary, [autofocus]') ||
        (session.form || main).querySelector('input:not([type="hidden"]), select, textarea');
      target?.focus();
    }
  }

  async function refreshPage() {
    if (window.WorkUI?.hasDraft()) throw new Error('background-draft');
    const main = document.querySelector('#main-content');
    const scroll = { x: scrollX, y: scrollY };
    const selected = [...main.querySelectorAll('input[form="bulk-tasks"]:checked')].map(input => input.name);
    const expanded = [...main.querySelectorAll('details')].map(details => details.open);
    const boardScroll = main.querySelector('.board-scroll')?.scrollLeft;
    const response = await fetch(location.href, { headers: { Accept: 'text/html' }, cache: 'no-store' });
    if (!response.ok || response.redirected) throw new Error('refresh');
    const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
    const next = parsed.querySelector('#main-content');
    if (!next) throw new Error('refresh');
    main.replaceChildren(...[...next.childNodes].map(node => document.importNode(node, true)));
    main.querySelectorAll('input[form="bulk-tasks"]').forEach(input => { input.checked = selected.includes(input.name); });
    main.querySelectorAll('details').forEach((details, index) => {
      if (expanded[index] !== undefined) details.open = expanded[index];
    });
    window.WorkUI?.mount(main);
    mountLaunchers(main);
    if (boardScroll !== undefined && main.querySelector('.board-scroll')) main.querySelector('.board-scroll').scrollLeft = boardScroll;
    for (const selector of ['[data-unread-count]', '[data-account-name]', '.workspace-switcher', '.nav-context']) {
      const current = document.querySelector(selector);
      const updated = parsed.querySelector(selector);
      if (current && updated) current.replaceChildren(...[...updated.childNodes].map(node => document.importNode(node, true)));
    }
    window.WorkTheme?.set(parsed.documentElement.dataset.themePreference || 'system');
    window.scrollTo(scroll.x, scroll.y);
  }

  async function loaded(session) {
    try {
      const doc = session.frame.contentDocument;
      const message = doc?.querySelector('[data-flash-type="success"]')?.dataset.flashMessage;
      if (session.pending && message) {
        sessions.delete(session.key);
        if (active === session) close();
        session.frame.remove();
        try {
          await refreshPage();
          window.showToast?.(message);
        } catch {
          window.showToast?.('Đã lưu. Hãy tải lại trang để xem dữ liệu mới; bản nhập trên trang chính vẫn được giữ.', 'info');
        }
        return;
      }
      session.pending = false;
      prepare(session);
    } catch {
      if (session === active) status.textContent = 'Không mở được nội dung. Hãy dùng liên kết Mở trang riêng.';
    }
  }

  function open(url, source, selection = null, field = null) {
    const target = new URL(url, location.href);
    if (target.origin !== location.origin) return;
    target.searchParams.delete('panel');
    const key = `${target.href}|${selection?.name || ''}|${selection?.action || ''}|${JSON.stringify(selection?.selected || [])}`;
    trigger = source;
    for (const session of sessions.values()) session.frame.hidden = true;
    active = sessions.get(key);
    if (!active) {
      const frame = document.createElement('iframe');
      frame.setAttribute('data-editor-frame', '');
      frame.title = selection?.title || 'Biểu mẫu chỉnh sửa';
      active = { key, frame, selection, title: frame.title, dirty: false, pending: false, form: null };
      const session = active;
      frame.addEventListener('load', () => {
        loaded(session).then(() => {
          if (field && active === session && dialog.open) frame.contentDocument?.querySelector(`[name="${CSS.escape(field)}"]`)?.focus();
        });
      });
      sessions.set(key, session);
      frame.src = target.href;
      frames.append(frame);
      evictIdleSessions();
    }
    active.frame.hidden = false;
    standalone.href = target.href;
    updateToolbar(active);
    if (!dialog.open) dialog.showModal();
    document.body.classList.add('page-editor-open');
    if (active.form) active.frame.contentWindow.focus();
  }

  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href], [data-task-panel]');
    if (!link || link.closest('#page-editor') || link.target === '_blank' || link.hasAttribute('download')) return;
    const url = link.getAttribute('href') || link.dataset.panelUrl;
    if (!url) return;
    const target = new URL(url, location.href);
    const editorPath = /\/(create|edit)$/.test(target.pathname) || /^\/(profile|settings|work-hours)$/.test(target.pathname) || /\/timers\/[^/]+\/adjust$/.test(target.pathname);
    if (target.origin !== location.origin || (!link.hasAttribute('data-task-panel') && !editorPath)) return;
    event.preventDefault();
    open(url, link, null, link.dataset.panelField);
  });
  dialog.querySelector('[data-editor-close]').addEventListener('click', close);
  dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
  });
  dialog.addEventListener('close', () => {
    document.body.classList.remove('page-editor-open');
    if (trigger?.isConnected) trigger.focus();
    else document.querySelector('#main-content')?.focus();
  });
  save.addEventListener('click', () => {
    if (!active?.form || active.pending) return;
    const button = active.form.querySelector('button[type="submit"]:not(:disabled), button:not([type]):not(:disabled)');
    if (button) active.form.requestSubmit(button);
    else active.form.requestSubmit();
  });
  window.addEventListener('beforeunload', event => {
    if (hasDirtySession()) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
  window.addEventListener('message', event => {
    if (event.origin !== location.origin) return;
    const session = [...sessions.values()].find(item => item.frame.contentWindow === event.source);
    if (!session) return;
    if (event.data?.workEditor === 'close' && active === session) close();
    if (event.data?.workEditor === 'dirty') session.dirty = true;
    if (event.data?.workEditor === 'submitting') { session.pending = true; session.dirty = false; }
    updateToolbar(session);
  });
  mountLaunchers();
  const main = document.querySelector('#main-content');
  if (main) new MutationObserver(() => mountLaunchers(main)).observe(main, { childList: true, subtree: true });
})();
