<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';

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
                header('Location: index.php');
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accept invitation - Vorta Prodtracker</title>
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

    <?php if (!$invite): ?>
      <h1 class="account-title">Invitation unavailable</h1>
      <p class="account-copy">This invitation is invalid, expired, or has already been accepted. Ask your company administrator for a new link.</p>
      <p class="account-foot"><a href="index.php">Back to sign in</a></p>
    <?php else: ?>
      <h1 class="account-title">Join your company</h1>
      <p class="account-copy">Create your Vorta account using the invitation for <strong><?= htmlspecialchars($invite['email']) ?></strong>.</p>
      <?php if ($error): ?>
        <div class="account-alert account-alert--error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="account-field">
          <label class="account-label" for="invite-name">Your name</label>
          <input class="account-input" id="invite-name" name="name" autocomplete="name" required autofocus>
        </div>
        <div class="account-field">
          <label class="account-label" for="invite-password">Password</label>
          <input class="account-input" id="invite-password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" required>
        </div>
        <button class="account-submit" type="submit">Create account</button>
      </form>
      <p class="account-foot">Already have an account? <a href="index.php">Sign in</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
