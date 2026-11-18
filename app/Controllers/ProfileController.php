<?php

/**
 * ProfileController — Profil Pengguna Mandiri.
 *
 * Mengizinkan semua role yang login (Admin, Kasir, Pelanggan) mengelola profil mereka sendiri.
 * Perlindungan KRITIS:
 * - Tidak dapat mengubah role sendiri.
 * - Tidak dapat mengakses atau mengubah profil pengguna lain (hanya $_SESSION['user_id']).
 * - Validasi CSRF dan sanitasi input.
 * - Verifikasi password lama sebelum ganti password.
 */
class ProfileController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
    }

    public function index()
    {
        $userId    = (int)$_SESSION['user_id'];
        $userModel = $this->model('User_model');
        $user      = $userModel->getUserById($userId);

        if (!$user) {
            header('Location: ' . BASE_URL . '/auth/logout');
            exit;
        }

        $data = [
            'judul' => 'Profil Saya',
            'user'  => $user,
        ];

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('profile/index', $data);
        $this->view('templates/footer');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/profile');
            exit;
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            Flasher::setFlash('Token keamanan tidak valid. Silakan coba lagi.', 'danger');
            header('Location: ' . BASE_URL . '/profile');
            exit;
        }

        $action = $_POST['action'] ?? 'update_info';

        if ($action === 'update_password') {
            $this->handlePasswordUpdate();
        } else {
            $this->handleInfoUpdate();
        }
    }

    /**
     * Handle update nama lengkap & WhatsApp
     */
    private function handleInfoUpdate()
    {
        $userId       = (int)$_SESSION['user_id'];
        $nama_lengkap = strip_tags(trim($_POST['nama_lengkap'] ?? ''));
        $whatsapp     = preg_replace('/[^0-9]/', '', trim($_POST['whatsapp'] ?? ''));

        // Validasi
        if (empty($nama_lengkap) || strlen($nama_lengkap) < 3) {
            Flasher::setFlash('Nama lengkap minimal 3 karakter.', 'danger');
            header('Location: ' . BASE_URL . '/profile');
            exit;
        }

        if (empty($whatsapp) || strlen($whatsapp) < 9 || strlen($whatsapp) > 15) {
            Flasher::setFlash('Nomor WhatsApp tidak valid (9–15 digit angka).', 'danger');
            header('Location: ' . BASE_URL . '/profile');
            exit;
        }

        $userModel = $this->model('User_model');

        // Cek duplikat WhatsApp (hanya jika beda dari milik sendiri)
        if ($userModel->checkWhatsappExists($whatsapp, $userId)) {
            Flasher::setFlash('Nomor WhatsApp sudah digunakan oleh akun lain.', 'danger');
            header('Location: ' . BASE_URL . '/profile');
            exit;
        }

        $updateData = [
            'nama_lengkap' => $nama_lengkap,
            'whatsapp'     => $whatsapp,
        ];

        $userModel->updateProfile($userId, $updateData);

        // Update nama di session
        $_SESSION['user_name'] = $nama_lengkap;

        Flasher::setFlash('Informasi akun berhasil diperbarui.', 'success');
        header('Location: ' . BASE_URL . '/profile');
        exit;
    }

    /**
     * Handle ganti password (perlu verifikasi password lama)
     */
    private function handlePasswordUpdate()
    {
        $userId        = (int)$_SESSION['user_id'];
        $passwordLama  = $_POST['password_lama']    ?? '';
        $passwordBaru  = $_POST['password']          ?? '';
        $passwordKonfirm = $_POST['password_confirm'] ?? '';

        // Validasi field wajib
        if (empty($passwordLama) || empty($passwordBaru) || empty($passwordKonfirm)) {
            Flasher::setFlash('Semua kolom password wajib diisi.', 'danger');
            header('Location: ' . BASE_URL . '/profile#pass-panel');
            exit;
        }

        // Validasi panjang password baru
        if (strlen($passwordBaru) < 6) {
            Flasher::setFlash('Password baru minimal 6 karakter.', 'danger');
            header('Location: ' . BASE_URL . '/profile#pass-panel');
            exit;
        }

        // Cek cocok
        if ($passwordBaru !== $passwordKonfirm) {
            Flasher::setFlash('Konfirmasi password tidak cocok.', 'danger');
            header('Location: ' . BASE_URL . '/profile#pass-panel');
            exit;
        }

        // Ambil data user untuk verifikasi password lama
        $userModel = $this->model('User_model');
        $user      = $userModel->getUserById($userId);

        if (!$user || !password_verify($passwordLama, $user['password'])) {
            Flasher::setFlash('Password saat ini salah. Silakan periksa kembali.', 'danger');
            header('Location: ' . BASE_URL . '/profile#pass-panel');
            exit;
        }

        // Cegah password baru sama dengan yang lama
        if (password_verify($passwordBaru, $user['password'])) {
            Flasher::setFlash('Password baru tidak boleh sama dengan password saat ini.', 'warning');
            header('Location: ' . BASE_URL . '/profile#pass-panel');
            exit;
        }

        $updateData = [
            'nama_lengkap' => $user['nama_lengkap'],
            'whatsapp'     => $user['whatsapp'],
            'password'     => password_hash($passwordBaru, PASSWORD_DEFAULT),
        ];

        $userModel->updateProfile($userId, $updateData);

        Flasher::setFlash('Password berhasil diperbarui. Silakan login ulang dengan password baru.', 'success');
        header('Location: ' . BASE_URL . '/profile#pass-panel');
        exit;
    }
}
