-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 27 Agu 2025 pada 16.52
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vorta_prodtracker`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('Hadir','Telat','Izin','Sakit','Alpa') DEFAULT 'Alpa',
  `location` varchar(100) NOT NULL,
  `shift` enum('Pagi','Siang') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `user_id`, `date`, `check_in`, `check_out`, `status`, `location`, `shift`, `notes`, `created_at`) VALUES
(8, 3, '2025-08-14', NULL, NULL, 'Izin', '', NULL, 'Sedang sakit', '2025-08-14 08:15:03'),
(9, 3, '2025-08-15', '16:24:52', '16:24:54', 'Alpa', '', NULL, NULL, '2025-08-15 09:24:52'),
(10, 4, '2025-08-21', '17:25:13', NULL, 'Alpa', '', NULL, NULL, '2025-08-21 10:25:13'),
(11, 4, '2025-08-22', '13:30:08', '14:02:47', 'Alpa', '', NULL, NULL, '2025-08-22 06:30:08'),
(13, 2, '2025-08-22', '13:50:58', NULL, 'Alpa', '', NULL, NULL, '2025-08-22 06:50:58'),
(14, 5, '2025-08-22', '13:54:58', NULL, 'Alpa', '', NULL, NULL, '2025-08-22 06:54:58'),
(19, 3, '2025-08-22', '14:43:21', NULL, 'Alpa', 'Kantor Mentes', NULL, NULL, '2025-08-22 07:43:21'),
(20, 4, '2025-08-25', '08:47:21', NULL, 'Telat', 'Kantor Mentes', NULL, NULL, '2025-08-25 01:47:21'),
(21, 5, '2025-08-26', '14:08:05', '14:08:08', 'Alpa', 'Kantor', NULL, NULL, '2025-08-26 07:08:05'),
(22, 3, '2025-08-27', '11:38:12', '11:52:48', 'Telat', 'Kantor', 'Pagi', NULL, '2025-08-27 04:38:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `position` enum('Employe','Internship','President Director','Research and Development Manager') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `workforce_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `employees`
--

INSERT INTO `employees` (`employee_id`, `user_id`, `name`, `position`, `phone`, `created_at`, `updated_at`, `workforce_id`) VALUES
(1, 4, 'Rizky Putra Hadi Sarwono', 'Internship', '085811095721', '2025-08-21 10:41:30', '2025-08-22 08:25:10', NULL),
(2, 5, 'Bryan Syahputra', 'Internship', '088213292741', '2025-08-22 06:54:36', '2025-08-22 06:54:36', NULL),
(3, 6, 'Muhammad Nabil', 'Internship', NULL, '2025-08-22 07:09:52', '2025-08-22 07:09:52', NULL),
(4, 1, 'Admin vorta', 'Internship', NULL, '2025-08-26 06:57:47', '2025-08-26 06:57:47', NULL),
(5, 2, 'admin2', 'Internship', NULL, '2025-08-26 06:57:47', '2025-08-26 06:57:47', NULL),
(6, 3, 'adminn', 'Internship', NULL, '2025-08-26 06:57:47', '2025-08-26 06:57:47', NULL),
(7, 7, 'Shaquille Raffalea', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(8, 8, 'Fazle Adrevi Bintang Al Farrel', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(9, 9, 'Farel Fadlillah', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(10, 10, 'Bintang Rayvan', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(11, 11, 'Iqbal Hadi Mustafa', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(12, 12, 'Zafira Marvella', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(13, 13, 'Kirana Firjal Atakhira', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(14, 14, 'Aisyah Ratna Aulia', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(15, 15, 'Firyal Dema Elputri', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(16, 16, 'Halena Maheswari Viehandini', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(17, 17, 'Hannif Fahmy Fadilah', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(18, 18, 'Kevin Revaldo', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(19, 19, 'Achmad wafiq risvyan', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(20, 20, 'Kurniawan yafi Djayakusuma', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(21, 21, 'Hildan argiansyah', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(22, 22, 'Joshua Matthew Hendra', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(23, 23, 'Fadhal nurul azmi', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(24, 24, 'Rasya al zikri', 'Internship', NULL, '2025-08-26 07:09:51', '2025-08-26 07:09:51', NULL),
(25, 25, 'Rifki', 'Internship', NULL, '2025-08-26 07:48:14', '2025-08-26 07:48:14', NULL),
(26, 26, 'Marsya Safeena Tama', 'Internship', NULL, '2025-08-26 07:48:14', '2025-08-27 13:51:07', NULL),
(35, 131, 'Prasetyo Adi', 'President Director', NULL, '2025-08-27 13:18:49', '2025-08-27 14:19:39', NULL),
(36, 132, 'Ristyo Arditto', 'Research and Development Manager', NULL, '2025-08-27 13:18:49', '2025-08-27 14:19:45', NULL),
(37, 133, 'Rhomie Bireuno', 'Employe', NULL, '2025-08-27 13:18:49', '2025-08-27 13:18:49', NULL),
(38, 134, 'Ahmad Zidan', 'Employe', NULL, '2025-08-27 13:18:49', '2025-08-27 13:18:49', NULL),
(39, 135, 'Muhammad Wildan Ichsanul Akbar', 'Employe', NULL, '2025-08-27 13:18:49', '2025-08-27 13:18:49', NULL),
(40, 136, 'Agusti Bahtiar', 'Employe', NULL, '2025-08-27 13:18:49', '2025-08-27 13:50:14', NULL),
(41, 137, 'Haikal Fakhri Agnitian', 'Employe', NULL, '2025-08-27 13:18:49', '2025-08-27 13:18:49', NULL),
(42, 138, 'Muhammad Rizky', 'Employe', NULL, '2025-08-27 13:18:49', '2025-08-27 13:18:49', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `employees_workforce`
--

CREATE TABLE `employees_workforce` (
  `employee_id` int(11) NOT NULL,
  `workforce_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `employees_workforce`
--

INSERT INTO `employees_workforce` (`employee_id`, `workforce_id`, `assigned_at`) VALUES
(1, 1, '2025-08-26 07:45:20'),
(1, 4, '2025-08-26 07:45:20'),
(1, 5, '2025-08-26 07:45:20'),
(1, 7, '2025-08-26 07:45:20'),
(2, 4, '2025-08-26 07:45:20'),
(2, 5, '2025-08-26 07:45:20'),
(2, 7, '2025-08-26 07:45:20'),
(3, 1, '2025-08-26 07:45:20'),
(3, 2, '2025-08-26 07:45:20'),
(7, 1, '2025-08-26 07:57:07'),
(7, 4, '2025-08-26 07:57:07'),
(7, 5, '2025-08-26 07:57:07'),
(8, 4, '2025-08-26 07:57:07'),
(8, 5, '2025-08-26 07:57:07'),
(8, 7, '2025-08-26 07:57:07'),
(9, 4, '2025-08-26 07:57:07'),
(9, 5, '2025-08-26 07:57:07'),
(9, 7, '2025-08-26 07:57:07'),
(10, 4, '2025-08-26 07:57:07'),
(10, 5, '2025-08-26 07:57:07'),
(10, 6, '2025-08-26 07:57:07'),
(11, 4, '2025-08-26 07:57:07'),
(11, 5, '2025-08-26 07:57:07'),
(11, 6, '2025-08-26 07:57:07'),
(12, 1, '2025-08-26 07:57:07'),
(13, 3, '2025-08-26 07:57:07'),
(14, 4, '2025-08-26 07:57:07'),
(14, 5, '2025-08-26 07:57:07'),
(15, 3, '2025-08-26 07:57:07'),
(16, 4, '2025-08-26 07:57:07'),
(16, 5, '2025-08-26 07:57:07'),
(17, 1, '2025-08-26 07:57:07'),
(17, 2, '2025-08-26 07:57:07'),
(17, 7, '2025-08-26 07:57:07'),
(18, 6, '2025-08-26 07:57:07'),
(19, 2, '2025-08-26 07:57:07'),
(20, 6, '2025-08-26 07:57:07'),
(21, 2, '2025-08-26 07:57:07'),
(21, 7, '2025-08-26 07:57:07'),
(22, 3, '2025-08-26 07:57:07'),
(22, 6, '2025-08-26 07:57:07'),
(23, 2, '2025-08-26 07:57:07'),
(23, 7, '2025-08-26 07:57:07'),
(24, 1, '2025-08-26 07:57:07'),
(24, 2, '2025-08-26 07:57:07'),
(25, 1, '2025-08-26 07:48:15'),
(25, 4, '2025-08-26 07:48:15'),
(25, 5, '2025-08-26 07:48:15'),
(26, 6, '2025-08-26 07:57:07'),
(35, 1, '2025-08-27 14:21:32'),
(35, 2, '2025-08-27 14:21:32'),
(35, 3, '2025-08-27 14:21:32'),
(35, 4, '2025-08-27 14:21:32'),
(35, 5, '2025-08-27 14:21:32'),
(35, 6, '2025-08-27 14:21:32'),
(35, 7, '2025-08-27 14:21:32'),
(36, 1, '2025-08-27 14:24:31'),
(36, 2, '2025-08-27 14:24:31'),
(36, 3, '2025-08-27 14:24:31'),
(36, 4, '2025-08-27 14:24:31'),
(36, 5, '2025-08-27 14:24:31'),
(36, 6, '2025-08-27 14:24:31'),
(36, 7, '2025-08-27 14:24:31'),
(37, 4, '2025-08-27 14:33:32'),
(37, 5, '2025-08-27 14:33:32'),
(38, 1, '2025-08-27 14:33:32'),
(38, 2, '2025-08-27 14:33:32'),
(38, 3, '2025-08-27 14:33:32'),
(38, 4, '2025-08-27 14:33:32'),
(38, 5, '2025-08-27 14:33:32'),
(38, 6, '2025-08-27 14:33:32'),
(38, 7, '2025-08-27 14:33:32'),
(39, 1, '2025-08-27 14:33:32'),
(39, 2, '2025-08-27 14:33:32'),
(39, 3, '2025-08-27 14:33:32'),
(39, 4, '2025-08-27 14:33:32'),
(39, 5, '2025-08-27 14:33:32'),
(39, 6, '2025-08-27 14:33:32'),
(39, 7, '2025-08-27 14:33:32'),
(40, 1, '2025-08-27 14:33:32'),
(40, 2, '2025-08-27 14:33:32'),
(40, 3, '2025-08-27 14:33:32'),
(40, 4, '2025-08-27 14:33:32'),
(40, 5, '2025-08-27 14:33:32'),
(40, 6, '2025-08-27 14:33:32'),
(40, 7, '2025-08-27 14:33:32'),
(41, 1, '2025-08-27 14:33:32'),
(41, 2, '2025-08-27 14:33:32'),
(41, 3, '2025-08-27 14:33:32'),
(41, 4, '2025-08-27 14:33:32'),
(41, 5, '2025-08-27 14:33:32'),
(41, 6, '2025-08-27 14:33:32'),
(41, 7, '2025-08-27 14:33:32'),
(42, 1, '2025-08-27 14:33:32'),
(42, 2, '2025-08-27 14:33:32'),
(42, 3, '2025-08-27 14:33:32'),
(42, 4, '2025-08-27 14:33:32'),
(42, 5, '2025-08-27 14:33:32'),
(42, 6, '2025-08-27 14:33:32'),
(42, 7, '2025-08-27 14:33:32');

-- --------------------------------------------------------

--
-- Struktur dari tabel `job_type`
--

CREATE TABLE `job_type` (
  `job_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `job_type`
--

INSERT INTO `job_type` (`job_type_id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Program', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(2, 'Website', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(3, 'Mobile Apps', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(4, 'Training Materi', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(5, 'Mengajar', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(6, 'QA/Testing', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(7, 'UI/UX', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(8, 'DevOps', '2025-08-26 08:21:04', '2025-08-26 08:21:04'),
(9, 'Dokumentasi', '2025-08-26 08:21:04', '2025-08-26 08:21:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `production_reports`
--

CREATE TABLE `production_reports` (
  `report_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `job_type` enum('Program','Website','Mobile Apps','Training Materi','Mengajar','QA/Testing','UI/UX','DevOps','Dokumentasi') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Progress','Selesai') DEFAULT 'Progress',
  `proof_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `proof_image` varchar(255) DEFAULT NULL,
  `workforce_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `production_reports`
--

INSERT INTO `production_reports` (`report_id`, `user_id`, `report_date`, `job_type`, `title`, `description`, `status`, `proof_link`, `created_at`, `proof_image`, `workforce_id`) VALUES
(1, 2, '2025-08-14', 'Website', 'blabla', 'blabla', 'Progress', 'https://blabla', '2025-08-14 06:06:55', NULL, NULL),
(2, 2, '2025-08-14', 'Website', 'blabla', 'blabla', 'Progress', 'https://blabla', '2025-08-14 06:07:38', NULL, NULL),
(3, 3, '2025-08-15', 'Mengajar', 'Test', '123123', 'Selesai', NULL, '2025-08-15 08:16:39', NULL, NULL),
(5, 2, '2025-08-15', 'Website', 'Progresss Casa Medica', 'Membuat page login', 'Progress', NULL, '2025-08-15 08:20:55', '../uploads/689eede79d217_Screenshot 2024-01-11 201846.png', NULL),
(10, 5, '2025-08-26', 'Website', 'Test', 'Test', 'Progress', NULL, '2025-08-26 10:12:09', '../uploads/Bryan_2_20250826_171209.png', 7),
(11, 9, '2025-08-27', 'Training Materi', 'TEST INPUT', 'Membuat..', 'Progress', NULL, '2025-08-27 02:35:35', '../uploads/Farel_Fadlillah_4_20250827_093535.jpg', 5);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'Admin vorta', 'admin@vorta.local', '$2y$10$O4Nw.K1f1C0GQ6gQCNhZC.xs6QzD7xHhYI6xw6n8QCDWZ0hV6qj7S', 'admin', 1, '2025-08-14 05:59:23'),
(2, 'admin2', 'admin2@vorta.local', '$2y$10$3kp0XtkEuKnIHqGVXPl61upDn1S6dC1NtOfAQS8X0UV25gJiLiHme', 'staff', 1, '2025-08-14 06:02:33'),
(3, 'adminn', 'adminn@vorta.local', '$2y$10$hE1zWVRUROvzZ94x5bjUcuf8a6cxXuGd7gbXcjCCg5Ik7IwSqmOBy', 'admin', 1, '2025-08-14 07:15:59'),
(4, 'Putra', 'Putra@vorta.local', '$2y$10$KmVIRJIylokPZ.ovsinT6.BPBNZEoMhVhREjwb7WfLCtT1PoutkKG', 'staff', 1, '2025-08-21 10:10:43'),
(5, 'Bryan', 'Bryan@vorta.local', '$2y$10$IqojS1l34SdhAcqsN9WJ3.SF2dKgCskYXv0e25RazpPUW1ZaPn..S', 'staff', 1, '2025-08-22 06:53:58'),
(6, 'Nabil', 'Nabil@vorta.local', '$2y$10$9fhlx6Wz64iCv9sc7Ck8ruCEy1CM3yd.3l51Z/ef63FWCvymkFwfe', 'staff', 1, '2025-08-22 07:09:22'),
(7, 'Raffa', 'raffa@vorta.local', '$2y$10$UYs1BgdXtJFJd1RQEFKeWe8x8e57CIKjUNozkxVDk.wjPcHFPZ8W6', 'staff', 1, '2025-08-26 06:57:47'),
(8, 'Fazle', 'fazle@vorta.local', '$2y$10$zpP16UQtIXR6L8VPa0x07uLK3Ja6lotlb4vXTSKRUIOOhW/Tr3kqm', 'staff', 1, '2025-08-26 06:57:47'),
(9, 'Farel', 'farel@vorta.local', '$2y$10$dooB1GsCWl.eXXUDu1n83.bcr661IotZtaTOm1l4RcDHU5G60oL2W', 'staff', 1, '2025-08-26 06:57:47'),
(10, 'Bintang', 'bintang@vorta.local', '$2y$10$CsrxqT.ws4KG190kyDKmmO3m55PeR4IUS..LH4FHN1vLdup6DlFmS', 'staff', 1, '2025-08-26 06:57:48'),
(11, 'Iqbal', 'iqbal@vorta.local', '$2y$10$3x6RZDUzwEpOcWc2kkRUTumXxnnQZdGg/IBVu7KnnG5w.UDHztCq.', 'staff', 1, '2025-08-26 06:57:48'),
(12, 'Zafira', 'zafira@vorta.local', '$2y$10$6lzhY2x7hfRKYi/8gsNvTuYvTugowtqKowVThhTix25tIy8IpvqXS', 'staff', 1, '2025-08-26 06:57:48'),
(13, 'kiya', 'kiya@vorta.local', '$2y$10$OUv4b0gFq0G0b5sGrmsRauzhPoQIggDqWmslFuYxxRoUs2A.t.sSS', 'staff', 1, '2025-08-26 06:57:48'),
(14, 'Asa', 'asa@vorta.local', '$2y$10$EYeyAStVGjiMESi339oI9u2TvszHsjlogNEBqvnnBYggqwZ1zqAgW', 'staff', 1, '2025-08-26 06:57:48'),
(15, 'Illy', 'Illy@vorta.local', '$2y$10$hVc9foLws1WrKoJs3eoT/elEMFTjPyll8qVYGxPVjEM4fyoRwfRsO', 'staff', 1, '2025-08-26 06:57:48'),
(16, 'Hana', 'hana@vorta.local', '$2y$10$Vtr6g00JjH5.JbKjc985pebbfFRRyVvC10htFlW62Tu5NU9naELHu', 'staff', 1, '2025-08-26 06:57:48'),
(17, 'Fahmy', 'fahmy@vorta.local', '$2y$10$goPk7ZKjdQ71be8ypn/gTeCyVrqDok1BvTytGdACp9DTzkD3jBlYq', 'staff', 1, '2025-08-26 06:57:48'),
(18, 'Kevin', 'kevin@vorta.local', '$2y$10$xSMJvleRjzAfQCiVoZ11w.NWJF8obU0im.ybaFBL1lOb402QLQX7O', 'staff', 1, '2025-08-26 06:57:48'),
(19, 'Risvyan', 'risvyan@vorta.local', '$2y$10$FTMIiFnP.ecPFfqgzZkWIuCU9SRFWMawJf8xT7wDkoi07FcIUZKsG', 'staff', 1, '2025-08-26 06:57:48'),
(20, 'Yafi', 'yafi@vorta.local', '$2y$10$xmsQgq6Ofn62T2vBgItL1eBLS0Y60xYmYPQ7TMzcIHQ2AN8KbnnFq', 'staff', 1, '2025-08-26 06:57:48'),
(21, 'Hildan', 'hildan@vorta.local', '$2y$10$Tewv.8sgLYbKtGNF2osvmuiseP/mvEXsq8TzJLUwFJ2t94TuRBA9.', 'staff', 1, '2025-08-26 06:57:49'),
(22, 'Joshua', 'joshua@vorta.local', '$2y$10$aaQCkUGPNs4g.g95Dwov/uIHOKy8n/9ugpIb/kTJZZ7lDuJjtUvyK', 'staff', 1, '2025-08-26 06:57:49'),
(23, 'Fadhal', 'fadhal@vorta.local', '$2y$10$V3Bn.WUHAtv2Shz0H2PK7.ZwLcXTLJ4qQ9kUNfhE30YTAFAIVB5s.', 'staff', 1, '2025-08-26 06:57:49'),
(24, 'Rasya', 'rasya@vorta.local', '$2y$10$QTqxs0YdOMMf6Skjc29xQePqjo7ZQ437F9AN9NScEbofztLX3msn2', 'staff', 1, '2025-08-26 06:57:49'),
(25, 'Rifki', 'rifki@vorta.local', '$2y$10$a2sMYMqhULnCJieQY14LH.vDoNh4wrsWsnc209t5EnbEwGARAAnBy', 'staff', 1, '2025-08-26 07:47:28'),
(26, 'Marsya', 'marsya@vorta.local', '$2y$10$YY3d8V/bUMGeUvH1M.SFA.zJWOSaGiii9L1i.2GdbIIvT0nStw9vm', 'staff', 1, '2025-08-26 07:47:28'),
(131, 'Prasetyo Adi', 'Prasetyo@vorta.local', '$2y$10$vmXEvC33fbXLknb9zkolzeM0Krej2rrliMXxAmXOtF1hBKZZe8xqG', 'admin', 1, '2025-08-27 13:16:01'),
(132, 'Ristyo Arditto', 'Ristyo@vorta.local', '$2y$10$cxn5OHitScmYKtNPu//3Fuxi6jTvuSUXVznEsT8r3OEpDHn9dgMty', 'admin', 1, '2025-08-27 13:16:01'),
(133, 'Rhomie Bireuno', 'Rhomie@vorta.local', '$2y$10$f00i7FyfgY.MW0Mg5sr14udH.Bkqd9IiFMFwXz.wqN9ZQSuH.IaI2', 'staff', 1, '2025-08-27 13:16:01'),
(134, 'Ahmad Zidan', 'Ahmad@vorta.local', '$2y$10$6hgkJtNeflZJdKcE..hU3Oy26d.V7oMh6tbDc5gYpZfMb946aBsyq', 'staff', 1, '2025-08-27 13:16:01'),
(135, 'Muhammad Wildan Ichsanul Akbar', 'Wildan@vorta.local', '$2y$10$.pQjd1GByIVWLDUb1HIiAOIZAhDJUEtVrsBe3KpCOMwiyw14elyUC', 'staff', 1, '2025-08-27 13:16:01'),
(136, 'Agusti Bahtiar', 'Agusti@vorta.local', '$2y$10$Cqdi15r7jL8q6byBUUVlSeTW9MmLvWKECkO0Ot/z6mcdq8ii/DJj6', 'staff', 1, '2025-08-27 13:16:01'),
(137, 'Haikal Fakhri Agnitian', 'Haikal@vorta.local', '$2y$10$1cQJcaziKqzlDg87FNr3XOjXl2pzm6wb29ktdUSX8i95t804gVD0m', 'staff', 1, '2025-08-27 13:16:01'),
(138, 'Muhammad Rizky', 'Rizky@vorta.local', '$2y$10$nwe4aWXtckQf89wKEMcTlOCefRdM5RZz5Jd1N7C.gx4GcptJOaqW.', 'staff', 1, '2025-08-27 13:16:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `work_force`
--

CREATE TABLE `work_force` (
  `workforce_id` int(11) NOT NULL,
  `workforce_name` varchar(100) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `work_force`
--

INSERT INTO `work_force` (`workforce_id`, `workforce_name`, `updated_at`, `created_at`) VALUES
(1, 'Jasa Raharja', '2025-08-26 06:57:47', '2025-08-26 06:57:47'),
(2, 'Hildiktipari', '2025-08-26 06:57:47', '2025-08-26 06:57:47'),
(3, 'Pasifik', '2025-08-26 06:57:47', '2025-08-26 06:57:47'),
(4, 'Inare', '2025-08-26 06:57:47', '2025-08-26 06:57:47'),
(5, 'Antara', '2025-08-26 06:57:47', '2025-08-26 06:57:47'),
(6, 'Trade Finance', '2025-08-26 06:57:47', '2025-08-26 06:57:47'),
(7, 'CasaMedika', '2025-08-26 06:57:47', '2025-08-26 06:57:47');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`date`);

--
-- Indeks untuk tabel `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_workforce_id` (`workforce_id`);

--
-- Indeks untuk tabel `employees_workforce`
--
ALTER TABLE `employees_workforce`
  ADD PRIMARY KEY (`employee_id`,`workforce_id`),
  ADD KEY `workforce_id` (`workforce_id`);

--
-- Indeks untuk tabel `job_type`
--
ALTER TABLE `job_type`
  ADD PRIMARY KEY (`job_type_id`);

--
-- Indeks untuk tabel `production_reports`
--
ALTER TABLE `production_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_reports_user` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `work_force`
--
ALTER TABLE `work_force`
  ADD PRIMARY KEY (`workforce_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT untuk tabel `job_type`
--
ALTER TABLE `job_type`
  MODIFY `job_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `production_reports`
--
ALTER TABLE `production_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT untuk tabel `work_force`
--
ALTER TABLE `work_force`
  MODIFY `workforce_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Ketidakleluasaan untuk tabel `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_workforce_id` FOREIGN KEY (`workforce_id`) REFERENCES `work_force` (`workforce_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `employees_workforce`
--
ALTER TABLE `employees_workforce`
  ADD CONSTRAINT `employees_workforce_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employees_workforce_ibfk_2` FOREIGN KEY (`workforce_id`) REFERENCES `work_force` (`workforce_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `production_reports`
--
ALTER TABLE `production_reports`
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

ALTER TABLE attendance 
ADD COLUMN explanation TEXT NULL AFTER notes;