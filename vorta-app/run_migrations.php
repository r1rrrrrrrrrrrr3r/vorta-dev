<?php
// run_migrations.php

require_once __DIR__ . '/lib/db.php';

echo "Menjalankan migrasi...\n";

// Baca versi saat ini
$versionFile = __DIR__ . '/migrations/schema_version.php';
$currentVersion = (int) require $versionFile;

// Ambil semua file migrasi
$migrationFiles = glob(__DIR__ . '/migrations/[0-9]*_*.php');
sort($migrationFiles);

$applied = 0;

foreach ($migrationFiles as $file) {
    // Ambil nomor versi dari nama file
    preg_match('/^(\d+)/', basename($file), $matches);
    $version = (int) $matches[1];

    if ($version > $currentVersion) {
        echo "Menjalankan migrasi: " . basename($file) . "...\n";
        $migration = require $file;

        try {
            $migration['up']($pdo);
            $currentVersion = $version;
            $applied++;
        } catch (Exception $e) {
            echo "Gagal menjalankan migrasi: " . $e->getMessage() . "\n";
            break;
        }
    }
}

// Simpan versi terbaru
file_put_contents($versionFile, "<?php\nreturn $currentVersion;");

if ($applied === 0) {
    echo "Tidak ada migrasi baru.\n";
} else {
    echo "$applied migrasi berhasil dijalankan. Versi terbaru: $currentVersion\n";
}

?>