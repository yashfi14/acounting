<?php

/**
 * UserController — Manajemen Akun Pengguna (Khusus Admin).
 *
 * Mengelola pengguna:
 * - Melihat daftar pengguna (Admin, Kasir, Pelanggan)
 * - Membuat akun kasir baru
 * - Mengedit data dan role pengguna
 * - Mengaktifkan dan menonaktifkan akun
 * - Mencegah demote admin terakhir dan penonaktifan akun sendiri
 */
class UserController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
        RoleMiddleware::allow('admin', 'Hanya Admin yang memiliki akses ke Manajemen Akun Pengguna.');
    }

    /**
     * Menampilkan daftar pengguna dengan filter dan pagination.
     */
    public function index()
    {
        $keyword = isset($_GET['q']) ? strip_tags(trim($_GET['q'])) : '';
        $role    = isset($_GET['role']) ? strip_tags(trim($_GET['role'])) : '';
        
        $validRoles = ['admin', 'kasir', 'pelanggan'];
        if (!in_array($role, $validRoles, true)) {
            $role = '';
        }

        $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit   = 10;
        $offset  = ($page - 1) * $limit;

        $userModel  = $this->model('User_model');
        $users      = $userModel->getAllUsers($limit, $offset, $keyword, $role);
        $totalUsers = $userModel->countUsers($keyword, $role);
        $totalPages = ceil($totalUsers / $limit);

        $data = [
            'judul'       => 'Manajemen Pengguna',
            'users'       => $users,
            'totalUsers'  => $totalUsers,
            'totalPages'  => $totalPages,
            'currentPage' => $page,
            'keyword'     => $keyword,
            'role'        => $role,
        ];

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('users/index', $data);
        $this->view('templates/footer');
    }

    /**
     * Halaman form tambah akun kasir / pengguna baru.
     */
    public function create()
    {
        $data = [
            'judul' => 'Tambah Akun Kasir / Pengguna',
        ];

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('users/create', $data);
        $this->view('templates/footer');
    }

    /**
     * Simpan akun pengguna baru ke database.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            Flasher::setFlash('Token keamanan tidak valid.', 'danger');
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        $nama_lengkap = strip_tags(trim($_POST['nama_lengkap'] ?? ''));
        $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $whatsapp     = preg_replace('/[^0-9]/', '', trim($_POST['whatsapp'] ?? ''));
        $password     = $_POST['password'] ?? '';
        $role         = trim($_POST['role'] ?? 'kasir');

        $allowedRoles = ['admin', 'kasir', 'pelanggan'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'kasir';
        }

        if (empty($nama_lengkap) || empty($email) || empty($whatsapp) || empty($password)) {
            Flasher::setFlash('Semua kolom wajib diisi.', 'danger');
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flasher::setFlash('Format email tidak valid.', 'danger');
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        if (strlen($password) < 6) {
            Flasher::setFlash('Password minimal 6 karakter.', 'danger');
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        $userModel = $this->model('User_model');

        // Cek apakah email sudah terdaftar
        if ($userModel->findUserByEmail($email)) {
            Flasher::setFlash('Email sudah digunakan oleh pengguna lain.', 'danger');
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $adminId        = (int)$_SESSION['user_id'];

        $userData = [
            'nama_lengkap' => $nama_lengkap,
            'email'        => $email,
            'whatsapp'     => $whatsapp,
            'password'     => $hashedPassword,
            'role'         => $role,
            'is_active'    => 1,
        ];

        // Jika role kasir, bind ke admin pembuat
        if ($role === 'kasir') {
            $created = $userModel->createCashier($userData, $adminId);
        } else {
            // General registration logic with specified role
            $db = new Database;
            $db->query("INSERT INTO users (nama_lengkap, email, whatsapp, password, role, is_active, admin_id, email_verified_at, whatsapp_verified_at, verification_status) 
                        VALUES (:nama_lengkap, :email, :whatsapp, :password, :role, 1, :admin_id, NOW(), NOW(), 'verified')");
            $db->bind('nama_lengkap', $nama_lengkap);
            $db->bind('email', $email);
            $db->bind('whatsapp', $whatsapp);
            $db->bind('password', $hashedPassword);
            $db->bind('role', $role);
            $db->bind('admin_id', $adminId);
            $db->execute();
            $created = $db->rowCount();
        }

        if ($created > 0) {
            Flasher::setFlash("Akun {$nama_lengkap} ({$role}) berhasil dibuat.", 'success');
            header('Location: ' . BASE_URL . '/users');
        } else {
            Flasher::setFlash('Gagal menambahkan pengguna.', 'danger');
            header('Location: ' . BASE_URL . '/users/create');
        }
        exit;
    }

    /**
     * Halaman form edit pengguna.
     */
    public function edit($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) {
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $userModel = $this->model('User_model');
        $user      = $userModel->getUserById($id);

        if (!$user) {
            Flasher::setFlash('Pengguna tidak ditemukan.', 'danger');
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $data = [
            'judul' => 'Edit Pengguna: ' . $user['nama_lengkap'],
            'user'  => $user,
        ];

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('users/edit', $data);
        $this->view('templates/footer');
    }

    /**
     * Update data dan role pengguna.
     */
    public function update($id = null)
    {
        $id = (int)$id;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            Flasher::setFlash('Token keamanan tidak valid.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        $userModel = $this->model('User_model');
        $existingUser = $userModel->getUserById($id);
        if (!$existingUser) {
            Flasher::setFlash('Pengguna tidak ditemukan.', 'danger');
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $nama_lengkap = strip_tags(trim($_POST['nama_lengkap'] ?? ''));
        $email        = trim($_POST['email'] ?? '');
        $whatsapp     = preg_replace('/[^0-9]/', '', trim($_POST['whatsapp'] ?? ''));
        $role         = trim($_POST['role'] ?? $existingUser['role']);
        $is_active    = isset($_POST['is_active']) ? 1 : 0;
        $password     = $_POST['password'] ?? '';

        $allowedRoles = ['admin', 'kasir', 'pelanggan'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = $existingUser['role'];
        }

        // Validasi field wajib. Email tidak boleh kosong atau berisi data
        // yang tidak valid; kolom email di database adalah VARCHAR(100).
        if ($nama_lengkap === '' || $email === '' || $whatsapp === '') {
            Flasher::setFlash('Nama lengkap, email, dan nomor WhatsApp wajib diisi.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        if (mb_strlen($nama_lengkap, 'UTF-8') > 100) {
            Flasher::setFlash('Nama lengkap maksimal 100 karakter.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flasher::setFlash('Format email tidak valid.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        if (mb_strlen($email, 'UTF-8') > 100) {
            Flasher::setFlash('Alamat email maksimal 100 karakter.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        if (strlen($whatsapp) < 9 || strlen($whatsapp) > 15) {
            Flasher::setFlash('Nomor WhatsApp tidak valid (9-15 digit angka).', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        // Cegah penonaktifan akun sendiri
        if ($id === (int)$_SESSION['user_id'] && $is_active === 0) {
            Flasher::setFlash('Anda tidak dapat menonaktifkan akun Anda sendiri.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        // Cegah demoting jika ini satu-satunya admin
        if ($existingUser['role'] === 'admin' && $role !== 'admin') {
            if ($userModel->countAdmins() <= 1) {
                Flasher::setFlash('Tidak dapat mengubah role admin ini karena merupakan satu-satunya admin aktif dalam sistem.', 'danger');
                header('Location: ' . BASE_URL . '/users/edit/' . $id);
                exit;
            }
        }

        // Validasi UNIQUE dengan mengecualikan ID pengguna yang sedang diedit.
        // Email milik pengguna ini sendiri tetap boleh disimpan ulang.
        if ($userModel->checkEmailExists($email, $id)) {
            Flasher::setFlash('Email sudah digunakan oleh pengguna lain.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        if ($userModel->checkWhatsappExists($whatsapp, $id)) {
            Flasher::setFlash('Nomor WhatsApp sudah digunakan oleh pengguna lain.', 'danger');
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        $updateData = [
            'nama_lengkap' => $nama_lengkap,
            'email'        => $email,
            'whatsapp'     => $whatsapp,
            'role'         => $role,
            'is_active'    => $is_active,
        ];

        if (!empty($password)) {
            if (strlen($password) < 6) {
                Flasher::setFlash('Password baru minimal 6 karakter.', 'danger');
                header('Location: ' . BASE_URL . '/users/edit/' . $id);
                exit;
            }
            $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            $userModel->updateUserByAdmin($id, $updateData);
        } catch (PDOException $e) {
            // Jangan tampilkan detail SQL ke pengguna. Duplicate constraint
            // tetap diberi pesan yang jelas; error lain dicatat untuk admin.
            error_log('[UserController] Gagal update user ID ' . $id . ': SQLSTATE ' . $e->getCode());
            if ($e->getCode() === '23000') {
                Flasher::setFlash('Email atau nomor WhatsApp sudah digunakan oleh pengguna lain.', 'danger');
            } else {
                Flasher::setFlash('Data pengguna gagal diperbarui karena kesalahan database. Silakan coba lagi.', 'danger');
            }
            header('Location: ' . BASE_URL . '/users/edit/' . $id);
            exit;
        }

        // Jika mengupdate diri sendiri, sinkronkan identitas di session agar
        // email baru langsung konsisten pada request berikutnya.
        if ($id === (int)$_SESSION['user_id']) {
            $_SESSION['user_name']     = $nama_lengkap;
            $_SESSION['user_role']     = $role;
            $_SESSION['user_email']    = $email;
            $_SESSION['user_whatsapp'] = $whatsapp;
        }

        Flasher::setFlash('Data pengguna berhasil diperbarui.', 'success');
        header('Location: ' . BASE_URL . '/users');
        exit;
    }

    /**
     * Toggle status aktif/nonaktif akun pengguna (via POST).
     */
    public function toggleStatus($id = null)
    {
        $id = (int)$id;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            Flasher::setFlash('Token keamanan tidak valid.', 'danger');
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        // Cegah penonaktifan diri sendiri
        if ($id === (int)$_SESSION['user_id']) {
            Flasher::setFlash('Anda tidak dapat menonaktifkan akun Anda sendiri.', 'danger');
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $userModel = $this->model('User_model');
        $user = $userModel->getUserById($id);
        if (!$user) {
            Flasher::setFlash('Pengguna tidak ditemukan.', 'danger');
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        // Cegah penonaktifan jika admin satu-satunya
        if ($user['role'] === 'admin' && (int)$user['is_active'] === 1 && $userModel->countAdmins() <= 1) {
            Flasher::setFlash('Tidak dapat menonaktifkan admin terakhir dalam sistem.', 'danger');
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $userModel->toggleUserStatus($id);
        $newStatus = (int)$user['is_active'] === 1 ? 'dinonaktifkan' : 'diaktifkan';
        Flasher::setFlash("Akun {$user['nama_lengkap']} berhasil {$newStatus}.", 'success');

        header('Location: ' . BASE_URL . '/users');
        exit;
    }
}
