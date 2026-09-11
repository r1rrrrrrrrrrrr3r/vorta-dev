<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

$user_id = $_SESSION['user']['user_id'] ?? 0;
$error = '';
$success = '';

// Ambil data pengguna: email dari users, name & phone dari employees
$stmt = $pdo->prepare("
    SELECT 
        u.email,
        COALESCE(e.name, u.name) AS name,  -- Jika nama di employees kosong, pakai dari users
        e.phone
    FROM users u 
    LEFT JOIN employees e ON u.user_id = e.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User tidak ditemukan.");
}

$name = $user['name'];
$email = $user['email'];
$phone = $user['phone'] ?? '';

// Proses saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Validasi
    if (empty($name) || empty($email)) {
        $error = "Nama dan email harus diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        // Cek apakah email sudah digunakan oleh user lain
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
            $error = "Email sudah digunakan oleh pengguna lain.";
        } else {
            // Update email di tabel users
            $updateUser = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $updateUser->execute([$email, $user_id]);

            // Update atau insert ke tabel employees
            $checkEmp = $pdo->prepare("SELECT user_id FROM employees WHERE user_id = ?");
            $checkEmp->execute([$user_id]);

            if ($checkEmp->fetch()) {
                // Sudah ada, update
                $updateEmp = $pdo->prepare("UPDATE employees SET name = ?, phone = ? WHERE user_id = ?");
                $updateEmp->execute([$name, $phone, $user_id]);
            } else {
                // Belum ada, insert baru
                $insertEmp = $pdo->prepare("INSERT INTO employees (user_id, name, phone) VALUES (?, ?, ?)");
                $insertEmp->execute([$user_id, $name, $phone]);
            }

            // Perbarui session
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;

            $success = "Profil berhasil diperbarui!";
        }
    }
}

include __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>vorta Prodtracker - Edit Profil</title>
    <link rel="stylesheet" href="css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="bg-gray-50">
    <div class="max-w-lg mx-auto bg-white rounded-lg shadow-lg p-6 mt-10">
        <div class="flex items-center gap-3 mb-6">
            <a href="profil.php" class="text-black font-medium">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="text-2xl font-bold text-gray-800">Edit Profil</h2>
        </div>

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

        <form method="POST" class="space-y-6">
            <!-- Nama -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>"
                       class="w-full px-4 py-2 border-b border-gray-300 outline-none focus:border-indigo-500 focus:ring-0 transition"
                       required>
            </div>

            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>"
                       class="w-full px-4 py-2 border-b border-gray-300 outline-none focus:border-indigo-500 focus:ring-0 transition"
                       required>
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>"
                       class="w-full px-4 py-2 border-b border-gray-300 outline-none focus:border-indigo-500 focus:ring-0 transition"
                       placeholder="Contoh: 081234567890">
            </div>

            <!-- Tombol -->
            <div class="flex gap-3 pt-4">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save Change
                </button>
                <a href="profil.php"
                   class="px-6 py-2 bg-gray-400 text-white font-medium rounded-lg hover:bg-gray-500 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>