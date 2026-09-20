<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/account.php';
require_login();

$user_id = $_SESSION['user']['user_id'] ?? 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = account_change_password(
        $pdo,
        (int) $user_id,
        $_POST['current_password'] ?? '',
        $_POST['new_password'] ?? '',
        $_POST['confirm_password'] ?? ''
    );
    if ($result['ok']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

include __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - Change Password</title>
    <link rel="stylesheet" href="css/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 44px; }
        .pw-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--text-faint, #6b7280);
            cursor: pointer;
            padding: 6px;
            line-height: 1;
        }
        .pw-toggle:hover { color: var(--brand, #4f46e5); }
        .pw-toggle .fas { width: 16px; height: 16px; font-size: 15px; }
        .pw-hint { font-size: 12px; margin-top: 6px; }
        .pw-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>

<body>
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-8">

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex items-center gap-3 mb-1">
                    <a href="profile.php" class="text-gray-500 hover:text-indigo-600 transition">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
                </div>
                <p class="text-sm text-gray-600">
                    Your current password is required. After saving you can keep using your session.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 md:p-8">

                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="passwordForm" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                        <div class="pw-wrap">
                            <input type="password" name="current_password" id="current_password" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                            <button type="button" class="pw-toggle" data-target="current_password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <div class="pw-wrap">
                                <input type="password" name="new_password" id="new_password" required
                                    minlength="<?= ACCOUNT_MIN_PASSWORD_LENGTH ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                <button type="button" class="pw-toggle" data-target="new_password" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Minimum <?= ACCOUNT_MIN_PASSWORD_LENGTH ?> characters.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                            <div class="pw-wrap">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="pw-hint text-gray-500" id="matchHint"></p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row flex-wrap gap-3 pt-6 border-t border-gray-200">
                        <button type="submit" id="passwordSubmit"
                            class="pw-btn px-8 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                            Update Password
                        </button>
                        <a href="profile.php"
                            class="pw-btn px-8 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        (function () {
            const form = document.getElementById('passwordForm');
            const newPw = document.getElementById('new_password');
            const confirmPw = document.getElementById('confirm_password');
            const hint = document.getElementById('matchHint');
            const submit = document.getElementById('passwordSubmit');
            let confirmed = false;

            document.querySelectorAll('.pw-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = document.getElementById(btn.dataset.target);
                    if (!input) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.innerHTML = show
                        ? '<i class="fas fa-eye-slash"></i>'
                        : '<i class="fas fa-eye"></i>';
                    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            });

            function setHint(text, ok) {
                if (!hint) return;
                hint.textContent = text || '';
                hint.style.color = ok ? '#15803d' : '#b91c1c';
            }

            function checkMatch() {
                if (confirmPw.value === '') {
                    setHint('');
                    submit.disabled = false;
                    submit.style.opacity = '1';
                    submit.style.cursor = 'pointer';
                    return;
                }
                const ok = newPw.value === confirmPw.value;
                setHint(ok ? 'Passwords match.' : 'Passwords do not match.', ok);
                submit.disabled = !ok;
                submit.style.opacity = ok ? '1' : '.6';
                submit.style.cursor = ok ? 'pointer' : 'not-allowed';
            }

            newPw?.addEventListener('input', checkMatch);
            confirmPw?.addEventListener('input', checkMatch);

            form?.addEventListener('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();

                if (newPw.value !== confirmPw.value) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Passwords do not match',
                        text: 'Please re-enter the confirmation password.',
                        confirmButtonColor: '#4f46e5'
                    });
                    return;
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Change your password?',
                    text: 'You will need the new password the next time you sign in.',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, change it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#4f46e5',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        icon: 'question',
                        title: 'Final confirmation',
                        text: 'This replaces your current password. Continue?',
                        showCancelButton: true,
                        confirmButtonText: 'Confirm and save',
                        cancelButtonText: 'Go back',
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true
                    }).then(function (second) {
                        if (!second.isConfirmed) return;
                        confirmed = true;
                        submit.disabled = true;
                        submit.textContent = 'Saving...';
                        form.submit();
                    });
                });
            });
        })();
    </script>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>