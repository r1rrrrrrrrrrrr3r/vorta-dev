<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/tenant.php';
require_login();
$company_id = current_company_id();

$user_id = $_SESSION['user']['user_id'];
$monthlyTarget = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
$stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
  die("Employee record not found.");
}

$employee_id = $employee['employee_id'];

$stmt = $pdo->prepare("SELECT workforce_id, workforce_name FROM work_force WHERE company_id = ? ORDER BY workforce_name");
$stmt->execute([$company_id]);
$work_forces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT job_type_id, name FROM job_type WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vorta Prodtracker - Report Form</title>
  <link rel="stylesheet" href="css/output.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="p-6 md:p-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4 border-gray-200">
          Daily Report Input
        </h1>

        <form action="save_report.php" method="POST" enctype="multipart/form-data" class="space-y-6">
          <?= csrf_field() ?>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
              <input type="date" name="report_date" value="<?= date('Y-m-d') ?>"
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
                  <option value="<?= $jt['job_type_id'] ?>">
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
              <input type="text" name="title"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                placeholder="Example: Login Page, Export PDF Feature" required>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Work Force</label>
              <select name="workforce_id"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                required>
                <option value="">-- Select Work Force --</option>
                <?php foreach ($work_forces as $wf): ?>
                  <option value="<?= $wf['workforce_id'] ?>">
                    <?= htmlspecialchars($wf['workforce_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($work_forces)): ?>
                <p class="text-red-500 text-sm mt-1">You are not registered under any work force yet.</p>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
            <textarea name="description" rows="4"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
              placeholder="Describe task..."></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
              <select name="status"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                <option value="Progress">Progress</option>
                <option value="Completed">Completed</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Proof (URL repo/screenshot)</label>
              <input type="url" name="proof_link" placeholder="https://..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Proof (Photo)</label>
            <div class="flex items-center gap-2">
              <input type="file" id="proof_image" name="proof_image" accept="image/*"
                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
              <button type="button" onclick="clearFileInput()"
                class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                Clear
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, JPEG (max size equals server size)</p>
          </div>

          <script>
            function clearFileInput() {
              const fileInput = document.getElementById('proof_image');
              fileInput.value = "";
            }
          </script>


          <div class="pt-2">
            <button type="submit" id="submitBtn"
              class="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 ease-in-out transform hover:scale-105">
              Save Report
            </button>
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
</body>

</html>

<script>
  const form = document.querySelector('form');
  const submitBtn = document.getElementById('submitBtn');

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    const fileInput = form.querySelector('input[name="proof_image"]');
    const file = fileInput.files[0];
    const maxSize = 1048576;

    if (file) {
      if (file.size > maxSize) {
        Swal.fire({
          icon: 'error',
          title: 'File too large!',
          text: 'Maximum size is 1 MB.',
          confirmButtonText: 'OK'
        });
        return;
      }

      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        Swal.fire({
          icon: 'error',
          title: 'Unsupported format!',
          text: 'Please use JPG, PNG, or WebP.',
          confirmButtonText: 'OK'
        });
        return;
      }
    }

    Swal.fire({
      title: 'Is the data correct?',
      text: "You are about to submit this report.",
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Save',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#4f46e5',
      cancelButtonColor: '#d946ef'
    }).then((result) => {
      if (result.isConfirmed) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        form.submit();
      }
    });
  });

  document.querySelector('input[name="proof_image"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function() {
        Swal.fire({
          title: 'Image Preview',
          imageUrl: reader.result,
          imageAlt: 'Preview',
          confirmButtonText: 'Close',
          width: 500
        });
      };
      reader.readAsDataURL(file);
    }
  });
</script>
<?php include __DIR__ . '/footer.php'; ?>