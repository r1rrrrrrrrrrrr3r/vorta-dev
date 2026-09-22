<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/tenant.php';
require_once __DIR__ . '/../../lib/audit.php';
require_admin();
$company_id = current_company_id();

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['entity'] === 'employees') {
  csrf_verify();
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
    $_SESSION['error'] = "User and name are required.";
  } else {
    try {
      if ($action === 'create') {

        $check = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND company_id = ?");
        $check->execute([$user_id, $company_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "This user is already an employee.";
        } else {
          $stmt = $pdo->prepare("INSERT INTO employees (company_id, user_id, name, position, phone) VALUES (?, ?, ?, ?, ?)");
          $stmt->execute([$company_id, $user_id, $name, $position, $phone]);
          audit_log($pdo, 'employee.created', 'employees', $pdo->lastInsertId(), ['user_id' => $user_id]);
          $_SESSION['success'] = "Employee added successfully.";
        }
      } elseif ($action === 'update') {

        $check = $pdo->prepare("SELECT user_id FROM employees WHERE employee_id = ? AND company_id = ?");
        $check->execute([$employee_id, $company_id]);
        $existing = $check->fetch();

        if (!$existing) {
          $_SESSION['error'] = "Employee not found.";
        } else {

          if ($existing['user_id'] != $user_id) {
            $check_user = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND company_id = ?");
            $check_user->execute([$user_id, $company_id]);
            if ($check_user->fetch()) {
              $_SESSION['error'] = "This user is already linked to another employee.";
            }
          }

          if (!isset($_SESSION['error'])) {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, position = ?, phone = ? WHERE employee_id = ? AND company_id = ?");
            $stmt->execute([$name, $position, $phone, $employee_id, $company_id]);
            audit_log($pdo, 'employee.updated', 'employees', $employee_id);
            $_SESSION['success'] = "Employee updated successfully.";
          }
        }
      } else {
        $_SESSION['error'] = "Invalid action.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Failed to save data: " . $e->getMessage();
    }
  }

  $params = ['tab' => 'employees', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'employee_import') {
  csrf_verify();
  $file = $_FILES['employee_csv'] ?? null;
  $imported = 0;
  $errors = [];
  if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    $_SESSION['error'] = 'Choose a valid CSV file.';
  } elseif (($file['size'] ?? 0) > 2097152) {
    $_SESSION['error'] = 'CSV files must be 2 MB or smaller.';
  } else {
    $handle = fopen($file['tmp_name'], 'rb');
    $header = $handle ? fgetcsv($handle) : false;
    $header = $header ? array_map(static fn($value) => strtolower(trim((string)$value)), $header) : [];
    $required = ['email', 'name', 'position', 'phone'];
    if ($header !== $required) {
      $errors[] = 'Header must be exactly: email,name,position,phone';
    } else {
      $userStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND company_id = ?');
      $employeeCheck = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND company_id = ?');
      $insert = $pdo->prepare('INSERT INTO employees (company_id, user_id, name, position, phone) VALUES (?, ?, ?, ?, ?)');
      $seen = [];
      $rowNumber = 1;
      while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;
        if (count($row) === 1 && trim((string)$row[0]) === '') continue;
        if (count($row) !== 4) {
          $errors[] = "Row {$rowNumber}: expected 4 columns.";
          continue;
        }
        [$email, $name, $position, $phone] = array_map(static fn($value) => trim((string)$value), $row);
        $emailKey = strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !in_array($position, $position_enum, true) || isset($seen[$emailKey])) {
          $errors[] = "Row {$rowNumber}: invalid email/name/position or duplicate email.";
          continue;
        }
        $seen[$emailKey] = true;
        $userStmt->execute([$email, $company_id]);
        $user = $userStmt->fetch();
        if (!$user) {
          $errors[] = "Row {$rowNumber}: no matching user account for {$email}.";
          continue;
        }
        $employeeCheck->execute([(int)$user['user_id'], $company_id]);
        if ($employeeCheck->fetch()) {
          $errors[] = "Row {$rowNumber}: {$email} is already an employee.";
          continue;
        }
        $insert->execute([$company_id, (int)$user['user_id'], $name, $position, $phone]);
        $imported++;
      }
      fclose($handle);
    }
    if ($imported > 0) {
      audit_log($pdo, 'employee.imported', 'employees', null, ['count' => $imported]);
    }
    $_SESSION['success'] = "{$imported} employee(s) imported.";
    if ($errors) $_SESSION['error'] = implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' More rows were skipped.' : '');
  }
  $params = ['tab' => 'employees', 'page' => $page];
  if ($search) $params['search'] = $search;
  header('Location: admin_master_data.php?' . http_build_query($params));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_emp'])) {
  csrf_verify();
  $employee_id = (int)$_POST['delete_emp'];
  try {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ? AND company_id = ?");
    $stmt->execute([$employee_id, $company_id]);
    audit_log($pdo, 'employee.deleted', 'employees', $employee_id);
    $_SESSION['success'] = "Employee deleted successfully.";
  } catch (PDOException $e) {
    $_SESSION['error'] = "Failed to delete: " . $e->getMessage();
  }

  $params = ['tab' => 'employees', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "e.name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$where[] = "e.company_id = ?";
$params[] = $company_id;
$whereSql = "WHERE " . implode(" AND ", $where);
$totalSql = "SELECT COUNT(*) AS cnt FROM employees e $whereSql";
$totalStmt = $pdo->prepare($totalSql);
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalEmployees = (int)($totalRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalEmployees / $perPage);
$sql = "
    SELECT e.employee_id, e.user_id, e.name, e.position, e.phone, u.name as user_name 
    FROM employees e 
    JOIN users u ON u.user_id = e.user_id AND u.company_id = e.company_id
    $whereSql 
    ORDER BY e.employee_id ASC 
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
    LEFT JOIN employees e ON u.user_id = e.user_id AND e.company_id = u.company_id
    WHERE u.company_id = $company_id
    ORDER BY u.name
");
$all_users = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);
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

<div class="bg-gray-50 p-6 rounded-lg mb-8">
  <div class="flex flex-row justify-between">
    <h2 class="text-lg font-semibold text-gray-800 mb-4" id="emp-form-title">
      Added Employee
    </h2>
    <div class="text-sm text-gray-600">Total Employees: <span class="font-medium"><?= $totalEmployees ?></span></div>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
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
              <?php if ($is_used && !$is_current): ?> (Employee) <?php endif; ?>
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

<style>
  .vorta-import { margin-bottom:32px; padding:20px; background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:var(--shadow-card,0 1px 3px rgba(0,0,0,.06),0 6px 18px -8px rgba(0,0,0,.12)); }
  .vorta-import__head { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px; }
  .vorta-import__intro { display:flex; align-items:flex-start; gap:12px; }
  .vorta-import__icon { flex:0 0 40px; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:rgba(99,102,241,.12); }
  .vorta-import__icon svg { display:block; color:#4f46e5; }
  .vorta-import__title { margin:0; font-size:18px; font-weight:700; color:var(--text,#1f2937); }
  .vorta-import__subtitle { margin:4px 0 0; font-size:14px; color:var(--text-muted,#6b7280); }
  .vorta-import__chip { margin-top:8px; font-size:12px; color:var(--text-muted,#6b7280); }
  .vorta-import__chip code { background:var(--surface-3,#f3f4f6); color:var(--text,#1f2937); padding:2px 8px; border-radius:6px; font-size:12px; }
  .vorta-import__template { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; padding:9px 14px; border-radius:10px; border:1px solid var(--border,#e5e7eb); background:var(--surface,#fff); color:var(--text,#1f2937); font-size:13px; font-weight:600; text-decoration:none; transition:background .15s ease; }
  .vorta-import__template:hover { background:var(--surface-3,#f3f4f6); }
  .vorta-import__form { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
  .vorta-file { position:relative; flex:1 1 260px; min-width:0; }
  .vorta-file__fake { display:flex; align-items:center; gap:8px; width:100%; box-sizing:border-box; padding:10px 14px; border-radius:10px; border:1px solid var(--border,#e5e7eb); background:var(--surface,#fff); color:var(--text-muted,#6b7280); font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .vorta-file__fake svg { flex:0 0 16px; color:var(--text-muted,#9ca3af); }
  .vorta-file:hover .vorta-file__fake { border-color:#a5b4fc; }
  .vorta-file__input { position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; margin:0; }
  .vorta-import__submit { padding:10px 20px; border-radius:10px; border:none; background:#4f46e5; color:#fff; font-size:14px; font-weight:700; cursor:pointer; transition:background .15s ease; }
  .vorta-import__submit:hover { background:#4338ca; }
  @media (max-width:480px) { .vorta-import { padding:16px; } .vorta-import__icon { display:none; } .vorta-import__template { width:100%; justify-content:center; } }
</style>
<div class="vorta-import">
  <div class="vorta-import__head">
    <div class="vorta-import__intro">
      <div class="vorta-import__icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"></path></svg>
      </div>
      <div>
        <h2 class="vorta-import__title">Import employees in bulk</h2>
        <p class="vorta-import__subtitle">Upload a CSV exported from Excel. User accounts must already exist.</p>
        <p class="vorta-import__chip">Columns: <code>email,name,position,phone</code></p>
      </div>
    </div>
    <a href="data:text/csv;charset=utf-8,email%2Cname%2Cposition%2Cphone%0Aemployee%40example.com%2CJane%20Doe%2CEmployee%2C08123456789"
      download="vorta-employees-template.csv" class="vorta-import__template">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"></path></svg>
      Download template
    </a>
  </div>
  <form method="POST" enctype="multipart/form-data" class="vorta-import__form">
    <input type="hidden" name="entity" value="employee_import">
    <?= csrf_field() ?>
    <div class="vorta-file">
      <span class="vorta-file__fake" id="employee-file-label">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"></path><path d="M14 2v6h6"></path></svg>
        Choose CSV file
      </span>
      <input id="employee-csv" class="vorta-file__input" type="file" name="employee_csv" accept=".csv,text/csv" required>
    </div>
    <button type="submit" class="vorta-import__submit">Import CSV</button>
  </form>
</div>
<script>
 document.getElementById('employee-csv')?.addEventListener('change', function () {
   const label = document.getElementById('employee-file-label');
   const name = this.files[0]?.name;
   label.lastChild.textContent = name ? ' ' + name : ' Choose CSV file';
 });
</script>

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

<div class="bg-white rounded-xl shadow-md overflow-hidden">
  <div class="p-6 md:p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">Employee List</h2>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left border-b border-gray-200">
            <th class="pb-3 font-medium text-gray-600">ID</th>
            <th class="pb-3 font-medium text-gray-600">User</th>
            <th class="pb-3 font-medium text-gray-600">Full Name</th>
            <th class="pb-3 font-medium text-gray-600">Position</th>
            <th class="pb-3 font-medium text-gray-600">Phone</th>
            <th class="pb-3 font-medium text-gray-600">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($employees)): ?>
            <tr>
              <td colspan="6" class="py-6 text-center text-sm text-gray-400">
                No employees found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($employees as $e): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= htmlspecialchars($e['employee_id'] ?? '-') ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= htmlspecialchars($e['user_name']) ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                  <?= htmlspecialchars($e['name']) ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= htmlspecialchars($e['position'] ?? '-') ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= htmlspecialchars($e['phone'] ?? '-') ?>
                </td>
                <td class="py-4 whitespace-nowrap space-x-1">
                  <button
                    type="button"
                    onclick='editEmployee(<?= (int)$e['employee_id'] ?>, <?= (int)$e['user_id'] ?>, <?= json_encode($e['name']) ?>, <?= json_encode($e['position'] ?? '') ?>, <?= json_encode($e['phone'] ?? '') ?>)'
                    class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600 transition">
                    Edit
                  </button>
                  <button
                    type="button"
                    onclick='confirmDeleteEmployee(<?= (int)$e['employee_id'] ?>, <?= json_encode($e['name']) ?>)'
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
    document.getElementById('emp-form-title').textContent = 'Add Employee';
    document.getElementById('emp-action').value = 'create';
    document.getElementById('emp-id').value = '';
    document.getElementById('current-user-id').value = '';
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

  function confirmDeleteEmployee(employeeId, employeeName) {
    Swal.fire({
      title: 'Delete this record?',
      text: `You are about to delete employee: ${employeeName}`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        submitDelete('delete_emp', employeeId);
      }
    });
  }
</script>