<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';

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
if ($error !== '') {
    http_response_code(422);
}

$layout = 'bare';
$pageTitle = 'Set new password';
include __DIR__ . '/../views/layout/start.php';
?>
<div class="w-full max-w-sm grid gap-6">
  <div class="flex items-center justify-center gap-3">
    <img src="images/vorta.png" alt="" class="size-10 object-contain">
    <span class="text-[18px] font-extrabold">Vorta</span>
  </div>

  <?php if ($done): ?>
    <div class="card card-body grid gap-3">
      <?= alert_box('ok', 'Your password has been changed.') ?>
      <h1 class="text-[20px] font-bold m-0">Password updated</h1>
      <a href="index.php" class="btn btn-primary btn-block">Sign in</a>
    </div>
  <?php elseif (!$reset): ?>
    <div class="card card-body grid gap-3">
      <h1 class="text-[20px] font-bold m-0">Link unavailable</h1>
      <p class="m-0 text-muted">This reset link is invalid or expired.</p>
      <a href="forgot_password.php" class="btn btn-secondary btn-block">Request a new one</a>
    </div>
  <?php else: ?>
    <form method="post" class="card card-body grid gap-4" data-turbo="false">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div>
        <h1 class="text-[20px] font-bold m-0">Choose a new password</h1>
        <p class="m-0 mt-1 text-muted text-[13px]">Pick a password you don&rsquo;t use anywhere else.</p>
      </div>

      <?php if ($error): ?>
        <?= alert_box('bad', $error) ?>
      <?php endif; ?>

      <div class="field">
        <label class="label" for="password">New password</label>
        <div class="input-group">
          <input class="input" id="password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required autofocus autocomplete="new-password">
          <button type="button" class="btn btn-ghost btn-sm" data-toggle-password="password" aria-controls="password" aria-pressed="false">Show</button>
        </div>
      </div>
      <div class="field">
        <label class="label" for="confirm_password">Confirm password</label>
        <input class="input" id="confirm_password" type="password" name="confirm_password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required autocomplete="new-password">
      </div>

      <button class="btn btn-primary btn-block" type="submit">Update password</button>
    </form>
  <?php endif; ?>
  <p class="text-center text-[13px] m-0"><a href="index.php" class="link">Back to sign in</a></p>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
