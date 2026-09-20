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

$initial = strtoupper(mb_substr(trim($profile['name']) !== '' ? $profile['name'] : 'U', 0, 1));

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - My Profile</title>
    <link rel="stylesheet" href="css/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .prof-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            flex: 0 0 64px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            font-size: 26px;
            font-weight: 600;
            user-select: none;
        }
        .prof-chip {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: capitalize;
        }
        .prof-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--surface-3, #f3f4f6);
            color: var(--text-muted, #6b7280);
            transition: background .12s ease, color .12s ease;
        }
        .prof-icon-btn:hover {
            background: var(--brand-soft, #eef2ff);
            color: var(--brand, #4f46e5);
        }
    </style>
</head>

<body>
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="prof-avatar"><?= htmlspecialchars($initial) ?></span>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($profile['name']) ?></h1>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($profile['email']) ?></p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php if (!empty($profile['position'])): ?>
                                    <span class="prof-chip bg-indigo-100 text-indigo-800"><?= htmlspecialchars($profile['position']) ?></span>
                                <?php endif; ?>
                                <span class="prof-chip <?= $badge_color ?>"><?= htmlspecialchars($current_status) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-start sm:self-center">
                        <a href="edit_profile.php#appearance" class="prof-icon-btn" title="Appearance">
                            <i class="fas fa-palette"></i>
                        </a>
                        <a href="change_password.php" class="prof-icon-btn" title="Change Password">
                            <i class="fas fa-key"></i>
                        </a>
                        <a href="edit_profile.php"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium whitespace-nowrap">
                            Edit Profile
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100">
                    <div>
                        <p class="text-xs text-gray-500">Phone</p>
                        <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($profile['phone'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Check-in</p>
                        <p class="text-sm font-medium text-gray-800"><?= $attendance['check_in'] ?? '-' ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Check-out</p>
                        <p class="text-sm font-medium text-gray-800"><?= $attendance['check_out'] ?? '-' ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Reports This Month</p>
                        <p class="text-sm font-medium text-gray-800"><?= $reportCount ?> / <?= $target ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold text-gray-800">Monthly Report Progress</h2>
                    <span class="text-sm font-medium text-gray-600"><?= $progress ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-indigo-600 h-3 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                </div>
                <p class="text-sm text-gray-500 mt-2"><?= $reportCount ?> of <?= $target ?> reports submitted this month.</p>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>