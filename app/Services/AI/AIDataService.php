<?php

class AIDataService {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (Exception $e) {
            $this->pdo = null;
        }
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function getUserContext($userId) {
        if (!$this->pdo || !$userId) return null;
        $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, whatsapp, role, admin_id FROM users WHERE id = ?");
        $stmt->execute([(int)$userId]);
        return $stmt->fetch() ?: null;
    }

    public function resolveOwnerId($userId, $role) {
        if ($role === 'kasir' || $role === 'pelanggan') {
            $stmt = $this->pdo->prepare("SELECT admin_id FROM users WHERE id = ?");
            $stmt->execute([(int)$userId]);
            $row = $stmt->fetch();
            if (!empty($row['admin_id'])) {
                return (int)$row['admin_id'];
            }
            if ($role === 'pelanggan') {
                return 1; // Default ke toko Admin utama
            }
            return (int)$userId;
        }
        return (int)$userId;
    }

    public function getStockOverview($ownerId) {
        if (!$this->pdo || !$ownerId) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_products,
                COALESCE(SUM(stock), 0) as total_units,
                COALESCE(AVG(stock), 0) as avg_stock,
                SUM(CASE WHEN stock <= 10 THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
            FROM products 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([(int)$ownerId]);
        $stats = $stmt->fetch();

        $stmtHigh = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY stock DESC LIMIT 1");
        $stmtHigh->execute([(int)$ownerId]);
        $highest = $stmtHigh->fetch();

        $stmtLow = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY stock ASC LIMIT 1");
        $stmtLow->execute([(int)$ownerId]);
        $lowest = $stmtLow->fetch();

        $stmtCrit = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 AND stock <= 10 ORDER BY stock ASC");
        $stmtCrit->execute([(int)$ownerId]);
        $criticalItems = $stmtCrit->fetchAll();

        return [
            'total_products'    => (int)$stats['total_products'],
            'total_units'       => (int)$stats['total_units'],
            'avg_stock'         => round((float)$stats['avg_stock'], 1),
            'low_stock_count'   => (int)$stats['low_stock_count'],
            'out_of_stock_count'=> (int)$stats['out_of_stock_count'],
            'highest'           => $highest ?: null,
            'lowest'            => $lowest ?: null,
            'critical_items'    => $criticalItems
        ];
    }

    public function getProductCounts($ownerId) {
        if (!$this->pdo || !$ownerId) return ['total_variants' => 0, 'total_units' => 0];
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_variants,
                COALESCE(SUM(stock), 0) as total_units
            FROM products 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([(int)$ownerId]);
        $row = $stmt->fetch();
        return [
            'total_variants' => (int)($row['total_variants'] ?? 0),
            'total_units'    => (int)($row['total_units'] ?? 0)
        ];
    }

    public function getLowStockProducts($ownerId, $threshold = 10) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 AND stock <= ? ORDER BY stock ASC");
        $stmt->execute([(int)$ownerId, (int)$threshold]);
        return $stmt->fetchAll();
    }

    public function getHighestStockProducts($ownerId, $limit = 5) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY stock DESC LIMIT " . (int)$limit);
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    public function getLowestStockProducts($ownerId, $limit = 5) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY stock ASC LIMIT " . (int)$limit);
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    public function getHighestPriceProduct($ownerId) {
        if (!$this->pdo || !$ownerId) return null;
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY price DESC LIMIT 1");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetch() ?: null;
    }

    public function getLowestPriceProduct($ownerId) {
        if (!$this->pdo || !$ownerId) return null;
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY price ASC LIMIT 1");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetch() ?: null;
    }

    public function findProductInText($ownerId, $text) {
        if (!$this->pdo || !$ownerId) return null;
        $cleanText = mb_strtolower(trim($text), 'UTF-8');
        if (empty($cleanText)) return null;

        $stmt = $this->pdo->prepare("SELECT id, product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1");
        $stmt->execute([(int)$ownerId]);
        $products = $stmt->fetchAll();
        if (empty($products)) return null;

        // 1. Exact full product name in text
        foreach ($products as $p) {
            $pName = mb_strtolower($p['product_name'], 'UTF-8');
            if (mb_strpos($cleanText, $pName) !== false) {
                return $p;
            }
        }

        // 2. Multi-word or distinct keyword match
        foreach ($products as $p) {
            $pName = mb_strtolower($p['product_name'], 'UTF-8');
            $words = preg_split('/\s+/', $pName);
            foreach ($words as $w) {
                if (mb_strlen($w) >= 4 && mb_strpos($cleanText, $w) !== false) {
                    return $p;
                }
            }
        }

        // 3. Synonym matching (kopi -> match kopi susu, americano, latte)
        if (mb_strpos($cleanText, 'kopi') !== false) {
            foreach ($products as $p) {
                $pName = mb_strtolower($p['product_name'], 'UTF-8');
                if (mb_strpos($pName, 'kopi') !== false || mb_strpos($pName, 'americano') !== false) {
                    return $p;
                }
            }
        }

        return null;
    }

    public function searchProduct($ownerId, $keyword) {
        if (!$this->pdo || !$ownerId) return [];
        $clean = trim($keyword);
        if (empty($clean)) return [];

        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 AND LOWER(product_name) LIKE ? ORDER BY product_name ASC");
        $stmt->execute([(int)$ownerId, '%' . mb_strtolower($clean, 'UTF-8') . '%']);
        $results = $stmt->fetchAll();

        // Sinonim kopi jika mencari "kopi"
        if (empty($results) && mb_strtolower($clean, 'UTF-8') === 'kopi') {
            $stmt2 = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 AND (LOWER(product_name) LIKE '%kopi%' OR LOWER(product_name) LIKE '%americano%' OR LOWER(product_name) LIKE '%espresso%' OR LOWER(product_name) LIKE '%latte%')");
            $stmt2->execute([(int)$ownerId]);
            $results = $stmt2->fetchAll();
        }

        return $results;
    }

    public function getAllProducts($ownerId, $limit = 20) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price FROM products WHERE user_id = ? AND is_active = 1 ORDER BY product_name ASC LIMIT " . (int)$limit);
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    public function getBestSellingProducts($ownerId, $limit = 5) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("
            SELECT 
                p.product_name, 
                SUM(td.quantity) as total_sold, 
                SUM(td.subtotal) as total_revenue 
            FROM transaction_details td 
            JOIN products p ON p.id = td.product_id 
            JOIN transactions t ON t.id = td.transaction_id 
            WHERE t.user_id = ? 
            GROUP BY p.id, p.product_name 
            ORDER BY total_sold DESC 
            LIMIT " . (int)$limit
        );
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    public function getTodaySales($ownerId) {
        if (!$this->pdo || !$ownerId) return ['total' => 0, 'count' => 0];
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as count FROM transactions WHERE user_id = ? AND DATE(transaction_date) = CURDATE()");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetch() ?: ['total' => 0, 'count' => 0];
    }

    public function getMonthSales($ownerId) {
        if (!$this->pdo || !$ownerId) return ['total' => 0, 'count' => 0];
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as count FROM transactions WHERE user_id = ? AND YEAR(transaction_date) = YEAR(NOW()) AND MONTH(transaction_date) = MONTH(NOW())");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetch() ?: ['total' => 0, 'count' => 0];
    }

    public function getTotalSales($ownerId) {
        if (!$this->pdo || !$ownerId) return ['total' => 0, 'count' => 0, 'first_date' => null];
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as count, MIN(transaction_date) as first_date FROM transactions WHERE user_id = ?");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetch() ?: ['total' => 0, 'count' => 0, 'first_date' => null];
    }

    public function getLastTransaction($ownerId) {
        if (!$this->pdo || !$ownerId) return null;
        $stmt = $this->pdo->prepare("SELECT t.id, t.transaction_date, t.total_amount, u.nama_lengkap as cashier_name FROM transactions t LEFT JOIN users u ON u.id = t.cashier_id WHERE t.user_id = ? ORDER BY t.transaction_date DESC LIMIT 1");
        $stmt->execute([(int)$ownerId]);
        $trx = $stmt->fetch();
        if (!$trx) return null;

        $stmtDet = $this->pdo->prepare("SELECT p.product_name, td.quantity, td.price, td.subtotal FROM transaction_details td JOIN products p ON p.id = td.product_id WHERE td.transaction_id = ?");
        $stmtDet->execute([(int)$trx['id']]);
        $trx['items'] = $stmtDet->fetchAll();

        return $trx;
    }

    public function getRecentTransactions($ownerId, $limit = 10) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.transaction_date, t.total_amount, u.nama_lengkap as cashier_name 
            FROM transactions t 
            LEFT JOIN users u ON u.id = t.cashier_id 
            WHERE t.user_id = ? 
            ORDER BY t.transaction_date DESC, t.id DESC 
            LIMIT " . (int)$limit
        );
        $stmt->execute([(int)$ownerId]);
        $transactions = $stmt->fetchAll();

        foreach ($transactions as &$trx) {
            $stmtDet = $this->pdo->prepare("
                SELECT p.product_name, td.quantity, td.price, td.subtotal 
                FROM transaction_details td 
                JOIN products p ON p.id = td.product_id 
                WHERE td.transaction_id = ?
            ");
            $stmtDet->execute([(int)$trx['id']]);
            $trx['items'] = $stmtDet->fetchAll();
        }
        return $transactions;
    }

    public function getSoldProductsList($ownerId) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.product_name,
                SUM(td.quantity) as total_sold,
                SUM(td.subtotal) as total_amount,
                COUNT(DISTINCT t.id) as transaction_count,
                MAX(t.transaction_date) as last_sold_at
            FROM transaction_details td
            JOIN products p ON p.id = td.product_id
            JOIN transactions t ON t.id = td.transaction_id
            WHERE t.user_id = ?
            GROUP BY p.id, p.product_name
            ORDER BY total_sold DESC, p.product_name ASC
        ");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    public function getRecentHistory($userId, $limit = 5) {
        if (!$this->pdo || !$userId) return [];
        $stmt = $this->pdo->prepare("SELECT question, answer, created_at FROM ai_conversations WHERE user_id = ? ORDER BY id DESC LIMIT " . (int)$limit);
        $stmt->execute([(int)$userId]);
        $rows = $stmt->fetchAll();
        return array_reverse($rows);
    }

    public function getUserCounts($ownerId = null) {
        if (!$this->pdo) return ['total' => 0, 'admin' => 0, 'kasir' => 0, 'pelanggan' => 0];

        if ($ownerId) {
            $stmt = $this->pdo->prepare("SELECT role, COUNT(*) as count FROM users
                                         WHERE is_active = 1 AND (id = ? OR admin_id = ?)
                                         GROUP BY role");
            $stmt->execute([(int)$ownerId, (int)$ownerId]);
        } else {
            $stmt = $this->pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
        }
        $rows = $stmt->fetchAll();

        $counts = ['total' => 0, 'admin' => 0, 'kasir' => 0, 'pelanggan' => 0];
        foreach ($rows as $r) {
            $roleKey = strtolower(trim($r['role']));
            $counts[$roleKey] = (int)$r['count'];
            $counts['total'] += (int)$r['count'];
        }
        return $counts;
    }

    public function getCashiers($ownerId = null) {
        if (!$this->pdo) return [];
        if ($ownerId) {
            $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, role, is_active FROM users WHERE role = 'kasir' AND (admin_id = ? OR id = ?) AND is_active = 1");
            $stmt->execute([(int)$ownerId, (int)$ownerId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, role, is_active FROM users WHERE role = 'kasir' AND is_active = 1");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function getAllUsersList($limit = 15, $ownerId = null) {
        if (!$this->pdo) return [];
        $limit = max(1, min(100, (int)$limit));
        if ($ownerId) {
            $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, role, is_active FROM users
                                         WHERE is_active = 1 AND (id = ? OR admin_id = ?)
                                         ORDER BY id ASC LIMIT {$limit}");
            $stmt->execute([(int)$ownerId, (int)$ownerId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, role, is_active FROM users WHERE is_active = 1 ORDER BY id ASC LIMIT {$limit}");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    // ============================================================
    // QUERY SERVICE TAMBAHAN (Aca) — prepared statements + scoping
    // ============================================================

    /**
     * Detail satu produk berdasarkan id atau kata kunci nama.
     */
    public function getProductDetail($ownerId, $term, $activeOnly = true) {
        if (!$this->pdo || !$ownerId) return null;
        $term = trim((string)$term);
        if ($term === '') return null;

        $activeSql = $activeOnly ? ' AND is_active = 1' : '';

        if (ctype_digit($term)) {
            $stmt = $this->pdo->prepare("SELECT id, product_name, stock, price, is_active, image, created_at, updated_at
                                         FROM products WHERE id = ? AND user_id = ?{$activeSql} LIMIT 1");
            $stmt->execute([(int)$term, (int)$ownerId]);
            $row = $stmt->fetch();
            if ($row) return $row;
        }

        $stmt = $this->pdo->prepare("SELECT id, product_name, stock, price, is_active, image, created_at, updated_at
                                     FROM products
                                     WHERE user_id = ?{$activeSql} AND LOWER(product_name) LIKE ?
                                     ORDER BY (LOWER(product_name) = ?) DESC, product_name ASC
                                     LIMIT 1");
        $like = '%' . mb_strtolower($term, 'UTF-8') . '%';
        $stmt->execute([(int)$ownerId, $like, mb_strtolower($term, 'UTF-8')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getProductDetailById($ownerId, $productId) {
        if (!$this->pdo || !$ownerId) return null;
        $stmt = $this->pdo->prepare("SELECT id, product_name, stock, price, is_active, image, created_at, updated_at
                                     FROM products WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([(int)$productId, (int)$ownerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Daftar produk dengan filter status stok.
     */
    public function getProductListFiltered($ownerId, $limit = 20, $keyword = '', $status = 'all') {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(50, (int)$limit));
        $sql = "SELECT id, product_name, stock, price, is_active, updated_at
                FROM products WHERE user_id = ?";
        $params = [(int)$ownerId];

        if ($keyword !== '') {
            $sql .= " AND LOWER(product_name) LIKE ?";
            $params[] = '%' . mb_strtolower(trim($keyword), 'UTF-8') . '%';
        }

        if ($status === 'low') {
            $sql .= " AND is_active = 1 AND stock > 0 AND stock <= 10";
        } elseif ($status === 'out') {
            $sql .= " AND is_active = 1 AND stock = 0";
        } elseif ($status === 'active') {
            $sql .= " AND is_active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND is_active = 0";
        }

        $sql .= " ORDER BY product_name ASC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOutOfStockProducts($ownerId, $limit = 25) {
        return $this->getProductListFiltered($ownerId, $limit, '', 'out');
    }

    public function getProductsUnderStock($ownerId, $maxStock = 10) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("SELECT id, product_name, stock, price FROM products
                                     WHERE user_id = ? AND is_active = 1 AND stock < ?
                                     ORDER BY stock ASC, product_name ASC LIMIT 50");
        $stmt->execute([(int)$ownerId, max(1, (int)$maxStock)]);
        return $stmt->fetchAll();
    }

    /**
     * Ringkasan persediaan (angka & nilai stok berdasarkan harga jual).
     */
    public function getInventorySummary($ownerId) {
        if (!$this->pdo || !$ownerId) {
            return ['total_products' => 0, 'total_units' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'inventory_value' => 0];
        }
        $stmt = $this->pdo->prepare("SELECT
                COUNT(*) AS total_products,
                COALESCE(SUM(stock), 0) AS total_units,
                COALESCE(SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END), 0) AS low_stock,
                COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                COALESCE(SUM(stock * price), 0) AS inventory_value
            FROM products WHERE user_id = ? AND is_active = 1");
        $stmt->execute([(int)$ownerId]);
        $row = $stmt->fetch() ?: [];
        return [
            'total_products'  => (int)($row['total_products'] ?? 0),
            'total_units'     => (int)($row['total_units'] ?? 0),
            'low_stock'       => (int)($row['low_stock'] ?? 0),
            'out_of_stock'    => (int)($row['out_of_stock'] ?? 0),
            'inventory_value' => (float)($row['inventory_value'] ?? 0),
        ];
    }

    /**
     * Daftar transaksi dengan filter tanggal, kasir, dan nomor nota.
     */
    public function getTransactionsFiltered($ownerId, array $filters = [], $limit = 10) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(50, (int)$limit));

        $sql = "SELECT t.id, t.transaction_date, t.total_amount, t.cashier_id,
                       u.nama_lengkap AS cashier_name,
                       COUNT(td.id) AS item_lines,
                       COALESCE(SUM(td.quantity), 0) AS total_qty
                FROM transactions t
                LEFT JOIN users u ON u.id = t.cashier_id
                LEFT JOIN transaction_details td ON td.transaction_id = t.id
                WHERE t.user_id = ?";
        $params = [(int)$ownerId];

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND DATE(t.transaction_date) BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['cashier_id'])) {
            $sql .= " AND t.cashier_id = ?";
            $params[] = (int)$filters['cashier_id'];
        }
        if (!empty($filters['cashier_name'])) {
            $sql .= " AND LOWER(u.nama_lengkap) LIKE ?";
            $params[] = '%' . mb_strtolower(trim((string)$filters['cashier_name']), 'UTF-8') . '%';
        }
        if (!empty($filters['transaction_id'])) {
            $sql .= " AND t.id = ?";
            $params[] = (int)$filters['transaction_id'];
        }

        $sql .= " GROUP BY t.id, t.transaction_date, t.total_amount, t.cashier_id, u.nama_lengkap
                  ORDER BY t.transaction_date DESC, t.id DESC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Detail transaksi + item. Scoped ke toko dan (opsional) kasir.
     */
    public function getTransactionDetailScoped($ownerId, $transactionId, $cashierId = null, $activeOnly = false) {
        if (!$this->pdo || !$ownerId) return null;

        $sql = "SELECT t.id, t.transaction_date, t.total_amount, t.cashier_id, u.nama_lengkap AS cashier_name
                FROM transactions t LEFT JOIN users u ON u.id = t.cashier_id
                WHERE t.id = ? AND t.user_id = ?";
        $params = [(int)$transactionId, (int)$ownerId];
        if ($cashierId !== null) {
            $sql .= " AND t.cashier_id = ?";
            $params[] = (int)$cashierId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $trx = $stmt->fetch();
        if (!$trx) return null;

        $stmtItems = $this->pdo->prepare("SELECT p.product_name, td.quantity, td.price, td.subtotal
                                          FROM transaction_details td
                                          JOIN products p ON p.id = td.product_id
                                          WHERE td.transaction_id = ?
                                          ORDER BY td.id ASC");
        $stmtItems->execute([(int)$transactionId]);
        $trx['items'] = $stmtItems->fetchAll();
        return $trx;
    }

    /**
     * Ringkasan penjualan pada rentang tanggal, opsional per kasir.
     */
    public function getSalesSummary($ownerId, $startDate = null, $endDate = null, $cashierId = null) {
        if (!$this->pdo || !$ownerId) {
            return ['transactions' => 0, 'units' => 0, 'total_sales' => 0, 'avg_basket' => 0];
        }

        $conditions = "";
        $params = [(int)$ownerId];

        if ($startDate && $endDate) {
            $conditions .= " AND DATE(t.transaction_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        if ($cashierId !== null) {
            $conditions .= " AND t.cashier_id = ?";
            $params[] = (int)$cashierId;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS transactions,
                                            COALESCE(SUM(t.total_amount), 0) AS total_sales
                                     FROM transactions t
                                     WHERE t.user_id = ?" . $conditions);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $stmtUnits = $this->pdo->prepare("SELECT COALESCE(SUM(td.quantity), 0) AS units
                                         FROM transaction_details td
                                         JOIN transactions t ON t.id = td.transaction_id
                                         WHERE t.user_id = ?" . $conditions);
        $stmtUnits->execute($params);
        $unitsRow = $stmtUnits->fetch() ?: [];

        $trxCount = (int)($row['transactions'] ?? 0);
        $total = (float)($row['total_sales'] ?? 0);

        return [
            'transactions' => $trxCount,
            'units'        => (int)($unitsRow['units'] ?? 0),
            'total_sales'  => $total,
            'avg_basket'   => $trxCount > 0 ? $total / $trxCount : 0,
        ];
    }

    /**
     * Produk terlaris pada rentang tanggal.
     */
    public function getBestSellingProductsRange($ownerId, $startDate = null, $endDate = null, $limit = 5, $cashierId = null) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(30, (int)$limit));

        $sql = "SELECT p.id, p.product_name, p.stock,
                       COALESCE(SUM(td.quantity), 0) AS total_sold,
                       COALESCE(SUM(td.subtotal), 0) AS total_revenue
                FROM transaction_details td
                JOIN products p ON p.id = td.product_id
                JOIN transactions t ON t.id = td.transaction_id
                WHERE t.user_id = ?";
        $params = [(int)$ownerId];

        if ($startDate && $endDate) {
            $sql .= " AND DATE(t.transaction_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        if ($cashierId !== null) {
            $sql .= " AND t.cashier_id = ?";
            $params[] = (int)$cashierId;
        }

        $sql .= " GROUP BY p.id, p.product_name, p.stock
                  ORDER BY total_sold DESC, p.product_name ASC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Produk yang paling jarang / belum terjual (dalam periode).
     */
    public function getLeastSellingProducts($ownerId, $startDate = null, $endDate = null, $limit = 5) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(30, (int)$limit));

        $sql = "SELECT p.id, p.product_name, p.stock, p.price,
                       COALESCE(SUM(td.quantity), 0) AS total_sold
                FROM products p
                LEFT JOIN transaction_details td ON td.product_id = p.id
                LEFT JOIN transactions t ON t.id = td.transaction_id AND t.user_id = p.user_id";
        $where = ["p.user_id = ?", "p.is_active = 1"];
        $params = [(int)$ownerId];

        if ($startDate && $endDate) {
            $where[] = "(t.id IS NULL OR DATE(t.transaction_date) BETWEEN ? AND ?)";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $sql .= " WHERE " . implode(' AND ', $where) . "
                  GROUP BY p.id, p.product_name, p.stock, p.price
                  ORDER BY total_sold ASC, p.product_name ASC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ringkasan performa kasir (khusus Admin).
     */
    public function getCashierPerformance($ownerId, $startDate = null, $endDate = null, $limit = 20) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(50, (int)$limit));

        $sql = "SELECT u.id, u.nama_lengkap,
                       COUNT(DISTINCT t.id) AS total_transactions,
                       COALESCE(SUM(t.total_amount), 0) AS total_sales
                FROM transactions t
                JOIN users u ON u.id = t.cashier_id
                WHERE t.user_id = ?";
        $params = [(int)$ownerId];

        if ($startDate && $endDate) {
            $sql .= " AND DATE(t.transaction_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $sql .= " GROUP BY u.id, u.nama_lengkap ORDER BY total_transactions DESC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Daftar kasir milik toko (tanpa admin), untuk filter transaksi.
     */
    public function getCashiersForOwner($ownerId) {
        if (!$this->pdo || !$ownerId) return [];
        $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, is_active
                                     FROM users
                                     WHERE role = 'kasir' AND admin_id = ? AND is_active = 1
                                     ORDER BY nama_lengkap ASC");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    /**
     * Profil pengguna sendiri (tanpa password/token).
     */
    public function getUserProfile($userId) {
        if (!$this->pdo || !$userId) return null;
        $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, email, whatsapp, role, is_active,
                                            email_verified_at, whatsapp_verified_at, created_at
                                     FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Katalog publik untuk pelanggan (tanpa data internal).
     */
    public function getPublicCatalog($ownerId, $limit = 20) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(50, (int)$limit));
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price, image
                                     FROM products WHERE user_id = ? AND is_active = 1
                                     ORDER BY product_name ASC LIMIT {$limit}");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    // ============================================================
    // RINGKASAN APLIKASI & AKTOR TRANSAKSI (Aca)
    // ============================================================

    /**
     * Ringkasan lintas tabel untuk pertanyaan "ringkasan aktivitas aplikasi".
     * Semua angka dihitung langsung dari database dengan scoping pemilik toko.
     */
    public function getApplicationOverview($ownerId) {
        if (!$this->pdo || !$ownerId) {
            return [
                'products_total' => 0, 'products_active' => 0, 'stock_units' => 0,
                'inventory_value' => 0, 'transactions_total' => 0, 'sales_total' => 0,
                'units_sold' => 0, 'first_transaction' => null, 'last_transaction' => null,
                'users_total' => 0, 'users_active' => 0, 'admins' => 0, 'cashiers' => 0,
                'customers' => 0, 'notifications_total' => 0, 'notifications_sent' => 0,
                'notifications_failed' => 0, 'ai_conversations' => 0,
            ];
        }

        $overview = [
            'products_total' => 0, 'products_active' => 0, 'stock_units' => 0,
            'inventory_value' => 0, 'transactions_total' => 0, 'sales_total' => 0,
            'units_sold' => 0, 'first_transaction' => null, 'last_transaction' => null,
            'users_total' => 0, 'users_active' => 0, 'admins' => 0, 'cashiers' => 0,
            'customers' => 0, 'notifications_total' => 0, 'notifications_sent' => 0,
            'notifications_failed' => 0, 'ai_conversations' => 0,
        ];

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total,
                                               COALESCE(SUM(is_active = 1), 0) AS active,
                                               COALESCE(SUM(stock), 0) AS units,
                                               COALESCE(SUM(stock * price), 0) AS value
                                        FROM products WHERE user_id = ?");
            $stmt->execute([(int)$ownerId]);
            $row = $stmt->fetch() ?: [];
            $overview['products_total'] = (int)($row['total'] ?? 0);
            $overview['products_active'] = (int)($row['active'] ?? 0);
            $overview['stock_units'] = (int)($row['units'] ?? 0);
            $overview['inventory_value'] = (float)($row['value'] ?? 0);

            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total,
                                               COALESCE(SUM(total_amount), 0) AS sales,
                                               MIN(transaction_date) AS first_date,
                                               MAX(transaction_date) AS last_date
                                        FROM transactions WHERE user_id = ?");
            $stmt->execute([(int)$ownerId]);
            $row = $stmt->fetch() ?: [];
            $overview['transactions_total'] = (int)($row['total'] ?? 0);
            $overview['sales_total'] = (float)($row['sales'] ?? 0);
            $overview['first_transaction'] = $row['first_date'] ?? null;
            $overview['last_transaction'] = $row['last_date'] ?? null;

            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(td.quantity), 0) AS units
                                        FROM transaction_details td
                                        JOIN transactions t ON t.id = td.transaction_id
                                        WHERE t.user_id = ?");
            $stmt->execute([(int)$ownerId]);
            $overview['units_sold'] = (int)(($stmt->fetch() ?: [])['units'] ?? 0);

            if ($this->tableExists('users')) {
                // Halaman Manajemen Pengguna menampilkan seluruh akun aplikasi.
                // Admin adalah satu-satunya role yang boleh melihat ringkasan ini.
                $stmt = $this->pdo->query("SELECT COUNT(*) AS total,
                                                   COALESCE(SUM(is_active = 1), 0) AS active,
                                                   COALESCE(SUM(role = 'admin'), 0) AS admins,
                                                   COALESCE(SUM(role = 'kasir'), 0) AS cashiers,
                                                   COALESCE(SUM(role = 'pelanggan'), 0) AS customers
                                            FROM users");
                $row = $stmt->fetch() ?: [];
                $overview['users_total'] = (int)($row['total'] ?? 0);
                $overview['users_active'] = (int)($row['active'] ?? 0);
                $overview['admins'] = (int)($row['admins'] ?? 0);
                $overview['cashiers'] = (int)($row['cashiers'] ?? 0);
                $overview['customers'] = (int)($row['customers'] ?? 0);
            }

            if ($this->tableExists('wa_notifications')) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total,
                                                   COALESCE(SUM(wn.status = 'sent'), 0) AS sent,
                                                   COALESCE(SUM(wn.status = 'failed'), 0) AS failed
                                            FROM wa_notifications wn
                                            JOIN users u ON u.id = wn.user_id
                                            WHERE u.id = ? OR u.admin_id = ?");
                $stmt->execute([(int)$ownerId, (int)$ownerId]);
                $row = $stmt->fetch() ?: [];
                $overview['notifications_total'] = (int)($row['total'] ?? 0);
                $overview['notifications_sent'] = (int)($row['sent'] ?? 0);
                $overview['notifications_failed'] = (int)($row['failed'] ?? 0);
            }

            if ($this->tableExists('ai_conversations')) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM ai_conversations WHERE user_id = ?");
                $stmt->execute([(int)$ownerId]);
                $overview['ai_conversations'] = (int)(($stmt->fetch() ?: [])['total'] ?? 0);
            }
        } catch (Throwable $e) {
            // Kembalikan angka yang sudah terkumpul; jangan gagalkan jawaban.
        }

        return $overview;
    }

    /**
     * Pengguna/kasir yang pernah melakukan transaksi, beserta jumlah & nilainya.
     */
    public function getTransactionActors($ownerId, $startDate = null, $endDate = null, $limit = 30) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(50, (int)$limit));

        $sql = "SELECT u.id, u.nama_lengkap, u.role,
                       COUNT(DISTINCT t.id) AS total_transactions,
                       COALESCE(SUM(t.total_amount), 0) AS total_sales,
                       MIN(t.transaction_date) AS first_transaction,
                       MAX(t.transaction_date) AS last_transaction
                FROM transactions t
                LEFT JOIN users u ON u.id = t.cashier_id
                WHERE t.user_id = ?";
        $params = [(int)$ownerId];

        if ($startDate && $endDate) {
            $sql .= " AND DATE(t.transaction_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $sql .= " GROUP BY u.id, u.nama_lengkap, u.role
                  ORDER BY total_transactions DESC, total_sales DESC
                  LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ringkasan notifikasi WhatsApp + 5 notifikasi terbaru (tanpa isi pesan).
     */
    public function getNotificationSummary($ownerId, $limit = 5) {
        if (!$this->pdo || !$ownerId) {
            return ['total' => 0, 'sent' => 0, 'failed' => 0, 'simulated' => 0, 'latest' => []];
        }

        $summary = ['total' => 0, 'sent' => 0, 'failed' => 0, 'simulated' => 0, 'latest' => []];

        try {
            $stmt = $this->pdo->prepare("SELECT wn.status, COUNT(*) AS total
                                        FROM wa_notifications wn
                                        JOIN users u ON u.id = wn.user_id
                                        WHERE u.id = ? OR u.admin_id = ?
                                        GROUP BY wn.status");
            $stmt->execute([(int)$ownerId, (int)$ownerId]);
            foreach ($stmt->fetchAll() as $row) {
                $status = (string)$row['status'];
                $count = (int)$row['total'];
                if (isset($summary[$status])) {
                    $summary[$status] = $count;
                }
                $summary['total'] += $count;
            }

            $limit = max(1, min(20, (int)$limit));
            $stmt = $this->pdo->prepare("SELECT wn.id, wn.phone_number, wn.type, wn.status, wn.created_at,
                                               u.nama_lengkap
                                        FROM wa_notifications wn
                                        JOIN users u ON u.id = wn.user_id
                                        WHERE u.id = ? OR u.admin_id = ?
                                        ORDER BY wn.id DESC LIMIT {$limit}");
            $stmt->execute([(int)$ownerId, (int)$ownerId]);
            $summary['latest'] = $stmt->fetchAll();
        } catch (Throwable $e) {
            // Tabel notifikasi mungkin belum siap; kembalikan default.
        }

        return $summary;
    }

    /**
     * Pengaturan WhatsApp admin (tanpa API key/OTP).
     */
    public function getWaSettingsSummary($ownerId) {
        if (!$this->pdo || !$ownerId) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT phone_number, is_connected, notify_transaction,
                                               notify_low_stock, low_stock_threshold, verified_at, updated_at
                                        FROM wa_settings WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int)$ownerId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Stok per produk (untuk jawaban ketika kategori tidak tersedia).
     */
    public function getStockByProduct($ownerId, $limit = 50) {
        if (!$this->pdo || !$ownerId) return [];
        $limit = max(1, min(100, (int)$limit));
        $stmt = $this->pdo->prepare("SELECT product_name, stock, price, (stock * price) AS stock_value
                                     FROM products
                                     WHERE user_id = ? AND is_active = 1
                                     ORDER BY stock DESC, product_name ASC
                                     LIMIT {$limit}");
        $stmt->execute([(int)$ownerId]);
        return $stmt->fetchAll();
    }

    /**
     * Audit metadata query Aca (tanpa isi pertanyaan/jawaban).
     */
    public function logQuery($userId, $role, $provider, $fallbackReason, $questionLength, $durationMs, $success = true) {
        if (!$this->pdo || !$userId) return false;
        try {
            $stmt = $this->pdo->prepare("INSERT INTO aca_ai_query_logs
                                        (user_id, role, provider, fallback_reason, question_length, duration_ms, is_success)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
                (int)$userId,
                mb_substr((string)$role, 0, 20),
                mb_substr((string)$provider, 0, 20),
                $fallbackReason !== null ? mb_substr((string)$fallbackReason, 0, 40) : null,
                max(0, (int)$questionLength),
                max(0, (int)$durationMs),
                $success ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            // Audit tidak boleh menggagalkan jawaban pengguna.
            return false;
        }
    }

    private function tableExists(string $table): bool {
        if (!$this->pdo) return false;
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.TABLES
                                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1");
            $stmt->execute([DB_NAME, $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}
