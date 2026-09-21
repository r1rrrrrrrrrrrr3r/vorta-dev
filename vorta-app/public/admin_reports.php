<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_admin();

$dailyMin = settings_get_daily_min_reports($pdo);
$month = $_GET['month'] ?? date('Y-m');
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));

$limit = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM production_reports pr
  JOIN users u ON u.user_id = pr.user_id
  LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
  WHERE pr.report_date BETWEEN ? AND ?
");
$countStmt->execute([$start, $end]);
$totalReports = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalReports / $limit);

$startPage = max(1, $page - 2);
$endPage = min($totalPages, $startPage + 4);
if ($endPage - $startPage < 4) {
  $startPage = max(1, $endPage - 4);
}

$stmt = $pdo->prepare("
  SELECT pr.*, u.name, wf.workforce_name
  FROM production_reports pr
  JOIN users u ON u.user_id = pr.user_id
  LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
  WHERE pr.report_date BETWEEN ? AND ?
  ORDER BY pr.report_date DESC, pr.report_id DESC
  LIMIT $limit OFFSET $offset
");
$stmt->execute([$start, $end]);
$rows = $stmt->fetchAll();

$today = date('Y-m-d');
$shortLimit = 10;
$shortPage = isset($_GET['short_page']) ? max(1, (int)$_GET['short_page']) : 1;
$shortOffset = ($shortPage - 1) * $shortLimit;
$countShort = $pdo->prepare("
  SELECT COUNT(*) 
  FROM users u
  WHERE u.is_active = 1 
  AND u.user_id NOT IN (
    SELECT DISTINCT pr.user_id 
    FROM production_reports pr 
    WHERE pr.report_date = ? 
    GROUP BY pr.user_id 
    HAVING COUNT(pr.report_id) >= ?
  )
");
$countShort->execute([$today, $dailyMin]);

$short = $pdo->prepare("
  SELECT 
    u.user_id, 
    u.name, 
    u.email,
    e.position,
    COUNT(pr.report_id) as report_count,
    GROUP_CONCAT(DISTINCT pr.job_type ORDER BY pr.report_id SEPARATOR ', ') as job_types,
    GROUP_CONCAT(DISTINCT pr.title ORDER BY pr.report_id SEPARATOR ' | ') as report_titles
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.report_date = ?
  LEFT JOIN employees e ON e.user_id = u.user_id
  WHERE u.is_active = 1
  GROUP BY u.user_id
  HAVING report_count < ?
  ORDER BY report_count ASC, u.name
  LIMIT $shortLimit OFFSET $shortOffset
");
$short->execute([$today, $dailyMin]);
$short->execute([$today, $dailyMin]);
$countShort->execute([$today, $dailyMin]);
$countShort->execute([$today]);
$totalShort = (int)$countShort->fetchColumn();
$totalShortPages = ceil($totalShort / $shortLimit);

$shortStartPage = max(1, $shortPage - 2);
$shortEndPage = min($totalShortPages, $shortStartPage + 4);
if ($shortEndPage - $shortStartPage < 4) {
  $shortStartPage = max(1, $shortEndPage - 4);
}

$short = $pdo->prepare("
  SELECT 
    u.user_id, 
    u.name, 
    u.email,
    e.position,
    COUNT(pr.report_id) as report_count,
    GROUP_CONCAT(DISTINCT pr.job_type ORDER BY pr.report_id SEPARATOR ', ') as job_types,
    GROUP_CONCAT(DISTINCT pr.title ORDER BY pr.report_id SEPARATOR ' | ') as report_titles
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.report_date = ?
  LEFT JOIN employees e ON e.user_id = u.user_id
  WHERE u.is_active = 1
  GROUP BY u.user_id
  HAVING report_count < 2
  ORDER BY report_count ASC, u.name
  LIMIT $shortLimit OFFSET $shortOffset
");
$short->execute([$today]);
$shortRows = $short->fetchAll();

$statsStmt = $pdo->prepare("
  SELECT 
    COUNT(*) as total_active_users,
    SUM(CASE WHEN report_count >= ? THEN 1 ELSE 0 END) as completed_users,
    SUM(CASE WHEN report_count > 0 AND report_count < ? THEN 1 ELSE 0 END) as partial_users,
    SUM(CASE WHEN report_count = 0 THEN 1 ELSE 0 END) as zero_users
  FROM (
    SELECT 
      u.user_id,
      COUNT(pr.report_id) as report_count
    FROM users u
    LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.report_date = ?
    WHERE u.is_active = 1
    GROUP BY u.user_id
  ) as user_reports
");
$statsStmt->execute([$dailyMin, $dailyMin, $today]);
$statsStmt->execute([$today]);
$stats = $statsStmt->fetch();

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vorta Prodtracker - Admin Reports</title>
  <link rel="stylesheet" href="css/output.css">
</head>

<body>
  <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 md:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <h1 class="text-2xl font-bold text-gray-800">All Reports</h1>
          <form class="flex flex-col sm:flex-row items-center gap-2">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month) ?>"
              class="px-3 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <button type="submit" class="px-4 py-2 w-full md:w-[68px] bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
              Filter
            </button>
          </form>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left border-b border-gray-200">
                <th class="pb-3 font-medium text-gray-600">Date</th>
                <th class="pb-3 font-medium text-gray-600">Name</th>
                <th class="pb-3 font-medium text-gray-600">Type</th>
                <th class="pb-3 font-medium text-gray-600">Title</th>
                <th class="pb-3 font-medium text-gray-600">Work Force</th>
                <th class="pb-3 font-medium text-gray-600">Status</th>
                <th class="pb-3 font-medium text-gray-600">Proof</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                    <?php echo htmlspecialchars($r['report_date']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                    <?php echo htmlspecialchars($r['name']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-sm text-gray-800">
                    <?php echo htmlspecialchars($r['job_type']) ?>
                  </td>
                  <td class="py-4 text-sm text-gray-800">
                    <?php echo htmlspecialchars($r['title']) ?>
                  </td>
                  <td class="py-4 text-sm text-gray-800">
                    <?php echo htmlspecialchars($r['workforce_name']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?php echo $r['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                      <?php echo htmlspecialchars($r['status']) ?>
                    </span>
                  </td>
                  <td class="py-4 whitespace-nowrap">
                    <?php if ($r): ?>
                      <button onclick="openModal(<?php echo $r['report_id'] ?>)"
                        class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 text-sm transition">
                        Detail
                      </button>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
            <div class="text-sm text-gray-600 whitespace-nowrap">
              Page <?= $page ?> of <?= $totalPages ?>
            </div>
            <nav class="flex flex-wrap justify-center gap-1">
              <?php if ($page > 1): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=1&short_page=<?= $shortPage ?>"
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

              <?php if ($page > 1): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page - 1 ?>&short_page=<?= $shortPage ?>"
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
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                  <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $i ?>&short_page=<?= $shortPage ?>"
                    class="<?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 hover:bg-indigo-50' ?> px-3 py-2 border border-gray-300 rounded text-sm font-medium transition">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </div>

              <div class="xs:hidden px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded text-sm font-medium">
                <?= $page ?>
              </div>

              <?php if ($page < $totalPages): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page + 1 ?>&short_page=<?= $shortPage ?>"
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

              <?php if ($page < $totalPages): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $totalPages ?>&short_page=<?= $shortPage ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
                  <span class="sm:hidden">Last</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&lt;</span>
                  <span class="sm:hidden">Last</span>
                </span>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 md:p-8">
        <div class="flex flex-col gap-4 mb-6">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="text-xl font-bold text-gray-800">
              Daily Report Completion - <?php echo htmlspecialchars(date('d F Y', strtotime($today))) ?>
            </h2>
            <button onclick="location.reload()"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium whitespace-nowrap">
              Refresh
            </button>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div class="flex items-center gap-2 p-3 bg-green-50 rounded-lg">
              <div class="w-3 h-3 bg-green-500 rounded-full"></div>
              <div>
                <div class="text-gray-600">Completed</div>
                <div class="font-bold text-green-800"><?= $stats['completed_users'] ?? 0 ?></div>
              </div>
            </div>
            <div class="flex items-center gap-2 p-3 bg-yellow-50 rounded-lg">
              <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
              <div>
                <div class="text-gray-600">Partial</div>
                <div class="font-bold text-yellow-800"><?= $stats['partial_users'] ?? 0 ?></div>
              </div>
            </div>
            <div class="flex items-center gap-2 p-3 bg-red-50 rounded-lg">
              <div class="w-3 h-3 bg-red-500 rounded-full"></div>
              <div>
                <div class="text-gray-600">No Reports</div>
                <div class="font-bold text-red-800"><?= $stats['zero_users'] ?? 0 ?></div>
              </div>
            </div>
            <div class="flex items-center gap-2 p-3 bg-gray-100 rounded-lg">
              <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
              <div>
                <div class="text-gray-600">Total Active</div>
                <div class="font-bold text-gray-800"><?= $stats['total_active_users'] ?? 0 ?></div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!$shortRows): ?>
          <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <div class="text-green-600 text-4xl mb-2"></div>
            <p class="text-green-800 font-medium text-lg">All staff have completed their daily reports!</p>
            <p class="text-green-600 text-sm mt-1">Every active employee has submitted at least 2 reports today.</p>
          </div>
        <?php else: ?>
          <div class="mb-6">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
              <span>Completion Progress</span>
              <span><?= $stats['completed_users'] ?? 0 ?>/<?= $stats['total_active_users'] ?? 0 ?> employees</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-green-600 h-2 rounded-full"
                style="width: <?= $stats['total_active_users'] > 0 ? round(($stats['completed_users'] / $stats['total_active_users']) * 100) : 0 ?>%">
              </div>
            </div>
          </div>

          <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-full">
              <thead>
                <tr class="text-left border-b border-gray-200">
                  <th class="pb-3 font-medium text-gray-600">Employee</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Position</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Reports</th>
                  <th class="pb-3 font-medium text-gray-600">Submitted Work</th>
                  <th class="pb-3 font-medium text-gray-600 text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php foreach ($shortRows as $s):
                  $reportCount = (int)$s['report_count'];
                  $statusText = $reportCount >= $dailyMin ? 'Completed' : ($reportCount === 0 ? 'No Reports' : $reportCount . ' Report' . ($reportCount > 1 ? 's' : ''));
                ?>
                  <tr class="hover:bg-gray-50 transition">
                    <td class="py-4">
                      <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($s['name']) ?></span>
                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($s['email']) ?></span>
                      </div>
                    </td>
                    <td class="py-4 text-sm text-gray-600 text-center">
                      <?php echo htmlspecialchars($s['position'] ?? '-') ?>
                    </td>
                    <td class="py-4 text-center">
                      <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                <?= $reportCount === 0 ? 'bg-red-100 text-red-800' : ($reportCount === 1 ? 'bg-yellow-100 text-yellow-800' :
                      'bg-green-100 text-green-800') ?>">
                        <?= $reportCount ?>
                      </span>
                    </td>
                    <td class="py-4 text-sm text-gray-600 max-w-xs">
                      <?php if (!empty($s['report_titles'])): ?>
                        <div class="truncate" title="<?= htmlspecialchars($s['report_titles']) ?>">
                          <?= htmlspecialchars($s['report_titles']) ?>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                          Types: <?= htmlspecialchars($s['job_types'] ?? 'None') ?>
                        </div>
                      <?php else: ?>
                        <span class="text-gray-400">No reports submitted</span>
                      <?php endif; ?>
                    </td>
                    <td class="py-4 text-center">
                      <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                <?= $reportCount === 0 ? 'bg-red-100 text-red-800' : ($reportCount < $dailyMin ? 'bg-yellow-100 text-yellow-800' :
                      'bg-green-100 text-green-800') ?>">
                        <?= $reportCount ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="md:hidden space-y-4">
            <?php foreach ($shortRows as $s):
              $reportCount = (int)$s['report_count'];
              $statusText = $reportCount === 0 ? 'No Reports' : ($reportCount === 1 ? '1 Report' : 'Completed');
              $statusColor = $reportCount === 0 ? 'red' : ($reportCount === 1 ? 'yellow' : 'green');
            ?>
              <div class="border border-gray-200 rounded-lg p-4 bg-white shadow-sm">
                <div class="flex justify-between items-start">
                  <div>
                    <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($s['name']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($s['email']) ?></p>
                    <p class="text-sm text-gray-600 mt-2">
                      <span class="font-medium">Position:</span>
                      <?php echo htmlspecialchars($s['position'] ?? '-') ?>
                    </p>
                  </div>
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
            <?= $reportCount === 0 ? 'bg-red-100 text-red-800' : ($reportCount === 1 ? 'bg-yellow-100 text-yellow-800' :
                  'bg-green-100 text-green-800') ?>">
                    <?= $reportCount ?>
                  </span>
                </div>

                <div class="mt-3 pt-3 border-t border-gray-100">
                  <p class="text-xs text-gray-600 mb-1">
                    <span class="font-medium">Submitted Work:</span>
                  </p>
                  <?php if (!empty($s['report_titles'])): ?>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($s['report_titles']) ?></p>
                    <p class="text-xs text-gray-500 mt-1">Types: <?= htmlspecialchars($s['job_types'] ?? 'None') ?></p>
                  <?php else: ?>
                    <p class="text-sm text-gray-400">No reports submitted</p>
                  <?php endif; ?>
                </div>

                <div class="mt-3 flex justify-end">
                  <span class="px-2.5 py-1 rounded-full text-xs font-medium
            <?= $reportCount === 0 ? 'bg-red-100 text-red-800' : ($reportCount === 1 ? 'bg-yellow-100 text-yellow-800' :
                  'bg-green-100 text-green-800') ?>">
                    <?= $statusText ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($totalShortPages > 1): ?>
          <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
            <div class="text-sm text-gray-600 whitespace-nowrap">
              Showing <?= count($shortRows) ?> of <?= $totalShort ?> employees - Page <?= $shortPage ?> of <?= $totalShortPages ?>
            </div>
            <nav class="flex flex-wrap justify-center gap-1">
              <?php if ($shortPage > 1): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page ?>&short_page=1"
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

              <?php if ($shortPage > 1): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page ?>&short_page=<?= $shortPage - 1 ?>"
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
                <?php for ($i = $shortStartPage; $i <= $shortEndPage; $i++): ?>
                  <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page ?>&short_page=<?= $i ?>"
                    class="<?= $i === $shortPage ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 hover:bg-indigo-50' ?> px-3 py-2 border border-gray-300 rounded text-sm font-medium transition">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </div>

              <div class="xs:hidden px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded text-sm font-medium">
                <?= $shortPage ?>
              </div>

              <?php if ($shortPage < $totalShortPages): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page ?>&short_page=<?= $shortPage + 1 ?>"
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

              <?php if ($shortPage < $totalShortPages): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page ?>&short_page=<?= $totalShortPages ?>"
                  class="px-2 py-2 sm:px-3 bg-white text-indigo-600 border border-gray-300 rounded hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
                  <span class="sm:hidden">Last</span>
                </a>
              <?php else: ?>
                <span class="px-2 py-2 sm:px-3 bg-gray-100 text-gray-400 border border-gray-300 rounded text-sm font-medium cursor-not-allowed whitespace-nowrap">
                  <span class="hidden sm:inline">Last &gt;&lt;</span>
                  <span class="sm:hidden">Last</span>
                </span>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold text-gray-800">Report Detail</h3>
            <button onclick="closeModal()" aria-label="Close"
              class="flex items-center justify-center w-9 h-9 rounded-full text-2xl leading-none text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition">
              &times;
            </button>
        </div>
        <div id="modalContent"></div>
      </div>
    </div>
  </div>
</body>

</html>

<script>
  function openModal(reportId) {
    fetch(`get_report_detail.php?id=${reportId}`)
      .then(response => response.text())
      .then(data => {
        document.getElementById('modalContent').innerHTML = data;
        document.getElementById('reportModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      });
  }

  function closeModal() {
    document.getElementById('reportModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
  }
</script>

<?php include __DIR__ . '/footer.php'; ?>