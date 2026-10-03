<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user'] = [
      'user_id' => $user['user_id'],
      'name' => $user['name'],
      'email' => $user['email'],
      'role' => $user['role'],
      'theme' => $user['theme'] ?? 'system',
      'nav_layout' => $user['nav_layout'] ?? 'sidebar'
    ];
    header("Location: dashboard.php", true, 303);
    exit;
  } else {
    $error = "Email or password is incorrect.";
    http_response_code(422);
  }
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
    <h1 class="text-[20px] font-bold m-0">Sign in</h1>

    <?php if (isset($error)): ?>
      <?= alert_box('bad', $error) ?>
    <?php endif; ?>

    <div class="field">
      <label class="label" for="email">Email</label>
      <input type="email" name="email" id="email" class="input" required autocomplete="username" autofocus
        placeholder="name@company.com" value="<?= e($email) ?>">
    </div>

    <div class="field">
      <label class="label" for="password">Password</label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="input" required autocomplete="current-password">
        <button type="button" class="btn btn-ghost btn-sm" data-toggle-password="password" aria-controls="password" aria-pressed="false">Show</button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  </form>

  <p class="text-center text-[12px] text-muted m-0">Vorta Productivity Tracker</p>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
