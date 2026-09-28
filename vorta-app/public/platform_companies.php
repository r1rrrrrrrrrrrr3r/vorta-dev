<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_admin();
if (!is_platform_admin()) {
    http_response_code(403);
    exit('Forbidden');
}

$message = '';
$inviteUrl = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $companyName = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    if ($companyName !== '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $companyName), '-')) . '-' . bin2hex(random_bytes(3));
        $stmt = $pdo->prepare("INSERT INTO companies (name, slug) VALUES (?, ?)");
        $stmt->execute([$companyName, $slug]);
        $companyId = (int)$pdo->lastInsertId();
        $message = 'Company created.';
    }
    if ($email !== '' && isset($companyId) && in_array($role, ['admin', 'manager', 'staff'], true)) {
        $rawToken = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            INSERT INTO company_invitations (company_id, email, role, token_hash, expires_at, created_by)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 72 HOUR), ?)
        ");
        $stmt->execute([$companyId, $email, $role, hash('sha256', $rawToken), $_SESSION['user']['user_id']]);
        $inviteUrl = rtrim($BASE_URL, '/') . '/accept_invite.php?token=' . urlencode($rawToken);
        $message .= ' Invite link generated below.';
    }
}
$companies = $pdo->query("SELECT company_id, name, slug, created_at FROM companies ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Companies</title></head><body>
<h1>Companies</h1>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($inviteUrl): ?><p>Invite link: <code><?= htmlspecialchars($inviteUrl) ?></code></p><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <input name="company_name" placeholder="Company name" required>
  <input type="email" name="email" placeholder="Admin email" required>
  <select name="role"><option value="admin">Admin</option><option value="manager">Manager</option><option value="staff">Staff</option></select>
  <button type="submit">Create company and invite</button>
</form>
<ul><?php foreach ($companies as $company): ?><li><?= htmlspecialchars($company['name']) ?></li><?php endforeach; ?></ul>
</body></html>
