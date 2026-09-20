<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT u.*, e.position, e.phone FROM users u LEFT JOIN employees e ON e.user_id = u.user_id WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$attendance = $stmt->fetch();

$target = 88;
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM production_reports WHERE user_id = ? AND report_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $monthStart, $monthEnd]);
$reportCount = (int)$stmt->fetchColumn();
$progress = min(100, round(($reportCount / $target) * 100));

$current_status = $attendance['status'] ?? 'Not Checked In';

$badge_color = match ($current_status) {
    'Present' => 'bg-green-100 text-green-800',
    'Late' => 'bg-yellow-100 text-yellow-800',
    'Leave' => 'bg-blue-100 text-blue-800',
    'Sick' => 'bg-purple-100 text-purple-800',
    'Absent', 'Forgot', 'Others' => 'bg-red-100 text-red-800',
    default => 'bg-gray-100 text-gray-800',
};

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Vorta Prodtracker - My Profile</title>
    <link rel="stylesheet" href="css/output.css">
</head>

<body>
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
                    <a href="edit_profile.php" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
                        Edit Profile
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="font-medium"><?= htmlspecialchars($profile['name']) ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium"><?= htmlspecialchars($profile['email']) ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Position</p>
                        <p class="font-medium"><?= htmlspecialchars($profile['position'] ?? '-') ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Phone</p>
                        <p class="font-medium"><?= htmlspecialchars($profile['phone'] ?? '-') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Today's Status</h2>
                <div class="flex items-center gap-4 mb-6">
                    <span class="px-4 py-2 rounded-full text-sm font-medium <?= $badge_color ?>">
                        <?= htmlspecialchars($current_status) ?>
                    </span>
                    <?php if ($attendance): ?>
                        <span class="text-sm text-gray-600">
                            Check-in: <?= $attendance['check_in'] ?? '-' ?> | Check-out: <?= $attendance['check_out'] ?? '-' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Monthly Report Progress</h3>
                <p class="text-sm text-gray-600 mb-2"><?= $reportCount ?> of <?= $target ?> reports this month</p>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-indigo-600 h-3 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                </div>
                <p class="text-right text-sm text-gray-500 mt-1"><?= $progress ?>%</p>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>