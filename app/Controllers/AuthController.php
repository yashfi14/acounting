<?php

/**
 * AuthController — Menangani Login dan Logout pengguna.
 * 
 * Perbaikan keamanan:
 * - session_regenerate_id(true) setelah login sukses (mencegah Session Fixation)
 * - CSRF token divalidasi pada POST login
 * - Cookie sesi dihapus saat logout
 * - Flash message di-escape dengan htmlspecialchars
 * - Tidak bocorkan informasi spesifik (email vs password salah)
 */
class AuthController extends Controller {

    public function index()
    {
        // Jika sudah login, lempar ke dashboard
        AuthHelper::checkGuest();

        $data['judul']       = 'Login';
        $data['csrf_token']  = CSRF::token();

        // Ambil dan bersihkan flash messages
        $data['error']     = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
        $data['old_email'] = isset($_SESSION['old_email'])   ? $_SESSION['old_email']   : '';
        unset($_SESSION['login_error'], $_SESSION['old_email']);

        $this->view('templates/auth_header', $data);
        $this->view('auth/login', $data);
        $this->view('templates/auth_footer');
    }

    public function login()
    {
        AuthHelper::checkGuest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/auth');
            exit;
        }

        // Validasi CSRF
        if (!isset($_POST['csrf_token']) || !CSRF::validate($_POST['csrf_token'])) {
            $_SESSION['login_error'] = 'Request tidak valid. Silakan coba lagi.';
            header('Location: ' . BASEURL . '/auth');
            exit;
        }

        $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Validasi input dasar
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email dan password wajib diisi.';
            $_SESSION['old_email']   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            header('Location: ' . BASEURL . '/auth');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['login_error'] = 'Format email tidak valid.';
            $_SESSION['old_email']   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            header('Location: ' . BASEURL . '/auth');
            exit;
        }

        $userModel = $this->model('User_model');
        $user      = $userModel->getUserByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // ✅ Cek status keaktifan akun
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                $_SESSION['login_error'] = 'Akun Anda telah dinonaktifkan oleh Administrator. Silakan hubungi pengelola.';
                $_SESSION['old_email']   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                header('Location: ' . BASEURL . '/auth');
                exit;
            }

            // Regenerate session ID untuk mencegah Session Fixation Attack
            session_regenerate_id(true);

            $_SESSION['user_id']            = $user['id'];
            $_SESSION['user_name']          = $user['nama_lengkap'];
            $_SESSION['user_email']         = $user['email'];
            $_SESSION['user_whatsapp']      = $user['whatsapp'];
            $_SESSION['user_role']          = $user['role'] ?? 'pelanggan'; // Role tetap dari DB
            $_SESSION['admin_id']           = $user['admin_id'] ?? null;
            $_SESSION['email_verified']     = !empty($user['email_verified_at']);
            $_SESSION['whatsapp_verified']  = !empty($user['whatsapp_verified_at']);
            $_SESSION['operational_status'] = 'aktif'; // Status default saat login

            // Cek status verifikasi sebelum redirect
            if (!$_SESSION['email_verified'] || !$_SESSION['whatsapp_verified']) {
                header('Location: ' . BASEURL . '/verification/status');
            } else {
                // Arahkan pelanggan langsung ke katalog barang/menu (tidak ke dashboard administratif)
                if ($_SESSION['user_role'] === 'pelanggan') {
                    header('Location: ' . BASEURL . '/catalog');
                } else {
                    header('Location: ' . BASEURL . '/dashboard');
                }
            }
            exit;
        }

        // ❌ Login gagal — pesan umum untuk mencegah user enumeration
        $_SESSION['login_error'] = 'Email atau password yang Anda masukkan salah.';
        $_SESSION['old_email']   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        header('Location: ' . BASEURL . '/auth');
        exit;
    }

    public function logout()
    {
        // Hapus semua data session
        $_SESSION = [];

        // Hapus cookie sesi agar tidak bisa digunakan ulang
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: ' . BASEURL . '/auth');
        exit;
    }

    /**
     * switchRole — Hanya untuk keperluan UI preview (tidak mengubah role di database).
     * Role akun bersifat TETAP sesuai yang ditetapkan admin di database.
     * Satu akun hanya memiliki satu peran permanen.
     *
     * Catatan: Fitur ini sengaja TIDAK menulis ke database agar role tidak berubah.
     * Untuk mengubah role pengguna, gunakan halaman Manajemen Pengguna (admin).
     */
    public function switchRole($newRole = "")
    {
        // Role akun bersifat tetap — redirect kembali tanpa perubahan
        $referer = $_SERVER['HTTP_REFERER'] ?? (BASEURL . '/dashboard');
        header('Location: ' . $referer);
        exit;
    }

    public function switchStatus($newStatus = "aktif")
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $validStatuses = ["aktif", "istirahat", "nonaktif", "offline"];
        $status = strtolower(trim($newStatus));
        if (in_array($status, $validStatuses, true)) {
            $_SESSION["operational_status"] = $status;
        }

        $referer = $_SERVER["HTTP_REFERER"] ?? (BASEURL . "/dashboard");
        header("Location: " . $referer);
        exit;
    }
}
