<?php

// use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS employees_workforce(
            employee_id INT,
            workforce_id INT,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (employee_id, workforce_id),
            
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
            FOREIGN KEY (workforce_id) REFERENCES work_force(workforce_id) ON DELETE CASCADE
            )";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec('ALTER TABLE work_force DROP FOREIGN KEY fk_employee_workforce');
        $pdo->exec('ALTER TABLE work_force DROP COLUMN employee_id');
    }
];
