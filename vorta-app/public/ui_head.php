<?php
require_once __DIR__ . '/../lib/csrf.php';

$uiTheme = $_SESSION['user']['theme'] ?? '';
$uiCsrfToken = csrf_token();
?>
<?= csrf_meta() ?>
<script>
  // Semua fetch POST same-origin otomatis membawa header X-CSRF-Token (dibaca dari meta, jadi tetap benar setelah navigasi Turbo).
  (function () {
    if (window.__vortaCsrfFetch || !window.fetch) return;
    window.__vortaCsrfFetch = true;
    var nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      init = init || {};
      var method = String(init.method || (input && input.method) || 'GET').toUpperCase();
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var sameOrigin = true;
      try { sameOrigin = new URL(url, location.href).origin === location.origin; } catch (e) {}
      var meta = document.querySelector('meta[name="csrf-token"]');
      if (method !== 'GET' && method !== 'HEAD' && sameOrigin && meta) {
        var headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
        if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', meta.content);
        init = Object.assign({}, init, { headers: headers });
      }
      return nativeFetch(input, init);
    };
  })();
</script>
<script>
  (function () {
    var serverTheme = <?= json_encode($uiTheme ?: null) ?>;
    // Turbo menjalankan ulang script ini kalau isinya berubah (mis. setelah login / tema berubah):
    // cukup terapkan tema server, jangan pasang listener dua kali.
    if (window.VortaUI) {
      if (serverTheme) window.VortaUI.setTheme(serverTheme, false);
      return;
    }
    var THEMES = ['light', 'dark', 'system'];

    function read(key) {
      try { return localStorage.getItem(key); } catch (e) { return null; }
    }
    function write(key, value) {
      try { localStorage.setItem(key, value); } catch (e) {}
    }

    var themePref = serverTheme || read('vorta-theme') || 'system';
    if (THEMES.indexOf(themePref) === -1) themePref = 'system';

    var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function resolveTheme(pref) {
      if (pref === 'system') return (mql && mql.matches) ? 'dark' : 'light';
      return pref;
    }

    var root = document.documentElement;
    root.setAttribute('data-theme-pref', themePref);
    root.setAttribute('data-theme', resolveTheme(themePref));

    function persist(body) {
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      body += '&csrf_token=' + encodeURIComponent(csrfMeta ? csrfMeta.content : <?= json_encode($uiCsrfToken) ?>);
      fetch('save_preferences.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: body
      }).catch(function () {});
    }

    function emit() {
      document.dispatchEvent(new CustomEvent('vorta:uichange', {
        detail: {
          themePreference: root.getAttribute('data-theme-pref'),
          theme: root.getAttribute('data-theme')
        }
      }));
    }

    window.VortaUI = {
      getThemePreference: function () {
        return root.getAttribute('data-theme-pref') || 'system';
      },
      getTheme: function () {
        return root.getAttribute('data-theme') || 'light';
      },
      setTheme: function (pref, persistIt) {
        if (THEMES.indexOf(pref) === -1) return;
        root.setAttribute('data-theme-pref', pref);
        root.setAttribute('data-theme', resolveTheme(pref));
        write('vorta-theme', pref);
        emit();
        if (persistIt !== false) persist('theme=' + encodeURIComponent(pref));
      }
    };

    if (mql) {
      var onOsChange = function () {
        if (root.getAttribute('data-theme-pref') === 'system') {
          root.setAttribute('data-theme', resolveTheme('system'));
          emit();
        }
      };
      if (mql.addEventListener) mql.addEventListener('change', onOsChange);
      else if (mql.addListener) mql.addListener(onOsChange);
    }
  })();
</script>
