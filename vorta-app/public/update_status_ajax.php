<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/audit.php';
require_login();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$company_id = current_company_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    http_response_code(400);
    exit;
}
csrf_verify();

$data = $_POST;
$report_id = (int)($data['report_id'] ?? 0);

if ($data['action'] !== 'mark_done') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    http_response_code(400);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id, status FROM production_reports WHERE report_id = ? AND company_id = ?");
    $stmt->execute([$report_id, $company_id]);
    $report = $stmt->fetch();

    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        http_response_code(404);
        exit;
    }

    if ($report['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        http_response_code(403);
        exit;
    }

    if ($report['status'] === 'Completed') {
        echo json_encode(['success' => true, 'message' => 'Already completed']);
        exit;
    }

    $update = $pdo->prepare("UPDATE production_reports SET status = 'Completed' WHERE report_id = ? AND company_id = ?");
    $update->execute([$report_id, $company_id]);
    audit_log($pdo, 'report.completed', 'production_reports', $report_id);

    echo json_encode([
        'success' => true,
        'message' => 'Status successfully changed to Completed'
    ]);

} catch (Exception $e) {
    error_log("AJAX Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    http_response_code(500);
}