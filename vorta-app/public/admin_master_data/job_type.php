<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/tenant.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['entity'] === 'job_type') {
    csrf_verify();
    $name = trim($_POST['name']);
    $action = $_POST['action'] ?? '';
    $job_type_id = (int)($_POST['job_type_id'] ?? 0);

    if (empty($name)) {
        $_SESSION['error'] = "Job type name is required.";
    } else {
        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO job_type (company_id, name) VALUES (?, ?)");
                $stmt->execute([$company_id, $name]);
                $_SESSION['success'] = "Job type added successfully.";
            } elseif ($action === 'update') {
                $stmt = $pdo->prepare("UPDATE job_type SET name = ? WHERE job_type_id = ? AND company_id = ?");
                $stmt->execute([$name, $job_type_id, $company_id]);
                if ($stmt->rowCount()) {
                    $_SESSION['success'] = "Job type updated successfully.";
                } else {
                    $_SESSION['error'] = "Job type not found.";
                }
            }

            $params = ['tab' => 'job_type', 'page' => $page];
            if ($search) $params['search'] = $search;
            $redirect = 'admin_master_data.php?' . http_build_query($params);
            echo "<script> window.location.href = '$redirect'; </script>";
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error'] = $action === 'create' 
                    ? "A job type with this name already exists." 
                    : "That job type name is already in use.";
            } else {
                $_SESSION['error'] = "Failed to save data.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job_type'])) {
    csrf_verify();
    $job_type_id = (int)$_POST['delete_job_type'];
    try {
        $stmt = $pdo->prepare("DELETE FROM job_type WHERE job_type_id = ? AND company_id = ?");
        $stmt->execute([$job_type_id, $company_id]);

        if ($stmt->rowCount()) {
            $_SESSION['success'] = "Job type deleted successfully.";
        } else {
            $_SESSION['error'] = "Job type not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to delete data.";
    }

    $params = ['tab' => 'job_type', 'page' => $page];
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
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM job_type $whereSql");
foreach ($params as $i => $val) {
    $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalJobTypes = (int)($totalRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalJobTypes / $perPage);
$sql = "SELECT * FROM job_type $whereSql ORDER BY job_type_id ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
    $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
function page_url($p) {
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

<div id="job-type-form-section" class="bg-gray-50 p-6 rounded-lg mb-8">
  <h2 class="text-lg font-semibold text-gray-800 mb-4" id="form-title">
    Added New Job Type
  </h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="job_type">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="job_type_id" value="" id="job-type-id-input">

    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Job Type Name</label>
      <input type="text" name="name" id="name" 
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
             placeholder="Example: Programming, Design, Admin" required>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
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
    <input type="hidden" name="tab" value="job_type">
    <input
      type="text"
      name="search"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search Job Type..."
      class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
    />
    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
      Search  
    </button>
    <?php if ($search): ?>
      <a href="?tab=job_type" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
        Clear
      </a>
    <?php endif; ?>
  </form>
</div>
<div class="bg-white rounded-xl shadow-md overflow-hidden">
  <div class="p-6 md:p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">Job Types</h2>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left border-b border-gray-200">
            <th class="pb-3 font-medium text-gray-600">ID</th>
            <th class="pb-3 font-medium text-gray-600">Job Type Name</th>
            <th class="pb-3 font-medium text-gray-600">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($job_types)): ?>
            <tr>
              <td colspan="3" class="py-6 text-center text-sm text-gray-400">
                No job types found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($job_types as $jt): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= (int)$jt['job_type_id'] ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                  <?= htmlspecialchars($jt['name']) ?>
                </td>
                <td class="py-4 whitespace-nowrap space-x-1">
                  <button
                    type="button"
                    onclick='editJobType(<?= (int)$jt['job_type_id'] ?>, <?= json_encode($jt['name']) ?>)'
                    class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600 transition">
                    Edit
                  </button>
                  <button
                    type="button"
                    onclick='confirmDelete(<?= (int)$jt['job_type_id'] ?>, <?= json_encode($jt['name']) ?>)'
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
             class="px-3 py-1 rounded border <?= ($p == $page) ? 'bg-indigo-600 text-white' : 'hover:bg-gray-100' ?>">
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

function editJobType(id, name) {
    document.getElementById('form-title').textContent = 'Edit Job Type';
    document.getElementById('name').value = name;
    document.getElementById('action-input').value = 'update';
    document.getElementById('job-type-id-input').value = id;
    document.getElementById('cancel-edit').classList.remove('hidden');
    document.getElementById('job-type-form-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.getElementById('cancel-edit')?.addEventListener('click', function () {
    document.querySelector('form').reset();
    document.getElementById('form-title').textContent = 'Add New Job Type';
    document.getElementById('action-input').value = 'create';
    document.getElementById('job-type-id-input').value = '';
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
        text: `You are about to delete job type: "${name}"`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            submitDelete('delete_job_type', id);
        }
    });
}
</script>