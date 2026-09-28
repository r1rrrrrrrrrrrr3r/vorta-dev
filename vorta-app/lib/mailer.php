<?php

function mail_config_value(string $key): string
{
  global $localConfig;
  $value = getenv($key);
  if ($value !== false && trim((string)$value) !== '') {
    return trim((string)$value);
  }
  return trim((string)($localConfig[$key] ?? ''));
}

function send_simple_mail($to, $subject, $message): bool
{
  $to = trim((string)$to);
  $subject = trim((string)$subject);
  $fromEmail = mail_config_value('MAIL_FROM_EMAIL');
  $fromName = mail_config_value('MAIL_FROM_NAME');

  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    error_log('Mail delivery refused: invalid recipient address.');
    return false;
  }
  if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    error_log('Mail delivery refused: MAIL_FROM_EMAIL is missing or invalid.');
    return false;
  }
  if ($subject === '' || preg_match('/[\r\n]/', $subject)) {
    error_log('Mail delivery refused: invalid subject.');
    return false;
  }
  if ($fromName === '') {
    $fromName = 'Vorta Prodtracker';
  }
  if (preg_match('/[\r\n]/', $fromName)) {
    error_log('Mail delivery refused: MAIL_FROM_NAME contains invalid line breaks.');
    return false;
  }

  $headers = "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
  $headers .= 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . ">\r\n";
  $headers .= "X-Mailer: Vorta Prodtracker\r\n";

  $sent = @mail($to, $subject, (string)$message, $headers);
  if (!$sent) {
    $lastError = error_get_last();
    error_log('Mail delivery failed for configured transport: ' . (($lastError['message'] ?? '') ?: 'mail() returned false.'));
  }
  return $sent;
}
