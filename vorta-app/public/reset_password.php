<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = $pdo->prepare("SELECT token_id, user_id FROM account_tokens WHERE token_hash = ? AND purpose = 'password_reset' AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
$stmt->execute([hash('sha256', $token)]);
$reset = $stmt->fetch();
$error = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    csrf_verify();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
        $error = 'Password must be at least ' . ACCOUNT_MIN_PASSWORD_LENGTH . ' characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $pdo->prepare('UPDATE account_tokens SET used_at = NOW() WHERE token_id = ?')->execute([(int)$reset['token_id']]);
            $pdo->commit();
            $done = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Unable to reset your password. Please request a new link.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set new password - Vorta Prodtracker</title>
  <link rel="stylesheet" href="css/output.css"><?php include __DIR__ . '/ui_head.php'; ?>
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

    <?php if ($done): ?>
      <h1 class="account-title">Password updated</h1>
      <p class="account-copy">Your password has been changed. <a href="index.php">Sign in</a>.</p>
    <?php elseif (!$reset): ?>
      <h1 class="account-title">Link unavailable</h1>
      <p class="account-copy">This reset link is invalid or expired. <a href="forgot_password.php">Request a new one</a>.</p>
    <?php else: ?>
      <h1 class="account-title">Choose a new password</h1>
      <p class="account-copy">Pick a password you don&rsquo;t use anywhere else.</p>
      <?php if ($error): ?>
        <div class="account-alert account-alert--error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="account-field">
          <label class="account-label" for="password">New password</label>
          <input class="account-input" id="password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required autofocus>
        </div>
        <div class="account-field">
          <label class="account-label" for="confirm_password">Confirm password</label>
          <input class="account-input" id="confirm_password" type="password" name="confirm_password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required>
        </div>
        <button class="account-submit" type="submit">Update password</button>
      </form>
      <p class="account-foot"><a href="index.php">Back to sign in</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
