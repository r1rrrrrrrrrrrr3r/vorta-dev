<?php

return [
    'run' => function (PDO $pdo) {
        echo "Seeding job types...\n";

        $data = [
            ['name' => 'Programming'],
            ['name' => 'Website'],
            ['name' => 'Mobile App'],
            ['name' => 'Training Materials'],
            ['name' => 'Teaching'],
            ['name' => 'QA / Testing'],
            ['name' => 'UI / UX'],
            ['name' => 'DevOps'],
            ['name' => 'Documentation'],
        ];

        $stmt = $pdo->prepare("INSERT INTO job_type (name) VALUES (:name)");

        foreach ($data as $row) {
            try {
                $stmt->execute($row);
                echo "Created job type: {$row['name']}\n";
            } catch (Exception $e) {
                echo "Failed to insert job type {$row['name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Job type seeding complete.\n";
    }
];