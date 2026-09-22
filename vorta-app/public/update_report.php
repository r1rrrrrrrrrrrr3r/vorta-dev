<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/audit.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_verify();

$user_id = $_SESSION['user']['user_id'];
$company_id = current_company_id();
$report_id = $_POST['report_id'] ?? null;

if (!$report_id) {
    die("Invalid report ID.");
}

$stmt = $pdo->prepare("SELECT * FROM production_reports WHERE report_id = ? AND user_id = ? AND company_id = ?");
$stmt->execute([$report_id, $user_id, $company_id]);
$report = $stmt->fetch();

if (!$report || $report['status'] !== 'Progress') {
    http_response_code(403);
    die("Access denied or report has already been completed.");
}

$report_date = $_POST['report_date'] ?? '';
$job_type = $_POST['job_type'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$status = $_POST['status'] ?? '';
$workforce_id = $_POST['workforce_id'] ?? null;
$proof_link = $_POST['proof_link'] ?? null;
$proof_image = $report['proof_image'];

if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['proof_image'];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1048576 || !is_uploaded_file($file['tmp_name'])) {
        die("Invalid image upload.");
    }
    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        die("Unsupported image format.");
    }
    $image = null;
    if ($mime === 'image/jpeg') {
        $image = @imagecreatefromjpeg($file['tmp_name']);
    } elseif ($mime === 'image/png') {
        $image = @imagecreatefrompng($file['tmp_name']);
    } elseif ($mime === 'image/webp') {
        $image = @imagecreatefromwebp($file['tmp_name']);
    }
    if (!$image) {
        die("Failed to process image.");
    }
    $filename = 'proof_' . bin2hex(random_bytes(12)) . '.jpg';
    $path = __DIR__ . '/../uploads/' . $filename;
    $success = imagejpeg($image, $path, 80);
    imagedestroy($image);
    if (!$success) {
        die("Failed to save image.");
    }
    if ($report['proof_image'] && preg_match('/^[A-Za-z0-9._-]+$/', $report['proof_image'])) {
        $oldPath = __DIR__ . '/../uploads/' . $report['proof_image'];
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }
    $proof_image = $filename;
}

$stmt = $pdo->prepare("UPDATE production_reports SET 
    report_date = ?, job_type = ?, title = ?, description = ?, status = ?, workforce_id = ?, proof_link = ?, proof_image = ?
    WHERE report_id = ? AND user_id = ? AND company_id = ?");

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
    ,$company_id
]);

if ($result) {
    audit_log($pdo, 'report.updated', 'production_reports', $report_id);
    header("Location: my_reports.php?month=" . urlencode(date('Y-m', strtotime($report_date))) . "&edit=success");
} else {
    header("Location: my_reports.php?edit=error");
}
exit;