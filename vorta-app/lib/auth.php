 <?php
// lib/auth.php
session_start();
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
  if ($_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

