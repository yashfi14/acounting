<?php

/**
 * AuthHelper — Middleware autentikasi dan helper identitas/role pengguna.
 */
class AuthHelper {

    /**
     * Pastikan user sudah LOGIN (belum tentu terverifikasi).
     * Digunakan di VerificationController.
     */
    public static function check()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASEURL . '/auth');
            exit;
        }
    }

    /**
     * Cek apakah pengguna saat ini sudah login (boolean).
     */
    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    /**
     * Pastikan user sudah LOGIN dan TERVERIFIKASI (email + WA).
     */
    public static function checkVerified()
    {
        self::check(); // Pastikan sudah login dulu

        $emailOk = isset($_SESSION['email_verified'])    && $_SESSION['email_verified']    === true;
        $waOk    = isset($_SESSION['whatsapp_verified']) && $_SESSION['whatsapp_verified'] === true;

        if (!$emailOk || !$waOk) {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }
    }

    /**
     * Pastikan user BELUM LOGIN (untuk halaman login/register).
     * Jika sudah login, redirect sesuai role.
     */
    public static function checkGuest()
    {
        if (!session_id()) session_start();

        if (isset($_SESSION['user_id'])) {
            if (self::role() === 'pelanggan') {
                header('Location: ' . BASEURL . '/catalog');
            } else {
                header('Location: ' . BASEURL . '/dashboard');
            }
            exit;
        }
    }

    /**
     * Dapatkan role pengguna saat ini.
     * Mengembalikan null jika pengguna belum login.
     */
    public static function role(): ?string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $_SESSION['user_role'] ?? 'pelanggan';
    }

    /**
     * Cek apakah role adalah Admin.
     */
    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /**
     * Cek apakah role adalah Kasir.
     */
    public static function isKasir(): bool
    {
        return self::role() === 'kasir';
    }

    /**
     * Cek apakah role adalah Pelanggan.
     */
    public static function isPelanggan(): bool
    {
        return self::role() === 'pelanggan';
    }

    /**
     * Dapatkan ID lingkup usaha (business_id / admin_id toko).
     * Jika admin: ID dirinya sendiri.
     * Jika kasir: admin_id toko tempatnya bekerja (atau ID dirinya jika mandiri).
     * Jika pelanggan: admin_id toko atau default 1.
     */
    public static function businessId(): int
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        $role = self::role();
        if ($role === 'admin') {
            return (int)($_SESSION['user_id'] ?? 1);
        }
        
        if (!empty($_SESSION['admin_id'])) {
            return (int)$_SESSION['admin_id'];
        }

        // Untuk pelanggan yang mendaftar mandiri, lingkup usaha mengarah ke Admin toko utama
        if ($role === 'pelanggan') {
            return (int)($_SESSION['store_admin_id'] ?? 1);
        }

        return (int)($_SESSION['user_id'] ?? 1);
    }
}
