<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

// Ambil user_id dari session
$user_id = $_SESSION['user']['user_id'] ?? 0;
$today = date('Y-m-d');

// Ambil status absensi hari ini
$stmt = $pdo->prepare("SELECT status FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$attendance = $stmt->fetch();
$current_status = $attendance['status'] ?? 'Belum Absen';

$badge_color = match ($current_status) {
    'Hadir' => 'bg-green-100 text-green-800',
    'Telat' => 'bg-yellow-100 text-yellow-800',
    'Izin' => 'bg-blue-100 text-blue-800',
    'Alpa' => 'bg-red-100 text-red-800',
    default => 'bg-gray-100 text-gray-800'
};

// Tentukan awal dan akhir bulan ini
$first_day_of_month = date('Y-m-01');
$last_day_of_month = date('Y-m-t');

// Hitung total laporan bulan ini
$stmt1 = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM production_reports 
    WHERE user_id = ? 
      AND report_date >= ? 
      AND report_date <= ?
");
$stmt1->execute([$user_id, $first_day_of_month, $last_day_of_month]);
$monthly_report = $stmt1->fetch(PDO::FETCH_ASSOC);
$total_monthly = (int)$monthly_report['total'];

$stmt2 = $pdo->prepare("
    SELECT 
        pr.title, 
        pr.job_type, 
        pr.status,
        COALESCE(wf.workforce_name, 'Tidak Ada') as workforce_name,
        pr.created_at 
    FROM production_reports pr
    LEFT JOIN work_force wf ON pr.workforce_id = wf.workforce_id
    WHERE pr.user_id = ? 
      AND DATE(pr.report_date) = ?
    ORDER BY pr.created_at DESC 
    LIMIT 5
");
$stmt2->execute([$user_id, $today]);
$recent_activities_today = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Ambil name dan email
$stmt3 = $pdo->prepare("
    SELECT u.email, e.name, e.position
    FROM users u 
    LEFT JOIN employees e ON u.user_id = e.user_id
    WHERE u.user_id = ?
");
$stmt3->execute([$user_id]);
$user = $stmt3->fetch(PDO::FETCH_ASSOC);

$email = $user['email'] ?? '';
$name = $user['name'] ?? 'No Name';
$position = $user['position'] ?? '';

// Hitung persentase (target 88 job = 100%)
$target = 88;
$percentage = $target > 0 ? min(100, ($total_monthly / $target) * 100) : 0;
$percentage = round($percentage, 2); // 2 angka di belakang koma

include __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - Profile</title>
    <link rel="stylesheet" href="css/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
</head>
<body class="bg-gray-50 min-h-screen">
    <main class="flex items-center justify-center p-4 pt-16 pb-24">
        <!-- Profil Card -->
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-center">
                <h1 class="text-xl font-bold text-white mb-6">Vorta Productivity Tracker</h1>
                <img src="/images/defaultprofile.png" alt="Profile Picture" class="w-20 h-20 rounded-full border-4 border-white mx-auto mb-3">
                <h2 class="text-2xl font-semibold text-white"><?php echo htmlspecialchars($name); ?></h2>
                <p class="text-indigo-100 italic"><?php echo htmlspecialchars($position); ?></p>
                <p class="text-white text-sm mt-1"><?php echo htmlspecialchars($email); ?></p>
            </div>

            <!-- Statistik -->
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                    <!-- Job Total -->
                    <div class="bg-blue-50 p-4 rounded-xl">
                        <p class="text-sm font-medium text-blue-800">Job Total</p>
                        <p class="text-3xl font-bold text-blue-700 mt-1"><?php echo $total_monthly; ?></p>
                        <p class="text-xs text-blue-600">of <?php echo $target; ?> targets</p>
                    </div>

                    <!-- Progress -->
                    <div class="bg-green-50 p-4 rounded-xl">
                        <p class="text-sm font-medium text-green-800">Progress</p>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2 mb-1">
                            <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <p class="text-sm font-bold text-green-700"><?php echo $percentage; ?>%</p>
                        <p class="text-xs text-gray-500"><?php echo $total_monthly; ?>/<?php echo $target; ?> Job</p>
                    </div>

                    <!-- Status -->
                    <div class="bg-purple-50 p-4 rounded-xl">
                        <p class="text-sm font-medium text-purple-800">Status</p>
                        <p class="mt-2 text-md font-medium <?php echo $badge_color; ?> px-3 py-1 rounded-full inline-block">
                            <?php echo htmlspecialchars($current_status); ?>
                        </p>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Current Activity (<?php echo date('d M'); ?>)</h3>
                    <ul class="space-y-2">
                        <?php if (empty($recent_activities_today)): ?>
                            <li class="text-gray-500 text-sm text-center py-3 bg-gray-50 rounded-lg">No activity today.</li>
                        <?php else: ?>
                            <?php foreach ($recent_activities_today as $act): ?>
                                <li class="p-3 border border-gray-200 rounded-lg bg-white hover:shadow-sm transition">
                                    <a href="reports_my.php" class="flex justify-between items-center">
                                        <div class="flex-1 truncate">
                                            <p class="font-medium text-gray-800 text-sm sm:text-base truncate"><?php echo htmlspecialchars($act['title']); ?></p>
                                            <p class="text-xs text-gray-600">
                                                <?php echo htmlspecialchars($act['job_type']); ?> • 
                                                <?php echo htmlspecialchars($act['workforce_name']); ?> • 
                                                <?php echo date('H:i', strtotime($act['created_at'])); ?>
                                            </p>
                                        </div>
                                        <span class="ml-3 px-2 py-1 text-xs font-medium rounded-full 
                                            <?php echo $act['status'] === 'Selesai' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo htmlspecialchars($act['status']); ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Tombol Aksi -->
                <div class="flex flex-col sm:flex-row gap-3 pt-2 items-center">
                    <a href="change_password.php" class="flex-1 w-[208px] sm:w-[145px] py-3 sm:py-4 flex items-center justify-center bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 transition text-sm font-medium">Change Password</a>
                    <a href="edit_profil.php" class="flex-1 w-[208px]  sm:w-[145px] py-3 sm:py-4 flex items-center justify-center bg-yellow-500 text-white text-center rounded-lg hover:bg-yellow-600 transition text-sm font-medium">Edit Profil</a>
                    <a href="logout.php" class="flex-1 w-[208px]  sm:w-[145px] py-3 sm:py-4 flex items-center justify-center bg-red-500 text-white text-center rounded-lg hover:bg-red-600 transition text-sm font-medium">Log Out</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>