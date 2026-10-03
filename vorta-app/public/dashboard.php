<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/reports.php';
require_login();

$isAdmin = $_SESSION['user']['role'] === 'admin';
$target = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
$today = date('Y-m-d');

$month = $isAdmin ? valid_month($_GET['month'] ?? null) : date('Y-m');
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));

// Ranking / staff performance (dipakai admin & Today)
$stmt = $pdo->prepare("
  SELECT u.user_id, u.name, u.role,
         COALESCE(SUM(pr.report_date BETWEEN ? AND ?), 0) as dummy,
         COUNT(pr.report_id) as total
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.report_date BETWEEN ? AND ?
  WHERE u.is_active = 1
  GROUP BY u.user_id
  ORDER BY total DESC, u.name
");
$stmt->execute([$start, $end, $start, $end]);
$users = $stmt->fetchAll();

if ($isAdmin) {
    $totalStaff = count($users);
    $totalReportsMonth = array_sum(array_column($users, 'total'));
    $onTrackStaff = count(array_filter($users, fn($u) => (int)$u['total'] >= $target['min']));
    $avgPerStaff = $totalStaff > 0 ? round($totalReportsMonth / $totalStaff, 1) : 0;

    $types = $pdo->prepare("SELECT job_type, COUNT(*) c FROM production_reports WHERE report_date BETWEEN ? AND ? GROUP BY job_type ORDER BY c DESC");
    $types->execute([$start, $end]);
    $typeRows = $types->fetchAll();
    $typeTotal = array_sum(array_column($typeRows, 'c'));

    $maxSlices = 6;
    $chartRows = array_slice($typeRows, 0, $maxSlices);
    if (count($typeRows) > $maxSlices) {
        $rest = array_slice($typeRows, $maxSlices);
        $chartRows[] = [
            'job_type' => 'Others',
            'c'        => array_sum(array_column($rest, 'c')),
            'detail'   => implode(', ', array_column($rest, 'job_type')),
        ];
    }
    foreach ($chartRows as $i => &$row) {
        $row['color'] = isset($row['detail']) ? 'c5' : 'c' . ($i + 1);
    }
    unset($row);

    $todayStats = report_daily_completion_stats($pdo, $today, $dailyMin);

    $pageTitle = 'Dashboard';
    $activeNav = 'dashboard';
    $pageScripts = ['chart'];
    include __DIR__ . '/../views/layout/start.php';
    include __DIR__ . '/../views/dashboard/admin.php';
    include __DIR__ . '/../views/layout/end.php';
    exit;
}

// ---------- Staff: Today ----------
require_once __DIR__ . '/../lib/attendance.php';
$user_id = (int) $_SESSION['user']['user_id'];

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$attendance = $stmt->fetch() ?: null;
$state = attendance_state($attendance);
$can_input_absence = attendance_can_input_absence($attendance);
$isIntern = attendance_is_intern($pdo, $user_id);

$stmt = $pdo->prepare("SELECT report_id, title, job_type, status FROM production_reports
                       WHERE user_id = ? AND report_date = ? ORDER BY report_id DESC");
$stmt->execute([$user_id, $today]);
$todayReports = $stmt->fetchAll();

$dailyCounts = report_daily_counts($pdo, $user_id, $start, $end);
$monthCount = array_sum($dailyCounts);

$weekdaysLeft = 0;
for ($d = strtotime($today . ' +1 day'); $d <= strtotime($end); $d = strtotime('+1 day', $d)) {
    if ((int) date('N', $d) <= 5) $weekdaysLeft++;
}

$myRank = null;
foreach (array_values($users) as $i => $u) {
    if ((int) $u['user_id'] === $user_id) {
        $myRank = $i + 1;
        break;
    }
}

$returnTo = 'dashboard.php';
$pageTitle = 'Today';
$activeNav = 'today';
$pageScripts = ['attendance'];
include __DIR__ . '/../views/layout/start.php';
include __DIR__ . '/../views/dashboard/today.php';
include __DIR__ . '/../views/layout/end.php';
