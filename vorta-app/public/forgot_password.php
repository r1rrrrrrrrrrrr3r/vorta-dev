<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/mailer.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $userId = (int)$stmt->fetchColumn();
        if ($userId > 0) {
            $pdo->prepare("DELETE FROM account_tokens WHERE user_id = ? AND purpose = 'password_reset' AND used_at IS NULL")->execute([$userId]);
            $rawToken = bin2hex(random_bytes(32));
            $insert = $pdo->prepare("INSERT INTO account_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, 'password_reset', ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            $insert->execute([$userId, hash('sha256', $rawToken)]);
            if ($BASE_URL !== '') {
                $url = $BASE_URL . '/reset_password.php?token=' . urlencode($rawToken);
                $sent = send_simple_mail($email, 'Reset your Vorta password', '<p>Reset your Vorta password within one hour:</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Reset password</a></p>');
                if (!$sent) {
                    $pdo->prepare("DELETE FROM account_tokens WHERE user_id = ? AND purpose = 'password_reset' AND token_hash = ?")
                        ->execute([$userId, hash('sha256', $rawToken)]);
                }
            } else {
                error_log('Password reset link not sent: APP_URL is not configured.');
                $pdo->prepare("DELETE FROM account_tokens WHERE user_id = ? AND purpose = 'password_reset' AND token_hash = ?")
                    ->execute([$userId, hash('sha256', $rawToken)]);
            }
        }
    }
    $message = 'If an active account exists for that email, reset instructions may be sent. If you do not receive them, check the address or contact your administrator.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset password - Vorta Prodtracker</title>
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

    <h1 class="account-title">Reset your password</h1>
    <p class="account-copy">Enter your account email and we&rsquo;ll send a secure reset link if the account exists.</p>

    <?php if ($message): ?>
      <div class="account-alert account-alert--ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="account-field">
        <label class="account-label" for="email">Email</label>
        <input class="account-input" id="email" type="email" name="email" required autofocus>
      </div>
      <button class="account-submit" type="submit">Send reset link</button>
    </form>

    <p class="account-foot"><a href="index.php">Back to sign in</a></p>
  </main>
</body>
</html>
