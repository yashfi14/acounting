<?php

/**
 * CatalogController — Halaman Publik / Pelanggan untuk melihat Daftar Menu & Barang.
 *
 * Sesuai ketentuan:
 * - Hanya menampilkan barang yang berstatus aktif (is_active = 1)
 * - Menampilkan nama barang, gambar (jika ada), harga (format Rupiah), status ketersediaan
 * - Fitur pencarian barang
 * - TIDAK menampilkan data internal (modal/biaya, omset, data penjualan, riwayat transaksi)
 */
class CatalogController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
    }

    public function index()
    {
        $data['judul']   = 'Daftar Menu & Barang';
        $business_id     = AuthHelper::businessId();

        $limit   = 12; // Grid 12 barang per halaman
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $limit;
        $keyword = strip_tags(trim($_GET['keyword'] ?? ''));

        $productModel        = $this->model('Product_model');
        $data['products']    = $productModel->getPublicCatalog($business_id, $limit, $offset, $keyword);
        $total_data          = $productModel->countPublicCatalog($business_id, $keyword);
        $data['total_pages'] = ceil($total_data / $limit);
        $data['current_page']= $page;
        $data['keyword']     = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
        $data['total_items'] = $total_data;

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('catalog/index', $data);
        $this->view('templates/footer');
    }
}
