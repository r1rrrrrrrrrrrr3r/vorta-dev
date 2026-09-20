<?php
/**
 * public/ui_head.php
 *
 * Include inside <head>, AFTER the output.css link. Links the theme/layout stylesheet
 * and applies both preferences to <html> before first paint, so there is no flash of
 * the wrong theme or a navbar briefly appearing when sidebar is chosen.
 *
 * Preference resolution order: localStorage (instant, per device) -> the user's saved
 * database value -> default. Two attributes are set:
 *
 *   data-theme      light | dark   (resolved; 'system' follows the OS)
 *   data-theme-pref light | dark | system  (what the user actually picked)
 *   data-nav        navbar | sidebar
 */
$uiTheme = $_SESSION['user']['theme'] ?? '';
$uiLayout = $_SESSION['user']['nav_layout'] ?? '';
?>
<link rel="stylesheet" href="css/theme.css">
<script>
  (function () {
    var serverTheme = <?= json_encode($uiTheme ?: null) ?>;
    var serverLayout = <?= json_encode($uiLayout ?: null) ?>;

    var THEMES = ['light', 'dark', 'system'];
    var LAYOUTS = ['navbar', 'sidebar'];

    function read(key) {
      try { return localStorage.getItem(key); } catch (e) { return null; }
    }
    function write(key, value) {
      try { localStorage.setItem(key, value); } catch (e) {}
    }

    var themePref = read('vorta-theme') || serverTheme || 'system';
    if (THEMES.indexOf(themePref) === -1) themePref = 'system';

    var layout = read('vorta-nav') || serverLayout || 'navbar';
    if (LAYOUTS.indexOf(layout) === -1) layout = 'navbar';

    var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function resolveTheme(pref) {
      if (pref === 'system') return (mql && mql.matches) ? 'dark' : 'light';
      return pref;
    }

    var root = document.documentElement;
    root.setAttribute('data-theme-pref', themePref);
    root.setAttribute('data-theme', resolveTheme(themePref));
    root.setAttribute('data-nav', layout);

    function persist(body) {
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
          theme: root.getAttribute('data-theme'),
          layout: root.getAttribute('data-nav')
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
      getLayout: function () {
        return root.getAttribute('data-nav') || 'navbar';
      },
      setTheme: function (pref, persistIt) {
        if (THEMES.indexOf(pref) === -1) return;
        root.setAttribute('data-theme-pref', pref);
        root.setAttribute('data-theme', resolveTheme(pref));
        write('vorta-theme', pref);
        emit();
        if (persistIt !== false) persist('theme=' + encodeURIComponent(pref));
      },
      setLayout: function (value, persistIt) {
        if (LAYOUTS.indexOf(value) === -1) return;
        root.setAttribute('data-nav', value);
        write('vorta-nav', value);
        // Leaving sidebar mode must not strand the off-canvas "open" state.
        var shell = document.querySelector('.vorta-shell');
        if (shell) shell.classList.remove('is-open');
        var backdrop = document.querySelector('.vorta-backdrop');
        if (backdrop) backdrop.classList.remove('is-open');
        emit();
        if (persistIt !== false) persist('nav_layout=' + encodeURIComponent(value));
      }
    };

    // Follow the OS live while the preference is 'system'.
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
