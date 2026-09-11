<?php
require_once __DIR__ . '/../lib/auth.php';
// Determine the correct base path for images
// $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// $image_path = '../images/vortalogo.jpg'; // Adjust this path according to your structure
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>vorta Productivity Tracker</title>
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
  <!-- Header/Navigation -->
  <header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto p-4">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <div class="flex-shrink-0">
          <img src="../images/vortalogo-removebg-preview.png" alt="Logo vorta" style="height: 40px; width: auto;"
            class="mr-2">
          <h1 class="text-xl font-bold text-indigo-700">
            <span class="hidden sm:inline">vorta</span> Productivity Tracker
          </h1>
        </div>

        <!-- Desktop Menu -->
        <?php if (isset($_SESSION['user'])): ?>
          <nav class="hidden lg:flex items-center space-x-1">
            <a href="dashboard.php"
              class="px-3 py-2 text-sm font-medium rounded-md transition-all <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">
              Dashboard
            </a>
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

            <!-- Admin Dropdown -->
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
              <div class="relative">
                <button id="admin-dropdown-btn" type="button"
                  class="px-3 py-2 text-sm font-medium rounded-md flex items-center gap-1 text-gray-600 hover:text-indigo-600 hover:bg-gray-100 focus:outline-none">
                  Admin Panel
                  <svg id="admin-arrow" class="w-2.5 h-2.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div id="admin-dropdown-menu"
                  class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden border border-gray-200">
                  <a href="admin_reports.php"
                    class="block px-4 py-2 text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_reports.php' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-700' ?>">
                    All Reports
                  </a>
                  <a href="admin_attendance.php"
                    class="block px-4 py-2 text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_attendance.php' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-700' ?>">
                    Attendance Report
                  </a>
                  <a href="admin_master_data.php"
                    class="block px-4 py-2 text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_master_data.php' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-700' ?>">
                    Master Data
                  </a>
                  <a href="admin_not_attendance.php"
                    class="block px-4 py-2 text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_not_attendance.php' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-700' ?>">
                    Not Absent
                  </a>
                  <!-- <a href="admin_employees_workforce.php"
                class="block px-4 py-2 text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_employees_workforce.php' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-700' ?>">
                Employee - Workforce
              </a> -->
                </div>
              </div>
            <?php endif; ?>

            <a href="logout.php"
              class="px-3 py-2 text-sm font-medium text-white rounded-md bg-red-500 hover:bg-red-600 transition-all">
              Logout
            </a>
          </nav>
        <?php endif; ?>

        <!-- Mobile menu button -->
        <div class="lg:hidden">
          <button id="mobile-menu-btn" class="text-gray-600 hover:text-indigo-600 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile Menu -->
      <?php if (isset($_SESSION['user'])): ?>
        <nav id="mobile-menu" class="lg:hidden pb-4 hidden border-t border-gray-200 mt-2">
          <div class="flex flex-col space-y-1">
            <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Dashboard</a>
            <a href="attendance.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Absen</a>
            <a href="report_form.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'report_form.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Input Laporan</a>
            <a href="reports_my.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'reports_my.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Laporan Saya</a>
            <a href="profil.php" class="px-3 py-2 rounded-md text-sm font-medium <?= basename($_SERVER['PHP_SELF']) === 'profil.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">My Profile</a>

            <!-- Mobile Admin Panel -->
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
              <div class="pt-2 mt-2 border-t border-gray-200">
                <button id="mobile-admin-btn" class="w-full text-left px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600 flex justify-between items-center">
                  Admin Panel
                  <svg id="mobile-admin-arrow" class="w-2.5 h-2.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div id="mobile-admin-menu" class="pl-4 mt-1 space-y-1 hidden">
                  <a href="admin_reports.php" class="block px-3 py-2 rounded-md text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_reports.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">All Reports</a>
                  <a href="admin_attendance.php" class="block px-3 py-2 rounded-md text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Attendance Report</a>
                  <a href="admin_master_data.php" class="block px-3 py-2 rounded-md text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_master_data.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Master Data</a>
                  <a href="admin_not_attendance.php" class="block px-3 py-2 rounded-md text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_not_attendance.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Not Absent</a>
                  <!-- <a href="admin_employees_workforce.php" class="block px-3 py-2 rounded-md text-sm <?= basename($_SERVER['PHP_SELF']) === 'admin_employees_workforce.php' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-100' ?>">Employee - workforcce</a> -->
                </div>
              </div>
            <?php endif; ?>

            <a href="logout.php" class="px-3 py-2 rounded-md text-sm font-medium text-red-600 hover:text-red-700 hover:bg-gray-100">Logout</a>
          </div>
        </nav>
      <?php endif; ?>
    </div>
  </header>

  <!-- Main Content -->
  <main class="container mx-auto px-4 py-6">
    <?php // Konten halaman akan dimasukkan di sini 
    ?>
  </main>

  <!-- JavaScript -->
  <script>
    // Toggle mobile menu
    document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
      const mobileMenu = document.getElementById('mobile-menu');
      const menuIcon = document.getElementById('menu-icon');

      mobileMenu.classList.toggle('hidden');

      // Change icon based on menu state
      if (mobileMenu.classList.contains('hidden')) {
        menuIcon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
      } else {
        menuIcon.setAttribute('d', 'M6 18L18 6M6 6l12 12');
      }
    });

    // Toggle desktop admin dropdown
    document.getElementById('admin-dropdown-btn')?.addEventListener('click', function(e) {
      e.stopPropagation();
      const menu = document.getElementById('admin-dropdown-menu');
      const arrow = document.getElementById('admin-arrow');

      menu.classList.toggle('hidden');
      arrow.classList.toggle('rotate-180');
    });

    // Toggle mobile admin submenu
    document.getElementById('mobile-admin-btn')?.addEventListener('click', function() {
      const menu = document.getElementById('mobile-admin-menu');
      const arrow = document.getElementById('mobile-admin-arrow');

      menu.classList.toggle('hidden');
      arrow.classList.toggle('rotate-180');
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      // Desktop admin dropdown
      const adminDropdown = document.getElementById('admin-dropdown-menu');
      const adminBtn = document.getElementById('admin-dropdown-btn');
      const adminArrow = document.getElementById('admin-arrow');

      if (adminDropdown && adminBtn && !adminBtn.contains(e.target) && !adminDropdown.contains(e.target)) {
        adminDropdown.classList.add('hidden');
        adminArrow.classList.remove('rotate-180');
      }
    });

    // Close mobile menu when clicking a link
    document.querySelectorAll('#mobile-menu a').forEach(link => {
      link.addEventListener('click', () => {
        document.getElementById('mobile-menu')?.classList.add('hidden');
        document.getElementById('menu-icon')?.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
      });
    });
  </script>
</body>

</html>