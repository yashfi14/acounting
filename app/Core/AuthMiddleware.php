<?php

/**
 * AuthMiddleware — Middleware pemeriksaan status autentikasi pengguna.
 */
class AuthMiddleware {

    /**
     * Pastikan pengguna sudah login.
     */
    public static function check()
    {
        AuthHelper::check();
    }

    /**
     * Periksa apakah pengguna sedang login (boolean).
     */
    public static function isLoggedIn(): bool
    {
        return AuthHelper::isLoggedIn();
    }

    /**
     * Pastikan pengguna sudah login dan akunnya terverifikasi.
     */
    public static function checkVerified()
    {
        AuthHelper::checkVerified();
    }

    /**
     * Pastikan pengguna belum login (untuk halaman login/register).
     */
    public static function checkGuest()
    {
        AuthHelper::checkGuest();
    }
}
