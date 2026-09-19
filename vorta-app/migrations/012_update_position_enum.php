<?php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE employees 
            MODIFY position ENUM(
                'Employe',
                'Employee',
                'Internship',
                'President Director',
                'Research and Development Manager',
                'Director',
                'Manager',
                'Other'
            ) NOT NULL");

        $pdo->exec("UPDATE employees SET position = 'Employee' WHERE position = 'Employe'");
        $pdo->exec("UPDATE employees SET position = 'Director' WHERE position = 'President Director'");
        $pdo->exec("UPDATE employees SET position = 'Manager' WHERE position = 'Research and Development Manager'");
        $pdo->exec("UPDATE employees SET position = 'Other' WHERE position = 'Internship'");

        $pdo->exec("ALTER TABLE employees 
            MODIFY position ENUM('Employee', 'Manager', 'Director', 'Other') NOT NULL");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE employees 
            MODIFY position ENUM(
                'Employe',
                'Employee',
                'Internship',
                'President Director',
                'Research and Development Manager',
                'Director',
                'Manager',
                'Other'
            ) NOT NULL");

        $pdo->exec("UPDATE employees SET position = 'Employe' WHERE position = 'Employee'");
        $pdo->exec("UPDATE employees SET position = 'President Director' WHERE position = 'Director'");
        $pdo->exec("UPDATE employees SET position = 'Research and Development Manager' WHERE position = 'Manager'");
        $pdo->exec("UPDATE employees SET position = 'Internship' WHERE position = 'Other'");

        $pdo->exec("ALTER TABLE employees 
            MODIFY position ENUM(
                'Employe',
                'Internship',
                'President Director',
                'Research and Development Manager'
            ) NOT NULL");
    }
];