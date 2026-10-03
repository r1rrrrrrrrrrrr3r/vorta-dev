<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ui.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/tenant.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/mailer.php';
require_once __DIR__ . '/../../lib/i18n.php';
require_admin();
$company_id = current_company_id();

$search = trim($_GET['search'] ?? '');
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$backParams = ['tab' => 'users', 'page' => $page];
if ($search) $backParams['search'] = $search;
$redirect = 'admin_master_data.php?' . http_build_query($backParams);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_invite'])) {
  csrf_verify();
  $invitationId = (int)$_POST['revoke_invite'];
  $stmt = $pdo->prepare('DELETE FROM company_invitations WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL');
  $stmt->execute([$invitationId, $company_id]);
  if ($stmt->rowCount()) {
    audit_log($pdo, 'user.invite_revoked', 'company_invitations', $invitationId);
    flash_set('ok', 'Invitation revoked');
  } else {
    flash_set('bad', 'Invitation not found.');
  }
  header('Location: ' . $redirect, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_invite'])) {
  csrf_verify();
  $invitationId = (int)$_POST['resend_invite'];
  $pendingStmt = $pdo->prepare('
    SELECT email, role
    FROM company_invitations
    WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL
    LIMIT 1
  ');
  $pendingStmt->execute([$invitationId, $company_id]);
  $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);

  if (!$pending) {
    flash_set('bad', 'Invitation not found.');
  } elseif ($BASE_URL === '') {
    flash_set('bad', 'Invitations are unavailable until APP_URL is configured.');
  } else {
    try {
      $rawToken = bin2hex(random_bytes(32));
      $update = $pdo->prepare('
        UPDATE company_invitations
        SET token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), created_at = CURRENT_TIMESTAMP
        WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL
      ');
      $update->execute([hash('sha256', $rawToken), $invitationId, $company_id]);
      $inviteUrl = rtrim($BASE_URL, '/') . '/accept_invite.php?token=' . urlencode($rawToken);
      $companyName = htmlspecialchars((string)($_SESSION['company']['name'] ?? 'your company'), ENT_QUOTES, 'UTF-8');
      $safeUrl = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
      $mailSent = send_simple_mail(
        $pending['email'],
        'Reminder: you have been invited to Vorta Prodtracker',
        '<p>Your invitation to join ' . $companyName . ' is still waiting for you.</p><p><a href="' . $safeUrl . '">Accept your invitation</a></p><p>This link expires in 7 days.</p>'
      );
      flash_set($mailSent ? 'ok' : 'info', $mailSent
        ? 'Invitation resent by email.'
        : 'Invitation renewed, but email delivery is unavailable. Copy the new link below.');
      $_SESSION['invite_url'] = $inviteUrl;
      audit_log($pdo, 'user.invite_resent', 'company_invitations', $invitationId, ['email' => $pending['email']]);
    } catch (Throwable $e) {
      flash_set('bad', 'Failed to resend the invitation.');
    }
  }
  header('Location: ' . $redirect, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'user_invite') {
  csrf_verify();
  $email = strtolower(trim((string)($_POST['invite_email'] ?? '')));
  $role = (string)($_POST['invite_role'] ?? 'staff');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('bad', 'Enter a valid email address.');
  } elseif ($BASE_URL === '') {
    flash_set('bad', 'Invitations are unavailable until APP_URL is configured.');
  } elseif (!in_array($role, ['admin', 'manager', 'staff'], true)) {
    flash_set('bad', 'Choose a valid invitation role.');
  } else {
    try {
      $existing = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
      $existing->execute([$email]);
      if ($existing->fetch()) {
        flash_set('bad', 'An account already exists for this email.');
      } else {
        $pdo->beginTransaction();
        $remove = $pdo->prepare('DELETE FROM company_invitations WHERE company_id = ? AND email = ? AND accepted_at IS NULL');
        $remove->execute([$company_id, $email]);

        $rawToken = bin2hex(random_bytes(32));
        $invite = $pdo->prepare('
          INSERT INTO company_invitations
            (company_id, email, role, token_hash, expires_at, created_by)
          VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
        ');
        $invite->execute([
          $company_id,
          $email,
          $role,
          hash('sha256', $rawToken),
          (int)($_SESSION['user']['user_id'] ?? 0)
        ]);
        $invitationId = $pdo->lastInsertId();
        $pdo->commit();

        $inviteUrl = rtrim($BASE_URL, '/') . '/accept_invite.php?token=' . urlencode($rawToken);
        $companyName = htmlspecialchars((string)($_SESSION['company']['name'] ?? 'your company'), ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
        $mailSent = send_simple_mail(
          $email,
          'You have been invited to Vorta Prodtracker',
          '<p>You have been invited to join ' . $companyName . ' on Vorta Prodtracker.</p><p><a href="' . $safeUrl . '">Accept your invitation</a></p><p>This link expires in 7 days.</p>'
        );
        flash_set($mailSent ? 'ok' : 'info', $mailSent
          ? 'Invitation created and emailed. The link is also available below.'
          : 'Invitation created, but email delivery is unavailable. Copy the link below and send it to the employee.');
        $_SESSION['invite_url'] = $inviteUrl;
        audit_log($pdo, 'user.invited', 'company_invitations', $invitationId, ['email' => $email, 'role' => $role]);
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      flash_set('bad', 'Failed to create the invitation.');
    }
  }

  header('Location: ' . $redirect, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'users') {
  csrf_verify();
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $action = $_POST['action'] ?? 'create';
  $user_id = (int)($_POST['user_id'] ?? 0);
  $role = $_POST['role'] ?? 'staff';

  if (!in_array($role, ['staff', 'manager', 'admin'], true)) {
    $role = 'staff';
  }

  if (empty($name) || empty($email)) {
    flash_set('bad', "Name and email are required.");
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('bad', "Invalid email format.");
  } elseif ($action === 'create' && mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
    flash_set('bad', "A password of at least " . ACCOUNT_MIN_PASSWORD_LENGTH . " characters is required.");
  } elseif ($action === 'update' && $password !== '' && mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
    flash_set('bad', "Password must be at least " . ACCOUNT_MIN_PASSWORD_LENGTH . " characters.");
  } else {
    try {
      if ($action === 'create') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND company_id = ?");
        $check->execute([$email, $company_id]);
        if ($check->fetch()) {
          flash_set('bad', "Email is already in use.");
        } else {
          $pass_hash = password_hash($password, PASSWORD_DEFAULT);
          $pdo->beginTransaction();
          try {
            $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$company_id, $name, $email, $pass_hash, $role]);
            $newUserId = (int) $pdo->lastInsertId();

            $empStmt = $pdo->prepare("INSERT INTO employees (company_id, user_id, name, position) VALUES (?, ?, ?, ?)");
            $empStmt->execute([$company_id, $newUserId, $name, 'Employee']);
            $newEmployeeId = (int) $pdo->lastInsertId();

            $pdo->commit();

            audit_log($pdo, 'user.created', 'users', $newUserId, ['role' => $role]);
            audit_log($pdo, 'employee.auto_created', 'employees', $newEmployeeId, ['user_id' => $newUserId]);
            flash_set('ok', "User added. A matching employee record was created, so the account is ready to use.");
          } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
              $pdo->rollBack();
            }
            throw $e;
          }
        }
      } elseif ($action === 'update') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
          flash_set('bad', "Email is already in use by another user.");
        } else {
          $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ? AND company_id = ?");
          $stmt->execute([$name, $email, $role, $user_id, $company_id]);
          audit_log($pdo, 'user.updated', 'users', $user_id, ['role' => $role]);
          if (!empty($password)) {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $pstmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND company_id = ?");
            $pstmt->execute([$pass_hash, $user_id, $company_id]);
          }
          flash_set('ok', "User updated");
        }
      } else {
        flash_set('bad', "Unknown action.");
      }
    } catch (PDOException $e) {
      flash_set('bad', "Couldn't save. Try again.");
    }
  }

  header('Location: ' . $redirect, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
  csrf_verify();
  $user_id = (int)$_POST['delete_user'];
  try {
    if ($user_id === (int)($_SESSION['user']['user_id'] ?? 0)) {
      flash_set('bad', "You cannot delete your own account.");
    } else {
      $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND company_id = ?");
      $stmt->execute([$user_id, $company_id]);
      audit_log($pdo, 'user.deleted', 'users', $user_id);

      if ($stmt->rowCount()) {
        flash_set('ok', "User deleted");
      } else {
        flash_set('bad', "User not found.");
      }
    }
  } catch (PDOException $e) {
    flash_set('bad', "Couldn't delete this user. They may still have reports or attendance records.");
  }

  header('Location: ' . $redirect, true, 303);
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "name LIKE ?";
  $params[] = "%$search%";
}
$where[] = "company_id = ?";
$params[] = $company_id;
$whereSql = "WHERE " . implode(" AND ", $where);
$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users $whereSql");
foreach ($params as $i => $val) {
  $totalStmt->bindValue($i + 1, $val, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRow = $totalStmt->fetch();
$totalUsers = (int)($totalRow['cnt'] ?? 0);
$sql = "SELECT user_id, name, email, role FROM users $whereSql ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$index = 1;
foreach ($params as $val) {
  $stmt->bindValue($index++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingInvitesStmt = $pdo->prepare('
  SELECT invitation_id, email, role, expires_at
  FROM company_invitations
  WHERE company_id = ? AND accepted_at IS NULL AND expires_at > NOW()
  ORDER BY created_at DESC
  LIMIT 10
');
$pendingInvitesStmt->execute([$company_id]);
$pendingInvites = $pendingInvitesStmt->fetchAll(PDO::FETCH_ASSOC);
$inviteUrl = $_SESSION['invite_url'] ?? '';
unset($_SESSION['invite_url']);

$selfId = (int) $_SESSION['user']['user_id'];
$roleOptions = ['staff' => 'Staff', 'manager' => 'Manager', 'admin' => 'Admin'];
?>
<?php if ($inviteUrl): ?>
  <section class="card card-body mb-4 grid gap-3" aria-labelledby="invite-link-title">
    <div>
      <h2 class="card-title" id="invite-link-title">Invitation link ready</h2>
      <p class="m-0 mt-1 text-muted text-[13px]">Send this one-time link to the employee. It expires in 7 days.</p>
    </div>
    <div class="input-group">
      <label for="invite-url" class="sr-only">Invitation link</label>
      <input id="invite-url" type="text" class="input" readonly value="<?= e($inviteUrl) ?>">
      <button type="button" class="btn btn-secondary btn-sm" id="copy-invite">Copy</button>
    </div>
  </section>
<?php endif; ?>
<?php
$mdTab = 'users';
$mdPlaceholder = 'Search by name…';
$mdAddLabel = 'Add user';
$mdPanel = 'drawer-user';
$mdTotal = $totalUsers;
$mdNoun = $totalUsers === 1 ? 'user' : 'users';
$mdExtraActions = '<button type="button" class="btn btn-secondary" data-drawer-panel="drawer-invite">' . icon('plus') . 'Invite user</button>';
include __DIR__ . '/../../views/master_data/toolbar.php';
?>
<section class="card">
  <?php if (empty($users)): ?>
    <?= $search ? empty_state('No users match “' . $search . '”', 'Try another name or clear the search.') : empty_state('No users yet', t('empty.invite')) ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Role</th><th class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): $id = (int)$u['user_id']; ?>
            <tr>
              <td><span class="cell-strong"><?= e($u['name']) ?><?= $id === $selfId ? ' <span class="text-muted font-normal">(you)</span>' : '' ?></span><span class="cell-sub"><?= e($u['email']) ?></span></td>
              <td><?= role_pill($u['role']) ?></td>
              <td class="col-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-menu-trigger aria-controls="user-menu-<?= $id ?>" aria-expanded="false" aria-haspopup="menu" aria-label="Actions for <?= e($u['name']) ?>"><?= icon('ellipsis-horizontal') ?></button>
                <div class="menu" id="user-menu-<?= $id ?>" role="menu" hidden>
                  <button type="button" class="menu-item" role="menuitem"
                    onclick="editUser(<?= $id ?>, <?= e(json_encode($u['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>, <?= e(json_encode($u['email'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>, <?= e(json_encode($u['role'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)"><?= icon('pencil') ?>Edit</button>
                  <?php if ($id !== $selfId): ?>
                    <div class="menu-sep"></div>
                    <form method="POST" action="<?= e($redirect) ?>" class="contents" data-turbo-frame="_top">
                      <?= csrf_field() ?>
                      <button type="submit" name="delete_user" value="<?= $id ?>" class="menu-item menu-item-danger" role="menuitem"
                        data-confirm="Delete user?" data-confirm-message="“<?= e($u['name']) ?>” will be removed. This can't be undone." data-confirm-text="Delete" data-confirm-tone="danger"><?= icon('trash') ?>Delete…</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($page, $perPage, $totalUsers) ?>
  <?php endif; ?>
</section>
</turbo-frame>

<?php if ($pendingInvites): ?>
  <section class="card mt-4" aria-labelledby="pending-invites-title">
    <div class="card-header">
      <h2 class="card-title" id="pending-invites-title">Pending invitations</h2>
      <span class="card-meta"><?= count($pendingInvites) ?></span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Email</th><th>Role</th><th>Expires</th><th class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($pendingInvites as $pending): $inviteId = (int)$pending['invitation_id']; ?>
            <tr>
              <td class="cell-strong break-all"><?= e($pending['email']) ?></td>
              <td><?= role_pill($pending['role']) ?></td>
              <td class="whitespace-nowrap"><?= e(fmt_date(substr((string) $pending['expires_at'], 0, 10))) ?> <span class="text-muted"><?= e(date('H:i', strtotime($pending['expires_at']))) ?></span></td>
              <td class="col-actions">
                <div class="inline-flex gap-1">
                  <form method="POST" action="<?= e($redirect) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" name="resend_invite" value="<?= $inviteId ?>" class="btn btn-ghost btn-sm">Resend</button>
                  </form>
                  <form method="POST" action="<?= e($redirect) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" name="revoke_invite" value="<?= $inviteId ?>" class="btn btn-ghost btn-sm text-bad"
                      data-confirm="Revoke invitation?" data-confirm-message="The link sent to <?= e($pending['email']) ?> will stop working." data-confirm-text="Revoke" data-confirm-tone="danger">Revoke</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<aside class="drawer" id="drawer-invite" role="dialog" aria-modal="true" aria-labelledby="invite-drawer-title" hidden>
  <form method="POST" id="invite-form" action="<?= e($redirect) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="user_invite">
    <div class="drawer-header">
      <div class="min-w-0"><div class="drawer-eyebrow">Invite</div><h2 class="drawer-title" id="invite-drawer-title">Invite employees</h2></div>
      <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
    </div>
    <div class="drawer-body">
      <p class="m-0 text-muted">Create a secure sign-up link so employees can set their own password.</p>
      <div class="field">
        <label class="label" for="invite-email">Employee email</label>
        <input type="email" name="invite_email" id="invite-email" class="input" placeholder="employee@example.com" required>
      </div>
      <div class="field">
        <span class="label" id="invite-role-label">Role</span>
        <div class="seg" role="radiogroup" aria-labelledby="invite-role-label">
          <?php foreach ($roleOptions as $value => $label): ?>
            <label><input type="radio" name="invite_role" value="<?= e($value) ?>" class="sr-only"<?= $value === 'staff' ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="drawer-footer">
      <button type="button" class="btn btn-ghost" data-drawer-close>Cancel</button>
      <button type="submit" class="btn btn-primary">Create invite</button>
    </div>
  </form>
</aside>

<aside class="drawer" id="drawer-user" role="dialog" aria-modal="true" aria-labelledby="user-drawer-title" hidden>
  <form method="POST" id="user-form" action="<?= e($redirect) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="users">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="user_id" value="" id="user-id-input">
    <div class="drawer-header">
      <div class="min-w-0"><div class="drawer-eyebrow" id="form-title">Add user</div><h2 class="drawer-title" id="user-drawer-title">New user</h2></div>
      <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
    </div>
    <div class="drawer-body">
      <div class="field">
        <label class="label" for="user-name">Name</label>
        <input type="text" name="name" id="user-name" class="input" required>
      </div>
      <div class="field">
        <label class="label" for="user-email">Email</label>
        <input type="email" name="email" id="user-email" class="input" placeholder="name@company.com" required>
      </div>
      <div class="field">
        <label class="label" for="user-password">Password</label>
        <input type="password" name="password" id="user-password" class="input" autocomplete="new-password" minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>" required>
        <p class="help" id="user-password-help">At least <?= ACCOUNT_MIN_PASSWORD_LENGTH ?> characters.</p>
      </div>
      <div class="field">
        <span class="label" id="user-role-label">Role</span>
        <div class="seg" role="radiogroup" aria-labelledby="user-role-label">
          <?php foreach ($roleOptions as $value => $label): ?>
            <label><input type="radio" name="role" value="<?= e($value) ?>" class="sr-only"<?= $value === 'staff' ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="help m-0" id="user-employee-help">A matching employee record is created automatically, so the account is ready to use.</p>
    </div>
    <div class="drawer-footer">
      <button type="button" class="btn btn-ghost" data-drawer-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="user-submit">Add user</button>
    </div>
  </form>
</aside>

<script>
(function () {
  const minLength = <?= (int) ACCOUNT_MIN_PASSWORD_LENGTH ?>;
  const password = document.getElementById('user-password');

  function resetUserForm() {
    document.getElementById('user-form').reset();
    document.getElementById('form-title').textContent = 'Add user';
    document.getElementById('user-drawer-title').textContent = 'New user';
    document.getElementById('user-submit').textContent = 'Add user';
    document.getElementById('user-password-help').textContent = 'At least ' + minLength + ' characters.';
    document.getElementById('user-employee-help').hidden = false;
    password.required = true;
    document.getElementById('action-input').value = 'create';
    document.getElementById('user-id-input').value = '';
  }

  function editUser(id, name, email, role) {
    document.getElementById('form-title').textContent = 'Edit user';
    document.getElementById('user-drawer-title').textContent = name;
    document.getElementById('user-submit').textContent = 'Save';
    document.getElementById('user-password-help').textContent = 'Leave empty to keep the current password.';
    document.getElementById('user-employee-help').hidden = true;
    document.getElementById('user-name').value = name;
    document.getElementById('user-email').value = email;
    password.value = '';
    password.required = false;
    const radio = document.querySelector('#user-form input[name="role"][value="' + role + '"]');
    if (radio) radio.checked = true;
    document.getElementById('action-input').value = 'update';
    document.getElementById('user-id-input').value = id;
    Vorta.drawer.openPanel('drawer-user');
  }

  document.getElementById('drawer-user').addEventListener('drawer:mode', resetUserForm);
  document.getElementById('drawer-user').addEventListener('drawer:close', resetUserForm);

  document.getElementById('copy-invite')?.addEventListener('click', async function () {
    const input = document.getElementById('invite-url');
    try {
      await navigator.clipboard.writeText(input.value);
    } catch (error) {
      input.select();
      document.execCommand('copy');
    }
    Vorta.toast('Link copied');
  });

  // dipanggil dari atribut onclick
  window.editUser = editUser;
})();
</script>
