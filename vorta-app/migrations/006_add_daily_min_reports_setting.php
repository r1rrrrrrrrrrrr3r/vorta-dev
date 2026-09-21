<?php

return [
    'up' => function (PDO $pdo) {
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = setting_value");
        $stmt->execute(['daily_min_reports', '2']);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DELETE FROM app_settings WHERE setting_key = 'daily_min_reports'");
    },
];