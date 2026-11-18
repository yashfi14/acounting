-- Migration: WhatsApp Notification & OTP Connection
-- Database: acounting_db
-- Catatan: users.id bertipe INT(11). Semua kolom FK WA harus INT(11)
-- agar foreign key dapat dibuat (sebelumnya BIGINT UNSIGNED menyebabkan errno 150).

CREATE TABLE IF NOT EXISTS `wa_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT(11) NOT NULL,
  -- Token gateway disimpan terenkripsi AES-256-GCM oleh WaSetting_model.
  `api_key` VARCHAR(512) NULL,
  `phone_number` VARCHAR(25) NOT NULL,
  `is_connected` TINYINT(1) NOT NULL DEFAULT 0,
  `otp_code` VARCHAR(255) NULL,
  `otp_expires_at` DATETIME NULL,
  `otp_attempts` INT NOT NULL DEFAULT 0,
  `verified_at` DATETIME NULL,
  `notify_transaction` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_low_stock` TINYINT(1) NOT NULL DEFAULT 1,
  `low_stock_threshold` INT NOT NULL DEFAULT 5,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_wa_settings_user` (`user_id`),
  CONSTRAINT `fk_wa_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wa_notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT(11) NOT NULL,
  `phone_number` VARCHAR(25) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('sent', 'failed', 'simulated', 'pending') NOT NULL DEFAULT 'sent',
  `response_info` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_wa_notifications_user` (`user_id`),
  KEY `idx_wa_notifications_status` (`status`),
  CONSTRAINT `fk_wa_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
