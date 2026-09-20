<?php

return [
    'run' => function (PDO $pdo) {
        echo "Seeding employees...\n";

        $stmt = $pdo->query("
            SELECT user_id, name, role
            FROM users
            WHERE user_id NOT IN (SELECT user_id FROM employees)
            ORDER BY user_id
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            echo "No users available to seed as employees.\n";
            return;
        }

        $insert = $pdo->prepare("
            INSERT INTO employees (user_id, name, position, phone)
            VALUES (:user_id, :name, :position, :phone)
        ");

        $count = 0;
        foreach ($users as $user) {
            $position = $user['role'] === 'admin' ? 'Manager' : 'Employee';

            try {
                $insert->execute([
                    'user_id' => $user['user_id'],
                    'name' => $user['name'],
                    'position' => $position,
                    'phone' => null,
                ]);
                echo "Created employee: {$user['name']} ($position)\n";
                $count++;
            } catch (PDOException $e) {
                echo "Failed to insert employee for {$user['name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Employees seeding complete. $count employee(s) created.\n";
    }
];