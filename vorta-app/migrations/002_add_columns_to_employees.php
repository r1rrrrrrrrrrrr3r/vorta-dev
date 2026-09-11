<?php
use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "ALTER TABLE employees 
                MODIFY position ENUM('Employe', 'Internship') NOT NULL";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {

    }
];

?>