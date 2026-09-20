<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE employees
            MODIFY COLUMN position
            ENUM('Employee','Intern','Staff','Supervisor','Team Lead','Manager','Senior Manager','Director','Other')
            NOT NULL DEFAULT 'Employee'");
    },
    'down' => function (PDO $pdo) {

        $pdo->exec("UPDATE employees
            SET position = 'Other'
            WHERE position IN ('Intern','Staff','Supervisor','Team Lead','Senior Manager')");
        $pdo->exec("ALTER TABLE employees
            MODIFY COLUMN position
            ENUM('Employee','Manager','Director','Other')
            NOT NULL DEFAULT 'Employee'");
    },
];
