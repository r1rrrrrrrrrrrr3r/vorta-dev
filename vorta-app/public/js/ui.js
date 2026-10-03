(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  // ---------- Toast ----------
  function toast(message, opts = {}) {
    const { tone = 'ok', actionText, actionHref, timeout = 4000 } = opts;
    let region = $('#toasts');
    if (!region) {
      region = document.createElement('div');
      region.id = 'toasts';
      region.className = 'toast-region';
      region.setAttribute('aria-live', 'polite');
      document.body.appendChild(region);
    }
    const el = document.createElement('div');
    el.className = 'toast' + (tone === 'bad' ? ' toast-bad' : '');
    el.setAttribute('role', tone === 'bad' ? 'alert' : 'status');
    const text = document.createElement('span');
    text.textContent = message;
    el.appendChild(text);
    if (actionText && actionHref) {
      const a = document.createElement('a');
      a.className = 'toast-action';
      a.href = actionHref;
      a.textContent = actionText;
      el.appendChild(a);
    }
    region.appendChild(el);
    setTimeout(() => el.remove(), timeout);
    return el;
  }

  // ---------- Confirm dialog ----------
  let confirmEl = null;
  function confirmDialog({ title, message = '', confirmText = 'Confirm', cancelText = 'Cancel', tone = 'primary' } = {}) {
    if (!confirmEl) {
      confirmEl = document.createElement('dialog');
      confirmEl.className = 'dialog';
      confirmEl.setAttribute('aria-labelledby', 'confirm-title');
      confirmEl.innerHTML =
        '<div class="dialog-header"><h2 class="dialog-title" id="confirm-title"></h2></div>' +
        '<div class="dialog-body"><p class="dialog-text" data-confirm-msg></p></div>' +
        '<div class="dialog-footer"><button type="button" class="btn btn-secondary" data-cancel></button>' +
        '<button type="button" class="btn" data-ok></button></div>';
      document.body.appendChild(confirmEl);
    }
    const d = confirmEl;
    $('#confirm-title', d).textContent = title || 'Are you sure?';
    const msg = $('[data-confirm-msg]', d);
    msg.textContent = message;
    msg.hidden = !message;
    const ok = $('[data-ok]', d);
    const cancel = $('[data-cancel]', d);
    ok.textContent = confirmText;
    ok.className = 'btn ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary');
    cancel.textContent = cancelText;
    const opener = document.activeElement;

    return new Promise(resolve => {
      let result = false;
      const finish = () => {
        ok.onclick = cancel.onclick = d.onclick = null;
        d.removeEventListener('close', finish);
        if (opener && opener.focus && document.contains(opener)) opener.focus();
        resolve(result);
      };
      ok.onclick = () => { result = true; d.close(); };
      cancel.onclick = () => d.close();
      d.onclick = (e) => { if (e.target === d) d.close(); };
      d.addEventListener('close', finish);
      d.showModal();
      cancel.focus();
    });
  }

  // ---------- Dialog (form) ----------
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-dialog-open]');
    if (opener) {
      const d = document.getElementById(opener.dataset.dialogOpen);
      if (d && d.showModal) {
        e.preventDefault();
        d._opener = opener;
        d.showModal();
        const first = d.querySelector('input:not([type=hidden]):not([hidden]), select, textarea');
        if (first) first.focus();
      }
      return;
    }
    const closer = e.target.closest('[data-dialog-close]');
    if (closer) {
      const d = closer.closest('dialog');
      if (d) { e.preventDefault(); d.close(); }
      return;
    }
    if (e.target.tagName === 'DIALOG' && e.target.classList.contains('dialog') && e.target !== confirmEl) {
      // klik di backdrop: target adalah dialog itu sendiri
      const r = e.target.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
    }
  });
  document.addEventListener('close', (e) => {
    const d = e.target;
    if (d && d.tagName === 'DIALOG' && d._opener && document.contains(d._opener)) d._opener.focus();
  }, true);

  // ---------- Drawer ----------
  let activeDrawer = null;
  let drawerOpener = null;

  function showDrawer(el) {
    const scrim = $('[data-drawer-scrim]');
    if (activeDrawer && activeDrawer !== el) hideDrawer(true);
    drawerOpener = document.activeElement;
    activeDrawer = el;
    el.hidden = false;
    if (scrim) scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('is-open')));
    const closeBtn = $('[data-drawer-close]', el);
    setTimeout(() => {
      const focusTarget = el.querySelector('input:not([type=hidden]), select, textarea') || closeBtn;
      if (focusTarget && el.id !== 'drawer') focusTarget.focus();
      else if (closeBtn) closeBtn.focus();
    }, 60);
  }

  function hideDrawer(silent) {
    const el = activeDrawer;
    if (!el) return;
    const scrim = $('[data-drawer-scrim]');
    el.classList.remove('is-open');
    if (scrim) scrim.hidden = true;
    document.body.style.overflow = '';
    activeDrawer = null;
    const done = () => { if (!el.classList.contains('is-open')) el.hidden = true; };
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) done();
    else setTimeout(done, 220);
    el.dispatchEvent(new CustomEvent('drawer:close'));
    if (!silent && drawerOpener && drawerOpener.focus && document.contains(drawerOpener)) drawerOpener.focus();
  }

  const drawer = {
    open({ eyebrow = '', title = '', url = null, html = '', footer = '' } = {}) {
      const el = $('#drawer');
      if (!el) return;
      $('[data-drawer-eyebrow]', el).textContent = eyebrow;
      $('[data-drawer-title]', el).textContent = title;
      const body = $('[data-drawer-body]', el);
      $('[data-drawer-footer]', el).innerHTML = footer;
      if (url) {
        body.innerHTML = '<p class="text-muted">Loading…</p>';
        fetch(url, { credentials: 'same-origin' })
          .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
          .then(t => { body.innerHTML = t; })
          .catch(() => { body.innerHTML = '<div class="alert alert-bad">Couldn\'t load this. Try again.</div>'; });
      } else {
        body.innerHTML = html;
      }
      showDrawer(el);
    },
    openPanel(id) {
      const el = document.getElementById(id);
      if (el) showDrawer(el);
    },
    close() { hideDrawer(false); },
  };

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-drawer-close]') || e.target.matches('[data-drawer-scrim]')) {
      e.preventDefault();
      drawer.close();
      return;
    }
    const panelBtn = e.target.closest('[data-drawer-panel]');
    if (panelBtn) {
      e.preventDefault();
      const el = document.getElementById(panelBtn.dataset.drawerPanel);
      if (el) el.dispatchEvent(new CustomEvent('drawer:mode', { detail: { mode: panelBtn.dataset.mode || 'create', trigger: panelBtn } }));
      drawer.openPanel(panelBtn.dataset.drawerPanel);
      return;
    }
    const opener = e.target.closest('[data-drawer-url]');
    if (opener && !e.target.closest('a, button, .menu, input, select, label')) {
      openRowDrawer(opener);
    }
  });

  function openRowDrawer(row) {
    drawer.open({ url: row.dataset.drawerUrl, title: row.dataset.drawerTitle || '', eyebrow: row.dataset.drawerEyebrow || '' });
  }

  document.addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-drawer-url]')) {
      e.preventDefault();
      openRowDrawer(e.target);
    }
  });

  // ---------- Menu ----------
  function closeMenus(except) {
    $$('[data-menu-trigger][aria-expanded="true"]').forEach(t => {
      const m = document.getElementById(t.getAttribute('aria-controls'));
      if (m && m !== except) { m.hidden = true; t.setAttribute('aria-expanded', 'false'); }
    });
  }

  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-menu-trigger]');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      const menu = document.getElementById(trigger.getAttribute('aria-controls'));
      if (!menu) return;
      const willOpen = menu.hidden;
      closeMenus(menu);
      menu.hidden = !willOpen;
      trigger.setAttribute('aria-expanded', String(willOpen));
      if (willOpen) {
        positionMenu(trigger, menu);
        const first = menu.querySelector('.menu-item:not([aria-disabled="true"])');
        if (first) first.focus();
      }
      return;
    }
    if (!e.target.closest('.menu')) closeMenus();
    else if (e.target.closest('.menu-item') && !e.target.closest('[aria-disabled="true"]') && !e.target.closest('[data-theme-set]')) {
      setTimeout(() => closeMenus(), 0);
    }
  });

  function positionMenu(trigger, menu) {
    // Menu di sidebar/topbar sudah diposisikan lewat CSS
    if (menu.closest('.sidebar-user') || menu.closest('.topbar')) return;
    // position:fixed supaya tidak terpotong oleh .table-wrap (overflow)
    const tr = trigger.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.top = (tr.bottom + 4) + 'px';
    menu.style.right = Math.max(8, document.documentElement.clientWidth - tr.right) + 'px';
    menu.style.left = 'auto';
    const mr = menu.getBoundingClientRect();
    if (mr.bottom > window.innerHeight - 8) {
      menu.style.top = Math.max(8, tr.top - mr.height - 4) + 'px';
    }
  }
  window.addEventListener('scroll', () => {
    const t = $('[data-menu-trigger][aria-expanded="true"]');
    if (t && !t.closest('.sidebar-user') && !t.closest('.topbar')) closeMenus();
  }, true);

  document.addEventListener('keydown', (e) => {
    const menu = e.target.closest && e.target.closest('.menu');
    if (menu && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      const items = $$('.menu-item', menu);
      const i = items.indexOf(e.target);
      const next = items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];
      if (next) next.focus();
    }
    if (e.key === 'Escape') {
      const openTrigger = $('[data-menu-trigger][aria-expanded="true"]');
      if (openTrigger) { closeMenus(); openTrigger.focus(); return; }
      if (activeDrawer && !document.querySelector('dialog[open]')) { drawer.close(); return; }
      const sb = $('#sidebar.is-open');
      if (sb) closeSidebar();
    }
  });

  document.addEventListener('click', (e) => {
    const disabled = e.target.closest('.menu-item[aria-disabled="true"]');
    if (disabled) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  // ---------- Sidebar (mobile) ----------
  function openSidebar() {
    const sb = $('#sidebar');
    const scrim = $('.sidebar-scrim');
    if (!sb) return;
    sb.classList.add('is-open');
    if (scrim) scrim.hidden = false;
    const first = $('.nav-link', sb);
    if (first) first.focus();
  }
  function closeSidebar() {
    const sb = $('#sidebar');
    const scrim = $('.sidebar-scrim');
    if (!sb) return;
    sb.classList.remove('is-open');
    if (scrim) scrim.hidden = true;
    const btn = $('[data-sidebar-open]');
    if (btn) btn.focus();
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-open]')) { openSidebar(); return; }
    if (e.target.closest('[data-sidebar-close]')) closeSidebar();
  });

  // ---------- Period picker ----------
  document.addEventListener('click', (e) => {
    const label = e.target.closest('.period-label');
    if (!label) return;
    const input = label.querySelector('input');
    if (input && input.showPicker) {
      e.preventDefault();
      try { input.showPicker(); } catch (err) { input.focus(); }
    }
  });
  document.addEventListener('keydown', (e) => {
    const label = e.target.closest && e.target.closest('.period-label');
    if (label && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); label.click(); }
  });
  document.addEventListener('change', (e) => {
    if (e.target.matches('.period-label input') && e.target.value) e.target.form.submit();
  });
  $$('.period-label').forEach(l => { l.tabIndex = 0; l.setAttribute('role', 'button'); });

  // ---------- Confirm links/forms ----------
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-confirm], button[data-confirm]');
    if (!a || a.closest('form[data-confirm]')) return;
    e.preventDefault();
    confirmDialog({
      title: a.dataset.confirm,
      message: a.dataset.confirmMessage,
      confirmText: a.dataset.confirmText,
      tone: a.dataset.confirmTone,
    }).then(ok => {
      if (!ok) return;
      if (a.tagName === 'A') window.location = a.href;
      else if (a.form) a.form.submit();
    });
  });
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!f.matches('form[data-confirm]') || f._confirmed) return;
    e.preventDefault();
    confirmDialog({
      title: f.dataset.confirm,
      message: f.dataset.confirmMessage,
      confirmText: f.dataset.confirmText,
      tone: f.dataset.confirmTone,
    }).then(ok => {
      if (!ok) return;
      f._confirmed = true;
      // pertahankan nilai tombol submit (mis. name="check_out")
      const sub = e.submitter;
      if (sub && sub.name) {
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = sub.name; h.value = sub.value;
        f.appendChild(h);
      }
      f.submit();
    });
  });

  // ---------- Theme menu ----------
  function syncTheme() {
    const pref = window.VortaUI ? window.VortaUI.getThemePreference() : 'system';
    $$('[data-theme-set]').forEach(b => {
      const on = b.dataset.themeSet === pref;
      if (b.getAttribute('role') === 'menuitemradio') b.setAttribute('aria-checked', String(on));
      else { b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', String(on)); }
    });
  }
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-theme-set]');
    if (!b || !window.VortaUI) return;
    e.preventDefault();
    window.VortaUI.setTheme(b.dataset.themeSet);
  });
  document.addEventListener('vorta:uichange', syncTheme);
  syncTheme();

  // ---------- Live search ----------
  // Search inputs inside GET forms submit themselves while typing (debounced);
  // focus and caret are restored after the page reloads.
  const LIVE_KEY = 'vorta:live-search';
  $$('form[method="get" i] input[type="search"]').forEach(input => {
    let timer = null;
    let composing = false;
    const initial = input.value;
    const submit = () => {
      if (input.value.trim() === initial.trim()) return;
      try {
        sessionStorage.setItem(LIVE_KEY, JSON.stringify({ id: input.id, pos: input.selectionStart }));
      } catch (e) { /* storage unavailable */ }
      input.form.submit();
    };
    input.addEventListener('compositionstart', () => { composing = true; });
    input.addEventListener('compositionend', () => { composing = false; input.dispatchEvent(new Event('input')); });
    input.addEventListener('input', () => {
      if (composing) return;
      clearTimeout(timer);
      timer = setTimeout(submit, 400);
    });
    input.form.addEventListener('submit', () => clearTimeout(timer));
  });
  try {
    const saved = JSON.parse(sessionStorage.getItem(LIVE_KEY) || 'null');
    sessionStorage.removeItem(LIVE_KEY);
    const el = saved && document.getElementById(saved.id);
    if (el) {
      el.focus();
      const pos = Math.min(saved.pos ?? el.value.length, el.value.length);
      el.setSelectionRange(pos, pos);
    }
  } catch (e) { /* storage unavailable */ }

  // ---------- Flash ----------
  function showFlash() {
    (window.VORTA_FLASH || []).forEach(f => toast(f.message, { tone: f.tone }));
    window.VORTA_FLASH = [];
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showFlash);
  else showFlash();

  window.Vorta = { toast, confirm: confirmDialog, drawer, escapeHtml };
})();
