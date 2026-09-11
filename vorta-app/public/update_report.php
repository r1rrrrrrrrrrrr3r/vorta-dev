<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$report_id = $_POST['report_id'] ?? null;

if (!$report_id) {
    die("ID laporan tidak valid.");
}

// Cek apakah laporan milik user dan masih progress
$stmt = $pdo->prepare("SELECT * FROM production_reports WHERE report_id = ? AND user_id = ?");
$stmt->execute([$report_id, $user_id]);
$report = $stmt->fetch();

if (!$report || $report['status'] !== 'Progress') {
    http_response_code(403);
    die("Akses ditolak atau laporan sudah selesai.");
}

// Ambil data form
$report_date = $_POST['report_date'] ?? '';
$job_type = $_POST['job_type'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$status = $_POST['status'] ?? '';
$workforce_id = $_POST['workforce_id'] ?? null;
$proof_link = $_POST['proof_link'] ?? null;
$proof_image = $report['proof_image']; // Pertahankan gambar lama

// Upload gambar baru jika ada
if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed)) {
        $filename = uniqid('proof_') . '.' . $ext;
        $path = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $path)) {
            // Hapus gambar lama jika ada
            if ($report['proof_image'] && file_exists(__DIR__ . '/../uploads/' . $report['proof_image'])) {
                unlink(__DIR__ . '/../uploads/' . $report['proof_image']);
            }
            $proof_image = $filename;
        }
    } else {
        die("Format gambar tidak didukung.");
    }
}

// Update database
$stmt = $pdo->prepare("UPDATE production_reports SET 
    report_date = ?, job_type = ?, title = ?, description = ?, status = ?, workforce_id = ?, proof_link = ?, proof_image = ?
    WHERE report_id = ? AND user_id = ?");

$result = $stmt->execute([
    $report_date,
    $job_type,
    $title,
    $description,
    $status,
    $workforce_id,
    $proof_link,
    $proof_image,
    $report_id,
    $user_id
]);

if ($result) {
    header("Location: reports_my.php?month=" . urlencode(date('Y-m', strtotime($report_date))) . "&edit=success");
} else {
    header("Location: reports_my.php?edit=error");
}
exit;