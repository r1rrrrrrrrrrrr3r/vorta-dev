<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ui.php';
require_admin();

$search = trim($_GET['search'] ?? '');
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$backParams = ['tab' => 'employees', 'page' => $page];
if ($search) $backParams['search'] = $search;
$redirect = 'admin_master_data.php?' . http_build_query($backParams);

function getEnumValues($pdo, $table, $column)
{
  $stmt = $pdo->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND COLUMN_NAME = '$column'
    ");
  $row = $stmt->fetch();
  if (!$row) return [];

  $type = $row['COLUMN_TYPE'];
  if (preg_match("/^enum\((.+)\)$/", $type, $matches)) {
    $items = explode(',', $matches[1]);
    return array_map(function ($item) {
      return trim($item, "'");
    }, $items);
  }
  return [];
}

$position_enum = getEnumValues($pdo, 'employees', 'position');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'employees') {
  $name = trim($_POST['name'] ?? '');
  $position = trim($_POST['position'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $action = $_POST['action'] ?? '';
  $employee_id = (int)($_POST['employee_id'] ?? 0);

  $user_id = $_POST['user_id'] ?? 0;
  if ($action === 'update' && empty($user_id) && !empty($_POST['current_user_id'])) {
    $user_id = (int)$_POST['current_user_id'];
  }
  $user_id = (int)$user_id;

  if (empty($name) || $user_id <= 0) {
    flash_set('bad', "User and name are required.");
  } else {
    try {
      if ($action === 'create') {
        $check = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
        $check->execute([$user_id]);
        if ($check->fetch()) {
          flash_set('bad', "This user is already an employee.");
        } else {
          $stmt = $pdo->prepare("INSERT INTO employees (user_id, name, position, phone) VALUES (?, ?, ?, ?)");
          $stmt->execute([$user_id, $name, $position, $phone]);
          flash_set('ok', "Employee added");
        }
      } elseif ($action === 'update') {
        $check = $pdo->prepare("SELECT user_id FROM employees WHERE employee_id = ?");
        $check->execute([$employee_id]);
        $existing = $check->fetch();

        if (!$existing) {
          flash_set('bad', "Employee not found.");
        } else {
          $conflict = false;
          if ($existing['user_id'] != $user_id) {
            $check_user = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
            $check_user->execute([$user_id]);
            if ($check_user->fetch()) {
              flash_set('bad', "This user is already linked to another employee.");
              $conflict = true;
            }
          }

          if (!$conflict) {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, position = ?, phone = ? WHERE employee_id = ?");
            $stmt->execute([$name, $position, $phone, $employee_id]);
            flash_set('ok', "Employee updated");
          }
        }
      } else {
        flash_set('bad', "Invalid action.");
      }
    } catch (PDOException $e) {
      flash_set('bad', "Couldn't save: " . $e->getMessage());
    }
  }

  header('Location: ' . $redirect, true, 303);
  exit;
}

if (isset($_GET['delete_emp'])) {
  $employee_id = (int)$_GET['delete_emp'];
  try {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    flash_set('ok', "Employee deleted");
  } catch (PDOException $e) {
    flash_set('bad', "Couldn't delete: " . $e->getMessage());
  }

  header('Location: ' . $redirect, true, 303);
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "e.name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$totalSql = "SELECT COUNT(*) AS cnt FROM employees e $whereSql";
$totalStmt = $pdo->prepare($totalSql);
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalEmployees = (int)($totalRow['cnt'] ?? 0);
$sql = "
    SELECT e.employee_id, e.user_id, e.name, e.position, e.phone, u.name as user_name, u.email as user_email
    FROM employees e
    JOIN users u ON u.user_id = e.user_id
    $whereSql
    ORDER BY e.name ASC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
  $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$allUsersStmt = $pdo->query("
    SELECT
        u.user_id,
        u.name,
        e.employee_id IS NOT NULL as is_employee
    FROM users u
    LEFT JOIN employees e ON u.user_id = e.user_id
    ORDER BY u.name
");
$all_users = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);

$mdTab = 'employees';
$mdPlaceholder = 'Search by name…';
$mdAddLabel = 'Add employee';
$mdPanel = 'drawer-employee';
$mdTotal = $totalEmployees;
$mdNoun = $totalEmployees === 1 ? 'employee' : 'employees';
include __DIR__ . '/../../views/master_data/toolbar.php';
?>
<section class="card">
  <?php if (empty($employees)): ?>
    <?= $search ? empty_state('No employees match “' . $search . '”', 'Try another name or clear the search.') : empty_state('No employees yet', 'Link a user to an employee record so they can submit reports.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Full name</th><th>Position</th><th>Phone</th><th class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($employees as $emp): $id = (int)$emp['employee_id']; ?>
            <tr>
              <td><span class="cell-strong"><?= e($emp['name']) ?></span><span class="cell-sub"><?= e($emp['user_name']) ?> · <?= e($emp['user_email']) ?></span></td>
              <td><?= $emp['position'] ? '<span class="pill pill-role">' . e($emp['position']) . '</span>' : '<span class="text-muted">–</span>' ?></td>
              <td class="tabular-nums"><?= e($emp['phone'] ?: '–') ?></td>
              <td class="col-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-menu-trigger aria-controls="emp-menu-<?= $id ?>" aria-expanded="false" aria-haspopup="menu" aria-label="Actions for <?= e($emp['name']) ?>"><?= icon('ellipsis-horizontal') ?></button>
                <div class="menu" id="emp-menu-<?= $id ?>" role="menu" hidden>
                  <button type="button" class="menu-item" role="menuitem"
                    onclick='editEmployee(<?= $id ?>, <?= (int)$emp['user_id'] ?>, <?= e(json_encode($emp['name'])) ?>, <?= e(json_encode($emp['position'] ?? '')) ?>, <?= e(json_encode($emp['phone'] ?? '')) ?>)'><?= icon('pencil') ?>Edit</button>
                  <div class="menu-sep"></div>
                  <a class="menu-item menu-item-danger" role="menuitem" href="<?= e(query_url(['tab' => 'employees', 'delete_emp' => $id], 'admin_master_data.php')) ?>"
                    data-confirm="Delete employee?" data-confirm-message="“<?= e($emp['name']) ?>” will be removed. This can't be undone." data-confirm-text="Delete" data-confirm-tone="danger"><?= icon('trash') ?>Delete…</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $perPage, $totalEmployees) ?>
  <?php endif; ?>
</section>
</turbo-frame>

<aside class="drawer" id="drawer-employee" role="dialog" aria-modal="true" aria-labelledby="emp-drawer-title" hidden>
  <form method="POST" id="employee-form" action="<?= e($redirect) ?>">
    <input type="hidden" name="entity" value="employees">
    <input type="hidden" name="action" value="create" id="emp-action">
    <input type="hidden" name="employee_id" value="" id="emp-id">
    <input type="hidden" name="current_user_id" id="current-user-id" value="">
    <div class="drawer-header">
      <div class="min-w-0"><div class="drawer-eyebrow" id="emp-form-title">Add employee</div><h2 class="drawer-title" id="emp-drawer-title">New employee</h2></div>
      <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
    </div>
    <div class="drawer-body">
      <div class="field">
        <label class="label" for="user_id">User</label>
        <select name="user_id" id="user_id" class="select" required>
          <option value="">Select user</option>
          <?php foreach ($all_users as $u): $is_used = (bool)$u['is_employee']; ?>
            <option value="<?= (int)$u['user_id'] ?>"<?= $is_used ? ' disabled data-used="1"' : '' ?>><?= e($u['name']) ?><?= $is_used ? ' (already an employee)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="emp-name">Full name</label>
        <input type="text" name="name" id="emp-name" class="input" required>
      </div>
      <div class="field">
        <label class="label" for="emp-position">Position</label>
        <select name="position" id="emp-position" class="select" required>
          <option value="">Select position</option>
          <?php foreach ($position_enum as $pos): ?>
            <option value="<?= e($pos) ?>"><?= e($pos) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="emp-phone">Phone <span class="optional">(optional)</span></label>
        <input type="tel" name="phone" id="emp-phone" class="input">
      </div>
    </div>
    <div class="drawer-footer">
      <button type="button" class="btn btn-ghost" data-drawer-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="emp-submit">Add employee</button>
    </div>
  </form>
</aside>

<script>
(function () {
  const empUserSelect = document.getElementById('user_id');

  function resetEmployeeForm() {
    document.getElementById('employee-form').reset();
    empUserSelect.querySelectorAll('option[data-used]').forEach(o => { o.disabled = true; });
    empUserSelect.disabled = false;
    document.getElementById('emp-form-title').textContent = 'Add employee';
    document.getElementById('emp-drawer-title').textContent = 'New employee';
    document.getElementById('emp-submit').textContent = 'Add employee';
    document.getElementById('emp-action').value = 'create';
    document.getElementById('emp-id').value = '';
    document.getElementById('current-user-id').value = '';
  }

  function editEmployee(id, user_id, name, position, phone) {
    document.getElementById('emp-form-title').textContent = 'Edit employee';
    document.getElementById('emp-drawer-title').textContent = name;
    document.getElementById('emp-submit').textContent = 'Save';
    // Linked user can't be changed here; keep it visible and send it via current_user_id
    const opt = empUserSelect.querySelector('option[value="' + user_id + '"]');
    if (opt) opt.disabled = false;
    empUserSelect.value = user_id;
    empUserSelect.disabled = true;
    document.getElementById('current-user-id').value = user_id;
    document.getElementById('emp-name').value = name;
    document.getElementById('emp-position').value = position;
    document.getElementById('emp-phone').value = phone;
    document.getElementById('emp-action').value = 'update';
    document.getElementById('emp-id').value = id;
    Vorta.drawer.openPanel('drawer-employee');
  }

  document.getElementById('drawer-employee').addEventListener('drawer:mode', resetEmployeeForm);
  document.getElementById('drawer-employee').addEventListener('drawer:close', resetEmployeeForm);

  // dipanggil dari atribut onclick
  window.editEmployee = editEmployee;
})();
</script>
