<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/login_guard.php';
require_once __DIR__ . '/../lib/tenant.php';

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
$email = '';
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
    header("Location: dashboard.php", true, 303);
    exit;
  } elseif (!isset($error)) {
    login_record_attempt($pdo, $email, false);
    $error = "Email or password is incorrect.";
  }
  http_response_code(422);
}

$layout = 'bare';
$pageTitle = 'Sign in';
include __DIR__ . '/../views/layout/start.php';
?>
<div class="w-full max-w-sm grid gap-6">
  <div class="flex items-center justify-center gap-3">
    <img src="images/vorta.png" alt="" class="size-10 object-contain">
    <span class="text-[18px] font-extrabold">Vorta</span>
  </div>

  <form method="post" class="card card-body grid gap-4">
    <?= csrf_field() ?>
    <h1 class="text-[20px] font-bold m-0">Sign in</h1>

    <?php if (isset($error)): ?>
      <?= alert_box('bad', $error) ?>
    <?php endif; ?>
    <?php if ($registered && !isset($error)): ?>
      <?= alert_box('ok', 'Your company workspace is ready. Sign in with the administrator account you just created.') ?>
    <?php endif; ?>

    <div class="field">
      <label class="label" for="email">Email</label>
      <input type="email" name="email" id="email" class="input" required autocomplete="username" autofocus
        placeholder="name@company.com" value="<?= e($email) ?>">
    </div>

    <div class="field">
      <div class="flex items-center justify-between gap-2">
        <label class="label" for="password">Password</label>
        <a href="forgot_password.php" class="link text-[12px]">Forgot password?</a>
      </div>
      <div class="input-group">
        <input type="password" name="password" id="password" class="input" required autocomplete="current-password">
        <button type="button" class="btn btn-ghost btn-sm" data-toggle-password="password" aria-controls="password" aria-pressed="false">Show</button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  </form>

  <p class="text-center text-[13px] text-muted m-0">New here? <a href="register.php" class="link">Create a company account</a></p>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
