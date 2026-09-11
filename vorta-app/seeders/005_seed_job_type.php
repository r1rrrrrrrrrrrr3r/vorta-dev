<?php
// seed_job_type.php

return [
    'run' => function (PDO $pdo) {
        echo "Menjalankan seeder untuk tabel job_type...\n";

        $data = [
            ['name' => 'Program'],
            ['name' => 'Website'],
            ['name' => 'Mobile Apps'],
            ['name' => 'Training Materi'],
            ['name' => 'Mengajar'],
            ['name' => 'QA/Testing'],
            ['name' => 'UI/UX'],
            ['name' => 'DevOps'],
            ['name' => 'Dokumentasi'],
        ];

        $stmt = $pdo->prepare("INSERT INTO job_type (name) VALUES (:name)");

        foreach ($data as $row) {
            try {
                $stmt->execute($row);
            } catch (Exception $e) {
                echo "Gagal insert job_type {$row['name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Seeder job_type selesai.\n";
    }
];
