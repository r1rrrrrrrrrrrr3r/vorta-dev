<?php
require_once __DIR__ . '/../lib/auth.php';

/**
 * Navigation is defined ONCE here and rendered once. Whether it appears as a top
 * navbar or a left sidebar is decided purely by CSS via <html data-nav="...">,
 * which is why switching layouts is instant and needs no page reload — and why
 * there is no second, drifting copy of the menu for mobile.
 */
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [];
if (isset($_SESSION['user'])) {
    // Inline SVG paths keep the nav dependency-free (no icon font needed).
    $icons = [
        'grid'     => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        'docs'     => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'database' => 'M4 7c0-1.657 3.582-3 8-3s8 1.343 8 3-3.582 3-8 3-8-1.343-8-3zm0 0v10c0 1.657 3.582 3 8 3s8-1.343 8-3V7',
        'userx'    => 'M17 16l4-4m0 0l-4-4m4 4H7M12 20a8 8 0 100-16 8 8 0 000 16z',
        'clock'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'plus'     => 'M12 4v16m8-8H4',
        'user'     => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    ];

    $navItems[] = ['href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => $icons['grid']];

    if ($_SESSION['user']['role'] === 'admin') {
        $navItems[] = ['href' => 'admin_reports.php',        'label' => 'All Reports',       'icon' => $icons['docs']];
        $navItems[] = ['href' => 'admin_attendance.php',     'label' => 'Attendance Report', 'icon' => $icons['calendar']];
        $navItems[] = ['href' => 'admin_master_data.php',    'label' => 'Master Data',       'icon' => $icons['database']];
        $navItems[] = ['href' => 'admin_not_attendance.php', 'label' => 'Not Absent',        'icon' => $icons['userx']];
    } else {
        $navItems[] = ['href' => 'attendance.php', 'label' => 'Attendance',   'icon' => $icons['clock']];
        $navItems[] = ['href' => 'report_form.php', 'label' => 'Report Input', 'icon' => $icons['plus']];
        $navItems[] = ['href' => 'my_reports.php',  'label' => 'My Report',    'icon' => $icons['docs']];
        $navItems[] = ['href' => 'profile.php',     'label' => 'My Profile',   'icon' => $icons['user']];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vorta Productivity Tracker</title>
  <link rel="stylesheet" href="css/output.css">
  <?php include __DIR__ . '/ui_head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">

  <header class="vorta-shell" id="vortaShell">
    <div class="vorta-shell-inner">
      <a href="<?= isset($_SESSION['user']) ? 'dashboard.php' : 'index.php' ?>" class="vorta-brand">
        <!-- Single source of truth: the project-root images/ folder, the one you edit.
             Do NOT keep a second copy under public/images — it silently goes stale. -->
        <img src="../images/vorta.png" alt="Vorta logo">
        <span class="vorta-brand-text">
          <span class="vorta-brand-title">Vorta</span>
          <span class="vorta-brand-sub">Productivity Tracker</span>
        </span>
      </a>

      <?php if (isset($_SESSION['user'])): ?>
        <button type="button" class="vorta-burger" id="vortaBurger" aria-label="Toggle menu">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path id="vortaBurgerIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>

        <div class="vorta-nav-group" id="vortaNavGroup">
          <nav class="vorta-nav">
            <?php foreach ($navItems as $item): ?>
              <a href="<?= $item['href'] ?>"
                class="vorta-nav-link <?= $currentPage === $item['href'] ? 'is-active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"></path>
                </svg>
                <span><?= htmlspecialchars($item['label']) ?></span>
              </a>
            <?php endforeach; ?>
          </nav>

          <a href="logout.php" class="vorta-logout">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            <span>Logout</span>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <script>
    (function () {
      const shell = document.getElementById('vortaShell');
      const burger = document.getElementById('vortaBurger');
      const burgerIcon = document.getElementById('vortaBurgerIcon');

      const OPEN = 'M6 18L18 6M6 6l12 12';
      const CLOSED = 'M4 6h16M4 12h16M4 18h16';

      function close() {
        shell?.classList.remove('is-open');
        burgerIcon?.setAttribute('d', CLOSED);
      }

      // On narrow screens both layouts collapse into this top bar, so one
      // burger handles both cases.
      burger?.addEventListener('click', function () {
        shell.classList.toggle('is-open');
        burgerIcon?.setAttribute('d', shell.classList.contains('is-open') ? OPEN : CLOSED);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
      });

      document.querySelectorAll('.vorta-nav-link').forEach(function (link) {
        link.addEventListener('click', close);
      });

      // Switching layout from Account Settings must not leave the menu expanded.
      document.addEventListener('vorta:uichange', close);
    })();
  </script>
</body>

</html>
