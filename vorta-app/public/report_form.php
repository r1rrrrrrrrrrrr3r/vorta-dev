<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/reports.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$monthlyTarget = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
$stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
  die("Employee record not found.");
}

$employee_id = $employee['employee_id'];

$stmt = $pdo->query("SELECT workforce_id, workforce_name FROM work_force ORDER BY workforce_name");
$work_forces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT job_type_id, name FROM job_type ORDER BY name");
$job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT workforce_id FROM production_reports WHERE user_id = ? AND workforce_id IS NOT NULL ORDER BY report_date DESC, report_id DESC LIMIT 1");
$stmt->execute([$user_id]);
$lastWorkforceId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

$todayCount = report_count_on($pdo, (int) $user_id, date('Y-m-d'));

$report = null;
$jobTypeValueKey = 'job_type_id';
$acceptTypes = ['image/jpeg', 'image/png', 'image/webp'];

$pageTitle = 'New report';
$activeNav = 'my_reports';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('New report', null, '', ['href' => 'my_reports.php', 'label' => 'My reports']) ?>

<div class="grid gap-4 lg:grid-cols-[1fr_300px] items-start">
  <form action="save_report.php" method="POST" enctype="multipart/form-data" class="card">
    <?php include __DIR__ . '/../views/reports/form_fields.php'; ?>
    <div class="card-footer justify-end">
      <a class="btn btn-ghost" href="my_reports.php">Cancel</a>
      <button type="submit" class="btn btn-primary" data-turbo-submits-with="Saving…">Save report</button>
    </div>
  </form>

  <aside class="card">
    <div class="card-header"><h2 class="card-title">Today</h2><span class="card-meta">minimum <?= (int) $dailyMin ?></span></div>
    <div class="card-body grid gap-3">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="big-number"><?= $todayCount ?> <small>/ <?= (int) $dailyMin ?></small></div>
        <?php if ($todayCount >= $dailyMin): ?>
          <?= status_pill(null, 'ok', 'Complete') ?>
        <?php elseif ($todayCount > 0): ?>
          <?= status_pill(null, 'warn', ($dailyMin - $todayCount) . ' more needed') ?>
        <?php else: ?>
          <?= status_pill(null, 'bad', 'No reports yet') ?>
        <?php endif; ?>
      </div>
      <?= segbar(min($todayCount, $dailyMin), $dailyMin) ?>
      <p class="help m-0">Monthly target <?= (int) $monthlyTarget['min'] ?>–<?= (int) $monthlyTarget['max'] ?> reports.</p>
    </div>
  </aside>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
