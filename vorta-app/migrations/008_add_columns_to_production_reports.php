<?php
// use PDO;

return [
    'up' => function (PDO $pdo) {
        // Tambahkan kolom workforce_id
        $pdo->exec("ALTER TABLE production_reports
                    ADD COLUMN workforce_id INT NULL");

        // Tambahkan foreign key constraint
        $pdo->exec("ALTER TABLE production_reports 
                    ADD CONSTRAINT fk_workforce_id 
                    FOREIGN KEY (workforce_id) 
                    REFERENCES work_force(workforce_id)
                    ON DELETE SET NULL 
                    ON UPDATE CASCADE");
    },
    'down' => function (PDO $pdo) {

    }
];

?>