<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS company_invitations (
                invitation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                email VARCHAR(120) NOT NULL,
                role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
                token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                accepted_at DATETIME NULL,
                created_by INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_invites_email (email),
                CONSTRAINT fk_invites_company FOREIGN KEY (company_id) REFERENCES companies(company_id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_invites_creator FOREIGN KEY (created_by) REFERENCES users(user_id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS company_invitations");
    },
];
