<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/settings.php';

$today = date('Y-m-d');
$dailyMin = settings_get_daily_min_reports($pdo);

$stmt = $pdo->prepare("
  SELECT u.user_id, u.name, u.email, COUNT(pr.report_id) as c
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.report_date = ?
  WHERE u.is_active = 1
  GROUP BY u.user_id
  HAVING c < ?
");
$stmt->execute([$today, $dailyMin]);
$rows = $stmt->fetchAll();

$sent = 0;
$failed = 0;
foreach ($rows as $r) {
  $name = htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8');
  $email = $r['email'];
  $c = (int)$r['c'];
  $msg = "
    <p>Halo {$name},</p>
    <p>You have submitted only <strong>{$c}</strong> report(s) today.</p>
    <p>The daily minimum is <strong>{$dailyMin} items</strong>. Please complete your daily report before 23:59.</p>
    <p>-  Productivity Tracker</p>
  ";
  if (send_simple_mail($email, "[Reminder] Complete Your Daily Report", $msg)) {
    $sent++;
  } else {
    $failed++;
  }
}

echo "Reminders attempted: " . count($rows) . "; sent: {$sent}; failed: {$failed}" . PHP_EOL;
