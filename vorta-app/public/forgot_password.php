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
            $url = rtrim($BASE_URL, '/') . '/reset_password.php?token=' . urlencode($rawToken);
            send_simple_mail($email, 'Reset your Vorta password', '<p>Reset your Vorta password within one hour:</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Reset password</a></p>');
        }
    }
    $message = 'If an active account exists for that email, a password reset link has been sent.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset password - Vorta Prodtracker</title>
  <link rel="stylesheet" href="css/output.css"><?php include __DIR__ . '/ui_head.php'; ?>
  <style>
    body{padding-left:0!important}.account-card{width:100%;max-width:440px;padding:32px;background:var(--surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:16px;box-shadow:var(--shadow-card,0 1px 3px rgba(0,0,0,.06),0 6px 18px -8px rgba(0,0,0,.12))}.account-title{margin:0;color:var(--text,#1f2937);font-size:26px;font-weight:700}.account-copy{margin-top:8px;color:var(--text-muted,#6b7280);font-size:14px;line-height:1.5}.account-field{margin-top:20px}.account-label{display:block;margin-bottom:6px;color:var(--text,#374151);font-size:14px;font-weight:600}.account-input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid var(--border,#d1d5db);border-radius:10px;background:var(--surface,#fff);color:var(--text,#1f2937);font-size:14px}.account-submit{width:100%;margin-top:22px;padding:11px 16px;border:0;border-radius:10px;background:#4f46e5;color:#fff;font-size:14px;font-weight:700;cursor:pointer}.account-submit:hover{background:#4338ca}.account-message{margin-top:18px;padding:12px 14px;border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;color:#166534;font-size:14px}
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <main class="account-card">
    <h1 class="account-title">Reset your password</h1>
    <p class="account-copy">Enter your account email and we’ll send a secure reset link if the account exists.</p>
    <?php if ($message): ?><div class="account-message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="account-field"><label class="account-label" for="email">Email</label><input class="account-input" id="email" type="email" name="email" required autofocus></div>
      <button class="account-submit" type="submit">Send reset link</button>
    </form>
    <p class="account-copy"><a href="index.php">Back to sign in</a></p>
  </main>
</body>
</html>
