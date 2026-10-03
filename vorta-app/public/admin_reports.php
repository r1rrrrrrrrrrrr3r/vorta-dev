<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/reports.php';
require_once __DIR__ . '/../lib/tenant.php';
require_admin();
$company_id = current_company_id();

$detailOwnerId = null;

if (isset($_GET['proof_image'])) {
    report_proof_image_output($pdo, (int)$_GET['proof_image'], $detailOwnerId);
}

if (isset($_GET['detail'])) {
    report_detail_output($pdo, (int)$_GET['detail'], $detailOwnerId, basename(__FILE__));
}

$tab = ($_GET['tab'] ?? 'all') === 'today' ? 'today' : 'all';
$dailyMin = settings_get_daily_min_reports($pdo);
$today = date('Y-m-d');
$todayStats = report_daily_completion_stats($pdo, $today, $dailyMin);
$missingToday = $todayStats['partial'] + $todayStats['none'];

if ($tab === 'all') {
    $month = valid_month($_GET['month'] ?? null);
    $start = $month . "-01";
    $end = date('Y-m-t', strtotime($start));

    $limit = 20;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    $q = trim((string) ($_GET['q'] ?? ''));
    $filterUser = (int) ($_GET['user_id'] ?? 0);
    $filterJobType = trim((string) ($_GET['job_type'] ?? ''));
    $filterStatus = in_array($_GET['status'] ?? '', ['Progress', 'Completed'], true) ? $_GET['status'] : '';

    $where = "pr.company_id = ? AND pr.report_date BETWEEN ? AND ?";
    $params = [$company_id, $start, $end];
    if ($q !== '') {
        $where .= " AND (pr.title LIKE ? OR u.name LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($filterUser > 0) {
        $where .= " AND pr.user_id = ?";
        $params[] = $filterUser;
    }
    if ($filterJobType !== '') {
        $where .= " AND pr.job_type = ?";
        $params[] = $filterJobType;
    }
    if ($filterStatus !== '') {
        $where .= " AND pr.status = ?";
        $params[] = $filterStatus;
    }
    $hasFilters = $q !== '' || $filterUser > 0 || $filterJobType !== '' || $filterStatus !== '';

    $countStmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM production_reports pr
      JOIN users u ON u.user_id = pr.user_id
      LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
      WHERE $where
    ");
    $countStmt->execute($params);
    $totalReports = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
      SELECT pr.*, u.name, wf.workforce_name
      FROM production_reports pr
      JOIN users u ON u.user_id = pr.user_id
      LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
      WHERE $where
      ORDER BY pr.report_date DESC, pr.report_id DESC
      LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $staffStmt = $pdo->prepare("SELECT user_id, name FROM users WHERE company_id = ? AND is_active = 1 ORDER BY name");
    $staffStmt->execute([$company_id]);
    $staffOptions = $staffStmt->fetchAll();
    $jobTypeStmt = $pdo->prepare("SELECT name FROM job_type WHERE company_id = ? ORDER BY name");
    $jobTypeStmt->execute([$company_id]);
    $jobTypeOptions = $jobTypeStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $shortLimit = 20;
    $shortPage = isset($_GET['short_page']) ? max(1, (int)$_GET['short_page']) : 1;
    $shortOffset = ($shortPage - 1) * $shortLimit;

    $countShort = $pdo->prepare("
      SELECT COUNT(*)
      FROM users u
      WHERE u.company_id = ? AND u.is_active = 1 AND u.role <> 'admin'
    ");
    $countShort->execute([$company_id]);
    $totalShort = (int)$countShort->fetchColumn();

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
}

function completion_pill(int $count, int $min): string
{
    if ($count >= $min) return status_pill(null, 'ok', 'Complete');
    if ($count > 0) return status_pill(null, 'warn', $count . ' of ' . $min);
    return status_pill('No reports');
}

$pageTitle = 'Reports';
$activeNav = 'reports';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Reports', 'Everything staff submitted') ?>

<nav class="tabs" aria-label="Report views">
  <a class="tab<?= $tab === 'all' ? ' is-active' : '' ?>" href="admin_reports.php"<?= $tab === 'all' ? ' aria-current="page"' : '' ?>>All reports</a>
  <a class="tab<?= $tab === 'today' ? ' is-active' : '' ?>" href="admin_reports.php?tab=today"<?= $tab === 'today' ? ' aria-current="page"' : '' ?>>
    Today's completion<?php if ($missingToday > 0): ?> <span class="tab-count"><?= $missingToday ?> missing</span><?php endif; ?>
  </a>
</nav>

<?php if ($tab === 'all'): ?>
  <div class="toolbar">
    <?= period_picker('month', $month, 'month', ['page']) ?>
    <form method="get" id="ar-filters" class="contents" data-autosubmit data-turbo-frame="ar-results" data-turbo-action="replace">
      <input type="hidden" name="month" value="<?= e($month) ?>">
      <div class="input-icon">
        <?= icon('magnifying-glass') ?>
        <label for="ar-search" class="sr-only">Search title or staff</label>
        <input type="search" id="ar-search" name="q" class="input" placeholder="Search title or staff…" value="<?= e($q) ?>">
      </div>
      <label for="ar-staff" class="sr-only">Staff</label>
      <select id="ar-staff" name="user_id" class="select">
        <option value="">All staff</option>
        <?php foreach ($staffOptions as $s): ?>
          <option value="<?= (int) $s['user_id'] ?>"<?= $filterUser === (int) $s['user_id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="ar-type" class="sr-only">Job type</label>
      <select id="ar-type" name="job_type" class="select">
        <option value="">All job types</option>
        <?php foreach ($jobTypeOptions as $jt): ?>
          <option value="<?= e($jt) ?>"<?= $filterJobType === $jt ? ' selected' : '' ?>><?= e($jt) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="ar-status" class="sr-only">Status</label>
      <select id="ar-status" name="status" class="select">
        <option value="">Any status</option>
        <option value="Progress"<?= $filterStatus === 'Progress' ? ' selected' : '' ?>>In progress</option>
        <option value="Completed"<?= $filterStatus === 'Completed' ? ' selected' : '' ?>>Completed</option>
      </select>
    </form>
    <a class="link text-[13px]" href="<?= e(query_url(['q' => null, 'user_id' => null, 'job_type' => null, 'status' => null, 'page' => null])) ?>" data-filter-clear="ar-filters"<?= $hasFilters ? '' : ' hidden' ?>>Clear filters</a>
  </div>

  <turbo-frame id="ar-results" class="results-frame" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
  <div class="toolbar"><span class="toolbar-count"><?= $totalReports ?> report<?= $totalReports === 1 ? '' : 's' ?></span></div>
  <section class="card">
    <?php if (empty($rows)): ?>
      <?= $hasFilters
          ? empty_state('No reports match these filters', 'Try another month or clear the filters.')
          : empty_state('No reports in ' . fmt_month($month), 'Reports staff submit this month will appear here.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Staff</th>
              <th>Title</th>
              <th>Work force</th>
              <th>Status</th>
              <th>Proof</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $hasLink = trim((string)($r['proof_link'] ?? '')) !== '';
              $hasImage = trim((string)($r['proof_image'] ?? '')) !== '';
            ?>
              <tr class="is-clickable" tabindex="0" data-drawer-url="admin_reports.php?detail=<?= (int) $r['report_id'] ?>"
                data-drawer-title="<?= e($r['title']) ?>" data-drawer-eyebrow="Report · <?= e($r['name']) ?>">
                <td class="whitespace-nowrap"><?= e(fmt_date($r['report_date'], 'short')) ?></td>
                <td class="whitespace-nowrap"><?= e($r['name']) ?></td>
                <td><span class="cell-strong"><?= e($r['title']) ?></span><span class="cell-sub"><?= e($r['job_type']) ?></span></td>
                <td><?= e($r['workforce_name'] ?? '–') ?></td>
                <td><?= status_pill($r['status']) ?></td>
                <td class="whitespace-nowrap text-muted">
                  <?php if (!$hasLink && !$hasImage): ?>–<?php endif; ?>
                  <?php if ($hasLink): ?><span role="img" aria-label="Has link" title="Link"><?= icon('link', 'size-4 inline') ?></span><?php endif; ?>
                  <?php if ($hasImage): ?><span role="img" aria-label="Has photo" title="Photo"><?= icon('photo', 'size-4 inline') ?></span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= pagination($page, $limit, $totalReports) ?>
    <?php endif; ?>
  </section>
  </turbo-frame>

<?php else: ?>
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h2 class="card-title">Daily completion · <?= e(fmt_date($today, 'long')) ?></h2>
    <a class="btn btn-secondary btn-sm" href="<?= e(query_url()) ?>"><?= icon('arrow-path') ?>Refresh</a>
  </div>

  <?= kpi_strip([
      ['label' => 'Complete', 'value' => $todayStats['complete'], 'note' => '≥ ' . $dailyMin . ' reports'],
      ['label' => 'Partial', 'value' => $todayStats['partial']],
      ['label' => 'No reports', 'value' => $todayStats['none'], 'tone' => $todayStats['none'] > 0 ? 'bad' : null],
      ['label' => 'Total active', 'value' => $todayStats['total']],
  ]) ?>

  <div class="grid gap-2">
    <?= progress_bar($todayStats['total'] > 0 ? $todayStats['complete'] / $todayStats['total'] * 100 : 0) ?>
    <span class="text-[13px] text-muted"><?= $todayStats['complete'] ?> of <?= $todayStats['total'] ?> staff complete</span>
  </div>

  <turbo-frame id="ar-today-results" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
  <section class="card">
    <?php if (!$shortRows): ?>
      <?= empty_state('No active staff') ?>
    <?php else: ?>
      <div class="table-wrap hidden md:block">
        <table class="table">
          <thead>
            <tr>
              <th>Staff</th>
              <th>Position</th>
              <th class="num">Reports</th>
              <th>Submitted work</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($shortRows as $s): $count = (int) $s['report_count']; ?>
              <tr>
                <td><span class="cell-strong"><?= e($s['name']) ?></span><span class="cell-sub"><?= e($s['email']) ?></span></td>
                <td><?= e($s['position'] ?: '–') ?></td>
                <td class="num"><?= $count ?></td>
                <td>
                  <?php if ($count > 0): ?>
                    <span class="block truncate max-w-[320px]" title="<?= e($s['report_titles']) ?>"><?= e($s['report_titles']) ?></span>
                    <span class="cell-sub truncate max-w-[320px]">Types: <?= e($s['job_types']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">–</span>
                  <?php endif; ?>
                </td>
                <td><?= completion_pill($count, $dailyMin) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <ul class="md:hidden m-0 p-0 list-none">
        <?php foreach ($shortRows as $s): $count = (int) $s['report_count']; ?>
          <li class="card-body grid gap-1 border-b border-line last:border-b-0">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0"><span class="cell-strong"><?= e($s['name']) ?></span><span class="cell-sub"><?= e($s['position'] ?: $s['email']) ?></span></div>
              <?= completion_pill($count, $dailyMin) ?>
            </div>
            <?php if ($count > 0): ?>
              <div class="text-[13px] truncate"><?= e($s['report_titles']) ?></div>
              <div class="text-[12px] text-muted truncate">Types: <?= e($s['job_types']) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?= pagination($shortPage, $shortLimit, $totalShort, 'short_page') ?>
    <?php endif; ?>
  </section>
  </turbo-frame>
<?php endif; ?>

<?php include __DIR__ . '/../views/layout/end.php'; ?>
