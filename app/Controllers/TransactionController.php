<?php

/**
 * TransactionController — Riwayat Transaksi untuk Admin & Kasir.
 *
 * Ketentuan RBAC:
 * - Admin: Melihat seluruh transaksi dalam lingkup usahanya
 * - Kasir: Melihat transaksi yang diproses sesuai hak aksesnya (kasir ini)
 * - Pelanggan: Ditolak (403 Forbidden)
 */
class TransactionController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
        // Hanya Admin dan Kasir yang dapat mengakses Riwayat Transaksi
        RoleMiddleware::allow(['admin', 'kasir'], 'Akses Ditolak: Halaman Riwayat Transaksi hanya diperuntukkan bagi Administrator dan Kasir.');
    }

    public function index()
    {
        $data['judul']   = 'Riwayat Transaksi';
        $role            = AuthHelper::role();
        $business_id     = AuthHelper::businessId();
        
        // Jika kasir, filter hanya transaksi yang diproses kasir ini
        $cashier_id      = ($role === 'kasir') ? (int)$_SESSION['user_id'] : null;

        $limit  = 10;
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        // Sanitasi & validasi filter input
        $keyword    = strip_tags(trim($_GET['keyword'] ?? ''));
        $keyword    = is_numeric($keyword) ? (int)$keyword : '';
        $start_date = $this->sanitizeDate($_GET['start_date'] ?? '');
        $end_date   = $this->sanitizeDate($_GET['end_date'] ?? '');

        if ($start_date && $end_date && $start_date > $end_date) {
            $start_date = '';
            $end_date   = '';
        }

        $transactionModel = $this->model('Transaction_model');

        $data['transactions'] = $transactionModel->getAllTransactions(
            $business_id, $limit, $offset,
            $keyword, $start_date, $end_date, $cashier_id
        );
        $total_data           = $transactionModel->countTransactions($business_id, $keyword, $start_date, $end_date, $cashier_id);

        $data['total_pages']  = ceil($total_data / $limit);
        $data['current_page'] = $page;
        $data['keyword']      = $keyword;
        $data['start_date']   = htmlspecialchars($start_date, ENT_QUOTES);
        $data['end_date']     = htmlspecialchars($end_date, ENT_QUOTES);
        $data['role']         = $role;

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('transactions/index', $data);
        $this->view('templates/footer');
    }

    public function detail($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) {
            header('Location: ' . BASEURL . '/transaction');
            exit;
        }

        $data['judul']   = 'Detail Transaksi';
        $role            = AuthHelper::role();
        $business_id     = AuthHelper::businessId();
        $cashier_id      = ($role === 'kasir') ? (int)$_SESSION['user_id'] : null;

        $transactionModel    = $this->model('Transaction_model');
        $data['transaction'] = $transactionModel->getTransactionById($id, $business_id, $cashier_id);

        if (!$data['transaction']) {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan atau berada di luar wewenang Anda.';
            header('Location: ' . BASEURL . '/transaction');
            exit;
        }

        $detailModel    = $this->model('TransactionDetail_model');
        $data['details'] = $detailModel->getDetailsByTransactionId($id, $business_id);
        $data['role']    = $role;

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('transactions/detail', $data);
        $this->view('templates/footer');
    }

    public function delete($id = null)
    {
        $id = (int)$id;
        if ($id <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/transaction');
            exit;
        }

        // Validasi CSRF
        if (!isset($_POST['csrf_token']) || !CSRF::validate($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Token keamanan tidak valid.';
            header('Location: ' . BASEURL . '/transaction');
            exit;
        }

        $role         = AuthHelper::role();
        $business_id  = AuthHelper::businessId();
        $cashier_id   = ($role === 'kasir') ? (int)$_SESSION['user_id'] : null;

        $transactionModel = $this->model('Transaction_model');
        $detailModel      = $this->model('TransactionDetail_model');
        $productModel     = $this->model('Product_model');

        // Pastikan transaksi sesuai hak akses
        $transaction = $transactionModel->getTransactionById($id, $business_id, $cashier_id);
        if (!$transaction) {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan atau bukan milik akun Anda.';
            header('Location: ' . BASEURL . '/transaction');
            exit;
        }

        $details = $detailModel->getDetailsByTransactionId($id, $business_id);

        $db = new Database();
        try {
            $db->beginTransaction();

            // Kembalikan stok ke inventaris toko
            foreach ($details as $item) {
                if ($item['product_id']) {
                    $productModel->restoreStock($item['product_id'], $business_id, $item['quantity']);
                }
            }

            // Hapus detail
            $detailModel->deleteDetails($id);

            // Hapus transaksi
            $transactionModel->deleteTransaction($id, $business_id, $cashier_id);

            $db->commit();
            $_SESSION['flash_success'] = 'Transaksi berhasil dihapus dan stok telah dikembalikan ke inventaris.';
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Gagal menghapus transaksi: ' . $e->getMessage();
        }

        header('Location: ' . BASEURL . '/transaction');
        exit;
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
