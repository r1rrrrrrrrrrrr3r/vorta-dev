<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/settings.php';
require_login();

$user_id = $_SESSION['user']['user_id'];
$detailOwnerId = (int)$user_id;

$detailSelf = basename(__FILE__);

function report_detail_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function report_detail_fetch(PDO $pdo, int $reportId, ?int $ownerId): ?array
{
    $sql = "SELECT pr.*, u.name AS user_name, wf.workforce_name
            FROM production_reports pr
            LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
            JOIN users u ON u.user_id = pr.user_id
            WHERE pr.report_id = ?";
    $params = [$reportId];
    if ($ownerId !== null) {
        $sql .= " AND pr.user_id = ?";
        $params[] = $ownerId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetch();
    return $found ?: null;
}

if (isset($_GET['proof_image'])) {
    $row = report_detail_fetch($pdo, (int)$_GET['proof_image'], $detailOwnerId);
    $file = $row ? basename((string)$row['proof_image']) : '';
    $path = __DIR__ . '/../uploads/' . $file;
    $mime = ($file !== '' && is_file($path)) ? mime_content_type($path) : false;
    if (!$mime || strpos($mime, 'image/') !== 0) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

if (isset($_GET['detail'])) {
    $row = report_detail_fetch($pdo, (int)$_GET['detail'], $detailOwnerId);
    if (!$row) {
        http_response_code(404);
        echo '<p class="text-red-600">Report not found or access denied.</p>';
        exit;
    }

    $status = $row['status'] ?? 'Progress';
    $statusClass = $status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
    $timestamp = strtotime((string) $row['report_date']);
    $dateLabel = $timestamp ? date('d M Y', $timestamp) : '-';
    $description = trim((string) ($row['description'] ?? ''));
    $rawLink = trim((string) ($row['proof_link'] ?? ''));
    $isHttp = (bool) preg_match('#^https?://#i', $rawLink);
    $imageFile = basename(trim((string) ($row['proof_image'] ?? '')));
    $hasImage = $imageFile !== '';
    $imageExists = $hasImage && is_file(__DIR__ . '/../uploads/' . $imageFile);
    $imageUrl = $detailSelf . '?proof_image=' . (int) $row['report_id'];

    $card = 'background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;';
    $label = 'font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-faint);margin-bottom:4px;';
    $value = 'font-size:14px;font-weight:600;color:var(--text);word-break:break-word;';
    $section = 'font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;';
    ?>
<div style="display:flex;flex-direction:column;gap:18px;">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
    <h4 style="font-size:18px;font-weight:700;line-height:1.3;color:var(--text);word-break:break-word;"><?= report_detail_e($row['title']) ?></h4>
    <span class="px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap <?= $statusClass ?>"><?= report_detail_e($status) ?></span>
  </div>

  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Date</div>
      <div style="<?= $value ?>"><?= report_detail_e($dateLabel) ?></div>
    </div>
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Submitted By</div>
      <div style="<?= $value ?>"><?= report_detail_e($row['user_name']) ?></div>
    </div>
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Job Type</div>
      <div style="<?= $value ?>"><?= report_detail_e($row['job_type'] ?? '-') ?></div>
    </div>
    <div style="<?= $card ?>">
      <div style="<?= $label ?>">Work Force</div>
      <div style="<?= $value ?>"><?= report_detail_e($row['workforce_name'] ?? '-') ?></div>
    </div>
  </div>

  <div>
    <div style="<?= $section ?>">Description</div>
    <div style="<?= $card ?>font-size:14px;line-height:1.6;color:var(--text);word-break:break-word;">
      <?php if ($description !== ''): ?>
        <?= nl2br(report_detail_e($description)) ?>
      <?php else: ?>
        <span style="color:var(--text-faint);font-style:italic;">No description provided.</span>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div style="<?= $section ?>">Proof</div>
    <?php if ($rawLink === '' && !$hasImage): ?>
      <div style="<?= $card ?>font-size:14px;color:var(--text-faint);font-style:italic;">No proof attached.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php if ($rawLink !== ''): ?>
          <div style="<?= $card ?>display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div style="min-width:0;flex:1;">
              <div style="<?= $label ?>">Link</div>
              <?php if ($isHttp): ?>
                <a href="<?= report_detail_e($rawLink) ?>" target="_blank" rel="noopener noreferrer"
                  style="font-size:14px;color:var(--brand);text-decoration:underline;word-break:break-all;"><?= report_detail_e($rawLink) ?></a>
              <?php else: ?>
                <span style="font-size:14px;color:var(--text);word-break:break-all;"><?= report_detail_e($rawLink) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($isHttp): ?>
              <a href="<?= report_detail_e($rawLink) ?>" target="_blank" rel="noopener noreferrer"
                class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition whitespace-nowrap">Open</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($hasImage): ?>
          <div style="<?= $card ?>">
            <div style="<?= $label ?>margin-bottom:8px;">Photo</div>
            <?php if ($imageExists): ?>
              <a href="<?= report_detail_e($imageUrl) ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?= report_detail_e($imageUrl) ?>" alt="Proof photo" loading="lazy"
                  onerror="this.parentNode.style.display='none';this.parentNode.nextElementSibling.style.display='block';"
                  style="display:block;max-width:100%;max-height:300px;margin:0 auto;border-radius:10px;object-fit:contain;">
              </a>
              <div style="display:none;font-size:14px;color:var(--text-faint);font-style:italic;">The photo could not be loaded.</div>
            <?php else: ?>
              <div style="font-size:14px;color:var(--text-faint);font-style:italic;">The photo file could not be found on the server.</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
    exit;
}

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
              <?php foreach ($rows as $r):
                $hasLink = trim((string)($r['proof_link'] ?? '')) !== '';
                $hasImage = trim((string)($r['proof_image'] ?? '')) !== '';
              ?>
                <tr class="hover:bg-gray-50 transition" id="row-<?php echo $r['report_id']; ?>">
                  <td class="py-4 whitespace-nowrap text-sm text-gray-600">
                    <?php echo htmlspecialchars($r['report_date']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                    <?php echo htmlspecialchars($r['job_type']) ?>
                  </td>
                  <td class="py-4 whitespace-nowrap text-sm">
                    <button type="button" onclick="openModal(<?php echo (int)$r['report_id']; ?>)"
                      class="text-left text-gray-800 hover:text-indigo-600 hover:underline transition" style="cursor:pointer;" title="View report detail">
                      <?php echo htmlspecialchars($r['title']) ?>
                    </button>
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
                    <?php if ($hasLink || $hasImage): ?>
                      <button type="button"
                        onclick="showProof(this)"
                        data-link="<?php echo htmlspecialchars(trim((string)($r['proof_link'] ?? '')), ENT_QUOTES) ?>"
                        data-image="<?php echo $hasImage ? 'my_reports.php?proof_image=' . (int)$r['report_id'] : '' ?>"
                        class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 text-sm transition">
                        View
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

                      <a href="edit_report.php?id=<?php echo $r['report_id']; ?>"
                        id="edit-<?php echo $r['report_id']; ?>"
                        class="px-3 py-1 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600 transition">
                        Edit
                      </a>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm">Completed</span>
                    <?php endif; ?>

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
              const editLink = document.getElementById('edit-' + reportId);
              if (editLink) editLink.remove();
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

<div id="reportModal" class="fixed inset-0 hidden z-50" style="background: rgba(15, 23, 42, 0.55);">
  <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white overflow-hidden"
    style="width: calc(100% - 2rem); max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; color: #fff; background: linear-gradient(135deg, #4f46e5, #7c3aed);">
      <div>
        <p style="font-size: 11px; letter-spacing: .1em; text-transform: uppercase; opacity: .8;">Production Report</p>
        <h3 style="font-size: 17px; font-weight: 700; line-height: 1.2;">Report Detail</h3>
      </div>
      <button onclick="closeModal()" aria-label="Close"
        style="width: 32px; height: 32px; border-radius: 9999px; background: rgba(255, 255, 255, .18); color: #fff; font-size: 20px; line-height: 1; cursor: pointer;">
        &times;
      </button>
    </div>
    <div id="modalContent" style="padding: 20px; overflow-y: auto;"></div>
    <div style="padding: 12px 20px; text-align: right; border-top: 1px solid var(--border);">
      <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium transition">
        Close
      </button>
    </div>
  </div>
</div>

<script>
  function openModal(reportId) {
    document.getElementById('modalContent').innerHTML = '<p class="text-sm text-gray-500">Loading...</p>';
    document.getElementById('reportModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    fetch('my_reports.php?detail=' + encodeURIComponent(reportId), {
        credentials: 'same-origin'
      })
      .then(r => r.text())
      .then(d => {
        document.getElementById('modalContent').innerHTML = d;
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

  document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function escapeAttr(text) {
    return escapeHtml(text).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function swalTheme() {
    const dark = window.VortaUI && window.VortaUI.getTheme() === 'dark';
    return dark ? {
      background: '#1e293b',
      color: '#e2e8f0'
    } : {
      background: '#ffffff',
      color: '#1f2937'
    };
  }

  function showProof(btn) {
    const link = (btn.dataset.link || '').trim();
    const image = btn.dataset.image || '';
    const isHttp = /^https?:\/\//i.test(link);
    const label = 'font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.6;margin-bottom:6px;';

    let html = '<div style="display:flex;flex-direction:column;gap:16px;text-align:left;">';

    if (image) {
      html += `<div>
        <div style="${label}">Photo</div>
        <a href="${escapeAttr(image)}" target="_blank" rel="noopener noreferrer">
          <img src="${escapeAttr(image)}" alt="Proof photo" onerror="this.style.display='none'"
            style="display:block;max-width:100%;max-height:320px;margin:0 auto;border-radius:12px;object-fit:contain;border:1px solid rgba(148,163,184,.35);">
        </a>
      </div>`;
    }

    if (link) {
      const linkHtml = isHttp ?
        `<a href="${escapeAttr(link)}" target="_blank" rel="noopener noreferrer" style="color:#6366f1;text-decoration:underline;">${escapeHtml(link)}</a>` :
        escapeHtml(link);
      html += `<div>
        <div style="${label}">Link</div>
        <div style="border:1px solid rgba(148,163,184,.35);border-radius:12px;padding:10px 14px;font-size:14px;word-break:break-all;">${linkHtml}</div>
      </div>`;
    }

    html += '</div>';

    Swal.fire(Object.assign({
      title: 'Report Proof',
      html: html,
      width: 560,
      showCloseButton: true,
      showConfirmButton: isHttp,
      confirmButtonText: 'Open Link',
      confirmButtonColor: '#4f46e5',
      showCancelButton: true,
      cancelButtonText: 'Close'
    }, swalTheme())).then((result) => {
      if (result.isConfirmed && isHttp) {
        window.open(link, '_blank', 'noopener,noreferrer');
      }
    });
  }
</script>

<?php include __DIR__ . '/footer.php'; ?>