-- ============================================================
-- Migration: Multi-provider AI Aca
-- Database: acounting_db
--
-- Menambahkan konfigurasi per provider (API key terenkripsi) dan
-- provider cadangan. Data Gemini lama tidak dihapus.
--
-- Aman dijalankan berulang: penambahan kolom dicek lewat information_schema
-- dan tabel memakai CREATE TABLE IF NOT EXISTS.
-- ============================================================

USE `acounting_db`;

-- 1. Konfigurasi tiap provider per admin.
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

-- 2. Kolom is_active dan provider cadangan pada tabel settings utama.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aca_ai_settings' AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `aca_ai_settings` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `admin_id`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aca_ai_settings' AND COLUMN_NAME = 'fallback_provider'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `aca_ai_settings` ADD COLUMN `fallback_provider` VARCHAR(40) DEFAULT NULL AFTER `model`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aca_ai_settings' AND COLUMN_NAME = 'fallback_model'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `aca_ai_settings` ADD COLUMN `fallback_model` VARCHAR(120) DEFAULT NULL AFTER `fallback_provider`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Migrasikan API key provider aktif dari skema lama tanpa membocorkan
--    plaintext. Cipher yang sudah ada dipertahankan jika migrasi diulang.
INSERT INTO `aca_ai_provider_configs`
    (`admin_id`, `provider`, `model`, `api_key_cipher`, `api_key_last4`,
     `api_key_updated_at`, `last_status`, `last_status_message`, `last_checked_at`)
SELECT
    `admin_id`, `provider`, `model`, `api_key_cipher`, `api_key_last4`,
    `api_key_updated_at`, `last_status`, `last_status_message`, `last_checked_at`
FROM `aca_ai_settings`
WHERE `api_key_cipher` IS NOT NULL AND `api_key_cipher` <> ''
ON DUPLICATE KEY UPDATE
    `api_key_cipher` = COALESCE(`aca_ai_provider_configs`.`api_key_cipher`, VALUES(`api_key_cipher`)),
    `api_key_last4` = COALESCE(`aca_ai_provider_configs`.`api_key_last4`, VALUES(`api_key_last4`)),
    `api_key_updated_at` = COALESCE(`aca_ai_provider_configs`.`api_key_updated_at`, VALUES(`api_key_updated_at`));

-- 4. Simpan metadata provider aktif walau key masih kosong.
INSERT INTO `aca_ai_provider_configs`
    (`admin_id`, `provider`, `model`, `last_status`, `last_status_message`, `last_checked_at`)
SELECT
    `admin_id`, `provider`, `model`, `last_status`, `last_status_message`, `last_checked_at`
FROM `aca_ai_settings`
ON DUPLICATE KEY UPDATE
    `aca_ai_provider_configs`.`id` = `aca_ai_provider_configs`.`id`;
