<?php

return [
    'run' => function (PDO $pdo) {
        echo "Seeding work force...\n";

        $data = [
            ['workforce_name' => 'Client Alpha'],
            ['workforce_name' => 'Client Beta'],
            ['workforce_name' => 'Client Gamma'],
            ['workforce_name' => 'Client Delta'],
            ['workforce_name' => 'Client Epsilon'],
            ['workforce_name' => 'Client Zeta'],
            ['workforce_name' => 'Client Eta'],
        ];

        $stmt = $pdo->prepare("INSERT INTO work_force (workforce_name) VALUES (:workforce_name)");

        foreach ($data as $row) {
            try {
                $stmt->execute($row);
                echo "Created work force: {$row['workforce_name']}\n";
            } catch (Exception $e) {
                echo "Failed to insert {$row['workforce_name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Work force seeding complete.\n";
    }
];