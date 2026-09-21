<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = setting_value");
        $stmt->execute(['monthly_target_min', '50']);
        $stmt->execute(['monthly_target_max', '88']);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS app_settings");
    },
];