<?php

const ACCOUNT_MIN_PASSWORD_LENGTH = 6;

function account_change_password(
    PDO $pdo,
    int $userId,
    string $current,
    string $new,
    string $confirm
): array {
    $current = trim($current);
    $new = trim($new);
    $confirm = trim($confirm);

    if ($current === '' || $new === '' || $confirm === '') {
        return ['ok' => false, 'message' => 'All password fields are required.'];
    }
    if ($new !== $confirm) {
        return ['ok' => false, 'message' => 'Password confirmation does not match.'];
    }
    if (mb_strlen($new) < ACCOUNT_MIN_PASSWORD_LENGTH) {
        return [
            'ok' => false,
            'message' => 'New password must be at least ' . ACCOUNT_MIN_PASSWORD_LENGTH . ' characters.',
        ];
    }

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'message' => 'User not found.'];
    }
    if (!password_verify($current, $row['password_hash'])) {
        return ['ok' => false, 'message' => 'Current password is incorrect.'];
    }

    if (password_verify($new, $row['password_hash'])) {
        return ['ok' => false, 'message' => 'The new password must be different from the current one.'];
    }

    $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $update->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

    return ['ok' => true, 'message' => 'Password changed successfully.'];
}

function account_update_profile(
    PDO $pdo,
    int $userId,
    string $name,
    string $email,
    string $phone
): array {
    $name = trim($name);
    $email = trim($email);
    $phone = trim($phone);

    if ($name === '' || $email === '') {
        return ['ok' => false, 'message' => 'Name and email are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid email format.'];
    }

    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $check->execute([$email, $userId]);
    if ($check->fetch()) {
        return ['ok' => false, 'message' => 'Email is already in use by another user.'];
    }

    $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?")
        ->execute([$email, $userId]);

    $hasEmployee = $pdo->prepare("SELECT user_id FROM employees WHERE user_id = ?");
    $hasEmployee->execute([$userId]);

    if ($hasEmployee->fetch()) {
        $pdo->prepare("UPDATE employees SET name = ?, phone = ? WHERE user_id = ?")
            ->execute([$name, $phone, $userId]);
    } else {
        $pdo->prepare("INSERT INTO employees (user_id, name, phone) VALUES (?, ?, ?)")
            ->execute([$userId, $name, $phone]);
    }

    return ['ok' => true, 'message' => 'Profile updated successfully.'];
}
