<?php

/**
 * SalesController — Database Penjualan Khusus Admin.
 *
 * Sesuai ketentuan:
 * - Hanya Admin yang dapat mengakses (Kasir & Pelanggan: 403 Forbidden)
 * - Melihat semua transaksi dalam lingkup usahanya
 * - Mencari transaksi & filter berdasarkan rentang tanggal
 * - Melihat detail transaksi & kasir yang bertugas
 * - Menghitung jumlah barang terjual dan total penjualan (omset)
 * - Melihat laporan penjualan harian dan bulanan
 */
class SalesController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
        // Proteksi ketat: HANYA role Admin yang diizinkan!
        RoleMiddleware::allow(['admin'], 'Akses Ditolak: Halaman Database Penjualan hanya dapat diakses oleh Administrator.');
    }

    public function index()
    {
        $data['judul']   = 'Database Penjualan';
        $business_id     = AuthHelper::businessId();

        $limit   = 10;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $limit;

        // Filter & sanitasi
        $keyword    = strip_tags(trim($_GET['keyword'] ?? ''));
        $keyword    = is_numeric($keyword) ? (int)$keyword : '';
        $start_date = $this->sanitizeDate($_GET['start_date'] ?? '');
        $end_date   = $this->sanitizeDate($_GET['end_date'] ?? '');

        if ($start_date && $end_date && $start_date > $end_date) {
            $start_date = '';
            $end_date   = '';
        }

        $transactionModel = $this->model('Transaction_model');

        // 1. Metrik Penjualan Keseluruhan / Terfilter
        $data['metrics'] = $transactionModel->getSalesMetrics($business_id, $start_date, $end_date);

        // 2. Daftar Transaksi
        $data['transactions'] = $transactionModel->getAllTransactions(
            $business_id, $limit, $offset, $keyword, $start_date, $end_date
        );
        $total_data           = $transactionModel->countTransactions($business_id, $keyword, $start_date, $end_date);
        $data['total_pages']  = ceil($total_data / $limit);
        $data['current_page'] = $page;
        $data['keyword']      = $keyword;
        $data['start_date']   = htmlspecialchars($start_date, ENT_QUOTES);
        $data['end_date']     = htmlspecialchars($end_date, ENT_QUOTES);

        // 3. Laporan Harian (30 hari terakhir)
        $data['daily_sales']   = $transactionModel->getDailySalesReport($business_id);

        // 4. Laporan Bulanan (12 bulan terakhir)
        $data['monthly_sales'] = $transactionModel->getMonthlySalesReport($business_id);

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('sales/index', $data);
        $this->view('templates/footer');
    }

    public function detail($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) {
            header('Location: ' . BASEURL . '/sales');
            exit;
        }

        $data['judul']   = 'Detail Database Penjualan';
        $business_id     = AuthHelper::businessId();

        $transactionModel    = $this->model('Transaction_model');
        $data['transaction'] = $transactionModel->getTransactionById($id, $business_id);

        if (!$data['transaction']) {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan.';
            header('Location: ' . BASEURL . '/sales');
            exit;
        }

        $detailModel    = $this->model('TransactionDetail_model');
        $data['details'] = $detailModel->getDetailsByTransactionId($id, $business_id);

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('sales/detail', $data);
        $this->view('templates/footer');
    }

    private function sanitizeDate(string $date): string
    {
        $date = trim($date);
        if (empty($date)) return '';
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if ($d && $d->format('Y-m-d') === $date) {
            return $date;
        }
        return '';
    }
}
