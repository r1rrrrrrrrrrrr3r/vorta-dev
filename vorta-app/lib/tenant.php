<?php

function current_company_id(): int
{
    $id = (int)($_SESSION['user']['company_id'] ?? 0);
    if ($id <= 0) {
        http_response_code(403);
        exit('Company context required.');
    }
    return $id;
}

function current_role(): string
{
    return (string)($_SESSION['user']['role'] ?? '');
}

function is_platform_admin(): bool
{
    return current_role() === 'platform_admin';
}

function is_company_admin(): bool
{
    return current_role() === 'admin';
}

function is_manager(): bool
{
    return current_role() === 'manager';
}

function company_load(PDO $pdo, int $companyId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ? LIMIT 1");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function tenant_apply_timezone(): void
{
    $tz = $_SESSION['company']['timezone'] ?? 'Asia/Jakarta';
    try {
        date_default_timezone_set($tz);
    } catch (Throwable $e) {
        date_default_timezone_set('Asia/Jakarta');
    }
}

function manager_workforce_ids(PDO $pdo, int $userId): array
{
    $ids = [];
    $stmt = $pdo->prepare("SELECT workforce_id FROM employees WHERE user_id = ? AND workforce_id IS NOT NULL");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $ids[] = (int) $row['workforce_id'];
    }
    $stmt = $pdo->prepare("
        SELECT ew.workforce_id
        FROM employees_workforce ew
        JOIN employees e ON e.employee_id = ew.employee_id
        WHERE e.user_id = ?
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $ids[] = (int) $row['workforce_id'];
    }
    return array_values(array_unique(array_filter($ids)));
}

function sql_in_placeholders(array $ids): string
{
    return implode(',', array_fill(0, max(count($ids), 1), '?'));
}
