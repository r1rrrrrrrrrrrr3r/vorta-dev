<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
csrf_verify();

$user_id = $_SESSION['user']['user_id'];
$report_id = $_POST['report_id'] ?? null;

if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM production_reports WHERE report_id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch();

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

if ($report['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this report']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM production_reports WHERE report_id = ?");
$result = $stmt->execute([$report_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete from database']);
}