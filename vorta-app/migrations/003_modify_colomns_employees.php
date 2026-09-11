<?php
use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "ALTER TABLE employees 
                MODIFY phone VARCHAR(20)";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {

    }
];

?>