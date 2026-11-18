<?php

class Transaction_model {
    private $table = 'transactions';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function createTransaction($data)
    {
        $cashierId = $data['cashier_id'] ?? $data['user_id'];

        $query = "INSERT INTO " . $this->table . " (user_id, cashier_id, transaction_date, total_amount) 
                  VALUES (:user_id, :cashier_id, :transaction_date, :total_amount)";
        
        $this->db->query($query);
        $this->db->bind('user_id', (int)$data['user_id']);
        $this->db->bind('cashier_id', (int)$cashierId);
        $this->db->bind('transaction_date', $data['transaction_date']);
        $this->db->bind('total_amount', $data['total_amount']);

        $this->db->execute();
        return $this->db->lastInsertId();
    }

    /**
     * Mengambil riwayat transaksi.
     * Jika $cashier_id diisi (misal untuk kasir), hanya menampilkan transaksi yang diproses kasir tersebut.
     * Jika null (untuk admin), menampilkan seluruh transaksi lingkup usaha ($user_id).
     */
    public function getAllTransactions($user_id, $limit, $offset, $keyword = '', $start_date = '', $end_date = '', $cashier_id = null)
    {
        $query = "SELECT t.*, u.nama_lengkap as cashier_name 
                  FROM " . $this->table . " t
                  LEFT JOIN users u ON t.cashier_id = u.id
                  WHERE t.user_id = :user_id";
        
        if ($cashier_id !== null) {
            $query .= " AND t.cashier_id = :cashier_id";
        }
        if (!empty($keyword)) {
            $query .= " AND t.id = :keyword";
        }
        if (!empty($start_date)) {
            $query .= " AND DATE(t.transaction_date) >= :start_date";
        }
        if (!empty($end_date)) {
            $query .= " AND DATE(t.transaction_date) <= :end_date";
        }
        
        $query .= " ORDER BY t.transaction_date DESC LIMIT :limit OFFSET :offset";
        
        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        
        if ($cashier_id !== null) {
            $this->db->bind('cashier_id', (int)$cashier_id);
        }
        if (!empty($keyword)) {
            $this->db->bind('keyword', (int)$keyword);
        }
        if (!empty($start_date)) {
            $this->db->bind('start_date', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->bind('end_date', $end_date);
        }
        
        $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
        $this->db->bind('offset', (int)$offset, PDO::PARAM_INT);

        return $this->db->resultSet();
    }

    public function countTransactions($user_id, $keyword = '', $start_date = '', $end_date = '', $cashier_id = null)
    {
        $query = "SELECT COUNT(id) as total FROM " . $this->table . " WHERE user_id = :user_id";
        
        if ($cashier_id !== null) {
            $query .= " AND cashier_id = :cashier_id";
        }
        if (!empty($keyword)) {
            $query .= " AND id = :keyword";
        }
        if (!empty($start_date)) {
            $query .= " AND DATE(transaction_date) >= :start_date";
        }
        if (!empty($end_date)) {
            $query .= " AND DATE(transaction_date) <= :end_date";
        }
        
        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        
        if ($cashier_id !== null) {
            $this->db->bind('cashier_id', (int)$cashier_id);
        }
        if (!empty($keyword)) {
            $this->db->bind('keyword', (int)$keyword);
        }
        if (!empty($start_date)) {
            $this->db->bind('start_date', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->bind('end_date', $end_date);
        }

        $result = $this->db->single();
        return (int)($result['total'] ?? 0);
    }

    public function getTransactionById($id, $user_id, $cashier_id = null)
    {
        $query = "SELECT t.*, u.nama_lengkap as cashier_name 
                  FROM " . $this->table . " t
                  LEFT JOIN users u ON t.cashier_id = u.id
                  WHERE t.id = :id AND t.user_id = :user_id";
        if ($cashier_id !== null) {
            $query .= " AND t.cashier_id = :cashier_id";
        }

        $this->db->query($query);
        $this->db->bind('id', (int)$id);
        $this->db->bind('user_id', (int)$user_id);
        if ($cashier_id !== null) {
            $this->db->bind('cashier_id', (int)$cashier_id);
        }
        return $this->db->single();
    }

    public function deleteTransaction($id, $user_id, $cashier_id = null)
    {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        if ($cashier_id !== null) {
            $query .= " AND cashier_id = :cashier_id";
        }
        $this->db->query($query);
        $this->db->bind('id', (int)$id);
        $this->db->bind('user_id', (int)$user_id);
        if ($cashier_id !== null) {
            $this->db->bind('cashier_id', (int)$cashier_id);
        }

        $this->db->execute();
        return $this->db->rowCount();
    }

    // === METRIK & LAPORAN PENJUALAN (ADMIN ONLY) ===

    /**
     * Mengambil ringkasan penjualan (total omset, total trx, total item terjual)
     */
    public function getSalesMetrics($user_id, $start_date = '', $end_date = '')
    {
        // Agregasi detail per transaksi lebih dulu agar total_amount tidak
        // terduplikasi ketika satu transaksi memiliki beberapa baris item.
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(t.total_amount), 0) as total_sales,
                    COALESCE(SUM(d.item_qty), 0) as total_items_sold
                  FROM transactions t
                  LEFT JOIN (
                      SELECT transaction_id, SUM(quantity) AS item_qty
                      FROM transaction_details
                      GROUP BY transaction_id
                  ) d ON d.transaction_id = t.id
                  WHERE t.user_id = :user_id";

        if (!empty($start_date)) {
            $query .= " AND DATE(t.transaction_date) >= :start_date";
        }
        if (!empty($end_date)) {
            $query .= " AND DATE(t.transaction_date) <= :end_date";
        }

        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        if (!empty($start_date)) {
            $this->db->bind('start_date', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->bind('end_date', $end_date);
        }

        return $this->db->single();
    }

    /**
     * Laporan penjualan harian (30 hari terakhir atau sesuai bulan)
     */
    public function getDailySalesReport($user_id, $month = '', $year = '')
    {
        $query = "SELECT 
                    DATE(t.transaction_date) as date,
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(d.item_qty), 0) as total_items,
                    COALESCE(SUM(t.total_amount), 0) as total_amount
                  FROM transactions t
                  LEFT JOIN (
                      SELECT transaction_id, SUM(quantity) AS item_qty
                      FROM transaction_details
                      GROUP BY transaction_id
                  ) d ON d.transaction_id = t.id
                  WHERE t.user_id = :user_id";

        if (!empty($month) && !empty($year)) {
            $query .= " AND MONTH(t.transaction_date) = :month AND YEAR(t.transaction_date) = :year";
        } else {
            // Default 30 hari terakhir
            $query .= " AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }

        $query .= " GROUP BY DATE(t.transaction_date) ORDER BY date DESC";

        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        if (!empty($month) && !empty($year)) {
            $this->db->bind('month', (int)$month);
            $this->db->bind('year', (int)$year);
        }

        return $this->db->resultSet();
    }

    /**
     * Laporan penjualan bulanan (12 bulan terakhir atau tahun tertentu)
     */
    public function getMonthlySalesReport($user_id, $year = '')
    {
        // Agregasi detail per transaksi lebih dulu agar total_amount tidak
        // terduplikasi ketika satu transaksi memiliki beberapa baris item.
        $query = "SELECT 
                    DATE_FORMAT(t.transaction_date, '%Y-%m') as month_year,
                    YEAR(t.transaction_date) as year,
                    MONTH(t.transaction_date) as month,
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(d.item_qty), 0) as total_items,
                    COALESCE(SUM(t.total_amount), 0) as total_amount
                  FROM transactions t
                  LEFT JOIN (
                      SELECT transaction_id, SUM(quantity) AS item_qty
                      FROM transaction_details
                      GROUP BY transaction_id
                  ) d ON d.transaction_id = t.id
                  WHERE t.user_id = :user_id";

        if (!empty($year)) {
            $query .= " AND YEAR(t.transaction_date) = :year";
        }

        $query .= " GROUP BY month_year, year, month ORDER BY month_year DESC LIMIT 12";

        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        if (!empty($year)) {
            $this->db->bind('year', (int)$year);
        }

        return $this->db->resultSet();
    }

    /**
     * Mengambil ringkasan operasional kasir tertentu (untuk Dashboard Kasir)
     */
    public function getCashierOperationalSummary($user_id, $cashier_id)
    {
        $query = "SELECT 
                    COUNT(id) as today_transactions,
                    COALESCE(SUM(total_amount), 0) as today_sales
                  FROM transactions 
                  WHERE user_id = :user_id 
                    AND cashier_id = :cashier_id 
                    AND DATE(transaction_date) = CURDATE()";

        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        $this->db->bind('cashier_id', (int)$cashier_id);
        return $this->db->single();
    }
}
