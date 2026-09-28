<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/tenant.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $companyName = trim($_POST['company_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($companyName === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Company, name, and a valid email are required.';
    } elseif (mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
        $error = 'Password must be at least ' . ACCOUNT_MIN_PASSWORD_LENGTH . ' characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                throw new RuntimeException('An account already exists for this email.');
            }

            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $companyName), '-')) . '-' . bin2hex(random_bytes(3));
            $stmt = $pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
            $stmt->execute([$companyName, $slug]);
            $companyId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('
                INSERT INTO users (company_id, name, email, password_hash, role)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$companyId, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
            $stmt = $pdo->prepare("INSERT INTO work_force (company_id, workforce_name) VALUES (?, 'General')");
            $stmt->execute([$companyId]);
            $stmt = $pdo->prepare("INSERT INTO job_type (company_id, name) VALUES (?, 'General')");
            $stmt->execute([$companyId]);
            $stmt = $pdo->prepare("INSERT INTO app_settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)");
            $stmt->execute([$companyId, 'monthly_target_min', '50']);
            $stmt->execute([$companyId, 'monthly_target_max', '88']);
            $stmt->execute([$companyId, 'daily_min_reports', '2']);
            $pdo->commit();
            header('Location: index.php?registered=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Registration failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vorta Prodtracker - Create Company</title>
  <link rel="stylesheet" href="css/output.css">
  <?php include __DIR__ . '/ui_head.php'; ?>
  <style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
  body { font-family: 'Inter', sans-serif; padding-left: 0 !important; }
  .login-card { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-8">
  <main class="w-full max-w-md">
    <section class="login-card bg-white rounded-xl p-8">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Create your company</h1>
        <p class="text-gray-600">Set up your workspace and administrator account.</p>
      </div>

      <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-5">
        <?= csrf_field() ?>
        <div>
          <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company name</label>
          <input id="company_name" name="company_name" required autofocus
            value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="Acme Studio">
        </div>
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Your name</label>
          <input id="name" name="name" required
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="Jane Doe">
        </div>
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Work email</label>
          <input id="email" type="email" name="email" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="you@company.com">
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input id="password" type="password" name="password" minlength="6" required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="At least 6 characters">
        </div>
        <button type="submit"
          class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">
          Create company
        </button>
      </form>
      <p class="mt-6 text-center text-sm text-gray-600">
        Already have an account?
        <a href="index.php" class="font-medium text-indigo-600 hover:text-indigo-700">Sign in</a>
      </p>
    </section>
  </main>
</body>
</html>
