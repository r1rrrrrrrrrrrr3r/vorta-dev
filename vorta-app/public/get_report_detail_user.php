<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/tenant.php';
require_login();
$company_id = current_company_id();

$report_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user']['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        pr.*,
        wf.workforce_name,
        u.name as user_name
    FROM production_reports pr
    LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
    JOIN users u ON u.user_id = pr.user_id AND u.company_id = pr.company_id
    WHERE pr.report_id = ? AND pr.user_id = ? AND pr.company_id = ?
");
$stmt->execute([$report_id, $user_id, $company_id]);
$row = $stmt->fetch();

if (!$row) {
    die('<p class="text-red-600">Report not found or access denied.</p>');
}

echo '<div class="space-y-4">';
echo '<p><strong>Date:</strong> ' . htmlspecialchars($row['report_date']) . '</p>';
echo '<p><strong>Name:</strong> ' . htmlspecialchars($row['user_name']) . '</p>';
echo '<p><strong>Job Type:</strong> ' . htmlspecialchars($row['job_type']) . '</p>';
echo '<p><strong>Title:</strong> ' . htmlspecialchars($row['title']) . '</p>';
echo '<p><strong>Workforce:</strong> ' . htmlspecialchars($row['workforce_name'] ?? '-') . '</p>';
echo '<p><strong>Description:</strong> ' . nl2br(htmlspecialchars($row['description'] ?? '')) . '</p>';
echo '<p><strong>Status:</strong> ' . htmlspecialchars($row['status']) . '</p>';

 $proofLink = trim((string)($row['proof_link'] ?? ''));
 $proofScheme = strtolower((string)parse_url($proofLink, PHP_URL_SCHEME));
if ($proofLink !== '' && filter_var($proofLink, FILTER_VALIDATE_URL) && in_array($proofScheme, ['http', 'https'], true)) {
    echo '<p><strong>Proof Link:</strong> <a href="' . htmlspecialchars($proofLink, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">' . htmlspecialchars($proofLink, ENT_QUOTES, 'UTF-8') . '</a></p>';
}

if (!empty($row['proof_image'])) {
    echo '<div class="mt-4">';
    echo '<p><strong>Proof Image:</strong></p>';
    echo '<img src="my_reports.php?proof_image=' . (int)$row['report_id'] . '" alt="Proof" class="max-w-full h-auto rounded-lg border border-gray-200 mt-2">';
    echo '</div>';
}

echo '</div>';