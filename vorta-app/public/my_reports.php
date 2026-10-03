<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/reports.php';
require_once __DIR__ . '/../lib/tenant.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$company_id = current_company_id();
$detailOwnerId = (int)$user_id;

if (isset($_GET['proof_image'])) {
    report_proof_image_output($pdo, (int)$_GET['proof_image'], $detailOwnerId);
}

if (isset($_GET['detail'])) {
    report_detail_output($pdo, (int)$_GET['detail'], $detailOwnerId, basename(__FILE__));
}

$month = valid_month($_GET['month'] ?? null);
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$monthlyTarget = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);

$statusFilter = in_array($_GET['status'] ?? '', ['Progress', 'Completed'], true) ? $_GET['status'] : '';
$q = trim((string) ($_GET['q'] ?? ''));

$where = "pr.user_id = ? AND pr.company_id = ? AND pr.report_date BETWEEN ? AND ?";
$params = [$user_id, $company_id, $start, $end];
if ($statusFilter !== '') {
    $where .= " AND pr.status = ?";
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where .= " AND pr.title LIKE ?";
    $params[] = '%' . $q . '%';
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM production_reports pr WHERE $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT
        pr.*,
        wf.workforce_name
    FROM production_reports pr
    LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id AND wf.company_id = pr.company_id
    WHERE $where
    ORDER BY pr.report_date DESC, pr.report_id DESC
    LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$dailyCounts = report_daily_counts($pdo, (int) $user_id, $start, $end);
$monthCount = array_sum($dailyCounts);
$min = (int) $monthlyTarget['min'];
$max = max(1, (int) $monthlyTarget['max']);

$hasFilters = $statusFilter !== '' || $q !== '';

$pageTitle = 'My reports';
$activeNav = 'my_reports';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('My reports', 'Your submitted work', '<a class="btn btn-primary" href="report_form.php">' . icon('plus') . 'New report</a>') ?>

<section class="card card-body grid gap-6 md:grid-cols-[240px_1fr] items-end">
  <div>
    <div class="section-label"><?= e(fmt_month($month)) ?></div>
    <div class="big-number mb-3"><?= (int) $monthCount ?> <small>reports</small></div>
    <?= progress_bar(min(100, $monthCount / $max * 100), [
        ['pct' => $min / $max * 100, 'label' => 'min ' . $min],
        ['pct' => 100, 'label' => 'max ' . $max],
    ], true) ?>
  </div>
  <?= daybars($month, $dailyCounts, $dailyMin) ?>
</section>

<div id="my-reports" class="page-section">
<div class="toolbar">
  <?= period_picker('month', $month, 'month', ['page']) ?>
  <nav class="seg" aria-label="Filter by status">
    <?php foreach (['' => 'All', 'Progress' => 'In progress', 'Completed' => 'Completed'] as $val => $lbl): ?>
      <a href="<?= e(query_url(['status' => $val ?: null, 'page' => null])) ?>" data-query-own="status,page" class="<?= $statusFilter === $val ? 'is-active' : '' ?>"<?= $statusFilter === $val ? ' aria-current="true"' : '' ?>><?= $lbl ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" id="mr-filters" class="input-icon" role="search" data-turbo-frame="mr-results" data-turbo-action="replace">
    <input type="hidden" name="month" value="<?= e($month) ?>">
    <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
    <?= icon('magnifying-glass') ?>
    <label for="mr-search" class="sr-only">Search titles</label>
    <input type="search" id="mr-search" name="q" class="input" placeholder="Search titles…" value="<?= e($q) ?>">
  </form>
</div>

<turbo-frame id="mr-results" class="results-frame" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
<div class="toolbar"><span class="toolbar-count"><?= $total ?> report<?= $total === 1 ? '' : 's' ?></span></div>
<section class="card">
  <?php if (empty($rows)): ?>
    <?php if ($hasFilters): ?>
      <?= empty_state('No reports match these filters', 'Try another month or clear the filters.', '<a class="btn btn-secondary btn-sm" href="' . e(query_url(['status' => null, 'q' => null, 'page' => null])) . '" data-turbo-frame="_top">Clear filters</a>') ?>
    <?php else: ?>
      <?= empty_state('No reports in ' . fmt_month($month), 'Reports you submit this month will appear here.', '<a class="btn btn-primary" href="report_form.php">' . icon('plus') . 'New report</a>') ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Title</th>
            <th class="max-sm:hidden">Work force</th>
            <th>Status</th>
            <th class="col-actions"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $id = (int) $r['report_id'];
            $isDone = $r['status'] === 'Completed';
          ?>
            <tr id="row-<?= $id ?>" class="is-clickable" tabindex="0" data-drawer-url="my_reports.php?detail=<?= $id ?>"
              data-drawer-title="<?= e($r['title']) ?>" data-drawer-eyebrow="Report">
              <td class="whitespace-nowrap"><?= e(fmt_date($r['report_date'], 'short')) ?></td>
              <td><span class="cell-strong"><?= e($r['title']) ?></span><span class="cell-sub"><?= e($r['job_type']) ?></span></td>
              <td class="max-sm:hidden"><?= e($r['workforce_name'] ?? '–') ?></td>
              <td class="whitespace-nowrap" data-status-cell>
                <?= status_pill($r['status']) ?>
                <?php if (!$isDone): ?>
                  <button type="button" class="link text-[13px] block mt-1 sm:inline sm:mt-0 sm:ml-2" data-mark-done="<?= $id ?>">Mark completed</button>
                <?php endif; ?>
              </td>
              <td class="col-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-menu-trigger aria-controls="menu-<?= $id ?>" aria-expanded="false" aria-haspopup="menu"
                  aria-label="Actions for <?= e($r['title']) ?>"><?= icon('ellipsis-horizontal') ?></button>
                <div class="menu" id="menu-<?= $id ?>" role="menu" hidden>
                  <button type="button" class="menu-item" role="menuitem" data-view-report="<?= $id ?>"><?= icon('eye') ?>View details</button>
                  <?php if ($isDone): ?>
                    <span class="menu-item" role="menuitem" aria-disabled="true" title="Completed reports can't be edited" data-edit-item><?= icon('pencil') ?>Edit</span>
                  <?php else: ?>
                    <a class="menu-item" role="menuitem" href="edit_report.php?id=<?= $id ?>" data-edit-item><?= icon('pencil') ?>Edit</a>
                  <?php endif; ?>
                  <div class="menu-sep"></div>
                  <button type="button" class="menu-item menu-item-danger" role="menuitem" data-delete-report="<?= $id ?>"
                    data-title="<?= e($r['title']) ?>" data-date="<?= e(fmt_date($r['report_date'])) ?>"><?= icon('trash') ?>Delete…</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $limit, $total) ?>
  <?php endif; ?>
</section>
</turbo-frame>
</div>

<a class="fab md:hidden" href="report_form.php" aria-label="New report"><?= icon('plus') ?></a>

<script>
(function () {
  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: body
    }).then(r => r.json());
  }

  // Listener di root halaman (elemen baru tiap render, jadi tidak menumpuk); root mencakup frame hasil.
  const root = document.getElementById('my-reports');
  if (!root) return;
  root.addEventListener('click', function (e) {
    const view = e.target.closest('[data-view-report]');
    if (view) {
      const row = document.getElementById('row-' + view.dataset.viewReport);
      Vorta.drawer.open({ url: row.dataset.drawerUrl, title: row.dataset.drawerTitle, eyebrow: 'Report' });
      return;
    }

    const done = e.target.closest('[data-mark-done]');
    if (done) {
      const id = done.dataset.markDone;
      Vorta.confirm({ title: 'Mark as completed?', message: "Completed reports can't be edited.", confirmText: 'Mark completed' })
        .then(ok => {
          if (!ok) return;
          done.disabled = true;
          post('update_status_ajax.php', 'report_id=' + encodeURIComponent(id) + '&action=mark_done')
            .then(d => {
              if (!d.success) throw new Error(d.message || '');
              const row = document.getElementById('row-' + id);
              row.querySelector('[data-status-cell]').innerHTML = '<span class="pill pill-ok">Completed</span>';
              const edit = row.querySelector('[data-edit-item]');
              if (edit) {
                const span = document.createElement('span');
                span.className = 'menu-item';
                span.setAttribute('role', 'menuitem');
                span.setAttribute('aria-disabled', 'true');
                span.title = "Completed reports can't be edited";
                span.dataset.editItem = '';
                span.innerHTML = edit.innerHTML;
                edit.replaceWith(span);
              }
              Vorta.toast('Marked as completed');
            })
            .catch(err => {
              done.disabled = false;
              Vorta.toast(err.message || "Couldn't update the status. Try again.", { tone: 'bad' });
            });
        });
      return;
    }

    const del = e.target.closest('[data-delete-report]');
    if (del) {
      const id = del.dataset.deleteReport;
      Vorta.confirm({
        title: 'Delete this report?',
        message: '“' + del.dataset.title + '” on ' + del.dataset.date + " will be removed. This can't be undone.",
        confirmText: 'Delete report',
        tone: 'danger'
      }).then(ok => {
        if (!ok) return;
        post('delete_report_ajax.php', 'report_id=' + encodeURIComponent(id))
          .then(d => {
            if (!d.success) throw new Error(d.message || '');
            document.getElementById('row-' + id)?.remove();
            Vorta.toast('Report deleted');
          })
          .catch(err => Vorta.toast(err.message || "Couldn't delete the report. Try again.", { tone: 'bad' }));
      });
    }
  });
})();
</script>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
