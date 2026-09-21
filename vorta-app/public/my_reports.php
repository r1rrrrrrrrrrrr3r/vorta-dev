<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$month = $_GET['month'] ?? date('Y-m');
$start = $month . "-01";
$end = date('Y-m-t', strtotime($start));
$limit = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$monthlyTarget = settings_get_monthly_target($pdo);
$dailyMin = settings_get_daily_min_reports($pdo);
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM production_reports WHERE user_id = ? AND report_date BETWEEN ? AND ?");
$totalStmt->execute([$user_id, $start, $end]);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

$stmt = $pdo->prepare("SELECT 
        pr.*,
        wf.workforce_name
    FROM production_reports pr
    LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
    WHERE pr.user_id = ? 
      AND pr.report_date BETWEEN ? AND ?
    ORDER BY pr.report_date DESC, pr.report_id DESC
    LIMIT $limit OFFSET $offset");
$stmt->execute([$user_id, $start, $end]);
$rows = $stmt->fetchAll();

$stmt2 = $pdo->prepare("SELECT DATE(report_date) d, COUNT(*) c 
                        FROM production_reports 
                        WHERE user_id = ? AND report_date BETWEEN ? AND ? 
                        GROUP BY DATE(report_date)");
$stmt2->execute([$user_id, $start, $end]);
$daily = $stmt2->fetchAll();

$labels = [];
$data = [];
foreach ($daily as $r) {
  $labels[] = $r['d'];
  $data[] = (int)$r['c'];
}
include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vorta Prodtracker - My Reports</title>
  <link rel="stylesheet" href="css/output.css">
</head>

<body>
  <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 md:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <div>
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800">My Reports</h1>
            <div class="flex items-center gap-4 mt-2">
              <span class="text-base md:text-lg font-semibold text-indigo-600"><?php echo htmlspecialchars($month) ?></span>
              <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-sm font-medium rounded-full">
                Total: <?php echo (int)$total ?> item
              </span>
            </div>
          </div>
          <form class="flex flex-col sm:flex-row items-center gap-2">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month) ?>"
              class="px-3 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <button type="submit" class="px-4 py-2 w-full md:w-[69px] bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
              Filter
            </button>
          </form>
        </div>

        <div class="bg-indigo-50 p-4 rounded-lg mb-6">
          <p class="text-sm text-indigo-800">
            <span class="font-medium">Monthly Target:</span>
            <?= (int) $monthlyTarget['min'] ?>–<?= (int) $monthlyTarget['max'] ?> items (minimum <?= $dailyMin ?> item<?= $dailyMin > 1 ? 's' : '' ?> per day)
          </p>
        </div>

        <div class="h-64">
          <canvas id="chart"></canvas>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 md:p-8">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Entry Details</h2>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left border-b border-gray-200">
                <th class="pb-3 font-medium text-gray-600">Date</th>
                <th class="pb-3 font-medium text-gray-600">Type</th>
                <th class="pb-3 font-medium text-gray-600">Title</th>
                <th class="pb-3 font-medium text-gray-600">Work Force</th>
                <th class="pb-3 font-medium text-gray-600">Status</th>
                <th class="pb-3 font-medium text-gray-600">Proof</th>
                <th class="pb-3 font-medium text-gray-600">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50 transition" id="row-<?php echo $r['report_id']; ?>">
                  <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                    <?php echo htmlspecialchars($r['report_date']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                    <?php echo htmlspecialchars($r['job_type']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-sm text-gray-800">
                    <?php echo htmlspecialchars($r['title']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-[10px] sm:text-sm font-medium text-gray-800">
                    <?php echo htmlspecialchars($r['workforce_name'] ?? '-'); ?>
                  </td>
                  <td class="py-4 whitespace-nowrap">
                    <span
                      class="status-badge px-2.5 py-1 rounded-full text-xs font-medium 
      <?php echo $r['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>"
                      id="status-<?php echo $r['report_id']; ?>">
                      <?php echo htmlspecialchars($r['status']); ?>
                    </span>
                  </td>

                  <td class="py-4 whitespace-nowrap">
                    <?php if ($r['proof_link']): ?>
                      <button type="button"
                        onclick="showProofLink(this.dataset.url)"
                        data-url="<?php echo htmlspecialchars($r['proof_link'], ENT_QUOTES) ?>"
                        class="text-indigo-600 hover:text-indigo-800 text-sm font-medium hover:underline transition">
                        View
                      </button>
                    <?php elseif ($r['proof_image']): ?>
                      <button onclick="openModal(<?php echo $r['report_id'] ?>)"
                        class="px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-sm transition">
                        See Picture
                      </button>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm">-</span>
                    <?php endif; ?>
                  </td>

                  <td class="py-4 whitespace-nowrap space-x-1">
                    <?php if ($r['status'] === 'Progress'): ?>
                      <button
                        onclick="markAsDone(<?php echo $r['report_id']; ?>, this)"
                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition">
                        Mark Complete
                      </button>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm">Completed</span>
                    <?php endif; ?>

                    <a href="edit_report.php?id=<?php echo $r['report_id']; ?>"
                      class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600 transition">
                      Edit
                    </a>

                    <button
                      onclick="deleteReport(<?php echo $r['report_id']; ?>, this)"
                      class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition"
                      title="Delete report">
                      Delete
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
          <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
            <div class="text-sm text-gray-600 whitespace-nowrap">
              Showing <?= count($rows) ?> of <?= $total ?> records (Page <?= $page ?> of <?= $totalPages ?>)
            </div>
            <nav class="flex flex-wrap justify-center gap-1">
              <?php if ($page > 1): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=1"
                  class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                  &laquo; First
                </a>
              <?php else: ?>
                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                  &laquo; First
                </span>
              <?php endif; ?>
  
              <?php if ($page > 1): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page - 1 ?>"
                  class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                  &lsaquo; Prev
                </a>
              <?php else: ?>
                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                  &lsaquo; Prev
                </span>
              <?php endif; ?>
  
              <?php
              $startPage = max(1, $page - 2);
              $endPage = min($totalPages, $page + 2);
  
              for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $page): ?>
                  <span class="px-3 py-2 bg-indigo-600 text-white border border-gray-300 rounded-lg text-sm font-medium">
                    <?= $i ?>
                  </span>
                <?php else: ?>
                  <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $i ?>"
                    class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                    <?= $i ?>
                  </a>
                <?php endif; ?>
              <?php endfor; ?>
  
              <?php if ($page < $totalPages): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $page + 1 ?>"
                  class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                  Next &rsaquo;
                </a>
              <?php else: ?>
                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                  Next &rsaquo;
                </span>
              <?php endif; ?>
  
              <?php if ($page < $totalPages): ?>
                <a href="?month=<?= htmlspecialchars($month) ?>&page=<?= $totalPages ?>"
                  class="px-3 py-2 bg-white text-indigo-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium transition">
                  Last &raquo;
                </a>
              <?php else: ?>
                <span class="px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 rounded-lg text-sm font-medium cursor-not-allowed">
                  Last &raquo;
                </span>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</body>

</html>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  const labels = <?php echo json_encode($labels); ?>;
  const data = <?php echo json_encode($data); ?>;
  new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Items per Day',
        data,
        backgroundColor: 'rgba(79, 70, 229, 0.7)',
        borderColor: 'rgba(79, 70, 229, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      },
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });

  function markAsDone(reportId, btn) {
    Swal.fire({
      title: 'Are you sure?',
      text: "Mark this report as completed?",
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, mark it!',
      cancelButtonText: 'Cancel'
    }).then((res) => {
      if (res.isConfirmed) {
        const originalText = btn.textContent;
        btn.textContent = 'Processing...';
        btn.disabled = true;

        fetch('update_status_ajax.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'report_id=' + reportId + '&action=mark_done'
          })
          .then(r => r.json())
          .then(d => {
            if (d.success) {
              const statusEl = document.getElementById('status-' + reportId);
              statusEl.textContent = 'Completed';
              statusEl.className = 'status-badge px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800';
              btn.textContent = 'Completed';
              btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
              btn.classList.add('bg-gray-400', 'text-gray-700', 'cursor-not-allowed');
              Swal.fire('Success!', 'Report status updated.', 'success');
            } else {
              Swal.fire('Failed!', d.message, 'error');
              btn.textContent = originalText;
              btn.disabled = false;
            }
          })
          .catch(e => {
            console.error(e);
            Swal.fire('Error', 'A connection error occurred.', 'error');
            btn.textContent = originalText;
            btn.disabled = false;
          });
      }
    });
  }

  function deleteReport(reportId, btn) {
    Swal.fire({
      title: 'Are you sure?',
      text: "This report will be permanently deleted!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((res) => {
      if (res.isConfirmed) {
        const row = document.getElementById('row-' + reportId);
        const originalText = btn.textContent;
        btn.textContent = 'Deleting...';
        btn.disabled = true;

        fetch('delete_report_ajax.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'report_id=' + reportId
          })
          .then(r => r.json())
          .then(d => {
            if (d.success) {
              row.classList.add('bg-red-50', 'animate-pulse');
              setTimeout(() => {
                row.remove();
                Swal.fire('Deleted!', 'Report successfully deleted.', 'success');
              }, 300);
            } else {
              Swal.fire('Failed!', d.message, 'error');
              btn.textContent = originalText;
              btn.disabled = false;
            }
          })
          .catch(e => {
            console.error(e);
            Swal.fire('Error', 'A connection error occurred.', 'error');
            btn.textContent = originalText;
            btn.disabled = false;
          });
      }
    });
  }
</script>

<div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
  <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-bold text-gray-800">Report Detail</h3>
          <button onclick="closeModal()" aria-label="Close"
            class="flex items-center justify-center w-9 h-9 rounded-full text-2xl leading-none text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition">
            &times;
          </button>
      </div>
      <div id="modalContent"></div>
    </div>
  </div>
</div>

<script>
  function openModal(reportId) {
    fetch(`get_report_detail_user.php?id=${reportId}`, {
        credentials: 'same-origin'
      })
      .then(r => r.text())
      .then(d => {
        document.getElementById('modalContent').innerHTML = d;
        document.getElementById('reportModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      })
      .catch(err => {
        console.error(err);
        document.getElementById('modalContent').innerHTML = '<p class="text-red-600">An error occurred while loading the data</p>';
      });
  }

  function closeModal() {
    document.getElementById('reportModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
  }


  function showProofLink(url) {
    Swal.fire({
      title: 'Report Proof',
      html: '<p class="text-sm" style="margin-bottom:.5rem;">Proof link for this report:</p>'
        + '<a href="' + encodeURI(url) + '" target="_blank" rel="noopener noreferrer"'
        + ' style="color:#4f46e5;text-decoration:underline;word-break:break-all;">'
        + $escapeHtml(url) + '</a>',
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'Open Link',
      cancelButtonText: 'Close',
      confirmButtonColor: '#4f46e5'
    }).then((result) => {
      if (result.isConfirmed) {
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    });
  }


  function $escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
</script>

<?php include __DIR__ . '/footer.php'; ?>