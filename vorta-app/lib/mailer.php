<?php
// lib/mailer.php
function send_simple_mail($to, $subject, $message) {
  $headers = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type:text/html;charset=UTF-8\r\n";
  $headers .= "From: vorta Tracker <no-reply@vorta.local>\r\n";
  // For production, replace with a real SMTP (e.g., PHPMailer/Sendgrid)
  return mail($to, $subject, $message, $headers);
}
?>
