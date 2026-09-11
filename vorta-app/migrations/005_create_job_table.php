<?php

// use PDO;

return [
    'up' => function (PDO $pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS work_force(
            workforce_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            workforce_name VARCHAR(100) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            -- Relasi ke master_employees
            CONSTRAINT fk_employee_workforce FOREIGN KEY (employee_id) 
                REFERENCES employees(employee_id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            )
        ";
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {
        $pdo->exec('ALTER TABLE work_force DROP FOREIGN KEY fk_employee_workforce');
        $pdo->exec('ALTER TABLE work_force DROP COLUMN employee_id');
    }
];
?>