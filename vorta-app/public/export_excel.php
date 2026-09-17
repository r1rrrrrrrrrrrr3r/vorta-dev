<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_admin();

$recapType = $_GET['recap_type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$notesFilter = $_GET['notes'] ?? '';
$userFilter = $_GET['user_id'] ?? '';

if ($recapType === 'daily') {
    $sql = "
        SELECT a.*, u.name, u.email
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.date = ?
    ";
    $params = [$date];
    if (!empty($notesFilter)) {
        $sql .= " AND a.notes = ?";
        $params[] = $notesFilter;
    }
    $sql .= " ORDER BY a.status, u.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $filename = "attendance_daily_{$date}.xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Email</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Location</th><th>Notes</th><th>Explanation</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($r['name']) . "</td>";
        echo "<td>" . htmlspecialchars($r['email']) . "</td>";
        echo "<td>" . htmlspecialchars($r['check_in'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($r['check_out'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($r['status']) . "</td>";
        echo "<td>" . htmlspecialchars($r['location'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($r['notes'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($r['explanation'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
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
        LEFT JOIN attendance a ON a.user_id = u.user_id AND a.date BETWEEN ? AND ?
        WHERE u.is_active = 1
    ";
    $params = [$start, $end];
    if (!empty($userFilter)) {
        $sql .= " AND u.user_id = ?";
        $params[] = $userFilter;
    }
    $sql .= " GROUP BY u.user_id ORDER BY u.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $filename = "attendance_monthly_{$month}.xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Present Morning</th><th>Present Afternoon</th><th>Present Invalid</th><th>Late</th><th>Leave</th><th>Sick</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($r['name']) . "</td>";
        echo "<td>" . (int)$r['present_shift_morning'] . "</td>";
        echo "<td>" . (int)$r['present_shift_afternoon'] . "</td>";
        echo "<td>" . (int)$r['present_invalid'] . "</td>";
        echo "<td>" . (int)$r['late'] . "</td>";
        echo "<td>" . (int)$r['leave_count'] . "</td>";
        echo "<td>" . (int)$r['sick'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

http_response_code(400);
echo "Invalid recap type.";