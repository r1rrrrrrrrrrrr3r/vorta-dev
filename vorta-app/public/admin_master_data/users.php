<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/tenant.php';
require_admin();
$company_id = current_company_id();

$success = '';
$error = '';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (isset($_SESSION['success'])) {
  $success = $_SESSION['success'];
  unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']);
}

$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'users') {
  csrf_verify();
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
    $_SESSION['error'] = "Name and email are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
  } elseif ($action === 'create' && mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
    $_SESSION['error'] = "A password of at least " . ACCOUNT_MIN_PASSWORD_LENGTH . " characters is required.";
  } elseif ($action === 'update' && $password !== '' && mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
    $_SESSION['error'] = "Password must be at least " . ACCOUNT_MIN_PASSWORD_LENGTH . " characters.";
  } else {
    try {
      if ($action === 'create') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND company_id = ?");
        $check->execute([$email, $company_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "Email is already in use.";
        } else {
          $pass_hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
          $stmt->execute([$company_id, $name, $email, $pass_hash, $role]);
          $_SESSION['success'] = "User added successfully.";
        }
      } elseif ($action === 'update') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id, $company_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "Email is already in use by another user.";
        } else {
          $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ? AND company_id = ?");
          $stmt->execute([$name, $email, $role, $user_id, $company_id]);
          if (!empty($password)) {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $pstmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND company_id = ?");
            $pstmt->execute([$pass_hash, $user_id, $company_id]);
          }
          $_SESSION['success'] = "User updated successfully.";
        }
      } else {
        $_SESSION['error'] = "Aksi tidak diketahui.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Failed to save data.";
    }
  }

  $params = ['tab' => 'users', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
  csrf_verify();
  $user_id = (int)$_POST['delete_user'];
  try {
    if ($user_id === (int)($_SESSION['user']['user_id'] ?? 0)) {
      $_SESSION['error'] = "You cannot delete your own account.";
    } else {
      $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND company_id = ?");
      $stmt->execute([$user_id, $company_id]);

      if ($stmt->rowCount()) {
        $_SESSION['success'] = "User deleted successfully.";
      } else {
        $_SESSION['error'] = "User not found.";
      }
    }
  } catch (PDOException $e) {
    $_SESSION['error'] = "Failed to delete data.";
  }

  $params = ['tab' => 'users', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$where[] = "company_id = ?";
$params[] = $company_id;
$whereSql = "WHERE " . implode(" AND ", $where);
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalUsers = (int)($totalRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalUsers / $perPage);
$sql = "SELECT user_id, name, email, role FROM users $whereSql ORDER BY user_id ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
  $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

function page_url($p)
{
  $q = $_GET;
  $q['page'] = $p;
  return 'admin_master_data.php?' . http_build_query($q);
}
?>

<?php if ($success): ?>
  <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<div id="user-form-section" class="bg-gray-50 p-6 rounded-lg mb-8">
  <h2 class="text-lg font-semibold text-gray-800 mb-4" id="form-title">
    Added New User
  </h2>
  <form method="POST" id="user-form">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="users">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="user_id" value="" id="user-id-input">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input type="text" name="name" id="user-name"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="...." required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" id="user-email"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="example@domain.com" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" id="user-password"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="At least <?= ACCOUNT_MIN_PASSWORD_LENGTH ?> characters">
        <p class="text-xs text-gray-500 mt-1">Required when adding a user; leave blank only when editing without changing it.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
        <select name="role" id="user-role"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700" id="submit-btn">
        Add
      </button>
      <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 hidden">
        Cancel
      </button>
    </div>
  </form>
</div>

<div class="mb-6">
  <form method="GET" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="tab" value="users">
    <input
      type="text"
      name="search"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search By Name..."
      class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
      Search
    </button>
    <?php if ($search): ?>
      <a href="?tab=users" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
        Clear
      </a>
    <?php endif; ?>
  </form>
</div>

<div class="bg-white rounded-xl shadow-md overflow-hidden">
  <div class="p-6 md:p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">User Management</h2>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left border-b border-gray-200">
            <th class="pb-3 font-medium text-gray-600">ID</th>
            <th class="pb-3 font-medium text-gray-600">Name</th>
            <th class="pb-3 font-medium text-gray-600">Email</th>
            <th class="pb-3 font-medium text-gray-600">Role</th>
            <th class="pb-3 font-medium text-gray-600">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="5" class="py-6 text-center text-sm text-gray-400">
                No users found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= (int)$u['user_id'] ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                  <?= htmlspecialchars($u['name']) ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= htmlspecialchars($u['email']) ?>
                </td>
                <td class="py-4 whitespace-nowrap">
                  <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                    <?= htmlspecialchars($u['role']) ?>
                  </span>
                </td>
                <td class="py-4 whitespace-nowrap space-x-1">
                  <button
                    type="button"
                    onclick='editUser(<?= (int)$u['user_id'] ?>, <?= json_encode($u['name']) ?>, <?= json_encode($u['email']) ?>, <?= json_encode($u['role']) ?>)'
                    class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600 transition">
                    Edit
                  </button>
                  <button
                    type="button"
                    onclick='confirmDelete(<?= (int)$u['user_id'] ?>, <?= json_encode($u['name']) ?>)'
                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition">
                    Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-6 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="text-sm text-gray-600">
      Page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span>
    </div>

    <ul class="flex flex-wrap items-center gap-2">
      <li>
        <a href="<?= $page > 1 ? page_url(1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page > 1 ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">&laquo; First</span>
          <span class="sm:hidden">&laquo;</span>
        </a>
      </li>

      <li>
        <a href="<?= $page > 1 ? page_url($page - 1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page > 1 ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">&lsaquo; Prev</span>
          <span class="sm:hidden">&lsaquo;</span>
        </a>
      </li>

      <?php
      $start = max(1, $page - 2);
      $end = min($totalPages, $page + 2);

      if ($start > 1) {
        echo '<li><a class="px-3 py-1 rounded border hover:bg-gray-100" href="' . page_url(1) . '">1</a></li>';
        if ($start > 2) echo '<li class="px-2">...</li>';
      }

      for ($p = $start; $p <= $end; $p++): ?>
        <li>
          <a href="<?= page_url($p) ?>"
            class="px-3 py-1 rounded border <?= $p === $page ? 'bg-indigo-600 text-white' : 'hover:bg-gray-100' ?>">
            <?= $p ?>
          </a>
        </li>
      <?php endfor;

      if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<li class="px-2">...</li>';
        echo '<li><a class="px-3 py-1 rounded border hover:bg-gray-100" href="' . page_url($totalPages) . '">' . $totalPages . '</a></li>';
      }
      ?>

      <li>
        <a href="<?= $page < $totalPages ? page_url($page + 1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page < $totalPages ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">Next &rsaquo;</span>
          <span class="sm:hidden">&rsaquo;</span>
        </a>
      </li>

      <li>
        <a href="<?= $page < $totalPages ? page_url($totalPages) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page < $totalPages ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">Last &raquo;</span>
          <span class="sm:hidden">&raquo;</span>
        </a>
      </li>
    </ul>
  </nav>
<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function editUser(id, name, email, role) {
    document.getElementById('form-title').textContent = 'Edit User';
    document.getElementById('user-name').value = name;
    document.getElementById('user-email').value = email;
    document.getElementById('user-role').value = role;
    document.getElementById('action-input').value = 'update';
    document.getElementById('user-id-input').value = id;
    document.getElementById('submit-btn').textContent = 'Update';
    document.getElementById('cancel-edit').classList.remove('hidden');

    document.getElementById('user-form-section').scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    });
  }

  document.getElementById('cancel-edit')?.addEventListener('click', function() {
    document.getElementById('user-form').reset();
    document.getElementById('form-title').textContent = 'Add New User';
    document.getElementById('action-input').value = 'create';
    document.getElementById('user-id-input').value = '';
    document.getElementById('user-role').value = 'staff';
    document.getElementById('submit-btn').textContent = 'Add';
    this.classList.add('hidden');
  });

  function submitDelete(name, value) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    [
      ['csrf_token', document.querySelector('input[name="csrf_token"]').value],
      [name, value]
    ].forEach(([key, val]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = val;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  }

  function confirmDelete(id, name) {
    Swal.fire({
      title: 'Delete this record?',
      text: `You are about to delete user: "${name}"`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        submitDelete('delete_user', id);
      }
    });
  }
</script>