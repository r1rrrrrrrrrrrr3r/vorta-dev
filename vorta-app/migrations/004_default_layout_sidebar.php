<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE users
            MODIFY COLUMN nav_layout ENUM('navbar','sidebar') NOT NULL DEFAULT 'sidebar'");
        $pdo->exec("UPDATE users SET nav_layout = 'sidebar' WHERE nav_layout = 'navbar'");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE users
            MODIFY COLUMN nav_layout ENUM('navbar','sidebar') NOT NULL DEFAULT 'navbar'");
    },
];
