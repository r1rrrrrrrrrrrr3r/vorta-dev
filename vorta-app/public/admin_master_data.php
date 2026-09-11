<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_admin();

$active_tab = $_GET['tab'] ?? 'work_force';
$success = '';
$error = '';

include __DIR__ . '/header.php';

// Mapping nama tab untuk dropdown
$tab_names = [
  'work_force' => 'Work Force',
  'users' => 'Users',
  'employees' => 'Employees',
  'job_type' => 'Job Type'
];
?>

<div class="max-w-6xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-bold text-gray-800 mb-6">Master Data Management</h1>

  <!-- Desktop Tabs -->
  <div class="hidden md:block mb-6">
    <div class="flex border-b border-gray-200">
      <a href="?tab=work_force" class="px-4 py-2 text-sm font-medium border-b-2 <?= $active_tab === 'work_force' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
        Work Force
      </a>
      <a href="?tab=users" class="px-4 py-2 text-sm font-medium border-b-2 <?= $active_tab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
        Users
      </a>
      <a href="?tab=employees" class="px-4 py-2 text-sm font-medium border-b-2 <?= $active_tab === 'employees' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
        Employees
      </a>
      <a href="?tab=job_type" class="px-4 py-2 text-sm font-medium border-b-2 <?= $active_tab === 'job_type' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
        Job Type
      </a>
    </div>
  </div>

  <!-- Mobile Dropdown -->
  <div class="md:hidden mb-6 relative">
    <select id="tab-selector" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
      <?php foreach ($tab_names as $key => $name): ?>
        <option value="<?= $key ?>" <?= $active_tab === $key ? 'selected' : '' ?>><?= $name ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($success): ?>
    <div class="mb-6 p-3 bg-green-100 text-green-800 text-sm rounded">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="mb-6 p-3 bg-red-100 text-red-800 text-sm rounded">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Tab Content -->
  <div class="bg-white border border-gray-200 rounded-lg p-6">
    <?php if ($active_tab === 'work_force'): ?>
      <?php include 'admin_master_data/work_force.php'; ?>
    <?php elseif ($active_tab === 'users'): ?>
      <?php include 'admin_master_data/users.php'; ?>
    <?php elseif ($active_tab === 'employees'): ?>
      <?php include 'admin_master_data/employees.php'; ?>
    <?php elseif ($active_tab === 'job_type'): ?>
      <?php include 'admin_master_data/job_type.php'; ?>
    <?php endif; ?>
  </div>
</div>

<script>
// Handle mobile tab selection
document.getElementById('tab-selector').addEventListener('change', function() {
  window.location.href = '?tab=' + this.value;
});
</script>

<?php include __DIR__ . '/footer.php'; ?>