<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_admin();

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// --- Search & Pagination
$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// --- Ambil daftar nilai ENUM dari kolom `position` di tabel `employees`
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

// --- Handle Create & Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['entity'] === 'employees') {
  $name = trim($_POST['name'] ?? '');
  $position = trim($_POST['position'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $action = $_POST['action'] ?? '';
  $employee_id = (int)($_POST['employee_id'] ?? 0);

  // Ambil user_id: dari dropdown, atau dari hidden saat edit
  $user_id = $_POST['user_id'] ?? 0;
  if ($action === 'update' && empty($user_id) && !empty($_POST['current_user_id'])) {
    $user_id = (int)$_POST['current_user_id'];
  }
  $user_id = (int)$user_id;

  if (empty($name) || $user_id <= 0) {
    $_SESSION['error'] = "User dan nama wajib diisi.";
  } else {
    try {
      if ($action === 'create') {
        // Cek apakah user_id sudah ada
        $check = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
        $check->execute([$user_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "User ini sudah menjadi employee.";
        } else {
          $stmt = $pdo->prepare("INSERT INTO employees (user_id, name, position, phone) VALUES (?, ?, ?, ?)");
          $stmt->execute([$user_id, $name, $position, $phone]);
          $_SESSION['success'] = "Employee berhasil ditambahkan.";
        }
      } elseif ($action === 'update') {
        // Cek employee_id valid
        $check = $pdo->prepare("SELECT user_id FROM employees WHERE employee_id = ?");
        $check->execute([$employee_id]);
        $existing = $check->fetch();

        if (!$existing) {
          $_SESSION['error'] = "Employee tidak ditemukan.";
        } else {
          // Cek jika user_id diubah ke yang sudah dipakai
          if ($existing['user_id'] != $user_id) {
            $check_user = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
            $check_user->execute([$user_id]);
            if ($check_user->fetch()) {
              $_SESSION['error'] = "User ini sudah menjadi employee lain.";
            }
          }

          if (!isset($_SESSION['error'])) {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, position = ?, phone = ? WHERE employee_id = ?");
            $stmt->execute([$name, $position, $phone, $employee_id]);
            $_SESSION['success'] = "Employee berhasil diperbarui.";
          }
        }
      } else {
        $_SESSION['error'] = "Aksi tidak valid.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Gagal menyimpan data: " . $e->getMessage();
    }
  }

  // Redirect dengan JavaScript
  $params = ['tab' => 'employees', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

// --- Handle Delete
if (isset($_GET['delete_emp'])) {
  $employee_id = (int)$_GET['delete_emp'];
  try {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $_SESSION['success'] = "Employee berhasil dihapus.";
  } catch (PDOException $e) {
    $_SESSION['error'] = "Gagal menghapus: " . $e->getMessage();
  }

  $params = ['tab' => 'employees', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

// --- Build WHERE clause for search
$where = [];
$params = [];
if ($search) {
  $where[] = "e.name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- Total count
$totalSql = "SELECT COUNT(*) AS cnt FROM employees e $whereSql";
$totalStmt = $pdo->prepare($totalSql);
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalEmployees = (int)($totalRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalEmployees / $perPage);

// --- Fetch employees with user name
$sql = "
    SELECT e.employee_id, e.employee_code, e.user_id, e.name, e.position, e.phone, u.name as user_name 
    FROM employees e 
    JOIN users u ON u.user_id = e.user_id 
    $whereSql 
    ORDER BY u.name 
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

// --- Ambil semua user, plus status apakah sudah jadi employee
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

// --- Helper for pagination URL
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
<div class="bg-gray-50 p-6 rounded-lg mb-8">
  <div class="flex flex-row justify-between">
    <h2 class="text-lg font-semibold text-gray-800 mb-4" id="emp-form-title">
      Added Employee
    </h2>
    <div class="text-sm text-gray-600">Total Employees: <span class="font-medium"><?= $totalEmployees ?></span></div>
  </div>
  <form method="POST">
    <input type="hidden" name="entity" value="employees">
    <input type="hidden" name="action" value="create" id="emp-action">
    <input type="hidden" name="employee_id" value="" id="emp-id">
    <input type="hidden" name="current_user_id" id="current-user-id" value="">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
        <select name="user_id" id="user_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
          <option value="">-- Select User --</option>
          <?php foreach ($all_users as $u): ?>
            <?php
            $is_used = (bool)$u['is_employee'];
            $is_current = $u['user_id'] == ($current_user_id ?? 0);
            ?>
            <option value="<?= $u['user_id'] ?>"
              <?= $is_used && !$is_current ? 'disabled' : '' ?>
              <?= ($current_user_id ?? '') == $u['user_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name']) ?>
              <?php if ($is_used && !$is_current): ?> (Sudah jadi employee) <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
        <input type="text" name="name" id="emp-name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
        <select name="position" id="emp-position" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
          <option value="">-- Select Position --</option>
          <?php foreach ($position_enum as $pos): ?>
            <option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
        <input type="text" name="phone" id="emp-phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        Save
      </button>
      <button type="button" id="cancel-emp" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 hidden">
        Cancel
      </button>
    </div>
  </form>
</div>

<!-- Search Form -->
<div class="mb-6">
  <form method="GET" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="tab" value="employees">
    <input
      type="text"
      name="search"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search by employee name..."
      class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
      Search
    </button>
    <?php if ($search): ?>
      <a href="?tab=employees" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
        Clear
      </a>
    <?php endif; ?>
  </form>
</div>

<!-- Tabel Data -->
<div class="overflow-x-auto">
  <table class="w-full min-w-[800px]">
    <thead class="bg-gray-50 border-b">
      <tr class="text-left text-sm text-gray-600">
        <th class="px-4 py-3">ID</th>
        <th class="px-4 py-3">User</th>
        <th class="px-4 py-3">Full Name</th>
        <th class="px-4 py-3">Position</th>
        <th class="px-4 py-3">Phone</th>
        <th class="px-4 py-3">Action</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php if (empty($employees)): ?>
        <tr>
          <td colspan="6" class="px-4 py-6 text-center text-gray-500">Tidak ada employee ditemukan.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($employees as $e): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3"><?= htmlspecialchars($e['employee_code'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($e['user_name']) ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($e['name']) ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($e['position'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($e['phone'] ?? '-') ?></td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button
                  onclick='editEmployee(<?= (int)$e['employee_id'] ?>, <?= (int)$e['user_id'] ?>, <?= json_encode($e['name']) ?>, <?= json_encode($e['position'] ?? '') ?>, <?= json_encode($e['phone'] ?? '') ?>)'
                  class="px-3 py-1 bg-yellow-500 text-white rounded text-xs hover:brightness-95">
                  Edit
                </button>
                <button
                  onclick='confirmDeleteEmployee(<?= (int)$e['employee_id'] ?>, <?= json_encode($e['name']) ?>)'
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
      $end   = min($totalPages, $page + 2);

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
  function editEmployee(id, user_id, name, position, phone) {
    document.getElementById('emp-form-title').textContent = 'Edit Employee';
    document.getElementById('user_id').value = user_id;
    document.getElementById('current-user-id').value = user_id;
    document.getElementById('emp-name').value = name;
    document.getElementById('emp-position').value = position;
    document.getElementById('emp-phone').value = phone;
    document.getElementById('emp-action').value = 'update';
    document.getElementById('emp-id').value = id;
    document.getElementById('cancel-emp').classList.remove('hidden');
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  }

  document.getElementById('cancel-emp')?.addEventListener('click', function() {
    document.querySelector('form').reset();
    document.getElementById('emp-form-title').textContent = 'Tambah Employee';
    document.getElementById('emp-action').value = 'create';
    document.getElementById('emp-id').value = '';
    document.getElementById('current-user-id').value = '';
    this.classList.add('hidden');
  });

  // Fungsi konfirmasi hapus dengan SweetAlert
  function confirmDeleteEmployee(employeeId, employeeName) {
    Swal.fire({
      title: 'Yakin hapus?',
      text: `Anda akan menghapus employee: ${employeeName}`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = `<?= page_url($page) ?>&delete_emp=${employeeId}`;
      }
    });
  }
</script>