<?php
// use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS job_type (
            job_type_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS job_type");
    }
];
?>