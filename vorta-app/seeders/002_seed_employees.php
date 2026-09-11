<?php
// seeders/003_seed_employees.php

return [
    'run' => function (PDO $pdo) {
        echo "Menjalankan seeder untuk tabel employees (dari data users)...\n";

        // Ambil semua user_id dan name dari tabel users
        $stmtSelectUsers = $pdo->query("
            SELECT user_id, name 
            FROM users 
            WHERE user_id NOT IN (SELECT user_id FROM employees WHERE user_id IS NOT NULL)
            ORDER BY user_id
        ");

        $users = $stmtSelectUsers->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            echo "⚠️  Tidak ada user tersedia atau semua user sudah jadi employee.\n";
            return;
        }

        // Data posisi untuk setiap employee
        $positions = [
            'Internship', 'Internship', 'Internship', 'Internship',
            'Internship', 'Internship', 'Internship', 'Internship',
            'Internship', 'Internship', 'Internship', 'Internship',
            'Internship', 'Internship', 'Internship', 'Internship',
            'Internship', 'Internship'
            // Sesuaikan jumlah dengan jumlah user
        ];

        // Persiapkan statement insert ke employees
        $stmtInsert = $pdo->prepare("
            INSERT INTO employees (name, position, user_id) 
            VALUES (:name, :position, :user_id)
        ");

        $inserted = 0;

        foreach ($users as $index => $user) {
            $position = $positions[$index] ?? 'Internship'; // fallback jika kekurangan data posisi

            try {
                $stmtInsert->execute([
                    'name' => $user['name'],
                    'position' => $position,
                    'user_id' => $user['user_id']
                ]);
                echo "✅ Employee dibuat: {$user['name']} (Position: $position, User ID: $user[user_id])\n";
                $inserted++;
            } catch (PDOException $e) {
                echo "❌ Gagal insert employee untuk {$user['name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Seeder employees selesai. $inserted data berhasil dimasukkan.\n";
    }
];