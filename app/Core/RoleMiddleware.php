<?php

/**
 * RoleMiddleware — Middleware otorisasi berbasis Role (RBAC).
 * 
 * Memeriksa hak akses pengguna di tingkat backend sebelum controller atau aksi dijalankan.
 * Menjamin keamanan endpoint tidak hanya bergantung pada penyembunyian elemen frontend.
 */
class RoleMiddleware {

    /**
     * Izinkan hanya role tertentu untuk mengakses controller/aksi.
     *
     * @param string|array $allowedRoles Role yang diperbolehkan (misal: 'admin' atau ['admin', 'kasir'])
     * @param string|null  $customMessage Pesan error kustom untuk 403
     */
    public static function allow($allowedRoles, $customMessage = null)
    {
        // Pastikan pengguna sudah login dan terverifikasi
        AuthHelper::checkVerified();

        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        $currentRole = self::role();

        if (!in_array($currentRole, $allowedRoles, true)) {
            self::deny($customMessage);
        }
    }

    /**
     * Tolak akses dan tampilkan HTTP 403 Forbidden.
     */
    public static function deny($customMessage = null)
    {
        // Deteksi apakah request berupa AJAX / JSON
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        if ($isAjax) {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $customMessage ?? '403 Forbidden: Anda tidak memiliki izin untuk melakukan tindakan ini.'
            ]);
            exit;
        }

        http_response_code(403);
        $roleName = self::role() ? ucfirst(self::role()) : 'Tamu';
        $errorMessage = $customMessage ?? 'Peran akun Anda (' . htmlspecialchars($roleName) . ') tidak memiliki akses ke fitur atau halaman ini.';
        require_once __DIR__ . '/../Views/errors/403.php';
        exit;
    }

    /**
     * Dapatkan role pengguna saat ini dari sesi.
     * Mengembalikan null jika pengguna belum login (guest).
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
     * Periksa apakah pengguna saat ini adalah Admin.
     */
    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /**
     * Periksa apakah pengguna saat ini adalah Kasir.
     */
    public static function isKasir(): bool
    {
        return self::role() === 'kasir';
    }

    /**
     * Periksa apakah pengguna saat ini adalah Pelanggan.
     */
    public static function isPelanggan(): bool
    {
        return self::role() === 'pelanggan';
    }

    /**
     * Periksa apakah pengguna memiliki role tertentu (bisa string atau array of roles).
     */
    public static function hasRole(string|array $role): bool
    {
        $currentRole = self::role();
        if ($currentRole === null) {
            return false;
        }
        if (is_array($role)) {
            $normalized = array_map('strtolower', $role);
            return in_array($currentRole, $normalized, true);
        }
        return $currentRole === strtolower($role);
    }
}
