<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS account_tokens (
                token_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                purpose ENUM('password_reset') NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_account_tokens_user (user_id),
                CONSTRAINT fk_account_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS account_tokens");
    },
];
