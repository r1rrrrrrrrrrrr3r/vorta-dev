<?php

const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_FAILURES = 5;

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 45);
}

function login_is_locked(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE email = ? AND ip = ? AND success = 0
          AND attempted_at > (NOW() - INTERVAL " . (int) LOGIN_WINDOW_MINUTES . " MINUTE)
    ");
    $stmt->execute([mb_strtolower($email), client_ip()]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_FAILURES;
}

function login_record_attempt(PDO $pdo, string $email, bool $success): void
{
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)");
    $stmt->execute([mb_strtolower($email), client_ip(), $success ? 1 : 0]);
}
