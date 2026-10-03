<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/uploads.php';
require_login();
$company_id = current_company_id();

$user_id = $_SESSION['user']['user_id'];
$report_id = $_GET['id'] ?? null;
$monthlyTarget = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
if (!$report_id) {
    die("Report ID not provided.");
}

$stmt = $pdo->prepare("SELECT pr.*, wf.workforce_name
                       FROM production_reports pr
                       LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
                       WHERE pr.report_id = ? AND pr.user_id = ? AND pr.company_id = ?");
$stmt->execute([$report_id, $user_id, $company_id]);
$report = $stmt->fetch();

if (!$report) {
    die("Report not found or you do not have access.");
}

if ($report['status'] !== 'Progress') {
    header("Location: my_reports.php");
    exit;
}

$job_types_stmt = $pdo->prepare("SELECT job_type_id, name FROM job_type WHERE company_id = ? ORDER BY name");
$job_types_stmt->execute([$company_id]);
$job_types = $job_types_stmt->fetchAll();

$workforce_stmt = $pdo->prepare("SELECT workforce_id, workforce_name FROM work_force WHERE company_id = ? ORDER BY workforce_name");
$workforce_stmt->execute([$company_id]);
$work_forces = $workforce_stmt->fetchAll();

$lastWorkforceId = null;
// update_report.php menyimpan job type sebagai nama; upload_save_image() menerima JPG/PNG/WebP
$jobTypeValueKey = 'name';
$acceptTypes = ['image/jpeg', 'image/png', 'image/webp'];

$pageTitle = 'Edit report';
$activeNav = 'my_reports';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Edit report', null, '', ['href' => 'my_reports.php?month=' . substr((string) $report['report_date'], 0, 7), 'label' => 'My reports']) ?>

<div class="grid gap-4 lg:grid-cols-[1fr_300px] items-start">
  <form action="update_report.php" method="POST" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="report_id" value="<?= (int)$report['report_id'] ?>">
    <?php include __DIR__ . '/../views/reports/form_fields.php'; ?>
    <div class="card-footer justify-end">
      <a class="btn btn-ghost" href="my_reports.php">Cancel</a>
      <button type="submit" class="btn btn-primary" data-turbo-submits-with="Saving…">Save changes</button>
    </div>
  </form>

  <aside class="card">
    <div class="card-header"><h2 class="card-title">Editing</h2></div>
    <div class="card-body">
      <dl class="dl">
        <dt>Date</dt><dd><?= e(fmt_date($report['report_date'], 'long')) ?></dd>
        <dt>Status</dt><dd><?= status_pill($report['status']) ?></dd>
        <dt>Work force</dt><dd><?= e($report['workforce_name'] ?? '–') ?></dd>
      </dl>
    </div>
  </aside>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
