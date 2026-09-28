<?php

$localConfigFile = __DIR__ . '/../config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
$APP_ENV = strtolower(trim((string) (getenv('APP_ENV') ?: ($localConfig['APP_ENV'] ?? 'production'))));
if (!in_array($APP_ENV, ['development', 'production'], true)) {
  $APP_ENV = 'development';
}
$configuredUrl = trim((string) (getenv('APP_URL') ?: ($APP_ENV === 'development' ? ($localConfig['APP_URL'] ?? '') : '')));
$BASE_URL = '';
if ($configuredUrl !== '' && filter_var($configuredUrl, FILTER_VALIDATE_URL)) {
  $parts = parse_url($configuredUrl);
  $scheme = strtolower((string)($parts['scheme'] ?? ''));
  if (in_array($scheme, $APP_ENV === 'production' ? ['https'] : ['http', 'https'], true)
      && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
    $BASE_URL = rtrim($configuredUrl, '/');
  }
}
