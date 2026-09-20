<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');
$limit = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT position FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

$isIntern = false;
if ($employee) {
    $position = strtolower(trim($employee['position']));
    $isIntern = in_array($position, ['internship', 'intern']);
}

if (isset($_POST['submitAbsenceReason'])) {
    csrf_verify();
    $absence_date = $today;
    $absence_type = $_POST['absence_type'] ?? '';
    $explanation = trim($_POST['explanation'] ?? '');

    if (empty($absence_type) || empty($explanation)) {
        header("Location: attendance.php?error=missing_data");
        exit;
    }

    if ($absence_date !== $today) {
        header("Location: attendance.php?error=invalid_date");
        exit;
    }

    if ($current_time >= '23:59:59') {
        header("Location: attendance.php?error=time_expired");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $absence_date]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] !== null && $existing['status'] !== '') {
            header("Location: attendance.php?error=already_attended");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE attendance SET status = ?, notes = ?, explanation = ?, updated_at = NOW()
                              WHERE user_id = ? AND date = ?");
        $stmt->execute([$absence_type, "Absence Reason: $absence_type", $explanation, $user_id, $absence_date]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, status, notes, explanation, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $absence_date, $absence_type, "Absence Reason: $absence_type", $explanation]);
    }

    header("Location: attendance.php?success=absence_reason_submitted");
    exit;
}

if (isset($_POST['submitLeave'])) {
    csrf_verify();
    $leave_type = $_POST['leave_type'] ?? '';
    $explanation = trim($_POST['explanation']);

    if (empty($leave_type) || empty($explanation)) {
        header("Location: attendance.php?error=missing_data");
        exit;
    }

    $stmt = $pdo->prepare("SELECT check_in FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $today]);
    $existing = $stmt->fetch();

    if ($existing && $existing['check_in']) {
        header("Location: attendance.php?error=already_checked_in");
        exit;
    }

    $status = match ($leave_type) {
        'Sick' => 'Sick',
        'Leave' => 'Leave',
        'Others' => 'Others',
        default => 'Leave'
    };

    $notes = "$status request";

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

if (isset($_POST['submitCheckIn'])) {
    csrf_verify();
    $current_time = date('H:i:s');
    $location = trim($_POST['location']);
    $shift = $_POST['shift'] ?? 'WFO';
    $explanation = trim($_POST['explanation'] ?? '');

    $stmt = $pdo->prepare("SELECT status FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $today]);
    $existing = $stmt->fetch();

    if ($existing && in_array($existing['status'], ['Leave', 'Sick', 'Others', 'Absent', 'Forgot'])) {
        header("Location: attendance.php?error=attendance_already_submitted");
        exit;
    }

    $status = 'Present';
    $notes = "Present: $shift";
    $save_explanation = null;

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

if (isset($_POST['check_out'])) {
    csrf_verify();
    $current_time = date('H:i:s');
    $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE user_id = ? AND date = ?");
    $stmt->execute([$current_time, $user_id, $today]);
    header("Location: attendance.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$attendance = $stmt->fetch();

$is_leave = $attendance && in_array($attendance['status'], ['Leave', 'Sick', 'Others', 'Absent', 'Forgot']);

$can_input_absence = ($current_time < '23:59:59') &&
    (!$attendance || empty($attendance['status']) ||
        in_array($attendance['status'], ['', null]));

$month = $_GET['month'] ?? date('Y-m');
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ?");
$totalStmt->execute([$user_id, $start, $end]);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

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
    <title>Vorta Productivity Tracker - Attendance</title>
    <link rel="stylesheet" href="css/output.css">
</head>

<body>
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Attendance Today (<?= date('d F Y') ?>)</h1>
                        <p class="text-sm text-gray-600 mt-1">
                            Absence reason submission deadline: Today until <strong>23:59</strong>
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <span class="inline-block px-4 py-2 bg-gray-100 text-gray-800 text-sm font-medium rounded-full whitespace-nowrap">
                            You are: <strong><?= $isIntern ? 'Internship' : 'Full-time Employee' ?></strong>
                        </span>

                        <?php if ($can_input_absence): ?>
                            <button type="button" id="btnAbsenceReason"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition whitespace-nowrap hover:opacity-85 hover:cursor-pointer"
                                style="background-color: #9333ea; color: white;">
                                Input Absence Reason
                            </button>

                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 p-4 bg-green-50 text-green-700 rounded-lg text-sm">
                        <?= htmlspecialchars(
                            $_GET['success'] === 'absence_reason_submitted' ? 'Absence reason submitted successfully.' :
                                'Action completed successfully.'
                        ) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg text-sm">
                        <?= htmlspecialchars(
                            $_GET['error'] === 'missing_data' ? 'Incomplete data.' : ($_GET['error'] === 'already_checked_in' ? 'You have already checked in, cannot request leave.' : ($_GET['error'] === 'leave_already_submitted' ? 'You have already requested leave, cannot check in.' : ($_GET['error'] === 'attendance_already_submitted' ? 'You have already submitted attendance for today.' : ($_GET['error'] === 'explanation_required' ? 'A reason for lateness is required (min. 10 characters).' : ($_GET['error'] === 'invalid_date' ? 'You can only submit an absence reason for today.' : ($_GET['error'] === 'time_expired' ? 'The absence reason submission deadline has passed (23:59).' : ($_GET['error'] === 'already_attended' ? 'You have already submitted attendance for that date.' :
                                'Unknown error.')))))))
                        ) ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <button type="button" id="btnCheckIn"
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'disabled' : '' ?>
                        class="w-full py-3 px-4
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'bg-gray-300 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700' ?>
                        text-white font-medium rounded-lg transition">
                        <?= $attendance && $attendance['check_in'] ? 'Checked-in (' . $attendance['check_in'] . ')' : ($is_leave ? 'Attendance Submitted' : 'Check-in') ?>
                    </button>

                    <form method="post" id="checkoutForm" class="flex-1">
                        <?= csrf_field() ?>
                        <button type="button" id="btnCheckout"
                            class="w-full py-3 px-4
                            <?= !$attendance || !$attendance['check_in'] || $attendance['check_out'] ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700' ?>
                            text-white font-medium rounded-lg transition"
                            <?= !$attendance || !$attendance['check_in'] || $attendance['check_out'] ? 'disabled' : '' ?>>
                            <?= $attendance && $attendance['check_out'] ? 'Checked-out (' . $attendance['check_out'] . ')' : 'Check-out' ?>
                        </button>
                        <input type="hidden" name="check_out" value="1">
                    </form>

                    <button id="btnLeave" type="button"
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'disabled' : '' ?>
                        class="w-full py-3 px-4
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'bg-gray-300 cursor-not-allowed' : 'bg-yellow-500 hover:bg-yellow-600' ?>
                        text-white font-medium rounded-lg transition">
                        <?= ($attendance && $attendance['check_in']) || $is_leave ? 'Already Action Taken' : 'Request Leave' ?>
                    </button>
                </div>

                <?php if ($attendance): ?>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-6">
                        <?php
                        $todayStatusLabel = $attendance['status'] ?: 'Unknown';
                        $todayStatusClass = match ($attendance['status']) {
                            'Present' => 'text-green-600',
                            'Late' => 'text-yellow-600',
                            'Leave', 'Sick', 'Others' => 'text-blue-600',
                            'Absent', 'Forgot' => 'text-red-600',
                            default => 'text-gray-500',
                        };
                        ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="font-medium <?= $todayStatusClass ?>">
                                <?= htmlspecialchars($todayStatusLabel) ?>
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

        <div id="modalAbsenceReason" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Input Absence Reason</h2>
                <p class="text-sm text-gray-600 mb-4">
                    <strong>Deadline:</strong> Today until <strong>23:59</strong><br>
                    <strong>Date:</strong> <?= date('d F Y') ?>
                </p>
                <form method="post" id="absenceReasonForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="absence_date" value="<?= $today ?>">

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Absence Type *</label>
                        <select name="absence_type" required class="w-full border rounded-lg p-2">
                            <option value="">-- Select Type --</option>
                            <option value="Absent">Absent</option>
                            <option value="Forgot">Forgot to Check In</option>
                            <option value="Sick">Sick</option>
                            <option value="Leave">Leave</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Reason / Explanation *</label>
                        <textarea name="explanation" class="w-full border rounded-lg p-2" rows="3"
                            placeholder="Explain the reason for your absence..." required></textarea>
                        <p class="text-xs text-gray-500 mt-1">Minimum 10 characters</p>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-yellow-800">
                            <strong>Note:</strong> An absence reason can only be submitted on the same day until 23:59. It cannot be submitted for a past day after midnight.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" id="closeModalAbsenceReason" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                        <button type="submit" name="submitAbsenceReason" class="px-4 py-2 bg-green-500 hover:bg-green-700 text-white rounded-lg">Submit</button>
                    </div>
                </form>
            </div>
        </div>


        <div id="modalCheckout" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Confirm Check-out</h2>
                <p class="mb-4">Are you sure you want to check out now?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" id="closeModalCheckout" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                    <button type="button" id="confirmCheckout" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Yes, Check-out</button>
                </div>
            </div>
        </div>

        <div id="modalCheckIn" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Check-in Confirmation</h2>
                <form method="post" id="checkInForm">
                    <?= csrf_field() ?>
                    <p class="text-sm text-gray-600 mb-3">
                        <strong>Morning:</strong> 07:30 - 11:59 | <strong>Afternoon:</strong> 13:00 - 17:30
                    </p>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Shift</label>
                        <select name="shift" required class="w-full border rounded-lg p-2" id="shiftSelect">
                            <option value="">-- Select Shift --</option>
                            <option value="Morning">Morning</option>
                            <option value="Afternoon">Afternoon</option>
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

        <div id="modalLeave" class="fixed inset-0 bg-white/30 backdrop-blur-sm flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Leave Form</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Type of Leave</label>
                        <select name="leave_type" required class="w-full border rounded-lg p-2">
                            <option value="">-- Select Type --</option>
                            <option value="Sick">Sick / Illness</option>
                            <option value="Leave">Leave</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Explanation / Reason</label>
                        <textarea name="explanation" class="w-full border rounded-lg p-2" rows="3" placeholder="e.g., High fever, family event" required></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" id="closeModalLeave" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                        <button type="submit" name="submitLeave" class="px-4 py-2 bg-yellow-500 text-white rounded-lg">Submit</button>
                    </div>
                </form>
            </div>
        </div>

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
                                        <?php
                                        $recordStatusLabel = $record['status'] ?: 'Unknown';
                                        $recordStatusClass = match ($record['status']) {
                                            'Present' => 'bg-green-100 text-green-800',
                                            'Late' => 'bg-yellow-100 text-yellow-800',
                                            'Leave', 'Sick', 'Others' => 'bg-blue-100 text-blue-800',
                                            'Absent', 'Forgot' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                        ?>
                                        <td class="py-4">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $recordStatusClass ?>">
                                                <?= htmlspecialchars($recordStatusLabel) ?>
                                            </span>
                                        </td>
                                        <td class="py-4"><?= htmlspecialchars($record['location'] ?? '-') ?></td>
                                        <td class="py-4"><?= htmlspecialchars($record['notes'] ?? '-') ?></td>
                                        <td class="py-4 text-sm max-w-[200px] whitespace-nowrap overflow-hidden text-ellipsis relative group">
                                            <span class="block truncate"
                                                title="<?= htmlspecialchars($record['explanation'] ?? '-') ?>">
                                                <?= htmlspecialchars($record['explanation'] ?? '-') ?>
                                            </span>

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

                <?php if ($totalPages > 1): ?>
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
                        <div class="text-sm text-gray-600 whitespace-nowrap">
                            Showing <?= count($monthly) ?> of <?= $total ?> records (Page <?= $page ?> of <?= $totalPages ?>)
                        </div>
                        <nav class="flex flex-wrap justify-center gap-1">
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

                    btnAbsenceReason?.addEventListener('click', () => {
                        modalAbsenceReason.classList.remove('hidden');
                    });

                    closeModalAbsenceReason?.addEventListener('click', () => {
                        modalAbsenceReason.classList.add('hidden');
                    });

                    const absenceReasonForm = document.getElementById('absenceReasonForm');
                    absenceReasonForm?.addEventListener('submit', function(e) {
                        const now = new Date();
                        const hours = now.getHours();
                        const minutes = now.getMinutes();

                        if (hours === 23 && minutes >= 59) {
                            e.preventDefault();
                            alert('The absence reason submission deadline has passed (23:59). You can no longer submit.');
                            return false;
                        }
                    });

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

                    shiftSelect?.addEventListener('change', function() {
                        const currentTime = getCurrentTime();
                        if (isLate(this.value, currentTime)) {
                            explanationContainer.classList.remove('hidden');
                        } else {
                            explanationContainer.classList.add('hidden');
                        }

                        const locationMap = { WFO: 'Office', WAC: 'Client', WFH: 'Home', WFA: 'Anywhere' };
                        const locationInput = document.querySelector('#modalCheckIn input[name="location"]');
                        if (locationInput && locationMap[this.value]) {
                            locationInput.value = locationMap[this.value];
                        }
                    });

                    checkInForm?.addEventListener('submit', function(e) {
                        const shift = shiftSelect.value;
                        const currentTime = getCurrentTime();
                        const explanation = checkInForm.querySelector('textarea[name="explanation"]')?.value.trim();

                        if (isLate(shift, currentTime) && (!explanation || explanation.length < 10)) {
                            e.preventDefault();
                            alert('A reason for lateness is required (minimum 10 characters).');
                        }
                    });

                    btnCheckIn?.addEventListener('click', () => {
                        if (btnCheckIn.disabled) {
                            alert('This action is not available.');
                        } else {
                            modalCheckIn.classList.remove('hidden');
                        }
                    });

                    closeModalCheckIn?.addEventListener('click', () => modalCheckIn.classList.add('hidden'));

                    btnLeave?.addEventListener('click', () => {
                        if (btnLeave.disabled) {
                            alert('This action is not available.');
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