<?php
// use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS employees (
            employee_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            position VARCHAR(100),
            phone INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS employees");
    }
];
?>