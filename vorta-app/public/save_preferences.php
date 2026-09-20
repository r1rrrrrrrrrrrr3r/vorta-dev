<?php
// public/save_preferences.php
// Persists the signed-in user's UI preferences (theme and/or navigation layout).
// Either field may be sent on its own, so the two controls save independently.
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

// Keep the session in step so the next page render bootstraps the same values.
$_SESSION['user']['theme'] = $result['theme'];
$_SESSION['user']['nav_layout'] = $result['nav_layout'];

echo json_encode($result);
