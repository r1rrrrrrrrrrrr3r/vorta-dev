<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'settings') {
    $result = settings_save($pdo, [
        'monthly_target_min' => $_POST['monthly_target_min'] ?? null,
        'monthly_target_max' => $_POST['monthly_target_max'] ?? null,
        'daily_min_reports' => $_POST['daily_min_reports'] ?? null,
    ]);

    flash_set($result['ok'] ? 'ok' : 'bad', $result['message']);
    header('Location: settings.php');
    exit;
}

$target = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);

$pageTitle = 'Settings';
$activeNav = 'settings';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Settings', 'Targets used across dashboards and reports') ?>

<form method="POST" class="card max-w-[760px]">
  <input type="hidden" name="entity" value="settings">
  <div class="card-header"><h2 class="card-title">Report targets</h2></div>
  <div class="card-body grid gap-4">
    <div class="grid gap-4 sm:grid-cols-3">
      <div class="field">
        <label class="label" for="st-min">Monthly minimum</label>
        <input type="number" name="monthly_target_min" id="st-min" min="1" class="input" value="<?= (int) $target['min'] ?>" required>
      </div>
      <div class="field">
        <label class="label" for="st-max">Monthly maximum</label>
        <input type="number" name="monthly_target_max" id="st-max" min="1" class="input" value="<?= (int) $target['max'] ?>" required>
      </div>
      <div class="field">
        <label class="label" for="st-daily">Daily minimum</label>
        <input type="number" name="daily_min_reports" id="st-daily" min="1" class="input" value="<?= (int) $dailyMin ?>" required>
      </div>
    </div>
    <p class="help m-0">Staff are on track when they reach the monthly minimum. Each staff member should submit at least the daily minimum every working day.</p>
  </div>
  <div class="card-footer justify-end">
    <button type="submit" class="btn btn-primary">Save targets</button>
  </div>
</form>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
