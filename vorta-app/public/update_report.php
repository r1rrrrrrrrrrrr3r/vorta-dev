<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/uploads.php';
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
$allowedStatuses = ['Progress', 'Completed'];

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Invalid report status.');
}
$jobTypeStmt = $pdo->prepare('SELECT name FROM job_type WHERE name = ? AND company_id = ? LIMIT 1');
$jobTypeStmt->execute([trim((string)$job_type), $company_id]);
if (!$jobTypeStmt->fetch()) {
    http_response_code(400);
    exit('Invalid job type.');
}
$workforceStmt = $pdo->prepare('SELECT workforce_id FROM work_force WHERE workforce_id = ? AND company_id = ?');
$workforceStmt->execute([(int)$workforce_id, $company_id]);
if (!$workforceStmt->fetch()) {
    http_response_code(400);
    exit('Invalid workforce.');
}
$proof_link = trim((string)$proof_link) ?: null;
if ($proof_link !== null) {
    $scheme = strtolower((string)parse_url($proof_link, PHP_URL_SCHEME));
    if (!filter_var($proof_link, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        http_response_code(400);
        exit('Proof link must use HTTP or HTTPS.');
    }
}

if (isset($_FILES['proof_image'])) {
    $upload = upload_save_image($_FILES['proof_image'], $company_id);
    if (!$upload['ok']) {
        die($upload['message']);
    }
    if ($upload['path'] !== null) {
        $proof_image = $upload['path'];
        $oldPath = upload_absolute_path($report['proof_image']);
        if ($oldPath && is_file($oldPath)) {
            unlink($oldPath);
        }
    }
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