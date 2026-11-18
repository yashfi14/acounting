<?php

require_once __DIR__ . '/ProductController.php';

/**
 * BarangController — Alias router untuk ProductController.
 * Memastikan setiap akses ke rute legacy /barang terlindungi secara penuh oleh RBAC,
 * verifikasi sesi, validasi CSRF, dan pembatasan peran (Admin & Kasir).
 */
class BarangController extends ProductController {}
