<?php

function settings_get(PDO $pdo, string $key, $default = null)
{
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
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

function settings_save(PDO $pdo, array $values): array
{
    $min = isset($values['monthly_target_min']) ? (int) $values['monthly_target_min'] : null;
    $max = isset($values['monthly_target_max']) ? (int) $values['monthly_target_max'] : null;

    if ($min === null || $max === null) {
        return ['ok' => false, 'message' => 'Minimum dan maksimum target wajib diisi.'];
    }
    if ($min <= 0 || $max <= 0) {
        return ['ok' => false, 'message' => 'Nilai target harus lebih dari 0.'];
    }
    if ($min > $max) {
        return ['ok' => false, 'message' => 'Minimum tidak boleh lebih besar dari maksimum.'];
    }

    $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['monthly_target_min', (string) $min]);
    $stmt->execute(['monthly_target_max', (string) $max]);

    return ['ok' => true, 'message' => 'Monthly target berhasil diupdate.'];
}