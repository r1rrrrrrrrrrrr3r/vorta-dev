<?php
// migrations/003_add_ui_preferences.php
// Per-user UI preferences: colour theme and navigation layout.
// Both are also mirrored in localStorage so the choice applies before first paint.

return [
    'up' => function (PDO $pdo) {
        $existing = [];
        foreach ($pdo->query("SHOW COLUMNS FROM users")->fetchAll() as $col) {
            $existing[$col['Field']] = true;
        }
        if (!isset($existing['theme'])) {
            $pdo->exec("ALTER TABLE users
                ADD COLUMN theme ENUM('light','dark','system') NOT NULL DEFAULT 'system' AFTER is_active");
        }
        if (!isset($existing['nav_layout'])) {
            $pdo->exec("ALTER TABLE users
                ADD COLUMN nav_layout ENUM('navbar','sidebar') NOT NULL DEFAULT 'navbar' AFTER theme");
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE users DROP COLUMN nav_layout");
        $pdo->exec("ALTER TABLE users DROP COLUMN theme");
    },
];
