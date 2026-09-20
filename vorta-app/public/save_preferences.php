<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/preferences.php';
require_login();

header('Content-Type: application/json');

$theme = array_key_exists('theme', $_POST) ? (string) $_POST['theme'] : null;
$layout = array_key_exists('nav_layout', $_POST) ? (string) $_POST['nav_layout'] : null;

$result = preferences_save($pdo, (int) $_SESSION['user']['user_id'], $theme, $layout);

if (!$result['ok']) {
    http_response_code(400);
    echo json_encode($result);
    exit;
}

$_SESSION['user']['theme'] = $result['theme'];
$_SESSION['user']['nav_layout'] = $result['nav_layout'];

echo json_encode($result);
