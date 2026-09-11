<?php
// seed_work_force.php

return [
    'run' => function (PDO $pdo) {
        echo "Menjalankan seeder untuk tabel work_force...\n";

        $data = [
            ['workforce_name' => 'Jasa Raharja'],
            ['workforce_name' => 'Hildiktipari'],
            ['workforce_name' => 'Pasifik'],
            ['workforce_name' => 'Inare'],
            ['workforce_name' => 'Antara'],
            ['workforce_name' => 'Trade Finance'],
            ['workforce_name' => 'CasaMedika'],
        ];

        $stmt = $pdo->prepare("INSERT INTO work_force (workforce_name) VALUES (:workforce_name)");

        foreach ($data as $row) {
            try {
                $stmt->execute($row);
            } catch (Exception $e) {
                echo "Gagal insert workforce {$row['workforce_name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Seeder work_force selesai.\n";
    }
];
