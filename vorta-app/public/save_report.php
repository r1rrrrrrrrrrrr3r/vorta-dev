<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$company_id = current_company_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Access denied.");
}
csrf_verify();

$report_date = $_POST['report_date'] ?? date('Y-m-d');
$job_type_id = (int)$_POST['job_type'];
$status = $_POST['status'] ?? 'Progress';
$title = trim($_POST['title']);
$description = trim($_POST['description']) ?: null;
$proof_link = trim($_POST['proof_link']) ?: null;
$workforce_id = (int)$_POST['workforce_id'];

if (empty($title) || empty($report_date) || $job_type_id <= 0) {
    die("Incomplete data.");
}

$stmt_lookup = $pdo->prepare("SELECT name FROM job_type WHERE job_type_id = ? AND company_id = ?");
$stmt_lookup->execute([$job_type_id, $company_id]);
$job_type_row = $stmt_lookup->fetch();

if (!$job_type_row) {
    die("Invalid job type.");
}
$job_type = $job_type_row['name'];

$employeeStmt = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND company_id = ?");
$employeeStmt->execute([$user_id, $company_id]);
$employee = $employeeStmt->fetch();

if (!$employee) {
    die("Employee data not found.");
}

$employee_id = $employee['employee_id'];

$check = $pdo->prepare("SELECT 1 FROM work_force WHERE workforce_id = ? AND company_id = ?");
$check->execute([$workforce_id, $company_id]);
if (!$check->fetch()) {
    die("Invalid workforce.");
}

$proof_image_path = null;
$max_size = 1048576;

if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed, PHP error code: " . $_FILES['proof_image']['error']);
    }
    $file = $_FILES['proof_image'];

    if ($file['size'] > $max_size) {
        die("Image file size must not exceed 1 MB.");
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed_types)) {
        die("Unsupported image format. Use JPG, PNG, or WebP.");
    }

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'report_' . time() . '_' . uniqid() . '.' . ($mime === 'image/webp' ? 'webp' : ($mime === 'image/png' ? 'png' : 'jpg'));
    $target_path = $upload_dir . $new_filename;

    $success = false;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            $success = imagejpeg($image, $target_path, 80);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $success = imagejpeg($bg, $target_path, 80);
            imagedestroy($bg);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($file['tmp_name']);
            $success = imagejpeg($image, $target_path, 80);
            break;
    }

    if (isset($image)) imagedestroy($image);

    if (!$success) {
        die("Failed to process image.");
    }

    $proof_image_path = '../uploads/' . $new_filename;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO production_reports 
        (company_id, user_id, report_date, job_type, title, description, status, proof_link, proof_image, workforce_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $company_id,
        $user_id,
        $report_date,
        $job_type,
        $title,
        $description,
        $status,
        $proof_link,
        $proof_image_path,
        $workforce_id 
    ]);

    header("Location: my_reports.php?success=report_saved");
    exit;
} catch (PDOException $e) {
    die("Failed to save report: " . $e->getMessage());
}