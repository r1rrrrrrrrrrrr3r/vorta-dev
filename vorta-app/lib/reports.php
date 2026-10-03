<?php
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/uploads.php';

function report_detail_fetch(PDO $pdo, int $reportId, ?int $ownerId): ?array
{
    $sql = "SELECT pr.*, u.name AS user_name, wf.workforce_name
            FROM production_reports pr
            LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
            JOIN users u ON u.user_id = pr.user_id AND u.company_id = pr.company_id
            WHERE pr.report_id = ? AND pr.company_id = ?";
    $params = [$reportId, current_company_id()];
    if ($ownerId !== null) {
        $sql .= " AND pr.user_id = ?";
        $params[] = $ownerId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetch();
    return $found ?: null;
}

/** Stream the proof image of a report (handler for ?proof_image=). Always exits. */
function report_proof_image_output(PDO $pdo, int $reportId, ?int $ownerId): never
{
    $row = report_detail_fetch($pdo, $reportId, $ownerId);
    $path = $row ? upload_absolute_path((string) $row['proof_image']) : null;
    $mime = $path ? mime_content_type($path) : false;
    if (!$mime || strpos($mime, 'image/') !== 0) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

/** Render the detail fragment (handler for ?detail=). Always exits. */
function report_detail_output(PDO $pdo, int $reportId, ?int $ownerId, string $selfUrl): never
{
    require_once __DIR__ . '/ui.php';
    $row = report_detail_fetch($pdo, $reportId, $ownerId);
    if (!$row) {
        http_response_code(404);
        echo alert_box('bad', 'Report not found or access denied.');
        exit;
    }
    $detailSelf = $selfUrl;
    include __DIR__ . '/../views/reports/detail.php';
    exit;
}

/** Daily completion for active non-admin users on $date. */
function report_daily_completion_stats(PDO $pdo, string $date, int $dailyMin): array
{
    $statsStmt = $pdo->prepare("
      SELECT
        COUNT(*) AS total_active_users,
        COALESCE(SUM(CASE WHEN report_count >= ? THEN 1 ELSE 0 END), 0) AS completed_users,
        COALESCE(SUM(CASE WHEN report_count > 0 AND report_count < ? THEN 1 ELSE 0 END), 0) AS partial_users,
        COALESCE(SUM(CASE WHEN report_count = 0 THEN 1 ELSE 0 END), 0) AS zero_users
      FROM (
        SELECT
          u.user_id,
          COUNT(pr.report_id) AS report_count
        FROM users u
        LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.company_id = u.company_id AND pr.report_date = ?
        WHERE u.company_id = ? AND u.is_active = 1 AND u.role <> 'admin'
        GROUP BY u.user_id
      ) AS user_reports
    ");
    $statsStmt->execute([$dailyMin, $dailyMin, $date, current_company_id()]);
    $s = $statsStmt->fetch() ?: [];
    return [
        'total'    => (int) ($s['total_active_users'] ?? 0),
        'complete' => (int) ($s['completed_users'] ?? 0),
        'partial'  => (int) ($s['partial_users'] ?? 0),
        'none'     => (int) ($s['zero_users'] ?? 0),
    ];
}

/** Map 'Y-m-d' => count of reports for a user between $start and $end. */
function report_daily_counts(PDO $pdo, int $userId, string $start, string $end): array
{
    $stmt = $pdo->prepare("SELECT DATE(report_date) d, COUNT(*) c
                           FROM production_reports
                           WHERE user_id = ? AND company_id = ? AND report_date BETWEEN ? AND ?
                           GROUP BY DATE(report_date)");
    $stmt->execute([$userId, current_company_id(), $start, $end]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['d']] = (int) $r['c'];
    }
    return $out;
}

function report_count_on(PDO $pdo, int $userId, string $date): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM production_reports WHERE user_id = ? AND company_id = ? AND report_date = ?");
    $stmt->execute([$userId, current_company_id(), $date]);
    return (int) $stmt->fetchColumn();
}
