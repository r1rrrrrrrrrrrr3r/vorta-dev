<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/lib/db.php';

echo "Running seeders...\n";

$versionFile = __DIR__ . '/seeders/schema_version.php';

if (!file_exists($versionFile)) {
    file_put_contents($versionFile, "<?php\nreturn 0;");
    echo "Created schema_version.php file (initial: 0)\n";
}

$currentVersion = (int) require $versionFile;

$seederFiles = glob(__DIR__ . '/seeders/[0-9]*_*.php');
sort($seederFiles);

$applied = 0;

foreach ($seederFiles as $file) {
    preg_match('/^(\d+)/', basename($file), $matches);
    $version = (int) $matches[1];

    if ($version > $currentVersion) {
        $filename = basename($file);
        echo "Running seeder: $filename...\n";

        try {
            $seeder = require $file;

            if (isset($seeder['run']) && is_callable($seeder['run'])) {
                $seeder['run']($pdo);
                $currentVersion = $version;
                $applied++;
            } else {
                throw new Exception("Seeder does not contain a callable 'run' function");
            }
        } catch (Exception $e) {
            echo "Failed to run seeder $filename: " . $e->getMessage() . "\n";
            echo "Process aborted. Last version remains: $currentVersion\n";
            break;
        }
    }
}

file_put_contents($versionFile, "<?php\nreturn $currentVersion;");

if ($applied === 0) {
    echo "No new seeders.\n";
} else {
    echo "$applied seeder(s) successfully executed. Current version: $currentVersion\n";
}