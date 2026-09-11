<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_admin();

// --- Init
$success = '';
$error = '';

// --- Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// --- Ambil pesan dari session
if (isset($_SESSION['success'])) {
  $success = $_SESSION['success'];
  unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']);
}

// --- Search & Pagination settings
$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// --- Handle Create & Update (POST)
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
    $_SESSION['error'] = "Nama dan Email wajib diisi.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Format email tidak valid.";
  } else {
    try {
      if ($action === 'create') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
          $_SESSION['error'] = "Email sudah digunakan.";
        } else {
          $pass_hash = password_hash($password ?: 'password', PASSWORD_DEFAULT);
          $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
          $stmt->execute([$name, $email, $pass_hash, $role]);
          $_SESSION['success'] = "User berhasil ditambahkan.";
        }
      } elseif ($action === 'update') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "Email sudah digunakan oleh user lain.";
        } else {
          $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ?");
          $stmt->execute([$name, $email, $role, $user_id]);
          if (!empty($password)) {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $pstmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $pstmt->execute([$pass_hash, $user_id]);
          }
          $_SESSION['success'] = "User berhasil diperbarui.";
        }
      } else {
        $_SESSION['error'] = "Aksi tidak diketahui.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Gagal menyimpan data.";
    }
  }

  // Redirect dengan JavaScript
  $params = ['tab' => 'users', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

// --- Handle Delete (GET)
if (isset($_GET['delete_user'])) {
  $user_id = (int)$_GET['delete_user'];
  try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount()) {
      $_SESSION['success'] = "User berhasil dihapus.";
    } else {
      $_SESSION['error'] = "User tidak ditemukan.";
    }
  } catch (PDOException $e) {
    $_SESSION['error'] = "Gagal menghapus data.";
  }

  $params = ['tab' => 'users', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

// --- Build WHERE clause
$where = [];
$params = [];
if ($search) {
  $where[] = "name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- Total count
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalUsers = (int)($totalRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalUsers / $perPage);

// --- Fetch data
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

// --- Helper pagination
function page_url($p)
{
  $q = $_GET;
  $q['page'] = $p;
  return 'admin_master_data.php?' . http_build_query($q);
}
?>

<!-- Messages -->
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

<!-- Form Tambah/Edit -->
<div id="user-form-section" class="bg-gray-50 p-6 rounded-lg mb-8">
  <h2 class="text-lg font-semibold text-gray-800 mb-4" id="form-title">
    Added New User
  </h2>
  <form method="POST" id="user-form">
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
          placeholder="Leave blank if you don't want to change it.">
        <p class="text-xs text-gray-500 mt-1">If empty when adding, default password: <code>password</code></p>
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

<!-- Search Form -->
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

<!-- Tabel Data -->
<div class="overflow-x-auto">
  <table class="w-full min-w-[700px]">
    <thead class="bg-gray-50 border-b">
      <tr class="text-left text-sm text-gray-600">
        <th class="px-4 py-3">ID</th>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Role</th>
        <th class="px-4 py-3">Action</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php if (empty($users)): ?>
        <tr>
          <td colspan="5" class="px-4 py-6 text-center text-gray-500">Tidak ada user ditemukan.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3"><?= (int)$u['user_id'] ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($u['name']) ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($u['email']) ?></td>
            <td class="px-4 py-3">
              <span class="uppercase text-xs font-medium px-2 py-1 rounded
                <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' ?>">
                <?= htmlspecialchars($u['role']) ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button
                  onclick='editUser(<?= (int)$u['user_id'] ?>, <?= json_encode($u['name']) ?>, <?= json_encode($u['email']) ?>, <?= json_encode($u['role']) ?>)'
                  class="px-3 py-1 bg-yellow-500 text-white rounded text-xs hover:brightness-95">
                  Edit
                </button>
                <button
                  onclick='confirmDelete(<?= (int)$u['user_id'] ?>, <?= json_encode($u['name']) ?>)'
                  class="px-3 py-1 bg-red-500 text-white rounded text-xs hover:brightness-95">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <nav class="mt-6 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="text-sm text-gray-600">
      Page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span>
    </div>

    <ul class="flex flex-wrap items-center gap-2">
      <!-- Tombol First -->
      <li>
        <a href="<?= $page > 1 ? page_url(1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page > 1 ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">&laquo; First</span>
          <span class="sm:hidden">&laquo;</span>
        </a>
      </li>

      <!-- Tombol Prev -->
      <li>
        <a href="<?= $page > 1 ? page_url($page - 1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page > 1 ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">&lsaquo; Prev</span>
          <span class="sm:hidden">&lsaquo;</span>
        </a>
      </li>

      <!-- Nomor Halaman -->
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

      <!-- Tombol Next -->
      <li>
        <a href="<?= $page < $totalPages ? page_url($page + 1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page < $totalPages ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">Next &rsaquo;</span>
          <span class="sm:hidden">&rsaquo;</span>
        </a>
      </li>

      <!-- Tombol Last -->
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


<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Edit User
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

  // Cancel edit
  document.getElementById('cancel-edit')?.addEventListener('click', function() {
    document.getElementById('user-form').reset();
    document.getElementById('form-title').textContent = 'Tambah User Baru';
    document.getElementById('action-input').value = 'create';
    document.getElementById('user-id-input').value = '';
    document.getElementById('user-role').value = 'staff';
    document.getElementById('submit-btn').textContent = 'Tambah';
    this.classList.add('hidden');
  });

  // Konfirmasi hapus
  function confirmDelete(id, name) {
    Swal.fire({
      title: 'Yakin hapus?',
      text: `Anda akan menghapus user: "${name}"`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        const url = new URL(window.location.href);
        url.searchParams.set('delete_user', id);
        url.searchParams.set('tab', 'users');
        if ('<?= $search ?>' !== '') url.searchParams.set('search', '<?= addslashes($search) ?>');
        if (<?= $page ?> > 1) url.searchParams.set('page', <?= $page ?>);
        window.location.href = url.toString();
      }
    });
  }
</script>