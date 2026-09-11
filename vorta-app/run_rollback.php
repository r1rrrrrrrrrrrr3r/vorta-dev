<?php
require_once __DIR__ . '/lib/db.php';

echo "Menjalankan rollback...\n";

// Baca versi saat ini
$versionFile = __DIR__ . '/migrations/schema_version.php';
$currentVersion = (int) require $versionFile;

if ($currentVersion <= 0) {
    echo "Tidak ada migrasi untuk di-rollback.\n";
    exit;
}

// Ambil semua file migrasi dan urutkan
$migrationFiles = glob(__DIR__ . '/migrations/[0-9]*_*.php');
sort($migrationFiles);

// Cari file dengan versi = currentVersion
$migrationFile = null;
foreach ($migrationFiles as $file) {
    preg_match('/^(\d+)/', basename($file), $matches);
    $version = (int) $matches[1];

    if ($version === $currentVersion) {
        $migrationFile = $file;
        break;
    }
}

if (!$migrationFile) {
    echo "File migrasi versi $currentVersion tidak ditemukan.\n";
    exit;
}

echo "Menjalankan rollback: " . basename($migrationFile) . "...\n";

// Jalankan down()
$migration = require $migrationFile;
try {
    $migration['down']($pdo);
    // Turunkan versi
    $newVersion = $currentVersion - 1;
    file_put_contents($versionFile, "<?php\nreturn $newVersion;");
    echo "✅ Rollback berhasil. Versi sekarang: $newVersion\n";
} catch (Exception $e) {
    echo "❌ Gagal rollback: " . $e->getMessage() . "\n";
}
