<?php

/**
 * CashierController — Sistem Kasir Point-of-Sale.
 *
 * Perbaikan keamanan KRITIS:
 * - ✅ RBAC: RoleMiddleware::allow(['admin', 'kasir'])
 * - ✅ HARGA DIAMBIL DARI DATABASE, bukan dari client (mencegah price manipulation)
 * - ✅ Total dihitung ulang di server-side
 * - ✅ Validasi product_id integer dan kepemilikan usaha (businessId)
 * - ✅ Validasi qty: integer positif
 * - ✅ checkVerified() — hanya user terverifikasi
 * - ✅ Pencatatan cashier_id pemroses transaksi
 */
class CashierController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
        RoleMiddleware::allow(['admin', 'kasir']);
    }

    public function index()
    {
        $data['judul'] = 'Transaksi Kasir';

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('cashier/index', $data);
        $this->view('templates/footer');
    }

    /**
     * Endpoint pencarian barang (AJAX GET).
     * Hanya mengembalikan barang aktif milik usaha yang sedang login dengan stok > 0.
     */
    public function search()
    {
        RoleMiddleware::allow(['admin', 'kasir']);

        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['q'])) {
            http_response_code(400);
            echo json_encode([]);
            exit;
        }

        $keyword      = strip_tags(trim($_GET['q']));
        $business_id  = AuthHelper::businessId();

        $productModel = $this->model('Product_model');
        // Hanya ambil produk aktif dengan stok > 0
        $products     = $productModel->getAllProducts($business_id, 20, 0, $keyword, true);

        $result = [];
        foreach ($products as $p) {
            if ((int)$p['stock'] > 0) {
                $result[] = [
                    'id'    => (int)$p['id'],
                    'name'  => htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8'),
                    'price' => (float)$p['price'],
                    'stock' => (int)$p['stock'],
                ];
            }
        }

        echo json_encode($result);
        exit;
    }

    /**
     * Endpoint checkout transaksi (AJAX POST).
     *
     * Alur server-side:
     * 1. Validasi CSRF
     * 2. Validasi format cart
     * 3. AMBIL HARGA DARI DATABASE (bukan dari client)
     * 4. Hitung ulang total di server
     * 5. Simpan transaksi dalam DB transaction (user_id = business_id, cashier_id = current user)
     * 6. Kurangi stok (dengan WHERE stock >= qty)
     */
    public function checkout()
    {
        RoleMiddleware::allow(['admin', 'kasir']);

        // Bersihkan buffer agar tidak ada output HTML (warning/notice) yang merusak JSON
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        if (empty($inputJSON)) {
            echo json_encode(['success' => false, 'message' => 'Request body kosong.']);
            exit;
        }

        $input = json_decode($inputJSON, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$input) {
            echo json_encode(['success' => false, 'message' => 'Data JSON tidak valid: ' . json_last_error_msg()]);
            exit;
        }

        // Validasi CSRF
        if (!isset($input['csrf_token']) || !CSRF::validate($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid. Silakan muat ulang halaman.']);
            exit;
        }

        if (empty($input['cart']) || !is_array($input['cart'])) {
            echo json_encode(['success' => false, 'message' => 'Keranjang kosong atau tidak valid.']);
            exit;
        }

        $business_id  = AuthHelper::businessId();
        $cashier_id   = (int)$_SESSION['user_id'];
        $productModel = $this->model('Product_model');
        $cart         = $input['cart'];

        // === Validasi format dan AMBIL HARGA DARI DATABASE ===
        $validated_items = [];
        foreach ($cart as $item) {
            $product_id = isset($item['id']) ? (int)$item['id'] : 0;
            $qty        = isset($item['qty']) ? (int)$item['qty'] : 0;

            if ($product_id <= 0 || $qty <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data item keranjang tidak valid.']);
                exit;
            }

            // Ambil data produk dari DB — validasi kepemilikan usaha (WHERE user_id = business_id)
            $product = $productModel->getProductById($product_id, $business_id);
            if (!$product) {
                echo json_encode(['success' => false, 'message' => "Barang tidak ditemukan atau bukan milik usaha Anda."]);
                exit;
            }

            if (isset($product['is_active']) && (int)$product['is_active'] === 0) {
                echo json_encode(['success' => false, 'message' => "Barang '{$product['product_name']}' sedang dinonaktifkan."]);
                exit;
            }

            if ((int)$product['stock'] < $qty) {
                $name = htmlspecialchars($product['product_name'], ENT_QUOTES);
                echo json_encode(['success' => false, 'message' => "Stok tidak cukup untuk barang: {$name} (stok: {$product['stock']}, diminta: {$qty})."]);
                exit;
            }

            $validated_items[] = [
                'id'       => $product_id,
                'name'     => $product['product_name'],
                'price'    => (float)$product['price'],
                'qty'      => $qty,
                'subtotal' => (float)$product['price'] * $qty,
            ];
        }

        // Hitung total di server
        $total_amount = array_sum(array_column($validated_items, 'subtotal'));

        // === Proses transaksi menggunakan satu koneksi bersama ===
        // Setiap model membuat koneksi Database-nya sendiri, sehingga beginTransaction() di koneksi
        // berbeda tidak mencakup operasi model. Solusi: gunakan satu koneksi $sharedDb untuk semua
        // operasi INSERT/UPDATE di dalam checkout agar benar-benar atomic.
        $sharedDb = new Database();

        try {
            $sharedDb->beginTransaction();

            // 1. Simpan header transaksi
            $sharedDb->query(
                'INSERT INTO transactions (user_id, cashier_id, transaction_date, total_amount) VALUES (:uid, :cid, :tdate, :total)'
            );
            $sharedDb->bind('uid',   (int)$business_id);
            $sharedDb->bind('cid',   (int)$cashier_id);
            $sharedDb->bind('tdate', date('Y-m-d H:i:s'));
            $sharedDb->bind('total', $total_amount);
            $sharedDb->execute();
            $transaction_id = $sharedDb->lastInsertId();

            if (!$transaction_id) {
                throw new Exception('Gagal membuat record transaksi. Silakan coba lagi.');
            }

            // 2. Simpan detail item transaksi
            foreach ($validated_items as $item) {
                $sharedDb->query(
                    'INSERT INTO transaction_details (transaction_id, product_id, quantity, price, subtotal) VALUES (:tid, :pid, :qty, :price, :sub)'
                );
                $sharedDb->bind('tid',   (int)$transaction_id);
                $sharedDb->bind('pid',   (int)$item['id']);
                $sharedDb->bind('qty',   (int)$item['qty']);
                $sharedDb->bind('price', (float)$item['price']);
                $sharedDb->bind('sub',   (float)$item['subtotal']);
                $sharedDb->execute();
            }

            // 3. Kurangi stok (WHERE stock >= qty untuk atomicity)
            foreach ($validated_items as $item) {
                $sharedDb->query(
                    'UPDATE products SET stock = stock - :qty1 WHERE id = :id AND user_id = :uid AND stock >= :qty2'
                );
                $sharedDb->bind('qty1', (int)$item['qty']);
                $sharedDb->bind('qty2', (int)$item['qty']);
                $sharedDb->bind('id',   (int)$item['id']);
                $sharedDb->bind('uid',  (int)$business_id);
                $sharedDb->execute();

                if ($sharedDb->rowCount() == 0) {
                    throw new Exception('Stok tidak mencukupi untuk: ' . htmlspecialchars($item['name'], ENT_QUOTES));
                }
            }

            $sharedDb->commit();

            // Notifikasi Otomatis WhatsApp jika toko mengaktifkan fitur ini
            try {
                require_once __DIR__ . '/../Services/WhatsApp/WhatsAppService.php';
                $waSettingModel = $this->model('WaSetting_model');
                $waSetting = $waSettingModel->getByUserId($business_id);
                if ($waSetting && (int)$waSetting['is_connected'] === 1) {
                    $waService = new WhatsAppService($waSettingModel->getDecryptedApiKey($business_id));
                    $waNotifModel = $this->model('WaNotification_model');

                    // 1. Notifikasi Transaksi Kasir Baru
                    if ((int)$waSetting['notify_transaction'] === 1) {
                        $currentUser = Auth::user();
                        $trxData = [
                            'id' => $transaction_id,
                            'cashier_name' => $currentUser ? ($currentUser['nama_lengkap'] ?? 'Kasir') : 'Kasir',
                            'total' => $total_amount,
                            'items' => $validated_items,
                        ];
                        $sentTx = $waService->sendTransactionNotification($waSetting['phone_number'], $trxData);
                        $statusTx = $sentTx ? ($waService->isMock() ? 'simulated' : 'sent') : 'failed';
                        $waNotifModel->logNotification(
                            $business_id,
                            $waSetting['phone_number'],
                            'transaction',
                            "Notifikasi Penjualan #TRX-{$transaction_id} (Total: Rp " . number_format($total_amount, 0, ',', '.') . ")",
                            $statusTx,
                            $sentTx ? 'Terkirim otomatis saat kasir checkout' : $waService->getLastError()
                        );
                    }

                    // 2. Notifikasi Peringatan Stok Menipis
                    if ((int)$waSetting['notify_low_stock'] === 1) {
                        $minStock = (int)($waSetting['low_stock_threshold'] ?? 5);
                        foreach ($validated_items as $item) {
                            $sharedDb->query('SELECT product_name, stock FROM products WHERE id = :id AND user_id = :uid LIMIT 1');
                            $sharedDb->bind('id', (int)$item['id']);
                            $sharedDb->bind('uid', (int)$business_id);
                            $updatedProd = $sharedDb->single();
                            if ($updatedProd && (int)$updatedProd['stock'] <= $minStock) {
                                $sentLow = $waService->sendLowStockNotification($waSetting['phone_number'], [
                                    'name' => $updatedProd['product_name'],
                                    'stock' => (int)$updatedProd['stock'],
                                    'min_stock' => $minStock,
                                ]);
                                $statusLow = $sentLow ? ($waService->isMock() ? 'simulated' : 'sent') : 'failed';
                                $waNotifModel->logNotification(
                                    $business_id,
                                    $waSetting['phone_number'],
                                    'low_stock',
                                    "Peringatan Stok Menipis: {$updatedProd['product_name']} tersisa {$updatedProd['stock']} unit",
                                    $statusLow,
                                    $sentLow ? 'Peringatan stok menipis otomatis' : $waService->getLastError()
                                );
                            }
                        }
                    }
                }
            } catch (\Throwable $waError) {
                // Jangan gagalkan transaksi kasir jika ada kendala di notifikasi WhatsApp
                error_log('[CashierController] WhatsApp notification warning: ' . $waError->getMessage());
            }

            // Buat token CSRF baru untuk transaksi berikutnya
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            echo json_encode([
                'success'        => true,
                'message'        => 'Transaksi berhasil disimpan!',
                'transaction_id' => $transaction_id,
                'total'          => $total_amount,
                'new_csrf'       => $_SESSION['csrf_token'],
            ]);

        } catch (Exception $e) {
            $sharedDb->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}


