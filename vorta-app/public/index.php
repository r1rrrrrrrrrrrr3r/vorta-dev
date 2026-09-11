<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

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
      'role' => $user['role']
    ];
    header("Location: dashboard.php");
    exit;
  } else {
    $error = "Email atau password salah.";
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - vorta Tracker</title>
  <link rel="stylesheet" href="css/output.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8fafc;
    }
    .login-card {
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center">
  <div class="w-full max-w-md">
    <div class="login-card bg-white rounded-xl p-8">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">vorta Tracker</h1>
        <p class="text-gray-600">Productivity System Login</p>
      </div>

      <?php if(isset($error)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg text-sm">
          <?php echo htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" name="email" required
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                 placeholder="email@contoh.com">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input type="password" name="password" required
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                 placeholder="••••••••">
        </div>

        <div>
          <button type="submit" 
                  class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
            Sign In
          </button>
        </div>
      </form>

        <!-- <div class="mt-6 pt-6 border-t border-gray-100">
          <p class="text-xs text-gray-500 text-center">
            Admin default: admin@vorta.local / admin123<br>
            (ubah setelah instalasi)
          </p>
        </div> -->
    </div>
  </div>
</body>
</html>