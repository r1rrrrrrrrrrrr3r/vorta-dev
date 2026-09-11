<?php
// use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "ALTER TABLE attendance 
                ADD COLUMN shift ENUM('Pagi', 'Siang') DEFAULT NULL
                AFTER location";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec('ALTER TABLE attendance DROP COLUMN shift');
    }
];

?>