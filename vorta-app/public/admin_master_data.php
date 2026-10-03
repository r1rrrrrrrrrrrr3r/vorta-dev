<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';
require_admin();

$tabs = [
  'users'      => ['Users', 'users.php'],
  'employees'  => ['Employees', 'employees.php'],
  'work_force' => ['Work forces', 'work_force.php'],
  'job_type'   => ['Job types', 'job_type.php'],
];

$active_tab = $_GET['tab'] ?? 'users';
if ($active_tab === 'settings') {
  header('Location: settings.php');
  exit;
}
if (!isset($tabs[$active_tab])) {
  $active_tab = 'users';
}

// Tab file boleh header()+exit karena output masih di-buffer
ob_start();
include __DIR__ . '/admin_master_data/' . $tabs[$active_tab][1];
$tabHtml = ob_get_clean();

$pageTitle = 'Master data';
$activeNav = 'master_data';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Master data', 'People and lists used in reports') ?>

<nav class="tabs" aria-label="Master data">
  <?php foreach ($tabs as $key => [$label]): ?>
    <a class="tab<?= $active_tab === $key ? ' is-active' : '' ?>" href="?tab=<?= $key ?>"<?= $active_tab === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
  <?php endforeach; ?>
</nav>

<?= $tabHtml ?>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
