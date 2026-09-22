<?php

return [
    'up' => function (PDO $pdo) {
        $companies = $pdo->query("SELECT company_id FROM companies")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($companies as $companyId) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO work_force (company_id, workforce_name) VALUES (?, 'General')");
            $stmt->execute([$companyId]);
            $stmt = $pdo->prepare("INSERT IGNORE INTO job_type (company_id, name) VALUES (?, 'General')");
            $stmt->execute([$companyId]);
            $stmt = $pdo->prepare("INSERT INTO app_settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = setting_value");
            $stmt->execute([$companyId, 'monthly_target_min', '50']);
            $stmt->execute([$companyId, 'monthly_target_max', '88']);
            $stmt->execute([$companyId, 'daily_min_reports', '2']);
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DELETE FROM app_settings WHERE setting_key IN ('monthly_target_min', 'monthly_target_max', 'daily_min_reports')");
    },
];
