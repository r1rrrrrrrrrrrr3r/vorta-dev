<?php
// update_status_ajax.php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

// Pastikan user login
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user']['user_id'];

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    http_response_code(400);
    exit;
}

$data = $_POST;
$report_id = (int)($data['report_id'] ?? 0);

if ($data['action'] !== 'mark_done') {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
    http_response_code(400);
    exit;
}

try {
    // Cek apakah laporan ada dan milik user
    $stmt = $pdo->prepare("SELECT user_id, status FROM production_reports WHERE report_id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Laporan tidak ditemukan']);
        http_response_code(404);
        exit;
    }

    if ($report['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        http_response_code(403);
        exit;
    }

    if ($report['status'] === 'Selesai') {
        echo json_encode(['success' => true, 'message' => 'Sudah selesai']);
        exit;
    }

    // Update status
    $update = $pdo->prepare("UPDATE production_reports SET status = 'Selesai' WHERE report_id = ?");
    $update->execute([$report_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Status berhasil diubah menjadi Selesai'
    ]);

} catch (Exception $e) {
    error_log("AJAX Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    http_response_code(500);
}
?>