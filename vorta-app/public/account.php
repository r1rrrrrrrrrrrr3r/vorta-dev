<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/ui.php';
require_login();

$user_id = (int) ($_SESSION['user']['user_id'] ?? 0);
$sections = ['profile' => 'Profile', 'appearance' => 'Appearance', 'password' => 'Password'];
$section = $_GET['section'] ?? 'profile';
if (!isset($sections[$section])) {
    $section = 'profile';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';
    if ($form === 'profile') {
        $result = account_update_profile(
            $pdo,
            $user_id,
            $_POST['name'] ?? '',
            $_POST['email'] ?? '',
            $_POST['phone'] ?? ''
        );
        if ($result['ok']) {
            $_SESSION['user']['name'] = trim($_POST['name']);
            $_SESSION['user']['email'] = trim($_POST['email']);
            flash_set('ok', 'Profile updated');
        } else {
            $_SESSION['account_error'] = ['profile', $result['message']];
        }
        header('Location: account.php?section=profile');
        exit;
    }
    if ($form === 'password') {
        $result = account_change_password(
            $pdo,
            $user_id,
            $_POST['current_password'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['confirm_password'] ?? ''
        );
        if ($result['ok']) {
            flash_set('ok', 'Password changed');
        } else {
            $_SESSION['account_error'] = ['password', $result['message']];
        }
        header('Location: account.php?section=password');
        exit;
    }
}

$accountError = $_SESSION['account_error'] ?? null;
unset($_SESSION['account_error']);
$errorFor = fn(string $s) => ($accountError && $accountError[0] === $s) ? $accountError[1] : '';

$stmt = $pdo->prepare("
    SELECT
        u.email,
        u.role,
        COALESCE(e.name, u.name) AS name,
        e.phone,
        e.position,
        e.employee_id
    FROM users u
    LEFT JOIN employees e ON u.user_id = e.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$isStaff = $user['role'] !== 'admin';
$attendance = null;
$reportCount = 0;
$target = settings_get_monthly_target($pdo);
if ($isStaff) {
    $stmt = $pdo->prepare("SELECT status FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, date('Y-m-d')]);
    $attendance = $stmt->fetch() ?: null;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM production_reports WHERE user_id = ? AND report_date BETWEEN ? AND ?");
    $stmt->execute([$user_id, date('Y-m-01'), date('Y-m-t')]);
    $reportCount = (int) $stmt->fetchColumn();
}

$pageTitle = 'Account';
$activeNav = 'account';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Account', 'Your profile and preferences') ?>

<div class="grid gap-6 md:grid-cols-[180px_1fr] items-start">
  <nav class="subnav" aria-label="Account sections">
    <?php foreach ($sections as $key => $label): ?>
      <a href="account.php?section=<?= $key ?>" class="<?= $section === $key ? 'is-active' : '' ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="grid gap-4 min-w-0 max-w-[640px]">
    <?php if ($section === 'profile'): ?>
      <section class="card card-body flex flex-wrap items-center gap-4">
        <span class="avatar avatar-lg"><?= e(initials($user['name'])) ?></span>
        <div class="min-w-0 flex-1">
          <div class="card-title"><?= e($user['name']) ?></div>
          <div class="text-muted break-all"><?= e($user['email']) ?></div>
          <div class="flex flex-wrap gap-2 mt-2">
            <?= $user['position'] ? '<span class="pill pill-role">' . e($user['position']) . '</span>' : role_pill($user['role']) ?>
            <?php if ($isStaff): ?>
              <?= $attendance && $attendance['status'] ? status_pill($attendance['status']) : status_pill('Not checked in') ?>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($isStaff): ?>
          <div class="w-full grid gap-2">
            <div class="flex justify-between text-[13px]"><span class="text-muted">Reports this month</span><span class="font-semibold tabular-nums"><?= $reportCount ?> / <?= (int) $target['min'] ?></span></div>
            <?= progress_bar($target['min'] > 0 ? min(100, $reportCount / $target['min'] * 100) : 0) ?>
          </div>
        <?php endif; ?>
      </section>

      <form method="POST" action="account.php?section=profile" class="card">
        <input type="hidden" name="form" value="profile">
        <div class="card-header"><h2 class="card-title">Profile</h2></div>
        <div class="card-body grid gap-4">
          <?php if ($errorFor('profile')): ?><?= alert_box('bad', $errorFor('profile')) ?><?php endif; ?>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
              <label class="label" for="ac-name">Full name</label>
              <input type="text" name="name" id="ac-name" class="input" required value="<?= e($user['name']) ?>" autocomplete="name">
            </div>
            <div class="field">
              <label class="label" for="ac-phone">Phone <span class="optional">(optional)</span></label>
              <input type="tel" name="phone" id="ac-phone" class="input" value="<?= e($user['phone'] ?? '') ?>" autocomplete="tel">
            </div>
          </div>
          <div class="field">
            <label class="label" for="ac-email">Email</label>
            <input type="email" name="email" id="ac-email" class="input" required value="<?= e($user['email']) ?>" autocomplete="email">
          </div>
          <?php if ($user['position']): ?>
            <div class="field">
              <label class="label" for="ac-position">Position</label>
              <input type="text" id="ac-position" class="input" value="<?= e($user['position']) ?>" readonly>
              <p class="help">Ask an admin to change this.</p>
            </div>
          <?php endif; ?>
        </div>
        <div class="card-footer justify-end">
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>

    <?php elseif ($section === 'appearance'): ?>
      <section class="card">
        <div class="card-header"><h2 class="card-title">Theme</h2></div>
        <div class="card-body grid gap-3 justify-items-start">
          <div class="seg" role="group" aria-label="Theme">
            <button type="button" data-theme-set="light"><?= icon('sun') ?>Light</button>
            <button type="button" data-theme-set="dark"><?= icon('moon') ?>Dark</button>
            <button type="button" data-theme-set="system"><?= icon('computer-desktop') ?>System</button>
          </div>
          <p class="help m-0">Saved automatically.</p>
        </div>
      </section>

    <?php else: ?>
      <form method="POST" action="account.php?section=password" class="card" id="password-form">
        <input type="hidden" name="form" value="password">
        <div class="card-header"><h2 class="card-title">Change password</h2></div>
        <div class="card-body grid gap-4">
          <?php if ($errorFor('password')): ?><?= alert_box('bad', $errorFor('password')) ?><?php endif; ?>
          <?php foreach ([
              ['current_password', 'Current password', 'current-password', ''],
              ['new_password', 'New password', 'new-password', 'At least ' . ACCOUNT_MIN_PASSWORD_LENGTH . ' characters, different from the current one.'],
              ['confirm_password', 'Confirm new password', 'new-password', ''],
          ] as [$fname, $flabel, $fauto, $fhelp]): ?>
            <div class="field">
              <label class="label" for="<?= $fname ?>"><?= $flabel ?></label>
              <div class="input-group">
                <input type="password" name="<?= $fname ?>" id="<?= $fname ?>" class="input" required autocomplete="<?= $fauto ?>">
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-toggle-password="<?= $fname ?>" aria-label="Show password" aria-pressed="false">
                  <span data-eye><?= icon('eye', 'size-4') ?></span><span data-eye-off hidden><?= icon('eye-slash', 'size-4') ?></span>
                </button>
              </div>
              <?php if ($fhelp): ?><p class="help"><?= e($fhelp) ?></p><?php endif; ?>
              <?php if ($fname === 'confirm_password'): ?><p class="field-error" id="pw-mismatch" hidden>Passwords don't match.</p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="card-footer justify-end">
          <button type="submit" class="btn btn-primary">Change password</button>
        </div>
      </form>
      <script>
        document.querySelectorAll('[data-toggle-password]').forEach(btn => {
          btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', String(show));
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('[data-eye]').hidden = show;
            btn.querySelector('[data-eye-off]').hidden = !show;
          });
        });
        (function () {
          const form = document.getElementById('password-form');
          const np = document.getElementById('new_password');
          const cp = document.getElementById('confirm_password');
          const err = document.getElementById('pw-mismatch');
          function check() {
            const bad = cp.value !== '' && np.value !== cp.value;
            err.hidden = !bad;
            cp.setAttribute('aria-invalid', String(bad));
            return !bad;
          }
          cp.addEventListener('input', check);
          np.addEventListener('input', check);
          form.addEventListener('submit', (e) => { if (!check()) { e.preventDefault(); cp.focus(); } });
        })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
