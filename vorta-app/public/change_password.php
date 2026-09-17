<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

$user_id = $_SESSION['user']['user_id'] ?? 0;
$error = '';
$success = '';

// Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validasi input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua kolom harus diisi.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter.";
    } else {
        // Ambil password lama dari database
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Pengguna tidak ditemukan.";
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $error = "Password lama salah.";
        } else {
            // Update password baru (di-hash)
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update->execute([$hashed_password, $user_id]);

            $success = "Password berhasil diubah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - Change Password</title>
    <link rel="stylesheet" href="css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <main class="flex justify-center items-center h-screen mx-1">
        <div class="w-auto sm:w-1/2 mx-auto bg-gray-50 rounded-lg shadow-lg p-6 mt-10">
            <a href="profile.php"
                class="text-black font-medium">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="text-2xl font-bold text-gray-800 my-6">Change Password</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-800 text-sm rounded">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-800 text-sm rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-slate-700">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required
                        class="w-full px-4 py-2 border-b-2 bg-transparent border-black outline-none focus:border-black focus:ring-0 rounded-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                        class="w-full px-4 py-2 border-b-2 bg-transparent border-black outline-none focus:border-black focus:ring-0 rounded-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Confirm New Password</label>
                    <input type="password" name="confirm_password" required
                        class="w-full px-4 py-2 border-b-2 bg-transparent border-black outline-none focus:border-black focus:ring-0 rounded-none">
                </div>

                <div class="flex w-full pt-2">
                    <button type="submit"
                        class="px-4 sm:px-6 py-1 sm:py-2 w-full bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 
                            text-white font-medium
                                rounded-lg 
                                hover:from-indigo-600 hover:via-purple-600 hover:to-pink-600 
                                focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 
                                transition-all duration-300 ease-in-out
                                shadow-md hover:shadow-lg">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>

</html>