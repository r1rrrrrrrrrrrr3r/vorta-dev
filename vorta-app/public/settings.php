<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/tenant.php';
require_admin();
$company_id = current_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'settings') {
    csrf_verify();
    $timezone = trim((string)($_POST['timezone'] ?? ''));
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        $result = ['ok' => false, 'message' => 'Choose a valid timezone.'];
    } else {
        $result = settings_save($pdo, [
            'monthly_target_min' => $_POST['monthly_target_min'] ?? null,
            'monthly_target_max' => $_POST['monthly_target_max'] ?? null,
            'daily_min_reports' => $_POST['daily_min_reports'] ?? null,
        ]);
        if ($result['ok']) {
            $timezoneStmt = $pdo->prepare('UPDATE companies SET timezone = ? WHERE company_id = ?');
            $timezoneStmt->execute([$timezone, $company_id]);
            $_SESSION['company']['timezone'] = $timezone;
            tenant_apply_timezone();
        }
    }
    if ($result['ok']) {
        audit_log($pdo, 'settings.updated', 'app_settings');
    }

    flash_set($result['ok'] ? 'ok' : 'bad', $result['message']);
    header('Location: settings.php', true, 303);
    exit;
}

settings_mark_reviewed($pdo);

$target = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
$companyStmt = $pdo->prepare('SELECT timezone FROM companies WHERE company_id = ?');
$companyStmt->execute([$company_id]);
$companyTimezone = (string)($companyStmt->fetchColumn() ?: 'Asia/Jakarta');
$timezoneOptions = [
    'UTC' => 'UTC',
    'Asia/Jakarta' => 'Asia/Jakarta (WIB)',
    'Asia/Singapore' => 'Asia/Singapore (SGT)',
    'Asia/Tokyo' => 'Asia/Tokyo (JST)',
    'Asia/Manila' => 'Asia/Manila (PHT)',
    'Australia/Sydney' => 'Australia/Sydney',
    'Europe/London' => 'Europe/London',
    'Europe/Paris' => 'Europe/Paris',
    'America/New_York' => 'America/New_York',
    'America/Los_Angeles' => 'America/Los_Angeles',
];
if (!isset($timezoneOptions[$companyTimezone]) && in_array($companyTimezone, timezone_identifiers_list(), true)) {
    $timezoneOptions = [$companyTimezone => $companyTimezone] + $timezoneOptions;
}

$pageTitle = 'Settings';
$activeNav = 'settings';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Settings', 'Reporting targets and regional preferences for this company') ?>

<form method="POST" class="card max-w-[760px]">
  <?= csrf_field() ?>
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
  <div class="card-header border-t border-line"><h2 class="card-title">Regional</h2></div>
  <div class="card-body grid gap-4">
    <div class="field sm:max-w-[420px]">
      <label class="label" for="st-timezone">Company timezone</label>
      <select name="timezone" id="st-timezone" class="select" required>
        <?php foreach ($timezoneOptions as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $companyTimezone === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help">Dates, reminders, and report periods are calculated in this timezone.</p>
    </div>
  </div>
  <div class="card-footer justify-end">
    <button type="submit" class="btn btn-primary">Save settings</button>
  </div>
</form>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
