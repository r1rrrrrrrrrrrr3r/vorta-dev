<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ui.php';
require_admin();

$search = trim($_GET['search'] ?? '');
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$backParams = ['tab' => 'work_force', 'page' => $page];
if ($search) $backParams['search'] = $search;
$redirect = 'admin_master_data.php?' . http_build_query($backParams);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'work_force') {
  $workforce_name = trim($_POST['workforce_name'] ?? '');
  $action = $_POST['action'] ?? '';
  $workforce_id = (int)($_POST['workforce_id'] ?? 0);

  if (empty($workforce_name)) {
    flash_set('bad', "Work force name is required.");
  } else {
    try {
      if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO work_force (workforce_name) VALUES (?)");
        $stmt->execute([$workforce_name]);
        flash_set('ok', "Work force added");
      } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE work_force SET workforce_name = ? WHERE workforce_id = ?");
        $stmt->execute([$workforce_name, $workforce_id]);
        if ($stmt->rowCount()) {
          flash_set('ok', "Work force updated");
        } else {
          flash_set('bad', "Work force not found.");
        }
      }
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        flash_set('bad', $action === 'create'
          ? "A work force with this name already exists."
          : "That work force name is already in use.");
      } else {
        flash_set('bad', "Couldn't save. Try again.");
      }
    }
  }
  header('Location: ' . $redirect, true, 303);
  exit;
}

if (isset($_GET['delete_work_force'])) {
  $workforce_id = (int)$_GET['delete_work_force'];
  try {
    $stmt = $pdo->prepare("DELETE FROM work_force WHERE workforce_id = ?");
    $stmt->execute([$workforce_id]);

    if ($stmt->rowCount()) {
      flash_set('ok', "Work force deleted");
    } else {
      flash_set('bad', "Work force not found.");
    }
  } catch (PDOException $e) {
    flash_set('bad', "Couldn't delete this work force. It may still be used by reports.");
  }
  header('Location: ' . $redirect, true, 303);
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "workforce_name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM work_force $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalworkforce = (int)($totalRow['cnt'] ?? 0);
$sql = "SELECT * FROM work_force $whereSql ORDER BY workforce_name ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
  $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$workforces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mdTab = 'work_force';
$mdPlaceholder = 'Search by name…';
$mdAddLabel = 'Add work force';
$mdPanel = 'drawer-work-force';
$mdTotal = $totalworkforce;
$mdNoun = $totalworkforce === 1 ? 'work force' : 'work forces';
include __DIR__ . '/../../views/master_data/toolbar.php';
?>
<section class="card">
  <?php if (empty($workforces)): ?>
    <?= $search ? empty_state('No work forces match “' . $search . '”', 'Try another name or clear the search.') : empty_state('No work forces yet', 'Add the clients or projects staff report against.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($workforces as $wf): $id = (int)$wf['workforce_id']; ?>
            <tr>
              <td class="cell-strong"><?= e($wf['workforce_name']) ?></td>
              <td class="col-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-menu-trigger aria-controls="wf-menu-<?= $id ?>" aria-expanded="false" aria-haspopup="menu" aria-label="Actions for <?= e($wf['workforce_name']) ?>"><?= icon('ellipsis-horizontal') ?></button>
                <div class="menu" id="wf-menu-<?= $id ?>" role="menu" hidden>
                  <button type="button" class="menu-item" role="menuitem" onclick='editWorkforce(<?= $id ?>, <?= e(json_encode($wf['workforce_name'])) ?>)'><?= icon('pencil') ?>Edit</button>
                  <div class="menu-sep"></div>
                  <a class="menu-item menu-item-danger" role="menuitem" href="<?= e(query_url(['tab' => 'work_force', 'delete_work_force' => $id], 'admin_master_data.php')) ?>"
                    data-confirm="Delete work force?" data-confirm-message="“<?= e($wf['workforce_name']) ?>” will be removed. This can't be undone." data-confirm-text="Delete" data-confirm-tone="danger"><?= icon('trash') ?>Delete…</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $perPage, $totalworkforce) ?>
  <?php endif; ?>
</section>
</turbo-frame>

<aside class="drawer" id="drawer-work-force" role="dialog" aria-modal="true" aria-labelledby="wf-drawer-title" hidden>
  <form method="POST" id="workforce-form" action="<?= e($redirect) ?>">
    <input type="hidden" name="entity" value="work_force">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="workforce_id" value="" id="workforce-id-input">
    <div class="drawer-header">
      <div class="min-w-0"><div class="drawer-eyebrow" id="form-title">Add work force</div><h2 class="drawer-title" id="wf-drawer-title">New work force</h2></div>
      <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
    </div>
    <div class="drawer-body">
      <div class="field">
        <label class="label" for="workforce_name">Name</label>
        <input type="text" name="workforce_name" id="workforce_name" class="input" placeholder="e.g. Inare, Antara" required>
      </div>
    </div>
    <div class="drawer-footer">
      <button type="button" class="btn btn-ghost" data-drawer-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="wf-submit">Add work force</button>
    </div>
  </form>
</aside>

<script>
(function () {
  function resetWorkforceForm() {
    document.getElementById('workforce-form').reset();
    document.getElementById('form-title').textContent = 'Add work force';
    document.getElementById('wf-drawer-title').textContent = 'New work force';
    document.getElementById('wf-submit').textContent = 'Add work force';
    document.getElementById('action-input').value = 'create';
    document.getElementById('workforce-id-input').value = '';
  }

  function editWorkforce(id, name) {
    document.getElementById('form-title').textContent = 'Edit work force';
    document.getElementById('wf-drawer-title').textContent = name;
    document.getElementById('wf-submit').textContent = 'Save';
    document.getElementById('workforce_name').value = name;
    document.getElementById('action-input').value = 'update';
    document.getElementById('workforce-id-input').value = id;
    Vorta.drawer.openPanel('drawer-work-force');
  }

  document.getElementById('drawer-work-force').addEventListener('drawer:mode', resetWorkforceForm);
  document.getElementById('drawer-work-force').addEventListener('drawer:close', resetWorkforceForm);

  // dipanggil dari atribut onclick
  window.editWorkforce = editWorkforce;
})();
</script>
