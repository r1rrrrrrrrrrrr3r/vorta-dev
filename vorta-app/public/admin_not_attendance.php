<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';
require_admin();

header('Location: admin_attendance.php?tab=missing&date=' . urlencode(valid_date($_GET['date'] ?? null)));
exit;
