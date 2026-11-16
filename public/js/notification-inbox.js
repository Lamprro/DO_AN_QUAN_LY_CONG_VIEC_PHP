// Notification inbox: mark read / unread without reloading the page.
// The card flips immediately; the server response then decides the final state.
// On failure the card goes back to what it was. Forms still work without JS.
(() => {
  const page = document.querySelector('[data-page="notification-inbox"]');
  if (!page) return;

  const filter = new URLSearchParams(location.search).get('filter') || 'all';
  const errors = {
    401: 'Bạn cần đăng nhập lại.',
    403: 'Bạn không có quyền với thông báo này.',
    404: 'Thông báo không còn trong phạm vi bạn được xem.',
    419: 'Phiên bảo mật đã hết hạn. Hãy tải lại trang.',
    429: 'Bạn thao tác quá nhanh. Hãy thử lại sau.',
  };

  const toast = (message, type = 'success') => window.showToast?.(message, type);
  const isRead = (card) => !card.classList.contains('is-unread');

  function render(card, read) {
    card.classList.toggle('is-unread', !read);
    const badge = card.querySelector('[data-read-badge]');
    if (badge) badge.textContent = read ? 'Đã đọc' : 'Chưa đọc';
    const button = card.querySelector('[data-read-toggle]');
    if (button) button.textContent = read ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc';
    const unread = card.querySelector('form[data-form="notification-read"] [name="unread"]');
    if (unread) unread.value = read ? '1' : '0';
  }

  function setUnreadCount(count) {
    if (!Number.isInteger(count)) return;
    document.querySelectorAll('[data-unread-count]').forEach((el) => { el.textContent = String(count); });
  }

  // With "Chưa đọc" / "Đã đọc" filters a card that no longer matches is hidden.
  function hideIfFiltered(card) {
    const read = isRead(card);
    if (!((filter === 'unread' && read) || (filter === 'read' && !read))) return;
    card.classList.add('is-leaving');
    setTimeout(() => {
      const list = card.parentElement;
      card.remove();
      if (list && !list.querySelector('.notification-item')) {
        const empty = document.createElement('div');
        empty.className = 'card';
        empty.style.cssText = 'text-align:center;padding:2rem 1.5rem;';
        empty.textContent = 'Không còn thông báo nào phù hợp bộ lọc trên trang này.';
        list.append(empty);
      }
    }, 250);
  }

  async function send(form, body) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) throw new Error(errors[419]);
    const response = await fetch(form.action, {
      method: 'PATCH' === (form.querySelector('[name="_method"]')?.value || '').toUpperCase() ? 'PATCH' : 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(12000),
    });
    const result = await response.json().catch(() => ({}));
    if (response.redirected || !response.ok || result.success !== true) {
      throw new Error(Object.values(result.errors || {}).flat().join('; ') || errors[response.status] || result.message || 'Không cập nhật được. Hãy thử lại.');
    }
    return result;
  }

  async function toggleOne(form) {
    const card = form.closest('.notification-item');
    if (!card || card.getAttribute('aria-busy') === 'true') return;
    const before = isRead(card);
    card.setAttribute('aria-busy', 'true');
    render(card, !before);
    try {
      const result = await send(form, { unread: before });
      render(card, result.read === true);
      setUnreadCount(result.unread_count);
      hideIfFiltered(card);
    } catch (error) {
      render(card, before);
      toast(error.message, 'error');
    } finally {
      card.removeAttribute('aria-busy');
    }
  }

  async function readAll(form) {
    const button = form.querySelector('button');
    if (form.getAttribute('aria-busy') === 'true') return;
    const cards = [...document.querySelectorAll('.notification-item.is-unread')];
    form.setAttribute('aria-busy', 'true');
    if (button) button.disabled = true;
    cards.forEach((card) => render(card, true));
    try {
      const result = await send(form, {});
      setUnreadCount(result.unread_count);
      cards.forEach(hideIfFiltered);
      toast(result.message);
    } catch (error) {
      cards.forEach((card) => render(card, false));
      toast(error.message, 'error');
    } finally {
      form.removeAttribute('aria-busy');
      if (button) button.disabled = false;
    }
  }

  // Capture phase so this runs before the generic form handler in app-ui.js,
  // which skips events that are already defaultPrevented.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.form === 'notification-read') {
      event.preventDefault();
      toggleOne(form);
    } else if (form.dataset.form === 'notification-read-all') {
      event.preventDefault();
      readAll(form);
    }
  }, true);
})();
