<?php

/**
 * UserContextService — Mengelola identitas, role, dan matriks izin pengguna saat ini.
 * Memastikan AI Assistant mematuhi batasan otorisasi (RBAC) di tingkat backend.
 */
class UserContextService {

    private int $userId;
    private string $role;
    private int $businessId;
    private string $userName;
    private string $userEmail;

    public function __construct(int $userId, string $role = 'pelanggan', int $businessId = 1, string $userName = '', string $userEmail = '')
    {
        $this->userId     = $userId;
        $this->role       = strtolower($role);
        $this->businessId = $businessId ?: $userId;
        $this->userName   = $userName ?: ($_SESSION['user_nama'] ?? 'Pengguna');
        $this->userEmail  = $userEmail ?: ($_SESSION['user_email'] ?? '');
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getBusinessId(): int
    {
        return $this->businessId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function isPelanggan(): bool
    {
        return $this->role === 'pelanggan';
    }

    /**
     * Hak akses laporan keuangan, omset keseluruhan toko, laba, dan data penjualan lengkap.
     * Hanya boleh diakses oleh Admin.
     */
    public function canViewFinancialReports(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Hak akses manajemen akun pengguna (tambah kasir, edit status user).
     * Hanya boleh diakses oleh Admin.
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Hak akses pengelolaan produk (tambah, edit, hapus barang fisik).
     * Hanya boleh diakses oleh Admin.
     */
    public function canManageProducts(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Hak akses operasional kasir (POS).
     * Boleh diakses oleh Admin dan Kasir.
     */
    public function canAccessPOS(): bool
    {
        return $this->isAdmin() || $this->isKasir();
    }

    /**
     * Hak akses monitoring stok kritis dan peringatan restock.
     * Boleh diakses oleh Admin dan Kasir.
     */
    public function canViewInventoryAlerts(): bool
    {
        return $this->isAdmin() || $this->isKasir();
    }

    /**
     * Hak akses riwayat transaksi.
     * Admin: melihat semua transaksi toko.
     * Kasir: hanya melihat transaksi yang diproses akun kasir tersebut.
     * Pelanggan: tidak memiliki akses riwayat transaksi internal kasir.
     */
    public function canViewTransactions(): bool
    {
        return $this->isAdmin() || $this->isKasir();
    }

    /**
     * Ringkasan panduan hak akses untuk disuntikkan ke System Prompt AI.
     */
    public function getRoleGuidanceText(): string
    {
        $name = htmlspecialchars($this->userName);
        $role = strtoupper($this->role);

        $text = "PENGGUNA AKTIF:\n"
              . "- Nama: {$name}\n"
              . "- Role: {$role}\n"
              . "- User ID: {$this->userId}\n"
              . "- Lingkup Usaha (Business ID): {$this->businessId}\n\n";

        if ($this->isPelanggan()) {
            $text .= "BATASAN AKSES PELANGGAN (WAJIB DIPATUHI):\n"
                   . "- Pengguna adalah PELANGGAN.\n"
                   . "- HANYA boleh menjawab pertanyaan tentang informasi umum sistem Acounting dan katalog/menu produk publik (harga, ketersediaan).\n"
                   . "- JANGAN PERNAH memberikan informasi omset, laba, total penjualan toko, data transaksi kasir, data pengguna lain, atau fitur manajerial.\n"
                   . "- Jika ditanya hal tersebut, tolak dengan sopan bahwa data internal bisnis hanya dapat diakses oleh Admin atau staf toko.";
        } elseif ($this->isKasir()) {
            $text .= "BATASAN AKSES KASIR (WAJIB DIPATUHI):\n"
                   . "- Pengguna adalah KASIR toko.\n"
                   . "- Boleh membantu operasional kasir POS, pengecekan ketersediaan stok fisik, peringatan stok menipis, dan transaksi kasir akun ini.\n"
                   . "- JANGAN PERNAH membocorkan laporan total omset keseluruhan toko, laba bersih usaha, database penjualan lengkap toko, atau pengelolaan akun pengguna (hak eksklusif Admin).\n"
                   . "- Jika ditanya omset toko keseluruhan, tolak dengan sopan sesuai batasan hak akses kasir.";
        } else {
            $text .= "HAK AKSES ADMINISTRATOR:\n"
                   . "- Pengguna adalah ADMINISTRATOR (pemilik toko).\n"
                   . "- Memiliki hak akses penuh ke seluruh data: omset, laba, laporan penjualan, stok, transaksi, kasir, dan analisis bisnis.";
        }

        return $text;
    }
}
