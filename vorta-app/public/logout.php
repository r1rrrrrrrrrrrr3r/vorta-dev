<?php
require_once __DIR__ . '/../lib/auth.php';

// kosongkan session array
$_SESSION = [];

// hapus cookie PHPSESSID di browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// destroy session di server
session_destroy();

// redirect ke halaman login (pakai BASE_URL)
require_once __DIR__ . '/../lib/config.php';
header("Location: {$BASE_URL}/index.php");
exit;
