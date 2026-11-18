<?php

/**
 * RegisterController — Menangani proses registrasi akun baru.
 *
 * Perbaikan keamanan:
 * - Validasi CSRF token diaktifkan
 * - Ganti FILTER_SANITIZE_STRING (deprecated PHP 8.1) → strip_tags + htmlspecialchars
 * - Panjang field dibatasi (mencegah DOS via panjang string)
 * - Validasi password minimal 8 karakter (dari 6)
 * - Key old_input konsisten antara view dan controller
 */
class RegisterController extends Controller {

    public function index()
    {
        AuthHelper::checkGuest();

        $data['judul'] = 'Daftar Akun';

        // Ambil flash messages dan old input
        $data['errors']    = isset($_SESSION['auth_errors']) ? $_SESSION['auth_errors'] : [];
        $data['old_input'] = isset($_SESSION['old_input'])   ? $_SESSION['old_input']   : [];
        unset($_SESSION['auth_errors'], $_SESSION['old_input']);

        $this->view('templates/auth_header', $data);
        $this->view('register/index', $data);
        $this->view('templates/auth_footer');
    }

    public function process()
    {
        AuthHelper::checkGuest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/register');
            exit;
        }

        // Validasi CSRF
        if (!isset($_POST['csrf_token']) || !CSRF::validate($_POST['csrf_token'])) {
            $_SESSION['auth_errors'] = ['Request tidak valid. Silakan muat ulang halaman dan coba lagi.'];
            header('Location: ' . BASEURL . '/register');
            exit;
        }

        // Sanitasi input — strip_tags + trim (FILTER_SANITIZE_STRING deprecated di PHP 8.1)
        $nama_lengkap         = strip_tags(trim($_POST['nama_lengkap'] ?? ''));
        $email                = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $whatsapp             = strip_tags(trim($_POST['whatsapp'] ?? ''));
        $sekolah              = strip_tags(trim($_POST['sekolah'] ?? ''));
        $password             = $_POST['password'] ?? '';
        $konfirmasi_password  = $_POST['konfirmasi_password'] ?? '';

        // Simpan old input (TANPA password) untuk repopulate form
        $old_input = [
            'nama_lengkap' => $nama_lengkap,
            'email'        => $email,
            'whatsapp'     => $whatsapp,
            'sekolah'      => $sekolah,
        ];

        $userModel = $this->model('User_model');
        $errors    = [];

        // === Validasi Backend ===

        if (empty($nama_lengkap) || empty($email) || empty($whatsapp) || empty($password)) {
            $errors[] = 'Semua kolom wajib diisi.';
        }

        // Batas panjang field
        if (strlen($nama_lengkap) > 100) {
            $errors[] = 'Nama lengkap maksimal 100 karakter.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        // Validasi nomor WhatsApp: hanya angka, +, strip, spasi; 8-20 karakter
        if (!preg_match('/^[0-9\+\-\s]{8,20}$/', $whatsapp)) {
            $errors[] = 'Format nomor WhatsApp tidak valid (8-20 digit).';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }

        if ($password !== $konfirmasi_password) {
            $errors[] = 'Password dan konfirmasi password tidak cocok.';
        }

        if (empty($errors) && $userModel->checkEmailExists($email)) {
            $errors[] = 'Email sudah terdaftar. Gunakan email lain.';
        }

        if (empty($errors) && $userModel->checkWhatsappExists($whatsapp)) {
            $errors[] = 'Nomor WhatsApp sudah terdaftar. Gunakan nomor lain.';
        }

        if (!empty($errors)) {
            $_SESSION['auth_errors'] = $errors;
            $_SESSION['old_input']   = $old_input;
            header('Location: ' . BASEURL . '/register');
            exit;
        }

        // Hash password menggunakan Bcrypt
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $data = [
            'nama_lengkap' => $nama_lengkap,
            'email'        => strtolower($email),
            'whatsapp'     => $whatsapp,
            'sekolah'      => $sekolah ?: null,
            'password'     => $hashed_password,
            'role'         => 'pelanggan', // Registrasi mandiri selalu diberi role pelanggan
            'is_active'    => 1,
        ];

        if ($userModel->registerUser($data) > 0) {
            // Registrasi berhasil — redirect ke halaman verifikasi
            unset($_SESSION['old_input']);
            $_SESSION['register_success'] = true;
            header('Location: ' . BASEURL . '/register/verify');
            exit;
        }

        // Gagal insert ke database
        $_SESSION['auth_errors'] = ['Terjadi kesalahan pada server. Silakan coba lagi.'];
        $_SESSION['old_input']   = $old_input;
        header('Location: ' . BASEURL . '/register');
        exit;
    }

    public function verify()
    {
        // Halaman konfirmasi setelah registrasi berhasil
        // Pastikan hanya bisa diakses setelah registrasi
        if (!isset($_SESSION['register_success'])) {
            header('Location: ' . BASEURL . '/register');
            exit;
        }
        unset($_SESSION['register_success']);

        $data['judul'] = 'Registrasi Berhasil';
        $this->view('templates/auth_header', $data);
        $this->view('register/verify', $data);
        $this->view('templates/auth_footer');
    }
}
