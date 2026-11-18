<?php

class Auth {
    // Memeriksa apakah user sudah login
    public static function check()
    {
        if (!session_id()) session_start();
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['login_error'] = "Anda harus login untuk mengakses halaman ini.";
            header('Location: ' . BASEURL . '/auth');
            exit;
        }
    }

    // Memeriksa apakah user sudah terverifikasi (Email & WA)
    public static function checkVerified()
    {
        self::check(); // Pastikan sudah login

        if (!isset($_SESSION['email_verified']) || !isset($_SESSION['whatsapp_verified'])) {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }
        
        if ($_SESSION['email_verified'] == false || $_SESSION['whatsapp_verified'] == false) {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }
    }

    // Mendapatkan data user yang sedang login dari session
    public static function user()
    {
        if (!session_id()) session_start();
        if (isset($_SESSION['user_id'])) {
            return [
                'id'          => $_SESSION['user_id'],
                'name'        => $_SESSION['user_name'] ?? 'User',
                'email'       => $_SESSION['user_email'] ?? '',
                'whatsapp'    => $_SESSION['user_whatsapp'] ?? '',
                'role'        => $_SESSION['user_role'] ?? 'pelanggan',
                'admin_id'    => $_SESSION['admin_id'] ?? null,
                'business_id' => self::businessId()
            ];
        }
        return null;
    }

    // Mendapatkan role pengguna saat ini
    public static function role(): ?string
    {
        return AuthHelper::role();
    }

    public static function isAdmin(): bool
    {
        return AuthHelper::isAdmin();
    }

    public static function isKasir(): bool
    {
        return AuthHelper::isKasir();
    }

    public static function isPelanggan(): bool
    {
        return AuthHelper::isPelanggan();
    }

    public static function businessId(): int
    {
        return AuthHelper::businessId();
    }
}
