<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/attendance.php';
require_once __DIR__ . '/../lib/tenant.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$company_id = current_company_id();
$today = date('Y-m-d');
$current_time = date('H:i:s');
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT position FROM employees WHERE user_id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$employee = $stmt->fetch();

$isIntern = false;
if ($employee) {
    $position = strtolower(trim($employee['position']));
    $isIntern = in_array($position, ['internship', 'intern']);
}

if (isset($_POST['submitAbsenceReason'])) {
    csrf_verify();
    $absence_date = $today;
    $absence_type = $_POST['absence_type'] ?? '';
    $explanation = trim($_POST['explanation'] ?? '');

    if (empty($absence_type) || empty($explanation)) {
        attendance_redirect('?error=missing_data');
    }

    if ($absence_date !== $today) {
        attendance_redirect('?error=invalid_date');
    }

    if ($current_time >= '23:59:59') {
        attendance_redirect('?error=time_expired');
    }

    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND company_id = ? AND date = ?");
    $stmt->execute([$user_id, $company_id, $absence_date]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] !== null && $existing['status'] !== '') {
            attendance_redirect('?error=already_attended');
        }

        $stmt = $pdo->prepare("UPDATE attendance SET status = ?, notes = ?, explanation = ?
                              WHERE user_id = ? AND company_id = ? AND date = ?");
        $stmt->execute([$absence_type, "Absence Reason: $absence_type", $explanation, $user_id, $company_id, $absence_date]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance (company_id, user_id, date, status, notes, explanation, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$company_id, $user_id, $absence_date, $absence_type, "Absence Reason: $absence_type", $explanation]);
    }

    flash_set('ok', attendance_messages()['success']['absence_reason_submitted']);
    attendance_redirect();
}

if (isset($_POST['submitLeave'])) {
    csrf_verify();
    $leave_type = $_POST['leave_type'] ?? '';
    $explanation = trim($_POST['explanation']);

    if (empty($leave_type) || empty($explanation)) {
        attendance_redirect('?error=missing_data');
    }

    $stmt = $pdo->prepare("SELECT check_in FROM attendance WHERE user_id = ? AND company_id = ? AND date = ?");
    $stmt->execute([$user_id, $company_id, $today]);
    $existing = $stmt->fetch();

    if ($existing && $existing['check_in']) {
        attendance_redirect('?error=already_checked_in');
    }

    $status = match ($leave_type) {
        'Sick' => 'Sick',
        'Leave' => 'Leave',
        'Others' => 'Others',
        default => 'Leave'
    };

    $notes = "$status request";

    $stmt = $pdo->prepare("INSERT INTO attendance (company_id, user_id, date, status, notes, explanation)
                          VALUES (?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE
                          status = VALUES(status),
                          notes = VALUES(notes),
                          explanation = VALUES(explanation)");

    $stmt->execute([$company_id, $user_id, $today, $status, $notes, $explanation]);

    flash_set('ok', 'Leave requested');
    attendance_redirect();
}

if (isset($_POST['submitCheckIn'])) {
    csrf_verify();
    $current_time = date('H:i:s');
    $location = trim($_POST['location']);
    $shift = $_POST['shift'] ?? 'WFO';
    $explanation = trim($_POST['explanation'] ?? '');

    $stmt = $pdo->prepare("SELECT status FROM attendance WHERE user_id = ? AND company_id = ? AND date = ?");
    $stmt->execute([$user_id, $company_id, $today]);
    $existing = $stmt->fetch();

    if ($existing && in_array($existing['status'], ['Leave', 'Sick', 'Others', 'Absent', 'Forgot'])) {
        attendance_redirect('?error=attendance_already_submitted');
    }

    $status = 'Present';
    $notes = "Present: $shift";
    $save_explanation = null;

    $is_late = false;
    $current_minutes = (int)date('H') * 60 + (int)date('i');

    if ($shift === 'Morning' && $current_minutes > (8 * 60 + 15)) {
        $is_late = true;
    } elseif ($shift === 'Afternoon' && $current_minutes > (13 * 60 + 30)) {
        $is_late = true;
    } elseif (in_array($shift, ['WFO', 'WAC', 'WFH', 'WFA']) && $current_minutes > (9 * 60 + 30)) {
        $is_late = true;
    }

    if ($is_late) {
        $status = 'Late';
        $notes = "Late: $shift";

        if (empty($explanation) || strlen($explanation) < 10) {
            attendance_redirect('?error=explanation_required');
        }
        $save_explanation = $explanation;
    }

    $stmt = $pdo->prepare("INSERT INTO attendance (company_id, user_id, date, check_in, status, location, notes, explanation)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE
                          check_in = VALUES(check_in),
                          status = VALUES(status),
                          location = VALUES(location),
                          notes = VALUES(notes),
                          explanation = VALUES(explanation)");

    $stmt->execute([
        $company_id,
        $user_id,
        $today,
        $current_time,
        $status,
        $location,
        $notes,
        $save_explanation
    ]);

    flash_set('ok', 'Checked in');
    attendance_redirect();
}

if (isset($_POST['check_out'])) {
    csrf_verify();
    $current_time = date('H:i:s');
    $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE user_id = ? AND company_id = ? AND date = ?");
    $stmt->execute([$current_time, $user_id, $company_id, $today]);
    flash_set('ok', 'Checked out');
    attendance_redirect();
}

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND company_id = ? AND date = ?");
$stmt->execute([$user_id, $company_id, $today]);
$attendance = $stmt->fetch() ?: null;

$state = attendance_state($attendance);
$can_input_absence = attendance_can_input_absence($attendance);

$month = valid_month($_GET['month'] ?? null);
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND company_id = ? AND date BETWEEN ? AND ?");
$totalStmt->execute([$user_id, $company_id, $start, $end]);
$total = (int)$totalStmt->fetchColumn();

$sql = "SELECT date, check_in, check_out, status, location, notes, explanation
        FROM attendance
        WHERE user_id = ? AND company_id = ? AND date BETWEEN ? AND ?
        ORDER BY date DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $company_id, $start, $end]);
$monthly = $stmt->fetchAll();

$countsStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE user_id = ? AND company_id = ? AND date BETWEEN ? AND ? GROUP BY status");
$countsStmt->execute([$user_id, $company_id, $start, $end]);
$statusCounts = [];
foreach ($countsStmt->fetchAll() as $r) {
    $statusCounts[(string) $r['status']] = (int) $r['c'];
}

$returnTo = 'attendance.php';
$pageTitle = 'Attendance';
$activeNav = 'attendance';
$pageScripts = ['attendance'];
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Attendance', $isIntern ? 'Intern' : 'Full-time employee') ?>

<?= attendance_alert_html() ?>

<section class="card">
  <div class="card-body flex flex-wrap items-center gap-6">
    <div>
      <div class="section-label"><?= e(fmt_date($today, 'day')) ?></div>
      <div class="big-number" data-clock><?= e(date('H:i')) ?></div>
    </div>
    <?php include __DIR__ . '/../views/attendance/timeline.php'; ?>
    <div class="grid gap-2 justify-items-start ml-auto">
      <?php if ($attendance && $attendance['status']): ?><?= status_pill($attendance['status']) ?><?php endif; ?>
      <?php include __DIR__ . '/../views/attendance/action.php'; ?>
    </div>
  </div>
</section>

<?= kpi_strip([
    ['label' => 'Present', 'value' => $statusCounts['Present'] ?? 0, 'note' => fmt_month($month)],
    ['label' => 'Late', 'value' => $statusCounts['Late'] ?? 0],
    ['label' => 'Leave', 'value' => ($statusCounts['Leave'] ?? 0) + ($statusCounts['Others'] ?? 0)],
    ['label' => 'Sick', 'value' => $statusCounts['Sick'] ?? 0],
    ['label' => 'Absent', 'value' => ($statusCounts['Absent'] ?? 0) + ($statusCounts['Forgot'] ?? 0), 'tone' => (($statusCounts['Absent'] ?? 0) + ($statusCounts['Forgot'] ?? 0)) > 0 ? 'bad' : null],
]) ?>

<section class="card">
  <div class="card-header">
    <h2 class="card-title">History</h2>
    <?= period_picker('month', $month, 'month', ['page']) ?>
  </div>
  <?php if (empty($monthly)): ?>
    <?= empty_state('No attendance records in ' . fmt_month($month)) ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th class="num">In</th>
            <th class="num">Out</th>
            <th>Status</th>
            <th>Location</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($monthly as $record): ?>
            <tr>
              <td class="whitespace-nowrap"><?= e(fmt_date($record['date'], 'day')) ?></td>
              <td class="num"><?= e(fmt_time($record['check_in'])) ?></td>
              <td class="num"><?= e(fmt_time($record['check_out'])) ?></td>
              <td><?= status_pill($record['status']) ?></td>
              <td><?= e($record['location'] ?: '–') ?></td>
              <td><span class="block truncate max-w-[260px] text-muted" title="<?= e($record['explanation'] ?? '') ?>"><?= e($record['explanation'] ?: '–') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $limit, $total) ?>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../views/attendance/dialogs.php'; ?>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
