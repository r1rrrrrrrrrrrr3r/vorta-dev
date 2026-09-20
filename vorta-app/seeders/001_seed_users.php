<?php

return [
    'run' => function (PDO $pdo) {
        echo "Seeding users...\n";

        $defaultPassword = 'password123';

        $users = [
            ['name' => 'System Administrator', 'email' => 'admin@vorta.local', 'role' => 'admin'],
            ['name' => 'John Doe', 'email' => 'john.doe@vorta.local', 'role' => 'staff'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@vorta.local', 'role' => 'staff'],
            ['name' => 'Michael Johnson', 'email' => 'michael.johnson@vorta.local', 'role' => 'staff'],
            ['name' => 'Emily Davis', 'email' => 'emily.davis@vorta.local', 'role' => 'staff'],
            ['name' => 'David Wilson', 'email' => 'david.wilson@vorta.local', 'role' => 'staff'],
            ['name' => 'Sarah Brown', 'email' => 'sarah.brown@vorta.local', 'role' => 'staff'],
            ['name' => 'James Taylor', 'email' => 'james.taylor@vorta.local', 'role' => 'staff'],
            ['name' => 'Olivia Martinez', 'email' => 'olivia.martinez@vorta.local', 'role' => 'staff'],
            ['name' => 'Daniel Anderson', 'email' => 'daniel.anderson@vorta.local', 'role' => 'staff'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role)
            VALUES (:name, :email, :password_hash, :role)
        ");

        foreach ($users as $user) {
            try {
                $stmt->execute([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password_hash' => password_hash($defaultPassword, PASSWORD_DEFAULT),
                    'role' => $user['role'],
                ]);
                echo "Created user: {$user['name']} ({$user['email']})\n";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    echo "Skipped duplicate email: {$user['email']}\n";
                } else {
                    echo "Failed to insert {$user['name']}: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "Users seeding complete. Default password for all accounts: $defaultPassword\n";
    }
];