<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/ui.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $companyName = trim($_POST['company_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($companyName === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Company, name, and a valid email are required.';
    } elseif (mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
        $error = 'Password must be at least ' . ACCOUNT_MIN_PASSWORD_LENGTH . ' characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                throw new RuntimeException('An account already exists for this email.');
            }

            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $companyName), '-')) . '-' . bin2hex(random_bytes(3));
            $stmt = $pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
            $stmt->execute([$companyName, $slug]);
            $companyId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('
                INSERT INTO users (company_id, name, email, password_hash, role)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$companyId, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
            $stmt = $pdo->prepare("INSERT INTO work_force (company_id, workforce_name) VALUES (?, 'General')");
            $stmt->execute([$companyId]);
            $stmt = $pdo->prepare("INSERT INTO job_type (company_id, name) VALUES (?, 'General')");
            $stmt->execute([$companyId]);
            $stmt = $pdo->prepare("INSERT INTO app_settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)");
            $stmt->execute([$companyId, 'monthly_target_min', '50']);
            $stmt->execute([$companyId, 'monthly_target_max', '88']);
            $stmt->execute([$companyId, 'daily_min_reports', '2']);
            $pdo->commit();
            header('Location: index.php?registered=1', true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Registration failed.';
        }
    }
}
if ($error !== '') {
    http_response_code(422);
}

$layout = 'bare';
$pageTitle = 'Create company';
include __DIR__ . '/../views/layout/start.php';
?>
<div class="w-full max-w-sm grid gap-6">
  <div class="flex items-center justify-center gap-3">
    <img src="images/vorta.png" alt="" class="size-10 object-contain">
    <span class="text-[18px] font-extrabold">Vorta</span>
  </div>

  <form method="post" class="card card-body grid gap-4">
    <?= csrf_field() ?>
    <div>
      <h1 class="text-[20px] font-bold m-0">Create your company</h1>
      <p class="m-0 mt-1 text-muted text-[13px]">Set up your workspace and administrator account.</p>
    </div>

    <?php if ($error): ?>
      <?= alert_box('bad', $error) ?>
    <?php endif; ?>

    <div class="field">
      <label class="label" for="company_name">Company name</label>
      <input class="input" id="company_name" name="company_name" required autofocus autocomplete="organization"
        value="<?= e($_POST['company_name'] ?? '') ?>" placeholder="Acme Studio">
    </div>
    <div class="field">
      <label class="label" for="name">Your name</label>
      <input class="input" id="name" name="name" required autocomplete="name"
        value="<?= e($_POST['name'] ?? '') ?>" placeholder="Jane Doe">
    </div>
    <div class="field">
      <label class="label" for="email">Work email</label>
      <input class="input" id="email" type="email" name="email" required autocomplete="email"
        value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@company.com">
    </div>
    <div class="field">
      <label class="label" for="password">Password</label>
      <div class="input-group">
        <input class="input" id="password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required autocomplete="new-password"
          placeholder="At least <?= ACCOUNT_MIN_PASSWORD_LENGTH ?> characters">
        <button type="button" class="btn btn-ghost btn-sm" data-toggle-password="password" aria-controls="password" aria-pressed="false">Show</button>
      </div>
    </div>

    <button class="btn btn-primary btn-block" type="submit">Create company</button>
  </form>

  <p class="text-center text-[13px] text-muted m-0">Already have an account? <a href="index.php" class="link">Sign in</a></p>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
