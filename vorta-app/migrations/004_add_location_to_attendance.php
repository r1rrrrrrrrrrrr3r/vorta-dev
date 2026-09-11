<?php
// migrations/004_add_location_to_attendance.php

use PDO;

return [
    'up' => function (PDO $pdo) {
        // Tambahkan kolom location setelah check_out
        $pdo->exec("ALTER TABLE attendance 
                    ADD COLUMN location VARCHAR(100) NOT NULL 
                    AFTER status");
        
        // Opsional: Tambahkan index jika ingin pencarian lebih cepat
        // $pdo->exec("CREATE INDEX idx_location ON attendance(location)");
    },

    'down' => function (PDO $pdo) {
        // Hapus kolom location (untuk rollback)
        // $pdo->exec("ALTER TABLE attendance 
        //             DROP COLUMN location");
    }
];

?>