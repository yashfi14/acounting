<?php

require_once __DIR__ . '/../Services/ProductImageService.php';

/**
 * ProductController — CRUD Barang & Stok untuk Admin & Kasir.
 *
 * Perbaikan RBAC:
 * - RoleMiddleware::allow(['admin', 'kasir']) — Pelanggan dilarang (403 Forbidden)
 * - Produk di-scope ke AuthHelper::businessId() (toko yang dikelola)
 * - Mendukung pengelolaan status aktif barang (is_active)
 */
class ProductController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
        // Hanya Admin dan Kasir yang memiliki izin CRUD Barang & Pengelolaan Stok
        RoleMiddleware::allow(['admin', 'kasir'], 'Hanya Administrator dan Kasir yang memiliki hak akses untuk mengelola barang dan stok.');
    }

    public function index()
    {
        $data['judul']   = 'Manajemen Barang';
        $business_id     = AuthHelper::businessId();

        $limit   = 10;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $limit;
        $keyword = strip_tags(trim($_GET['keyword'] ?? ''));

        // Ambil flash messages
        $data['success'] = $_SESSION['flash_success'] ?? null;
        $data['error']   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $productModel          = $this->model('Product_model');
        $data['products']      = $productModel->getAllProducts($business_id, $limit, $offset, $keyword);
        $total_data            = $productModel->countProducts($business_id, $keyword);
        $data['total_pages']   = ceil($total_data / $limit);
        $data['current_page']  = $page;
        $data['keyword']       = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
        $data['is_admin']      = RoleMiddleware::isAdmin();

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('products/index', $data);
        $this->view('templates/footer');
    }

    public function create()
    {
        $data['judul']     = 'Tambah Barang';
        $data['old_input'] = $_SESSION['old_input'] ?? [];
        $data['errors']    = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['old_input'], $_SESSION['flash_errors']);

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('products/create', $data);
        $this->view('templates/footer');
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/product/create');
            exit;
        }

        // Validasi CSRF
        if (!isset($_POST['csrf_token']) || !CSRF::validate($_POST['csrf_token'])) {
            $_SESSION['flash_errors'] = ['Request tidak valid. Silakan coba lagi.'];
            header('Location: ' . BASEURL . '/product/create');
            exit;
        }

        $name      = strip_tags(trim($_POST['name'] ?? ''));
        $stock     = (int)($_POST['stock'] ?? 0);
        $price     = $_POST['price'] ?? '';
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $errors    = [];

        // Validasi
        if (empty($name)) {
            $errors[] = 'Nama barang tidak boleh kosong.';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Nama barang maksimal 255 karakter.';
        }

        if ($stock < 0) {
            $errors[] = 'Stok tidak boleh bernilai negatif.';
        }

        if (!is_numeric($price) || (float)$price < 0) {
            $errors[] = 'Harga harus berupa angka positif yang valid.';
        }

        $imagePath = null;
        if (isset($_FILES['image'])) {
            [$imagePath, $imageError] = ProductImageService::store($_FILES['image']);
            if ($imageError !== null) {
                $errors[] = $imageError;
            }
        }

        if (!empty($errors)) {
            if ($imagePath !== null) {
                ProductImageService::delete($imagePath);
            }
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_input'] = [
                'name' => $name, 'stock' => $stock, 'price' => $price, 'is_active' => $is_active
            ];
            header('Location: ' . BASEURL . '/product/create');
            exit;
        }

        $business_id = AuthHelper::businessId();

        $data = [
            'user_id'   => $business_id,
            'name'      => $name,
            'stock'     => $stock,
            'price'     => (float)$price,
            'is_active' => $is_active,
            'image'     => $imagePath,
        ];

        if ($this->model('Product_model')->addProduct($data) > 0) {
            $_SESSION['flash_success'] = 'Barang <strong>' . htmlspecialchars($name, ENT_QUOTES) . '</strong> berhasil ditambahkan.';
            header('Location: ' . BASEURL . '/product');
        } else {
            if ($imagePath !== null) {
                ProductImageService::delete($imagePath);
            }
            $_SESSION['flash_errors'] = ['Gagal menyimpan barang. Silakan coba lagi.'];
            header('Location: ' . BASEURL . '/product/create');
        }
        exit;
    }

    public function edit($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) {
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        $data['judul']     = 'Edit Barang';
        $business_id       = AuthHelper::businessId();
        $data['errors']    = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);

        $data['product'] = $this->model('Product_model')->getProductById($id, $business_id);

        if (!$data['product']) {
            $_SESSION['flash_error'] = 'Barang tidak ditemukan atau di luar lingkup usaha Anda.';
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('products/edit', $data);
        $this->view('templates/footer');
    }

    public function update($id = null)
    {
        $id = (int)$id;
        if ($id <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        // Validasi CSRF
        if (!isset($_POST['csrf_token']) || !CSRF::validate($_POST['csrf_token'])) {
            $_SESSION['flash_errors'] = ['Request tidak valid.'];
            header('Location: ' . BASEURL . '/product/edit/' . $id);
            exit;
        }

        $business_id = AuthHelper::businessId();
        $name        = strip_tags(trim($_POST['name'] ?? ''));
        $stock       = (int)($_POST['stock'] ?? 0);
        $price       = $_POST['price'] ?? '';
        $is_active   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $errors      = [];

        if (empty($name)) {
            $errors[] = 'Nama barang tidak boleh kosong.';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Nama barang maksimal 255 karakter.';
        }

        if ($stock < 0) {
            $errors[] = 'Stok tidak boleh bernilai negatif.';
        }

        if (!is_numeric($price) || (float)$price < 0) {
            $errors[] = 'Harga harus berupa angka positif yang valid.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            header('Location: ' . BASEURL . '/product/edit/' . $id);
            exit;
        }

        $productModel = $this->model('Product_model');
        $product      = $productModel->getProductById($id, $business_id);
        if (!$product) {
            $_SESSION['flash_error'] = 'Barang tidak ditemukan atau di luar lingkup usaha Anda.';
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        $imagePath = null;
        if (isset($_FILES['image'])) {
            [$imagePath, $imageError] = ProductImageService::store($_FILES['image']);
            if ($imageError !== null) {
                $errors[] = $imageError;
            }
        }

        if (!empty($errors)) {
            if ($imagePath !== null) {
                ProductImageService::delete($imagePath);
            }
            $_SESSION['flash_errors'] = $errors;
            header('Location: ' . BASEURL . '/product/edit/' . $id);
            exit;
        }

        $data = [
            'id'        => $id,
            'user_id'   => $business_id,
            'name'      => $name,
            'stock'     => $stock,
            'price'     => (float)$price,
            'is_active' => $is_active,
        ];
        if ($imagePath !== null) {
            $data['image'] = $imagePath;
        }

        $productModel->updateProduct($data);

        if ($imagePath !== null && !empty($product['image']) && $product['image'] !== $imagePath) {
            ProductImageService::delete($product['image']);
        }

        $_SESSION['flash_success'] = 'Barang <strong>' . htmlspecialchars($name, ENT_QUOTES) . '</strong> berhasil diperbarui.';
        header('Location: ' . BASEURL . '/product');
        exit;
    }

    public function delete($id = null)
    {
        $id = (int)$id;
        if ($id <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        // Validasi CSRF
        if (!isset($_POST['csrf_token']) || !CSRF::validate($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Token keamanan tidak valid.';
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        $business_id  = AuthHelper::businessId();
        $productModel = $this->model('Product_model');

        $product = $productModel->getProductById($id, $business_id);
        if (!$product) {
            $_SESSION['flash_error'] = 'Barang tidak ditemukan atau di luar lingkup usaha Anda.';
            header('Location: ' . BASEURL . '/product');
            exit;
        }

        $productModel->deleteProduct($id, $business_id);
        if (!empty($product['image'])) {
            ProductImageService::delete($product['image']);
        }
        $_SESSION['flash_success'] = 'Barang berhasil dihapus dari inventaris.';
        header('Location: ' . BASEURL . '/product');
        exit;
    }
}
