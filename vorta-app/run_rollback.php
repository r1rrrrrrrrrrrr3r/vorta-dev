<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/lib/db.php';

echo "Running rollback...\n";

$versionFile = __DIR__ . '/migrations/schema_version.php';
$currentVersion = (int) require $versionFile;

if ($currentVersion <= 0) {
    echo "No migrations to roll back.\n";
    exit;
}

$migrationFiles = glob(__DIR__ . '/migrations/[0-9]*_*.php');
sort($migrationFiles);

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
    echo "Migration file for version $currentVersion not found.\n";
    exit;
}

echo "Running rollback: " . basename($migrationFile) . "...\n";

$migration = require $migrationFile;
try {
    $migration['down']($pdo);
    $newVersion = $currentVersion - 1;
    file_put_contents($versionFile, "<?php\nreturn $newVersion;");
    echo "Rollback successful. Current version: $newVersion\n";
} catch (Exception $e) {
    echo "Rollback failed: " . $e->getMessage() . "\n";
}