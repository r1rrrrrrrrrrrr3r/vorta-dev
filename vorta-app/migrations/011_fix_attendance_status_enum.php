<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE attendance
                    MODIFY status ENUM('Present','Late','Leave','Sick','Others','Absent','Forgot','Alpa')
                    DEFAULT 'Alpa'");

        $pdo->exec("UPDATE attendance SET status = 'Present' WHERE status = '' AND notes LIKE 'Hadir:%'");
        $pdo->exec("UPDATE attendance SET status = 'Late'    WHERE status = '' AND notes LIKE 'Late:%'");
        $pdo->exec("UPDATE attendance SET status = 'Absent'  WHERE status = '' AND notes LIKE 'Absence Reason: Absent%'");
        $pdo->exec("UPDATE attendance SET status = 'Forgot'  WHERE status = '' AND notes LIKE 'Absence Reason: Forgot%'");
        $pdo->exec("UPDATE attendance SET status = 'Sick'    WHERE status = '' AND (notes LIKE 'Absence Reason: Sick%' OR notes LIKE 'Sick request%')");
        $pdo->exec("UPDATE attendance SET status = 'Leave'   WHERE status = '' AND (notes LIKE 'Absence Reason: Leave%' OR notes LIKE 'Leave request%')");
        $pdo->exec("UPDATE attendance SET status = 'Others'  WHERE status = '' AND (notes LIKE 'Absence Reason: Others%' OR notes LIKE 'Others request%')");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE attendance
                    MODIFY status ENUM('Hadir','Telat','Izin','Sakit','Alpa')
                    DEFAULT 'Alpa'");
    }
];