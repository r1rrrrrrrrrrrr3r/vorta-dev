<?php
$localConfigFile = __DIR__ . '/../config.local.php';
$usingLocalConfig = is_file($localConfigFile);
$localConfig = $usingLocalConfig ? require $localConfigFile : [];
$APP_ENV = strtolower(trim((string) (getenv('APP_ENV') ?: ($localConfig['APP_ENV'] ?? 'production'))));
$useLocalConfig = $APP_ENV === 'development' && $usingLocalConfig;
$DB_HOST = trim((string) (getenv('DB_HOST') ?: ($useLocalConfig ? ($localConfig['DB_HOST'] ?? '') : '')));
$DB_NAME = trim((string) (getenv('DB_NAME') ?: ($useLocalConfig ? ($localConfig['DB_NAME'] ?? '') : '')));
$DB_USER = trim((string) (getenv('DB_USER') ?: ($useLocalConfig ? ($localConfig['DB_USER'] ?? '') : '')));
$DB_PASS = (string) (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($useLocalConfig ? ($localConfig['DB_PASS'] ?? '') : ''));
date_default_timezone_set('Asia/Jakarta');

if ($DB_HOST === '' || $DB_NAME === '' || $DB_USER === '' || ($DB_PASS === '' && !$useLocalConfig)) {
  error_log('Database connection refused: DB_HOST, DB_NAME, DB_USER, and DB_PASS must all be configured.');
  http_response_code(500);
  die('The application is temporarily unavailable.');
}

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  error_log('Database connection failed: ' . $e->getMessage());
  http_response_code(500);
  die('The application is temporarily unavailable.');
}
?>