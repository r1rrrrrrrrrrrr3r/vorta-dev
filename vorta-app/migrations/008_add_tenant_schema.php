<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->beginTransaction();
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS companies (
                    company_id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    slug VARCHAR(150) NOT NULL UNIQUE,
                    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
                    locale VARCHAR(10) NOT NULL DEFAULT 'en',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                INSERT INTO companies (name, slug)
                SELECT 'Default Company', 'default'
                WHERE NOT EXISTS (SELECT 1 FROM companies WHERE slug = 'default')
            ");
            $companyId = (int)$pdo->query("SELECT company_id FROM companies WHERE slug = 'default' LIMIT 1")->fetchColumn();

            $pdo->exec("ALTER TABLE users MODIFY role ENUM('platform_admin','admin','manager','staff') NOT NULL DEFAULT 'staff'");

            $tables = ['users', 'work_force', 'job_type', 'employees', 'production_reports', 'attendance', 'app_settings'];
            foreach ($tables as $table) {
                $columnExists = $pdo->prepare("
                    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'company_id'
                ");
                $columnExists->execute([$table]);
                if (!(int)$columnExists->fetchColumn()) {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN company_id INT NULL");
                }
                $pdo->exec("UPDATE `$table` SET company_id = " . $companyId . " WHERE company_id IS NULL");
                $pdo->exec("ALTER TABLE `$table` MODIFY company_id INT NOT NULL DEFAULT " . $companyId);
                $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_{$table}_company` (company_id)");
                $pdo->exec("
                    ALTER TABLE `$table`
                    ADD CONSTRAINT `fk_{$table}_company`
                    FOREIGN KEY (company_id) REFERENCES companies(company_id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
                ");
            }
            $pdo->exec("ALTER TABLE app_settings DROP PRIMARY KEY, ADD PRIMARY KEY (company_id, setting_key)");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS audit_log (
                    audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    company_id INT NOT NULL,
                    user_id INT NULL,
                    action VARCHAR(100) NOT NULL,
                    entity VARCHAR(100) NULL,
                    entity_id VARCHAR(64) NULL,
                    meta_json JSON NULL,
                    ip VARCHAR(45) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_audit_company_created (company_id, created_at),
                    CONSTRAINT fk_audit_company FOREIGN KEY (company_id) REFERENCES companies(company_id)
                        ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id)
                        ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS audit_log");
        foreach (['app_settings', 'attendance', 'production_reports', 'employees', 'job_type', 'work_force', 'users'] as $table) {
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `fk_{$table}_company`");
            $pdo->exec("ALTER TABLE `$table` DROP INDEX `idx_{$table}_company`, DROP COLUMN company_id");
        }
        $pdo->exec("DROP TABLE IF EXISTS companies");
    },
];
