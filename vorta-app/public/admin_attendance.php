<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_admin();

$recapType = $_GET['recap_type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');

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

$userFilter = $_GET['user_id'] ?? '';

function buildDailyUrl($page, $date, $notes)
{
  return '?' . http_build_query([
    'recap_type' => 'daily',
    'date' => $date,
    'notes' => $notes,
    'daily_page' => $page
  ]);
}

$limitDaily = 10;
$dailyPage = isset($_GET['daily_page']) ? max(1, (int)$_GET['daily_page']) : 1;
$dailyOffset = ($dailyPage - 1) * $limitDaily;

$sqlCountDaily = "SELECT COUNT(*) FROM attendance a WHERE a.date = ?";
$paramsDaily = [$date];
if (!empty($notesFilter)) {
  $sqlCountDaily .= " AND a.notes = ?";
  $paramsDaily[] = $notesFilter;
}
$countDaily = $pdo->prepare($sqlCountDaily);
$countDaily->execute($paramsDaily);
$totalDaily = (int)$countDaily->fetchColumn();
$totalDailyPages = ceil($totalDaily / $limitDaily);

$dailyStartPage = max(1, $dailyPage - 2);
$dailyEndPage = min($totalDailyPages, $dailyStartPage + 4);
if ($dailyEndPage - $dailyStartPage < 4) {
  $dailyStartPage = max(1, $dailyEndPage - 4);
}

$sqlDaily = "
    SELECT a.*, u.name, u.email
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.date = ?
";
$paramsDaily = [$date];
if (!empty($notesFilter)) {
  $sqlDaily .= " AND a.notes = ?";
  $paramsDaily[] = $notesFilter;
}
$sqlDaily .= " ORDER BY a.status, u.name
    LIMIT $limitDaily OFFSET $dailyOffset";

$stmt = $pdo->prepare($sqlDaily);
$stmt->execute($paramsDaily);
$daily = $stmt->fetchAll();

$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));

$limitMonthly = 10;
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
$totalMonthlyPages = ceil($totalMonthly / $limitMonthly);

$monthlyStartPage = max(1, $monthlyPage - 2);
$monthlyEndPage = min($totalMonthlyPages, $monthlyStartPage + 4);
if ($monthlyEndPage - $monthlyStartPage < 4) {
  $monthlyStartPage = max(1, $monthlyEndPage - 4);
}

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

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>vorta Prodtracker - Attendance Recap</title>
  <link rel="stylesheet" href="css/output.css">
</head>

<body>
  <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">

    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 md:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <h1 class="text-2xl font-bold text-gray-800">Attendance Recap</h1>

          <form class="flex flex-col sm:flex-row items-center gap-2" method="get">
            <input type="hidden" name="recap_type" value="<?= $recapType ?>">

            <select name="recap_type"
              onchange="this.form.submit()"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <option value="daily" <?= $recapType === 'daily' ? 'selected' : '' ?>>Daily</option>
              <option value="monthly" <?= $recapType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>

            <?php if ($recapType === 'daily'): ?>
              <input type="date" name="date"
                value="<?= htmlspecialchars($date) ?>"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">

              <select name="notes"
                onchange="this.form.submit()"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="">-- All Notes --</option>
                <option value="Present: Morning" <?= $notesFilter === 'Present: Morning' ? 'selected' : '' ?>>Present: Morning</option>
                <option value="Present: Afternoon" <?= $notesFilter === 'Present: Afternoon' ? 'selected' : '' ?>>Present: Afternoon</option>
                <option value="Present: WFH" <?= $notesFilter === 'Present: WFH' ? 'selected' : '' ?>>Present: WFH</option>
                <option value="Present: WFO" <?= $notesFilter === 'Present: WFO' ? 'selected' : '' ?>>Present: WFO</option>
                <option value="Present: WAC" <?= $notesFilter === 'Present: WAC' ? 'selected' : '' ?>>Present: WAC</option>
                <option value="Present: WFA" <?= $notesFilter === 'Present: WFA' ? 'selected' : '' ?>>Present: WFA</option>
                <option value="Late: Morning" <?= $notesFilter === 'Late: Morning' ? 'selected' : '' ?>>Late: Morning</option>
                <option value="Late: Afternoon" <?= $notesFilter === 'Late: Afternoon' ? 'selected' : '' ?>>Late: Afternoon</option>
                <option value="Late: WFH" <?= $notesFilter === 'Late: WFH' ? 'selected' : '' ?>>Late: WFH</option>
                <option value="Late: WFO" <?= $notesFilter === 'Late: WFO' ? 'selected' : '' ?>>Late: WFO</option>
                <option value="Late: WAC" <?= $notesFilter === 'Late: WAC' ? 'selected' : '' ?>>Late: WAC</option>
                <option value="Late: WFA" <?= $notesFilter === 'Late: WFA' ? 'selected' : '' ?>>Late: WFA</option>
                <option value="Absence Reason: Absent" <?= $notesFilter === 'Absence Reason: Absent' ? 'selected' : '' ?>>Absence Reason: Absent</option>
                <option value="Absence Reason: Forgot" <?= $notesFilter === 'Absence Reason: Forgot' ? 'selected' : '' ?>>Absence Reason: Forgot</option>
                <option value="Absence Reason: Sick" <?= $notesFilter === 'Absence Reason: Sick' ? 'selected' : '' ?>>Absence Reason: Sick</option>
                <option value="Absence Reason: Leave" <?= $notesFilter === 'Absence Reason: Leave' ? 'selected' : '' ?>>Absence Reason: Leave</option>
                <option value="Absence Reason: Others" <?= $notesFilter === 'Absence Reason: Others' ? 'selected' : '' ?>>Absence Reason: Others</option>
              </select>
            <?php else: ?>
              <input type="month" name="month"
                value="<?= htmlspecialchars($month) ?>"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">

              <select name="user_id"
                onchange="this.form.submit()"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="">-- All Users --</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?= $user['user_id'] ?>" <?= $userFilter == $user['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>

            <button type="submit"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
              Filter
            </button>
            <a href="export_excel.php?recap_type=<?= urlencode($recapType) ?>&date=<?= urlencode($date) ?>&month=<?= urlencode($month) ?>&notes=<?= urlencode($notesFilter) ?>&user_id=<?= urlencode($userFilter) ?>"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
              Export Excel
            </a>
          </form>
        </div>

        <?php if ($recapType === 'monthly' && empty($userFilter)): ?>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-3">Monthly Summary - <?= date('F Y', strtotime($start)) ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
              <div class="text-center">
                <div class="text-2xl font-bold text-green-700"><?= $monthlyTotals['total_present_morning'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Present Morning</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?= $monthlyTotals['total_present_afternoon'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Present Afternoon</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600"><?= $monthlyTotals['total_late'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Late</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-indigo-600"><?= $monthlyTotals['total_leave'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Leave</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $monthlyTotals['total_sick'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Sick</div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
          <table class="w-full">
            <?php if ($recapType === 'daily'): ?>
              <thead>
                <tr class="text-left border-b border-gray-200">
                  <th class="pb-3 font-medium text-gray-600">Name</th>
                  <th class="pb-3 font-medium text-gray-600">Email</th>
                  <th class="pb-3 font-medium text-gray-600">Check-in</th>
                  <th class="pb-3 font-medium text-gray-600">Check-out</th>
                  <th class="pb-3 font-medium text-gray-600">Status</th>
                  <th class="pb-3 font-medium text-gray-600">Location</th>
                  <th class="pb-3 font-medium text-gray-600">Notes</th>
                  <th class="pb-3 font-medium text-gray-600">Explanation</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (count($daily) > 0): ?>
                  <?php foreach ($daily as $record): ?>
                    <tr class="hover:bg-gray-50 transition">
                      <td class="py-4 text-sm font-medium text-gray-800"><?= htmlspecialchars($record['name']) ?></td>
                      <td class="py-4 text-sm text-gray-600"><?= htmlspecialchars($record['email']) ?></td>
                      <td class="py-4 text-sm"><?= $record['check_in'] ?? '-' ?></td>
                      <td class="py-4 text-sm"><?= $record['check_out'] ?? '-' ?></td>
                      <td class="py-4 text-sm"><?= htmlspecialchars($record['status']) ?></td>
                      <td class="py-4 text-sm"><?= htmlspecialchars($record['location'] ?? '-') ?></td>
                      <td class="py-4 text-sm"><?= !empty($record['notes']) ? htmlspecialchars($record['notes']) : '-' ?></td>
                      <td class="py-4 text-sm hover:cursor-pointer max-w-xs lg:max-w-sm truncate"
                        title="<?= htmlspecialchars($record['explanation'] ?? '-') ?>">
                        <?= htmlspecialchars($record['explanation'] ?? '-') ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="py-4 text-center text-gray-500">No attendance records found for this date.</td>
                  </tr>
                <?php endif; ?>
              </tbody>

            <?php else: ?>
              <thead>
                <tr class="text-left border-b border-gray-200">
                  <th class="pb-3 font-medium text-gray-600">Name</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Present Morning</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Present Afternoon</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Present Invalid</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Late</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Leave</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Sick</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Total Present</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Total Absent</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (count($monthly) > 0): ?>
                  <?php foreach ($monthly as $record):
                    $totalPresent = ($record['present_shift_morning'] ?? 0) + ($record['present_shift_afternoon'] ?? 0);
                    $totalAbsent = ($record['late'] ?? 0) + ($record['leave_count'] ?? 0) + ($record['sick'] ?? 0);
                  ?>
                    <tr class="hover:bg-gray-50 transition">
                      <td class="py-4 text-sm font-medium text-gray-800"><?= htmlspecialchars($record['name']) ?></td>
                      <td class="py-4 text-sm text-center text-green-700"><?= $record['present_shift_morning'] ?? 0 ?></td>
                      <td class="py-4 text-sm text-center text-green-600"><?= $record['present_shift_afternoon'] ?? 0 ?></td>
                      <td class="py-4 text-sm text-center text-red-600"><?= $record['present_invalid'] ?? 0 ?></td>
                      <td class="py-4 text-sm text-center text-yellow-600"><?= $record['late'] ?? 0 ?></td>
                      <td class="py-4 text-sm text-center text-indigo-600"><?= $record['leave_count'] ?? 0 ?></td>
                      <td class="py-4 text-sm text-center text-purple-600"><?= $record['sick'] ?? 0 ?></td>
                      <td class="py-4 text-sm text-center font-bold text-green-800"><?= $totalPresent ?></td>
                      <td class="py-4 text-sm text-center font-bold text-red-600"><?= $totalAbsent ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="9" class="py-4 text-center text-gray-500">No monthly records found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            <?php endif; ?>
          </table>
        </div>

        <?php if ($recapType === 'daily' && $totalDailyPages > 1): ?>
          <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
            <div class="text-sm text-gray-600 whitespace-nowrap">
              Page <?= $dailyPage ?> of <?= $totalDailyPages ?>
            </div>
            <nav class="flex flex-wrap justify-center gap-1">
              <?php if ($dailyPage > 1): ?>
                <a href="?recap_type=daily&date=<?= htmlspecialchars($date) ?>&notes=<?= htmlspecialchars($notesFilter) ?>&daily_page=1"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">&lt;&lt; First</span>
                  <span class="sm:hidden">First</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">&lt;&lt; First</span>
                  <span class="sm:hidden">First</span>
                </span>
              <?php endif; ?>

              <?php if ($dailyPage > 1): ?>
                <a href="?recap_type=daily&date=<?= htmlspecialchars($date) ?>&notes=<?= htmlspecialchars($notesFilter) ?>&daily_page=<?= $dailyPage - 1 ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">&lt; Prev</span>
                  <span class="sm:hidden">&lt;</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">&lt; Prev</span>
                  <span class="sm:hidden">&lt;</span>
                </span>
              <?php endif; ?>

              <div class="hidden xs:flex gap-1">
                <?php for ($i = $dailyStartPage; $i <= $dailyEndPage; $i++): ?>
                  <a href="?recap_type=daily&date=<?= htmlspecialchars($date) ?>&notes=<?= htmlspecialchars($notesFilter) ?>&daily_page=<?= $i ?>"
                    class="<?= $i === $dailyPage ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 hover:bg-indigo-50' ?> px-3 py-2 border border-gray-300 rounded text-sm font-medium transition">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </div>

              <div class="xs:hidden px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded text-sm font-medium">
                <?= $dailyPage ?>
              </div>

              <?php if ($dailyPage < $totalDailyPages): ?>
                <a href="?recap_type=daily&date=<?= htmlspecialchars($date) ?>&notes=<?= htmlspecialchars($notesFilter) ?>&daily_page=<?= $dailyPage + 1 ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">Next &gt;</span>
                  <span class="sm:hidden">&gt;</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">Next &gt;</span>
                  <span class="sm:hidden">&gt;</span>
                </span>
              <?php endif; ?>

              <?php if ($dailyPage < $totalDailyPages): ?>
                <a href="?recap_type=daily&date=<?= htmlspecialchars($date) ?>&notes=<?= htmlspecialchars($notesFilter) ?>&daily_page=<?= $totalDailyPages ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
                  <span class="sm:hidden">Last</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
                  <span class="sm:hidden">Last</span>
                </span>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>

        <?php if ($recapType === 'monthly' && $totalMonthlyPages > 1): ?>
          <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
            <div class="text-sm text-gray-600 whitespace-nowrap">
              Page <?= $monthlyPage ?> of <?= $totalMonthlyPages ?>
            </div>
            <nav class="flex flex-wrap justify-center gap-1">
              <?php if ($monthlyPage > 1): ?>
                <a href="?recap_type=monthly&month=<?= htmlspecialchars($month) ?>&user_id=<?= htmlspecialchars($userFilter) ?>&monthly_page=1"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">&lt;&lt; First</span>
                  <span class="sm:hidden">First</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">&lt;&lt; First</span>
                  <span class="sm:hidden">First</span>
                </span>
              <?php endif; ?>

              <?php if ($monthlyPage > 1): ?>
                <a href="?recap_type=monthly&month=<?= htmlspecialchars($month) ?>&user_id=<?= htmlspecialchars($userFilter) ?>&monthly_page=<?= $monthlyPage - 1 ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">&lt; Prev</span>
                  <span class="sm:hidden">&lt;</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">&lt; Prev</span>
                  <span class="sm:hidden">&lt;</span>
                </span>
              <?php endif; ?>

              <div class="hidden xs:flex gap-1">
                <?php for ($i = $monthlyStartPage; $i <= $monthlyEndPage; $i++): ?>
                  <a href="?recap_type=monthly&month=<?= htmlspecialchars($month) ?>&user_id=<?= htmlspecialchars($userFilter) ?>&monthly_page=<?= $i ?>"
                    class="<?= $i === $monthlyPage ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 hover:bg-indigo-50' ?> px-3 py-2 border border-gray-300 rounded text-sm font-medium transition">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </div>

              <div class="xs:hidden px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded text-sm font-medium">
                <?= $monthlyPage ?>
              </div>

              <?php if ($monthlyPage < $totalMonthlyPages): ?>
                <a href="?recap_type=monthly&month=<?= htmlspecialchars($month) ?>&user_id=<?= htmlspecialchars($userFilter) ?>&monthly_page=<?= $monthlyPage + 1 ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">Next &gt;</span>
                  <span class="sm:hidden">&gt;</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">Next &gt;</span>
                  <span class="sm:hidden">&gt;</span>
                </span>
              <?php endif; ?>

              <?php if ($monthlyPage < $totalMonthlyPages): ?>
                <a href="?recap_type=monthly&month=<?= htmlspecialchars($month) ?>&user_id=<?= htmlspecialchars($userFilter) ?>&monthly_page=<?= $totalMonthlyPages ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
                  <span class="sm:hidden">Last</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
                  <span class="sm:hidden">Last</span>
                </span>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>

<?php include __DIR__ . '/footer.php'; ?>