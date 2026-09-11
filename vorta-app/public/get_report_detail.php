<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_admin();

$report_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
  SELECT pr.*, u.name, wf.workforce_name
  FROM production_reports pr
  LEFT JOIN work_force wf ON wf.workforce_id = pr.workforce_id
  JOIN users u ON u.user_id = pr.user_id
  WHERE pr.report_id = ?
");
$stmt->execute([$report_id]);
$row = $stmt->fetch();

if (!$row) {
  die('<p class="text-red-600">Laporan tidak ditemukan</p>');
}

echo '<div class="space-y-4">';
echo '<p><strong>Tanggal:</strong> ' . htmlspecialchars($row['report_date']) . '</p>';
echo '<p><strong>Nama:</strong> ' . htmlspecialchars($row['name']) . '</p>';
echo '<p><strong>Jenis Pekerjaan:</strong> ' . htmlspecialchars($row['job_type']) . '</p>';
echo '<p><strong>Judul:</strong> ' . htmlspecialchars($row['title']) . '</p>';
echo '<p><strong>Work Force:</strong> ' . htmlspecialchars($row['workforce_name']) . '</p>';
echo '<p><strong>Deskripsi:</strong> ' . nl2br(htmlspecialchars($row['description'])) . '</p>';
echo '<p><strong>Status:</strong> ' . htmlspecialchars($row['status']) . '</p>';

// Tampilkan bukti link jika ada
if ($row['proof_link']) {
  echo '<p><strong>Bukti Link:</strong> <a href="' . htmlspecialchars($row['proof_link']) . '" target="_blank" class="text-indigo-600 hover:underline">' . htmlspecialchars($row['proof_link']) . '</a></p>';
}

// Tampilkan bukti gambar jika ada
if ($row['proof_image']) {
  echo '<div class="mt-4">';
  echo '<p><strong>Bukti Gambar:</strong></p>';
  echo '<img src="' . htmlspecialchars($row['proof_image']) . '" alt="Bukti" class="max-w-full h-auto rounded-lg border border-gray-200 mt-2">';
  echo '</div>';
}

echo '</div>';
