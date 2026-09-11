<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$report_id = $_POST['report_id'] ?? null;

if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Report ID tidak valid']);
    exit;
}

// Cek apakah laporan milik user ini
$stmt = $pdo->prepare("SELECT user_id FROM production_reports WHERE report_id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch();

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Laporan tidak ditemukan']);
    exit;
}

if ($report['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak berhak menghapus laporan ini']);
    exit;
}

// Hapus laporan
$stmt = $pdo->prepare("DELETE FROM production_reports WHERE report_id = ?");
$result = $stmt->execute([$report_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Laporan berhasil dihapus']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus dari database']);
}