<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/tenant.php';
require_login();
$company_id = current_company_id();

$month = $_GET['month'] ?? date('Y-m');
$start = $month . "-01";
$target = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
$setup = [];
if (($_SESSION['user']['role'] ?? '') === 'admin') {
    $countStmt = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM users WHERE company_id = ?) AS users,
        (SELECT COUNT(*) FROM employees WHERE company_id = ?) AS employees,
        (SELECT COUNT(*) FROM work_force WHERE company_id = ?) AS workforces,
        (SELECT COUNT(*) FROM company_invitations WHERE company_id = ? AND accepted_at IS NULL AND expires_at > NOW()) AS pending_invites,
        (SELECT COUNT(*) FROM app_settings WHERE company_id = ? AND setting_key IN ('monthly_target_min', 'monthly_target_max', 'daily_min_reports')) AS configured_settings");
    $countStmt->execute([$company_id, $company_id, $company_id, $company_id, $company_id]);
    $setup = $countStmt->fetch() ?: [];
}
$end = date('Y-m-t', strtotime($start));
$stmt = $pdo->prepare("
  SELECT u.user_id, u.name, u.role,
         COALESCE(SUM(pr.report_date BETWEEN ? AND ?), 0) as dummy,
         COUNT(pr.report_id) as total
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.company_id = u.company_id AND pr.report_date BETWEEN ? AND ?
  WHERE u.is_active = 1 AND u.company_id = ?
  GROUP BY u.user_id
  ORDER BY total DESC, u.name
");
$stmt->execute([$start, $end, $start, $end, $company_id]);
$users = $stmt->fetchAll();

$totalStaff = count($users);
$totalReportsMonth = array_sum(array_column($users, 'total'));
$onTrackStaff = count(array_filter($users, fn($u) => (int)$u['total'] >= $target['min']));
$avgPerStaff = $totalStaff > 0 ? round($totalReportsMonth / $totalStaff, 1) : 0;
$onTrackPct = $totalStaff > 0 ? round(($onTrackStaff / $totalStaff) * 100) : 0;

$types = $pdo->prepare("SELECT job_type, COUNT(*) c FROM production_reports WHERE company_id = ? AND report_date BETWEEN ? AND ? GROUP BY job_type ORDER BY c DESC");
$types->execute([$company_id, $start, $end]);
$typeRows = $types->fetchAll();
$typeTotal = array_sum(array_column($typeRows, 'c'));

$limit = 5;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$totalData = count($users);
$totalPage = ceil($totalData / $limit);
$usersPage = array_slice($users, $offset, $limit);
$startPage = max(1, $page - 2);
$endPage = min($totalPage, $startPage + 4);
if ($endPage - $startPage < 4) {
    $startPage = max(1, $endPage - 4);
}

$pieColors = ['#6366f1', '#3b82f6', '#10b981', '#f59e0b', '#f43f5e', '#8b5cf6', '#14b8a6', '#eab308'];

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
    $row['color'] = isset($row['detail']) ? '#94a3b8' : $pieColors[$i % count($pieColors)];
}
unset($row);

$serverThemePref = $_SESSION['user']['theme'] ?? 'system';
if (!in_array($serverThemePref, ['light', 'dark', 'system'], true)) {
    $serverThemePref = 'system';
}
$serverResolvedTheme = $serverThemePref === 'dark' ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme-pref="<?= htmlspecialchars($serverThemePref) ?>" data-theme="<?= htmlspecialchars($serverResolvedTheme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vorta Production Dashboard</title>
  <style>
    html{background:#f3f4f6}
    html[data-theme="dark"]{background:#0f172a}
    @media (prefers-color-scheme: dark){
      html[data-theme-pref="system"]{background:#0f172a}
    }
    body{background-color:transparent}

    .progress-bar { height: 8px; border-radius: 4px; }
    .progress-fill { height: 100%; border-radius: 4px; transition: width 0.4s ease; }
    .stat-card {
      display: flex; align-items: center; gap: 14px; padding: 18px; border-radius: 14px;
      background: var(--surface, #fff);
      box-shadow: var(--shadow-card, 0 1px 3px rgba(0,0,0,.06), 0 6px 18px -8px rgba(0,0,0,.12));
    }
    .stat-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex: 0 0 44px; border-radius: 12px; }
    .stat-icon svg { width: 22px; height: 22px; }
    .stat-value { font-size: 22px; font-weight: 700; line-height: 1.1; }
    .stat-label { font-size: 12.5px; color: var(--text-muted, #6b7280); margin-top: 2px; }
    .rank-badge {
      display: inline-flex; align-items: center; justify-content: center;
      width: 22px; height: 22px; border-radius: 999px; font-size: 11px; font-weight: 700;
      background: var(--surface-3, #f3f4f6); color: var(--text-muted, #6b7280); flex: 0 0 22px;
    }

    .chart-card { display: flex; flex-direction: column; }
    .chart-inner { display: flex; flex-direction: column; flex: 1; padding: 24px; }
    .chart-layout {
      display: flex; align-items: center; justify-content: center;
      flex-wrap: wrap; gap: 20px 28px;
      margin: auto 0;
      padding: 12px 0;
    }
    .donut-wrap { position: relative; width: 168px; height: 168px; flex: 0 0 168px; }
    .donut-center {
      position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
      text-align: center; pointer-events: none;
    }
    .donut-center .num { font-size: 24px; font-weight: 700; color: var(--text, #1f2937); line-height: 1; }
    .donut-center .lbl { font-size: 11px; color: var(--text-muted, #6b7280); margin-top: 2px; }

    .legend-list { flex: 1 1 190px; min-width: 0; max-width: 100%; }
    .legend-row {
      display: grid; grid-template-columns: 10px minmax(0, 1fr) auto;
      align-items: center; column-gap: 10px; padding: 7px 0;
    }
    .legend-row + .legend-row { border-top: 1px solid var(--border, #e5e7eb); }
    .legend-dot { width: 10px; height: 10px; border-radius: 999px; }
    .legend-name {
      font-size: 13px; color: var(--text, #1f2937);
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .legend-count { font-size: 12.5px; font-weight: 600; color: var(--text, #1f2937); white-space: nowrap; }
    .legend-pct { font-weight: 500; color: var(--text-faint, #9ca3af); margin-left: 4px; }

    .chart-empty {
      flex: 1; min-height: 160px; display: flex; align-items: center; justify-content: center;
      font-size: 14px; color: var(--text-faint, #9ca3af);
    }
    .chart-note {
      margin-top: 8px; padding-top: 14px; border-top: 1px solid var(--border, #e5e7eb);
      font-size: 13px; color: var(--text-muted, #6b7280);
    }
  </style>
  <link rel="stylesheet" href="css/output.css">
  <?php include_once __DIR__ . '/ui_head.php'; ?>
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/header.php'; ?>

<div class="container mx-auto px-4 py-8">
  <header class="mb-6">
    <h1 class="text-xl sm:text-2xl md:text-3xl sm:text-start text-center font-bold text-gray-800">
      Production Dashboard
    </h1>
    <div class="flex sm:flex-row flex-col items-center justify-between md:mt-4 mt-0 gap-2 md:gap-0">
      <span class="md:text-xl text-lg font-semibold text-indigo-600"><?php echo htmlspecialchars($month) ?></span>
      <form class="flex gap-2">
        <input type="month" name="month" value="<?php echo htmlspecialchars($month) ?>"
          class="md:w-full w-[178px] px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
          Filter
        </button>
      </form>
    </div>
  </header>

  <style>
    .vorta-setup { display:flex; align-items:flex-start; gap:16px; margin-bottom:24px; padding:20px; background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:var(--shadow-card,0 1px 3px rgba(0,0,0,.06),0 6px 18px -8px rgba(0,0,0,.12)); }
    .vorta-setup__icon { flex:0 0 44px; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:rgba(99,102,241,.12); }
    .vorta-setup__icon svg { display:block; color:#4f46e5; }
    .vorta-setup__body { min-width:0; flex:1; }
    .vorta-setup__title { margin:0; font-size:20px; font-weight:700; color:var(--text,#1f2937); }
    .vorta-setup__subtitle { margin:4px 0 0; font-size:14px; color:var(--text-muted,#6b7280); }
    .vorta-setup__steps { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
    .vorta-step { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; border-radius:10px; font-size:14px; font-weight:600; text-decoration:none; border:1px solid var(--border,#e5e7eb); color:var(--text,#1f2937); background:var(--surface,#fff); transition:background .15s ease,border-color .15s ease; }
    .vorta-step:hover { background:var(--surface-3,#f3f4f6); }
    .vorta-step__num { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:999px; font-size:11px; font-weight:700; background:var(--surface-3,#f3f4f6); color:var(--text-muted,#6b7280); }
    .vorta-step--primary { background:#4f46e5; border-color:#4f46e5; color:#fff; }
    .vorta-step--primary:hover { background:#4338ca; }
    .vorta-step--primary .vorta-step__num { background:rgba(255,255,255,.25); color:#fff; }
    .vorta-step--complete { border-color:#bbf7d0; color:#166534; background:#f0fdf4; }
    .vorta-step--complete:hover { background:#dcfce7; }
    .vorta-step--complete .vorta-step__num { background:#dcfce7; color:#15803d; }
    @media (max-width:480px) { .vorta-setup { padding:16px; } .vorta-setup__icon { display:none; } .vorta-step { padding:9px 12px; } }
  </style>
  <?php
    $setupHasTeam = !empty($setup) && ((int)$setup['users'] > 1 || (int)$setup['pending_invites'] > 0);
    $setupHasEmployees = !empty($setup) && (int)$setup['employees'] > 0;
    $setupHasTargets = !empty($setup) && (int)$setup['configured_settings'] >= 3;
  ?>
  <?php if (!empty($setup) && (!$setupHasTeam || !$setupHasEmployees || !$setupHasTargets)): ?>
    <section class="vorta-setup">
      <div class="vorta-setup__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
      </div>
      <div class="vorta-setup__body">
        <h2 class="vorta-setup__title">Finish setting up your workspace</h2>
        <p class="vorta-setup__subtitle">Invite your team, assign employees, and set targets before collecting reports.</p>
        <div class="vorta-setup__steps">
          <a href="admin_master_data.php?tab=users" class="vorta-step <?= $setupHasTeam ? 'vorta-step--complete' : 'vorta-step--primary' ?>">
            <span class="vorta-step__num"><?= $setupHasTeam ? '✓' : '1' ?></span> Invite users<?php if ((int)($setup['pending_invites'] ?? 0) > 0): ?> (<?= (int)$setup['pending_invites'] ?> pending)<?php endif; ?>
          </a>
          <a href="admin_master_data.php?tab=employees" class="vorta-step <?= $setupHasEmployees ? 'vorta-step--complete' : '' ?>">
            <span class="vorta-step__num"><?= $setupHasEmployees ? '✓' : '2' ?></span> Assign employees<?php if ($setupHasEmployees): ?> (<?= (int)$setup['employees'] ?>)<?php endif; ?>
          </a>
          <a href="admin_master_data.php?tab=settings" class="vorta-step <?= $setupHasTargets ? 'vorta-step--complete' : '' ?>">
            <span class="vorta-step__num"><?= $setupHasTargets ? '✓' : '3' ?></span> Set targets
          </a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
      <div class="stat-icon bg-indigo-50">
        <svg fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
      </div>
      <div>
        <div class="stat-value"><?= (int) $totalReportsMonth ?></div>
        <div class="stat-label">Total Reports</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon bg-blue-50">
        <svg fill="none" stroke="#2563eb" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 0a4 4 0 100-8 4 4 0 000 8z"></path></svg>
      </div>
      <div>
        <div class="stat-value"><?= (int) $totalStaff ?></div>
        <div class="stat-label">Active Staff</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon bg-green-50">
        <svg fill="none" stroke="#16a34a" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      </div>
      <div>
        <div class="stat-value"><?= (int) $onTrackStaff ?><span class="text-sm font-medium text-gray-400">/<?= (int) $totalStaff ?></span></div>
        <div class="stat-label">On Track (<?= $onTrackPct ?>%)</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon bg-yellow-50">
        <svg fill="none" stroke="#ca8a04" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
      </div>
      <div>
        <div class="stat-value"><?= $avgPerStaff ?></div>
        <div class="stat-label">Avg / Staff</div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Employee Performance</h2>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left border-b border-gray-200">
                <th class="pb-3 font-medium text-gray-600 w-10"></th>
                <th class="pb-3 font-medium text-gray-600">Name</th>
                <th class="pb-3 font-medium text-gray-600">Total</th>
                <th class="pb-3 font-medium text-gray-600">Progress</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($usersPage as $i => $u):
                $pct = $u['total'] >= $target['max'] ? 100 : round(($u['total'] / $target['max']) * 100);
                $colorClass = $u['total'] >= $target['min'] ? 'bg-green-500' : ($u['total'] >= ($target['min'] * 0.6) ? 'bg-yellow-500' : 'bg-red-500');
              ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="py-4">
                  <span class="rank-badge"><?= $offset + $i + 1 ?></span>
                </td>
                <td class="py-4">
                  <div class="flex items-center">
                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($u['name']) ?></span>
                    <?php if ($u['role'] === 'admin'): ?>
                      <span class="ml-2 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full">Admin</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="py-4 font-medium"><?php echo (int)$u['total'] ?></td>
                <td class="py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-full bg-gray-200 rounded-full h-2.5 progress-bar">
                      <div class="progress-fill <?php echo $colorClass ?>" style="width: <?php echo $pct ?>%"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-600 w-10 text-right"><?php echo $pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPage > 1): ?>
          <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
            <div class="text-sm text-gray-600 whitespace-nowrap">
              Page <?= $page ?> of <?= $totalPage ?>
            </div>
            <nav class="flex flex-wrap justify-center gap-1">
              <?php if ($page > 1): ?>
                <a href="?page=1&month=<?= htmlspecialchars($month) ?>"
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
                <a href="?page=<?= $page - 1 ?>&month=<?= htmlspecialchars($month) ?>"
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
                  <a href="?page=<?= $i ?>&month=<?= htmlspecialchars($month) ?>"
                     class="<?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 hover:bg-indigo-50' ?>
                      px-3 py-2 border border-gray-300 rounded text-sm font-medium transition">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </div>
              <div class="xs:hidden px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded text-sm font-medium">
                <?= $page ?>
              </div>
              <?php if ($page < $totalPage): ?>
                <a href="?page=<?= $page + 1 ?>&month=<?= htmlspecialchars($month) ?>"
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
              <?php if ($page < $totalPage): ?>
                <a href="?page=<?= $totalPage ?>&month=<?= htmlspecialchars($month) ?>"
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

    <div class="bg-white rounded-xl shadow-md overflow-hidden chart-card">
      <div class="chart-inner">
        <h2 class="text-xl font-semibold text-gray-800 mb-2">Job Type Distribution</h2>

        <?php if (empty($chartRows)): ?>
          <div class="chart-empty">No reports for this month yet.</div>
        <?php else: ?>
          <div class="chart-layout">
            <div class="donut-wrap">
              <canvas id="pie"></canvas>
              <div class="donut-center">
                <div class="num"><?= (int) $typeTotal ?></div>
                <div class="lbl">reports</div>
              </div>
            </div>

            <div class="legend-list">
              <?php foreach ($chartRows as $t):
                $pctType = $typeTotal > 0 ? round(($t['c'] / $typeTotal) * 100) : 0;
              ?>
                <div class="legend-row" title="<?= htmlspecialchars($t['detail'] ?? $t['job_type']) ?>">
                  <span class="legend-dot" style="background: <?= $t['color'] ?>"></span>
                  <span class="legend-name"><?= htmlspecialchars($t['job_type']) ?></span>
                  <span class="legend-count"><?= (int) $t['c'] ?><span class="legend-pct"><?= $pctType ?>%</span></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <p class="chart-note">
          Rule: Minimum <?= $dailyMin ?> item<?= $dailyMin > 1 ? 's' : '' ?> per day per staff.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
  const pieLabels  = <?= json_encode(array_column($chartRows, 'job_type')) ?>;
  const pieData    = <?= json_encode(array_map('intval', array_column($chartRows, 'c'))) ?>;
  const pieColors  = <?= json_encode(array_column($chartRows, 'color')) ?>;
  const pieDetails = <?= json_encode(array_map(fn($r) => $r['detail'] ?? '', $chartRows)) ?>;

  function surfaceColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--surface').trim() || '#ffffff';
  }

  if (pieData.length > 0) {
    const pieChart = new Chart(document.getElementById('pie'), {
      type: 'doughnut',
      data: {
        labels: pieLabels,
        datasets: [{
          data: pieData,
          backgroundColor: pieColors,
          borderWidth: 2,
          borderColor: surfaceColor(),
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              afterLabel: (ctx) => pieDetails[ctx.dataIndex] || ''
            }
          }
        }
      }
    });

    document.addEventListener('vorta:uichange', function () {
      pieChart.data.datasets[0].borderColor = surfaceColor();
      pieChart.update('none');
    });
  }
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>