-- Migration: perbaikan skema users untuk Manajemen Pengguna & verifikasi
-- Database: acounting_db
-- Aman dijalankan berulang (idempotent) pada MariaDB 10.4+.

USE `acounting_db`;

-- Kode User_model/UserController memakai kolom verification_status,
-- tetapi kolom ini belum ada pada database yang diimpor dari database.sql.
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `verification_status` VARCHAR(50) NOT NULL DEFAULT 'unverified'
  AFTER `whatsapp_verified_at`;

-- Sinkronkan status untuk akun yang sudah terverifikasi email + WhatsApp.
UPDATE `users`
SET `verification_status` = 'verified'
WHERE `email_verified_at` IS NOT NULL
  AND `whatsapp_verified_at` IS NOT NULL
  AND (`verification_status` IS NULL OR `verification_status` <> 'verified');
