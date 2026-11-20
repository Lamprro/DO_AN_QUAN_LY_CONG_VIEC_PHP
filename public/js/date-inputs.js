// One date format on every screen: dd/mm/yyyy and dd/mm/yyyy HH:mm.
//
// Native <input type="date|datetime-local"> shows the BROWSER's locale (an en-US browser shows
// 12/05/2025 = 5 Dec), which is easy to misread. Each such input gets a text box beside it:
// - the text box shows / accepts dd/mm/yyyy (or dd/mm/yyyy HH:mm, 24h);
// - the native input stays in the form (hidden) and still sends ISO values to the server,
//   so controllers, validation and other scripts reading input.value are unchanged;
// - the calendar button opens the native picker; values set from code are mirrored too.
(() => {
  'use strict';
  const native = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  const pad = (n) => String(n).padStart(2, '0');
  const TYPES = 'input[type="date"], input[type="datetime-local"]';

  function toDisplay(type, iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(iso || '');
    if (!m) return '';
    const day = `${m[3]}/${m[2]}/${m[1]}`;
    return type === 'datetime-local' && m[4] ? `${day} ${m[4]}:${m[5]}` : day;
  }

  // Returns '' for empty, an ISO string when valid, null when not (yet) valid.
  function toIso(type, text) {
    const value = text.trim();
    if (!value) return '';
    const m = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})(?:[ ,T]+(\d{1,2})[:h](\d{2}))?$/.exec(value);
    if (!m) return null;
    const [d, mo, y] = [Number(m[1]), Number(m[2]), Number(m[3])];
    const check = new Date(y, mo - 1, d);
    if (check.getFullYear() !== y || check.getMonth() !== mo - 1 || check.getDate() !== d) return null;
    const date = `${y}-${pad(mo)}-${pad(d)}`;
    if (type === 'date') return m[4] === undefined ? date : null;
    if (m[4] === undefined) return null;
    const [h, mi] = [Number(m[4]), Number(m[5])];
    if (h > 23 || mi > 59) return null;
    return `${date}T${pad(h)}:${pad(mi)}`;
  }

  function enhance(input) {
    if (input.dataset.dateUi || input.closest('[data-date-ui-skip]')) return;
    input.dataset.dateUi = '1';
    const type = input.type;
    const label = type === 'date' ? 'dd/mm/yyyy' : 'dd/mm/yyyy hh:mm';

    const wrap = document.createElement('span');
    wrap.className = 'date-ui';
    const text = document.createElement('input');
    text.type = 'text';
    text.className = 'date-ui-text';
    text.inputMode = 'numeric';
    text.autocomplete = 'off';
    text.placeholder = label;
    text.title = `Định dạng ${label}`;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'date-ui-button';
    button.setAttribute('aria-label', 'Mở lịch chọn ngày');
    button.textContent = '📅';

    input.before(wrap);
    wrap.append(text, button, input);
    input.classList.add('date-ui-native');
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');

    // Labels, required, errors and disabled now live on the visible box.
    const labelled = input.id && document.querySelector(`label[for="${CSS.escape(input.id)}"]`);
    if (labelled) text.setAttribute('aria-label', `${labelled.textContent.trim()} (${label})`);
    for (const attr of ['aria-invalid', 'aria-describedby']) {
      if (input.hasAttribute(attr)) text.setAttribute(attr, input.getAttribute(attr));
    }
    const syncState = () => {
      text.disabled = input.disabled;
      text.readOnly = input.readOnly;
      button.disabled = input.disabled || input.readOnly;
      if (input.required) { text.required = true; input.required = false; }
    };
    syncState();
    new MutationObserver(syncState).observe(input, { attributes: true, attributeFilter: ['disabled', 'readonly', 'required'] });
    input.addEventListener('focus', () => text.focus());

    const show = () => { text.value = toDisplay(type, native.get.call(input)); text.setCustomValidity(''); };
    // Mirror values assigned from code (presets, editors, form restore).
    Object.defineProperty(input, 'value', {
      configurable: true,
      get() { return native.get.call(this); },
      set(v) { native.set.call(this, v); show(); },
    });
    input.addEventListener('input', show);
    input.addEventListener('change', show);
    input.form?.addEventListener('reset', () => setTimeout(show));
    show();

    const validate = (iso) => {
      let message = '';
      if (iso === null) message = `Nhập đúng định dạng ${label}.`;
      else if (iso && input.min && iso < input.min) message = `Không được trước ${toDisplay(type, input.min)}.`;
      else if (iso && input.max && iso > input.max) message = `Không được sau ${toDisplay(type, input.max)}.`;
      text.setCustomValidity(message);
      return message === '';
    };
    const push = (iso, event) => {
      if (native.get.call(input) === iso && event === 'input') return;
      native.set.call(input, iso);
      input.dispatchEvent(new Event(event, { bubbles: true }));
    };
    text.addEventListener('input', () => {
      const iso = toIso(type, text.value);
      if (validate(iso)) push(iso, 'input');
    });
    text.addEventListener('change', () => {
      const iso = toIso(type, text.value);
      if (!validate(iso)) { text.reportValidity(); return; }
      push(iso, 'change');
      text.value = toDisplay(type, iso);
    });

    button.addEventListener('click', () => {
      try { input.showPicker(); } catch { text.focus(); }
    });
  }

  const scan = (root) => root.querySelectorAll?.(TYPES).forEach(enhance);
  scan(document);
  new MutationObserver((records) => {
    for (const record of records) {
      record.addedNodes.forEach((node) => {
        if (node.nodeType !== 1) return;
        if (node.matches(TYPES)) enhance(node);
        else scan(node);
      });
    }
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
