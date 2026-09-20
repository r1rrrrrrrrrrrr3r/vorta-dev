<?php

require_once __DIR__ . '/lib/db.php';

echo "Running migrations...\n";

$versionFile = __DIR__ . '/migrations/schema_version.php';
$currentVersion = (int) require $versionFile;

$migrationFiles = glob(__DIR__ . '/migrations/[0-9]*_*.php');
sort($migrationFiles);

$applied = 0;

foreach ($migrationFiles as $file) {
    preg_match('/^(\d+)/', basename($file), $matches);
    $version = (int) $matches[1];

    if ($version > $currentVersion) {
        echo "Running migration: " . basename($file) . "...\n";
        $migration = require $file;

        try {
            $migration['up']($pdo);
            $currentVersion = $version;
            $applied++;
        } catch (Exception $e) {
            echo "Failed to run migration: " . $e->getMessage() . "\n";
            break;
        }
    }
}

file_put_contents($versionFile, "<?php\nreturn $currentVersion;");

if ($applied === 0) {
    echo "No new migrations.\n";
} else {
    echo "$applied migration(s) successfully executed. Current version: $currentVersion\n";
}