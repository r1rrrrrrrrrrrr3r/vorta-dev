<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ui.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/tenant.php';
require_once __DIR__ . '/../../lib/audit.php';
require_admin();
$company_id = current_company_id();

$search = trim($_GET['search'] ?? '');
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$backParams = ['tab' => 'job_type', 'page' => $page];
if ($search) $backParams['search'] = $search;
$redirect = 'admin_master_data.php?' . http_build_query($backParams);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'job_type') {
  csrf_verify();
  $name = trim($_POST['name'] ?? '');
  $action = $_POST['action'] ?? '';
  $job_type_id = (int)($_POST['job_type_id'] ?? 0);

  if (empty($name)) {
    flash_set('bad', "Job type name is required.");
  } else {
    try {
      if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO job_type (company_id, name) VALUES (?, ?)");
        $stmt->execute([$company_id, $name]);
        audit_log($pdo, 'job_type.created', 'job_type', $pdo->lastInsertId());
        flash_set('ok', "Job type added");
      } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE job_type SET name = ? WHERE job_type_id = ? AND company_id = ?");
        $stmt->execute([$name, $job_type_id, $company_id]);
        audit_log($pdo, 'job_type.updated', 'job_type', $job_type_id);
        if ($stmt->rowCount()) {
          flash_set('ok', "Job type updated");
        } else {
          flash_set('bad', "Job type not found.");
        }
      }
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        flash_set('bad', $action === 'create'
          ? "A job type with this name already exists."
          : "That job type name is already in use.");
      } else {
        flash_set('bad', "Couldn't save. Try again.");
      }
    }
  }
  header('Location: ' . $redirect, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job_type'])) {
  csrf_verify();
  $job_type_id = (int)$_POST['delete_job_type'];
  try {
    $stmt = $pdo->prepare("DELETE FROM job_type WHERE job_type_id = ? AND company_id = ?");
    $stmt->execute([$job_type_id, $company_id]);
    audit_log($pdo, 'job_type.deleted', 'job_type', $job_type_id);

    if ($stmt->rowCount()) {
      flash_set('ok', "Job type deleted");
    } else {
      flash_set('bad', "Job type not found.");
    }
  } catch (PDOException $e) {
    flash_set('bad', "Couldn't delete this job type. Try again.");
  }
  header('Location: ' . $redirect, true, 303);
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "name LIKE ?";
  $params[] = "%$search%";
}
$where[] = "company_id = ?";
$params[] = $company_id;
$whereSql = "WHERE " . implode(" AND ", $where);
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM job_type $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalJobTypes = (int)($totalRow['cnt'] ?? 0);
$sql = "SELECT * FROM job_type $whereSql ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
  $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mdTab = 'job_type';
$mdPlaceholder = 'Search by name…';
$mdAddLabel = 'Add job type';
$mdPanel = 'drawer-job-type';
$mdTotal = $totalJobTypes;
$mdNoun = $totalJobTypes === 1 ? 'job type' : 'job types';
include __DIR__ . '/../../views/master_data/toolbar.php';
?>
<section class="card">
  <?php if (empty($job_types)): ?>
    <?= $search ? empty_state('No job types match “' . $search . '”', 'Try another name or clear the search.') : empty_state('No job types yet', 'Add the kinds of work staff can report.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($job_types as $jt): $id = (int)$jt['job_type_id']; ?>
            <tr>
              <td class="cell-strong"><?= e($jt['name']) ?></td>
              <td class="col-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-menu-trigger aria-controls="jt-menu-<?= $id ?>" aria-expanded="false" aria-haspopup="menu" aria-label="Actions for <?= e($jt['name']) ?>"><?= icon('ellipsis-horizontal') ?></button>
                <div class="menu" id="jt-menu-<?= $id ?>" role="menu" hidden>
                  <button type="button" class="menu-item" role="menuitem" onclick="editJobType(<?= $id ?>, <?= e(json_encode($jt['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)"><?= icon('pencil') ?>Edit</button>
                  <div class="menu-sep"></div>
                  <form method="POST" action="<?= e($redirect) ?>" class="contents" data-turbo-frame="_top">
                    <?= csrf_field() ?>
                    <button type="submit" name="delete_job_type" value="<?= $id ?>" class="menu-item menu-item-danger" role="menuitem"
                      data-confirm="Delete job type?" data-confirm-message="“<?= e($jt['name']) ?>” will be removed. This can't be undone." data-confirm-text="Delete" data-confirm-tone="danger"><?= icon('trash') ?>Delete…</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $perPage, $totalJobTypes) ?>
  <?php endif; ?>
</section>
</turbo-frame>

<aside class="drawer" id="drawer-job-type" role="dialog" aria-modal="true" aria-labelledby="jt-drawer-title" hidden>
  <form method="POST" id="jobtype-form" action="<?= e($redirect) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="job_type">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="job_type_id" value="" id="jobtype-id-input">
    <div class="drawer-header">
      <div class="min-w-0"><div class="drawer-eyebrow" id="form-title">Add job type</div><h2 class="drawer-title" id="jt-drawer-title">New job type</h2></div>
      <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
    </div>
    <div class="drawer-body">
      <div class="field">
        <label class="label" for="jt-name">Name</label>
        <input type="text" name="name" id="jt-name" class="input" placeholder="e.g. Frontend, Design, Testing" required>
      </div>
    </div>
    <div class="drawer-footer">
      <button type="button" class="btn btn-ghost" data-drawer-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="jt-submit">Add job type</button>
    </div>
  </form>
</aside>

<script>
(function () {
  function resetJobTypeForm() {
    document.getElementById('jobtype-form').reset();
    document.getElementById('form-title').textContent = 'Add job type';
    document.getElementById('jt-drawer-title').textContent = 'New job type';
    document.getElementById('jt-submit').textContent = 'Add job type';
    document.getElementById('action-input').value = 'create';
    document.getElementById('jobtype-id-input').value = '';
  }

  function editJobType(id, name) {
    document.getElementById('form-title').textContent = 'Edit job type';
    document.getElementById('jt-drawer-title').textContent = name;
    document.getElementById('jt-submit').textContent = 'Save';
    document.getElementById('jt-name').value = name;
    document.getElementById('action-input').value = 'update';
    document.getElementById('jobtype-id-input').value = id;
    Vorta.drawer.openPanel('drawer-job-type');
  }

  document.getElementById('drawer-job-type').addEventListener('drawer:mode', resetJobTypeForm);
  document.getElementById('drawer-job-type').addEventListener('drawer:close', resetJobTypeForm);

  // dipanggil dari atribut onclick
  window.editJobType = editJobType;
})();
</script>
