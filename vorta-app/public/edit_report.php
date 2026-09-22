<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/csrf.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$report_id = $_GET['id'] ?? null;
$monthlyTarget = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
if (!$report_id) {
    die("Report ID not provided.");
}

$stmt = $pdo->prepare("SELECT pr.*, wf.workforce_name 
                       FROM production_reports pr
                       LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
                       WHERE pr.report_id = ? AND pr.user_id = ?");
$stmt->execute([$report_id, $user_id]);
$report = $stmt->fetch();

if (!$report) {
    die("Report not found or you do not have access.");
}

if ($report['status'] !== 'Progress') {
    header("Location: my_reports.php");
    exit;
}

$job_types_stmt = $pdo->query("SELECT job_type_id, name FROM job_type ORDER BY name");
$job_types = $job_types_stmt->fetchAll();

$workforce_stmt = $pdo->query("SELECT workforce_id, workforce_name FROM work_force ORDER BY workforce_name");
$workforces = $workforce_stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorta Prodtracker - Edit Report</title>
    <link rel="stylesheet" href="css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <div class="p-6 md:p-8">
                <h1 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4 border-gray-200">
                    Edit Daily Report
                </h1>

                <form action="update_report.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$report['report_id'] ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                            <input type="date" name="report_date" value="<?= htmlspecialchars($report['report_date']) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                                required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Job Type</label>
                            <select name="job_type"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                                required>
                                <option value="">-- Select Job Type --</option>
                                <?php foreach ($job_types as $jt): ?>
                                    <option value="<?= htmlspecialchars($jt['name']) ?>"
                                        <?= ($jt['name'] === $report['job_type']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($job_types)): ?>
                                <p class="text-red-500 text-sm mt-1">No job types registered yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Title/Menu/Screen</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($report['title']) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                                placeholder="Example: Login Page, Export PDF Feature" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Work Force</label>
                            <select name="workforce_id"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                                required>
                                <option value="">-- Select Work Force --</option>
                                <?php foreach ($workforces as $wf): ?>
                                    <option value="<?= (int)$wf['workforce_id'] ?>" <?= $wf['workforce_id'] == $report['workforce_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wf['workforce_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($workforces)): ?>
                                <p class="text-red-500 text-sm mt-1">You are not registered under any work force yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="4"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                            placeholder="Describe task..."><?= htmlspecialchars($report['description'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select name="status"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                                <option value="Progress" <?= $report['status'] === 'Progress' ? 'selected' : '' ?>>Progress</option>
                                <option value="Completed" <?= $report['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Proof (URL repo/screenshot)</label>
                            <input type="url" name="proof_link" value="<?= htmlspecialchars($report['proof_link'] ?? '') ?>" placeholder="https://..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Proof (Photo)</label>
                        <?php if (!empty($report['proof_image'])): ?>
                            <div class="mb-3">
                                <img src="../uploads/<?= htmlspecialchars(rawurlencode(basename($report['proof_image']))) ?>" alt="Current Proof"
                                    class="max-w-xs h-auto rounded border shadow-sm">
                                <p class="text-xs text-gray-500 mt-1">Current image. Leave empty to keep using this image.</p>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="proof_image" accept="image/*"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                            onchange="validateFileSize(this)">
                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, JPEG (max. 1MB)</p>
                    </div>

                    <div class="flex flex-col md:flex-row pt-2 gap-3 text-center">
                        <button type="submit" id="submitBtn"
                            class="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm md:text-base font-semibold rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 ease-in-out transform hover:scale-105">
                            Update Report
                        </button>
                        <a href="my_reports.php" class="w-full md:w-auto px-8 py-3 bg-gray-400 hover:bg-gray-500 text-sm md:text-base text-white font-semibold rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-200 ease-in-out transform hover:scale-105">
                            Back to Report
                        </a>
                    </div>
                </form>

                <div class="mt-8 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <p class="text-sm text-indigo-800 font-medium">
                        <strong>Policy:</strong> Minimum <?= $dailyMin ?> report<?= $dailyMin > 1 ? 's' : '' ?> per day. Monthly Target:
                        <?= (int) $monthlyTarget['min'] ?>–<?= (int) $monthlyTarget['max'] ?> item.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function validateFileSize(input) {
            if (input.files && input.files[0]) {
                const maxSize = 1 * 1024 * 1024;
                if (input.files[0].size > maxSize) {
                    Swal.fire('Error', 'File too large. Maximum 1MB.', 'error');
                    input.value = '';
                }
            }
        }
    </script>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>