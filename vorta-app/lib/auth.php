<?php
if (session_status() === PHP_SESSION_NONE) {
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}
require_once __DIR__ . '/config.php';

function require_login() {
  global $BASE_URL;
  if (!isset($_SESSION['user'])) {
    header("Location: {$BASE_URL}/index.php");
    exit;
  }
}

function require_admin() {
  require_login();
  if (!in_array($_SESSION['user']['role'] ?? '', ['admin', 'platform_admin'], true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

function require_role(array $roles): void
{
  require_login();
  if (!in_array($_SESSION['user']['role'] ?? '', $roles, true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}