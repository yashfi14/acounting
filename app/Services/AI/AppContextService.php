<?php

require_once __DIR__ . '/UserContextService.php';

/**
 * AppContextService — Menghubungkan AI Assistant ke data aktual dari database MySQL
 * dengan penegakan izin akses (RBAC) ketat di tingkat query.
 */
class AppContextService {

    private UserContextService $userContext;
    private ?Database $db = null;

    public function __construct(UserContextService $userContext)
    {
        $this->userContext = $userContext;
    }

    private function getDb(): Database
    {
        if ($this->db === null) {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../Core/Database.php';
            }
            $this->db = new Database();
        }
        return $this->db;
    }

    /**
     * Memeriksa apakah database dapat diakses secara normal.
     */
    public function isDatabaseAvailable(): bool
    {
        try {
            $db = $this->getDb();
            $db->query("SELECT 1");
            $db->execute();
            return true;
        } catch (Throwable $e) {
            error_log('[AppContextService] DB Connection Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengambil ringkasan operasional toko (hanya jika diizinkan).
     */
    public function getStoreSummary(): ?array
    {
        if (!$this->userContext->canViewFinancialReports()) {
            return null;
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT 
                (SELECT COUNT(id) FROM products WHERE user_id = :uid1 AND is_active = 1) as total_products,
                (SELECT COALESCE(SUM(stock), 0) FROM products WHERE user_id = :uid2 AND is_active = 1) as total_stock,
                (SELECT COUNT(id) FROM transactions WHERE user_id = :uid3) as total_transactions,
                (SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE user_id = :uid4) as total_sales");
            $db->bind('uid1', $uid);
            $db->bind('uid2', $uid);
            $db->bind('uid3', $uid);
            $db->bind('uid4', $uid);
            $res = $db->single();

            return [
                'total_products'     => (int)($res['total_products'] ?? 0),
                'total_stock'        => (int)($res['total_stock'] ?? 0),
                'total_transactions' => (int)($res['total_transactions'] ?? 0),
                'total_sales'        => (float)($res['total_sales'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('[AppContextService] getStoreSummary error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengambil data penjualan hari ini secara real-time.
     */
    public function getTodaySales(): ?array
    {
        if (!$this->userContext->canViewFinancialReports()) {
            return null;
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();
            $today = date('Y-m-d');

            $db->query("SELECT COUNT(id) as today_trx, COALESCE(SUM(total_amount), 0) as today_omset 
                        FROM transactions 
                        WHERE user_id = :uid AND DATE(transaction_date) = :today");
            $db->bind('uid', $uid);
            $db->bind('today', $today);
            $res = $db->single();

            $omset = (float)($res['today_omset'] ?? 0);
            $trx   = (int)($res['today_trx'] ?? 0);
            $avg   = $trx > 0 ? ($omset / $trx) : 0;

            return [
                'date'             => $today,
                'today_omset'      => $omset,
                'today_trx'        => $trx,
                'avg_basket'       => $avg,
                'has_transactions' => $trx > 0
            ];
        } catch (Throwable $e) {
            error_log('[AppContextService] getTodaySales error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengambil data penjualan bulan ini secara real-time.
     */
    public function getMonthlySales(): ?array
    {
        if (!$this->userContext->canViewFinancialReports()) {
            return null;
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT COUNT(id) as month_trx, COALESCE(SUM(total_amount), 0) as month_omset 
                        FROM transactions 
                        WHERE user_id = :uid 
                          AND YEAR(transaction_date) = YEAR(CURDATE()) 
                          AND MONTH(transaction_date) = MONTH(CURDATE())");
            $db->bind('uid', $uid);
            $res = $db->single();

            $omset = (float)($res['month_omset'] ?? 0);
            $trx   = (int)($res['month_trx'] ?? 0);

            return [
                'month_name'       => date('F Y'),
                'month_omset'      => $omset,
                'month_trx'        => $trx,
                'has_transactions' => $trx > 0
            ];
        } catch (Throwable $e) {
            error_log('[AppContextService] getMonthlySales error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengambil transaksi terakhir yang tercatat di toko/kasir.
     */
    public function getLastTransaction(): ?array
    {
        if (!$this->userContext->canViewTransactions()) {
            return null;
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            if ($this->userContext->isAdmin()) {
                $db->query("SELECT t.*, u.nama_lengkap as cashier_name 
                            FROM transactions t 
                            LEFT JOIN users u ON t.cashier_id = u.id 
                            WHERE t.user_id = :uid 
                            ORDER BY t.transaction_date DESC, t.id DESC 
                            LIMIT 1");
                $db->bind('uid', $uid);
            } else {
                // Kasir hanya melihat transaksi milik akun kasir tersebut
                $db->query("SELECT t.*, u.nama_lengkap as cashier_name 
                            FROM transactions t 
                            LEFT JOIN users u ON t.cashier_id = u.id 
                            WHERE t.user_id = :uid AND t.cashier_id = :cid 
                            ORDER BY t.transaction_date DESC, t.id DESC 
                            LIMIT 1");
                $db->bind('uid', $uid);
                $db->bind('cid', $this->userContext->getUserId());
            }

            $trx = $db->single();
            if (!$trx) {
                return null;
            }

            // Ambil rincian detail barang dalam transaksi ini
            $db->query("SELECT td.quantity, td.price, td.subtotal, p.product_name 
                        FROM transaction_details td 
                        JOIN products p ON td.product_id = p.id 
                        WHERE td.transaction_id = :tid");
            $db->bind('tid', $trx['id']);
            $items = $db->resultSet();

            $trx['items'] = $items;
            return $trx;
        } catch (Throwable $e) {
            error_log('[AppContextService] getLastTransaction error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengambil produk terlaris berdasarkan kuantitas terjual.
     * Boleh diakses oleh Admin dan Kasir.
     */
    public function getBestSellers(int $limit = 5): array
    {
        if ($this->userContext->isPelanggan()) {
            return [];
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT p.product_name, p.price, p.stock, 
                               COALESCE(SUM(td.quantity), 0) as total_sold, 
                               COALESCE(SUM(td.subtotal), 0) as total_revenue
                        FROM products p
                        LEFT JOIN transaction_details td ON p.id = td.product_id
                        WHERE p.user_id = :uid AND p.is_active = 1
                        GROUP BY p.id, p.product_name, p.price, p.stock
                        ORDER BY total_sold DESC, p.stock ASC
                        LIMIT :lim");
            $db->bind('uid', $uid);
            $db->bind('lim', $limit, PDO::PARAM_INT);
            return $db->resultSet();
        } catch (Throwable $e) {
            error_log('[AppContextService] getBestSellers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil daftar produk yang stoknya habis (0) atau menipis (<= threshold).
     * Boleh diakses oleh Admin dan Kasir.
     */
    public function getStockAlerts(int $threshold = 15): array
    {
        if ($this->userContext->isPelanggan()) {
            return [];
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT product_name, stock, price 
                        FROM products 
                        WHERE user_id = :uid AND is_active = 1 AND stock <= :th 
                        ORDER BY stock ASC");
            $db->bind('uid', $uid);
            $db->bind('th', $threshold, PDO::PARAM_INT);
            return $db->resultSet();
        } catch (Throwable $e) {
            error_log('[AppContextService] getStockAlerts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil produk dengan stok di bawah angka tertentu.
     * Digunakan untuk menangani pertanyaan spesifik/lanjutan (misal: "kalau yang di bawah 5?").
     */
    public function getProductsUnderStock(int $maxStock): array
    {
        if ($this->userContext->isPelanggan()) {
            return [];
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT product_name, stock, price 
                        FROM products 
                        WHERE user_id = :uid AND is_active = 1 AND stock < :max 
                        ORDER BY stock ASC");
            $db->bind('uid', $uid);
            $db->bind('max', $maxStock, PDO::PARAM_INT);
            return $db->resultSet();
        } catch (Throwable $e) {
            error_log('[AppContextService] getProductsUnderStock error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mencari produk berdasarkan kata kunci nama produk.
     */
    public function searchProduct(string $keyword): array
    {
        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT product_name, price, stock 
                        FROM products 
                        WHERE user_id = :uid AND product_name LIKE :kw AND is_active = 1 
                        ORDER BY product_name ASC 
                        LIMIT 6");
            $db->bind('uid', $uid);
            $db->bind('kw', "%{$keyword}%");
            return $db->resultSet();
        } catch (Throwable $e) {
            error_log('[AppContextService] searchProduct error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil semua produk aktif toko (katalog).
     */
    public function getAllActiveProducts(int $limit = 20): array
    {
        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT product_name, price, stock 
                        FROM products 
                        WHERE user_id = :uid AND is_active = 1 
                        ORDER BY product_name ASC 
                        LIMIT :lim");
            $db->bind('uid', $uid);
            $db->bind('lim', $limit, PDO::PARAM_INT);
            return $db->resultSet();
        } catch (Throwable $e) {
            error_log('[AppContextService] getAllActiveProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil daftar kasir toko yang terdaftar (Khusus Admin).
     */
    public function getCashiers(): array
    {
        if (!$this->userContext->canManageUsers()) {
            return [];
        }

        try {
            $db = $this->getDb();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT nama_lengkap, email, whatsapp, is_active 
                        FROM users 
                        WHERE admin_id = :uid AND role = 'kasir' 
                        ORDER BY nama_lengkap ASC");
            $db->bind('uid', $uid);
            return $db->resultSet();
        } catch (Throwable $e) {
            error_log('[AppContextService] getCashiers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Membangun snapshot data real-time toko yang aman untuk disuntikkan ke System Prompt AI (RAG).
     */
    public function getGroundingSnapshotForAI(): string
    {
        $snapshot = "KONDISI AKTUAL DATABASE TOKO (DATA REAL-TIME):\n";

        // 1. Data Produk
        $products = $this->getAllActiveProducts(15);
        if (!empty($products)) {
            $snapshot .= "Daftar Produk Aktif di Toko:\n";
            foreach ($products as $p) {
                $status = ($p['stock'] > 0) ? "Stok: {$p['stock']} unit" : "HABIS (0 unit)";
                $snapshot .= "- {$p['product_name']}: Rp " . number_format($p['price'], 0, ',', '.') . " ({$status})\n";
            }
        } else {
            $snapshot .= "Belum ada produk aktif yang terdaftar di toko.\n";
        }

        // 2. Data Penjualan (Hanya untuk Admin)
        if ($this->userContext->canViewFinancialReports()) {
            $today = $this->getTodaySales();
            if ($today) {
                $snapshot .= "\nPenjualan Hari Ini (" . date('d M Y') . "): "
                          . "Total Omset = Rp " . number_format($today['today_omset'], 0, ',', '.') . ", "
                          . "Jumlah Transaksi = {$today['today_trx']} nota.\n";
            }

            $summary = $this->getStoreSummary();
            if ($summary) {
                $snapshot .= "Akumulasi Keseluruhan Toko: "
                          . "Total Penjualan = Rp " . number_format($summary['total_sales'], 0, ',', '.') . ", "
                          . "Total Transaksi = {$summary['total_transactions']} nota, "
                          . "Total Varian Produk = {$summary['total_products']} varian, "
                          . "Total Stok Fisik = {$summary['total_stock']} unit.\n";
            }
        }

        return $snapshot;
    }
}
