<?php

function settings_get(PDO $pdo, string $key, $default = null)
{
    $companyId = (int)($_SESSION['user']['company_id'] ?? 1);
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE company_id = ? AND setting_key = ?");
    $stmt->execute([$companyId, $key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function settings_get_monthly_target(PDO $pdo): array
{
    return [
        'min' => (int) settings_get($pdo, 'monthly_target_min', 50),
        'max' => (int) settings_get($pdo, 'monthly_target_max', 88),
    ];
}

function settings_get_daily_min_reports(PDO $pdo): int
{
    return (int) settings_get($pdo, 'daily_min_reports', 2);
}

function settings_save(PDO $pdo, array $values): array
{
    $companyId = (int)($_SESSION['user']['company_id'] ?? 1);
    $min = isset($values['monthly_target_min']) ? (int) $values['monthly_target_min'] : null;
    $max = isset($values['monthly_target_max']) ? (int) $values['monthly_target_max'] : null;
    $dailyMin = isset($values['daily_min_reports']) ? (int) $values['daily_min_reports'] : null;

    if ($min === null || $max === null || $dailyMin === null) {
        return ['ok' => false, 'message' => 'Semua field target wajib diisi.'];
    }
    if ($min <= 0 || $max <= 0 || $dailyMin <= 0) {
        return ['ok' => false, 'message' => 'Nilai target harus lebih dari 0.'];
    }
    if ($min > $max) {
        return ['ok' => false, 'message' => 'Minimum tidak boleh lebih besar dari maksimum.'];
    }

    $stmt = $pdo->prepare("INSERT INTO app_settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$companyId, 'monthly_target_min', (string) $min]);
    $stmt->execute([$companyId, 'monthly_target_max', (string) $max]);
    $stmt->execute([$companyId, 'daily_min_reports', (string) $dailyMin]);

    return ['ok' => true, 'message' => 'Monthly target berhasil diupdate.'];
}