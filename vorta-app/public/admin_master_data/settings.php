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
  <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<div id="settings-form-section" class="bg-gray-50 p-6 rounded-lg mb-8">
  <h2 class="text-lg font-semibold text-gray-800 mb-4" id="form-title">
    Monthly Target
  </h2>
  <form method="POST" id="settings-form">
    <input type="hidden" name="entity" value="settings">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum</label>
        <input type="number" name="monthly_target_min" min="1" value="<?= (int) $target['min'] ?>"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="Example: 50" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Maximum</label>
        <input type="number" name="monthly_target_max" min="1" value="<?= (int) $target['max'] ?>"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="Example: 88" required>
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        Save
      </button>
    </div>
  </form>
</div>

<div class="bg-white rounded-xl shadow-md overflow-hidden">
  <div class="p-6 md:p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">Current Setting</h2>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left border-b border-gray-200">
            <th class="pb-3 font-medium text-gray-600">Key</th>
            <th class="pb-3 font-medium text-gray-600">Value</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr class="hover:bg-gray-50 transition">
            <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
              Monthly Target Minimum
            </td>
            <td class="py-4 whitespace-nowrap text-sm text-gray-600">
              <?= (int) $target['min'] ?> items
            </td>
          </tr>
          <tr class="hover:bg-gray-50 transition">
            <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
              Monthly Target Maximum
            </td>
            <td class="py-4 whitespace-nowrap text-sm text-gray-600">
              <?= (int) $target['max'] ?> items
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>