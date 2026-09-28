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
  <style>
    body { padding-left:0 !important; }
    .invite-card { width:100%; max-width:440px; padding:32px; background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:var(--shadow-card,0 1px 3px rgba(0,0,0,.06),0 6px 18px -8px rgba(0,0,0,.12)); }
    .invite-title { margin:0; color:var(--text,#1f2937); font-size:26px; font-weight:700; }
    .invite-subtitle { margin-top:8px; color:var(--text-muted,#6b7280); font-size:14px; line-height:1.5; }
    .invite-label { display:block; margin-bottom:6px; color:var(--text,#374151); font-size:14px; font-weight:600; }
    .invite-input { width:100%; box-sizing:border-box; padding:11px 12px; border:1px solid var(--border,#d1d5db); border-radius:10px; background:var(--surface,#fff); color:var(--text,#1f2937); font-size:14px; }
    .invite-field { margin-top:18px; }
    .invite-submit { width:100%; margin-top:22px; padding:11px 16px; border:0; border-radius:10px; background:#4f46e5; color:#fff; font-size:14px; font-weight:700; cursor:pointer; }
    .invite-submit:hover { background:#4338ca; }
    .invite-error { margin-top:18px; padding:12px 14px; border:1px solid #fecaca; border-radius:10px; background:#fef2f2; color:#b91c1c; font-size:14px; }
    .invite-invalid { color:var(--text-muted,#6b7280); font-size:15px; line-height:1.5; }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <main class="invite-card">
    <?php if (!$invite): ?>
      <h1 class="invite-title">Invitation unavailable</h1>
      <p class="invite-subtitle invite-invalid">This invitation is invalid, expired, or has already been accepted. Ask your company administrator for a new link.</p>
    <?php else: ?>
      <h1 class="invite-title">Join your company</h1>
      <p class="invite-subtitle">Create your Vorta account using the invitation for <strong><?= htmlspecialchars($invite['email']) ?></strong>.</p>
      <?php if ($error): ?><div class="invite-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="invite-field">
          <label class="invite-label" for="invite-name">Your name</label>
          <input class="invite-input" id="invite-name" name="name" autocomplete="name" required>
        </div>
        <div class="invite-field">
          <label class="invite-label" for="invite-password">Password</label>
          <input class="invite-input" id="invite-password" type="password" name="password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" required>
        </div>
        <button class="invite-submit" type="submit">Create account</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
