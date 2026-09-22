<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/tenant.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/mailer.php';
require_admin();
$company_id = current_company_id();

$success = '';
$error = '';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (isset($_SESSION['success'])) {
  $success = $_SESSION['success'];
  unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_invite'])) {
  csrf_verify();
  $invitationId = (int)$_POST['revoke_invite'];
  $stmt = $pdo->prepare('DELETE FROM company_invitations WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL');
  $stmt->execute([$invitationId, $company_id]);
  $_SESSION[$stmt->rowCount() ? 'success' : 'error'] = $stmt->rowCount() ? 'Invitation revoked.' : 'Invitation not found.';
  header('Location: admin_master_data.php?tab=users');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_invite'])) {
  csrf_verify();
  $invitationId = (int)$_POST['resend_invite'];
  $pendingStmt = $pdo->prepare('SELECT email FROM company_invitations WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL LIMIT 1');
  $pendingStmt->execute([$invitationId, $company_id]);
  $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);
  if (!$pending) {
    $_SESSION['error'] = 'Invitation not found.';
  } else {
    try {
      $rawToken = bin2hex(random_bytes(32));
      $update = $pdo->prepare('UPDATE company_invitations SET token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), created_at = CURRENT_TIMESTAMP WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL');
      $update->execute([hash('sha256', $rawToken), $invitationId, $company_id]);
      $_SESSION['invite_url'] = rtrim($BASE_URL, '/') . '/accept_invite.php?token=' . urlencode($rawToken);
      $_SESSION['success'] = 'Invitation renewed. Copy the new link below.';
      audit_log($pdo, 'user.invite_resent', 'company_invitations', $invitationId, ['email' => $pending['email']]);
    } catch (Throwable $e) {
      $_SESSION['error'] = 'Failed to resend the invitation.';
    }
  }
  header('Location: admin_master_data.php?tab=users');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['entity'] ?? '') === 'user_invite') {
  csrf_verify();
  $email = strtolower(trim((string)($_POST['invite_email'] ?? '')));
  $role = (string)($_POST['invite_role'] ?? 'staff');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Enter a valid email address.';
  } elseif ($BASE_URL === '') {
    $_SESSION['error'] = 'Invitations are unavailable until APP_URL is configured.';
  } elseif (!in_array($role, ['admin', 'manager', 'staff'], true)) {
    $_SESSION['error'] = 'Choose a valid invitation role.';
  } else {
    try {
      $existing = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
      $existing->execute([$email]);
      if ($existing->fetch()) {
        $_SESSION['error'] = 'An account already exists for this email.';
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
        $pdo->commit();

        $inviteUrl = rtrim($BASE_URL, '/') . '/accept_invite.php?token=' . urlencode($rawToken);
        $companyName = htmlspecialchars((string)($_SESSION['company']['name'] ?? 'your company'), ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
        $mailSent = send_simple_mail(
          $email,
          'You have been invited to Vorta Prodtracker',
          '<p>You have been invited to join ' . $companyName . ' on Vorta Prodtracker.</p><p><a href="' . $safeUrl . '">Accept your invitation</a></p><p>This link expires in 7 days.</p>'
        );
        $_SESSION['success'] = $mailSent
          ? 'Invitation created and emailed. The link is also available below.'
          : 'Invitation created, but email delivery is unavailable. Copy the link below and send it to the employee.';
        $_SESSION['invite_url'] = $inviteUrl;
        audit_log($pdo, 'user.invited', 'company_invitations', $pdo->lastInsertId(), ['email' => $email, 'role' => $role]);
      }

      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_invite'])) {
        csrf_verify();
        $invitationId = (int)$_POST['revoke_invite'];
        $stmt = $pdo->prepare('DELETE FROM company_invitations WHERE invitation_id = ? AND company_id = ? AND accepted_at IS NULL');
        $stmt->execute([$invitationId, $company_id]);
        $_SESSION[$stmt->rowCount() ? 'success' : 'error'] = $stmt->rowCount() ? 'Invitation revoked.' : 'Invitation not found.';
        header('Location: admin_master_data.php?tab=users');
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
          $_SESSION['error'] = 'Invitation not found.';
        } elseif ($BASE_URL === '') {
          $_SESSION['error'] = 'Invitations are unavailable until APP_URL is configured.';
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
            $_SESSION['success'] = $mailSent
              ? 'Invitation resent by email.'
              : 'Invitation renewed, but email delivery is unavailable. Copy the new link below.';
            $_SESSION['invite_url'] = $inviteUrl;
            audit_log($pdo, 'user.invite_resent', 'company_invitations', $invitationId, ['email' => $pending['email']]);
          } catch (Throwable $e) {
            $_SESSION['error'] = 'Failed to resend the invitation.';
          }
        }
        header('Location: admin_master_data.php?tab=users');
        exit;
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $_SESSION['error'] = 'Failed to create the invitation.';
    }
  }

  $redirect = 'admin_master_data.php?tab=users';
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
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
    $_SESSION['error'] = "Name and email are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
  } elseif ($action === 'create' && mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
    $_SESSION['error'] = "A password of at least " . ACCOUNT_MIN_PASSWORD_LENGTH . " characters is required.";
  } elseif ($action === 'update' && $password !== '' && mb_strlen($password) < ACCOUNT_MIN_PASSWORD_LENGTH) {
    $_SESSION['error'] = "Password must be at least " . ACCOUNT_MIN_PASSWORD_LENGTH . " characters.";
  } else {
    try {
      if ($action === 'create') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND company_id = ?");
        $check->execute([$email, $company_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "Email is already in use.";
        } else {
          $pass_hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
          $stmt->execute([$company_id, $name, $email, $pass_hash, $role]);
          audit_log($pdo, 'user.created', 'users', $pdo->lastInsertId(), ['role' => $role]);
          $_SESSION['success'] = "User added successfully.";
        }
      } elseif ($action === 'update') {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
          $_SESSION['error'] = "Email is already in use by another user.";
        } else {
          $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ? AND company_id = ?");
          $stmt->execute([$name, $email, $role, $user_id, $company_id]);
          audit_log($pdo, 'user.updated', 'users', $user_id, ['role' => $role]);
          if (!empty($password)) {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $pstmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND company_id = ?");
            $pstmt->execute([$pass_hash, $user_id, $company_id]);
          }
          $_SESSION['success'] = "User updated successfully.";
        }
      } else {
        $_SESSION['error'] = "Aksi tidak diketahui.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Failed to save data.";
    }
  }

  $params = ['tab' => 'users', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
  csrf_verify();
  $user_id = (int)$_POST['delete_user'];
  try {
    if ($user_id === (int)($_SESSION['user']['user_id'] ?? 0)) {
      $_SESSION['error'] = "You cannot delete your own account.";
    } else {
      $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND company_id = ?");
      $stmt->execute([$user_id, $company_id]);
      audit_log($pdo, 'user.deleted', 'users', $user_id);

      if ($stmt->rowCount()) {
        $_SESSION['success'] = "User deleted successfully.";
      } else {
        $_SESSION['error'] = "User not found.";
      }
    }
  } catch (PDOException $e) {
    $_SESSION['error'] = "Failed to delete data.";
  }

  $params = ['tab' => 'users', 'page' => $page];
  if ($search) $params['search'] = $search;
  $redirect = 'admin_master_data.php?' . http_build_query($params);
  echo "<script> window.location.href = '$redirect'; </script>";
  exit;
}

$where = [];
$params = [];
if ($search) {
  $where[] = "name LIKE ?";
  $params[] = "%$search%";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
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
$totalPages = (int)ceil($totalUsers / $perPage);
$sql = "SELECT user_id, name, email, role FROM users $whereSql ORDER BY user_id ASC LIMIT ? OFFSET ?";
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

function page_url($p)
{
  $q = $_GET;
  $q['page'] = $p;
  return 'admin_master_data.php?' . http_build_query($q);
}
?>

<?php if ($success): ?>
  <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<?php if ($inviteUrl): ?>
  <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
    <p class="font-semibold">Invitation link ready</p>
    <p class="mt-1">Send this one-time link to the employee. It expires in 7 days.</p>
    <div class="mt-3 flex gap-2">
      <input id="invite-url" type="text" readonly value="<?= htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') ?>"
        class="min-w-0 flex-1 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-indigo-800">
      <button type="button" id="copy-invite" class="rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white hover:bg-indigo-700">Copy</button>
    </div>
  </div>
<?php endif; ?>

<style>
  .vorta-invite { margin-bottom:32px; padding:20px; background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:var(--shadow-card,0 1px 3px rgba(0,0,0,.06),0 6px 18px -8px rgba(0,0,0,.12)); }
  .vorta-invite__title { margin:0; font-size:18px; font-weight:700; color:var(--text,#1f2937); }
  .vorta-invite__subtitle { margin:4px 0 0; font-size:14px; color:var(--text-muted,#6b7280); }
  .vorta-invite__form { display:flex; flex-wrap:wrap; gap:12px; margin-top:18px; }
  .vorta-invite__field { flex:1 1 240px; min-width:0; }
  .vorta-invite__field input, .vorta-invite__field select { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid var(--border,#e5e7eb); border-radius:10px; background:var(--surface,#fff); color:var(--text,#1f2937); font-size:14px; }
  .vorta-invite__submit { padding:10px 18px; border:0; border-radius:10px; background:#4f46e5; color:#fff; font-size:14px; font-weight:700; cursor:pointer; }
  .vorta-invite__submit:hover { background:#4338ca; }
  .vorta-invite__pending { margin-top:18px; padding-top:16px; border-top:1px solid var(--border,#e5e7eb); }
  .vorta-invite__pending-title { margin:0 0 8px; font-size:13px; font-weight:700; color:var(--text,#1f2937); }
  .vorta-invite__pending-list { display:flex; flex-wrap:wrap; gap:8px; }
  .vorta-invite__pending-item { padding:7px 10px; border-radius:8px; background:var(--surface-3,#f3f4f6); color:var(--text-muted,#6b7280); font-size:12px; }
</style>

<section class="vorta-invite">
  <h2 class="vorta-invite__title">Invite employees</h2>
  <p class="vorta-invite__subtitle">Create a secure sign-up link so employees can set their own password.</p>
  <form method="POST" class="vorta-invite__form">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="user_invite">
    <label class="vorta-invite__field">
      <span class="block text-sm font-medium text-gray-700 mb-1">Employee email</span>
      <input type="email" name="invite_email" placeholder="employee@example.com" required>
    </label>
    <label class="vorta-invite__field">
      <span class="block text-sm font-medium text-gray-700 mb-1">Role</span>
      <select name="invite_role">
        <option value="staff">Staff</option>
        <option value="manager">Manager</option>
        <option value="admin">Admin</option>
      </select>
    </label>
    <div class="flex items-end">
      <button type="submit" class="vorta-invite__submit">Create invite</button>
    </div>
  </form>
  <?php if ($pendingInvites): ?>
    <div class="vorta-invite__pending">
      <p class="vorta-invite__pending-title">Pending invitations</p>
      <div class="vorta-invite__pending-list">
        <?php foreach ($pendingInvites as $pending): ?>
          <span class="vorta-invite__pending-item">
            <?= htmlspecialchars($pending['email']) ?> · <?= htmlspecialchars(ucfirst($pending['role'])) ?> · expires <?= htmlspecialchars(date('M j, Y H:i', strtotime($pending['expires_at']))) ?>
            <form method="POST" style="display:inline;margin-left:8px">
              <?= csrf_field() ?>
              <button type="submit" name="resend_invite" value="<?= (int)$pending['invitation_id'] ?>" style="border:0;background:none;color:#4f46e5;font-size:12px;cursor:pointer">Resend</button>
            </form>
            <form method="POST" style="display:inline;margin-left:8px">
              <?= csrf_field() ?>
              <button type="submit" name="revoke_invite" value="<?= (int)$pending['invitation_id'] ?>" style="border:0;background:none;color:#b91c1c;font-size:12px;cursor:pointer">Revoke</button>
            </form>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php if ($inviteUrl): ?>
  <script>
    document.getElementById('copy-invite')?.addEventListener('click', async function () {
      const input = document.getElementById('invite-url');
      try {
        await navigator.clipboard.writeText(input.value);
        this.textContent = 'Copied';
        setTimeout(() => { this.textContent = 'Copy'; }, 1800);
      } catch (error) {
        input.select();
        document.execCommand('copy');
        this.textContent = 'Copied';
      }
    });
  </script>
<?php endif; ?>

<div id="user-form-section" class="bg-gray-50 p-6 rounded-lg mb-8">
  <h2 class="text-lg font-semibold text-gray-800 mb-4" id="form-title">
    Added New User
  </h2>
  <form method="POST" id="user-form">
    <?= csrf_field() ?>
    <input type="hidden" name="entity" value="users">
    <input type="hidden" name="action" value="create" id="action-input">
    <input type="hidden" name="user_id" value="" id="user-id-input">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input type="text" name="name" id="user-name"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="...." required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" id="user-email"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="example@domain.com" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" id="user-password"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          placeholder="At least <?= ACCOUNT_MIN_PASSWORD_LENGTH ?> characters">
        <p class="text-xs text-gray-500 mt-1">Required when adding a user; leave blank only when editing without changing it.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
        <select name="role" id="user-role"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="staff">Staff</option>
          <option value="manager">Manager</option>
          <option value="admin">Admin</option>
        </select>
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700" id="submit-btn">
        Add
      </button>
      <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 hidden">
        Cancel
      </button>
    </div>
  </form>
</div>

<div class="mb-6">
  <form method="GET" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="tab" value="users">
    <input
      type="text"
      name="search"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search By Name..."
      class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
      Search
    </button>
    <?php if ($search): ?>
      <a href="?tab=users" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
        Clear
      </a>
    <?php endif; ?>
  </form>
</div>

<div class="bg-white rounded-xl shadow-md overflow-hidden">
  <div class="p-6 md:p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">User Management</h2>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left border-b border-gray-200">
            <th class="pb-3 font-medium text-gray-600">ID</th>
            <th class="pb-3 font-medium text-gray-600">Name</th>
            <th class="pb-3 font-medium text-gray-600">Email</th>
            <th class="pb-3 font-medium text-gray-600">Role</th>
            <th class="pb-3 font-medium text-gray-600">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="5" class="py-6 text-center text-sm text-gray-400">
                No users found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= (int)$u['user_id'] ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                  <?= htmlspecialchars($u['name']) ?>
                </td>
                <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                  <?= htmlspecialchars($u['email']) ?>
                </td>
                <td class="py-4 whitespace-nowrap">
                  <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                    <?= htmlspecialchars($u['role']) ?>
                  </span>
                </td>
                <td class="py-4 whitespace-nowrap space-x-1">
                  <button
                    type="button"
                    onclick="editUser(<?= (int)$u['user_id'] ?>, <?= htmlspecialchars(json_encode($u['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($u['email'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($u['role'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)"
                    class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600 transition">
                    Edit
                  </button>
                  <button
                    type="button"
                    onclick="confirmDelete(<?= (int)$u['user_id'] ?>, <?= htmlspecialchars(json_encode($u['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)"
                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition">
                    Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-6 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="text-sm text-gray-600">
      Page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span>
    </div>

    <ul class="flex flex-wrap items-center gap-2">
      <li>
        <a href="<?= $page > 1 ? page_url(1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page > 1 ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">&laquo; First</span>
          <span class="sm:hidden">&laquo;</span>
        </a>
      </li>

      <li>
        <a href="<?= $page > 1 ? page_url($page - 1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page > 1 ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">&lsaquo; Prev</span>
          <span class="sm:hidden">&lsaquo;</span>
        </a>
      </li>

      <?php
      $start = max(1, $page - 2);
      $end = min($totalPages, $page + 2);

      if ($start > 1) {
        echo '<li><a class="px-3 py-1 rounded border hover:bg-gray-100" href="' . page_url(1) . '">1</a></li>';
        if ($start > 2) echo '<li class="px-2">...</li>';
      }

      for ($p = $start; $p <= $end; $p++): ?>
        <li>
          <a href="<?= page_url($p) ?>"
            class="px-3 py-1 rounded border <?= $p === $page ? 'bg-indigo-600 text-white' : 'hover:bg-gray-100' ?>">
            <?= $p ?>
          </a>
        </li>
      <?php endfor;

      if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<li class="px-2">...</li>';
        echo '<li><a class="px-3 py-1 rounded border hover:bg-gray-100" href="' . page_url($totalPages) . '">' . $totalPages . '</a></li>';
      }
      ?>

      <li>
        <a href="<?= $page < $totalPages ? page_url($page + 1) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page < $totalPages ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">Next &rsaquo;</span>
          <span class="sm:hidden">&rsaquo;</span>
        </a>
      </li>

      <li>
        <a href="<?= $page < $totalPages ? page_url($totalPages) : 'javascript:void(0)' ?>"
          class="px-3 py-1 rounded border <?= $page < $totalPages ? 'hover:bg-gray-100' : 'opacity-50 cursor-not-allowed' ?>">
          <span class="hidden sm:inline">Last &raquo;</span>
          <span class="sm:hidden">&raquo;</span>
        </a>
      </li>
    </ul>
  </nav>
<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function editUser(id, name, email, role) {
    document.getElementById('form-title').textContent = 'Edit User';
    document.getElementById('user-name').value = name;
    document.getElementById('user-email').value = email;
    document.getElementById('user-role').value = role;
    document.getElementById('action-input').value = 'update';
    document.getElementById('user-id-input').value = id;
    document.getElementById('submit-btn').textContent = 'Update';
    document.getElementById('cancel-edit').classList.remove('hidden');

    document.getElementById('user-form-section').scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    });
  }

  document.getElementById('cancel-edit')?.addEventListener('click', function() {
    document.getElementById('user-form').reset();
    document.getElementById('form-title').textContent = 'Add New User';
    document.getElementById('action-input').value = 'create';
    document.getElementById('user-id-input').value = '';
    document.getElementById('user-role').value = 'staff';
    document.getElementById('submit-btn').textContent = 'Add';
    this.classList.add('hidden');
  });

  function submitDelete(name, value) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    [
      ['csrf_token', document.querySelector('input[name="csrf_token"]').value],
      [name, value]
    ].forEach(([key, val]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = val;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  }

  function confirmDelete(id, name) {
    Swal.fire({
      title: 'Delete this record?',
      text: `You are about to delete user: "${name}"`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        submitDelete('delete_user', id);
      }
    });
  }
</script>