<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/tenant.php';

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
            header('Location: index.php?registered=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Registration failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create company - Vorta Prodtracker</title>
  <link rel="stylesheet" href="css/output.css">
  <?php include __DIR__ . '/ui_head.php'; ?>
</head>
<body class="account-shell">
  <main class="account-card">
    <div class="account-brand">
      <img src="../images/vorta.png" alt="Vorta">
      <div>
        <div class="account-brand-name">Vorta Prodtracker</div>
        <div class="account-brand-sub">Productivity System</div>
      </div>
    </div>

    <h1 class="account-title">Create your company</h1>
    <p class="account-copy">Set up your workspace and administrator account.</p>

    <?php if ($error): ?>
      <div class="account-alert account-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="account-field">
        <label class="account-label" for="company_name">Company name</label>
        <input class="account-input" id="company_name" name="company_name" required autofocus
          value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" placeholder="Acme Studio">
      </div>
      <div class="account-field">
        <label class="account-label" for="name">Your name</label>
        <input class="account-input" id="name" name="name" required
          value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Jane Doe">
      </div>
      <div class="account-field">
        <label class="account-label" for="email">Work email</label>
        <input class="account-input" id="email" type="email" name="email" required
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@company.com">
      </div>
      <div class="account-field">
        <label class="account-label" for="password">Password</label>
        <input class="account-input" id="password" type="password" name="password" minlength="6" required
          placeholder="At least 6 characters">
      </div>
      <button class="account-submit" type="submit">Create company</button>
    </form>

    <p class="account-foot">Already have an account? <a href="index.php">Sign in</a></p>
  </main>
</body>
</html>
