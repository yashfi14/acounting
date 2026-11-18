-- ============================================================
-- Migration: Upgrade AI Assistant Aca (Local-first + multi-provider)
-- Database: acounting_db
-- Aman dijalankan berulang (CREATE TABLE IF NOT EXISTS).
--
-- Catatan: tabel memakai prefix `aca_` agar tidak bertabrakan dengan
-- tabel ai_settings/ai_audit_log milik aplikasi lain pada database yang sama.
-- ============================================================

USE `acounting_db`;

-- 1. Konfigurasi Aca per Admin / toko.
--    API key TIDAK pernah disimpan sebagai plaintext.
--    Nilai api_key_cipher dienkripsi AES-256-GCM oleh AICryptoService.
--    Kunci enkripsi disimpan terpisah dari database (env / storage key file).
CREATE TABLE IF NOT EXISTS `aca_ai_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_id` BIGINT(20) UNSIGNED NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `provider` VARCHAR(30) NOT NULL DEFAULT 'relink',
    `model` VARCHAR(100) NOT NULL DEFAULT 'deepseek-v4-flash',
    `fallback_provider` VARCHAR(40) DEFAULT NULL,
    `fallback_model` VARCHAR(120) DEFAULT NULL,
    `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.30,
    `max_output_tokens` INT(11) NOT NULL DEFAULT 800,
    `api_key_cipher` TEXT DEFAULT NULL,
    `api_key_last4` VARCHAR(8) DEFAULT NULL,
    `api_key_updated_at` DATETIME DEFAULT NULL,
    `last_status` VARCHAR(40) NOT NULL DEFAULT 'not_configured',
    `last_status_message` VARCHAR(255) DEFAULT NULL,
    `last_checked_at` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_aca_ai_settings_admin` (`admin_id`),
    CONSTRAINT `fk_aca_ai_settings_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1b. Konfigurasi per provider AI (multi-provider).
CREATE TABLE IF NOT EXISTS `aca_ai_provider_configs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_id` BIGINT(20) UNSIGNED NOT NULL,
    `provider` VARCHAR(40) NOT NULL,
    `model` VARCHAR(120) NOT NULL DEFAULT '',
    `base_url` VARCHAR(255) DEFAULT NULL,
    `api_key_cipher` TEXT DEFAULT NULL,
    `api_key_last4` VARCHAR(8) DEFAULT NULL,
    `api_key_updated_at` DATETIME DEFAULT NULL,
    `last_status` VARCHAR(40) NOT NULL DEFAULT 'not_configured',
    `last_status_message` VARCHAR(255) DEFAULT NULL,
    `last_checked_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_aca_ai_provider_admin` (`admin_id`, `provider`),
    KEY `idx_aca_ai_provider_status` (`last_status`),
    CONSTRAINT `fk_aca_ai_provider_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Audit log tindakan administratif Aca.
--    Jangan pernah mencatat API key, password, atau token di sini.
CREATE TABLE IF NOT EXISTS `aca_ai_audit_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` BIGINT(20) UNSIGNED NOT NULL,
    `action` VARCHAR(60) NOT NULL,
    `detail` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aca_ai_audit_admin` (`admin_id`),
    KEY `idx_aca_ai_audit_created` (`created_at`),
    CONSTRAINT `fk_aca_ai_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Audit aktivitas query Aca (semua role).
--    Tidak menyimpan isi pertanyaan/jawaban mentah agar data sensitif tidak
--    terduplikasi. Hanya metadata operasional untuk audit kinerja & keamanan.
CREATE TABLE IF NOT EXISTS `aca_ai_query_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'pelanggan',
    `provider` VARCHAR(20) NOT NULL DEFAULT 'local',
    `fallback_reason` VARCHAR(40) DEFAULT NULL,
    `question_length` INT(11) NOT NULL DEFAULT 0,
    `duration_ms` INT(11) NOT NULL DEFAULT 0,
    `is_success` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aca_ai_query_user` (`user_id`),
    KEY `idx_aca_ai_query_created` (`created_at`),
    KEY `idx_aca_ai_query_provider` (`provider`),
    CONSTRAINT `fk_aca_ai_query_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
