<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/ui.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = $pdo->prepare("
    SELECT * FROM company_invitations
    WHERE token_hash = ? AND accepted_at IS NULL AND expires_at > NOW()
    LIMIT 1
");
$stmt->execute([hash('sha256', $token)]);
$invite = $stmt->fetch();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invite) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
        $error = 'Name and a password of at least ' . ACCOUNT_MIN_PASSWORD_LENGTH . ' characters are required.';
    } else {
        $existing = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $existing->execute([$invite['email']]);
        if ($existing->fetch()) {
            $error = 'An account already exists for this email.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$invite['company_id'], $name, $invite['email'], password_hash($password, PASSWORD_DEFAULT), $invite['role']]);
                $userId = (int)$pdo->lastInsertId();
                $employee = $pdo->prepare("INSERT INTO employees (company_id, user_id, name) VALUES (?, ?, ?)");
                $employee->execute([$invite['company_id'], $userId, $name]);
                $stmt = $pdo->prepare("UPDATE company_invitations SET accepted_at = NOW() WHERE invitation_id = ?");
                $stmt->execute([$invite['invitation_id']]);
                $pdo->commit();
                header('Location: index.php', true, 303);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'We could not activate this invitation. Please ask the administrator to create a new link.';
            }
        }
    }
}
if ($error !== '') {
    http_response_code(422);
}

$layout = 'bare';
$pageTitle = 'Accept invitation';
include __DIR__ . '/../views/layout/start.php';
?>
<div class="w-full max-w-sm grid gap-6">
  <div class="flex items-center justify-center gap-3">
    <img src="images/vorta.png" alt="" class="size-10 object-contain">
    <span class="text-[18px] font-extrabold">Vorta</span>
  </div>

  <?php if (!$invite): ?>
    <div class="card card-body grid gap-3">
      <h1 class="text-[20px] font-bold m-0">Invitation unavailable</h1>
      <p class="m-0 text-muted">This invitation is invalid, expired, or has already been accepted. Ask your company administrator for a new link.</p>
    </div>
    <p class="text-center text-[13px] m-0"><a href="index.php" class="link">Back to sign in</a></p>
  <?php else: ?>
    <form method="post" class="card card-body grid gap-4">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div>
        <h1 class="text-[20px] font-bold m-0">Join your company</h1>
        <p class="m-0 mt-1 text-muted text-[13px]">Create your Vorta account using the invitation for <strong class="text-ink break-all"><?= e($invite['email']) ?></strong>.</p>
      </div>

      <?php if ($error): ?>
        <?= alert_box('bad', $error) ?>
      <?php endif; ?>

      <div class="field">
        <label class="label" for="invite-name">Your name</label>
        <input class="input" id="invite-name" name="name" autocomplete="name" required autofocus value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="label" for="invite-password">Password</label>
        <div class="input-group">
          <input class="input" id="invite-password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" required>
          <button type="button" class="btn btn-ghost btn-sm" data-toggle-password="invite-password" aria-controls="invite-password" aria-pressed="false">Show</button>
        </div>
        <p class="help">At least <?= ACCOUNT_MIN_PASSWORD_LENGTH ?> characters.</p>
      </div>

      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p class="text-center text-[13px] text-muted m-0">Already have an account? <a href="index.php" class="link">Sign in</a></p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
