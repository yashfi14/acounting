<?php

class Dashboard_model {
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function getTotalProducts($user_id)
    {
        $this->db->query("SELECT COUNT(id) as total FROM products WHERE user_id = :user_id");
        $this->db->bind('user_id', $user_id);
        $result = $this->db->single();
        return $result['total'] ?? 0;
    }

    public function getTotalStock($user_id)
    {
        $this->db->query("SELECT SUM(stock) as total_stock FROM products WHERE user_id = :user_id");
        $this->db->bind('user_id', $user_id);
        $result = $this->db->single();
        return $result['total_stock'] ?? 0;
    }

    public function getTotalTransactions($user_id)
    {
        $this->db->query("SELECT COUNT(id) as total FROM transactions WHERE user_id = :user_id");
        $this->db->bind('user_id', $user_id);
        $result = $this->db->single();
        return $result['total'] ?? 0;
    }

    public function getTotalSales($user_id)
    {
        $this->db->query("SELECT SUM(total_amount) as total_sales FROM transactions WHERE user_id = :user_id");
        $this->db->bind('user_id', $user_id);
        $result = $this->db->single();
        return $result['total_sales'] ?? 0;
    }

    public function getLowStockProducts($user_id, $limit = 5)
    {
        $this->db->query("SELECT * FROM products WHERE user_id = :user_id AND stock <= 10 ORDER BY stock ASC LIMIT :limit");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('limit', $limit);
        return $this->db->resultSet();
    }

    public function getRecentTransactions($user_id, $limit = 5)
    {
        $this->db->query("SELECT * FROM transactions WHERE user_id = :user_id ORDER BY transaction_date DESC LIMIT :limit");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('limit', $limit);
        return $this->db->resultSet();
    }

    public function getRecentTransactionsForCashier($user_id, $cashier_id, $limit = 5)
    {
        $this->db->query("SELECT * FROM transactions WHERE user_id = :user_id AND cashier_id = :cashier_id ORDER BY transaction_date DESC LIMIT :limit");
        $this->db->bind('user_id', (int)$user_id);
        $this->db->bind('cashier_id', (int)$cashier_id);
        $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
        return $this->db->resultSet();
    }

    public function getSalesChartData($user_id, $days = 7)
    {
        $labels = [];
        $salesData = [];
        $trxData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d M', strtotime($date));

            $this->db->query("SELECT COUNT(id) as total_trx, COALESCE(SUM(total_amount), 0) as total_omset 
                              FROM transactions 
                              WHERE user_id = :user_id AND DATE(transaction_date) = :trx_date");
            $this->db->bind('user_id', $user_id);
            $this->db->bind('trx_date', $date);
            $res = $this->db->single();

            $salesData[] = (float)($res['total_omset'] ?? 0);
            $trxData[] = (int)($res['total_trx'] ?? 0);
        }

        return [
            'labels'       => $labels,
            'sales'        => $salesData,
            'transactions' => $trxData
        ];
    }

    public function getStockDistribution($user_id)
    {
        $this->db->query("SELECT 
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as habis,
            SUM(CASE WHEN stock > 0 AND stock <= 15 THEN 1 ELSE 0 END) as menipis,
            SUM(CASE WHEN stock > 15 THEN 1 ELSE 0 END) as aman,
            COUNT(id) as total
        FROM products WHERE user_id = :user_id AND is_active = 1");
        $this->db->bind('user_id', $user_id);
        $res = $this->db->single();

        return [
            'aman'    => (int)($res['aman'] ?? 0),
            'menipis' => (int)($res['menipis'] ?? 0),
            'habis'   => (int)($res['habis'] ?? 0),
            'total'   => (int)($res['total'] ?? 0)
        ];
    }

    public function getTodayStats($user_id)
    {
        $today = date('Y-m-d');
        $this->db->query("SELECT 
            COUNT(id) as today_trx, 
            COALESCE(SUM(total_amount), 0) as today_omset 
        FROM transactions 
        WHERE user_id = :user_id AND DATE(transaction_date) = :today");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('today', $today);
        $res = $this->db->single();

        return [
            'today_trx'   => (int)($res['today_trx'] ?? 0),
            'today_omset' => (float)($res['today_omset'] ?? 0)
        ];
    }

    public function getTopProducts($user_id, $limit = 5)
    {
        $this->db->query("SELECT p.id, p.product_name, p.price, p.stock, COALESCE(SUM(td.quantity), 0) as total_sold
        FROM products p
        LEFT JOIN transaction_details td ON p.id = td.product_id
        WHERE p.user_id = :user_id AND p.is_active = 1
        GROUP BY p.id, p.product_name, p.price, p.stock
        ORDER BY total_sold DESC, p.stock ASC
        LIMIT :limit");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('limit', $limit);
        return $this->db->resultSet();
    }
}
