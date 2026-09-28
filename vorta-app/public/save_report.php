<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/uploads.php';
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
$allowedStatuses = ['Progress', 'Completed'];

if (empty($title) || empty($report_date) || $job_type_id <= 0 || !in_array($status, $allowedStatuses, true)) {
    die("Incomplete data.");
}
if ($proof_link !== null && !filter_var($proof_link, FILTER_VALIDATE_URL)) {
    die("Proof link must be a valid URL.");
}
$proofScheme = $proof_link !== null ? strtolower((string)parse_url($proof_link, PHP_URL_SCHEME)) : '';
if ($proof_link !== null && !in_array($proofScheme, ['http', 'https'], true)) {
    die("Proof link must use HTTP or HTTPS.");
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
if (isset($_FILES['proof_image'])) {
    $upload = upload_save_image($_FILES['proof_image'], $company_id);
    if (!$upload['ok']) {
        die($upload['message']);
    }
    $proof_image_path = $upload['path'];
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
    audit_log($pdo, 'report.created', 'production_reports', $pdo->lastInsertId(), [
        'workforce_id' => $workforce_id,
    ]);

    header("Location: my_reports.php?success=report_saved");
    exit;
} catch (PDOException $e) {
    error_log('Failed to save report: ' . $e->getMessage());
    http_response_code(500);
    die("Failed to save report.");
}