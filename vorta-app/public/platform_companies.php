<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_once __DIR__ . '/../lib/ui.php';
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

$pageTitle = 'Companies';
$activeNav = 'companies';
include __DIR__ . '/../views/layout/start.php';
?>
<?= page_header('Companies', 'Workspaces on this Vorta installation') ?>

<?php if ($message): ?>
  <?= alert_box('ok', trim($message)) ?>
<?php endif; ?>
<?php if ($inviteUrl): ?>
  <section class="card card-body mb-4 grid gap-3" aria-labelledby="pc-invite-title">
    <h2 class="card-title" id="pc-invite-title">Invite link</h2>
    <div class="input-group">
      <label for="pc-invite-url" class="sr-only">Invite link</label>
      <input id="pc-invite-url" type="text" class="input" readonly value="<?= e($inviteUrl) ?>">
    </div>
  </section>
<?php endif; ?>

<div class="grid gap-4 lg:grid-cols-[1fr_360px] items-start">
  <section class="card">
    <div class="card-header"><h2 class="card-title">All companies</h2><span class="card-meta"><?= count($companies) ?></span></div>
    <?php if (!$companies): ?>
      <?= empty_state('No companies yet') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Name</th><th>Slug</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($companies as $company): ?>
              <tr>
                <td class="cell-strong"><?= e($company['name']) ?></td>
                <td class="text-muted"><?= e($company['slug']) ?></td>
                <td class="whitespace-nowrap"><?= e(fmt_date(substr((string) $company['created_at'], 0, 10))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <form method="post" class="card" data-turbo="false">
    <?= csrf_field() ?>
    <div class="card-header"><h2 class="card-title">Create company and invite</h2></div>
    <div class="card-body grid gap-4">
      <div class="field">
        <label class="label" for="pc-name">Company name</label>
        <input class="input" id="pc-name" name="company_name" required>
      </div>
      <div class="field">
        <label class="label" for="pc-email">Admin email</label>
        <input class="input" id="pc-email" type="email" name="email" required>
      </div>
      <div class="field">
        <label class="label" for="pc-role">Role</label>
        <select class="select" id="pc-role" name="role">
          <option value="admin">Admin</option>
          <option value="manager">Manager</option>
          <option value="staff">Staff</option>
        </select>
      </div>
    </div>
    <div class="card-footer justify-end">
      <button type="submit" class="btn btn-primary">Create company and invite</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../views/layout/end.php'; ?>
