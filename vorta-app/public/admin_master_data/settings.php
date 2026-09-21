<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/settings.php';
require_admin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'settings') {
    $result = settings_save($pdo, [
        'monthly_target_min' => $_POST['monthly_target_min'] ?? null,
        'monthly_target_max' => $_POST['monthly_target_max'] ?? null,
    ]);

    $_SESSION[$result['ok'] ? 'success' : 'error'] = $result['message'];
    header("Location: admin_master_data.php?tab=settings");
    exit;
}

$target = settings_get_monthly_target($pdo);
?>

<?php if ($success): ?>
  <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md overflow-hidden">
  <div class="p-6 md:p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-2">Monthly Target</h2>
    <p class="text-sm text-gray-600 mb-6">
      Set the minimum and maximum number of reports per month. These values will automatically appear on the Report Input and My Reports pages.
    </p>

    <form method="POST" class="max-w-md space-y-4">
      <input type="hidden" name="entity" value="settings">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Minimum</label>
          <input type="number" name="monthly_target_min" min="1" value="<?= (int) $target['min'] ?>"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Maximum</label>
          <input type="number" name="monthly_target_max" min="1" value="<?= (int) $target['max'] ?>"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
      </div>
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>