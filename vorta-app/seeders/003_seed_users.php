<?php
// seeders/002_seed_users.php

return [
    'run' => function (PDO $pdo) {
        echo "Menjalankan seeder untuk tabel users...\n";

        $data = [
            ['name' => 'Shaquille Raffalea'],
            ['name' => 'Fazle Adrevi Bintang Al Farrel'],
            ['name' => 'Farel Fadlillah'],
            ['name' => 'Bintang Rayvan'],
            ['name' => 'Iqbal Hadi Mustafa'],
            ['name' => 'Zafira Marvella'],
            ['name' => 'Kirana Firjal Atakhira'],
            ['name' => 'Aisyah Ratna Aulia'],
            ['name' => 'Firyal Dema Elputri'],
            ['name' => 'Halena Maheswari Viehandini'],
            ['name' => 'Hannif Fahmy Fadilah'],
            ['name' => 'Kevin Revaldo'],
            ['name' => 'Achmad wafiq risvyan'],
            ['name' => 'Kurniawan yafi Djayakusuma'],
            ['name' => 'Hildan argiansyah'],
            ['name' => 'Joshua Matthew Hendra'],
            ['name' => 'Fadhal nurul azmi'],
            ['name' => 'Rasya al zikri'],
            ['name' => 'Rifki'],
            ['name' => 'Marsya']

        ];

        // Password default (bisa diubah sesuai kebutuhan)
        $defaultPassword = 'password123'; // Ganti dengan yang lebih aman jika perlu

        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role) 
            VALUES (:name, :email, :password_hash, :role)
        ");

        foreach ($data as $person) {
            $name = $person['name'];

            // Buat email dari nama: "Shaquille Raffalea" → shaquille.raffalea@company.com
            $email = strtolower(preg_replace('/\s+/', '.', $name)) . '@vorta.local';

            // Hash password langsung
            $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

            // Role: semua staff
            $role = 'staff';

            try {
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => $role
                ]);
                echo "✅ Berhasil insert: $name ($email)\n";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    echo "⚠️  Duplikat email dilewati: $email\n";
                } else {
                    echo "❌ Gagal insert $name: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "Seeder users selesai. Semua pengguna diberi role 'staff'.\n";
    }
];