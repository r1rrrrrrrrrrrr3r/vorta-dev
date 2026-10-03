<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';

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

$layout = 'bare';
$pageTitle = 'Reset password';
include __DIR__ . '/../views/layout/start.php';
?>
<div class="w-full max-w-sm grid gap-6">
  <div class="flex items-center justify-center gap-3">
    <img src="images/vorta.png" alt="" class="size-10 object-contain">
    <span class="text-[18px] font-extrabold">Vorta</span>
  </div>

  <form method="post" class="card card-body grid gap-4" data-turbo="false">
    <?= csrf_field() ?>
    <div>
      <h1 class="text-[20px] font-bold m-0">Reset your password</h1>
      <p class="m-0 mt-1 text-muted text-[13px]">Enter your account email and we&rsquo;ll send a secure reset link if the account exists.</p>
    </div>

    <?php if ($message): ?>
      <?= alert_box('ok', $message) ?>
    <?php endif; ?>

    <div class="field">
      <label class="label" for="email">Email</label>
      <input class="input" id="email" type="email" name="email" required autofocus autocomplete="username" placeholder="name@company.com">
    </div>

    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
  </form>

  <p class="text-center text-[13px] m-0"><a href="index.php" class="link">Back to sign in</a></p>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
