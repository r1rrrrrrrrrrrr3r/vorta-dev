<?php
require_once __DIR__ . '/../lib/auth.php';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vorta Productivity Tracker</title>
  <link rel="stylesheet" href="css/output.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">
  <header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto p-4">
      <div class="flex items-center justify-between h-16">
        <div class="flex-shrink-0 flex items-center">
          <img src="../images/vorta.png" alt="Logo vorta" class="h-10 w-10 object-contain mr-2" style="height: 55px; width: 55px; max-height: 55px; display: inline-block;">
          <h1 class="text-xl font-bold text-indigo-700">
            <span class="hidden sm:inline"></span> Productivity Tracker
          </h1>
        </div>

        <?php if (isset($_SESSION['user'])): ?>
          <nav class="hidden lg:flex items-center space-x-1">
            <a href="dashboard.php"
              class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
              Dashboard
            </a>

            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
              <a href="admin_reports.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'admin_reports.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                All Reports
              </a>
              <a href="admin_attendance.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'admin_attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                Attendance Report
              </a>
              <a href="admin_master_data.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'admin_master_data.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                Master Data
              </a>
              <a href="admin_not_attendance.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'admin_not_attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                Not Absent
              </a>
            <?php else: ?>
              <a href="attendance.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                Absen
              </a>
              <a href="report_form.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'report_form.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                Report Input
              </a>
              <a href="reports_my.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'reports_my.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                My Report
              </a>
              <a href="profil.php"
                class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'profil.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
                My Profile
              </a>
            <?php endif; ?>

            <a href="logout.php"
              class="px-3 py-2 text-sm font-medium text-white rounded-md bg-red-500 hover:bg-red-600 transition-all">
              Logout
            </a>
          </nav>
        <?php endif; ?>

        <div class="lg:hidden">
          <button id="mobile-menu-btn" class="text-gray-600 hover:text-indigo-600 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
          </button>
        </div>
      </div>

      <?php if (isset($_SESSION['user'])): ?>
        <nav id="mobile-menu" class="lg:hidden pb-4 hidden border-t border-gray-200 mt-2">
          <div class="flex flex-col space-y-1">
            <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Dashboard</a>

            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
              <a href="admin_reports.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'admin_reports.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">All Reports</a>
              <a href="admin_attendance.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'admin_attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Attendance Report</a>
              <a href="admin_master_data.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'admin_master_data.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Master Data</a>
              <a href="admin_not_attendance.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'admin_not_attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Not Absent</a>
            <?php else: ?>
              <a href="attendance.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Absen</a>
              <a href="report_form.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'report_form.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Input Laporan</a>
              <a href="reports_my.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'reports_my.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Laporan Saya</a>
              <a href="profil.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'profil.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">My Profile</a>
            <?php endif; ?>

            <a href="logout.php" class="px-3 py-2 rounded-md text-sm font-medium text-red-600 hover:text-red-700 hover:bg-gray-100">Logout</a>
          </div>
        </nav>
      <?php endif; ?>
    </div>
  </header>

  <main class="container mx-auto px-4 py-6">
    <?php
    ?>
  </main>

  <script>
    document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
      const mobileMenu = document.getElementById('mobile-menu');
      const menuIcon = document.getElementById('menu-icon');

      mobileMenu.classList.toggle('hidden');

      if (mobileMenu.classList.contains('hidden')) {
        menuIcon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
      } else {
        menuIcon.setAttribute('d', 'M6 18L18 6M6 6l12 12');
      }
    });

    document.querySelectorAll('#mobile-menu a').forEach(link => {
      link.addEventListener('click', () => {
        document.getElementById('mobile-menu')?.classList.add('hidden');
        document.getElementById('menu-icon')?.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
      });
    });
  </script>
</body>

</html>