<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/login_guard.php';
require_once __DIR__ . '/../lib/tenant.php';

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $email = trim((string)($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';
  if (login_is_locked($pdo, $email)) {
    $error = "Too many failed attempts. Please try again later.";
  } else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
  }
  if (!empty($user) && password_verify($password, $user['password_hash'])) {
    login_record_attempt($pdo, $email, true);
    session_regenerate_id(true);
    $_SESSION['user'] = [
      'user_id' => $user['user_id'],
      'name' => $user['name'],
      'email' => $user['email'],
      'role' => $user['role'],
      'company_id' => (int)($user['company_id'] ?? 0),
      'theme' => $user['theme'] ?? 'system',
      'nav_layout' => $user['nav_layout'] ?? 'sidebar'
    ];
    $_SESSION['company'] = company_load($pdo, (int)($user['company_id'] ?? 0)) ?: [];
    tenant_apply_timezone();
    header("Location: dashboard.php");
    exit;
  } elseif (!isset($error)) {
    login_record_attempt($pdo, $email, false);
    $error = "Incorrect email or password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in - Vorta Prodtracker</title>
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

    <h1 class="account-title">Sign in</h1>
    <p class="account-copy">Use your work account to continue.</p>

    <?php if (isset($error)): ?>
      <div class="account-alert account-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($registered): ?>
      <div class="account-alert account-alert--ok">
        Your company workspace is ready. Sign in with the administrator account you just created.
      </div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="account-field">
        <label class="account-label" for="email">Email</label>
        <input class="account-input" id="email" type="email" name="email" required autofocus
               placeholder="email@example.com">
      </div>
      <div class="account-field">
        <label class="account-label" for="password">Password</label>
        <input class="account-input" id="password" type="password" name="password" required
               placeholder="••••••••">
      </div>
      <button class="account-submit" type="submit">Sign in</button>
    </form>

    <p class="account-foot"><a href="forgot_password.php">Forgot your password?</a></p>
    <p class="account-foot">New here? <a href="register.php">Create a company account</a></p>
  </main>
</body>
</html>