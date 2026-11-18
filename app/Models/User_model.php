<?php

class User_model {
    private $table = 'users';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function registerUser($data)
    {
        $role     = 'pelanggan'; // Registrasi umum selalu dan hanya menghasilkan role pelanggan
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $adminId  = $data['admin_id'] ?? null;

        $query = "INSERT INTO users (nama_lengkap, email, whatsapp, sekolah, password, role, is_active, admin_id) 
                  VALUES (:nama_lengkap, :email, :whatsapp, :sekolah, :password, :role, :is_active, :admin_id)";
        
        $this->db->query($query);
        $this->db->bind('nama_lengkap', $data['nama_lengkap']);
        $this->db->bind('email', $data['email']);
        $this->db->bind('whatsapp', $data['whatsapp']);
        $this->db->bind('sekolah', $data['sekolah'] ?? null);
        $this->db->bind('password', $data['password']);
        $this->db->bind('role', $role);
        $this->db->bind('is_active', $isActive);
        $this->db->bind('admin_id', $adminId);

        $this->db->execute();
        return $this->db->rowCount();
    }

    public function register($data)
    {
        return $this->registerUser($data);
    }

    public function findUserByEmail($email)
    {
        return $this->getUserByEmail($email);
    }

    public function checkEmailExists($email, $excludeId = null)
    {
        $query = "SELECT id FROM {$this->table} WHERE email = :email";
        if ($excludeId !== null) {
            $query .= " AND id != :exclude_id";
        }
        $this->db->query($query);
        $this->db->bind('email', $email);
        if ($excludeId !== null) {
            $this->db->bind('exclude_id', (int)$excludeId);
        }
        $this->db->execute();
        return $this->db->rowCount() > 0;
    }

    public function checkWhatsappExists($whatsapp, $excludeId = null)
    {
        $query = "SELECT id FROM {$this->table} WHERE whatsapp = :whatsapp";
        if ($excludeId !== null) {
            $query .= " AND id != :exclude_id";
        }
        $this->db->query($query);
        $this->db->bind('whatsapp', $whatsapp);
        if ($excludeId !== null) {
            $this->db->bind('exclude_id', (int)$excludeId);
        }
        $this->db->execute();
        return $this->db->rowCount() > 0;
    }

    public function getUserByEmail($email)
    {
        $this->db->query('SELECT * FROM ' . $this->table . ' WHERE email=:email');
        $this->db->bind('email', $email);
        return $this->db->single();
    }

    public function verifyEmail($user_id)
    {
        $this->db->query('UPDATE ' . $this->table . ' SET email_verified_at = NOW(), verification_status = "verified" WHERE id = :id');
        $this->db->bind('id', $user_id);
        $this->db->execute();
        return $this->db->rowCount();
    }

    public function verifyWhatsApp($user_id)
    {
        $this->db->query('UPDATE ' . $this->table . ' SET whatsapp_verified_at = NOW(), verification_status = "verified" WHERE id = :id');
        $this->db->bind('id', $user_id);
        $this->db->execute();
        return $this->db->rowCount();
    }

    public function getUserById($user_id)
    {
        $this->db->query('SELECT * FROM ' . $this->table . ' WHERE id = :id');
        $this->db->bind('id', (int)$user_id);
        return $this->db->single();
    }

    // === USER MANAGEMENT (ADMIN ONLY) ===

    /**
     * Ambil semua pengguna dengan filter keyword dan role
     */
    public function getAllUsers($limit = 10, $offset = 0, $keyword = '', $role = '')
    {
        $query = "SELECT id, nama_lengkap, email, whatsapp, role, is_active, admin_id, verification_status, created_at 
                  FROM {$this->table} WHERE 1=1";

        if (!empty($keyword)) {
            $query .= " AND (nama_lengkap LIKE :keyword OR email LIKE :keyword OR whatsapp LIKE :keyword)";
        }
        if (!empty($role)) {
            $query .= " AND role = :role";
        }

        $query .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $this->db->query($query);
        if (!empty($keyword)) {
            $this->db->bind('keyword', "%{$keyword}%");
        }
        if (!empty($role)) {
            $this->db->bind('role', $role);
        }
        $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
        $this->db->bind('offset', (int)$offset, PDO::PARAM_INT);

        return $this->db->resultSet();
    }

    /**
     * Hitung total pengguna untuk paginasi
     */
    public function countUsers($keyword = '', $role = '')
    {
        $query = "SELECT COUNT(id) as total FROM {$this->table} WHERE 1=1";

        if (!empty($keyword)) {
            $query .= " AND (nama_lengkap LIKE :keyword OR email LIKE :keyword OR whatsapp LIKE :keyword)";
        }
        if (!empty($role)) {
            $query .= " AND role = :role";
        }

        $this->db->query($query);
        if (!empty($keyword)) {
            $this->db->bind('keyword', "%{$keyword}%");
        }
        if (!empty($role)) {
            $this->db->bind('role', $role);
        }

        $row = $this->db->single();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Buat akun kasir oleh Admin
     */
    public function createCashier($data, $adminId)
    {
        $query = "INSERT INTO {$this->table} 
                  (nama_lengkap, email, whatsapp, password, role, is_active, admin_id, email_verified_at, whatsapp_verified_at, verification_status) 
                  VALUES (:nama_lengkap, :email, :whatsapp, :password, 'kasir', 1, :admin_id, NOW(), NOW(), 'verified')";

        $this->db->query($query);
        $this->db->bind('nama_lengkap', $data['nama_lengkap']);
        $this->db->bind('email', $data['email']);
        $this->db->bind('whatsapp', $data['whatsapp']);
        $this->db->bind('password', $data['password']);
        $this->db->bind('admin_id', (int)$adminId);

        $this->db->execute();
        return $this->db->rowCount();
    }

    /**
     * Update data user oleh Admin
     */
    public function updateUserByAdmin($id, $data)
    {
        $query = "UPDATE {$this->table} SET 
                    nama_lengkap = :nama_lengkap, 
                    email = :email, 
                    whatsapp = :whatsapp, 
                    role = :role, 
                    is_active = :is_active";

        if (!empty($data['password'])) {
            $query .= ", password = :password";
        }

        $query .= " WHERE id = :id";

        $this->db->query($query);
        $this->db->bind('nama_lengkap', $data['nama_lengkap']);
        $this->db->bind('email', $data['email']);
        $this->db->bind('whatsapp', $data['whatsapp']);
        $this->db->bind('role', $data['role']);
        $this->db->bind('is_active', (int)$data['is_active']);
        $this->db->bind('id', (int)$id);

        if (!empty($data['password'])) {
            $this->db->bind('password', $data['password']);
        }

        $this->db->execute();
        return $this->db->rowCount();
    }

    /**
     * Toggle status aktif/nonaktif user
     */
    public function toggleUserStatus($id)
    {
        $query = "UPDATE {$this->table} SET is_active = (1 - is_active) WHERE id = :id";
        $this->db->query($query);
        $this->db->bind('id', (int)$id);
        $this->db->execute();
        return $this->db->rowCount();
    }

    /**
     * Hitung berapa banyak user dengan role admin (untuk cegah demote admin terakhir)
     */
    public function countAdmins()
    {
        $query = "SELECT COUNT(id) as total FROM {$this->table} WHERE role = 'admin' AND is_active = 1";
        $this->db->query($query);
        $row = $this->db->single();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Update profil mandiri oleh pengguna (hanya nama, whatsapp, dan password - TANPA role)
     */
    public function updateProfile($id, $data)
    {
        $query = "UPDATE {$this->table} SET nama_lengkap = :nama_lengkap, whatsapp = :whatsapp";
        if (!empty($data['password'])) {
            $query .= ", password = :password";
        }
        $query .= " WHERE id = :id";

        $this->db->query($query);
        $this->db->bind('nama_lengkap', $data['nama_lengkap']);
        $this->db->bind('whatsapp', $data['whatsapp']);
        $this->db->bind('id', (int)$id);

        if (!empty($data['password'])) {
            $this->db->bind('password', $data['password']);
        }

        $this->db->execute();
        return $this->db->rowCount();
    }
}
