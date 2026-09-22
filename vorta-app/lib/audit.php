<?php

function audit_log(PDO $pdo, string $action, ?string $entity = null, $entityId = null, array $meta = []): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (company_id, user_id, action, entity, entity_id, meta_json, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user']['company_id'] ?? null,
            $_SESSION['user']['user_id'] ?? null,
            $action,
            $entity,
            $entityId !== null ? (string) $entityId : null,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
        ]);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}
