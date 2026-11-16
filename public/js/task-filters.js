// Task list / board: filters apply as soon as a value changes, with no "Apply" button.
// - selects and checkboxes: apply at once
// - search box: apply 350 ms after typing stops (Enter applies at once)
// - preset chips, "Xóa lọc" and pagination links: loaded in place
// The page is fetched as HTML and #main-content is swapped, but the filter form the user
// is typing in is kept as-is so focus and caret never jump. The URL follows the filters,
// so reload, back/forward, sharing a link and saving a filter all see the same state.
(() => {
  'use strict';

  const main = document.querySelector('#main-content');
  if (!main || !main.querySelector('form[data-live-filter]')) return;

  const TYPE_DELAY = 350;
  let controller = null;
  let typingTimer = null;

  const liveForm = () => main.querySelector('form[data-live-filter]');

  // Only send values that differ from the defaults, so URLs stay short and readable.
  const defaults = { preset: 'all', archived: 'active', sort: 'created_at', direction: 'desc', per_page: '10' };
  function urlFromForm(form) {
    const url = new URL(form.action, location.origin);
    for (const [name, value] of new FormData(form)) {
      if (name === 'page' || value === '' || defaults[name] === value) continue;
      url.searchParams.append(name, value);
    }
    return url;
  }

  function sameScope(url) {
    const form = liveForm();
    return form && url.origin === location.origin && url.pathname === new URL(form.action, location.origin).pathname;
  }

  async function load(url, { push = true, keepForm = true } = {}) {
    controller?.abort();
    controller = new AbortController();
    const current = liveForm();
    main.setAttribute('aria-busy', 'true');
    main.classList.add('is-filtering');
    try {
      const response = await fetch(url, {
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal: controller.signal,
      });
      if (!response.ok || response.redirected) throw new Error('load');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const next = doc.querySelector('#main-content');
      if (!next) throw new Error('load');

      // Keep the form the user is working in; take everything else from the server.
      const active = document.activeElement;
      const caret = active && 'selectionStart' in active ? [active.selectionStart, active.selectionEnd] : null;
      const nextForm = next.querySelector('form[data-live-filter]');
      if (keepForm && current && nextForm) {
        current.querySelector('.filter-active')?.remove();
        nextForm.replaceWith(current);
      }
      // Moving (not cloning) the parsed nodes keeps the live form and its listeners,
      // even when it sits deep inside the board markup.
      main.replaceChildren(...next.childNodes);

      if (push) history.pushState({ taskFilter: true }, '', url);
      else history.replaceState({ taskFilter: true }, '', url);
      document.title = doc.title || document.title;
      window.WorkUI?.mount(main);

      if (active && main.contains(active)) {
        active.focus({ preventScroll: true });
        if (caret) try { active.setSelectionRange(...caret); } catch { /* not a text input */ }
      }
    } catch (error) {
      if (error.name === 'AbortError') return;
      location.assign(url); // fall back to a normal page load
    } finally {
      main.removeAttribute('aria-busy');
      main.classList.remove('is-filtering');
    }
  }

  function applyNow(push = true) {
    clearTimeout(typingTimer);
    const form = liveForm();
    if (form) load(urlFromForm(form), { push });
  }

  main.addEventListener('change', (event) => {
    const field = event.target;
    if (!field.form?.matches('[data-live-filter]') || field.type === 'search') return;
    applyNow(true);
  });

  main.addEventListener('input', (event) => {
    const field = event.target;
    if (!field.form?.matches('[data-live-filter]') || field.type !== 'search') return;
    clearTimeout(typingTimer);
    // Typing replaces the history entry so Back does not step through every letter.
    typingTimer = setTimeout(() => applyNow(false), TYPE_DELAY);
  });

  // Enter in the search box (or the no-JS submit button).
  main.addEventListener('submit', (event) => {
    if (!event.target.matches('form[data-live-filter]')) return;
    event.preventDefault();
    applyNow(true);
  }, true);

  // Preset chips, reset link and pagination load in place.
  main.addEventListener('click', (event) => {
    const link = event.target.closest('[data-live-links] a, a[data-filter-reset], .task-pagination a, .jira-pagination a');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
    const url = new URL(link.href, location.href);
    if (!sameScope(url)) return;
    event.preventDefault();
    // These links set the whole filter, so the form is rebuilt from the server.
    const isPage = link.closest('.task-pagination, .jira-pagination');
    load(url, { push: true, keepForm: Boolean(isPage) });
  });

  window.addEventListener('popstate', () => {
    if (liveForm()) load(location.href, { push: false, keepForm: false });
  });
})();
