<?php
// seeders/EmployeeWorkforceSeeder.php

// class EmployeeWorkforceSeeder
return [
    'run' => function ($pdo)
    {
        echo "Menjalankan seeder: EmployeeWorkforceSeeder\n";

        $data = [
    [1, 1], // Putra + Jasa Raharja
    [1, 4], // Putra + Inare
    [1, 5], // Putra + Antara
    [1, 7], // Putra + Casamedika

    [2, 4], // Bryan + Inare
    [2, 5], // Bryan + Antara
    [2, 7], // Bryan + Casamedika

    [3, 1], // Nabil + Jasa Raharja
    [3, 2], // Nabil + Hildiktipari

    [7, 1], // Raffa + Jasa Raharja
    [7, 4], // Raffa + Inare
    [7, 5], // Raffa + Antara

    [8, 4], // Fazle + Inare
    [8, 5], // Fazle + Antara
    [8, 7], // Fazle + Casamedika

    [9, 4], // Farel + Inare
    [9, 5], // Farel + Antara
    [9, 7], // Farel + Casamedika

    [10, 4], // Bintang + Inare
    [10, 5], // Bintang + Antara
    [10, 6], // Bintang + Trade Finance

    [11, 4], // Iqbal + Inare
    [11, 5], // Iqbal + Antara
    [11, 6], // Iqbal + Trade Finance

    [12, 1], // Zafira + Jasa Raharja

    [13, 3], // Kiya + Pasifik

    [14, 4], // Asa + Inare
    [14, 5], // Asa + Antara

    [15, 3], // Ily + Pasifik

    [16, 4], // Hana + Inare
    [16, 5], // Hana + Antara

    [18, 6], // Kevin + Trade Finance

    [19, 2], // Risvyan + Hildiktipari

    [20, 6], // Yafi + Trade Finance

    [21, 2], // Hildan + Hildiktipari
    [21, 7], // Hildan + Casamedika

    [22, 3], // Joshua + Pasifik
    [22, 6], // Joshua + Trade Finance

    [23, 2], // Fadhal + Hildiktipari
    [23, 7], // Fadhal + Casamedika

    [24, 1], // Rasya + Jasa Raharja
    [24, 2], // Rasya + Hildiktipari

    [25, 1], // Rifki + Jasa Raharja
    [25, 4], // Rifki + Inare
    [25, 5], // Rifki + Antara

    [26, 6], // Marsya + Trade Finance

    [17, 1], // Fahmy + Jasa Raharja
    [17, 2], // Fahmy + Hildiktipari
    [17, 7], // Fahmy + Casamedika
];



        $stmt = $pdo->prepare("
            INSERT INTO employees_workforce (employee_id, workforce_id) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE employee_id = employee_id
        ");

        foreach ($data as $row) {
            $stmt->execute($row);
        }

        echo "✅ Data employee_workforce berhasil diisi.\n";
    }
];