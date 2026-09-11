<?php
// run_seeders.php

require_once __DIR__ . '/lib/db.php';

echo "Menjalankan seeder...\n";

// Lokasi file versi untuk seeder
$versionFile = __DIR__ . '/seeders/schema_version.php';

// Pastikan file versi ada
if (!file_exists($versionFile)) {
    file_put_contents($versionFile, "<?php\nreturn 0;");
    echo "✅ File schema_version.php dibuat (awal: 0)\n";
}

// Baca versi terakhir yang sudah dijalankan
$currentVersion = (int) require $versionFile;

// Ambil semua file seeder yang berpola: angka_awalan
$seederFiles = glob(__DIR__ . '/seeders/[0-9]*_*.php');
sort($seederFiles); // Urutkan berdasarkan nama file

$applied = 0;

foreach ($seederFiles as $file) {
    // Ambil angka versi dari nama file: 001_seed_employees.php → 1
    preg_match('/^(\d+)/', basename($file), $matches);
    $version = (int)$matches[1];

    // Hanya jalankan jika versi > currentVersion
    if ($version > $currentVersion) {
        $filename = basename($file);
        echo "Menjalankan seeder: $filename...\n";

        try {
            $seeder = require $file;

            if (isset($seeder['run']) && is_callable($seeder['run'])) {
                $seeder['run']($pdo);
                $currentVersion = $version; // Update versi
                $applied++;
            } else {
                throw new Exception("Seeder tidak memiliki fungsi 'run'");
            }
        } catch (Exception $e) {
            echo "❌ Gagal menjalankan seeder $filename: " . $e->getMessage() . "\n";
            echo "Proses dihentikan. Versi terakhir tetap: $currentVersion\n";
            break;
        }
    }
}

// Simpan versi terbaru ke file
file_put_contents($versionFile, "<?php\nreturn $currentVersion;");

// Output hasil
if ($applied === 0) {
    echo "Tidak ada seeder baru.\n";
} else {
    echo "✅ $applied seeder berhasil dijalankan. Versi terbaru: $currentVersion\n";
}