-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 21, 2026 at 01:35 AM
-- Server version: 8.0.44
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `akademitrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_kuliah`
--

CREATE TABLE `jadwal_kuliah` (
  `id` int NOT NULL,
  `mata_kuliah_id` int NOT NULL,
  `hari` tinyint NOT NULL COMMENT '1=Senin, 7=Minggu',
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `ruangan` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jadwal_kuliah`
--

INSERT INTO `jadwal_kuliah` (`id`, `mata_kuliah_id`, `hari`, `jam_mulai`, `jam_selesai`, `ruangan`, `created_at`) VALUES
(1, 1, 1, '08:00:00', '10:00:00', 'Lab Komputer 1', '2026-10-21 01:01:40'),
(2, 2, 2, '10:00:00', '12:00:00', 'Ruang B203', '2026-10-21 01:01:40'),
(3, 3, 3, '13:00:00', '15:00:00', 'Ruang A105', '2026-10-21 01:01:40'),
(4, 4, 4, '08:00:00', '10:00:00', 'Lab Komputer 2', '2026-10-21 01:01:40'),
(5, 1, 5, '09:00:00', '11:00:00', 'Lab Komputer 1', '2026-10-21 01:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `id` int NOT NULL,
  `nama_mata_kuliah` varchar(100) NOT NULL,
  `dosen` varchar(100) DEFAULT NULL,
  `warna` varchar(20) DEFAULT '#79502E',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`id`, `nama_mata_kuliah`, `dosen`, `warna`, `created_at`) VALUES
(1, 'Pemrograman Web', 'Bapak Andi Pratama', '#243B64', '2026-10-21 00:56:09'),
(2, 'Basis Data', 'Ibu Siti Rahmawati', '#4F7CAC', '2026-10-21 01:01:40'),
(3, 'Bisnis Digital', 'Bapak Dimas Saputra', '#5B8E7D', '2026-10-21 01:01:40'),
(4, 'Analisis Data', 'Ibu Rina Puspitasari', '#B77945', '2026-10-21 01:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `sesi_belajar`
--

CREATE TABLE `sesi_belajar` (
  `id` int NOT NULL,
  `tugas_id` int DEFAULT NULL,
  `target_skill_id` int DEFAULT NULL,
  `judul` varchar(150) NOT NULL,
  `jenis` enum('Mengerjakan Tugas','Review Materi','Latihan Skill') NOT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime NOT NULL,
  `status` enum('Terjadwal','Selesai','Dibatalkan') NOT NULL DEFAULT 'Terjadwal',
  `catatan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sesi_belajar`
--

INSERT INTO `sesi_belajar` (`id`, `tugas_id`, `target_skill_id`, `judul`, `jenis`, `waktu_mulai`, `waktu_selesai`, `status`, `catatan`, `created_at`) VALUES
(1, 1, 1, 'Mengerjakan CRUD PHP', 'Mengerjakan Tugas', '2026-10-21 19:00:00', '2026-10-21 20:30:00', 'Terjadwal', 'Fokus pada proses penyimpanan data.', '2026-10-21 01:01:40'),
(2, 2, 1, 'Menyusun laporan basis data', 'Mengerjakan Tugas', '2026-10-22 19:00:00', '2026-10-22 20:00:00', 'Terjadwal', 'Melengkapi laporan praktikum.', '2026-10-21 01:01:40'),
(3, 3, 2, 'Mempelajari analisis UMKM', 'Review Materi', '2026-10-20 16:00:00', '2026-10-20 17:00:00', 'Selesai', 'Membaca materi analisis UMKM.', '2026-10-21 01:01:40'),
(4, NULL, 3, 'Latihan konsistensi UI', 'Latihan Skill', '2026-10-23 15:30:00', '2026-10-23 16:15:00', 'Terjadwal', 'Memeriksa ukuran komponen.', '2026-10-21 01:01:40'),
(5, 4, 2, 'Latihan visualisasi data', 'Mengerjakan Tugas', '2026-10-19 19:00:00', '2026-10-19 20:00:00', 'Selesai', 'Mencoba membuat grafik sederhana.', '2026-10-21 01:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `target_skill`
--

CREATE TABLE `target_skill` (
  `id` int NOT NULL,
  `nama_skill` varchar(100) NOT NULL,
  `deskripsi` text,
  `target_tanggal` date DEFAULT NULL,
  `progres` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('Berjalan','Tercapai','Ditunda') NOT NULL DEFAULT 'Berjalan',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `target_skill`
--

INSERT INTO `target_skill` (`id`, `nama_skill`, `deskripsi`, `target_tanggal`, `progres`, `status`, `created_at`) VALUES
(1, 'PHP dan MySQL', 'Belajar membuat aplikasi CRUD.', '2026-11-15', 65, 'Berjalan', '2026-10-21 01:01:40'),
(2, 'Analisis Data', 'Belajar mengolah dan memvisualisasikan data.', '2026-12-01', 35, 'Berjalan', '2026-10-21 01:01:40'),
(3, 'Desain UI/UX', 'Meningkatkan kemampuan desain antarmuka.', '2026-11-30', 80, 'Berjalan', '2026-10-21 01:01:40'),
(4, 'Presentasi', 'Berlatih menyampaikan hasil proyek.', '2026-12-10', 100, 'Tercapai', '2026-10-21 01:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `tugas`
--

CREATE TABLE `tugas` (
  `id` int NOT NULL,
  `mata_kuliah_id` int DEFAULT NULL,
  `judul` varchar(150) NOT NULL,
  `deskripsi` text,
  `deadline` date NOT NULL,
  `prioritas` enum('Rendah','Sedang','Tinggi') NOT NULL DEFAULT 'Sedang',
  `estimasi_menit` int DEFAULT NULL,
  `status` enum('Belum Mulai','Dikerjakan','Selesai') NOT NULL DEFAULT 'Belum Mulai',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tugas`
--

INSERT INTO `tugas` (`id`, `mata_kuliah_id`, `judul`, `deskripsi`, `deadline`, `prioritas`, `estimasi_menit`, `status`, `created_at`) VALUES
(1, 1, 'Membuat halaman CRUD PHP', 'Menyelesaikan fitur tambah, tampil, ubah, dan hapus.', '2026-10-23', 'Tinggi', 180, 'Dikerjakan', '2026-10-21 01:01:40'),
(2, 2, 'Laporan praktikum basis data', 'Menyusun laporan praktikum database.', '2026-10-24', 'Tinggi', 120, 'Belum Mulai', '2026-10-21 01:01:40'),
(3, 3, 'Analisis media sosial UMKM', 'Menganalisis konten promosi digital.', '2026-10-27', 'Sedang', 90, 'Belum Mulai', '2026-10-21 01:01:40'),
(4, 4, 'Latihan visualisasi data', 'Membuat visualisasi data sederhana.', '2026-10-20', 'Sedang', 120, 'Dikerjakan', '2026-10-21 01:01:40'),
(5, 1, 'Revisi tampilan AkademiTrack', 'Merapikan tampilan dan responsivitas.', '2026-10-29', 'Rendah', 60, 'Selesai', '2026-10-21 01:01:40'),
(6, 2, 'Mempelajari normalisasi database', 'Membuat catatan normalisasi tabel.', '2026-10-30', 'Rendah', 45, 'Belum Mulai', '2026-10-21 01:01:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jadwal_mata_kuliah` (`mata_kuliah_id`);

--
-- Indexes for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sesi_belajar`
--
ALTER TABLE `sesi_belajar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sesi_tugas` (`tugas_id`),
  ADD KEY `fk_sesi_skill` (`target_skill_id`);

--
-- Indexes for table `target_skill`
--
ALTER TABLE `target_skill`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tugas`
--
ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tugas_mata_kuliah` (`mata_kuliah_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sesi_belajar`
--
ALTER TABLE `sesi_belajar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `target_skill`
--
ALTER TABLE `target_skill`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tugas`
--
ALTER TABLE `tugas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD CONSTRAINT `fk_jadwal_mata_kuliah` FOREIGN KEY (`mata_kuliah_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sesi_belajar`
--
ALTER TABLE `sesi_belajar`
  ADD CONSTRAINT `fk_sesi_skill` FOREIGN KEY (`target_skill_id`) REFERENCES `target_skill` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sesi_tugas` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tugas`
--
ALTER TABLE `tugas`
  ADD CONSTRAINT `fk_tugas_mata_kuliah` FOREIGN KEY (`mata_kuliah_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
