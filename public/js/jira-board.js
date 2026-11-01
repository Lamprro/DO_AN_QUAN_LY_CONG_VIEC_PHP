(() => {
  'use strict';

  const mounted = new WeakSet();
  const state = { filter: 'all', search: '', advanced: null };
  let dragging = null;
  let saving = false;
  const normalize = value => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLocaleLowerCase('vi').trim();

  function toast(message, kind = 'success', reload = false) {
    let region = document.querySelector('.jira-toast-region');
    if (!region) {
      region = document.createElement('div');
      region.className = 'jira-toast-region';
      region.setAttribute('aria-label', 'Thông báo bảng công việc');
      document.body.append(region);
    }
    const item = document.createElement('div');
    item.className = 'jira-toast';
    item.dataset.kind = kind;
    item.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    const text = document.createElement('p');
    text.textContent = message;
    if (reload) {
      const link = document.createElement('a');
      link.href = location.href;
      link.textContent = ' Tải lại bảng';
      text.append(link);
    }
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = '✕';
    close.setAttribute('aria-label', 'Đóng thông báo');
    close.addEventListener('click', () => item.remove());
    item.append(text, close);
    region.append(item);
    if (kind === 'success') setTimeout(() => item.remove(), 6000);
  }

  function updateDeadline(card) {
    const deadline = Date.parse(card.dataset.deadline);
    const active = !['DONE', 'CANCELLED'].includes(card.dataset.currentStatus);
    const overdue = active && Number.isFinite(deadline) && deadline < Date.now();
    const soon = active && !overdue && deadline <= Date.now() + 86400000;
    card.dataset.isOverdue = String(overdue);
    const badge = card.querySelector('[data-deadline-badge]');
    badge?.classList.toggle('is-overdue', overdue);
    badge?.classList.toggle('is-due-soon', soon);
    const label = card.querySelector('[data-deadline-label]');
    if (label) label.textContent = overdue ? 'Quá hạn • ' : soon ? 'Sắp đến hạn • ' : '';
  }

  function applyFilters(board) {
    const search = normalize(state.search);
    const filtered = state.filter !== 'all' || search !== '';
    let visible = 0;
    let loaded = 0;
    let total = 0;
    board.querySelectorAll('.jira-chip').forEach(chip => chip.setAttribute('aria-pressed', String(chip.dataset.quickFilter === state.filter)));
    board.querySelectorAll('.jira-column').forEach(column => {
      const cards = [...column.querySelectorAll('.jira-card')];
      let count = 0;
      cards.forEach(card => {
        updateDeadline(card);
        const data = card.dataset;
        const matches = {
          all: true,
          mine: data.assigneeId === board.dataset.userId,
          high: ['high', 'urgent'].includes(data.priority),
          overdue: data.isOverdue === 'true',
          unassigned: !data.assigneeId,
        };
        card.hidden = !(matches[state.filter] && normalize(`${data.code} ${data.title}`).includes(search));
        if (!card.hidden) count++;
      });
      const columnTotal = Number(column.dataset.total);
      const badge = column.querySelector('[data-column-count]');
      badge.textContent = filtered ? `${count}/${columnTotal}` : String(columnTotal);
      badge.title = `${count} thẻ đang hiển thị / ${columnTotal} công việc trong cột`;
      const empty = column.querySelector('[data-column-empty]');
      empty.hidden = count > 0;
      empty.textContent = filtered ? 'Không có thẻ phù hợp trên trang này' : columnTotal > 0 ? 'Không còn thẻ trên trang này' : 'Chưa có công việc';
      visible += count;
      loaded += cards.length;
      total += columnTotal;
    });
    const summary = board.querySelector('[data-board-summary]');
    if (summary) summary.textContent = `${visible}/${loaded} thẻ hiển thị • ${total} tổng công việc`;
  }

  function clearDrag(board) {
    board.querySelectorAll('.is-dragover, .is-dragging').forEach(element => element.classList.remove('is-dragover', 'is-dragging'));
    dragging = null;
  }

  async function refreshBoard(board) {
    const response = await fetch(location.href, {
      headers: { Accept: 'text/html' }, cache: 'no-store', signal: AbortSignal.timeout(15000),
    });
    if (!response.ok || response.redirected) throw new Error('refresh');
    const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
    const next = parsed.querySelector('[data-jira-board]');
    if (!next || !board.isConnected) throw new Error('refresh');
    const scrollEl = board.querySelector('.jira-scroll');
    const scroll = scrollEl ? scrollEl.scrollLeft : 0;
    const replacement = document.importNode(next, true);
    board.replaceWith(replacement);
    const newScroll = replacement.querySelector('.jira-scroll');
    if (newScroll) newScroll.scrollLeft = scroll;
    mount(replacement);
    window.WorkUI?.mount(replacement);
  }

  async function moveCard(board, card, target) {
    const targetStatus = target.dataset.status;
    const currentStatus = card.dataset.currentStatus;
    if (targetStatus === currentStatus || saving || !card.draggable) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return toast('Thiếu phiên bảo mật. Tải lại bảng trước khi thao tác.', 'error', true);

    saving = true;
    board.setAttribute('aria-busy', 'true');
    card.classList.add('is-saving');
    const source = card.closest('.jira-column');
    const parent = card.parentNode;
    const next = card.nextSibling;
    const dropZone = target.querySelector('[data-drop-zone]');
    dropZone.prepend(card);
    applyFilters(board);

    let committed = false;
    let certainFailure = false;
    let failureStatus;

    try {
      const response = await fetch(card.dataset.statusUrl, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          status: targetStatus,
          expected_status: currentStatus,
        }),
        signal: AbortSignal.timeout(12000),
      });

      failureStatus = response.status;
      certainFailure = [401, 403, 404, 409, 419, 422, 429].includes(response.status);
      const result = await response.json().catch(() => ({}));

      if (response.redirected || !response.ok || result.success !== true || result.status !== targetStatus) {
        const messages = {
          401: 'Bạn cần đăng nhập lại.',
          403: 'Bạn không có quyền chuyển trạng thái công việc này.',
          404: 'Công việc không còn trong phạm vi truy cập.',
          419: 'Phiên bảo mật đã hết hạn. Hãy tải lại bảng.',
          429: 'Bạn thao tác quá nhanh. Hãy thử lại sau.',
        };
        const errorMsg = Object.values(result.errors || {}).flat().join('; ') || messages[response.status] || (certainFailure ? result.message : '') || 'Chưa xác định được kết quả lưu. Hãy tải lại bảng.';
        throw new Error(errorMsg);
      }

      committed = true;
      card.dataset.currentStatus = result.status;
      source.dataset.total = String(Math.max(0, Number(source.dataset.total) - 1));
      target.dataset.total = String(Number(target.dataset.total) + 1);
      applyFilters(board);
      await refreshBoard(board);
      toast(result.message || 'Đã cập nhật trạng thái công việc.');
    } catch (error) {
      if (committed) {
        card.draggable = false;
        card.querySelector('.jira-card-actions')?.setAttribute('hidden', '');
        toast('Đã lưu trạng thái, nhưng chưa tải lại được bảng. Tải lại để đồng bộ quyền, bộ lọc và phân trang.', 'error', true);
      } else {
        parent.insertBefore(card, next?.parentNode === parent ? next : null);
        if (!certainFailure) card.draggable = false;
        const message = certainFailure ? error.message : 'Chưa xác định được kết quả lưu do kết nối hoặc phản hồi máy chủ. Thẻ đã trở về vị trí cũ; tải lại bảng để xác nhận trước khi thử lại.';
        const hint = certainFailure && /lý do/i.test(message) ? ' Dùng nút Đổi trạng thái để nhập lý do.' : '';
        toast(message + hint, 'error', !certainFailure || [401, 409, 419].includes(failureStatus));
        applyFilters(board);
      }
    } finally {
      saving = false;
      card.classList.remove('is-saving');
      board.removeAttribute('aria-busy');
    }
  }

  function mount(board) {
    if (mounted.has(board)) return;
    mounted.add(board);
    const search = board.querySelector('[data-quick-search]');
    const advanced = board.querySelector('[data-advanced-filters]');
    const toggle = board.querySelector('[data-advanced-toggle]');
    if (search) {
      search.value = state.search;
      search.addEventListener('input', () => { state.search = search.value; applyFilters(board); });
    }
    if (advanced && toggle) {
      if (state.advanced === null) state.advanced = advanced.dataset.hasFilters === 'true';
      advanced.hidden = !state.advanced;
      toggle.setAttribute('aria-expanded', String(state.advanced));
    }
    applyFilters(board);
    board.addEventListener('click', event => {
      const chip = event.target.closest('[data-quick-filter]');
      if (chip) { state.filter = chip.dataset.quickFilter; applyFilters(board); }
      if (event.target.closest('[data-advanced-toggle]')) {
        state.advanced = !state.advanced;
        if (advanced) advanced.hidden = !state.advanced;
        toggle?.setAttribute('aria-expanded', String(state.advanced));
      }
    });
    board.addEventListener('dragstart', event => {
      const card = event.target.closest('.jira-card');
      if (!card || !card.draggable || saving || event.target.closest('a, button, input')) { event.preventDefault(); return; }
      dragging = card;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', card.dataset.taskId);
      card.classList.add('is-dragging');
    });
    board.addEventListener('dragover', event => {
      const column = event.target.closest('.jira-column');
      if (!dragging || !column || saving || dragging.dataset.currentStatus === column.dataset.status) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      board.querySelectorAll('.is-dragover').forEach(item => { if (item !== column) item.classList.remove('is-dragover'); });
      column.classList.add('is-dragover');
    });
    board.addEventListener('dragleave', event => {
      const column = event.target.closest('.jira-column');
      if (column && !column.contains(event.relatedTarget)) column.classList.remove('is-dragover');
    });
    board.addEventListener('drop', event => {
      const column = event.target.closest('.jira-column');
      const card = dragging;
      if (!card || !column) return;
      event.preventDefault();
      clearDrag(board);
      void moveCard(board, card, column);
    });
    board.addEventListener('dragend', () => clearDrag(board));
  }

  document.querySelectorAll('[data-jira-board]').forEach(mount);
  const main = document.querySelector('#main-content');
  if (main) new MutationObserver(() => main.querySelectorAll('[data-jira-board]').forEach(mount)).observe(main, { childList: true, subtree: true });
  document.addEventListener('click', event => {
    if (saving && event.target.closest('[data-task-panel]')) {
      event.preventDefault();
      event.stopImmediatePropagation();
      toast('Đang lưu trạng thái. Vui lòng chờ giây lát.', 'error');
    }
  }, true);
  window.addEventListener('beforeunload', event => {
    if (saving) { event.preventDefault(); event.returnValue = ''; }
  });
})();
