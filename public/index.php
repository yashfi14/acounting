<?php

/**
 * Front Controller — Entry point utama aplikasi Acounting.
 * Semua request diproses melalui file ini via .htaccess URL rewriting.
 */

// === Output Buffering ===
// Mencegah PHP warning/notice merusak respons JSON dari endpoint AJAX
ob_start();

// === Session Security Hardening ===
// Gunakan nama sesi yang unik (bukan nama default 'PHPSESSID')
session_name('acounting_sid');

session_set_cookie_params([
    'lifetime' => 0,                          // Sesi berakhir saat browser ditutup
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),   // True jika HTTPS
    'httponly' => true,                        // Tidak bisa diakses via JavaScript
    'samesite' => 'Lax',                      // Lax: kompatibel dengan redirect GET
]);

if (!session_id()) {
    session_start();
}

// === Load Konfigurasi Utama ===
require_once '../app/Config/config.php';

// === Load Core Framework ===
require_once '../app/Core/App.php';
require_once '../app/Core/Controller.php';
require_once '../app/Core/Database.php';
require_once '../app/Core/AuthHelper.php';
require_once '../app/Core/AuthMiddleware.php';
require_once '../app/Core/RoleMiddleware.php';
require_once '../app/Core/Auth.php';
require_once '../app/Core/CSRF.php';
require_once '../app/Core/Flasher.php';

// === Jalankan Router ===
$app = new App;
