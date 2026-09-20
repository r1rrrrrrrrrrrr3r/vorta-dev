<?php

return [
    'run' => function (PDO $pdo) {
        echo "Seeding employee <-> work force assignments...\n";

        $employees = $pdo->query("SELECT employee_id FROM employees ORDER BY employee_id")
            ->fetchAll(PDO::FETCH_COLUMN);
        $workforces = $pdo->query("SELECT workforce_id FROM work_force ORDER BY workforce_id")
            ->fetchAll(PDO::FETCH_COLUMN);

        if (empty($employees) || empty($workforces)) {
            echo "No employees or work forces available to link.\n";
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO employees_workforce (employee_id, workforce_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE employee_id = employee_id
        ");

        $assignmentsPerEmployee = 2;
        $total = count($workforces);
        $linked = 0;

        foreach ($employees as $i => $employeeId) {
            for ($offset = 0; $offset < $assignmentsPerEmployee; $offset++) {
                $workforceId = $workforces[($i + $offset) % $total];
                $stmt->execute([$employeeId, $workforceId]);
                $linked++;
            }
        }

        echo "Employee <-> work force seeding complete. $linked assignment(s) created.\n";
    }
];