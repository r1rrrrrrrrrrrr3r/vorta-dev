<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');
$limit = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get employee position
$stmt = $pdo->prepare("SELECT position FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

$isIntern = false;
if ($employee) {
    $position = strtolower(trim($employee['position']));
    $isIntern = in_array($position, ['internship', 'intern', 'magang']);
}

// Handle Absence Reason Submission (REVISI: Hanya untuk hari ini)
if (isset($_POST['submitAbsenceReason'])) {
    $absence_date = $today; // SELALU hari ini, tidak bisa pilih kemarin
    $absence_type = $_POST['absence_type'] ?? '';
    $explanation = trim($_POST['explanation'] ?? '');

    // Validasi input
    if (empty($absence_type) || empty($explanation)) {
        header("Location: attendance.php?error=missing_data");
        exit;
    }

    // Validasi: Hanya boleh input untuk hari ini
    if ($absence_date !== $today) {
        header("Location: attendance.php?error=invalid_date");
        exit;
    }

    // Validasi: Batas waktu hari ini sampai 23:59
    if ($current_time >= '23:59:59') {
        header("Location: attendance.php?error=time_expired");
        exit;
    }

    // Cek apakah sudah ada attendance record untuk hari ini
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $absence_date]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Jika sudah ada record, hanya bisa update jika statusnya masih kosong/null
        if ($existing['status'] !== null && $existing['status'] !== '') {
            header("Location: attendance.php?error=already_attended");
            exit;
        }

        // Update existing record
        $stmt = $pdo->prepare("UPDATE attendance SET status = ?, notes = ?, explanation = ?, updated_at = NOW() 
                              WHERE user_id = ? AND date = ?");
        $stmt->execute([$absence_type, "Absence Reason: $absence_type", $explanation, $user_id, $absence_date]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, status, notes, explanation, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $absence_date, $absence_type, "Absence Reason: $absence_type", $explanation]);
    }

    header("Location: attendance.php?success=absence_reason_submitted");
    exit;
}

// Handle Leave Request
if (isset($_POST['submitLeave'])) {
    $leave_type = $_POST['leave_type'] ?? '';
    $explanation = trim($_POST['explanation']);

    if (empty($leave_type) || empty($explanation)) {
        header("Location: attendance.php?error=missing_data");
        exit;
    }

    // Cek apakah sudah check-in
    $stmt = $pdo->prepare("SELECT check_in FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $today]);
    $existing = $stmt->fetch();

    if ($existing && $existing['check_in']) {
        header("Location: attendance.php?error=already_checked_in");
        exit;
    }

    // Tentukan status
    $status = match ($leave_type) {
        'Sick' => 'Sick',
        'Leave' => 'Leave',
        'Others' => 'Others',
        default => 'Leave'
    };

    $notes = "$status request";

    // Simpan ke database
    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, status, notes, explanation) 
                          VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = VALUES(status), 
                          notes = VALUES(notes), 
                          explanation = VALUES(explanation)");

    $stmt->execute([$user_id, $today, $status, $notes, $explanation]);

    header("Location: attendance.php");
    exit;
}

// Handle Check-in
if (isset($_POST['submitCheckIn'])) {
    $current_time = date('H:i:s');
    $location = trim($_POST['location']);
    $shift = $_POST['shift'] ?? 'WFO';
    $explanation = trim($_POST['explanation'] ?? '');

    // Cek apakah sudah request leave atau absence reason
    $stmt = $pdo->prepare("SELECT status FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $today]);
    $existing = $stmt->fetch();

    if ($existing && in_array($existing['status'], ['Leave', 'Sick', 'Others', 'Absent', 'Forgot'])) {
        header("Location: attendance.php?error=attendance_already_submitted");
        exit;
    }

    $status = 'Present';
    $notes = "Hadir: $shift";
    $save_explanation = null;

    // Cek keterlambatan
    $is_late = false;
    $current_minutes = (int)date('H') * 60 + (int)date('i');

    if ($shift === 'Morning' && $current_minutes > (8 * 60 + 15)) {
        $is_late = true;
    } elseif ($shift === 'Afternoon' && $current_minutes > (13 * 60 + 30)) {
        $is_late = true;
    } elseif (in_array($shift, ['WFO', 'WAC', 'WFH', 'WFA']) && $current_minutes > (9 * 60 + 30)) {
        $is_late = true;
    }

    if ($is_late) {
        $status = 'Late';
        $notes = "Late: $shift";

        if (empty($explanation) || strlen($explanation) < 10) {
            header("Location: attendance.php?error=explanation_required");
            exit;
        }
        $save_explanation = $explanation;
    }

    // Simpan ke database
    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in, status, location, notes, explanation) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          check_in = VALUES(check_in), 
                          status = VALUES(status), 
                          location = VALUES(location),
                          notes = VALUES(notes),
                          explanation = VALUES(explanation)");

    $stmt->execute([
        $user_id,
        $today,
        $current_time,
        $status,
        $location,
        $notes,
        $save_explanation
    ]);

    header("Location: attendance.php");
    exit;
}

// Handle Check-out
if (isset($_POST['check_out'])) {
    $current_time = date('H:i:s');
    $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE user_id = ? AND date = ?");
    $stmt->execute([$current_time, $user_id, $today]);
    header("Location: attendance.php");
    exit;
}

// Get today's attendance
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$attendance = $stmt->fetch();

// Cek apakah sudah request leave atau absence
$is_leave = $attendance && in_array($attendance['status'], ['Leave', 'Sick', 'Others', 'Absent', 'Forgot']);

// REVISI: Hanya bisa input absence reason untuk hari ini saja, sampai jam 23:59
$can_input_absence = ($current_time < '23:59:59') &&
    (!$attendance || empty($attendance['status']) ||
        in_array($attendance['status'], ['', null]));

// Get monthly attendance dengan pagination
$month = $_GET['month'] ?? date('Y-m');
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));

// Hitung total data untuk pagination
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ?");
$totalStmt->execute([$user_id, $start, $end]);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

// Get data dengan pagination
$sql = "SELECT date, check_in, check_out, status, location, notes, explanation
        FROM attendance 
        WHERE user_id = ? AND date BETWEEN ? AND ?
        ORDER BY date DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $start, $end]);
$monthly = $stmt->fetchAll();



include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - Attendance</title>
    <link rel="stylesheet" href="css/output.css">
</head>

<body>
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

        <!-- Today's Attendance -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <!-- Header Section -->
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Attendance Today (<?= date('d F Y') ?>)</h1>
                        <p class="text-sm text-gray-600 mt-1">
                            Batas input absence reason: Hari ini sampai jam <strong>23:59</strong>
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <!-- Employee Status Badge -->
                        <span class="inline-block px-4 py-2 bg-gray-100 text-gray-800 text-sm font-medium rounded-full whitespace-nowrap">
                            You are: <strong><?= $isIntern ? 'Internship' : 'Full-time Employee' ?></strong>
                        </span>

                        <!-- Input Absence Reason Button -->
                        <?php if ($can_input_absence): ?>
                            <button type="button" id="btnAbsenceReason"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition whitespace-nowrap hover:opacity-85 hover:cursor-pointer"
                                style="background-color: #9333ea; color: white;">
                                📝 Input Absence Reason
                            </button>

                        <?php endif; ?>
                    </div>
                </div>

                <!-- Success Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 p-4 bg-green-50 text-green-700 rounded-lg text-sm">
                        <?= htmlspecialchars(
                            $_GET['success'] === 'absence_reason_submitted' ? 'Alasan ketidakhadiran berhasil disimpan.' :
                                'Aksi berhasil dilakukan.'
                        ) ?>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg text-sm">
                        <?= htmlspecialchars(
                            $_GET['error'] === 'missing_data' ? 'Data tidak lengkap.' : ($_GET['error'] === 'already_checked_in' ? 'Anda sudah check-in, tidak bisa request leave.' : ($_GET['error'] === 'leave_already_submitted' ? 'Anda sudah request leave, tidak bisa check-in.' : ($_GET['error'] === 'attendance_already_submitted' ? 'Anda sudah mengisi kehadiran untuk hari ini.' : ($_GET['error'] === 'explanation_required' ? 'Alasan keterlambatan wajib diisi (min. 10 karakter).' : ($_GET['error'] === 'invalid_date' ? 'Hanya bisa input absence reason untuk hari ini.' : ($_GET['error'] === 'time_expired' ? 'Batas waktu input absence reason sudah lewat (23:59).' : ($_GET['error'] === 'already_attended' ? 'Anda sudah mengisi kehadiran untuk tanggal tersebut.' :
                                'Error tidak diketahui.')))))))
                        ) ?>
                    </div>
                <?php endif; ?>

                <!-- Main Action Buttons -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <!-- Check-in -->
                    <button type="button" id="btnCheckIn"
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'disabled' : '' ?>
                        class="w-full py-3 px-4 
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'bg-gray-300 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700' ?>
                        text-white font-medium rounded-lg transition">
                        <?= $attendance && $attendance['check_in'] ? 'Checked-in (' . $attendance['check_in'] . ')' : ($is_leave ? 'Attendance Submitted' : 'Check-in') ?>
                    </button>

                    <!-- Checkout Button -->
                    <form method="post" id="checkoutForm" class="flex-1">
                        <button type="button" id="btnCheckout"
                            class="w-full py-3 px-4 
                            <?= !$attendance || !$attendance['check_in'] || $attendance['check_out'] ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700' ?>
                            text-white font-medium rounded-lg transition"
                            <?= !$attendance || !$attendance['check_in'] || $attendance['check_out'] ? 'disabled' : '' ?>>
                            <?= $attendance && $attendance['check_out'] ? 'Checked-out (' . $attendance['check_out'] . ')' : 'Check-out' ?>
                        </button>
                        <input type="hidden" name="check_out" value="1">
                    </form>

                    <!-- Leave -->
                    <button id="btnLeave" type="button"
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'disabled' : '' ?>
                        class="w-full py-3 px-4 
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'bg-gray-300 cursor-not-allowed' : 'bg-yellow-500 hover:bg-yellow-600' ?>
                        text-white font-medium rounded-lg transition">
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'Already Action Taken' : 'Request Leave' ?>
                    </button>
                </div>

                <!-- Today's Details -->
                <?php if ($attendance): ?>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="font-medium 
                                <?= $attendance['status'] === 'Present' ? 'text-green-600' : ($attendance['status'] === 'Late' ? 'text-yellow-600' :
                                    'text-red-600') ?>">
                                <?= htmlspecialchars($attendance['status']) ?>
                            </p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Check-in</p>
                            <p class="font-medium"><?= $attendance['check_in'] ?? '-' ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Check-out</p>
                            <p class="font-medium"><?= $attendance['check_out'] ?? '-' ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Location</p>
                            <p class="font-medium"><?= htmlspecialchars($attendance['location'] ?? '-') ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Explanation</p>
                            <p class="font-medium max-w-xs truncate"
                                title="<?= htmlspecialchars($attendance['explanation'] ?? '-') ?>">
                                <?= htmlspecialchars($attendance['explanation'] ?? '-') ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal Absence Reason (REVISI: Hanya untuk hari ini) -->
        <div id="modalAbsenceReason" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Input Absence Reason</h2>
                <p class="text-sm text-gray-600 mb-4">
                    <strong>Batas waktu:</strong> Hari ini sampai jam <strong>23:59</strong><br>
                    <strong>Tanggal:</strong> <?= date('d F Y') ?>
                </p>
                <form method="post" id="absenceReasonForm">
                    <input type="hidden" name="absence_date" value="<?= $today ?>">

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Jenis Ketidakhadiran *</label>
                        <select name="absence_type" required class="w-full border rounded-lg p-2">
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Absent">Tidak Hadir (Absent)</option>
                            <option value="Forgot">Lupa Absen (Forgot)</option>
                            <option value="Sick">Sakit (Sick)</option>
                            <option value="Leave">Cuti (Leave)</option>
                            <option value="Others">Lainnya (Others)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Alasan / Penjelasan *</label>
                        <textarea name="explanation" class="w-full border rounded-lg p-2" rows="3"
                            placeholder="Jelaskan alasan ketidakhadiran Anda..." required></textarea>
                        <p class="text-xs text-gray-500 mt-1">Minimal 10 karakter</p>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-yellow-800">
                            <strong>Perhatian:</strong> Absence reason hanya bisa diinput pada hari yang sama sampai jam 23:59. Besok sudah tidak bisa input untuk hari ini.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" id="closeModalAbsenceReason" class="px-4 py-2 bg-gray-300 rounded-lg">Batal</button>
                        <button type="submit" name="submitAbsenceReason" class="px-4 py-2 bg-green-500 hover:bg-green-700 text-white rounded-lg">Submit</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Modal Checkout -->
        <div id="modalCheckout" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Konfirmasi Check-out</h2>
                <p class="mb-4">Apakah kamu yakin ingin melakukan check-out sekarang?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" id="closeModalCheckout" class="px-4 py-2 bg-gray-300 rounded-lg">Batal</button>
                    <button type="button" id="confirmCheckout" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Ya, Checkout</button>
                </div>
            </div>
        </div>

        <!-- Modal Check-in -->
        <div id="modalCheckIn" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Check-in Confirmation</h2>
                <form method="post" id="checkInForm">
                    <p class="text-sm text-gray-600 mb-3">
                        <strong>Morning:</strong> 07:30 - 11:59 | <strong>Afternoon:</strong> 13:00 - 17:30
                    </p>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Shift</label>
                        <select name="shift" required class="w-full border rounded-lg p-2" id="shiftSelect">
                            <option value="">-- Select Shift --</option>
                            <option value="Morning">Pagi</option>
                            <option value="Afternoon">Siang</option>
                            <option value="WFO">Whole Day at Office (WFO)</option>
                            <option value="WAC">Working at Client (WAC)</option>
                            <option value="WFH">Working from Home (WFH)</option>
                            <option value="WFA">Working from Anywhere (WFA)</option>
                        </select>
                        <p id="shiftHint" class="mt-1 text-xs text-gray-500 italic">Please select your shift.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Location</label>
                        <input type="text" name="location" placeholder="Office, WFH, Meeting" class="w-full border rounded-lg p-2" required>
                    </div>

                    <div class="mb-4 hidden" id="explanationContainer">
                        <label class="block text-sm font-medium mb-1">Explanation / Reason for Late</label>
                        <textarea name="explanation" class="w-full border rounded-lg p-2" rows="3" placeholder="e.g., Traffic jam, health issue, family emergency"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" id="closeModalCheckIn" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                        <button type="submit" name="submitCheckIn" class="px-4 py-2 bg-green-600 text-white rounded-lg">Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Leave -->
        <div id="modalLeave" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Leave Form</h2>
                <form method="post">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Type of Leave</label>
                        <select name="leave_type" required class="w-full border rounded-lg p-2">
                            <option value="">-- Select Type --</option>
                            <option value="Sick">Sick / Illness</option>
                            <option value="Leave">Leave / Cuti</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Explanation / Reason</label>
                        <textarea name="explanation" class="w-full border rounded-lg p-2" rows="3" placeholder="e.g., Demam tinggi, acara keluarga" required></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" id="closeModalLeave" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                        <button type="submit" name="submitLeave" class="px-4 py-2 bg-yellow-500 text-white rounded-lg">Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Monthly Recap (sama seperti sebelumnya) -->
        <!-- Monthly Recap -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Monthly Recap</h2>
                    <form class="flex flex-col sm:flex-row items-center gap-2">
                        <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 transition">
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Filter</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-gray-200">
                            <tr>
                                <th class="pb-3 font-medium text-gray-600 text-left">Date</th>
                                <th class="pb-3 font-medium text-gray-600 text-left">Check-in</th>
                                <th class="pb-3 font-medium text-gray-600 text-left">Check-out</th>
                                <th class="pb-3 font-medium text-gray-600 text-left">Status</th>
                                <th class="pb-3 font-medium text-gray-600 text-left">Location</th>
                                <th class="pb-3 font-medium text-gray-600 text-left">Notes</th>
                                <th class="pb-3 font-medium text-gray-600 text-left">Explanation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($monthly)): ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-500">
                                        No attendance records found for this month.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($monthly as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-4"><?= htmlspecialchars($record['date']) ?></td>
                                        <td class="py-4"><?= $record['check_in'] ?? '-' ?></td>
                                        <td class="py-4"><?= $record['check_out'] ?? '-' ?></td>
                                        <td class="py-4">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                                <?= $record['status'] === 'Present' ? 'bg-green-100 text-green-800' : ($record['status'] === 'Late' ? 'bg-yellow-100 text-yellow-800' :
                                                    'bg-red-100 text-red-800') ?>">
                                                <?= htmlspecialchars($record['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4"><?= htmlspecialchars($record['location'] ?? '-') ?></td>
                                        <td class="py-4"><?= htmlspecialchars($record['notes'] ?? '-') ?></td>
                                        <td class="py-4 text-sm max-w-[200px] whitespace-nowrap overflow-hidden text-ellipsis relative group">
                                            <span class="block truncate"
                                                title="<?= htmlspecialchars($record['explanation'] ?? '-') ?>">
                                                <?= htmlspecialchars($record['explanation'] ?? '-') ?>
                                            </span>

                                            <!-- Tooltip -->
                                            <?php if (!empty($record['explanation'])): ?>
                                                <div class="absolute hidden group-hover:block left-0 top-full mt-1 w-64 bg-gray-900 text-white text-xs rounded-lg p-2 shadow-lg z-50">
                                                    <?= htmlspecialchars($record['explanation']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
                        <div class="text-sm text-gray-600 whitespace-nowrap">
                            Showing <?= count($monthly) ?> of <?= $total ?> records (Page <?= $page ?> of <?= $totalPages ?>)
                        </div>
                        <nav class="flex flex-wrap justify-center gap-1">
                            <!-- First Page -->
                            <?php if ($page > 1): ?>
                                <a href="?month=<?= htmlspecialchars($month) ?>&page=1"
                                    class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                                    &laquo; First
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                                    &laquo; First
                                </span>
                            <?php endif; ?>

                            <!-- Previous -->
                            <?php if ($page > 1): ?>
                                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page - 1 ?>"
                                    class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                                    &lsaquo; Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                                    &lsaquo; Prev
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded-lg text-sm font-medium">
                                        <?= $i ?>
                                    </span>
                                <?php else: ?>
                                    <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $i ?>"
                                        class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page + 1 ?>"
                                    class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                                    Next &rsaquo;
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                                    Next &rsaquo;
                                </span>
                            <?php endif; ?>

                            <!-- Last -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $totalPages ?>"
                                    class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                                    Last &raquo;
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                                    Last &raquo;
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>

            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const btnCheckIn = document.getElementById('btnCheckIn');
                    const modalCheckIn = document.getElementById('modalCheckIn');
                    const closeModalCheckIn = document.getElementById('closeModalCheckIn');

                    const btnLeave = document.getElementById('btnLeave');
                    const modalLeave = document.getElementById('modalLeave');
                    const closeModalLeave = document.getElementById('closeModalLeave');

                    const btnCheckout = document.getElementById('btnCheckout');
                    const modalCheckout = document.getElementById('modalCheckout');
                    const closeModalCheckout = document.getElementById('closeModalCheckout');
                    const confirmCheckout = document.getElementById('confirmCheckout');
                    const checkoutForm = document.getElementById('checkoutForm');

                    const btnAbsenceReason = document.getElementById('btnAbsenceReason');
                    const modalAbsenceReason = document.getElementById('modalAbsenceReason');
                    const closeModalAbsenceReason = document.getElementById('closeModalAbsenceReason');

                    const shiftSelect = document.getElementById('shiftSelect');
                    const explanationContainer = document.getElementById('explanationContainer');
                    const checkInForm = document.getElementById('checkInForm');

                    function getCurrentTime() {
                        const now = new Date();
                        return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:00`;
                    }

                    function isLate(shift, timeStr) {
                        const [h, m] = timeStr.split(':').map(Number);
                        const minutes = h * 60 + m;
                        if (shift === 'Morning' && minutes > 8 * 60 + 15) return true;
                        if (shift === 'Afternoon' && minutes > 13 * 60 + 30) return true;
                        if (['WFO', 'WAC', 'WFH', 'WFA'].includes(shift) && minutes > 9 * 60 + 30) return true;
                        return false;
                    }

                    // Absence Reason Modal
                    btnAbsenceReason?.addEventListener('click', () => {
                        modalAbsenceReason.classList.remove('hidden');
                    });

                    closeModalAbsenceReason?.addEventListener('click', () => {
                        modalAbsenceReason.classList.add('hidden');
                    });

                    // Validasi waktu untuk absence reason
                    const absenceReasonForm = document.getElementById('absenceReasonForm');
                    absenceReasonForm?.addEventListener('submit', function(e) {
                        const now = new Date();
                        const hours = now.getHours();
                        const minutes = now.getMinutes();

                        // Cek jika sudah lewat jam 23:59
                        if (hours === 23 && minutes >= 59) {
                            e.preventDefault();
                            alert('Batas waktu input absence reason sudah lewat (23:59). Tidak bisa input lagi.');
                            return false;
                        }
                    });


                    // Checkout Modal
                    btnCheckout?.addEventListener('click', () => {
                        if (!btnCheckout.disabled) {
                            modalCheckout.classList.remove('hidden');
                        }
                    });

                    closeModalCheckout?.addEventListener('click', () => {
                        modalCheckout.classList.add('hidden');
                    });

                    confirmCheckout?.addEventListener('click', () => {
                        checkoutForm.submit();
                    });

                    // Check-in Modal
                    shiftSelect?.addEventListener('change', function() {
                        const currentTime = getCurrentTime();
                        if (isLate(this.value, currentTime)) {
                            explanationContainer.classList.remove('hidden');
                        } else {
                            explanationContainer.classList.add('hidden');
                        }
                    });

                    checkInForm?.addEventListener('submit', function(e) {
                        const shift = shiftSelect.value;
                        const currentTime = getCurrentTime();
                        const explanation = checkInForm.querySelector('textarea[name="explanation"]')?.value.trim();

                        if (isLate(shift, currentTime) && (!explanation || explanation.length < 10)) {
                            e.preventDefault();
                            alert('Alasan keterlambatan wajib diisi (minimal 10 karakter).');
                        }
                    });

                    btnCheckIn?.addEventListener('click', () => {
                        if (btnCheckIn.disabled) {
                            alert('Aksi tidak bisa dilakukan.');
                        } else {
                            modalCheckIn.classList.remove('hidden');
                        }
                    });

                    closeModalCheckIn?.addEventListener('click', () => modalCheckIn.classList.add('hidden'));

                    // Leave Modal
                    btnLeave?.addEventListener('click', () => {
                        if (btnLeave.disabled) {
                            alert('Aksi tidak bisa dilakukan.');
                        } else {
                            modalLeave.classList.remove('hidden');
                        }
                    });

                    closeModalLeave?.addEventListener('click', () => modalLeave.classList.add('hidden'));
                });
            </script>

            <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>