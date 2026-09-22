<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/tenant.php';
require_admin();
$company_id = current_company_id();

$detailOwnerId = null;
$detailSelf = basename(__FILE__);

function report_detail_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function report_detail_fetch(PDO $pdo, int $reportId, ?int $ownerId): ?array
{
    $sql = "SELECT pr.*, u.name AS user_name, wf.workforce_name
            FROM production_reports pr
            LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
            JOIN users u ON u.user_id = pr.user_id
            WHERE pr.report_id = ? AND pr.company_id = ?";
    $params = [$reportId, current_company_id()];
    if ($ownerId !== null) {
        $sql .= " AND pr.user_id = ?";
        $params[] = $ownerId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetch();
    return $found ?: null;
}

if (isset($_GET['proof_image'])) {
    $row = report_detail_fetch($pdo, (int)$_GET['proof_image'], $detailOwnerId);
    $file = $row ? basename((string)$row['proof_image']) : '';
    $path = __DIR__ . '/../uploads/' . $file;
    $mime = ($file !== '' && is_file($path)) ? mime_content_type($path) : false;
    if (!$mime || strpos($mime, 'image/') !== 0) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

if (isset($_GET['detail'])) {
    $row = report_detail_fetch($pdo, (int)$_GET['detail'], $detailOwnerId);
    if (!$row) {
        http_response_code(404);
        echo '<p class="text-red-600">Report not found or access denied.</p>';
        exit;
    }

    $status = $row['status'] ?? 'Progress';
    $statusClass = $status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
    $timestamp = strtotime((string) $row['report_date']);
    $dateLabel = $timestamp ? date('d M Y', $timestamp) : '-';
    $description = trim((string) ($row['description'] ?? ''));
    $rawLink = trim((string) ($row['proof_link'] ?? ''));
    $isHttp = (bool) preg_match('#^https?://#i', $rawLink);
    $imageFile = basename(trim((string) ($row['proof_image'] ?? '')));
    $hasImage = $imageFile !== '';
    $imageExists = $hasImage && is_file(__DIR__ . '/../uploads/' . $imageFile);
    $imageUrl = $detailSelf . '?proof_image=' . (int) $row['report_id'];

    $card = 'background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;';
    $label = 'font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-faint);margin-bottom:4px;';
    $value = 'font-size:14px;font-weight:600;color:var(--text);word-break:break-word;';
    $section = 'font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;';
    ?>
<div style="display:flex;flex-direction:column;gap:18px;">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
    <h4 style="font-size:18px;font-weight:700;line-height:1.3;color:var(--text);word-break:break-word;"><?= report_detail_e($row['title']) ?></h4>
    <span class="px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap <?= $statusClass ?>"><?= report_detail_e($status) ?></span>
  </div>

  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Date</div>
      <div style="<?= $value ?>"><?= report_detail_e($dateLabel) ?></div>
    </div>
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Submitted By</div>
      <div style="<?= $value ?>"><?= report_detail_e($row['user_name']) ?></div>
    </div>
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Job Type</div>
      <div style="<?= $value ?>"><?= report_detail_e($row['job_type'] ?? '-') ?></div>
    </div>
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Work Force</div>
      <div style="<?= $value ?>"><?= report_detail_e($row['workforce_name'] ?? '-') ?></div>
    </div>
  </div>

  <div>
    <div style="<?= $section ?>">Description</div>
    <div style="<?= $card ?>font-size:14px;line-height:1.6;color:var(--text);word-break:break-word;">
      <?php if ($description !== ''): ?>
        <?= nl2br(report_detail_e($description)) ?>
      <?php else: ?>
        <span style="color:var(--text-faint);font-style:italic;">No description provided.</span>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div style="<?= $section ?>">Proof</div>
    <?php if ($rawLink === '' && !$hasImage): ?>
      <div style="<?= $card ?>font-size:14px;color:var(--text-faint);font-style:italic;">No proof attached.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php if ($rawLink !== ''): ?>
          <div style="<?= $card ?>display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div style="min-width:0;flex:1;">
              <div style="<?= $label ?>">Link</div>
              <?php if ($isHttp): ?>
                <a href="<?= report_detail_e($rawLink) ?>" target="_blank" rel="noopener noreferrer"
                  style="font-size:14px;color:var(--brand);text-decoration:underline;word-break:break-all;"><?= report_detail_e($rawLink) ?></a>
              <?php else: ?>
                <span style="font-size:14px;color:var(--text);word-break:break-all;"><?= report_detail_e($rawLink) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($isHttp): ?>
              <a href="<?= report_detail_e($rawLink) ?>" target="_blank" rel="noopener noreferrer"
                class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition whitespace-nowrap">Open</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($hasImage): ?>
          <div style="<?= $card ?>">
            <div style="<?= $label ?>margin-bottom:8px;">Photo</div>
            <?php if ($imageExists): ?>
              <a href="<?= report_detail_e($imageUrl) ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?= report_detail_e($imageUrl) ?>" alt="Proof photo" loading="lazy"
                  onerror="this.parentNode.style.display='none';this.parentNode.nextElementSibling.style.display='block';"
                  style="display:block;max-width:100%;max-height:300px;margin:0 auto;border-radius:10px;object-fit:contain;">
              </a>
              <div style="display:none;font-size:14px;color:var(--text-faint);font-style:italic;">The photo could not be loaded.</div>
            <?php else: ?>
              <div style="font-size:14px;color:var(--text-faint);font-style:italic;">The photo file could not be found on the server.</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
    exit;
}


function report_status_info(int $count, int $min): array
{
  if ($count === 0) {
    return ['No Reports', 'bg-red-100 text-red-800'];
  }
  if ($count < $min) {
    return [$count . ' Report' . ($count > 1 ? 's' : ''), 'bg-yellow-100 text-yellow-800'];
  }
  return ['Completed', 'bg-green-100 text-green-800'];
}

$dailyMin = settings_get_daily_min_reports($pdo);
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
  $month = date('Y-m');
}
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
  WHERE pr.company_id = ? AND pr.report_date BETWEEN ? AND ?
");
$countStmt->execute([$company_id, $start, $end]);
$totalReports = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalReports / $limit);

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
  WHERE pr.company_id = ? AND pr.report_date BETWEEN ? AND ?
  ORDER BY pr.report_date DESC, pr.report_id DESC
  LIMIT $limit OFFSET $offset
");
$stmt->execute([$company_id, $start, $end]);
$rows = $stmt->fetchAll();

$today = date('Y-m-d');
$shortLimit = 10;
$shortPage = isset($_GET['short_page']) ? max(1, (int)$_GET['short_page']) : 1;
$shortOffset = ($shortPage - 1) * $shortLimit;

$countShort = $pdo->prepare("
  SELECT COUNT(*)
  FROM users u
  WHERE u.company_id = ? AND u.is_active = 1 AND u.role <> 'admin'
");
$countShort->execute([$company_id]);
$totalShort = (int)$countShort->fetchColumn();
$totalShortPages = (int)ceil($totalShort / $shortLimit);

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
    COUNT(pr.report_id) AS report_count,
    GROUP_CONCAT(DISTINCT pr.job_type ORDER BY pr.report_id SEPARATOR ', ') AS job_types,
    GROUP_CONCAT(DISTINCT pr.title ORDER BY pr.report_id SEPARATOR ' | ') AS report_titles
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.company_id = u.company_id AND pr.report_date = ?
  LEFT JOIN employees e ON e.user_id = u.user_id AND e.company_id = u.company_id
  WHERE u.company_id = ? AND u.is_active = 1 AND u.role <> 'admin'
  GROUP BY u.user_id, u.name, u.email, e.position
  ORDER BY report_count ASC, u.name
  LIMIT $shortLimit OFFSET $shortOffset
");
$short->execute([$today, $company_id]);
$shortRows = $short->fetchAll();

$statsStmt = $pdo->prepare("
  SELECT
    COUNT(*) AS total_active_users,
    COALESCE(SUM(CASE WHEN report_count >= ? THEN 1 ELSE 0 END), 0) AS completed_users,
    COALESCE(SUM(CASE WHEN report_count > 0 AND report_count < ? THEN 1 ELSE 0 END), 0) AS partial_users,
    COALESCE(SUM(CASE WHEN report_count = 0 THEN 1 ELSE 0 END), 0) AS zero_users
  FROM (
    SELECT
      u.user_id,
      COUNT(pr.report_id) AS report_count
    FROM users u
    LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.company_id = u.company_id AND pr.report_date = ?
    WHERE u.company_id = ? AND u.is_active = 1 AND u.role <> 'admin'
    GROUP BY u.user_id
  ) AS user_reports
");
$statsStmt->execute([$dailyMin, $dailyMin, $today, $company_id]);
$stats = $statsStmt->fetch();

$totalActive = (int)($stats['total_active_users'] ?? 0);
$completedUsers = (int)($stats['completed_users'] ?? 0);
$completionPct = $totalActive > 0 ? round(($completedUsers / $totalActive) * 100) : 0;

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
            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"
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
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="7" class="py-6 text-center text-sm text-gray-400">
                    No reports found for this month.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr class="hover:bg-gray-50 transition">
                    <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                      <?= htmlspecialchars($r['report_date']) ?>
                    </td>
                    <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                      <?= htmlspecialchars($r['name']) ?>
                    </td>
                    <td class="py-4 whitespace-nowrap text-sm text-gray-800">
                      <?= htmlspecialchars($r['job_type']) ?>
                    </td>
                    <td class="py-4 text-sm text-gray-800">
                      <?= htmlspecialchars($r['title']) ?>
                    </td>
                    <td class="py-4 text-sm text-gray-800">
                      <?= htmlspecialchars($r['workforce_name'] ?? '-') ?>
                    </td>
                    <td class="py-4 whitespace-nowrap">
                      <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $r['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                        <?= htmlspecialchars($r['status']) ?>
                      </span>
                    </td>
                    <td class="py-4 whitespace-nowrap">
                      <button onclick="openModal(<?= (int)$r['report_id'] ?>)"
                        class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 text-sm transition">
                        Detail
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
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
                  <span class="hidden sm:inline">Last &gt;&gt;</span>
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
              Daily Report Completion - <?= htmlspecialchars(date('d F Y', strtotime($today))) ?>
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
                <div class="font-bold text-green-800"><?= $completedUsers ?></div>
              </div>
            </div>
            <div class="flex items-center gap-2 p-3 bg-yellow-50 rounded-lg">
              <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
              <div>
                <div class="text-gray-600">Partial</div>
                <div class="font-bold text-yellow-800"><?= (int)($stats['partial_users'] ?? 0) ?></div>
              </div>
            </div>
            <div class="flex items-center gap-2 p-3 bg-red-50 rounded-lg">
              <div class="w-3 h-3 bg-red-500 rounded-full"></div>
              <div>
                <div class="text-gray-600">No Reports</div>
                <div class="font-bold text-red-800"><?= (int)($stats['zero_users'] ?? 0) ?></div>
              </div>
            </div>
            <div class="flex items-center gap-2 p-3 bg-gray-100 rounded-lg">
              <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
              <div>
                <div class="text-gray-600">Total Active</div>
                <div class="font-bold text-gray-800"><?= $totalActive ?></div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!$shortRows): ?>
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
            <p class="text-gray-600 font-medium text-lg">No active staff found.</p>
          </div>
        <?php else: ?>
          <div class="mb-6">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
              <span>Completion Progress</span>
              <span><?= $completedUsers ?>/<?= $totalActive ?> employees</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-green-600 h-2 rounded-full" style="width: <?= $completionPct ?>%"></div>
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
                  [$statusText, $statusClass] = report_status_info($reportCount, $dailyMin);
                ?>
                  <tr class="hover:bg-gray-50 transition">
                    <td class="py-4">
                      <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['name']) ?></span>
                        <span class="text-xs text-gray-500"><?= htmlspecialchars($s['email']) ?></span>
                      </div>
                    </td>
                    <td class="py-4 text-sm text-gray-600 text-center">
                      <?= htmlspecialchars($s['position'] ?? '-') ?>
                    </td>
                    <td class="py-4 text-center">
                      <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold <?= $statusClass ?>">
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
                      <span class="px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap <?= $statusClass ?>">
                        <?= htmlspecialchars($statusText) ?>
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
              [$statusText, $statusClass] = report_status_info($reportCount, $dailyMin);
            ?>
              <div class="border border-gray-200 rounded-lg p-4 bg-white shadow-sm">
                <div class="flex justify-between items-start">
                  <div>
                    <h3 class="font-medium text-gray-800"><?= htmlspecialchars($s['name']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($s['email']) ?></p>
                    <p class="text-sm text-gray-600 mt-2">
                      <span class="font-medium">Position:</span>
                      <?= htmlspecialchars($s['position'] ?? '-') ?>
                    </p>
                  </div>
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold <?= $statusClass ?>">
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
                  <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                    <?= htmlspecialchars($statusText) ?>
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

  <div id="reportModal" class="fixed inset-0 hidden z-50" style="background: rgba(15, 23, 42, 0.55);">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white overflow-hidden"
      style="width: calc(100% - 2rem); max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; color: #fff; background: linear-gradient(135deg, #4f46e5, #7c3aed);">
        <div>
          <p style="font-size: 11px; letter-spacing: .1em; text-transform: uppercase; opacity: .8;">Production Report</p>
          <h3 style="font-size: 17px; font-weight: 700; line-height: 1.2;">Report Detail</h3>
        </div>
        <button onclick="closeModal()" aria-label="Close"
          style="width: 32px; height: 32px; border-radius: 9999px; background: rgba(255, 255, 255, .18); color: #fff; font-size: 20px; line-height: 1; cursor: pointer;">
          &times;
        </button>
      </div>
      <div id="modalContent" style="padding: 20px; overflow-y: auto;"></div>
      <div style="padding: 12px 20px; text-align: right; border-top: 1px solid var(--border);">
        <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium transition">
          Close
        </button>
      </div>
    </div>
  </div>

  <script>
    function openModal(reportId) {
      document.getElementById('modalContent').innerHTML = '<p class="text-sm text-gray-500">Loading...</p>';
      document.getElementById('reportModal').classList.remove('hidden');
      document.body.style.overflow = 'hidden';

      fetch('admin_reports.php?detail=' + encodeURIComponent(reportId), {
          credentials: 'same-origin'
        })
        .then(function(response) {
          return response.text();
        })
        .then(function(data) {
          document.getElementById('modalContent').innerHTML = data;
          document.getElementById('reportModal').classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        })
        .catch(function() {
          document.getElementById('modalContent').innerHTML = '<p class="text-red-600">An error occurred while loading the data</p>';
          document.getElementById('reportModal').classList.remove('hidden');
        });
    }

    function closeModal() {
      document.getElementById('reportModal').classList.add('hidden');
      document.body.style.overflow = 'auto';
    }

    document.getElementById('reportModal').addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeModal();
    });
  </script>

  <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>