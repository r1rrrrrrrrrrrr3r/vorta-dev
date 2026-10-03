<?php

const ATTENDANCE_AWAY_STATUSES = ['Leave', 'Sick', 'Others', 'Absent', 'Forgot'];

function attendance_messages(): array
{
    return [
        'error' => [
            'missing_data'                 => 'Please fill in all required fields.',
            'already_checked_in'           => "You've already checked in today, so you can't request leave.",
            'leave_already_submitted'      => "You've already requested leave today, so you can't check in.",
            'attendance_already_submitted' => 'Your attendance for today is already recorded.',
            'explanation_required'         => 'You checked in late. Please give a reason of at least 10 characters.',
            'invalid_date'                 => 'You can only report an absence for today.',
            'time_expired'                 => "The deadline for today's absence report (23:59) has passed.",
            'already_attended'             => 'Your attendance for that date is already recorded.',
        ],
        'success' => [
            'absence_reason_submitted' => 'Absence reported.',
        ],
    ];
}

/** Alert HTML for ?error= / ?success= on attendance-related pages. */
function attendance_alert_html(): string
{
    $m = attendance_messages();
    if (isset($_GET['error'])) {
        return alert_box('bad', $m['error'][(string) $_GET['error']] ?? 'Something went wrong. Try again.');
    }
    if (isset($_GET['success'])) {
        return alert_box('ok', $m['success'][(string) $_GET['success']] ?? 'Done.');
    }
    return '';
}

/** Redirect back to the page the attendance form came from. */
function attendance_redirect(string $query = ''): never
{
    $allowed = ['attendance.php', 'dashboard.php'];
    $target = $_POST['return_to'] ?? '';
    if (!in_array($target, $allowed, true)) {
        $target = 'attendance.php';
    }
    header('Location: ' . $target . $query);
    exit;
}

/** 'none' | 'checked_in' | 'checked_out' | 'away' */
function attendance_state(?array $row): string
{
    if (!$row) return 'none';
    if (in_array($row['status'] ?? '', ATTENDANCE_AWAY_STATUSES, true)) return 'away';
    if (!empty($row['check_in']) && empty($row['check_out'])) return 'checked_in';
    if (!empty($row['check_in']) && !empty($row['check_out'])) return 'checked_out';
    return 'none';
}

/** Staff (non-admin with employee row) without attendance record on $date. Same query as admin "Not checked in". */
function attendance_missing_count(PDO $pdo, string $date): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        JOIN employees e ON u.user_id = e.user_id
        LEFT JOIN attendance a ON u.user_id = a.user_id AND a.date = ?
        WHERE u.role != 'admin'
          AND a.user_id IS NULL
    ");
    $stmt->execute([$date]);
    return (int) $stmt->fetchColumn();
}

/** Can the user still submit an absence reason today (same rule as attendance.php). */
function attendance_can_input_absence(?array $row): bool
{
    return (date('H:i:s') < '23:59:59') &&
        (!$row || empty($row['status']) || in_array($row['status'], ['', null]));
}

function attendance_is_intern(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT position FROM employees WHERE user_id = ?");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch();
    if (!$employee) return false;
    $position = strtolower(trim((string) $employee['position']));
    return in_array($position, ['internship', 'intern']);
}
