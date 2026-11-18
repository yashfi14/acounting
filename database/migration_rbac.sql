-- Migration: Role-Based Access Control (RBAC)
-- Database: acounting_db

-- 1. Tambah kolom role, is_active, admin_id pada tabel users
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `role` ENUM('admin', 'kasir', 'pelanggan') NOT NULL DEFAULT 'pelanggan' AFTER `password`,
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
ADD COLUMN IF NOT EXISTS `admin_id` INT(11) NULL DEFAULT NULL AFTER `is_active`,
ADD COLUMN IF NOT EXISTS `verification_status` VARCHAR(50) NOT NULL DEFAULT 'unverified' AFTER `whatsapp_verified_at`;

-- 2. Tambah kolom is_active dan image pada tabel products
ALTER TABLE `products`
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `price`,
ADD COLUMN IF NOT EXISTS `image` VARCHAR(255) NULL DEFAULT NULL AFTER `is_active`;

-- 3. Tambah kolom cashier_id pada tabel transactions
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `cashier_id` INT(11) NULL DEFAULT NULL AFTER `user_id`;

-- 4. Set akun pertama (ID = 1) sebagai Admin terverifikasi dan aktif
UPDATE `users` 
SET `role` = 'admin', 
    `is_active` = 1, 
    `email_verified_at` = COALESCE(`email_verified_at`, NOW()), 
    `whatsapp_verified_at` = COALESCE(`whatsapp_verified_at`, NOW()), 
    `verification_status` = 'verified' 
WHERE `id` = 1;

-- 5. Set akun lainnya yang belum memiliki role sebagai Pelanggan
UPDATE `users` 
SET `role` = 'pelanggan' 
WHERE `id` != 1 AND (`role` IS NULL OR `role` = '');

-- 6. Update cashier_id pada transaksi lama jika masih NULL
UPDATE `transactions` 
SET `cashier_id` = `user_id` 
WHERE `cashier_id` IS NULL;
