<?php

require_once __DIR__ . '/AIDataService.php';
require_once __DIR__ . '/AIConversationService.php';
require_once __DIR__ . '/DatabaseSchemaService.php';
require_once __DIR__ . '/ApplicationKnowledgeService.php';
require_once __DIR__ . '/AIQueryEngine.php';
require_once __DIR__ . '/AIAccountingContextEngine.php';

class SmartLocalAIProvider {
    private $dataService;
    private $schemaService;
    private $queryEngine;
    private $accountingEngine;
    private $userId;
    private $userRole;
    private $userName;
    private $ownerId;
    /** True bila jawaban lokal tidak ditemukan dan layak diteruskan ke Gemini. */
    private $fallback = false;
    /** Pertanyaan mentah terakhir, dipakai handler yang butuh teks asli. */
    private $lastQuestion = '';

    public function __construct($userId = null) {
        $this->dataService = new AIDataService();
        $this->schemaService = new DatabaseSchemaService($this->dataService->getPdo());
        $this->queryEngine = new AIQueryEngine($this->dataService);
        $this->accountingEngine = new AIAccountingContextEngine($this->dataService);
        $this->userId = $userId;
        
        if (session_status() === PHP_SESSION_ACTIVE && !$this->userId) {
            $this->userId = $_SESSION['user_id'] ?? null;
        }

        if ($this->userId) {
            $user = $this->dataService->getUserContext($this->userId);
            if ($user) {
                $this->userRole = $user['role'];
                $this->userName = $user['nama_lengkap'];
                $this->ownerId  = $this->dataService->resolveOwnerId($user['id'], $user['role']);
            }
        } else {
            $this->userRole = 'pelanggan';
            $this->userName = 'Pengguna';
            $this->ownerId  = 1;
        }
    }

    public function setUserContext($userId, $role, $name) {
        $this->userId   = $userId;
        $this->userRole = $role;
        $this->userName = $name;
        $this->ownerId  = $this->dataService->resolveOwnerId($userId, $role);
    }

    public function isFallback(): bool {
        return $this->fallback;
    }

    public function ask($question, array $history = []): string {
        $this->fallback = false;
        $rawQ = trim($question);
        $this->lastQuestion = $rawQ;
        $q = mb_strtolower($rawQ, 'UTF-8');

        // Normalisasi typo umum bahasa Indonesia sehari-hari
        $typoMap = [
            'tukiskan' => 'tuliskan',
            'tukis'    => 'tulis',
            'tulisan'  => 'tuliskan',
            'trasaksi' => 'transaksi',
            'transksi' => 'transaksi',
            'tranasksi'=> 'transaksi',
            'teransaksi'=> 'transaksi',
            'brang'    => 'barang',
            'prodk'    => 'produk',
            'prduk'    => 'produk',
            'omset'    => 'omzet',
        ];
        foreach ($typoMap as $wrong => $right) {
            $q = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/u', $right, $q);
        }

        // Refresh user context from DB if user_id is set
        if ($this->userId && empty($this->userName)) {
            $user = $this->dataService->getUserContext($this->userId);
            if ($user) {
                $this->userRole = $user['role'];
                $this->userName = $user['nama_lengkap'];
                $this->ownerId  = $this->dataService->resolveOwnerId($user['id'], $user['role']);
            }
        }

        // 0. Panduan penggunaan aplikasi (dokumentasi lokal, bukan query data).
        //    Harus diperiksa sebelum follow-up agar "cara melihat laporan"
        //    tidak dianggap sebagai permintaan data transaksi.
        if ($this->isApplicationHelp($q)) {
            return $this->handleApplicationHelp($q);
        }

        // Resolusi Pertanyaan Lanjutan / Multi-Turn Follow-Up
        $followUp = $this->resolveFollowUp($q, $history);
        if ($followUp !== null) {
            return $followUp;
        }

        // 0a. Ringkasan seluruh aktivitas aplikasi (lintas tabel)
        if ($this->isApplicationOverview($q)) {
            return $this->handleApplicationOverview();
        }

        // 0b. Stok berdasarkan kategori (sistem belum punya tabel kategori)
        if ($this->isCategoryStock($q)) {
            return $this->handleCategoryStock();
        }

        // 0c. Struktur/skema database aplikasi (khusus Admin)
        if ($this->isAppSchema($q)) {
            return $this->handleAppSchema();
        }

        // 0d. Barang masuk/keluar (hanya barang keluar yang tercatat via transaksi)
        if ($this->isStockMovementTotals($q)) {
            return $this->handleStockMovementTotals($q);
        }

        // 0e. Siapa pengguna/kasir yang melakukan transaksi
        if ($this->isTransactionActors($q)) {
            return $this->handleTransactionActors($q);
        }

        // 0f. Notifikasi WhatsApp & pengaturan WhatsApp (khusus Admin)
        if ($this->isNotificationSummary($q)) {
            return $this->handleNotificationSummary();
        }
        if ($this->isWaSettings($q)) {
            return $this->handleWaSettings();
        }

        // 0g. Domain Akuntansi & Safe Query Engine (Pembelian, Piutang, Utang, Laba Kotor, Saldo Kas/Bank, Pelanggan Terbesar)
        $domainResult = $this->queryEngine->handleDomainQuestion($rawQ, (int)$this->ownerId, (string)$this->userRole);
        if (!empty($domainResult['handled'])) {
            return $domainResult['answer'];
        }

        // 1. Total Varian Produk / Jumlah Jenis Produk
        if ($this->isProductCount($q)) {
            return $this->handleProductCount();
        }

        // 2. Total Stok Keseluruhan / Jumlah Unit Barang
        if ($this->isTotalStock($q)) {
            return $this->handleTotalStock();
        }

        // 3. Produk Termahal & Termurah
        if ($this->isHighestPrice($q)) {
            return $this->handleHighestPrice();
        }
        if ($this->isLowestPrice($q)) {
            return $this->handleLowestPrice();
        }

        // 4. Tanya Harga Produk Tertentu (cth: "berapa harga americano", "harga red velvet")
        if ($this->isProductPriceInquiry($q)) {
            return $this->handleProductPriceInquiry($q);
        }

        // 4b. Fitur operasional yang memang belum direkam sistem (jujur, tanpa mengarang)
        if ($this->isUnsupportedOperational($q)) {
            return $this->handleUnsupportedOperational($q);
        }

        // 4c. Ringkasan / nilai persediaan
        if ($this->isInventorySummary($q)) {
            return $this->handleInventorySummary();
        }

        // 4d. Detail satu produk
        if ($this->isProductDetail($q)) {
            return $this->handleProductDetail($q);
        }

        // 5. Kondisi Stok Umum (cth: "apakah stok masih banyak", "stok aman nggak")
        if ($this->isStockCondition($q)) {
            return $this->handleStockCondition();
        }

        // 6. Barang Hampir Habis / Perlu Restok
        if ($this->isLowStock($q)) {
            return $this->handleLowStock();
        }

        // 7. Produk Stok Paling Banyak / Terbanyak
        if ($this->isHighestStock($q)) {
            return $this->handleHighestStock();
        }

        // 8. Produk Stok Paling Sedikit / Tersedikit
        if ($this->isLowestStock($q)) {
            return $this->handleLowestStock();
        }

        // 8b. Produk paling jarang / tidak laku
        if ($this->isLeastSelling($q)) {
            return $this->handleLeastSelling($q);
        }

        // 9. Produk Terlaris (Best Seller) — dengan dukungan rentang tanggal
        if ($this->isBestSelling($q) || $this->isBestSellingRange($q)) {
            return $this->handleBestSelling($q);
        }

        // 9b. Jumlah transaksi pada rentang tanggal (termasuk hari ini)
        if ($this->isTransactionCountRange($q)) {
            return $this->handleTransactionCountRange($q);
        }

        // 10. Omzet & Penjualan Hari Ini
        if ($this->isRevenueToday($q)) {
            return $this->handleRevenueToday();
        }

        // 11. Omzet Bulan Ini
        if ($this->isRevenueMonth($q)) {
            return $this->handleRevenueMonth();
        }

        // 12. Total Omzet Keseluruhan
        if ($this->isRevenueTotal($q)) {
            return $this->handleRevenueTotal();
        }

        // 12b. Laporan penjualan pada rentang tanggal (minggu lalu, kemarin, dll.)
        if ($this->isPeriodSales($q)) {
            return $this->handlePeriodSales($q);
        }

        // 12c. Rekap performa kasir (khusus Admin)
        if ($this->isCashierPerformance($q)) {
            return $this->handleCashierPerformance($q);
        }

        // 13. Daftar Nama Barang yang Transaksinya Selesai / Terjual (cth: "tuliskan nama nama barang yang transaksinya selesai", "barang apa saja yang sudah terjual")
        if ($this->isSoldProductsList($q)) {
            return $this->handleSoldProductsList();
        }

        // 14. Detail satu transaksi (cth: "detail transaksi #TRX-3")
        if ($this->isTransactionDetail($q)) {
            return $this->handleTransactionDetail($q);
        }

        // 14b. Daftar / Rincian Seluruh Transaksi yang Ada (cth: "ada transaksi apa saja", "daftar transaksi", "rincian transaksi")
        if ($this->isTransactionList($q)) {
            return $this->handleTransactionList($q);
        }

        // 15. Total Transaksi (Berapa kali transaksi, jumlah transaksi)
        if ($this->isTransactionCount($q)) {
            return $this->handleTransactionCount();
        }

        // 16. Riwayat Transaksi Terakhir
        if ($this->isLastTransaction($q)) {
            return $this->handleLastTransaction();
        }

        // 15. Performa Penjualan & Analisis Toko
        if ($this->isPerformanceAnalysis($q)) {
            return $this->handlePerformanceAnalysis();
        }

        // 16. Daftar Semua Produk / Katalog
        if ($this->isProductList($q)) {
            return $this->handleProductList();
        }

        // 17. Cari Stok Produk Tertentu (misal: "Berapa stok kopi?", "Stok mineral")
        if ($this->isProductStockSearch($q)) {
            return $this->handleProductStockSearch($q);
        }

        // 18. Manajemen Pengguna / Jumlah Akun Pengguna (cth: "ada berapa manajemen pengguna", "berapa user", "ada berapa kasir")
        if ($this->isUserCount($q)) {
            return $this->handleUserCount($q);
        }

        // 19. Daftar Pengguna (cth: "daftar pengguna", "siapa saja pengguna", "daftar kasir")
        if ($this->isUserList($q)) {
            return $this->handleUserList($q);
        }

        // 20. Penjelasan Menu Manajemen Pengguna
        if ($this->isAboutUserManagement($q)) {
            return $this->handleAboutUserManagement();
        }

        // 21. Pertanyaan Panduan Fitur
        if ($this->isHowPrintReceipt($q)) {
            return $this->handleHowPrintReceipt();
        }
        if ($this->isHowAddProduct($q)) {
            return $this->handleHowAddProduct();
        }
        if ($this->isWhyTransactionFailed($q)) {
            return $this->handleWhyTransactionFailed();
        }
        if ($this->isHowUseCashier($q)) {
            return $this->handleHowUseCashier();
        }
        if ($this->isHowAddKasir($q)) {
            return $this->handleHowAddKasir();
        }
        if ($this->isAboutFeatures($q)) {
            return $this->handleAboutFeatures();
        }
        if ($this->isAboutRole($q)) {
            return $this->handleAboutRole();
        }
        if ($this->isAboutSecurity($q)) {
            return $this->handleAboutSecurity();
        }
        if ($this->isWhoAmI($q)) {
            return $this->handleWhoAmI();
        }
        if ($this->isAboutAca($q)) {
            return $this->handleAboutAca();
        }
        if ($this->isUnavailableFeature($q)) {
            return $this->handleUnavailableFeature($rawQ);
        }

        // 19. Salam / Greeting (Pastikan hanya salam tunggal, tidak ada pertanyaan bisnis lain)
        if ($this->isGreeting($q)) {
            return $this->handleGreeting();
        }

        // 20. Direct Product Match: jika pengguna menyebut nama produk langsung (cth: "mineral", "red velvet", "apakah ada butterscoth")
        $directProductAnswer = $this->handleDirectProductQuery($q, $rawQ);
        if ($directProductAnswer !== null) {
            return $directProductAnswer;
        }

        // Fallback Cerdas & Ringkas (Tidak bertele-tele)
        return $this->handleSmartFallback($rawQ);
    }

    // ---- MULTI-TURN RESOLVER ----

    private function resolveFollowUp($q, array $history) {
        if (empty($history)) return null;

        // Jangan biarkan konteks lama menimpa pertanyaan identitas/umum yang eksplisit.
        if ($this->isWhoAmI($q) || $this->isGreeting($q) || $this->isAboutAca($q)) {
            return null;
        }

        $lastConv = end($history);
        $lastQ = mb_strtolower($lastConv['question'] ?? '', 'UTF-8');

        // Cari topik Accounting terakhir di beberapa turn. Ini penting untuk
        // rangkaian follow-up seperti:
        //   "total penjualan bulan ini?" -> "kalau hari ini?" -> "kalau kemarin?"
        // Tanpa ini, turn kedua/ketiga kehilangan topik dan bisa dianggap
        // pertanyaan stok produk.
        $contextQ = $lastQ;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $hq = mb_strtolower((string)($history[$i]['question'] ?? ''), 'UTF-8');
            if (preg_match('/\b(omzet|omset|penjualan|pendapatan|pemasukan|transaksi|laporan|untung|laba)\b/i', $hq)) {
                $contextQ = $hq;
                break;
            }
        }

        // Follow-up periode: "kalau minggu ini?", "kalau kemarin?"
        $followUpRange = $this->dateRange($q);
        if ($followUpRange !== null && mb_strlen(trim($q), 'UTF-8') <= 45) {
            if ($this->contains($contextQ, 'omzet') || $this->contains($contextQ, 'omset') || $this->contains($contextQ, 'penjualan') || $this->contains($contextQ, 'pendapatan') || $this->contains($contextQ, 'pemasukan') || $this->contains($contextQ, 'laporan')) {
                return $this->handlePeriodSales($q);
            }
            if ($this->contains($contextQ, 'transaksi')) {
                return $this->handleTransactionCountRange($q);
            }
        }

        // Follow-up kasir: "kalau kasir Udin?"
        $cashierTerm = AIConversationService::extractCashierTerm($q);
        if ($cashierTerm !== null && ($this->contains($contextQ, 'transaksi') || $this->contains($contextQ, 'penjualan') || $this->contains($contextQ, 'kasir'))) {
            return $this->handleTransactionList($q);
        }

        if ($this->matchesAny($q, [
            'mana yang paling sedikit', 'mana yang paling dikit', 'yang paling sedikit apa',
            'yang paling dikit', 'paling sedikit', 'paling dikit', 'stok terendah', 'mana yang sedikit', 'mana yang dikit'
        ])) {
            return $this->handleLowestStock();
        }

        if ($this->matchesAny($q, [
            'mana yang paling banyak', 'yang paling banyak apa', 'paling banyak apa', 'stok tertinggi', 'mana yang banyak'
        ])) {
            return $this->handleHighestStock();
        }

        if (preg_match('/^(?:kalau|kalo|gimana kalau|bagaimana dengan|kalo untuk|kalau untuk)\s+([a-z0-9\s]+)\??$/i', $q, $m)) {
            $productKeyword = trim($m[1]);
            $isDateKeyword = preg_match('/\b(hari\s+ini|kemarin|minggu|pekan|bulan|tahun|lalu|ini|sekarang|tadi)\b/i', $productKeyword);
            if (!empty($productKeyword) && !$isDateKeyword) {
                return $this->handleProductStockSearch("stok " . $productKeyword);
            }
        }

        if ($this->matchesAny($q, ['hari ini', 'kalau hari ini', 'kalo hari ini']) && ($this->contains($lastQ, 'omzet') || $this->contains($lastQ, 'penjualan') || $this->contains($lastQ, 'pendapatan'))) {
            return $this->handleRevenueToday();
        }

        // Follow-up konteks transaksi (cth: setelah bot menjawab jumlah transaksi / omzet)
        if ($this->contains($lastQ, 'transaksi') || $this->contains($lastQ, 'omzet') || $this->contains($lastQ, 'penjualan')) {
            if ($this->contains($q, 'barang') || $this->contains($q, 'produk') || $this->contains($q, 'nama') || $this->contains($q, 'terjual') || $this->contains($q, 'laku')) {
                return $this->handleSoldProductsList();
            }
            if ($this->matchesAny($q, ['apa saja', 'apa aja', 'ada apa saja', 'ada apa aja', 'mana saja', 'mana aja', 'sebutkan', 'rinciannya', 'detailnya', 'lihat', 'tampilkan'])) {
                return $this->handleTransactionList();
            }
        }

        return null;
    }

    // ---- INTENT DETECTORS ----

    private function isApplicationOverview($q): bool {
        if ($this->matchesAny($q, ['cara ', 'bagaimana cara', 'gimana cara', 'panduan'])) {
            return false;
        }
        return $this->matchesAny($q, [
            'ringkasan seluruh aktivitas', 'ringkasan aktivitas', 'ringkasan aplikasi',
            'aktivitas aplikasi', 'statistik aplikasi', 'rekap seluruh data',
            'semua data aplikasi', 'ringkasan sistem', 'overview aplikasi',
            'ringkasan data aplikasi', 'ringkasan seluruh data', 'laporan seluruh aktivitas'
        ]);
    }

    private function isCategoryStock($q): bool {
        if (!$this->contains($q, 'kategori')) {
            return false;
        }
        return $this->matchesAny($q, ['stok', 'stock', 'barang', 'produk', 'persediaan', 'inventaris']);
    }

    private function isAppSchema($q): bool {
        return $this->matchesAny($q, [
            'skema database', 'struktur database', 'tabel database', 'relasi tabel',
            'relasi database', 'tabel apa saja', 'database aplikasi', 'struktur tabel',
            'skema aplikasi', 'tabel yang ada'
        ]);
    }

    private function isStockMovementTotals($q): bool {
        if ($this->matchesAny($q, ['riwayat stok', 'histori stok', 'history stok', 'pergerakan stok', 'mutasi stok', 'kartu stok'])) {
            return false; // dijawab jujur oleh handleUnsupportedOperational
        }
        if ($this->matchesAny($q, [
            'barang masuk', 'stok masuk', 'barang keluar', 'stok keluar',
            'jumlah barang masuk', 'jumlah barang keluar', 'total barang masuk', 'total barang keluar',
            'berapa barang masuk', 'berapa barang keluar'
        ])) {
            return true;
        }
        $hasIn = $this->contains($q, 'masuk') && $this->matchesAny($q, ['barang', 'stok', 'produk']);
        $hasOut = $this->contains($q, 'keluar') && $this->matchesAny($q, ['barang', 'stok', 'produk']);
        return ($hasIn || $hasOut) && $this->matchesAny($q, ['berapa', 'jumlah', 'total', 'banyak']);
    }

    private function isTransactionActors($q): bool {
        if ($this->matchesAny($q, [
            'siapa pengguna yang melakukan transaksi', 'siapa yang melakukan transaksi',
            'pengguna yang melakukan transaksi', 'siapa saja yang transaksi',
            'aktor transaksi', 'kasir yang melakukan transaksi', 'siapa kasir yang transaksi',
            'siapa saja kasir yang transaksi', 'siapa yang mencatat transaksi'
        ])) {
            return true;
        }
        return $this->contains($q, 'transaksi')
            && $this->matchesAny($q, ['siapa', 'pengguna'])
            && $this->matchesAny($q, ['melakukan', 'membuat', 'mencatat']);
    }

    private function isNotificationSummary($q): bool {
        if (!$this->matchesAny($q, ['notifikasi', 'whatsapp', ' wa '])) {
            return false;
        }
        if ($this->matchesAny($q, ['cara ', 'bagaimana cara', 'gimana cara', 'panduan', 'apa itu', 'fungsi', 'fitur'])) {
            return false;
        }
        return $this->matchesAny($q, [
            'ringkasan', 'riwayat', 'status', 'terakhir', 'berapa', 'jumlah', 'log',
            'notifikasi', 'terkirim', 'gagal'
        ]);
    }

    private function isWaSettings($q): bool {
        if (!$this->matchesAny($q, ['whatsapp', ' wa ', 'notifikasi'])) {
            return false;
        }
        return $this->matchesAny($q, ['pengaturan', 'setting', 'konfigurasi', 'ambang', 'threshold', 'terhubung']);
    }

    private function isProductCount($q): bool {
        // "ada berapa total varian produk", "berapa varian produk", "ada berapa produk", "jumlah jenis produk", "manajemen barang"
        if ($this->matchesAny($q, [
            'total varian', 'varian produk', 'jumlah varian', 'berapa varian', 'ada berapa varian',
            'berapa produk', 'ada berapa produk', 'jumlah produk', 'total produk', 'banyak produk',
            'banyaknya produk', 'jenis produk', 'macam produk', 'berapa jenis barang', 'jumlah jenis barang',
            'berapa macam barang', 'total jenis barang', 'total varian barang', 'ada berapa barang yang dijual',
            'manajemen barang', 'ada berapa manajemen barang'
        ])) {
            return true;
        }

        if (($this->contains($q, 'varian') || $this->contains($q, 'jenis') || $this->contains($q, 'produk') || $this->contains($q, 'barang')) &&
            $this->matchesAny($q, ['ada berapa', 'berapa total', 'jumlah', 'berapa banyak']) &&
            !$this->contains($q, 'stok') && !$this->contains($q, 'harga') && !$this->contains($q, 'transaksi')) {
            return true;
        }

        return false;
    }

    private function isTotalStock($q): bool {
        // "total stok", "total unit stok", "ada berapa total stok", "stok keseluruhan"
        if ($this->matchesAny($q, [
            'total stok', 'stok total', 'total unit stok', 'stok keseluruhan', 'seluruh stok',
            'semua stok', 'jumlah stok keseluruhan', 'berapa unit stok', 'total persediaan',
            'total persediaan barang', 'total stok barang', 'ada berapa total stok'
        ])) {
            return true;
        }

        if ($this->contains($q, 'stok') && $this->matchesAny($q, ['total', 'keseluruhan', 'semua', 'seluruh', 'unit'])) {
            return true;
        }

        return false;
    }

    private function isHighestPrice($q): bool {
        return $this->matchesAny($q, [
            'paling mahal', 'termahal', 'harga tertinggi', 'harga paling tinggi', 'produk termahal', 'barang termahal'
        ]);
    }

    private function isLowestPrice($q): bool {
        return $this->matchesAny($q, [
            'paling murah', 'termurah', 'harga terendah', 'harga paling rendah', 'produk termurah', 'barang termurah', 'paling terjangkau'
        ]);
    }

    private function isProductPriceInquiry($q): bool {
        if ($this->matchesAny($q, ['berapa harga', 'harga berapa', 'harganya berapa', 'harga produk', 'harga barang'])) {
            return true;
        }
        if ($this->contains($q, 'harga') && !$this->isHighestPrice($q) && !$this->isLowestPrice($q)) {
            return true;
        }
        return false;
    }

    private function isInventorySummary($q): bool {
        return $this->matchesAny($q, [
            'ringkasan inventaris', 'ringkasan persediaan', 'ringkasan stok', 'nilai stok',
            'nilai persediaan', 'total nilai stok', 'nilai inventaris', 'berapa nilai stok',
            'inventaris saya', 'persediaan saya', 'rekap stok', 'statistik stok'
        ]);
    }

    private function isUnsupportedOperational($q): bool {
        return $this->matchesAny($q, [
            'riwayat stok', 'histori stok', 'history stok', 'pergerakan stok', 'mutasi stok',
            'stok masuk', 'stok keluar', 'barang masuk', 'barang keluar', 'kartu stok',
            'metode pembayaran', 'payment method', 'jenis pembayaran', 'diskon', 'potongan harga',
            'pajak', 'ppn', 'export data', 'ekspor data', 'import data', 'impor data',
            'backup data', 'restore data', 'laporan pdf', 'cetak laporan', 'data pelanggan',
            'riwayat pelanggan', 'poin pelanggan', 'loyalty'
        ]);
    }

    private function isProductDetail($q): bool {
        return $this->matchesAny($q, [
            'detail produk', 'detail barang', 'info produk', 'info barang', 'informasi produk',
            'informasi barang', 'rincian produk', 'rincian barang', 'data produk', 'data barang',
            'spesifikasi produk', 'spesifikasi barang'
        ]);
    }

    private function isLeastSelling($q): bool {
        return $this->matchesAny($q, [
            'paling jarang terjual', 'paling jarang laku', 'jarang terjual', 'jarang laku',
            'kurang laku', 'tidak laku', 'tidak terjual', 'belum terjual', 'paling sedikit terjual',
            'paling sedikit laku', 'produk tidak laku', 'barang tidak laku', 'produk sepi'
        ]);
    }

    private function isBestSellingRange($q): bool {
        if (!$this->isBestSelling($q)) return false;
        return $this->dateRange($q) !== null;
    }

    private function isTransactionCountRange($q): bool {
        if ($this->dateRange($q) === null) return false;
        if ($this->contains($q, 'omzet') || $this->contains($q, 'omset') || $this->contains($q, 'pendapatan') || $this->contains($q, 'penjualan')) {
            return false; // ditangani laporan penjualan/omzet
        }
        return $this->contains($q, 'transaksi') &&
            $this->matchesAny($q, ['berapa', 'jumlah', 'total', 'ada berapa', 'banyak']);
    }

    private function isPeriodSales($q): bool {
        if ($this->dateRange($q) === null) return false;
        if ($this->matchesAny($q, ['cara ', 'bagaimana cara', 'gimana cara', 'panduan'])) return false;
        return $this->matchesAny($q, ['omzet', 'omset', 'penjualan', 'pendapatan', 'pemasukan', 'laba', 'untung', 'laporan']);
    }

    private function isCashierPerformance($q): bool {
        if ($this->matchesAny($q, ['cara transaksi', 'cara menggunakan kasir', 'panduan kasir', 'proses kasir', 'transaksi di kasir'])) {
            return false;
        }
        return $this->matchesAny($q, [
            'performa kasir', 'kinerja kasir', 'penjualan per kasir', 'rekap kasir',
            'siapa kasir terbaik', 'kasir paling', 'transaksi kasir', 'penjualan kasir',
            'kinerja staf', 'performa staf', 'kasir mana yang'
        ]);
    }

    private function isTransactionDetail($q): bool {
        if (!$this->contains($q, 'transaksi') && !$this->contains($q, 'nota') && !$this->contains($q, 'trx')) {
            return false;
        }
        if ($this->matchesAny($q, ['terakhir', 'terbaru', 'terkini'])) {
            return false; // ditangani intent transaksi terakhir
        }
        if (preg_match('/#?\s*trx[-\s]?(\d{1,7})/i', $q)) return true;
        return (bool)preg_match('/\b(?:transaksi|nota)\s*(?:nomor|no\.?|id)?\s*#?(\d{1,7})\b/i', $q);
    }

    private function isStockCondition($q): bool {
        if ($this->matchesAny($q, [
            'apakah stok masih banyak', 'stok masih banyak', 'stok banyak nggak', 'stok masih aman',
            'stok aman nggak', 'stok aman gak', 'stok aman ga', 'apakah stok aman', 'apakah stok cukup',
            'kondisi stok', 'keadaan stok', 'status stok', 'bagaimana kondisi stok', 'gimana kondisi stok',
            'ringkasan stok', 'kondisi barang', 'cek kondisi stok', 'stok saya masih aman', 'apakah stok saya cukup',
            'apakah stok barang masih banyak', 'stok masih ada banyak'
        ])) {
            return true;
        }

        if (($this->contains($q, 'stok') || $this->contains($q, 'barang')) && 
            $this->matchesAny($q, ['masih banyak', 'aman nggak', 'aman gak', 'aman ga', 'masih aman', 'cukup nggak', 'kondisi', 'keadaan', 'status']) &&
            !$this->contains($q, 'harga')) {
            return true;
        }

        return false;
    }

    private function isLowStock($q): bool {
        return $this->matchesAny($q, [
            'hampir habis', 'mau habis', 'akan habis', 'hampir kosong', 'stok menipis', 'stok tipis',
            'stok kritis', 'stok rendah', 'stok habis', 'stok kosong', 'perlu restok', 'perlu restock',
            'harus restok', 'harus restock', 'yang mau abis', 'yang hampir abis', 'restok apa',
            'barang apa yang hampir habis', 'barang apa yang mau habis', 'apa yang harus saya restok',
            'barang yang perlu dibeli lagi', 'produk yang habis'
        ]);
    }

    private function isHighestStock($q): bool {
        return $this->matchesAny($q, [
            'stok paling banyak', 'stoknya paling banyak', 'paling banyak stok', 'stok terbanyak',
            'stok tertinggi', 'produk apa yang paling banyak', 'produk mana yang stoknya paling banyak',
            'barang apa yang stoknya paling banyak', 'stok melimpah'
        ]) || ($this->matchesAny($q, ['paling banyak', 'terbanyak']) && $this->contains($q, 'stok'));
    }

    private function isLowestStock($q): bool {
        return $this->matchesAny($q, [
            'stok paling sedikit', 'stoknya paling sedikit', 'stok terendah', 'stok paling kecil',
            'produk mana yang stoknya paling sedikit', 'barang apa yang stoknya paling sedikit',
            'produk apa yang stoknya paling sedikit', 'mana yang paling sedikit', 'mana yang stoknya paling sedikit',
            'paling sedikit stok', 'stok tersedikit'
        ]) || ($this->matchesAny($q, ['paling sedikit', 'tersedikit', 'terendah']) && $this->contains($q, 'stok'));
    }

    private function isBestSelling($q): bool {
        return $this->matchesAny($q, [
            'paling laris', 'paling laku', 'best seller', 'bestseller', 'terlaris', 'terlaku',
            'paling banyak terjual', 'paling sering dibeli', 'produk terlaris', 'barang terlaris',
            'produk apa yang paling laris', 'barang paling laris apa', 'produk mana yang paling laris',
            'barang paling laris', 'paling sering keluar', 'paling banyak keluar', 'sering keluar',
            'barang keluar terbanyak', 'paling sering terjual', 'paling sering laku', 'produk paling sering keluar'
        ]);
    }

    private function isRevenueToday($q): bool {
        if ($this->matchesAny($q, ['hari ini laku berapa', 'laku berapa hari ini', 'omzet hari ini', 'omset hari ini', 'pendapatan hari ini', 'penjualan hari ini', 'pemasukan hari ini', 'transaksi hari ini'])) {
            return true;
        }
        return ($this->contains($q, 'hari ini') || $this->contains($q, 'today')) && 
               $this->matchesAny($q, ['omzet', 'omset', 'pendapatan', 'pemasukan', 'penjualan', 'laku', 'penghasilan', 'transaksi']);
    }

    private function isRevenueMonth($q): bool {
        return ($this->contains($q, 'bulan ini') || $this->contains($q, 'bulanan')) && 
               $this->matchesAny($q, ['omzet', 'omset', 'pendapatan', 'pemasukan', 'penjualan', 'penghasilan']);
    }

    private function isRevenueTotal($q): bool {
        return $this->matchesAny($q, [
            'total omzet', 'total omset', 'total pendapatan', 'total penjualan', 'total pemasukan',
            'berapa omzet saya', 'berapa omset saya', 'berapa total pendapatan', 'seluruh pendapatan', 'omzet keseluruhan'
        ]);
    }

    private function isSoldProductsList($q): bool {
        if ($this->matchesAny($q, [
            'nama nama barang yang transaksinya selesai', 'nama barang yang transaksinya selesai',
            'barang yang transaksinya selesai', 'produk yang transaksinya selesai',
            'barang apa saja yang transaksinya selesai', 'produk apa saja yang transaksinya selesai',
            'tuliskan nama nama barang yang transaksinya selesai', 'tuliskan nama barang yang transaksinya selesai',
            'sebutkan nama nama barang yang transaksinya selesai', 'sebutkan nama barang yang transaksinya selesai',
            'barang yang selesai ditransaksikan', 'produk yang selesai ditransaksikan',
            'nama barang yang sudah terjual', 'barang apa saja yang sudah terjual', 'barang yang terjual',
            'produk apa saja yang sudah terjual', 'produk yang sudah terjual', 'produk yang terjual',
            'nama nama barang yang terjual', 'barang yang laku apa saja', 'produk yang laku',
            'barang apa saja yang laku', 'barang yang keluar', 'apa saja barang yang terjual',
            'barang yang pernah ditransaksikan', 'produk yang pernah ditransaksikan',
            'barang apa yang ditransaksikan', 'daftar barang yang terjual', 'daftar barang laku',
            'apa saja barang yang laku', 'item apa saja yang terjual', 'item yang terjual'
        ])) {
            return true;
        }

        $hasTrx = $this->contains($q, 'transaksi') || $this->contains($q, 'terjual') || $this->contains($q, 'laku') || $this->contains($q, 'dibeli');
        $hasItem = $this->contains($q, 'barang') || $this->contains($q, 'produk') || $this->contains($q, 'item') || $this->contains($q, 'nama');
        $hasDone = $this->contains($q, 'selesai') || $this->contains($q, 'terjual') || $this->contains($q, 'keluar') || $this->contains($q, 'laku') || $this->contains($q, 'sukses') || $this->contains($q, 'apa saja') || $this->contains($q, 'apa aja');

        return ($hasTrx && $hasItem && $hasDone && !$this->isBestSelling($q));
    }

    private function isTransactionList($q): bool {
        if ($this->matchesAny($q, [
            'ada transaksi apa saja', 'transaksi apa saja', 'apa saja transaksinya', 'apa saja transaksi',
            'daftar transaksi', 'list transaksi', 'riwayat transaksi', 'sebutkan transaksi', 'tampilkan transaksi',
            'rincian transaksi', 'transaksi yang ada', 'transaksi yang sudah ada', 'transaksi apa aja',
            'apa aja transaksinya', 'detail transaksi', 'transaksi selesai apa saja', 'transaksi yang selesai',
            'ada transaksi apa', 'ada transaksi apa aja', 'transaksi apa yang ada', 'transaksi apa yang selesai'
        ])) {
            return true;
        }

        if ($this->contains($q, 'transaksi') && $this->matchesAny($q, ['apa saja', 'apa aja', 'daftar', 'list', 'sebutkan', 'tampilkan', 'mana saja', 'mana aja', 'ada apa', 'detail', 'rincian', 'tercatat'])) {
            return true;
        }

        // Transaksi berdasarkan tanggal/periode tertentu (tanpa kata "tampilkan").
        if ($this->contains($q, 'transaksi') && $this->dateRange($q) !== null) {
            return true;
        }

        return false;
    }

    private function isTransactionCount($q): bool {
        return $this->matchesAny($q, [
            'total transaksi', 'ada berapa transaksi', 'berapa transaksi', 'jumlah transaksi',
            'berapa kali transaksi', 'berapa banyak transaksi', 'total penjualan yang tercatat',
            'ada berapa riwayat transaksi', 'database penjualan'
        ]) && !$this->contains($q, 'hari ini') && !$this->contains($q, 'terakhir') && !$this->isTransactionList($q);
    }

    private function isLastTransaction($q): bool {
        return $this->matchesAny($q, ['transaksi terakhir', 'transaksi terbaru', 'penjualan terakhir', 'transaksi terkini', 'order terakhir']);
    }

    private function isPerformanceAnalysis($q): bool {
        return $this->matchesAny($q, [
            'performa penjualan', 'performa toko', 'kinerja toko', 'kinerja penjualan', 'analisis bisnis',
            'analisa bisnis', 'bagaimana performa', 'gimana performa', 'evaluasi toko', 'kondisi toko',
            'kondisi bisnis', 'perkembangan toko', 'perkembangan penjualan', 'ringkasan bisnis', 'bagaimana performa penjualan saya'
        ]);
    }

    private function isProductList($q): bool {
        return $this->matchesAny($q, ['daftar produk', 'list produk', 'semua produk', 'daftar barang', 'list barang', 'produk apa saja', 'barang apa saja', 'tampilkan produk', 'katalog produk']);
    }

    private function isProductStockSearch($q): bool {
        if ($this->matchesAny($q, ['fitur', 'menu', 'aplikasi', 'sistem', 'akun', 'password', 'cetak', 'tambah'])) {
            return false;
        }

        if ($this->matchesAny($q, [
            'berapa stok', 'cek stok', 'lihat stok', 'stok barang', 'stok produk', 'stoknya berapa',
            'sisa stok', 'sisa berapa', 'berapa sisa', 'apakah ada', 'ada gak', 'ada nggak', 'ada ga',
            'jual gak', 'jual nggak', 'jual ga', 'punya gak', 'punya nggak'
        ])) {
            return true;
        }

        if ($this->contains($q, 'stok') && !$this->isStockCondition($q) && !$this->isLowStock($q) && !$this->isHighestStock($q) && !$this->isLowestStock($q) && !$this->isTotalStock($q)) {
            return true;
        }

        return false;
    }

    private function isGreeting($q): bool {
        $clean = trim(preg_replace('/[?!.,]/', '', $q));
        return (bool)preg_match('/^(halo|hai|hello|hi|hey|assalamualaikum|selamat (pagi|siang|sore|malam)|apa kabar)(\s+aca)?$/i', $clean);
    }

    private function isWhoAmI($q): bool {
        return $this->matchesAny($q, [
            'siapa saya', 'siapa aku', 'siapakah saya', 'info akun', 'profil saya',
            'profil akun', 'data akun saya', 'nama saya siapa', 'siapa nama saya',
            'role saya', 'apa role saya', 'role aku', 'peran saya', 'hak akses saya'
        ]);
    }

    private function isAboutAca($q): bool {
        return $this->matchesAny($q, [
            'kamu siapa', 'siapa kamu', 'siapa anda', 'siapakah anda', 'aca siapa', 'apa itu aca',
            'kamu bisa apa', 'bisa bantu apa', 'tentang aca', 'aca itu siapa', 'kamu robot',
            'apa nama kamu', 'nama kamu siapa', 'apakah kamu ai', 'kamu ai', 'apa tugas kamu',
            'siapa yang membuat kamu', 'siapa yang menciptakan kamu'
        ]);
    }

    private function isUnavailableFeature($q): bool {
        return $this->matchesAny($q, [
            'scan barcode', 'barcode', 'shopee', 'tokopedia', 'marketplace', 'hutang',
            'piutang', 'kasbon', 'multi cabang', 'kupon', 'voucher', 'ekspor excel', 'export excel',
            'qris', 'qr code', 'payment gateway', 'midtrans'
        ]);
    }

    private function isHowPrintReceipt($q): bool {
        return $this->matchesAny($q, [
            'cetak struk', 'print struk', 'cetak nota', 'print nota', 'bagaimana cara cetak struk',
            'gimana cara cetak struk', 'cara print struk', 'cara cetak bukti', 'cetak bukti pembayaran',
            'cara cetak struk'
        ]);
    }

    private function isHowAddProduct($q): bool {
        return $this->matchesAny($q, [
            'cara menambahkan produk', 'cara menambah produk', 'cara tambah produk', 'cara menambahkan barang',
            'cara menambah barang', 'cara tambah barang', 'bagaimana cara menambahkan produk', 'gimana cara menambahkan barang',
            'cara input produk', 'cara input barang', 'nambah barang gimana', 'tambah produk baru', 'gimana cara menambahkan produk'
        ]);
    }

    private function isWhyTransactionFailed($q): bool {
        return $this->matchesAny($q, [
            'transaksi gagal', 'kenapa transaksi saya gagal', 'kenapa transaksi gagal', 'checkout gagal',
            'kenapa gagal bayar', 'transaksi error', 'pembayaran gagal', 'kasir error', 'mengapa transaksi gagal'
        ]);
    }

    private function isHowUseCashier($q): bool {
        return $this->matchesAny($q, [
            'cara transaksi di kasir', 'cara kasir', 'cara melakukan transaksi', 'cara jualan di kasir',
            'proses kasir', 'gimana transaksi di kasir', 'bagaimana cara transaksi', 'cara menggunakan kasir'
        ]);
    }

    private function isHowAddKasir($q): bool {
        return $this->matchesAny($q, [
            'tambah kasir', 'menambah kasir', 'buat akun kasir', 'cara tambah kasir', 'daftarkan kasir', 'tambah karyawan', 'gimana tambah kasir'
        ]);
    }

    private function isAboutFeatures($q): bool {
        return $this->matchesAny($q, ['fitur apa', 'apa saja fitur', 'fitur aplikasi', 'aplikasi bisa apa', 'fungsi aplikasi', 'tentang acounting', 'menu apa saja', 'fitur acounting']);
    }

    private function isAboutRole($q): bool {
        return $this->matchesAny($q, ['hak akses', 'role apa saja', 'apa itu admin', 'apa itu kasir', 'apa itu pelanggan', 'perbedaan admin kasir', 'peran pengguna']);
    }

    private function isAboutSecurity($q): bool {
        return $this->matchesAny($q, ['keamanan', 'password', 'ganti password', 'lupa password', 'privasi data', 'enkripsi']);
    }

    private function isUserCount($q): bool {
        if ($this->matchesAny($q, [
            'berapa manajemen pengguna', 'ada berapa manajemen pengguna', 'jumlah manajemen pengguna', 'total manajemen pengguna',
            'berapa pengguna', 'ada berapa pengguna', 'jumlah pengguna', 'total pengguna', 'banyak pengguna',
            'berapa user', 'ada berapa user', 'jumlah user', 'total user', 'banyak user',
            'berapa akun', 'ada berapa akun', 'jumlah akun', 'total akun', 'akun terdaftar',
            'ada berapa kasir', 'berapa kasir', 'jumlah kasir', 'total kasir', 'ada berapa staff', 'ada berapa staf',
            'ada berapa admin', 'berapa admin', 'jumlah admin',
            'ada berapa pelanggan', 'berapa pelanggan', 'jumlah pelanggan'
        ])) {
            return true;
        }

        if (($this->contains($q, 'pengguna') || $this->contains($q, 'user') || $this->contains($q, 'akun') || $this->contains($q, 'kasir') || $this->contains($q, 'manajemen pengguna')) &&
            $this->matchesAny($q, ['ada berapa', 'berapa', 'jumlah', 'total', 'banyak'])) {
            return true;
        }

        return false;
    }

    private function isUserList($q): bool {
        return $this->matchesAny($q, [
            'daftar pengguna', 'list pengguna', 'siapa saja pengguna', 'siapa saja user', 'daftar user',
            'list user', 'daftar kasir', 'list kasir', 'siapa kasir', 'siapa saja kasir', 'siapa admin',
            'data pengguna', 'data user', 'data akun', 'tampilkan pengguna', 'tampilkan user',
            'tampilkan akun', 'semua pengguna', 'semua user', 'semua akun'
        ]);
    }

    private function isAboutUserManagement($q): bool {
        return $this->matchesAny($q, [
            'apa itu manajemen pengguna', 'fungsi manajemen pengguna', 'menu manajemen pengguna',
            'cara manajemen pengguna', 'fitur manajemen pengguna'
        ]);
    }

    /**
     * Deteksi pertanyaan panduan penggunaan aplikasi (bukan pertanyaan data).
     */
    private function isApplicationHelp($q): bool {
        $hasHowTo = (bool)preg_match(
            '/\b(?:cara|caranya|gimana\s+cara|bagaimana\s+cara|langkah|panduan|tutorial|di\s+mana|dimana|letak|lokasi)\b/i',
            $q
        );
        $hasDefinition = (bool)preg_match(
            '/\b(?:menu|fitur|fungsi|kegunaan|pengertian|apa\s+itu|apa\s+fungsi|untuk\s+apa)\b/i',
            $q
        );
        $hasAppTopic = (bool)preg_match(
            '/\b(?:barang|produk|transaksi|kasir|penjualan|laporan|keuangan|pengguna|user|akun|admin|pelanggan|profil|password|verifikasi|otp|whatsapp|dashboard|katalog|aca|acounting|aplikasi|sistem|stok|harga|struk|nota|cetak|database|menu|fitur|login|registrasi)\b/i',
            $q
        );

        if (($hasHowTo || $hasDefinition) && $hasAppTopic) {
            return true;
        }

        return (bool)preg_match(
            '/\b(?:tambah|menambah|menambahkan|buat|membuat|ubah|edit|hapus|batal|batalkan|cetak|print|atur|setting|pengaturan)\b.{0,30}\b(?:barang|produk|transaksi|kasir|pengguna|user|akun|laporan|struk|nota|stok|harga|password|whatsapp|fitur)\b/i',
            $q
        );
    }

    // ---- HANDLERS (RINGKAS & TIDAK BERTELE-TELE) ----

    private function handleApplicationOverview(): string {
        if ($this->userRole === 'pelanggan') {
            return "Ringkasan aktivitas internal toko hanya dapat diakses oleh Admin atau Kasir. Anda tetap dapat melihat katalog produk aktif di menu **Katalog Barang**.";
        }

        if ($this->userRole === 'kasir') {
            $inv = $this->dataService->getInventorySummary($this->ownerId);
            $own = $this->dataService->getSalesSummary($this->ownerId, null, null, (int)$this->userId);
            $rows = [
                ['Produk aktif', number_format($inv['total_products'], 0, ',', '.') . ' varian'],
                ['Total unit stok', number_format($inv['total_units'], 0, ',', '.') . ' unit'],
                ['Stok menipis (≤10)', $inv['low_stock'] . ' produk'],
                ['Stok habis', $inv['out_of_stock'] . ' produk'],
                ['Transaksi Anda', number_format($own['transactions'], 0, ',', '.') . ' transaksi'],
                ['Unit terjual (transaksi Anda)', number_format($own['units'], 0, ',', '.') . ' unit'],
                ['Nilai transaksi Anda', 'Rp ' . number_format($own['total_sales'], 0, ',', '.')],
            ];
            return "**Ringkasan Operasional Anda**\n\n" . $this->markdownTable(['Komponen', 'Nilai'], $rows);
        }

        $o = $this->dataService->getApplicationOverview($this->ownerId);
        $rows = [
            ['Produk terdaftar', number_format($o['products_total'], 0, ',', '.') . ' varian'],
            ['Produk aktif', number_format($o['products_active'], 0, ',', '.') . ' varian'],
            ['Total unit stok', number_format($o['stock_units'], 0, ',', '.') . ' unit'],
            ['Nilai persediaan', 'Rp ' . number_format($o['inventory_value'], 0, ',', '.')],
            ['Total transaksi', number_format($o['transactions_total'], 0, ',', '.') . ' transaksi'],
            ['Total omzet', 'Rp ' . number_format($o['sales_total'], 0, ',', '.')],
            ['Total unit terjual', number_format($o['units_sold'], 0, ',', '.') . ' unit'],
            ['Pengguna aktif', number_format($o['users_active'], 0, ',', '.') . ' dari ' . number_format($o['users_total'], 0, ',', '.') . ' akun'],
            ['Admin / Kasir / Pelanggan', $o['admins'] . ' / ' . $o['cashiers'] . ' / ' . $o['customers']],
            ['Notifikasi WhatsApp', number_format($o['notifications_total'], 0, ',', '.') . ' (' . $o['notifications_sent'] . ' terkirim, ' . $o['notifications_failed'] . ' gagal)'],
        ];

        $lines = ["**Ringkasan Seluruh Aktivitas Aplikasi**", "", $this->markdownTable(['Komponen', 'Nilai'], $rows)];

        if (!empty($o['first_transaction'])) {
            $lines[] = "Periode transaksi: **" . $this->formatDateTime($o['first_transaction']) . "** s.d. **" . $this->formatDateTime($o['last_transaction']) . "**.";
        } else {
            $lines[] = "Belum ada transaksi tercatat di toko ini.";
        }
        $lines[] = "Data kategori, supplier, pemasukan/pengeluaran, dan pergerakan stok tidak tersedia di skema Acounting.";

        return implode("\n", $lines);
    }

    private function handleCategoryStock(): string {
        if ($this->userRole === 'pelanggan') {
            return "Informasi stok internal toko hanya dapat diakses oleh Admin dan Kasir.";
        }

        $rows = $this->dataService->getStockByProduct($this->ownerId, 50);
        if (empty($rows)) {
            return "Belum ada produk aktif di inventaris toko Anda.";
        }

        $totalUnits = 0;
        $tableRows = [];
        foreach ($rows as $r) {
            $stock = (int)$r['stock'];
            $totalUnits += $stock;
            $tableRows[] = [
                ucwords($r['product_name']),
                number_format($stock, 0, ',', '.'),
                'Rp ' . number_format($r['price'], 0, ',', '.'),
                'Rp ' . number_format($r['stock_value'], 0, ',', '.'),
            ];
        }

        return "Acounting **belum memiliki tabel kategori barang**, sehingga total stok per kategori tidak dapat dihitung. Berikut stok per produk sebagai gantinya:\n\n"
            . $this->markdownTable(['Nama Barang', 'Stok', 'Harga', 'Nilai Stok'], $tableRows)
            . "\n\nTotal **" . count($rows) . " produk** dengan **" . number_format($totalUnits, 0, ',', '.') . " unit** stok.";
    }

    private function handleAppSchema(): string {
        if ($this->userRole !== 'admin') {
            return "Informasi struktur database hanya dapat diakses oleh Admin toko.";
        }

        $schema = $this->schemaService->getSchema();
        if (empty($schema)) {
            return "Struktur database tidak dapat dibaca saat ini.";
        }

        // Jika pengguna menyebut satu tabel, tampilkan kolom tabel tersebut.
        $askedTable = null;
        foreach ($schema as $table => $meta) {
            if (!empty($meta['legacy'])) {
                continue;
            }
            if ($this->contains(mb_strtolower($this->lastQuestion ?? '', 'UTF-8'), $table)) {
                $askedTable = $table;
                break;
            }
        }

        if ($askedTable !== null) {
            $cols = [];
            foreach ($schema[$askedTable]['columns'] as $col) {
                if (!empty($col['sensitive'])) {
                    continue; // Kolom password/token/API key/OTP tidak pernah ditampilkan.
                }
                $cols[] = [
                    $col['name'],
                    $col['type'],
                    $col['key'] === 'PRI' ? 'PK' : ($col['key'] === 'UNI' ? 'UNIQUE' : ($col['key'] === 'MUL' ? 'INDEX' : '-')),
                    $col['nullable'] ? 'Ya' : 'Tidak',
                ];
            }
            $relations = [];
            foreach ($schema[$askedTable]['relations'] as $rel) {
                $relations[] = $rel['column'] . ' → ' . $rel['references_table'] . '.' . $rel['references_column'];
            }
            $text = "**Struktur Tabel `{$askedTable}`**\n\n"
                . $this->markdownTable(['Kolom', 'Tipe', 'Key', 'Nullable'], $cols);
            if (!empty($relations)) {
                $text .= "\n\nRelasi: " . implode(', ', $relations) . ".";
            }
            return $text . "\nKolom sensitif (password/token/API key/OTP) disembunyikan.";
        }

        $counts = $this->schemaService->getRowCounts();
        $rows = [];
        foreach ($schema as $table => $meta) {
            if (!empty($meta['legacy'])) {
                continue;
            }
            $rows[] = [$table, $meta['purpose'], number_format($counts[$table] ?? 0, 0, ',', '.')];
        }

        $relations = [];
        foreach ($schema as $table => $meta) {
            if (!empty($meta['legacy'])) {
                continue;
            }
            foreach ($meta['relations'] as $rel) {
                $relations[] = $table . '.' . $rel['column'] . ' → ' . $rel['references_table'] . '.' . $rel['references_column'];
            }
        }

        return "**Tabel Database Acounting**\n\n" . $this->markdownTable(['Tabel', 'Fungsi', 'Jumlah Baris'], $rows)
            . "\n\nRelasi utama: " . implode('; ', $relations) . "."
            . "\nKolom password, token, API key, dan OTP tidak pernah ditampilkan.";
    }

    private function handleStockMovementTotals($q): string {
        if ($this->userRole === 'pelanggan') {
            return "Data stok internal toko hanya dapat diakses oleh Admin dan Kasir.";
        }

        $range = $this->dateRange($q);
        $cashierId = $this->userRole === 'kasir' ? (int)$this->userId : null;
        $summary = $this->dataService->getSalesSummary(
            $this->ownerId,
            $range['start'] ?? null,
            $range['end'] ?? null,
            $cashierId
        );

        $period = $range !== null ? " pada **{$range['label']}**" : '';
        $scope = $cashierId !== null ? ' dari transaksi Anda' : '';
        $lines = [
            "**Jumlah Barang Keluar{$period}{$scope}:** **" . number_format($summary['units'], 0, ',', '.') . " unit** dari **" . number_format($summary['transactions'], 0, ',', '.') . " transaksi**.",
            "",
            "**Jumlah barang masuk tidak dapat dihitung** karena Acounting belum menyimpan riwayat pergerakan/stok masuk. Sistem hanya menyimpan stok terkini dan mengurangi stok otomatis saat transaksi berhasil."
        ];

        $top = $this->dataService->getBestSellingProductsRange(
            $this->ownerId,
            $range['start'] ?? null,
            $range['end'] ?? null,
            5,
            $cashierId
        );
        if (!empty($top)) {
            $rows = [];
            foreach ($top as $item) {
                $rows[] = [ucwords($item['product_name']), number_format($item['total_sold'], 0, ',', '.')];
            }
            $lines[] = "";
            $lines[] = "**Barang keluar terbanyak:**";
            $lines[] = $this->markdownTable(['Nama Barang', 'Unit Keluar'], $rows);
        }

        return implode("\n", $lines);
    }

    private function handleTransactionActors($q): string {
        if ($this->userRole === 'pelanggan') {
            return "Data transaksi internal toko bersifat rahasia.";
        }

        if ($this->userRole === 'kasir') {
            $own = $this->dataService->getSalesSummary($this->ownerId, null, null, (int)$this->userId);
            return "Transaksi Anda dicatat atas nama **" . $this->safeName($this->userName) . "**: **{$own['transactions']} transaksi** dengan nilai **Rp " . number_format($own['total_sales'], 0, ',', '.') . "**.";
        }

        $range = $this->dateRange($q);
        $rows = $this->dataService->getTransactionActors(
            $this->ownerId,
            $range['start'] ?? null,
            $range['end'] ?? null
        );

        if (empty($rows)) {
            return "Belum ada pengguna yang mencatat transaksi pada periode tersebut.";
        }

        $period = $range !== null ? " ({$range['label']})" : '';
        $tableRows = [];
        $totalTrx = 0;
        $totalSales = 0;
        foreach ($rows as $r) {
            $name = !empty($r['nama_lengkap']) ? $this->safeName($r['nama_lengkap']) : 'Tidak diketahui';
            $role = $r['role'] ? ucfirst($r['role']) : '-';
            $trx = (int)$r['total_transactions'];
            $sales = (float)$r['total_sales'];
            $totalTrx += $trx;
            $totalSales += $sales;
            $tableRows[] = [
                $name,
                $role,
                number_format($trx, 0, ',', '.'),
                'Rp ' . number_format($sales, 0, ',', '.'),
                !empty($r['last_transaction']) ? $this->formatDateTime($r['last_transaction']) : '-',
            ];
        }

        return "**Pengguna yang melakukan transaksi{$period}:**\n\n"
            . $this->markdownTable(['Nama', 'Peran', 'Transaksi', 'Total Omzet', 'Transaksi Terakhir'], $tableRows)
            . "\n\nTotal **" . count($rows) . " pengguna**, **" . number_format($totalTrx, 0, ',', '.') . " transaksi**, omzet **Rp " . number_format($totalSales, 0, ',', '.') . "**.";
    }

    private function handleNotificationSummary(): string {
        if ($this->userRole !== 'admin') {
            return "Ringkasan notifikasi WhatsApp hanya dapat diakses oleh Admin toko.";
        }

        $summary = $this->dataService->getNotificationSummary($this->ownerId, 5);
        if (($summary['total'] ?? 0) === 0) {
            return "Belum ada notifikasi WhatsApp yang tercatat.";
        }

        $statusRows = [
            ['Terkirim', number_format($summary['sent'], 0, ',', '.')],
            ['Gagal', number_format($summary['failed'], 0, ',', '.')],
            ['Simulasi', number_format($summary['simulated'], 0, ',', '.')],
            ['Total', number_format($summary['total'], 0, ',', '.')],
        ];

        $text = "**Ringkasan Notifikasi WhatsApp**\n\n" . $this->markdownTable(['Status', 'Jumlah'], $statusRows);

        if (!empty($summary['latest'])) {
            $rows = [];
            foreach ($summary['latest'] as $n) {
                $rows[] = [
                    $this->formatDateTime($n['created_at']),
                    $this->safeName($n['nama_lengkap'] ?? '-'),
                    $n['type'] ?? '-',
                    $this->statusLabel($n['status'] ?? '-'),
                    $this->maskPhone($n['phone_number'] ?? ''),
                ];
            }
            $text .= "\n\n**5 Notifikasi Terbaru**\n\n" . $this->markdownTable(['Tanggal', 'Penerima', 'Jenis', 'Status', 'Nomor'], $rows);
        }

        return $text . "\nIsi pesan notifikasi tidak ditampilkan untuk menjaga privasi.";
    }

    private function handleWaSettings(): string {
        if ($this->userRole !== 'admin') {
            return "Pengaturan WhatsApp hanya dapat diakses oleh Admin toko.";
        }

        $setting = $this->dataService->getWaSettingsSummary($this->ownerId);
        if (!$setting) {
            return "Pengaturan WhatsApp belum dibuat untuk toko ini.";
        }

        $rows = [
            ['Nomor terdaftar', $setting['phone_number'] ? $this->maskPhone($setting['phone_number']) : '-'],
            ['Status koneksi', !empty($setting['is_connected']) ? 'Terhubung' : 'Belum terhubung'],
            ['Notifikasi transaksi', !empty($setting['notify_transaction']) ? 'Aktif' : 'Nonaktif'],
            ['Notifikasi stok rendah', !empty($setting['notify_low_stock']) ? 'Aktif' : 'Nonaktif'],
            ['Ambang stok rendah', (int)($setting['low_stock_threshold'] ?? 0) . ' unit'],
            ['Terverifikasi pada', !empty($setting['verified_at']) ? $this->formatDateTime($setting['verified_at']) : '-'],
        ];

        return "**Pengaturan WhatsApp**\n\n" . $this->markdownTable(['Pengaturan', 'Nilai'], $rows)
            . "\nAPI key dan kode OTP tidak ditampilkan.";
    }

    private function handleUserCount($q): string {
        if ($this->userRole !== 'admin') {
            return "Informasi manajemen pengguna hanya dapat diakses oleh Admin toko.";
        }

        if ($this->contains($q, 'kasir')) {
            $cashiers = $this->dataService->getCashiers($this->ownerId);
            $count = count($cashiers);
            if ($count === 0) {
                return "Saat ini belum ada kasir yang terdaftar untuk toko Anda.";
            }
            $names = array_map(function($c) { return ucwords($c['nama_lengkap']); }, $cashiers);
            return "Toko Anda memiliki **{$count} kasir terdaftar**: **" . implode(', ', $names) . "**.";
        }

        if ($this->contains($q, 'admin')) {
            $counts = $this->dataService->getUserCounts(null);
            return "Terdapat **{$counts['admin']} akun Administrator** aktif di sistem.";
        }

        if ($this->contains($q, 'pelanggan')) {
            $counts = $this->dataService->getUserCounts(null);
            return "Terdapat **{$counts['pelanggan']} akun Pelanggan** terdaftar di sistem.";
        }

        $counts = $this->dataService->getUserCounts(null);
        $total = $counts['total'];
        $admin = $counts['admin'];
        $kasir = $counts['kasir'];
        $pelanggan = $counts['pelanggan'];

        return "Saat ini terdapat **{$total} pengguna terdaftar** di sistem Manajemen Pengguna: **{$admin} Admin**, **{$kasir} Kasir**, dan **{$pelanggan} Pelanggan**.";
    }

    private function handleUserList($q): string {
        if ($this->userRole !== 'admin') {
            return "Informasi daftar pengguna hanya dapat diakses oleh Admin toko.";
        }

        if ($this->contains($q, 'kasir')) {
            $cashiers = $this->dataService->getCashiers($this->ownerId);
            if (empty($cashiers)) {
                return "Belum ada akun kasir yang terdaftar.";
            }
            $lines = ["Daftar kasir toko (" . count($cashiers) . " orang):"];
            foreach ($cashiers as $i => $c) {
                $lines[] = ($i + 1) . ". **" . ucwords($c['nama_lengkap']) . "** (" . $c['email'] . ")";
            }
            return implode("\n", $lines);
        }

        $users = $this->dataService->getAllUsersList(15, null);
        if (empty($users)) {
            return "Belum ada akun pengguna yang terdaftar.";
        }
        $rows = [];
        foreach ($users as $u) {
            $rows[] = [
                $this->safeName($u['nama_lengkap']),
                ucfirst($u['role']),
                $u['email'] ?? '-',
            ];
        }
        return "**Daftar Pengguna Terdaftar** (" . count($users) . " akun)\n\n"
            . $this->markdownTable(['Nama', 'Peran', 'Email'], $rows);
    }

    private function handleAboutUserManagement(): string {
        return "Menu **Manajemen Pengguna** digunakan oleh Admin untuk mengelola akun (Admin, Kasir, dan Pelanggan), mengubah role, dan memantau status operasional akun.";
    }

    /**
     * Jawab panduan fitur dari dokumentasi resmi ApplicationKnowledgeService.
     */
    private function handleApplicationHelp($q): string {
        $guideMap = [
            'tambah_barang' => '/\b(?:tambah|menambah|menambahkan|input|buat)\b.{0,30}\b(?:barang|produk)\b/i',
            'edit_barang' => '/\b(?:ubah|edit|update|ganti|perbarui)\b.{0,30}\b(?:barang|produk|harga|stok)\b/i',
            'hapus_barang' => '/\b(?:hapus|delete)\b.{0,30}\b(?:barang|produk)\b/i',
            'cetak_struk' => '/\b(?:cetak|mencetak|print)\b.{0,30}\b(?:struk|nota|bukti)\b/i',
            'hapus_transaksi' => '/\b(?:batal|batalkan|hapus)\b.{0,30}\btransaksi\b/i',
            'laporan_penjualan' => '/\b(?:laporan|keuangan|omzet|omset|penjualan)\b/i',
            'verifikasi_akun' => '/\b(?:verifikasi|otp|aktivasi)\b/i',
            'tambah_kasir' => '/\b(?:tambah|menambah|menambahkan|buat|membuat|daftarkan|registrasi)\b.{0,30}\b(?:pengguna|kasir|akun|user)\b/i',
            'transaksi_kasir' => '/\b(?:transaksi|kasir|penjualan)\b/i',
        ];

        $guideKey = null;
        foreach ($guideMap as $key => $pattern) {
            if (preg_match($pattern, $q)) {
                $guideKey = $key;
                break;
            }
        }

        $guides = ApplicationKnowledgeService::getGuides();
        if ($guideKey !== null && isset($guides[$guideKey])) {
            $guide = $guides[$guideKey];
            $adminOnly = in_array($guideKey, ['tambah_kasir', 'laporan_penjualan', 'hapus_transaksi'], true);
            if ($adminOnly && $this->userRole !== 'admin') {
                return "Panduan **" . $guide['title'] . "** khusus untuk Admin. Akun Anda saat ini adalah **" . ucfirst((string)$this->userRole) . "**, sehingga menu tersebut belum dapat diakses. Silakan hubungi Admin toko jika Anda memerlukan perubahan pada fitur ini.";
            }

            $text = "**" . $guide['title'] . "**\nMenu: **" . $guide['menu'] . "**\n\n";
            foreach ($guide['steps'] as $index => $step) {
                $text .= ($index + 1) . ". " . $step . "\n";
            }
            return trim($text);
        }

        // Pertanyaan definisi aplikasi.
        if (preg_match('/\b(?:apa\s+itu|pengertian|tentang)\b.{0,20}\b(?:acounting|aplikasi|sistem)\b/i', $q)) {
            return ApplicationKnowledgeService::getAppOverview();
        }

        $menus = ApplicationKnowledgeService::getOfficialMenus();
        $available = [];
        foreach ($menus as $menu) {
            if (in_array($this->userRole, $menu['role'] ?? [], true)) {
                $available[] = '**' . $menu['name'] . '** (' . $menu['url'] . ')';
            }
        }
        $list = implode(', ', array_slice($available, 0, 8));
        return "Saya belum memiliki panduan spesifik untuk pertanyaan itu. Menu yang dapat Anda akses saat ini: {$list}. Silakan sebutkan menu atau langkah yang ingin Anda pelajari, misalnya \"cara menambahkan barang\" atau \"cara membuat transaksi\".";
    }

    private function handleProductCount(): string {
        $counts = $this->dataService->getProductCounts($this->ownerId);
        $variants = $counts['total_variants'];
        $units = number_format($counts['total_units'], 0, ',', '.');
        return "Toko Anda saat ini memiliki **{$variants} varian produk** aktif dengan total stok **{$units} unit**.";
    }

    private function handleTotalStock(): string {
        $counts = $this->dataService->getProductCounts($this->ownerId);
        $units = number_format($counts['total_units'], 0, ',', '.');
        $variants = $counts['total_variants'];
        return "Total persediaan seluruh produk saat ini adalah **{$units} unit** (dari **{$variants} varian produk** aktif).";
    }

    private function handleHighestPrice(): string {
        $p = $this->dataService->getHighestPriceProduct($this->ownerId);
        if (!$p) return "Belum ada produk aktif di katalog toko Anda.";
        $name = ucwords($p['product_name']);
        $price = number_format($p['price'], 0, ',', '.');
        return "Produk dengan harga termahal adalah **{$name}** seharga **Rp {$price}** (stok: {$p['stock']} unit).";
    }

    private function handleLowestPrice(): string {
        $p = $this->dataService->getLowestPriceProduct($this->ownerId);
        if (!$p) return "Belum ada produk aktif di katalog toko Anda.";
        $name = ucwords($p['product_name']);
        $price = number_format($p['price'], 0, ',', '.');
        return "Produk dengan harga termurah adalah **{$name}** seharga **Rp {$price}** (stok: {$p['stock']} unit).";
    }

    private function handleProductPriceInquiry($q): string {
        // Coba cari nama produk yang ada dalam kalimat
        $matched = $this->dataService->findProductInText($this->ownerId, $q);
        if ($matched) {
            $name = ucwords($matched['product_name']);
            $price = number_format($matched['price'], 0, ',', '.');
            return "Harga **{$name}** adalah **Rp {$price}** per unit (sisa stok: **{$matched['stock']} unit**).";
        }

        // Jika belum cocok, bersihkan stopwords
        $stopWords = ['berapa', 'harga', 'harganya', 'cek', 'lihat', 'produk', 'barang', 'apakah', 'ada', 'untuk', 'satuan', 'dong', 'ya'];
        $term = $q;
        foreach ($stopWords as $sw) {
            $term = preg_replace('/\b' . preg_quote($sw, '/') . '\b/iu', '', $term);
        }
        $term = trim(preg_replace('/[?!.,]/', '', $term));

        if (!empty($term)) {
            $results = $this->dataService->searchProduct($this->ownerId, $term);
            if (!empty($results)) {
                $p = $results[0];
                $name = ucwords($p['product_name']);
                $price = number_format($p['price'], 0, ',', '.');
                return "Harga **{$name}** adalah **Rp {$price}** per unit (sisa stok: **{$p['stock']} unit**).";
            }
        }

        return "Produk yang Anda tanyakan harganya tidak ditemukan di katalog toko. Pastikan ejaan nama produk sesuai.";
    }

    private function handleDirectProductQuery($q, $rawQ): ?string {
        $p = $this->dataService->findProductInText($this->ownerId, $q);
        if ($p) {
            $name = ucwords($p['product_name']);
            $price = number_format($p['price'], 0, ',', '.');
            $status = ($p['stock'] == 0) ? ' *(Habis)*' : '';
            return "**{$name}** tersedia di katalog: stok **{$p['stock']} unit**{$status}, harga **Rp {$price}**.";
        }
        return null;
    }

    private function handleStockCondition(): string {
        if ($this->userRole === 'pelanggan') {
            return "Katalog barang aktif dapat Anda lihat langsung di menu beranda.";
        }

        $overview = $this->dataService->getStockOverview($this->ownerId);
        if (!$overview || $overview['total_products'] === 0) {
            return "Saat ini inventaris Anda masih kosong (belum ada produk aktif).";
        }

        $units = number_format($overview['total_units'], 0, ',', '.');
        $vars = $overview['total_products'];

        if ($overview['out_of_stock_count'] > 0) {
            return "Perhatian: Ada **{$overview['out_of_stock_count']} produk habis** dari total {$vars} varian ({$units} unit stok). Segera lakukan restok.";
        } elseif ($overview['low_stock_count'] > 0) {
            return "Stok barang Anda secara umum cukup (total **{$units} unit** dari {$vars} varian), namun ada **{$overview['low_stock_count']} produk** dengan stok menipis (≤ 10 unit).";
        } else {
            return "Secara umum stok barang Anda **masih aman dan mencukupi** (total **{$units} unit** dari **{$vars} varian produk**).";
        }
    }

    private function handleLowStock(): string {
        if ($this->userRole === 'pelanggan') {
            return "Informasi stok internal toko hanya dapat diakses oleh Admin dan Kasir.";
        }

        $lowItems = $this->dataService->getLowStockProducts($this->ownerId, 10);
        if (empty($lowItems)) {
            return "Kondisi aman, saat ini **tidak ada barang yang hampir habis** (semua produk memiliki stok di atas 10 unit).";
        }

        $rows = [];
        $outCount = 0;
        foreach ($lowItems as $item) {
            $stock = (int)$item['stock'];
            if ($stock === 0) {
                $outCount++;
            }
            $rows[] = [ucwords($item['product_name']), number_format($stock, 0, ',', '.')];
        }

        $text = "Berikut barang dengan stok rendah (≤10 unit):\n\n"
            . $this->markdownTable(['Nama Barang', 'Stok'], $rows)
            . "\n\nTerdapat **" . count($lowItems) . " barang** yang perlu diperhatikan";
        if ($outCount > 0) {
            $text .= ", termasuk **{$outCount} barang habis**";
        }
        return $text . ".";
    }

    private function handleHighestStock(): string {
        if ($this->userRole === 'pelanggan') {
            return "Informasi stok internal hanya dapat diakses oleh Admin dan Kasir.";
        }

        $items = $this->dataService->getHighestStockProducts($this->ownerId, 1);
        if (empty($items)) {
            return "Belum ada produk aktif di inventaris Anda.";
        }

        $top = $items[0];
        $name = ucwords($top['product_name']);
        $price = number_format($top['price'], 0, ',', '.');
        return "Produk dengan stok terbanyak saat ini adalah **{$name}** sebanyak **{$top['stock']} unit** (Rp {$price}).";
    }

    private function handleLowestStock(): string {
        if ($this->userRole === 'pelanggan') {
            return "Informasi stok internal hanya dapat diakses oleh Admin dan Kasir.";
        }

        $items = $this->dataService->getLowestStockProducts($this->ownerId, 1);
        if (empty($items)) {
            return "Belum ada produk aktif di inventaris Anda.";
        }

        $low = $items[0];
        $name = ucwords($low['product_name']);
        $price = number_format($low['price'], 0, ',', '.');
        return "Produk dengan stok paling sedikit saat ini adalah **{$name}** dengan sisa **{$low['stock']} unit** (Rp {$price}).";
    }

    private function handleProductStockSearch($q): string {
        if ($this->userRole === 'pelanggan') {
            $matched = $this->dataService->findProductInText($this->ownerId, $q);
            if ($matched) {
                $name = ucwords($matched['product_name']);
                $price = number_format($matched['price'], 0, ',', '.');
                $availability = ((int)$matched['stock'] > 0) ? 'Tersedia' : 'Habis';
                return "**{$name}** — harga **Rp {$price}** per unit. Status: **{$availability}**. Katalog lengkap dapat dilihat di menu **Katalog Barang**.";
            }
            return "Produk yang Anda cari tidak ditemukan pada katalog aktif. Silakan lihat menu **Katalog Barang** untuk daftar lengkap.";
        }

        // Coba cari kecocokan produk dari teks pertanyaan langsung
        $matched = $this->dataService->findProductInText($this->ownerId, $q);
        if ($matched) {
            $name = ucwords($matched['product_name']);
            $price = number_format($matched['price'], 0, ',', '.');
            $status = ($matched['stock'] == 0) ? ' *(Habis)*' : (($matched['stock'] <= 5) ? ' *(Menipis)*' : '');
            return "Stok **{$name}** saat ini tersisa **{$matched['stock']} unit**{$status} (Harga: Rp {$price}).";
        }

        $stopWords = [
            'berapa', 'stok', 'stock', 'stoknya', 'cek', 'cari', 'lihat', 'tampilkan', 'jumlah',
            'apakah', 'ada', 'punya', 'nggak', 'gak', 'ga', 'ya', 'dong', 'sih', 'tolong',
            'info', 'tentang', 'barang', 'produk', 'kalau', 'kalo', 'bagaimana dengan', 'gimana dengan', 'sisa',
            'saat', 'ini', 'sekarang', 'skrg', 'kini', 'hari', 'total', 'keseluruhan', 'semua'
        ];
        
        $term = $q;
        foreach ($stopWords as $sw) {
            $term = preg_replace('/\b' . preg_quote($sw, '/') . '\b/iu', '', $term);
        }
        $term = trim(preg_replace('/[?!.,]/', '', $term));

        if (mb_strlen($term, 'UTF-8') < 2) {
            return $this->handleStockCondition();
        }

        $results = $this->dataService->searchProduct($this->ownerId, $term);
        if (empty($results)) {
            return "Produk **\"{$term}\"** tidak ditemukan dalam katalog barang aktif toko Anda.";
        }

        if (count($results) === 1) {
            $p = $results[0];
            $name = ucwords($p['product_name']);
            $price = number_format($p['price'], 0, ',', '.');
            $status = ($p['stock'] == 0) ? ' *(Habis)*' : (($p['stock'] <= 5) ? ' *(Menipis)*' : '');
            return "Stok **{$name}** saat ini tersisa **{$p['stock']} unit**{$status} (Harga: Rp {$price}).";
        }

        $rows = [];
        foreach ($results as $p) {
            $rows[] = [
                ucwords($p['product_name']),
                number_format($p['stock'], 0, ',', '.'),
                'Rp ' . number_format($p['price'], 0, ',', '.'),
            ];
        }
        return "Ditemukan **" . count($results) . " produk** terkait \"{$term}\":\n\n"
            . $this->markdownTable(['Nama Barang', 'Stok', 'Harga'], $rows);
    }

    private function handleBestSelling($q = ''): string {
        if ($this->userRole === 'pelanggan') {
            return "Data analitik penjualan internal hanya dapat diakses oleh pengelola toko.";
        }

        $range = $this->dateRange((string)$q);
        $cashierId = $this->userRole === 'kasir' ? (int)$this->userId : null;

        if ($range !== null) {
            $best = $this->dataService->getBestSellingProductsRange(
                $this->ownerId, $range['start'], $range['end'], 3, $cashierId
            );
        } else {
            $best = $cashierId !== null
                ? $this->dataService->getBestSellingProductsRange($this->ownerId, null, null, 3, $cashierId)
                : $this->dataService->getBestSellingProducts($this->ownerId, 3);
        }

        if (empty($best)) {
            return "Saat ini **belum ada transaksi penjualan yang tercatat** pada periode tersebut, sehingga data produk terlaris belum terbentuk.";
        }

        $periodLabel = $range !== null ? " ({$range['label']})" : '';
        $scopeLabel = $cashierId !== null ? ' dari transaksi Anda' : '';

        $rows = [];
        $totalUnits = 0;
        foreach ($best as $item) {
            $units = (int)$item['total_sold'];
            $totalUnits += $units;
            $rows[] = [
                ucwords($item['product_name']),
                number_format($units, 0, ',', '.'),
                'Rp ' . number_format($item['total_revenue'], 0, ',', '.'),
            ];
        }

        return "**Produk Terlaris{$periodLabel}{$scopeLabel}**\n\n"
            . $this->markdownTable(['Nama Barang', 'Terjual', 'Omzet'], $rows)
            . "\n\nTotal **" . number_format($totalUnits, 0, ',', '.') . " unit** dari **" . count($best) . " produk**.";
    }

    private function handleLeastSelling($q): string {
        if ($this->userRole === 'pelanggan') {
            return "Data analitik penjualan internal hanya dapat diakses oleh pengelola toko.";
        }

        $range = $this->dateRange($q);
        $items = $this->dataService->getLeastSellingProducts(
            $this->ownerId,
            $range['start'] ?? null,
            $range['end'] ?? null,
            5
        );

        if (empty($items)) {
            return "Belum ada produk aktif untuk dianalisis.";
        }

        $periodLabel = $range !== null ? " ({$range['label']})" : '';
        $rows = [];
        foreach ($items as $p) {
            $sold = (int)$p['total_sold'];
            $rows[] = [
                ucwords($p['product_name']),
                $sold === 0 ? 'Belum terjual' : number_format($sold, 0, ',', '.'),
                number_format($p['stock'], 0, ',', '.'),
            ];
        }
        return "**Produk Paling Jarang Terjual{$periodLabel}**\n\n"
            . $this->markdownTable(['Nama Barang', 'Terjual', 'Stok'], $rows);
    }

    private function handleTransactionDetail($q): string {
        if ($this->userRole === 'pelanggan') {
            return "Riwayat transaksi internal hanya dapat diakses oleh Admin dan Kasir.";
        }

        $id = $this->extractTransactionId($q);
        if ($id === null) {
            return "Sebutkan nomor nota yang ingin dilihat, misalnya **detail transaksi #TRX-3**.";
        }

        $trx = $this->dataService->getTransactionDetailScoped(
            $this->ownerId,
            $id,
            $this->userRole === 'kasir' ? (int)$this->userId : null
        );

        if (!$trx) {
            return "Transaksi **#TRX-{$id}** tidak ditemukan pada data yang dapat Anda akses.";
        }

        $time = date('d F Y, H:i', strtotime($trx['transaction_date']));
        $lines = ["**Detail Transaksi #TRX-{$trx['id']}**", "Tanggal: {$time}"];
        if (!empty($trx['cashier_name'])) {
            $lines[] = "Kasir: {$trx['cashier_name']}";
        }
        $lines[] = "Total: **Rp " . number_format($trx['total_amount'], 0, ',', '.') . "**";

        if (!empty($trx['items'])) {
            $itemRows = [];
            foreach ($trx['items'] as $item) {
                $itemRows[] = [
                    ucwords($item['product_name']),
                    number_format($item['quantity'], 0, ',', '.'),
                    'Rp ' . number_format($item['price'], 0, ',', '.'),
                    'Rp ' . number_format($item['subtotal'], 0, ',', '.'),
                ];
            }
            $lines[] = "";
            $lines[] = $this->markdownTable(['Item', 'Qty', 'Harga', 'Subtotal'], $itemRows);
        }

        return implode("\n", $lines);
    }

    private function handleTransactionCountRange($q): string {
        if ($this->userRole === 'pelanggan') return "Data transaksi internal toko bersifat rahasia.";

        $range = $this->dateRange($q);
        if ($range === null) return $this->handleTransactionCount();

        $cashierId = $this->userRole === 'kasir' ? (int)$this->userId : null;
        $summary = $this->dataService->getSalesSummary($this->ownerId, $range['start'], $range['end'], $cashierId);
        $scope = $cashierId !== null ? ' oleh Anda' : '';

        return "Pada **{$range['label']}** tercatat **{$summary['transactions']} transaksi**{$scope} dengan nilai **Rp " . number_format($summary['total_sales'], 0, ',', '.') . "**.";
    }

    private function handlePeriodSales($q): string {
        if ($this->userRole === 'pelanggan') return "Data keuangan toko bersifat internal.";
        if ($this->userRole === 'kasir') return "Laporan omzet dan penjualan toko hanya dapat diakses oleh Admin.";

        $range = $this->dateRange($q);
        if ($range === null) return $this->handleSalesFallback();

        $summary = $this->dataService->getSalesSummary($this->ownerId, $range['start'], $range['end']);
        $best = $this->dataService->getBestSellingProductsRange($this->ownerId, $range['start'], $range['end'], 1);

        $lines = ["**Laporan Penjualan {$range['label']}**"];
        $lines[] = "Transaksi: **{$summary['transactions']}**";
        $lines[] = "Unit terjual: **{$summary['units']} unit**";
        $lines[] = "Omzet: **Rp " . number_format($summary['total_sales'], 0, ',', '.') . "**";
        $lines[] = "Rata-rata per transaksi: **Rp " . number_format($summary['avg_basket'], 0, ',', '.') . "**";

        if (!empty($best)) {
            $lines[] = "Produk terlaris: **" . ucwords($best[0]['product_name']) . "** ({$best[0]['total_sold']} unit)";
        }

        return implode("\n", $lines);
    }

    private function handleSalesFallback(): string {
        return "Sebutkan periodenya agar laporan lebih akurat, misalnya **hari ini**, **minggu ini**, **bulan ini**, atau **7 hari terakhir**.";
    }

    private function handleCashierPerformance($q): string {
        if ($this->userRole === 'pelanggan') return "Data performa kasir bersifat internal.";
        if ($this->userRole === 'kasir') {
            $own = $this->dataService->getSalesSummary($this->ownerId, null, null, (int)$this->userId);
            return "Performa Anda: **{$own['transactions']} transaksi** dengan nilai **Rp " . number_format($own['total_sales'], 0, ',', '.') . "**. Rekap seluruh kasir hanya dapat diakses Admin.";
        }

        $range = $this->dateRange($q);
        $rows = $this->dataService->getCashierPerformance(
            $this->ownerId,
            $range['start'] ?? null,
            $range['end'] ?? null
        );

        if (empty($rows)) {
            return "Belum ada transaksi kasir yang tercatat pada periode tersebut.";
        }

        $periodLabel = $range !== null ? " ({$range['label']})" : '';
        $tableRows = [];
        $totalTrx = 0;
        $totalSales = 0;
        foreach ($rows as $row) {
            $trx = (int)$row['total_transactions'];
            $value = (float)$row['total_sales'];
            $totalTrx += $trx;
            $totalSales += $value;
            $tableRows[] = [
                $this->safeName($row['nama_lengkap']),
                number_format($trx, 0, ',', '.'),
                'Rp ' . number_format($value, 0, ',', '.'),
            ];
        }
        return "**Performa Kasir{$periodLabel}**\n\n"
            . $this->markdownTable(['Nama Kasir', 'Transaksi', 'Total Omzet'], $tableRows)
            . "\n\nTotal **" . number_format($totalTrx, 0, ',', '.') . " transaksi** dengan omzet **Rp " . number_format($totalSales, 0, ',', '.') . "**.";
    }

    private function handleInventorySummary(): string {
        if ($this->userRole === 'pelanggan') {
            return "Ringkasan inventaris internal hanya dapat diakses oleh Admin dan Kasir.";
        }

        $s = $this->dataService->getInventorySummary($this->ownerId);
        if ($s['total_products'] === 0) {
            return "Inventaris toko masih kosong (belum ada produk aktif).";
        }

        $units = number_format($s['total_units'], 0, ',', '.');
        $value = number_format($s['inventory_value'], 0, ',', '.');

        $lines = ["**Ringkasan Inventaris**"];
        $lines[] = "Varian produk aktif: **{$s['total_products']}**";
        $lines[] = "Total unit stok: **{$units} unit**";
        $lines[] = "Stok menipis (1–10 unit): **{$s['low_stock']} produk**";
        $lines[] = "Stok habis: **{$s['out_of_stock']} produk**";
        $lines[] = "Nilai stok (harga jual × stok): **Rp {$value}**";

        return implode("\n", $lines);
    }

    private function handleUnsupportedOperational($q): string {
        if ($this->matchesAny($q, ['riwayat stok', 'histori stok', 'history stok', 'pergerakan stok', 'mutasi stok', 'stok masuk', 'stok keluar', 'barang masuk', 'barang keluar', 'kartu stok'])) {
            return "Acounting belum memiliki **riwayat pergerakan stok** (stok masuk/keluar per tanggal). Sistem hanya menyimpan **stok terkini** dan memotong stok otomatis saat transaksi kasir berhasil.";
        }
        if ($this->matchesAny($q, ['metode pembayaran', 'payment method', 'jenis pembayaran'])) {
            return "Sistem belum menyimpan **metode pembayaran** per transaksi. Kasir mencatat nominal uang tunai yang diterima dan kembalian; tidak ada kolom tunai/QRIS/debit pada nota.";
        }
        if ($this->matchesAny($q, ['diskon', 'potongan harga', 'voucher', 'kupon'])) {
            return "Fitur **diskon/voucher otomatis** belum tersedia. Harga yang dipakai adalah harga jual pada Manajemen Barang; harga promo dapat diubah langsung di menu tersebut.";
        }
        if ($this->matchesAny($q, ['pajak', 'ppn'])) {
            return "Acounting belum menerapkan **perhitungan pajak/PPN** pada transaksi. Total nota adalah jumlah subtotal item tanpa pajak tambahan.";
        }
        if ($this->matchesAny($q, ['export data', 'ekspor data', 'import data', 'impor data', 'backup data', 'restore data', 'laporan pdf', 'cetak laporan'])) {
            return "Fitur **ekspor/impor data dan cetak laporan PDF** belum tersedia. Data penjualan dapat dilihat melalui menu **Database Penjualan**, sedangkan struk dapat dicetak dari detail transaksi.";
        }
        return "Data **pelanggan dan riwayat belanja per pelanggan** belum dicatat secara khusus di Acounting. Sistem mencatat transaksi kasir tanpa menghubungkannya ke akun pelanggan.";
    }

    private function handleProductDetail($q): string {
        $term = $this->extractProductTerm($q);
        if ($term === '') {
            return "Sebutkan nama produk yang ingin dilihat detailnya, misalnya **detail produk kopi**.";
        }

        $p = $this->dataService->getProductDetail($this->ownerId, $term);
        if (!$p) {
            return "Produk **\"{$term}\"** tidak ditemukan pada katalog aktif toko.";
        }

        $name = ucwords($p['product_name']);
        $price = number_format($p['price'], 0, ',', '.');
        $status = ((int)$p['stock'] === 0) ? 'Habis' : (((int)$p['stock'] <= 10) ? 'Menipis' : 'Tersedia');

        return "**{$name}**\n- ID produk: #{$p['id']}\n- Harga: **Rp {$price}**\n- Stok: **{$p['stock']} unit**\n- Status: **{$status}**";
    }

    private function handleRevenueToday(): string {
        if ($this->userRole === 'pelanggan') {
            return "Data keuangan toko bersifat internal dan rahasia.";
        }
        if ($this->userRole === 'kasir') {
            return "Laporan total omzet toko hanya dapat diakses oleh Admin.";
        }

        $sales = $this->dataService->getTodaySales($this->ownerId);
        $totalFmt = number_format($sales['total'], 0, ',', '.');

        if ($sales['count'] == 0) {
            return "Omzet hari ini (" . date('d F Y') . ") belum ada transaksi tercatat (**Rp 0**).";
        }

        return "Omzet hari ini (" . date('d F Y') . ") adalah **Rp {$totalFmt}** dari **{$sales['count']} transaksi**.";
    }

    private function handleRevenueMonth(): string {
        if ($this->userRole === 'pelanggan') return "Data keuangan toko bersifat internal.";
        if ($this->userRole === 'kasir') return "Laporan omzet bulanan hanya dapat diakses oleh Admin.";

        $sales = $this->dataService->getMonthSales($this->ownerId);
        $monthName = $this->monthName((int)date('m')) . ' ' . date('Y');
        $totalFmt = number_format($sales['total'], 0, ',', '.');

        return "Omzet bulan **{$monthName}** tercatat **Rp {$totalFmt}** dari **{$sales['count']} transaksi**.";
    }

    private function handleRevenueTotal(): string {
        if ($this->userRole === 'pelanggan') return "Data keuangan toko bersifat internal.";
        if ($this->userRole === 'kasir') return "Total pendapatan toko hanya dapat diakses oleh Admin.";

        $sales = $this->dataService->getTotalSales($this->ownerId);
        $totalFmt = number_format($sales['total'], 0, ',', '.');

        return "Total pendapatan keseluruhan toko adalah **Rp {$totalFmt}** dari **{$sales['count']} transaksi**.";
    }

    private function handleSoldProductsList(): string {
        if ($this->userRole === 'pelanggan') {
            return "Data rincian penjualan barang toko bersifat internal dan hanya dapat diakses oleh Admin atau Kasir.";
        }

        $isCashierScope = ($this->userRole === 'kasir');
        if ($isCashierScope) {
            $soldList = $this->dataService->getBestSellingProductsRange($this->ownerId, null, null, 50, (int)$this->userId);
            foreach ($soldList as &$row) {
                $row['total_amount'] = $row['total_revenue'] ?? 0;
            }
            unset($row);
        } else {
            $soldList = $this->dataService->getSoldProductsList($this->ownerId);
        }

        if (empty($soldList)) {
            return $isCashierScope
                ? "Belum ada barang yang tercatat terjual dari transaksi Anda."
                : "Saat ini **belum ada barang yang selesai ditransaksikan** (belum ada transaksi penjualan yang tercatat).";
        }

        $totalUnits = 0;
        $totalOmzet = 0;
        $rows = [];
        foreach ($soldList as $item) {
            $name = ucwords($item['product_name']);
            $qty = (int)$item['total_sold'];
            $subtotal = (float)$item['total_amount'];
            $totalUnits += $qty;
            $totalOmzet += $subtotal;
            $rows[] = [
                $name,
                number_format($qty, 0, ',', '.'),
                'Rp ' . number_format($subtotal, 0, ',', '.'),
            ];
        }

        $variantCount = count($soldList);
        $totalLabel = $isCashierScope ? 'Total nilai transaksi Anda' : 'Total Omzet';
        $intro = $isCashierScope
            ? "Berikut barang yang terjual dari transaksi Anda:"
            : "Berikut nama barang dari transaksi yang telah selesai terjual:";

        return $intro . "\n\n"
            . $this->markdownTable(['Nama Barang', 'Terjual', 'Subtotal'], $rows)
            . "\n\nTotal **{$variantCount} jenis barang**, **" . number_format($totalUnits, 0, ',', '.') . " unit terjual** ({$totalLabel}: **Rp " . number_format($totalOmzet, 0, ',', '.') . "**).";
    }

    private function handleTransactionList($q = ''): string {
        if ($this->userRole === 'pelanggan') {
            return "Data riwayat transaksi toko bersifat internal dan hanya dapat diakses oleh Admin atau Kasir.";
        }

        $filters = [];
        $cashierLabel = '';
        $periodLabel = '';
        $range = $this->dateRange($q);
        if ($range !== null) {
            $filters['start_date'] = $range['start'];
            $filters['end_date'] = $range['end'];
            $periodLabel = " pada {$range['label']}";
        }
        if ($this->userRole === 'kasir') {
            $filters['cashier_id'] = (int)$this->userId;
            $cashierLabel = ' (transaksi Anda)';
        } else {
            $cashierTerm = AIConversationService::extractCashierTerm(mb_strtolower((string)$q, 'UTF-8'));
            if ($cashierTerm !== null) {
                $filters['cashier_name'] = $cashierTerm;
                $cashierLabel = ' untuk kasir "' . $cashierTerm . '"';
            }
        }

        $transactions = $this->dataService->getTransactionsFiltered($this->ownerId, $filters, 20);
        if (empty($transactions)) {
            return "Saat ini **belum ada transaksi yang tercatat**{$periodLabel}{$cashierLabel} di toko Anda.";
        }

        $tableRows = [];
        foreach ($transactions as $t) {
            $time = $this->formatDateTime($t['transaction_date']);
            $total = 'Rp ' . number_format($t['total_amount'], 0, ',', '.');
            $cashier = !empty($t['cashier_name']) ? $this->safeName($t['cashier_name']) : '-';
            $qty = (int)($t['total_qty'] ?? 0);
            $tableRows[] = ['#TRX-' . $t['id'], $time, $cashier, number_format($qty, 0, ',', '.') . ' unit', $total];
        }

        $summary = $this->dataService->getSalesSummary(
            $this->ownerId,
            $range['start'] ?? null,
            $range['end'] ?? null,
            $this->userRole === 'kasir' ? (int)$this->userId : null
        );

        $shown = count($transactions);
        $text = "**Riwayat Transaksi{$periodLabel}{$cashierLabel}**\n\n"
            . $this->markdownTable(['Nota', 'Tanggal', 'Kasir', 'Qty', 'Total'], $tableRows);

        if ($summary['transactions'] > $shown) {
            $text .= "\n\nMenampilkan **{$shown} transaksi terbaru** dari total **" . number_format($summary['transactions'], 0, ',', '.') . " transaksi**.";
        }

        $text .= "\nTotal: **" . number_format($summary['transactions'], 0, ',', '.') . " transaksi**, **" . number_format($summary['units'], 0, ',', '.') . " unit**, omzet **Rp " . number_format($summary['total_sales'], 0, ',', '.') . "**.";
        return $text;
    }

    private function handleTransactionCount(): string {
        if ($this->userRole === 'pelanggan') return "Data transaksi internal toko bersifat rahasia.";

        if ($this->userRole === 'kasir') {
            $own = $this->dataService->getSalesSummary($this->ownerId, null, null, (int)$this->userId);
            return "Anda telah mencatat **{$own['transactions']} transaksi** dengan nilai **Rp " . number_format($own['total_sales'], 0, ',', '.') . "**.";
        }

        $sales = $this->dataService->getTotalSales($this->ownerId);
        $totalFmt = number_format($sales['total'], 0, ',', '.');
        return "Toko Anda telah mencatat **{$sales['count']} transaksi** dengan total omzet **Rp {$totalFmt}**.";
    }

    private function handlePerformanceAnalysis(): string {
        if ($this->userRole === 'pelanggan') return "Evaluasi performa toko hanya dapat diakses oleh Admin.";
        if ($this->userRole === 'kasir') return "Ringkasan performa toko dikelola oleh Admin.";

        $overview = $this->dataService->getStockOverview($this->ownerId);
        $today = $this->dataService->getTodaySales($this->ownerId);
        $month = $this->dataService->getMonthSales($this->ownerId);

        $vars = $overview['total_products'] ?? 0;
        $units = number_format($overview['total_units'] ?? 0, 0, ',', '.');
        $todayOmzet = number_format($today['total'], 0, ',', '.');
        $monthOmzet = number_format($month['total'], 0, ',', '.');

        return "Ringkasan Performa Toko:\n- **Stok**: {$vars} varian produk ({$units} unit)\n- **Omzet Hari Ini**: Rp {$todayOmzet} ({$today['count']} transaksi)\n- **Omzet Bulan Ini**: Rp {$monthOmzet} ({$month['count']} transaksi)";
    }

    private function handleLastTransaction(): string {
        if ($this->userRole === 'pelanggan') return "Riwayat transaksi toko bersifat internal.";

        if ($this->userRole === 'kasir') {
            $rows = $this->dataService->getTransactionsFiltered($this->ownerId, ['cashier_id' => (int)$this->userId], 1);
            $trx = $rows[0] ?? null;
            if (!$trx) return "Belum ada transaksi yang tercatat untuk akun Anda.";
            $time = date('d F Y, H:i', strtotime($trx['transaction_date']));
            $total = number_format($trx['total_amount'], 0, ',', '.');
            return "Transaksi terakhir Anda (#TRX-{$trx['id']}) tercatat pada **{$time}** senilai **Rp {$total}**.";
        }

        $trx = $this->dataService->getLastTransaction($this->ownerId);
        if (!$trx) {
            return "Belum ada transaksi yang tercatat dalam sistem.";
        }

        $time = date('d F Y, H:i', strtotime($trx['transaction_date']));
        $total = number_format($trx['total_amount'], 0, ',', '.');
        $cashier = !empty($trx['cashier_name']) ? " oleh kasir **{$trx['cashier_name']}**" : '';

        return "Transaksi terakhir (#TRX-{$trx['id']}) tercatat pada **{$time}** senilai **Rp {$total}**{$cashier}.";
    }

    private function handleProductList(): string {
        $products = $this->dataService->getAllProducts($this->ownerId, 25);
        if (empty($products)) {
            return "Katalog barang toko Anda masih kosong.";
        }

        $rows = [];
        $totalUnits = 0;
        foreach ($products as $p) {
            $totalUnits += (int)$p['stock'];
            $rows[] = [
                ucwords($p['product_name']),
                number_format($p['stock'], 0, ',', '.'),
                'Rp ' . number_format($p['price'], 0, ',', '.'),
            ];
        }

        return "**Daftar Produk Aktif** (" . count($products) . " varian)\n\n"
            . $this->markdownTable(['Nama Barang', 'Stok', 'Harga'], $rows)
            . "\n\nTotal stok: **" . number_format($totalUnits, 0, ',', '.') . " unit**.";
    }

    private function handleHowAddProduct(): string {
        return "Cara Menambah Produk Baru:\n1. Buka menu **Manajemen Barang** di sidebar.\n2. Klik tombol **Tambah Barang** (biru di kanan atas).\n3. Isi nama produk, jumlah stok, harga jual, dan foto (opsional).\n4. Klik **Simpan**.";
    }

    private function handleHowPrintReceipt(): string {
        return "Cara Mencetak Struk:\n1. Buka menu **Riwayat Transaksi** di sidebar.\n2. Klik tombol **Detail** pada transaksi yang diinginkan.\n3. Klik tombol **Cetak Struk** di bagian bawah untuk mencetak ke printer kasir atau PDF.";
    }

    private function handleWhyTransactionFailed(): string {
        return "Penyebab umum transaksi gagal:\n1. **Keranjang belanja kosong** belum ada barang yang dipilih.\n2. **Stok tidak mencukupi** kuantitas melebihi sisa stok di database.\n3. **Kuantitas < 1** jumlah barang harus minimal 1.\n4. **Token CSRF kedaluwarsa** muat ulang (*refresh*) halaman kasir.";
    }

    private function handleHowUseCashier(): string {
        return "Panduan Transaksi Kasir:\n1. Buka menu **Transaksi Kasir** di sidebar.\n2. Cari dan klik produk untuk dimasukkan ke keranjang.\n3. Tentukan jumlah pembelian.\n4. Klik **Bayar** / Selesaikan Pembayaran.";
    }

    private function handleHowAddKasir(): string {
        return "Panduan Akun Kasir:\nAkun kasir didaftarkan dengan role **Kasir** dan otomatis terhubung ke toko Admin untuk operasional POS tanpa akses laporan keuangan toko.";
    }

    private function handleUnavailableFeature($rawQ): string {
        return "Fitur **\"{$rawQ}\"** saat ini belum tersedia di Acounting. Sistem saat ini fokus pada kasir POS, stok real-time, cetak struk, dan laporan omzet.";
    }

    private function handleAboutFeatures(): string {
        return "Fitur utama Acounting:\n- **Dashboard**: Ringkasan penjualan, stok, dan grafik tren\n- **Manajemen Barang**: Kelola katalog, harga, dan stok\n- **Transaksi Kasir (POS)**: Penjualan cepat dengan pemotongan stok otomatis\n- **Riwayat Transaksi**: Arsip nota, detail, dan cetak struk\n- **Database Penjualan**: Laporan omzet dan rekap kasir (Admin)\n- **Manajemen Pengguna**: Kelola akun Admin, Kasir, dan Pelanggan (Admin)\n- **AI Assistant Aca**: Jawaban lokal dari database, dengan provider AI opsional\n- **Pengaturan Aca**: Konfigurasi provider, model & API key per provider (Admin)";
    }

    private function handleAboutRole(): string {
        return "Hak Akses Pengguna:\n- **Admin**: Pemilik toko dengan akses penuh (produk, kasir, laporan omzet)\n- **Kasir**: Staff operasional untuk transaksi POS dan cek stok\n- **Pelanggan**: Melihat katalog produk aktif";
    }

    private function handleAboutSecurity(): string {
        return "Keamanan Acounting:\n- Enkripsi password menggunakan bcrypt hashing\n- Proteksi formulir dengan CSRF token\n- Pencegahan SQL Injection dengan PDO prepared statements\n- Pemisahan hak akses berbasis RBAC";
    }

    private function handleWhoAmI(): string {
        if (!$this->userId) return "Anda belum terdeteksi login ke aplikasi Acounting.";
        $roleMap = ['admin' => 'Administrator', 'kasir' => 'Kasir', 'pelanggan' => 'Pelanggan'];
        $roleLabel = $roleMap[$this->userRole] ?? ucfirst((string)$this->userRole);
        $name = trim((string)$this->userName) !== '' ? $this->userName : 'Pengguna';
        return "{$name} adalah {$roleLabel}.";
    }

    private function handleAboutAca(): string {
        return "Saya adalah **AI Assistant** yang terintegrasi dengan aplikasi **Acounting** ini. Saya siap membantu Anda mencari informasi barang, memeriksa transaksi, memahami laporan keuangan, serta membantu penggunaan fitur aplikasi.";
    }

    private function handleGreeting(): string {
        $hour = (int)date('H');
        if ($hour >= 5 && $hour < 11) $waktu = 'pagi';
        elseif ($hour >= 11 && $hour < 15) $waktu = 'siang';
        elseif ($hour >= 15 && $hour < 18) $waktu = 'sore';
        else $waktu = 'malam';

        $name = $this->userName ? ' ' . $this->userName : '';
        return "Selamat {$waktu}{$name}! Ada yang bisa saya bantu hari ini?";
    }

    private function handleSmartFallback($originalQuestion): string {
        $this->fallback = true;
        return "Maaf, Aca belum menemukan data untuk pertanyaan: **\"{$originalQuestion}\"**. Anda bisa menanyakan info varian produk, stok barang, harga, omzet penjualan, atau manajemen pengguna.";
    }

    // ---- UTILITIES ----

    /**
     * Susun tabel Markdown yang aman (pipe di dalam sel di-escape).
     */
    private function markdownTable(array $headers, array $rows): string {
        $escape = static function ($value) {
            $text = (string)$value;
            $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
            $text = str_replace('|', '\\|', $text);
            return trim($text);
        };

        $out = '| ' . implode(' | ', array_map($escape, $headers)) . ' |';
        $out .= "\n|" . implode('|', array_fill(0, count($headers), '---')) . '|';
        foreach ($rows as $row) {
            $cells = array_map($escape, array_values($row));
            // Samakan jumlah sel dengan header.
            while (count($cells) < count($headers)) {
                $cells[] = '';
            }
            $cells = array_slice($cells, 0, count($headers));
            $out .= "\n| " . implode(' | ', $cells) . ' |';
        }
        return $out;
    }

    private function formatDateTime($value): string {
        if (empty($value)) {
            return '-';
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return (string)$value;
        }
        return date('d', $ts) . ' ' . $this->monthName((int)date('n', $ts)) . ' ' . date('Y, H:i', $ts);
    }

    private function safeName($name): string {
        $name = trim((string)$name);
        return $name !== '' ? $name : '-';
    }

    private function statusLabel($status): string {
        $map = ['sent' => 'Terkirim', 'failed' => 'Gagal', 'simulated' => 'Simulasi'];
        return $map[(string)$status] ?? ucfirst((string)$status);
    }

    private function maskPhone($phone): string {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        $length = strlen($digits);
        if ($length === 0) {
            return '-';
        }
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . substr($digits, -4);
    }

    private function dateRange($q): ?array {
        $q = mb_strtolower(trim((string)$q), 'UTF-8');
        return AIConversationService::detectDateRange($q);
    }

    private function extractTransactionId($q): ?int {
        if (preg_match('/#?\s*trx[-\s]?(\d{1,7})/i', $q, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/\b(?:transaksi|nota)\s*(?:nomor|no\.?|id)?\s*#?(\d{1,7})\b/i', $q, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/#(\d{1,7})/', $q, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function extractProductTerm($q): string {
        $term = mb_strtolower((string)$q, 'UTF-8');
        $stopWords = [
            'detail', 'rincian', 'info', 'informasi', 'data', 'spesifikasi', 'produk', 'barang',
            'tolong', 'lihat', 'tampilkan', 'cek', 'dong', 'ya', 'yang', 'itu', 'tersebut'
        ];
        foreach ($stopWords as $sw) {
            $term = preg_replace('/\b' . preg_quote($sw, '/') . '\b/iu', ' ', $term);
        }
        $term = trim(preg_replace('/\s+/u', ' ', $term));
        return trim($term, " ?!.,");
    }

    private function matchesAny($haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) return true;
        }
        return false;
    }

    private function contains($haystack, $needle): bool {
        return mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
    }

    private function monthName($m): string {
        $names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $names[$m] ?? '';
    }
}
