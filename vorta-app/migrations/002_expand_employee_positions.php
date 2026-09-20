<?php
// migrations/002_expand_employee_positions.php
// Adds more employee positions. The Master Data > Employees form reads this ENUM
// dynamically via getEnumValues(), so the dropdown picks new values up automatically.

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE employees
            MODIFY COLUMN position
            ENUM('Employee','Intern','Staff','Supervisor','Team Lead','Manager','Senior Manager','Director','Other')
            NOT NULL DEFAULT 'Employee'");
    },
    'down' => function (PDO $pdo) {
        // Collapse values that will not exist after narrowing, so no row is left invalid.
        $pdo->exec("UPDATE employees
            SET position = 'Other'
            WHERE position IN ('Intern','Staff','Supervisor','Team Lead','Senior Manager')");
        $pdo->exec("ALTER TABLE employees
            MODIFY COLUMN position
            ENUM('Employee','Manager','Director','Other')
            NOT NULL DEFAULT 'Employee'");
    },
];
