<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/attendance.php';
require_admin();

$tab = $_GET['tab'] ?? null;
if (!in_array($tab, ['daily', 'monthly', 'missing'], true)) {
  $tab = (($_GET['recap_type'] ?? '') === 'monthly') ? 'monthly' : 'daily';
}
$recapType = $tab === 'monthly' ? 'monthly' : 'daily';
$date = valid_date($_GET['date'] ?? null);
$month = valid_month($_GET['month'] ?? null);

$validNotes = [
  '',
  'Present: Morning',
  'Present: Afternoon',
  'Present: WFH',
  'Present: WFO',
  'Present: WAC',
  'Present: WFA',
  'Late: Morning',
  'Late: Afternoon',
  'Late: WFH',
  'Late: WFO',
  'Late: WAC',
  'Late: WFA',
  'Absence Reason: Absent',
  'Absence Reason: Forgot',
  'Absence Reason: Sick',
  'Absence Reason: Leave',
  'Absence Reason: Others',
];
$notesFilter = in_array($_GET['notes'] ?? '', $validNotes) ? ($_GET['notes'] ?? '') : '';

/** Label ramah untuk opsi filter shift (value tetap). */
function notes_label(string $note): string
{
  if ($note === '') return 'All shifts';
  [$kind, $what] = array_map('trim', explode(':', $note, 2));
  return match ($kind) {
    'Present' => "$what (on time)",
    'Late' => "$what (late)",
    default => $what === 'Forgot' ? 'Forgot to check in' : $what,
  };
}

$userFilter = $_GET['user_id'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));

// Not checked in count for the selected date (tab badge + daily KPI)
$missingCount = attendance_missing_count($pdo, $date);

if ($tab === 'daily') {
  $limitDaily = 20;
  $dailyPage = isset($_GET['daily_page']) ? max(1, (int)$_GET['daily_page']) : 1;
  $dailyOffset = ($dailyPage - 1) * $limitDaily;

  $whereDaily = "a.date = ?";
  $paramsDaily = [$date];
  if (!empty($notesFilter)) {
    $whereDaily .= " AND a.notes = ?";
    $paramsDaily[] = $notesFilter;
  }
  if ($q !== '') {
    $whereDaily .= " AND u.name LIKE ?";
    $paramsDaily[] = '%' . $q . '%';
  }

  $countDaily = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN users u ON a.user_id = u.user_id WHERE $whereDaily");
  $countDaily->execute($paramsDaily);
  $totalDaily = (int)$countDaily->fetchColumn();

  $stmt = $pdo->prepare("
      SELECT a.*, u.name, u.email
      FROM attendance a
      JOIN users u ON a.user_id = u.user_id
      WHERE $whereDaily
      ORDER BY a.status, u.name
      LIMIT $limitDaily OFFSET $dailyOffset
  ");
  $stmt->execute($paramsDaily);
  $daily = $stmt->fetchAll();

  $kpiStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE date = ? GROUP BY status");
  $kpiStmt->execute([$date]);
  $dayCounts = [];
  foreach ($kpiStmt->fetchAll() as $r) {
    $dayCounts[(string) $r['status']] = (int) $r['c'];
  }
} elseif ($tab === 'monthly') {
  $start = $month . "-01";
  $end = date('Y-m-t', strtotime($start));

  $limitMonthly = 20;
  $monthlyPage = isset($_GET['monthly_page']) ? max(1, (int)$_GET['monthly_page']) : 1;
  $monthlyOffset = ($monthlyPage - 1) * $limitMonthly;

  $usersStmt = $pdo->query("SELECT user_id, name FROM users WHERE is_active = 1 ORDER BY name");
  $users = $usersStmt->fetchAll();

  $sqlCountMonthly = "SELECT COUNT(*) FROM users WHERE is_active = 1";
  $paramsMonthly = [];
  if (!empty($userFilter)) {
    $sqlCountMonthly .= " AND user_id = ?";
    $paramsMonthly[] = $userFilter;
  }
  $countMonthly = $pdo->prepare($sqlCountMonthly);
  $countMonthly->execute($paramsMonthly);
  $totalMonthly = (int)$countMonthly->fetchColumn();

  $sqlMonthly = "
      SELECT u.user_id, u.name,
             SUM(CASE
                 WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) <= '07:45:00' THEN 1
                 ELSE 0
             END) as present_shift_morning,
             SUM(CASE
                 WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) > '07:45:00' AND TIME(a.check_in) <= '13:10:00' THEN 1
                 ELSE 0
             END) as present_shift_afternoon,
             SUM(CASE
                 WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) > '13:10:00' THEN 1
                 ELSE 0
             END) as present_invalid,
             SUM(CASE
                 WHEN a.notes LIKE 'Late:%' THEN 1
                 ELSE 0
             END) as late,
             SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
             SUM(CASE WHEN a.status = 'Sick' THEN 1 ELSE 0 END) as sick,
             COUNT(a.attendance_id) as total_records
      FROM users u
      LEFT JOIN attendance a ON a.user_id = u.user_id AND a.date BETWEEN ? AND ?
      WHERE u.is_active = 1
  ";
  $paramsMonthlyQuery = [$start, $end];
  if (!empty($userFilter)) {
    $sqlMonthly .= " AND u.user_id = ?";
    $paramsMonthlyQuery[] = $userFilter;
  }
  $sqlMonthly .= " GROUP BY u.user_id
      ORDER BY u.name
      LIMIT $limitMonthly OFFSET $monthlyOffset";

  $stmt = $pdo->prepare($sqlMonthly);
  $stmt->execute($paramsMonthlyQuery);
  $monthly = $stmt->fetchAll();

  $sqlTotalMonthly = "
      SELECT
             SUM(CASE
                 WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) <= '07:45:00' THEN 1
                 ELSE 0
             END) as total_present_morning,
             SUM(CASE
                 WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) > '07:45:00' AND TIME(a.check_in) <= '13:10:00' THEN 1
                 ELSE 0
             END) as total_present_afternoon,
             SUM(CASE
                 WHEN a.notes LIKE 'Late:%' THEN 1
                 ELSE 0
             END) as total_late,
             SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as total_leave,
             SUM(CASE WHEN a.status = 'Sick' THEN 1 ELSE 0 END) as total_sick
      FROM attendance a
      WHERE a.date BETWEEN ? AND ?
  ";
  $totalStmt = $pdo->prepare($sqlTotalMonthly);
  $totalStmt->execute([$start, $end]);
  $monthlyTotals = $totalStmt->fetch();
} else {
  // Not checked in (dipindah dari admin_not_attendance.php)
  $limit = 20;
  $missingPage = (int)($_GET['missing_page'] ?? 1);
  if ($missingPage < 1) $missingPage = 1;
  $offset = ($missingPage - 1) * $limit;

  $stmt = $pdo->prepare("
      SELECT
          u.user_id,
          u.name,
          u.email,
          e.position
      FROM users u
      JOIN employees e ON u.user_id = e.user_id
      LEFT JOIN attendance a ON u.user_id = a.user_id AND a.date = ?
      WHERE u.role != 'admin'
        AND a.user_id IS NULL
      ORDER BY e.position, u.name
      LIMIT ? OFFSET ?
  ");
  $stmt->bindValue(1, $date, PDO::PARAM_STR);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $stmt->execute();
  $users_not_checked_in = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $totalEmpStmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM users u
      JOIN employees e ON u.user_id = e.user_id
      WHERE u.role != 'admin'
  ");
  $totalEmpStmt->execute();
  $total_employees = (int) $totalEmpStmt->fetchColumn();

  $presentStmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM attendance a
      JOIN users u ON u.user_id = a.user_id
      JOIN employees e ON u.user_id = e.user_id
      WHERE a.date = ?
        AND u.role != 'admin'
  ");
  $presentStmt->execute([$date]);
  $present_count = (int) $presentStmt->fetchColumn();
}

$exportUrl = 'export_excel.php?' . http_build_query([
  'recap_type' => $recapType,
  'date' => $date,
  'month' => $month,
  'notes' => $notesFilter,
  'user_id' => $userFilter,
]);
$exportBtn = $tab === 'missing' ? '' : '<a class="btn btn-secondary" href="' . e($exportUrl) . '" data-turbo="false">' . icon('arrow-down-tray') . 'Export</a>';

$tabUrl = fn(string $t) => 'admin_attendance.php?' . http_build_query(array_filter([
  'tab' => $t,
  'date' => $t === 'monthly' ? null : ($_GET['date'] ?? null),
  'month' => $t === 'monthly' ? ($_GET['month'] ?? null) : null,
]));

$pageTitle = 'Attendance';
$activeNav = 'attendance';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Attendance', 'Check-ins, leave and absences') ?>

<nav class="tabs" aria-label="Attendance views">
  <?php foreach (['daily' => 'Daily', 'monthly' => 'Monthly', 'missing' => 'Not checked in'] as $key => $label): ?>
    <a class="tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e($tabUrl($key)) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
      <?= $label ?><?php if ($key === 'missing' && $missingCount > 0): ?> <span class="tab-count"><?= $missingCount ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'daily'): ?>
  <div class="toolbar">
    <?= period_picker('date', $date, 'date', ['daily_page']) ?>
    <form method="get" id="aa-daily-filters" class="contents" data-autosubmit data-turbo-frame="aa-records" data-turbo-action="replace">
      <input type="hidden" name="tab" value="daily">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <label for="aa-shift" class="sr-only">Shift</label>
      <select id="aa-shift" name="notes" class="select">
        <?php foreach ($validNotes as $note): ?>
          <option value="<?= e($note) ?>"<?= $notesFilter === $note ? ' selected' : '' ?>><?= e(notes_label($note)) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="input-icon">
        <?= icon('magnifying-glass') ?>
        <label for="aa-search" class="sr-only">Search staff</label>
        <input type="search" id="aa-search" name="q" class="input" placeholder="Search staff…" value="<?= e($q) ?>">
      </div>
    </form>
  </div>

  <?php /* Export ikut di dalam frame supaya URL-nya mengikuti filter terbaru. */ ?>
  <turbo-frame id="aa-records" class="results-frame" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
  <div class="toolbar"><span class="toolbar-count"><?= $totalDaily ?> record<?= $totalDaily === 1 ? '' : 's' ?></span><?= $exportBtn ?></div>

  <?= kpi_strip([
      ['label' => 'Present', 'value' => $dayCounts['Present'] ?? 0, 'note' => fmt_date($date, 'day')],
      ['label' => 'Late', 'value' => $dayCounts['Late'] ?? 0],
      ['label' => 'Leave / sick', 'value' => ($dayCounts['Leave'] ?? 0) + ($dayCounts['Sick'] ?? 0) + ($dayCounts['Others'] ?? 0)],
      ['label' => 'Not checked in', 'value' => $missingCount, 'tone' => $missingCount > 0 ? 'bad' : null],
  ]) ?>

  <section class="card">
    <?php if (empty($daily)): ?>
      <?= empty_state('No attendance records on ' . fmt_date($date, 'long'), ($notesFilter !== '' || $q !== '') ? 'Try another date or clear the filters.' : '') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Staff</th>
              <th class="num">In</th>
              <th class="num">Out</th>
              <th>Status</th>
              <th>Location</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daily as $r): ?>
              <tr>
                <td><span class="cell-strong"><?= e($r['name']) ?></span><span class="cell-sub"><?= e($r['email']) ?></span></td>
                <td class="num"><?= e(fmt_time($r['check_in'])) ?></td>
                <td class="num"><?= e(fmt_time($r['check_out'])) ?></td>
                <td><?= status_pill($r['status']) ?><?php if (!empty($r['notes'])): ?><span class="cell-sub"><?= e(notes_label((string) $r['notes'])) ?></span><?php endif; ?></td>
                <td><?= e($r['location'] ?: '–') ?></td>
                <td><span class="block truncate max-w-[260px] text-muted" title="<?= e($r['explanation'] ?? '') ?>"><?= e($r['explanation'] ?: '–') ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= pagination($dailyPage, $limitDaily, $totalDaily, 'daily_page') ?>
    <?php endif; ?>
  </section>
  </turbo-frame>

<?php elseif ($tab === 'monthly'): ?>
  <div class="toolbar">
    <?= period_picker('month', $month, 'month', ['monthly_page']) ?>
    <form method="get" id="aa-monthly-filters" class="contents" data-autosubmit data-turbo-frame="aa-monthly" data-turbo-action="replace">
      <input type="hidden" name="tab" value="monthly">
      <input type="hidden" name="month" value="<?= e($month) ?>">
      <label for="aa-staff" class="sr-only">Staff</label>
      <select id="aa-staff" name="user_id" class="select">
        <option value="">All staff</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['user_id'] ?>"<?= (string) $userFilter === (string) $u['user_id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <turbo-frame id="aa-monthly" class="results-frame" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
  <div class="toolbar"><span class="toolbar-count"><?= $totalMonthly ?> staff</span><?= $exportBtn ?></div>

  <?php if (empty($userFilter)): ?>
    <?= kpi_strip([
        ['label' => 'Present morning', 'value' => (int) ($monthlyTotals['total_present_morning'] ?? 0), 'note' => fmt_month($month)],
        ['label' => 'Present afternoon', 'value' => (int) ($monthlyTotals['total_present_afternoon'] ?? 0)],
        ['label' => 'Late', 'value' => (int) ($monthlyTotals['total_late'] ?? 0)],
        ['label' => 'Leave', 'value' => (int) ($monthlyTotals['total_leave'] ?? 0)],
        ['label' => 'Sick', 'value' => (int) ($monthlyTotals['total_sick'] ?? 0)],
    ]) ?>
  <?php endif; ?>

  <section class="card">
    <?php if (empty($monthly)): ?>
      <?= empty_state('No attendance records in ' . fmt_month($month)) ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Staff</th>
              <th class="num" title="Present, morning shift">Morning</th>
              <th class="num" title="Present, afternoon shift">Afternoon</th>
              <th class="num" title="Present, check-in after 13:10">Invalid</th>
              <th class="num">Late</th>
              <th class="num">Leave</th>
              <th class="num">Sick</th>
              <th class="num" title="Total present">Present</th>
              <th class="num" title="Total absent (late + leave + sick)">Absent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthly as $record):
              $totalPresent = ($record['present_shift_morning'] ?? 0) + ($record['present_shift_afternoon'] ?? 0);
              $totalAbsent = ($record['late'] ?? 0) + ($record['leave_count'] ?? 0) + ($record['sick'] ?? 0);
            ?>
              <tr>
                <td class="cell-strong"><?= e($record['name']) ?></td>
                <td class="num"><?= (int) ($record['present_shift_morning'] ?? 0) ?></td>
                <td class="num"><?= (int) ($record['present_shift_afternoon'] ?? 0) ?></td>
                <td class="num"><?= (int) ($record['present_invalid'] ?? 0) ?></td>
                <td class="num"><?= (int) ($record['late'] ?? 0) ?></td>
                <td class="num"><?= (int) ($record['leave_count'] ?? 0) ?></td>
                <td class="num"><?= (int) ($record['sick'] ?? 0) ?></td>
                <td class="num cell-strong"><?= (int) $totalPresent ?></td>
                <td class="num cell-strong"><?= (int) $totalAbsent ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= pagination($monthlyPage, $limitMonthly, $totalMonthly, 'monthly_page') ?>
    <?php endif; ?>
  </section>
  </turbo-frame>

<?php else: ?>
  <div class="toolbar">
    <?= period_picker('date', $date, 'date', ['missing_page']) ?>
  </div>

  <?= kpi_strip([
      ['label' => 'Total staff', 'value' => $total_employees],
      ['label' => 'Checked in', 'value' => $present_count, 'note' => fmt_date($date, 'day')],
      ['label' => 'Not checked in', 'value' => $missingCount, 'tone' => $missingCount > 0 ? 'bad' : null],
  ]) ?>

  <turbo-frame id="aa-missing" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
  <section class="card">
    <?php if (empty($users_not_checked_in)): ?>
      <?= empty_state('Everyone has checked in', 'All staff have an attendance record for ' . fmt_date($date, 'long') . '.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Staff</th><th>Position</th></tr></thead>
          <tbody>
            <?php foreach ($users_not_checked_in as $u): ?>
              <tr>
                <td><span class="cell-strong"><?= e($u['name']) ?></span><span class="cell-sub"><?= e($u['email']) ?></span></td>
                <td><span class="pill pill-role"><?= e($u['position'] ?: 'Unknown') ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= pagination($missingPage, $limit, $missingCount, 'missing_page') ?>
    <?php endif; ?>
  </section>
  </turbo-frame>
<?php endif; ?>

<?php include __DIR__ . '/../views/layout/end.php'; ?>
