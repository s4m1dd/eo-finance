-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Waktu pembuatan: 12 Jul 2026 pada 20.54
-- Versi server: 8.0.44
-- Versi PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `eo_finance`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `nama_user` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aksi` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entitas` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entitas_id` int UNSIGNED DEFAULT NULL,
  `deskripsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_lama` text COLLATE utf8mb4_unicode_ci,
  `data_baru` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `eo`
--

CREATE TABLE `eo` (
  `id` int UNSIGNED NOT NULL,
  `nama_eo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `events`
--

CREATE TABLE `events` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `klien_id` int UNSIGNED DEFAULT NULL,
  `nama_event` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `lokasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','aktif','selesai','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `anggaran` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `expense_template`
--

CREATE TABLE `expense_template` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `nama_template` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` enum('pemasukan','pengeluaran') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pengeluaran',
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_estimasi` decimal(18,2) DEFAULT NULL COMMENT 'Estimasi jumlah; bisa diubah saat dipakai',
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `klien`
--

CREATE TABLE `klien` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` bigint UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `tipe` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `judul` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `otp_2fa`
--

CREATE TABLE `otp_2fa` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `kode_otp` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expired_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `otp_reset`
--

CREATE TABLE `otp_reset` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `kode_otp` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expired_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penugasan`
--

CREATE TABLE `penugasan` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `event_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `role_dalam_event` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `event_id` int UNSIGNED NOT NULL,
  `vendor_id` int UNSIGNED DEFAULT NULL,
  `tipe` enum('pemasukan','pengeluaran') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_approval` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `status_pembayaran` enum('lunas','belum_lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lunas',
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` decimal(18,2) NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `bukti_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bukti_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bukti_data` mediumblob,
  `tanggal_transaksi` date NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `catatan_approval` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin_eo','staff_eo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT '0',
  `two_fa_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = 2FA aktif untuk akun ini (hanya admin_eo)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `vendor`
--

CREATE TABLE `vendor` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_layanan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `vendor_review`
--

CREATE TABLE `vendor_review` (
  `id` int UNSIGNED NOT NULL,
  `eo_id` int UNSIGNED NOT NULL,
  `vendor_id` int UNSIGNED NOT NULL,
  `event_id` int UNSIGNED NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL DEFAULT '3' COMMENT '1–5: 1=sangat buruk, 5=sangat baik',
  `catatan` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_eo_waktu` (`eo_id`,`created_at`),
  ADD KEY `idx_audit_entitas` (`entitas`,`entitas_id`),
  ADD KEY `idx_audit_user` (`user_id`);

--
-- Indeks untuk tabel `eo`
--
ALTER TABLE `eo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_eo` (`eo_id`),
  ADD KEY `idx_events_klien` (`klien_id`),
  ADD KEY `idx_events_status` (`status`),
  ADD KEY `fk_events_created_by` (`created_by`);

--
-- Indeks untuk tabel `expense_template`
--
ALTER TABLE `expense_template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_template_eo` (`eo_id`),
  ADD KEY `fk_template_user` (`created_by`);

--
-- Indeks untuk tabel `klien`
--
ALTER TABLE `klien`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_klien_eo` (`eo_id`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user_unread` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_notif_eo` (`eo_id`);

--
-- Indeks untuk tabel `otp_2fa`
--
ALTER TABLE `otp_2fa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp2fa_user` (`user_id`);

--
-- Indeks untuk tabel `otp_reset`
--
ALTER TABLE `otp_reset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user` (`user_id`);

--
-- Indeks untuk tabel `penugasan`
--
ALTER TABLE `penugasan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_penugasan_event_user` (`event_id`,`user_id`),
  ADD KEY `idx_penugasan_eo` (`eo_id`),
  ADD KEY `idx_penugasan_user` (`user_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaksi_eo` (`eo_id`),
  ADD KEY `idx_transaksi_event` (`event_id`),
  ADD KEY `idx_transaksi_tipe` (`tipe`),
  ADD KEY `fk_transaksi_vendor` (`vendor_id`),
  ADD KEY `fk_transaksi_created_by` (`created_by`),
  ADD KEY `idx_transaksi_status_approval` (`eo_id`,`status_approval`),
  ADD KEY `fk_transaksi_approved_by` (`approved_by`),
  ADD KEY `idx_transaksi_status_pembayaran` (`eo_id`,`tipe`,`status_pembayaran`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email_per_eo` (`eo_id`,`email`);

--
-- Indeks untuk tabel `vendor`
--
ALTER TABLE `vendor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_eo` (`eo_id`);

--
-- Indeks untuk tabel `vendor_review`
--
ALTER TABLE `vendor_review`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_vendor_event` (`vendor_id`,`event_id`),
  ADD KEY `idx_review_eo` (`eo_id`),
  ADD KEY `idx_review_vendor` (`vendor_id`),
  ADD KEY `fk_review_event` (`event_id`),
  ADD KEY `fk_review_user` (`created_by`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `eo`
--
ALTER TABLE `eo`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `events`
--
ALTER TABLE `events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `expense_template`
--
ALTER TABLE `expense_template`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `klien`
--
ALTER TABLE `klien`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `otp_2fa`
--
ALTER TABLE `otp_2fa`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `otp_reset`
--
ALTER TABLE `otp_reset`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `penugasan`
--
ALTER TABLE `penugasan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `vendor`
--
ALTER TABLE `vendor`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `vendor_review`
--
ALTER TABLE `vendor_review`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_events_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_events_klien` FOREIGN KEY (`klien_id`) REFERENCES `klien` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `expense_template`
--
ALTER TABLE `expense_template`
  ADD CONSTRAINT `fk_template_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_template_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `klien`
--
ALTER TABLE `klien`
  ADD CONSTRAINT `fk_klien_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notif_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `otp_2fa`
--
ALTER TABLE `otp_2fa`
  ADD CONSTRAINT `fk_otp2fa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `otp_reset`
--
ALTER TABLE `otp_reset`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penugasan`
--
ALTER TABLE `penugasan`
  ADD CONSTRAINT `fk_penugasan_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_penugasan_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_penugasan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transaksi_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_transaksi_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transaksi_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transaksi_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `vendor`
--
ALTER TABLE `vendor`
  ADD CONSTRAINT `fk_vendor_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `vendor_review`
--
ALTER TABLE `vendor_review`
  ADD CONSTRAINT `fk_review_eo` FOREIGN KEY (`eo_id`) REFERENCES `eo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_review_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
