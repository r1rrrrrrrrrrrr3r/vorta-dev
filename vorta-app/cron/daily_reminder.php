<?php
// Run this via cron at 17:00 server time: 
// 0 17 * * * /usr/bin/php /path/to/cron/daily_reminder.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';

$today = date('Y-m-d');

$stmt = $pdo->prepare("
  SELECT u.user_id, u.name, u.email, COUNT(pr.report_id) as c
  FROM users u
  LEFT JOIN production_reports pr ON pr.user_id = u.user_id AND pr.report_date = ?
  WHERE u.is_active = 1
  GROUP BY u.user_id
  HAVING c < 2
");
$stmt->execute([$today]);
$rows = $stmt->fetchAll();

foreach ($rows as $r) {
  $name = $r['name'];
  $email = $r['email'];
  $c = (int)$r['c'];
  $msg = "
    <p>Halo {$name},</p>
    <p>Hari ini Anda baru menginput <strong>{$c}</strong> pekerjaan.</p>
    <p>Target harian minimal adalah <strong>2 item</strong>. Mohon lengkapi laporan harian sebelum 23:59.</p>
    <p>-  Productivity Tracker</p>
  ";
  send_simple_mail($email, "[Reminder] Lengkapi Laporan Harian", $msg);
}

echo "Reminders sent: " . count($rows) . PHP_EOL;
