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
  <style>
    body{padding-left:0!important}.account-card{width:100%;max-width:440px;padding:32px;background:var(--surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:16px;box-shadow:var(--shadow-card,0 1px 3px rgba(0,0,0,.06),0 6px 18px -8px rgba(0,0,0,.12))}.account-title{margin:0;color:var(--text,#1f2937);font-size:26px;font-weight:700}.account-copy{margin-top:8px;color:var(--text-muted,#6b7280);font-size:14px;line-height:1.5}.account-field{margin-top:20px}.account-label{display:block;margin-bottom:6px;color:var(--text,#374151);font-size:14px;font-weight:600}.account-input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid var(--border,#d1d5db);border-radius:10px;background:var(--surface,#fff);color:var(--text,#1f2937);font-size:14px}.account-submit{width:100%;margin-top:22px;padding:11px 16px;border:0;border-radius:10px;background:#4f46e5;color:#fff;font-size:14px;font-weight:700;cursor:pointer}.account-error{margin-top:18px;padding:12px 14px;border:1px solid #fecaca;border-radius:10px;background:#fef2f2;color:#b91c1c;font-size:14px}
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <main class="account-card">
    <?php if ($done): ?>
      <h1 class="account-title">Password updated</h1><p class="account-copy">Your password has been changed. <a href="index.php">Sign in</a>.</p>
    <?php elseif (!$reset): ?>
      <h1 class="account-title">Link unavailable</h1><p class="account-copy">This reset link is invalid or expired. <a href="forgot_password.php">Request a new one</a>.</p>
    <?php else: ?>
      <h1 class="account-title">Choose a new password</h1>
      <?php if ($error): ?><div class="account-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="account-field"><label class="account-label" for="password">New password</label><input class="account-input" id="password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required autofocus></div>
        <div class="account-field"><label class="account-label" for="confirm_password">Confirm password</label><input class="account-input" id="confirm_password" type="password" name="confirm_password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required></div>
        <button class="account-submit" type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
