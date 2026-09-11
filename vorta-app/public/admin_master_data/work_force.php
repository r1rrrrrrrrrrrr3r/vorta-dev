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

// --- Search & Pagination settings
$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// --- Handle Create & Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['entity'] === 'work_force') {
  $workforce_name = trim($_POST['workforce_name']);
  $action = $_POST['action'] ?? '';
  $workforce_id = (int)($_POST['workforce_id'] ?? 0);

  if (empty($workforce_name)) {
    $_SESSION['error'] = "Nama Work Force wajib diisi.";
  } else {
    try {
      if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO work_force (workforce_name) VALUES (?)");
        $stmt->execute([$workforce_name]);
        $_SESSION['success'] = "Work Force berhasil ditambahkan.";
      } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE work_force SET workforce_name = ? WHERE workforce_id = ?");
        $stmt->execute([$workforce_name, $workforce_id]);
        if ($stmt->rowCount()) {
          $_SESSION['success'] = "Work Force berhasil diperbarui.";
        } else {
          $_SESSION['error'] = "Work Force tidak ditemukan.";
        }
      }

      // Redirect
      $params = ['tab' => 'work_force', 'page' => $page];
      if ($search) $params['search'] = $search;
      $redirect = 'admin_master_data.php?' . http_build_query($params);
      echo "<script> window.location.href = '$redirect'; </script>";
      exit;
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        $_SESSION['error'] = $action === 'create'
          ? "Work Force dengan nama ini sudah ada."
          : "Nama Work Force sudah digunakan.";
      } else {
        $_SESSION['error'] = "Gagal menyimpan data.";
      }
    }
  }
}

// --- Handle Delete
if (isset($_GET['delete_work_force'])) {
  $workforce_id = (int)$_GET['delete_work_force'];
  try {
    $stmt = $pdo->prepare("DELETE FROM work_force WHERE workforce_id = ?");
    $stmt->execute([$workforce_id]);

    if ($stmt->rowCount()) {
      $_SESSION['success'] = "Work Force berhasil dihapus.";
    } else {
      $_SESSION['error'] = "Work Force tidak ditemukan.";
    }
  } catch (PDOException $e) {
    $_SESSION['error'] = "Gagal menghapus data.";
  }

  $params = ['tab' => 'work_force', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

// --- Build WHERE clause
$where = [];
$params = [];
if ($search) {
  $where[] = "workforce_name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- Total count
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM work_force $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalworkforce = (int)($totalRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalworkforce / $perPage);

// --- Fetch data
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
<div id="workforce-form-section" class="bg-gray-50 p-6 rounded-lg mb-8">
  <h2 class="text-lg font-semibold text-gray-800 mb-4" id="form-title">
    Added New Work Force
  </h2>
  <form method="POST" id="workforce-form">
    <input type="hidden" name="entity" value="work_force">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="workforce_id" value="" id="workforce-id-input">

    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Work Force Name</label>
      <input type="text" name="workforce_name" id="workforce_name"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        placeholder="Example: Inare, Antara" required>
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

<!-- Search Form -->
<div class="mb-6">
  <form method="GET" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="tab" value="work_force">
    <input
      type="text"
      name="search"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search Work Force..."
      class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
      Search
    </button>
    <?php if ($search): ?>
      <a href="?tab=work_force" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
        Clear
      </a>
    <?php endif; ?>
  </form>
</div>

<!-- Tabel Data -->
<div class="overflow-x-auto">
  <table class="w-full min-w-[500px]">
    <thead class="bg-gray-50 border-b">
      <tr class="text-left text-sm text-gray-600">
        <th class="px-4 py-3">ID</th>
        <th class="px-4 py-3">Work Force Name</th>
        <th class="px-4 py-3">Action</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php if (empty($workforces)): ?>
        <tr>
          <td colspan="3" class="px-4 py-6 text-center text-gray-500">Tidak ada data Work Force ditemukan.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($workforces as $wf): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3"><?= (int)$wf['workforce_id'] ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($wf['workforce_name']) ?></td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button
                  onclick='editWorkforce(<?= (int)$wf['workforce_id'] ?>, <?= json_encode($wf['workforce_name']) ?>)'
                  class="px-3 py-1 bg-yellow-500 text-white rounded text-xs hover:brightness-95">
                  Edit
                </button>
                <button
                  onclick='confirmDelete(<?= (int)$wf['workforce_id'] ?>, <?= json_encode($wf['workforce_name']) ?>)'
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
  // Edit Workforce
  function editWorkforce(id, name) {
    document.getElementById('form-title').textContent = 'Edit Work Force';
    document.getElementById('workforce_name').value = name;
    document.getElementById('action-input').value = 'update';
    document.getElementById('workforce-id-input').value = id;
    document.getElementById('cancel-edit').classList.remove('hidden');

    document.getElementById('workforce-form-section').scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    });
  }

  // Cancel edit
  document.getElementById('cancel-edit')?.addEventListener('click', function() {
    document.getElementById('workforce-form').reset();
    document.getElementById('form-title').textContent = 'Tambah Work Force Baru';
    document.getElementById('action-input').value = 'create';
    document.getElementById('workforce-id-input').value = '';
    this.classList.add('hidden');
  });

  // Konfirmasi hapus
  function confirmDelete(id, name) {
    Swal.fire({
      title: 'Yakin hapus?',
      text: `Anda akan menghapus Work Force: "${name}"`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        const url = new URL(window.location.href);
        url.searchParams.set('delete_work_force', id);
        url.searchParams.set('tab', 'work_force');
        window.location.href = url.toString();
      }
    });
  }
</script>

<?php include __DIR__ . '/../footer.php'; ?>