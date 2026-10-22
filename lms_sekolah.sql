DROP DATABASE IF EXISTS `db_lms`;
CREATE DATABASE `db_lms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_lms`;

-- Tabel Users (Siswa & Guru)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `role` ENUM('siswa', 'guru') NOT NULL,
  `kelas` VARCHAR(20) DEFAULT NULL,
  `no_telp` VARCHAR(20),
  `email` VARCHAR(100),
  `foto_profil` VARCHAR(255) DEFAULT 'default.jpg',
  `failed_attempts` INT DEFAULT 0,
  `lockout_time` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Modul Pembelajaran per Kelas / Mapel
CREATE TABLE `modul` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(150) NOT NULL,
  `mapel` VARCHAR(50) NOT NULL,
  `tingkat_kelas` VARCHAR(20) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Tabel LKPD & Tugas
CREATE TABLE `lkpd` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(150) NOT NULL,
  `mapel` VARCHAR(50) NOT NULL,
  `kelas` VARCHAR(20) NOT NULL,
  `tipe` ENUM('file', 'link') NOT NULL,
  `file_or_link` VARCHAR(255) NOT NULL,
  `pertemuan_ke` INT DEFAULT 1,
  `uploaded_by` INT,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Tabel Pengumpulan Tugas & Jawaban Teks
CREATE TABLE `pengumpulan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_siswa` INT NOT NULL,
  `id_lkpd` INT DEFAULT NULL,
  `tipe_tugas` ENUM('lkpd', 'lain') NOT NULL,
  `judul_tugas` VARCHAR(150),
  `mapel` VARCHAR(50) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `jawaban_teks` TEXT DEFAULT NULL,
  `nilai` INT DEFAULT NULL,
  `catatan_guru` TEXT DEFAULT NULL,
  `tgl_pengerjaan` DATE DEFAULT (CURRENT_DATE),
  FOREIGN KEY (`id_siswa`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_lkpd`) REFERENCES `lkpd`(`id`) ON DELETE CASCADE
);

-- Tabel Absensi & Geofencing GPS
CREATE TABLE `absensi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_siswa` INT NOT NULL,
  `mapel` VARCHAR(50) NOT NULL,
  `tanggal` DATE NOT NULL,
  `keterangan` ENUM('Hadir', 'Sakit', 'Izin', 'Alpha') NOT NULL,
  `catatan` TEXT,
  `foto_absen` VARCHAR(255),
  `lokasi_gps` VARCHAR(100),
  `created_by` INT,
  UNIQUE KEY `unique_absen_harian` (`id_siswa`, `tanggal`, `mapel`),
  FOREIGN KEY (`id_siswa`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Tabel Pengaturan Sistem (Koordinat Lab Geofencing & Status Chat)
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NOT NULL
);

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('lab_lat', '-6.402500'),
('lab_lng', '106.978000'),
('chat_status', 'active');

-- Tabel Live Chat Terisolasi per Kelas
CREATE TABLE `class_chats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kelas` VARCHAR(20) NOT NULL,
  `id_sender` INT NOT NULL,
  `pesan` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_sender`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Tabel WebRTC Signaling P2P
CREATE TABLE `webrtc_stream` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `target_id` INT DEFAULT NULL,
  `type` VARCHAR(20) NOT NULL,
  `payload` MEDIUMTEXT NOT NULL
);

-- Akun Default Guru/Admin ('admin' / 'adminxampp')
INSERT INTO `users` (`username`, `password`, `nama_lengkap`, `role`, `email`) VALUES
('admin', '$2y$10$usesomesillystringfore2uDLvn12bIqp.1vH.x/o.YwB9b.jG2', 'Administrator Utama', 'guru', 'admin@sekolah.sch.id');