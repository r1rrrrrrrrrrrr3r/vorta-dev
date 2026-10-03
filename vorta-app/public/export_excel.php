<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/xlsx.php';
require_once __DIR__ . '/../lib/tenant.php';
require_admin();
$company_id = current_company_id();

$recapType = $_GET['recap_type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$notesFilter = $_GET['notes'] ?? '';
$userFilter = $_GET['user_id'] ?? '';

function export_slug(string $value): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $value);
    return trim($slug, '-');
}

if ($recapType === 'daily') {
    $sql = "
        SELECT a.*, u.name, u.email
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.company_id = ? AND u.company_id = a.company_id AND a.date = ?
    ";
    $params = [$company_id, $date];
    if (!empty($notesFilter)) {
        $sql .= " AND a.notes = ?";
        $params[] = $notesFilter;
    }
    $sql .= " ORDER BY a.status, u.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $headers = ['Name', 'Email', 'Check-in', 'Check-out', 'Status', 'Location', 'Notes', 'Explanation'];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['name'],
            $r['email'],
            $r['check_in'] ?? '-',
            $r['check_out'] ?? '-',
            $r['status'],
            $r['location'] ?? '-',
            $r['notes'] ?? '-',
            $r['explanation'] ?? '-',
        ];
    }

    $filename = 'attendance_daily_' . $date;
    if (!empty($notesFilter)) {
        $filename .= '_' . export_slug($notesFilter);
    }

    xlsx_download($filename . '.xlsx', $headers, $data, 'Daily ' . $date);
}

if ($recapType === 'monthly') {
    $start = $month . "-01";
    $end = date('Y-m-t', strtotime($start));

    $sql = "
        SELECT u.user_id, u.name,
               SUM(CASE
                   WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) <= '07:45:00' THEN 1
                   ELSE 0
               END) as present_shift_morning,
               SUM(CASE
                   WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) > '07:45:00' AND TIME(a.check_in) <= '13:10:00' THEN 1
                   ELSE 0
               END) as present_shift_afternoon,
               SUM(CASE
                   WHEN a.status = 'Present' AND a.notes LIKE 'Present:%' AND TIME(a.check_in) > '13:10:00' THEN 1
                   ELSE 0
               END) as present_invalid,
               SUM(CASE WHEN a.notes LIKE 'Late:%' THEN 1 ELSE 0 END) as late,
               SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
               SUM(CASE WHEN a.status = 'Sick' THEN 1 ELSE 0 END) as sick
        FROM users u
        LEFT JOIN attendance a ON a.user_id = u.user_id AND a.company_id = u.company_id AND a.date BETWEEN ? AND ?
        WHERE u.company_id = ? AND u.is_active = 1
    ";
    $params = [$start, $end, $company_id];
    if (!empty($userFilter)) {
        $sql .= " AND u.user_id = ?";
        $params[] = $userFilter;
    }
    $sql .= " GROUP BY u.user_id ORDER BY u.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $headers = ['Name', 'Present Morning', 'Present Afternoon', 'Present Invalid', 'Late', 'Leave', 'Sick'];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['name'],
            (int) $r['present_shift_morning'],
            (int) $r['present_shift_afternoon'],
            (int) $r['present_invalid'],
            (int) $r['late'],
            (int) $r['leave_count'],
            (int) $r['sick'],
        ];
    }

    $filename = 'attendance_monthly_' . $month;
    if (!empty($userFilter)) {

        $nameStmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ? AND company_id = ?");
        $nameStmt->execute([$userFilter, $company_id]);
        $who = $nameStmt->fetchColumn();
        if ($who) {
            $filename .= '_' . export_slug($who);
        }
    }

    xlsx_download($filename . '.xlsx', $headers, $data, 'Monthly ' . $month);
}

http_response_code(400);
echo "Invalid recap type.";
