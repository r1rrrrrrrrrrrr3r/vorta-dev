<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_login();

$user_id = $_SESSION['user']['user_id'];

// Ambil employee_id dari users -> employees
$stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
  die("Data karyawan tidak ditemukan.");
}

$employee_id = $employee['employee_id'];

// Ambil semua work_force 
$stmt = $pdo->query("SELECT workforce_id, workforce_name FROM work_force ORDER BY workforce_name");
$work_forces = $stmt->fetchAll(PDO::FETCH_ASSOC);

//colong table job_type
$stmt = $pdo->query("SELECT job_type_id, name FROM job_type ORDER BY name");
$job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>vorta Prodtracker - Report Form</title>
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

          <!-- Date & Job Type -->
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
                <p class="text-red-500 text-sm mt-1">Belum ada job type yang terdaftar.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Title & Work Force -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Title/Menu/Layar</label>
              <input type="text" name="title"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
                placeholder="Example: Login Page, Fitur Export PDF" required>
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
                <p class="text-red-500 text-sm mt-1">Anda belum terdaftar di work force manapun.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
            <textarea name="description" rows="4"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150"
              placeholder="Describe task..."></textarea>
          </div>

          <!-- Status & Proof Link -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
              <select name="status"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                <option value="Progress">Progress</option>
                <option value="Selesai">Selesai</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Proof (URL repo/screenshot)</label>
              <input type="url" name="proof_link" placeholder="https://..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
            </div>
          </div>

          <!-- Proof Image Upload -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Proof (Foto)</label>
            <div class="flex items-center gap-2">
              <input type="file" id="proof_image" name="proof_image" accept="image/*"
                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
              <button type="button" onclick="clearFileInput()"
                class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                ✕
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, JPEG (max size equals server size)</p>
          </div>

          <script>
            function clearFileInput() {
              const fileInput = document.getElementById('proof_image');
              fileInput.value = ""; // kosongkan isi input file
            }
          </script>


          <!-- Submit Button -->
          <div class="pt-2">
            <button type="submit" id="submitBtn"
              class="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 ease-in-out transform hover:scale-105">
              Save Report
            </button>
          </div>
        </form>

        <!-- Policy Note -->
        <div class="mt-8 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
          <p class="text-sm text-indigo-800 font-medium">
            📌 <strong>Policy:</strong> Minimum 2 reports per day. Monthly Target: 50–88 item.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>

<script>
  // Ambil form
  const form = document.querySelector('form');
  const submitBtn = document.getElementById('submitBtn');

  form.addEventListener('submit', function(e) {
    e.preventDefault(); // Cegah submit langsung

    const fileInput = form.querySelector('input[name="proof_image"]');
    const file = fileInput.files[0];
    const maxSize = 1048576; // 1 MB

    // Validasi file
    if (file) {
      if (file.size > maxSize) {
        Swal.fire({
          icon: 'error',
          title: 'Ukuran file terlalu besar!',
          text: 'Maksimal 1 MB.',
          confirmButtonText: 'Oke'
        });
        return;
      }

      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        Swal.fire({
          icon: 'error',
          title: 'Format tidak didukung!',
          text: 'Gunakan JPG, PNG, atau WebP.',
          confirmButtonText: 'Oke'
        });
        return;
      }
    }

    // Konfirmasi submit
    Swal.fire({
      title: 'Apakah data sudah benar?',
      text: "Anda akan mengunggah laporan ini.",
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, Simpan',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#4f46e5',
      cancelButtonColor: '#d946ef'
    }).then((result) => {
      if (result.isConfirmed) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sedang menyimpan...';
        form.submit(); // Submit form
      }
    });
  });

  // Optional: Preview gambar
  document.querySelector('input[name="proof_image"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function() {
        Swal.fire({
          title: 'Pratinjau Gambar',
          imageUrl: reader.result,
          imageAlt: 'Preview',
          confirmButtonText: 'Tutup',
          width: 500
        });
      };
      reader.readAsDataURL(file);
    }
  });
</script>
<?php include __DIR__ . '/footer.php'; ?>