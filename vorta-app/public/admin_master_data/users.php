<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ui.php';
require_admin();

$search = trim($_GET['search'] ?? '');
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$backParams = ['tab' => 'users', 'page' => $page];
if ($search) $backParams['search'] = $search;
$redirect = 'admin_master_data.php?' . http_build_query($backParams);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'users') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $action = $_POST['action'] ?? 'create';
  $user_id = (int)($_POST['user_id'] ?? 0);
  $role = $_POST['role'] ?? 'staff';

  if (!in_array($role, ['staff', 'admin'])) {
    $role = 'staff';
  }

  if (empty($name) || empty($email)) {
    flash_set('bad', "Name and email are required.");
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('bad', "Invalid email format.");
  } else {
    try {
      if ($action === 'create') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
          flash_set('bad', "Email is already in use.");
        } else {
          $pass_hash = password_hash($password ?: 'password', PASSWORD_DEFAULT);
          $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
          $stmt->execute([$name, $email, $pass_hash, $role]);
          flash_set('ok', "User added");
        }
      } elseif ($action === 'update') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
          flash_set('bad', "Email is already in use by another user.");
        } else {
          $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ?");
          $stmt->execute([$name, $email, $role, $user_id]);
          if (!empty($password)) {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $pstmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $pstmt->execute([$pass_hash, $user_id]);
          }
          flash_set('ok', "User updated");
        }
      } else {
        flash_set('bad', "Unknown action.");
      }
    } catch (PDOException $e) {
      flash_set('bad', "Couldn't save. Try again.");
    }
  }

  header('Location: ' . $redirect);
  exit;
}

if (isset($_GET['delete_user'])) {
  $user_id = (int)$_GET['delete_user'];
  try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount()) {
      flash_set('ok', "User deleted");
    } else {
      flash_set('bad', "User not found.");
    }
  } catch (PDOException $e) {
    flash_set('bad', "Couldn't delete this user. They may still have reports or attendance records.");
  }

  header('Location: ' . $redirect);
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalUsers = (int)($totalRow['cnt'] ?? 0);
$sql = "SELECT user_id, name, email, role FROM users $whereSql ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
  $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selfId = (int) $_SESSION['user']['user_id'];

$mdTab = 'users';
$mdPlaceholder = 'Search by name…';
$mdAddLabel = 'Add user';
$mdPanel = 'drawer-user';
$mdTotal = $totalUsers;
$mdNoun = $totalUsers === 1 ? 'user' : 'users';
include __DIR__ . '/../../views/master_data/toolbar.php';
?>
<section class="card">
  <?php if (empty($users)): ?>
    <?= $search ? empty_state('No users match “' . $search . '”', 'Try another name or clear the search.') : empty_state('No users yet') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Role</th><th class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): $id = (int)$u['user_id']; ?>
            <tr>
              <td><span class="cell-strong"><?= e($u['name']) ?><?= $id === $selfId ? ' <span class="text-muted font-normal">(you)</span>' : '' ?></span><span class="cell-sub"><?= e($u['email']) ?></span></td>
              <td><?= role_pill($u['role']) ?></td>
              <td class="col-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-menu-trigger aria-controls="user-menu-<?= $id ?>" aria-expanded="false" aria-haspopup="menu" aria-label="Actions for <?= e($u['name']) ?>"><?= icon('ellipsis-horizontal') ?></button>
                <div class="menu" id="user-menu-<?= $id ?>" role="menu" hidden>
                  <button type="button" class="menu-item" role="menuitem"
                    onclick='editUser(<?= $id ?>, <?= e(json_encode($u['name'])) ?>, <?= e(json_encode($u['email'])) ?>, <?= e(json_encode($u['role'])) ?>)'><?= icon('pencil') ?>Edit</button>
                  <div class="menu-sep"></div>
                  <a class="menu-item menu-item-danger" role="menuitem" href="<?= e(query_url(['tab' => 'users', 'delete_user' => $id], 'admin_master_data.php')) ?>"
                    data-confirm="Delete user?" data-confirm-message="“<?= e($u['name']) ?>” will be removed. This can't be undone." data-confirm-text="Delete" data-confirm-tone="danger"><?= icon('trash') ?>Delete…</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $perPage, $totalUsers) ?>
  <?php endif; ?>
</section>

<aside class="drawer" id="drawer-user" role="dialog" aria-modal="true" aria-labelledby="user-drawer-title" hidden>
  <form method="POST" id="user-form" action="<?= e($redirect) ?>">
    <input type="hidden" name="entity" value="users">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="user_id" value="" id="user-id-input">
    <div class="drawer-header">
      <div class="min-w-0"><div class="drawer-eyebrow" id="form-title">Add user</div><h2 class="drawer-title" id="user-drawer-title">New user</h2></div>
      <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
    </div>
    <div class="drawer-body">
      <div class="field">
        <label class="label" for="user-name">Name</label>
        <input type="text" name="name" id="user-name" class="input" required>
      </div>
      <div class="field">
        <label class="label" for="user-email">Email</label>
        <input type="email" name="email" id="user-email" class="input" placeholder="name@company.com" required>
      </div>
      <div class="field">
        <label class="label" for="user-password">Password <span class="optional">(optional)</span></label>
        <input type="password" name="password" id="user-password" class="input" autocomplete="new-password">
        <p class="help" id="user-password-help">Leave empty to use the default password “password”.</p>
      </div>
      <div class="field">
        <span class="label" id="user-role-label">Role</span>
        <div class="seg" role="radiogroup" aria-labelledby="user-role-label">
          <label><input type="radio" name="role" value="staff" class="sr-only" checked><span>Staff</span></label>
          <label><input type="radio" name="role" value="admin" class="sr-only"><span>Admin</span></label>
        </div>
      </div>
    </div>
    <div class="drawer-footer">
      <button type="button" class="btn btn-ghost" data-drawer-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="user-submit">Add user</button>
    </div>
  </form>
</aside>

<script>
  function resetUserForm() {
    document.getElementById('user-form').reset();
    document.getElementById('form-title').textContent = 'Add user';
    document.getElementById('user-drawer-title').textContent = 'New user';
    document.getElementById('user-submit').textContent = 'Add user';
    document.getElementById('user-password-help').textContent = 'Leave empty to use the default password “password”.';
    document.getElementById('action-input').value = 'create';
    document.getElementById('user-id-input').value = '';
  }

  function editUser(id, name, email, role) {
    document.getElementById('form-title').textContent = 'Edit user';
    document.getElementById('user-drawer-title').textContent = name;
    document.getElementById('user-submit').textContent = 'Save';
    document.getElementById('user-password-help').textContent = 'Leave empty to keep the current password.';
    document.getElementById('user-name').value = name;
    document.getElementById('user-email').value = email;
    document.getElementById('user-password').value = '';
    const radio = document.querySelector('#user-form input[name="role"][value="' + role + '"]');
    if (radio) radio.checked = true;
    document.getElementById('action-input').value = 'update';
    document.getElementById('user-id-input').value = id;
    Vorta.drawer.openPanel('drawer-user');
  }

  document.getElementById('drawer-user').addEventListener('drawer:mode', resetUserForm);
  document.getElementById('drawer-user').addEventListener('drawer:close', resetUserForm);
</script>
