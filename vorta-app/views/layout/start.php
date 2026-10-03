<?php
/**
 * Layout shell. Expects (optional): $pageTitle, $activeNav, $pageScripts, $layout ('bare').
 */
require_once __DIR__ . '/../../lib/ui.php';

$pageTitle   = $pageTitle ?? 'Vorta';
$activeNav   = $activeNav ?? '';
$pageScripts = $pageScripts ?? [];
$layout      = $layout ?? 'app';

$themePref = $_SESSION['user']['theme'] ?? 'system';
if (!in_array($themePref, ['light', 'dark', 'system'], true)) {
    $themePref = 'system';
}
$themeResolved = $themePref === 'dark' ? 'dark' : 'light';

$currentUser = $_SESSION['user'] ?? null;
$role = $currentUser['role'] ?? '';
$hasTabbar = $layout !== 'bare' && $currentUser && !is_admin_role($role);

$nav = [];
if ($layout !== 'bare' && $currentUser) {
    if (is_admin_role($role)) {
        require_once __DIR__ . '/../../lib/attendance.php';
        $notCheckedInToday = isset($pdo) ? attendance_missing_count($pdo, date('Y-m-d')) : 0;
        $nav = [
            ['key' => 'dashboard',   'href' => 'dashboard.php',         'label' => 'Dashboard',   'icon' => 'squares'],
        ];
        if ($role === 'platform_admin') {
            $nav[] = ['key' => 'companies', 'href' => 'platform_companies.php', 'label' => 'Companies', 'icon' => 'building-office'];
        }
        $nav = array_merge($nav, [
            ['group' => 'Data'],
            ['key' => 'reports',     'href' => 'admin_reports.php',     'label' => 'Reports',     'icon' => 'document-text'],
            ['key' => 'attendance',  'href' => 'admin_attendance.php',  'label' => 'Attendance',  'icon' => 'clock', 'badge' => $notCheckedInToday],
            ['key' => 'master_data', 'href' => 'admin_master_data.php', 'label' => 'Master data', 'icon' => 'circle-stack'],
            ['group' => 'System'],
            ['key' => 'settings',    'href' => 'settings.php',          'label' => 'Settings',    'icon' => 'cog'],
        ]);
    } else {
        $nav = [
            ['key' => 'today',      'href' => 'dashboard.php',  'label' => 'Today',      'icon' => 'home'],
            ['key' => 'my_reports', 'href' => 'my_reports.php', 'label' => 'My reports', 'icon' => 'document-text'],
            ['key' => 'attendance', 'href' => 'attendance.php', 'label' => 'Attendance', 'icon' => 'clock'],
        ];
    }
}

$userName  = $currentUser['name'] ?? '';
$userEmail = $currentUser['email'] ?? '';
$isAccountPage = $activeNav === 'account';

/** User menu markup (shared by sidebar button and topbar avatar). */
$renderUserMenu = function (string $id) use ($userName, $userEmail, $themePref): string {
    $themes = ['light' => ['Light', 'sun'], 'dark' => ['Dark', 'moon'], 'system' => ['System', 'computer-desktop']];
    $html = '<div class="menu" id="' . $id . '" role="menu" hidden>'
        . '<div class="menu-head"><strong>' . e($userName) . '</strong><span>' . e($userEmail) . '</span></div>'
        . '<div class="menu-sep"></div>'
        . '<a class="menu-item" role="menuitem" href="account.php">' . icon('user') . 'Account</a>'
        . '<div class="menu-label">Theme</div>';
    foreach ($themes as $value => [$label, $ic]) {
        $html .= '<button type="button" class="menu-item" role="menuitemradio" aria-checked="' . ($themePref === $value ? 'true' : 'false') . '" data-theme-set="' . $value . '">'
            . icon($ic) . $label . icon('check', 'menu-check size-4') . '</button>';
    }
    return $html . '<div class="menu-sep"></div>'
        . '<a class="menu-item" role="menuitem" href="logout.php" data-turbo="false">' . icon('arrow-right-on-rectangle') . 'Log out</a></div>';
};
?>
<!DOCTYPE html>
<html lang="en" data-theme-pref="<?= e($themePref) ?>" data-theme="<?= e($themeResolved) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · Vorta</title>
  <link rel="icon" href="images/vorta.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <?php $asset = fn(string $p): string => $p . '?v=' . @filemtime(__DIR__ . '/../../public/' . $p); ?>
  <meta name="turbo-prefetch" content="false">
  <meta name="turbo-refresh-method" content="morph">
  <meta name="turbo-refresh-scroll" content="preserve">
  <link rel="stylesheet" href="<?= e($asset('css/output.css')) ?>" data-turbo-track="reload">
  <?php include __DIR__ . '/../../public/ui_head.php'; ?>
  <script>window.vortaReady = window.vortaReady || function (fn) { if (window.Vorta) fn(); else (window.__vortaQ = window.__vortaQ || []).push(fn); };</script>
  <script src="<?= e($asset('js/vendor/turbo.js')) ?>" defer data-turbo-track="reload"></script>
  <script src="<?= e($asset('js/ui.js')) ?>" defer data-turbo-track="reload"></script>
  <script src="<?= e($asset('js/attendance.js')) ?>" defer data-turbo-track="reload"></script>
</head>
<body class="app<?= $hasTabbar ? ' has-tabbar' : '' ?>">
<a href="#main" class="skip-link">Skip to content</a>
<?php if ($layout === 'bare'): ?>
<main class="min-h-screen grid place-items-center p-4" id="main">
<?php else: ?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <a href="dashboard.php" class="sidebar-brand">
    <img src="images/vorta.png" alt="">
    <span><span class="sidebar-brand-title">Vorta</span><span class="sidebar-brand-sub">Productivity Tracker</span></span>
  </a>
  <nav>
    <?php foreach ($nav as $item): ?>
      <?php if (isset($item['group'])): ?>
        <div class="nav-group-label"><?= e($item['group']) ?></div>
      <?php else: $isActive = $activeNav === $item['key']; ?>
        <a href="<?= e($item['href']) ?>" class="nav-link<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
          <?php if (!empty($item['badge'])): ?><span class="nav-badge" title="<?= (int) $item['badge'] ?> not checked in today"><?= (int) $item['badge'] ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-user">
    <button type="button" class="sidebar-user-btn<?= $isAccountPage ? ' is-active' : '' ?>" data-menu-trigger aria-controls="user-menu-side" aria-expanded="false" aria-haspopup="menu">
      <span class="avatar"><?= e(initials($userName)) ?></span>
      <span class="min-w-0 flex-1"><span class="sidebar-user-name"><?= e($userName) ?></span><span class="sidebar-user-role"><?= e(role_label($role)) ?></span></span>
      <?= icon('chevron-up-down', 'size-4 opacity-70') ?>
    </button>
    <?= $renderUserMenu('user-menu-side') ?>
  </div>
</aside>
<div class="sidebar-scrim" data-sidebar-close hidden></div>
<div class="app-main">
  <header class="topbar lg:hidden">
    <button type="button" class="btn btn-ghost btn-icon" data-sidebar-open aria-label="Open menu" aria-controls="sidebar"><?= icon('bars-3', 'size-5') ?></button>
    <img src="images/vorta.png" alt="">
    <span class="topbar-title"><?= e($pageTitle) ?></span>
    <span class="flex-1"></span>
    <div class="relative">
      <button type="button" class="btn btn-ghost btn-icon" data-menu-trigger aria-controls="user-menu-top" aria-expanded="false" aria-haspopup="menu" aria-label="Account menu">
        <span class="avatar"><?= e(initials($userName)) ?></span>
      </button>
      <?= $renderUserMenu('user-menu-top') ?>
    </div>
  </header>
  <main class="page" id="main">
<?php endif; ?>
