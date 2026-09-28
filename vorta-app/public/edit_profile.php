<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/account.php';
require_login();

$user_id = $_SESSION['user']['user_id'] ?? 0;

$profileError = '';
$profileSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = account_update_profile(
        $pdo,
        (int) $user_id,
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['phone'] ?? ''
    );
    if ($result['ok']) {
        $profileSuccess = $result['message'];
        $_SESSION['user']['name'] = trim($_POST['name']);
        $_SESSION['user']['email'] = trim($_POST['email']);
    } else {
        $profileError = $result['message'];
    }
}

$stmt = $pdo->prepare("
    SELECT
        u.email,
        u.role,
        COALESCE(e.name, u.name) AS name,
        e.phone,
        e.position
    FROM users u
    LEFT JOIN employees e ON u.user_id = e.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$name = $user['name'];
$email = $user['email'];
$phone = $user['phone'] ?? '';
$position = $user['position'] ?? '';
$role = $user['role'] ?? '';
$initial = strtoupper(mb_substr(trim($name) !== '' ? $name : 'U', 0, 1));

include __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - Account Settings</title>
    <link rel="stylesheet" href="css/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .acct-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            flex: 0 0 56px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            font-size: 22px;
            font-weight: 600;
            user-select: none;
        }
        .acct-chip {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: capitalize;
        }
        .acct-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>

<body>
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="acct-avatar"><?= htmlspecialchars($initial) ?></span>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Account Settings</h1>
                            <p class="text-sm text-gray-600 mt-1">
                                <?= htmlspecialchars($email) ?>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php if ($position !== ''): ?>
                                    <span class="acct-chip bg-indigo-100 text-indigo-800"><?= htmlspecialchars($position) ?></span>
                                <?php endif; ?>
                                <?php if ($role !== ''): ?>
                                    <span class="acct-chip bg-gray-100 text-gray-700"><?= htmlspecialchars($role) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="profile.php"
                        class="acct-btn px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium whitespace-nowrap">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <h2 class="text-xl font-bold text-gray-800 mb-1">Profile Information</h2>
                <p class="text-sm text-gray-600 mb-6">Update your name, email address, and contact number.</p>

                <?php if ($profileError): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg">
                        <?= htmlspecialchars($profileError) ?>
                    </div>
                <?php endif; ?>
                <?php if ($profileSuccess): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg">
                        <?= htmlspecialchars($profileSuccess) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($name) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                                placeholder="e.g. 081234567890">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                            required>
                    </div>

                    <?php if ($position !== ''): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Position</label>
                            <input type="text" value="<?= htmlspecialchars($position) ?>" disabled
                                class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1">Position is managed by an administrator.</p>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-col sm:flex-row flex-wrap gap-3 pt-6 border-t border-gray-200">
                        <button type="submit"
                            class="acct-btn px-8 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                            Save Changes
                        </button>
                        <a href="profile.php"
                            class="acct-btn px-8 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden" id="appearance">
            <div class="p-6 md:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
                    <h2 class="text-xl font-bold text-gray-800">Appearance</h2>
                    <span class="pref-saved" id="prefSaved">Saved</span>
                </div>
                <p class="text-sm text-gray-600 mb-6">
                    Changes apply immediately and are saved to your account, so they follow you on any device.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-1">Theme</p>
                        <p class="text-xs text-gray-500 mb-3">System follows your operating system setting.</p>
                        <div class="pref-switch" data-pref-switch="theme">
                            <button type="button" data-value="light" title="Light">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                <span>Light</span>
                            </button>
                            <button type="button" data-value="dark" title="Dark">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                <span>Dark</span>
                            </button>
                            <button type="button" data-value="system" title="Follow system">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <span>System</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-1">Navigation Layout</p>
                        <p class="text-xs text-gray-500 mb-3">Menu across the top, or a rail down the left side.</p>
                        <div class="pref-switch" data-pref-switch="layout">
                            <button type="button" data-value="navbar" title="Top navbar">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16M4 9h16M6 13h12M6 17h12"></path></svg>
                                <span>Navbar</span>
                            </button>
                            <button type="button" data-value="sidebar" title="Left sidebar">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h5v14H4zM12 7h8M12 12h8M12 17h8"></path></svg>
                                <span>Sidebar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden" id="security">
            <div class="p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-1">Security</h2>
                        <p class="text-sm text-gray-600">
                            Change your password on its own page. Your current password is required to confirm.
                        </p>
                    </div>
                    <a href="change_password.php"
                        class="acct-btn px-8 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium whitespace-nowrap">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </div>
        </div>
        </div>

    </div>

    <script>

        (function () {
            if (!window.VortaUI) return;

            const savedFlag = document.getElementById('prefSaved');
            let savedTimer = null;

            function flashSaved() {
                if (!savedFlag) return;
                savedFlag.classList.add('is-visible');
                clearTimeout(savedTimer);
                savedTimer = setTimeout(() => savedFlag.classList.remove('is-visible'), 1600);
            }

            function sync() {
                const theme = window.VortaUI.getThemePreference();
                const layout = window.VortaUI.getLayout();
                document.querySelectorAll('[data-pref-switch="theme"] button').forEach(b => {
                    b.classList.toggle('is-active', b.dataset.value === theme);
                });
                document.querySelectorAll('[data-pref-switch="layout"] button').forEach(b => {
                    b.classList.toggle('is-active', b.dataset.value === layout);
                });
            }

            document.querySelectorAll('[data-pref-switch="theme"] button').forEach(btn => {
                btn.addEventListener('click', () => {
                    window.VortaUI.setTheme(btn.dataset.value);
                    flashSaved();
                });
            });

            document.querySelectorAll('[data-pref-switch="layout"] button').forEach(btn => {
                btn.addEventListener('click', () => {
                    window.VortaUI.setLayout(btn.dataset.value);
                    flashSaved();
                });
            });

            document.addEventListener('vorta:uichange', sync);
            sync();
        })();

    </script>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>