<?php

class DashboardController extends Controller {
    
    public function __construct()
    {
        AuthHelper::checkVerified();
    }
    
    public function index()
    {
        $role = AuthHelper::role();

        // Ketentuan: Pelanggan tidak boleh mengakses dashboard administratif.
        // Setelah login atau akses ke dashboard, arahkan pelanggan ke katalog barang/menu.
        if ($role === 'pelanggan') {
            header('Location: ' . BASEURL . '/catalog');
            exit;
        }

        $user_id     = (int)$_SESSION['user_id'];
        $business_id = AuthHelper::businessId();
        
        $dashboardModel   = $this->model('Dashboard_model');
        $transactionModel = $this->model('Transaction_model');
        $productModel     = $this->model('Product_model');

        if ($role === 'admin') {
            // === DASHBOARD ADMIN ===
            $data['judul'] = 'Dashboard Admin';
            $data['role']  = 'admin';

            $data['total_products']     = $dashboardModel->getTotalProducts($business_id);
            $data['total_stock']        = $dashboardModel->getTotalStock($business_id);
            $data['total_transactions'] = $dashboardModel->getTotalTransactions($business_id);
            $data['total_sales']        = $dashboardModel->getTotalSales($business_id);
            $data['low_stock_products'] = $dashboardModel->getLowStockProducts($business_id, 6);
            $data['sales_chart']        = $dashboardModel->getSalesChartData($business_id, 7);
            $data['stock_dist']         = $dashboardModel->getStockDistribution($business_id);
            $data['today_stats']        = $dashboardModel->getTodayStats($business_id);
            $data['top_products']       = $dashboardModel->getTopProducts($business_id, 5);

            $recentTransactions = $dashboardModel->getRecentTransactions($business_id, 5);

            // Tambahkan detail jumlah item & nama kasir
            $db = new Database();
            foreach ($recentTransactions as &$trx) {
                $db->query("SELECT SUM(quantity) as total_items FROM transaction_details WHERE transaction_id = :trx_id");
                $db->bind('trx_id', $trx['id']);
                $res = $db->single();
                $trx['total_items'] = $res['total_items'] ?? 0;

                // Ambil nama kasir jika ada
                if (!empty($trx['cashier_id'])) {
                    $db->query("SELECT nama_lengkap FROM users WHERE id = :cid");
                    $db->bind('cid', $trx['cashier_id']);
                    $cRes = $db->single();
                    $trx['cashier_name'] = $cRes['nama_lengkap'] ?? 'Admin';
                } else {
                    $trx['cashier_name'] = 'Admin';
                }
            }
            $data['recent_transactions'] = $recentTransactions;

            $this->view('templates/header', $data);
            $this->view('templates/sidebar', $data);
            $this->view('dashboard/admin', $data);
            $this->view('templates/footer');

        } else {
            // === DASHBOARD KASIR ===
            // Ketentuan: Kasir dapat melihat daftar barang, tombol transaksi baru, 
            // riwayat transaksi sesuai hak akses, ringkasan operasional yang diizinkan.
            // Kasir TIDAK boleh melihat laporan seluruh penjualan.
            $data['judul'] = 'Dashboard Kasir';
            $data['role']  = 'kasir';

            $data['total_products'] = $dashboardModel->getTotalProducts($business_id);
            $data['total_stock']    = $dashboardModel->getTotalStock($business_id);

            // Ringkasan operasional kasir ini hari ini
            $kasirSummary = $transactionModel->getCashierOperationalSummary($business_id, $user_id);
            $data['today_transactions'] = $kasirSummary['today_transactions'] ?? 0;

            // Transaksi terbaru milik kasir ini
            $recentTransactions = $dashboardModel->getRecentTransactionsForCashier($business_id, $user_id, 5);

            $db = new Database();
            foreach ($recentTransactions as &$trx) {
                $db->query("SELECT SUM(quantity) as total_items FROM transaction_details WHERE transaction_id = :trx_id");
                $db->bind('trx_id', $trx['id']);
                $res = $db->single();
                $trx['total_items'] = $res['total_items'] ?? 0;
            }
            $data['recent_transactions'] = $recentTransactions;

            // Produk stok rendah di toko
            $data['low_stock_products'] = $dashboardModel->getLowStockProducts($business_id, 5);
            $data['stock_dist']         = $dashboardModel->getStockDistribution($business_id);
            $data['top_products']       = $dashboardModel->getTopProducts($business_id, 5);

            $this->view('templates/header', $data);
            $this->view('templates/sidebar', $data);
            $this->view('dashboard/kasir', $data);
            $this->view('templates/footer');
        }
    }
}
