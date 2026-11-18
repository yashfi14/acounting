<?php

require_once __DIR__ . '/AIProviderInterface.php';
require_once __DIR__ . '/UserContextService.php';
require_once __DIR__ . '/AppContextService.php';
require_once __DIR__ . '/ApplicationKnowledgeService.php';

/**
 * LocalAIProvider — Mesin AI Cerdas Terintegrasi Lokal untuk Acounting.
 * Memberikan jawaban instan, akurat, mematuhi RBAC, memahami percakapan multi-turn,
 * dan terhubung langsung ke basis data operasional secara real-time.
 */
class LocalAIProvider implements AIProviderInterface {

    private UserContextService $userContext;
    private AppContextService $appContext;

    public function __construct(UserContextService $userContext, AppContextService $appContext)
    {
        $this->userContext = $userContext;
        $this->appContext  = $appContext;
    }

    /**
     * Normalisasi string input (pembersihan karakter & penggantian singkatan umum).
     */
    private function normalize(string $q): string
    {
        $q = mb_strtolower(trim($q));
        $replacements = [
            '/\bgmn\b/' => 'bagaimana',
            '/\bbrapa\b/' => 'berapa',
            '/\bgk\b|\bnggak\b|\bga\b|\bgak\b/' => 'tidak',
            '/\budh\b|\bsdh\b/' => 'sudah',
            '/\bblm\b/' => 'belum',
            '/\btp\b/' => 'tapi',
            '/\bdr\b/' => 'dari',
            '/\byg\b/' => 'yang',
            '/\bdg\b|\bdgn\b/' => 'dengan',
            '/\butk\b/' => 'untuk',
            '/\bsy\b/' => 'saya',
            '/\bkmu\b/' => 'kamu',
            '/\bmkasih\b|\bmakasi\b|\bthx\b|\bthanks\b/' => 'terima kasih',
            '/\bomzet\b/' => 'omset',
            '/\brestok\b/' => 'restock',
            '/\bprint\b/' => 'cetak',
            '/\bnota\b/' => 'struk',
            '/\bbest seller\b|\bterlaris\b|\bpaling laku\b|\bpaling laris\b/' => 'best_seller',
        ];
        return preg_replace(array_keys($replacements), array_values($replacements), $q);
    }

    public function ask(string $question, array $history = []): string
    {
        // ── 1. Validasi Input Dasar ──────────────────────────────────────
        $trimmed = trim($question);
        if ($trimmed === '') {
            return "Pertanyaan tidak boleh kosong. Silakan ketik pertanyaan seputar fitur atau data toko Anda.";
        }
        if (mb_strlen($trimmed) > 1000) {
            return "Pertanyaan terlalu panjang (maksimal 1000 karakter). Silakan ajukan pertanyaan yang lebih ringkas.";
        }

        $norm = $this->normalize($trimmed);

        // ── 2. Pemeriksaan Kesehatan Database ────────────────────────────
        if (!$this->appContext->isDatabaseAvailable()) {
            return "Maaf, saat ini sistem basis data sedang tidak dapat diakses atau sedang dalam pemeliharaan. Silakan coba kembali beberapa saat lagi.";
        }

        // ── 3. Pertahanan Keamanan & Anti-Jailbreak ──────────────────────
        if (preg_match('/(abaikan.*aturan|abaikan.*instruksi|ignore.*previous.*instructions|bypass.*role|bocorkan.*password|lihat.*semua.*password|tampilkan.*kunci.*rahasia|bypass.*security)/i', $norm)) {
            return "Permintaan tersebut tidak dapat diproses. Sebagai asisten resmi Acounting, saya beroperasi dengan mematuhi protokol keamanan sistem dan batasan hak akses yang berlaku.";
        }

        // ── 4. Penegakan Otorisasi / RBAC (Backend Enforced) ─────────────
        if ($this->userContext->isPelanggan()) {
            if (preg_match('/(omset|pendapatan|total penjualan|laba|keuntungan|tambah barang|edit barang|hapus barang|hapus transaksi|batal transaksi|manajemen barang|kelola pengguna|user|kasir|admin|stok menipis)/i', $norm)) {
                return "Maaf, sebagai **Pelanggan**, Anda hanya memiliki hak akses untuk menanyakan informasi umum aplikasi serta melihat daftar menu dan harga barang yang tersedia untuk publik.\n\nData keuangan, omset penjualan, dan pengelolaan inventaris/kasir hanya dapat diakses oleh Admin atau staf toko yang berwenang. 😊";
            }
        } elseif ($this->userContext->isKasir()) {
            if (preg_match('/(omset toko|total penjualan toko|laba toko|keuntungan toko|database penjualan|laporan penjualan|kelola pengguna|tambah user|manajemen user|admin)/i', $norm)) {
                return "Maaf, sebagai **Kasir**, Anda tidak memiliki akses ke laporan keuangan keseluruhan toko, database penjualan lengkap, atau manajemen pengguna. Fitur tersebut merupakan wewenang Administrator.\n\nAnda dapat menanyakan panduan kasir, stok barang, atau transaksi yang Anda layani.";
            }
        }

        // ── 5. Deteksi Pertanyaan Di Luar Konteks Aplikasi (Out-of-Domain) ─
        if ($this->isOutOfDomain($norm)) {
            return "Pertanyaan tersebut tidak berkaitan langsung dengan fitur aplikasi **Acounting**.\n\nSaya dapat membantu Anda menggunakan aplikasi kasir, memeriksa data produk dan stok, memahami transaksi POS, atau menggunakan fitur lain yang tersedia di akun Anda. 😊";
        }

        // ── 6. Deteksi Fitur yang Belum Tersedia (Honest Limitations) ─────
        $unsupported = $this->checkUnsupportedFeatures($norm);
        if ($unsupported !== null) {
            return $unsupported;
        }

        // ── 7. Penanganan Pertanyaan Lanjutan (Multi-Turn Context) ────────
        $followUpAnswer = $this->resolveFollowUp($norm, $history);
        if ($followUpAnswer !== null) {
            return $followUpAnswer;
        }

        // ── 8. Sapaan & Kesopanan (Conversational) ────────────────────────
        if (preg_match('/(terima kasih|makasih|thank you|matur nuwun|nuhun)/i', $norm)) {
            return "Sama-sama! Senang sekali bisa membantu Anda. 😊\n\nJika ada pertanyaan lain seputar penggunaan aplikasi Acounting, stok barang, atau transaksi kasir, silakan tanyakan lagi ya!";
        }
        if (preg_match('/(kamu pintar|hebat|keren|mantap|top|bagus|cerdas|smart)/i', $norm)) {
            return "Terima kasih banyak atas apresiasinya! 🥰 Aca selalu siap membantu operasional toko Anda agar semakin rapi dan lancar. Ada hal lain yang ingin Anda ketahui?";
        }
        if (preg_match('/^(halo|hai|hello|hi|pagi|siang|sore|malam|assalamualaikum|selamat)/i', $norm) && mb_strlen($norm) < 35) {
            $nama = htmlspecialchars($this->userContext->getUserName());
            return "Halo {$nama}! 👋 Saya **Aca**, asisten AI cerdas aplikasi **Acounting**.\n\nAda yang bisa saya bantu seputar operasional kasir, stok barang, atau panduan penggunaan fitur toko Anda hari ini?";
        }
        if (preg_match('/(siapa kamu|siapa aca|tentang aca|kamu siapa)/i', $norm)) {
            return ApplicationKnowledgeService::getAppOverview() . "\n\nSaya adalah **Aca** 🤖, asisten AI resmi Acounting yang bertugas membantu Anda menjalankan sistem ini dengan mudah dan akurat.";
        }

        // ── 9. Pertanyaan Berbasis Data Real-Time ─────────────────────────

        // 9a. Penjualan / Omset Hari Ini
        if (preg_match('/(omset.*hari ini|penjualan.*hari ini|performa.*hari ini|transaksi.*hari ini|berapa.*hari ini|rekap.*hari ini)/i', $norm)) {
            return $this->handleTodaySalesQuery();
        }

        // 9b. Penjualan / Transaksi Bulan Ini
        if (preg_match('/(bulan ini|penjualan.*bulan ini|omset.*bulan ini|transaksi.*bulan ini)/i', $norm)) {
            return $this->handleMonthlySalesQuery();
        }

        // 9c. Transaksi Terakhir
        if (preg_match('/(transaksi.*terakhir|transaksi.*paling baru|nota.*terakhir|penjualan.*terakhir)/i', $norm)) {
            return $this->handleLastTransactionQuery();
        }

        // 9d. Produk Terlaris / Best Seller
        if (preg_match('/(best_seller|produk.*laku|barang.*laku|paling.*banyak.*dibeli|produk.*favorit|menu.*favorit|paling.*banyak.*terjual)/i', $norm)) {
            return $this->handleBestSellerQuery();
        }

        // 9e. Peringatan Stok Menipis / Habis / Kritis
        if (preg_match('/(stok.*(menipis|habis|sedikit|kritis|rendah|tinggal sedikit)|barang.*(menipis|habis|kritis)|perlu.*restock|kapan.*restock|produk.*habis)/i', $norm)) {
            return $this->handleStockAlertsQuery();
        }

        // 9f. Jumlah Produk & Total Stok Fisik
        if (preg_match('/(berapa.*(banyak|jumlah|total).*(produk|barang|varian)|total.*stok|stok.*total)/i', $norm)) {
            return $this->handleProductCountQuery();
        }

        // 9g. Total Omset Akumulasi Toko (Khusus Admin)
        if (preg_match('/(total.*(omset|penjualan|pemasukan|pendapatan|laba|keuntungan)|berapa.*total.*omset)/i', $norm)) {
            return $this->handleOverallSalesQuery();
        }

        // 9h. Simulasi Kalkulator Belanja Kasir
        $calc = $this->handleOrderCalculation($norm);
        if ($calc !== null) {
            return $calc;
        }

        // 9i. Pencarian Spesifik 1 Produk (Cek Harga / Ketersediaan)
        $prodSearch = $this->handleProductSearchQuery($norm);
        if ($prodSearch !== null) {
            return $prodSearch;
        }

        // 9j. Daftar Tim Kasir (Khusus Admin)
        if (preg_match('/(siapa.*kasir|daftar.*kasir|kasir.*terdaftar|kasir.*saya|staf.*kasir)/i', $norm)) {
            return $this->handleCashierListQuery();
        }

        // ── 10. Panduan Fitur & Alur Penggunaan Aplikasi ───────────────────
        $guideAnswer = $this->handleFeatureGuideQuery($norm);
        if ($guideAnswer !== null) {
            return $guideAnswer;
        }

        // ── 11. Analisis Bisnis Berdasarkan Data Riil ──────────────────────
        if (preg_match('/(saran.*(bisnis|toko|penjualan|omset)|strategi|tips.*(bisnis|jualan|omset|umkm)|tingkatkan.*omset|rekomendasi.*bisnis|ide.*promosi)/i', $norm)) {
            return $this->handleBusinessAdvice();
        }

        // ── 12. Troubleshooting / Kendala Penggunaan ───────────────────────
        $troubleshoot = $this->handleTroubleshootingQuery($norm);
        if ($troubleshoot !== null) {
            return $troubleshoot;
        }

        // ── 13. Default Klarifikasi Ramah ──────────────────────────────────
        return "Pertanyaan Anda belum cukup spesifik mengenai fitur atau data Acounting. 🤔\n\n"
             . "💡 **Berikut beberapa hal yang dapat saya bantu secara langsung**:\n"
             . "- 🛒 *\"Bagaimana cara melakukan transaksi di kasir?\"*\n"
             . "- 📦 *\"Bagaimana cara menambahkan produk baru?\"*\n"
             . "- 📊 *\"Berapa total penjualan dan transaksi hari ini?\"*\n"
             . "- ⚠️ *\"Produk apa saja yang stoknya hampir habis?\"*\n"
             . "- 🏆 *\"Produk apa yang paling banyak terjual?\"*\n"
             . "- 🧾 *\"Bagaimana cara mencetak struk nota belanja?\"*\n"
             . "- 🔄 *\"Bagaimana cara membatalkan transaksi dan mengembalikan stok?\"*\n\n"
             . "Silakan ajukan pertanyaan seputar fitur di atas!";
    }

    /**
     * Memeriksa apakah pertanyaan berada di luar domain aplikasi (Out-of-Domain).
     */
    private function isOutOfDomain(string $norm): bool
    {
        $patterns = [
            '/(piala dunia|world cup|sepak bola|champions league|liga inggris|ronaldo|messi)/i',
            '/(presiden|pemilu|politik|pilpres|dpr|menteri)/i',
            '/(cuaca.*hari ini|prakiraan cuaca|hujan.*besok|gempa)/i',
            '/(resep masakan|cara membuat kue|bumbu rendang|film bioskop|lirik lagu|chord)/i',
            '/(jarak.*bumi.*ke.*bulan|siapa penemu|sejarah kerajaan)/i'
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $norm)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Memeriksa dan merespon pertanyaan tentang fitur yang belum didukung secara jujur.
     */
    private function checkUnsupportedFeatures(string $norm): ?string
    {
        $unsupportedList = ApplicationKnowledgeService::getUnsupportedFeatures();
        foreach ($unsupportedList as $key => $feat) {
            foreach ($feat['keywords'] as $kw) {
                if (mb_strpos($norm, $kw) !== false) {
                    return $feat['explanation'] . "\n\nSaya dapat membantu Anda mengoperasikan fitur-fitur yang sudah tersedia di aplikasi Acounting.";
                }
            }
        }
        return null;
    }

    /**
     * Menyelesaikan pertanyaan lanjutan (Multi-turn Context Resolver).
     */
    private function resolveFollowUp(string $norm, array $history): ?string
    {
        if (empty($history)) {
            return null;
        }

        // Ambil konteks pertanyaan terakhir user
        $lastUserQuery = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['role'] === 'user') {
                $lastUserQuery = mb_strtolower($history[$i]['content']);
                break;
            }
        }

        // Kasus 1: Follow-up tentang filter stok di bawah X (e.g. "kalau yang di bawah 5?", "stok di bawah 10?")
        if (preg_match('/(?:kalau|jika|sedangkan|bagaimana)?\s*(?:yang)?\s*stok(?:nya)?\s*(?:di)?\s*bawah\s*(\d+)/i', $norm, $m) ||
            preg_match('/(?:di)?\s*bawah\s*(\d+)\s*(?:unit|stok|produk|item|barang)?/i', $norm, $m)) {
            $threshold = (int)end($m);
            if ($threshold > 0) {
                return $this->handleProductsUnderStockQuery($threshold);
            }
        }

        // Kasus 2: Follow-up setelah bertanya tentang produk/stok, tapi user hanya bertanya "kalau yang habis?"
        if (preg_match('/(kalau|yang)\s*(?:produk)?\s*(habis|kosong)/i', $norm)) {
            return $this->handleStockAlertsQuery(true); // hanya stok 0
        }

        // Kasus 3: Follow-up setelah bertanya penjualan hari ini, lalu bertanya "kalau kemarin?"
        if (preg_match('/(kalau|bagaimana)\s*(?:penjualan|omset|transaksi)?\s*(kemarin)/i', $norm)) {
            if (!$this->userContext->canViewFinancialReports()) {
                return "Maaf, data riwayat omset hanya dapat diakses oleh Administrator.";
            }
            return $this->handleYesterdaySalesQuery();
        }

        return null;
    }

    // ── Handlers Data Query ──────────────────────────────────────────────

    private function handleTodaySalesQuery(): string
    {
        $sales = $this->appContext->getTodaySales();
        if ($sales === null) {
            return "Maaf, Anda tidak memiliki izin untuk melihat laporan keuangan toko.";
        }

        $tglIndo = date('d F Y');
        if (!$sales['has_transactions']) {
            return "📊 **Laporan Penjualan Hari Ini ({$tglIndo})**:\n\n"
                 . "- **Total Omset**: Rp 0\n"
                 . "- **Jumlah Transaksi**: 0 nota tercatat\n\n"
                 . "Saat ini belum ada transaksi kasir yang diselesaikan untuk hari ini. Transaksi yang berhasil dicatat di menu Kasir akan otomatis muncul di sini secara real-time.";
        }

        $omset = number_format($sales['today_omset'], 0, ',', '.');
        $avg   = number_format($sales['avg_basket'], 0, ',', '.');

        return "📊 **Laporan Penjualan Hari Ini ({$tglIndo})**:\n\n"
             . "- **Total Omset**: **Rp {$omset}**\n"
             . "- **Jumlah Transaksi**: **{$sales['today_trx']} nota** selesai\n"
             . "- **Rata-rata Belanja (Basket Size)**: Rp {$avg} per transaksi\n\n"
             . "Data di atas dihitung langsung dari basis data transaksi toko hari ini.";
    }

    private function handleYesterdaySalesQuery(): string
    {
        try {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $db = new Database();
            $uid = $this->userContext->getBusinessId();

            $db->query("SELECT COUNT(id) as y_trx, COALESCE(SUM(total_amount), 0) as y_omset 
                        FROM transactions 
                        WHERE user_id = :uid AND DATE(transaction_date) = :yday");
            $db->bind('uid', $uid);
            $db->bind('yday', $yesterday);
            $res = $db->single();

            $trx   = (int)($res['y_trx'] ?? 0);
            $omset = (float)($res['y_omset'] ?? 0);
            $tglIndo = date('d F Y', strtotime($yesterday));

            if ($trx === 0) {
                return "📊 **Penjualan Kemarin ({$tglIndo})**:\n\nTidak ada transaksi penjualan yang tercatat pada hari kemarin (Total: Rp 0, 0 transaksi).";
            }

            $omsetFmt = number_format($omset, 0, ',', '.');
            return "📊 **Penjualan Kemarin ({$tglIndo})**:\n\n"
                 . "- **Total Omset**: Rp {$omsetFmt}\n"
                 . "- **Jumlah Transaksi**: {$trx} nota transaksi selesai.";
        } catch (Throwable $e) {
            return "Maaf, terjadi kendala saat memuat data penjualan kemarin.";
        }
    }

    private function handleMonthlySalesQuery(): string
    {
        $sales = $this->appContext->getMonthlySales();
        if ($sales === null) {
            return "Maaf, Anda tidak memiliki izin untuk melihat laporan keuangan bulanan.";
        }

        if (!$sales['has_transactions']) {
            return "📅 **Penjualan Bulan Ini ({$sales['month_name']})**:\n\nBelum ada transaksi penjualan yang tercatat untuk bulan ini (Total: Rp 0, 0 transaksi).";
        }

        $omset = number_format($sales['month_omset'], 0, ',', '.');
        return "📅 **Penjualan Bulan Ini ({$sales['month_name']})**:\n\n"
             . "- **Total Omset**: **Rp {$omset}**\n"
             . "- **Jumlah Transaksi**: **{$sales['month_trx']} nota**\n\n"
             . "Data ini mencerminkan transaksi aktif toko Anda selama bulan berjalan.";
    }

    private function handleLastTransactionQuery(): string
    {
        $trx = $this->appContext->getLastTransaction();
        if ($trx === null) {
            if ($this->userContext->isKasir()) {
                return "Belum ada transaksi penjualan yang tercatat atas akun kasir Anda.";
            }
            return "Belum ada transaksi penjualan yang tercatat di sistem toko saat ini.";
        }

        $idFmt = '#TRX-' . str_pad($trx['id'], 4, '0', STR_PAD_LEFT);
        $waktu = date('d F Y, H:i', strtotime($trx['transaction_date']));
        $total = number_format($trx['total_amount'], 0, ',', '.');
        $kasir = htmlspecialchars($trx['cashier_name'] ?? 'Admin');

        $resp = "🧾 **Transaksi Terakhir Tercatat ({$idFmt})**:\n\n"
              . "- **Waktu**: {$waktu} WIB\n"
              . "- **Kasir Pelaksana**: {$kasir}\n"
              . "- **Total Pembayaran**: **Rp {$total}**\n\n";

        if (!empty($trx['items'])) {
            $resp .= "Rincian Barang:\n";
            foreach ($trx['items'] as $it) {
                $hSub = number_format($it['subtotal'], 0, ',', '.');
                $resp .= "- {$it['product_name']} ({$it['quantity']}x) — Rp {$hSub}\n";
            }
        }
        return $resp;
    }

    private function handleBestSellerQuery(): string
    {
        $bestsellers = $this->appContext->getBestSellers(5);
        if (empty($bestsellers)) {
            return "Saat ini belum ada data produk yang terdaftar di toko.";
        }

        $hasSold = false;
        foreach ($bestsellers as $b) {
            if ($b['total_sold'] > 0) {
                $hasSold = true;
                break;
            }
        }

        if ($hasSold) {
            $resp = "🏆 **Produk Paling Banyak Terjual (Best Seller)**:\n\n";
            $no = 1;
            foreach ($bestsellers as $b) {
                if ($b['total_sold'] > 0) {
                    $badge = $no === 1 ? '🥇' : ($no === 2 ? '🥈' : ($no === 3 ? '🥉' : '⭐'));
                    $rev = number_format($b['total_revenue'], 0, ',', '.');
                    $resp .= "{$badge} **{$b['product_name']}**\n"
                           . "   - Terjual: **{$b['total_sold']} unit** | Pendapatan: Rp {$rev}\n"
                           . "   - Sisa Stok Fisik: {$b['stock']} unit\n";
                    $no++;
                }
            }
            $resp .= "\n💡 *Saran*: Pastikan stok produk terlaris di atas selalu terjaga agar tidak kehabisan saat jam ramai.";
            return $resp;
        }

        // Jika belum ada barang yang laku (misal toko baru)
        $resp = "📦 **Status Penjualan Produk**:\n\n"
              . "Belum ada transaksi penjualan yang diselesaikan di toko ini. Namun, berikut produk aktif yang siap dipromosikan:\n\n";
        foreach ($bestsellers as $b) {
            $harga = number_format($b['price'], 0, ',', '.');
            $resp .= "- **{$b['product_name']}** — Rp {$harga} (Stok: {$b['stock']} unit)\n";
        }
        $resp .= "\n💡 *Tips*: Lakukan transaksi pertama di menu **Transaksi Kasir** untuk mulai menghasilkan data best seller.";
        return $resp;
    }

    private function handleStockAlertsQuery(bool $onlyHabis = false): string
    {
        $alerts = $this->appContext->getStockAlerts(15);
        $habis   = [];
        $menipis = [];

        foreach ($alerts as $a) {
            if ($a['stock'] == 0) {
                $habis[] = $a;
            } elseif ($a['stock'] <= 15) {
                $menipis[] = $a;
            }
        }

        if ($onlyHabis) {
            if (empty($habis)) {
                return "✅ Tidak ada produk yang stoknya habis (stok = 0). Semua produk masih memiliki stok.";
            }
            $resp = "🔴 **Daftar Produk yang Stoknya Habis (0 unit)**:\n\n";
            foreach ($habis as $h) {
                $resp .= "- **{$h['product_name']}** (Stok: 0 unit) — Segera restock!\n";
            }
            return $resp;
        }

        if (empty($habis) && empty($menipis)) {
            return "✅ **Kondisi Inventori Aman!**\n\nSaat ini tidak ada produk yang stoknya kritis. Seluruh barang di toko memiliki stok di atas 15 unit.";
        }

        $resp = "⚠️ **Peringatan Inventori Stok Kritis (≤ 15 unit)**:\n\n";
        if (!empty($habis)) {
            $resp .= "🔴 **HABIS (0 unit)**:\n";
            foreach ($habis as $h) {
                $resp .= "- **{$h['product_name']}** — Stok: 0 unit\n";
            }
            $resp .= "\n";
        }
        if (!empty($menipis)) {
            $resp .= "🟡 **MENIPIS (≤ 15 unit)**:\n";
            foreach ($menipis as $m) {
                $resp .= "- **{$m['product_name']}** — Sisa: **{$m['stock']} unit**\n";
            }
            $resp .= "\n";
        }
        $resp .= "💡 *Tindakan*: Buka menu **Manajemen Barang** lalu klik tombol **Edit** pada barang terkait untuk menambah stok fisik.";
        return $resp;
    }

    private function handleProductsUnderStockQuery(int $threshold): string
    {
        $products = $this->appContext->getProductsUnderStock($threshold);
        if (empty($products)) {
            return "✅ Tidak ada produk yang memiliki stok di bawah {$threshold} unit. Semua produk memiliki stok minimal {$threshold} unit.";
        }

        $resp = "📦 **Produk dengan Stok di Bawah {$threshold} Unit**:\n\n";
        foreach ($products as $p) {
            $harga = number_format($p['price'], 0, ',', '.');
            $status = $p['stock'] == 0 ? '🔴 Habis (0 unit)' : "🟡 Sisa {$p['stock']} unit";
            $resp .= "- **{$p['product_name']}**: {$status} (Harga: Rp {$harga})\n";
        }
        return $resp;
    }

    private function handleProductCountQuery(): string
    {
        $summary = $this->appContext->getStoreSummary();
        if ($summary !== null) {
            return "📦 **Status Inventori Produk Toko**:\n\n"
                 . "- **Total Varian Produk**: **{$summary['total_products']} item** aktif\n"
                 . "- **Total Akumulasi Stok Fisik**: **{$summary['total_stock']} unit**\n\n"
                 . "Anda dapat memeriksa atau memperbarui rincian setiap barang di menu **Manajemen Barang**.";
        }

        // Fallback untuk Kasir (tanpa data omset)
        $products = $this->appContext->getAllActiveProducts(100);
        $totalVarian = count($products);
        $totalStok = 0;
        foreach ($products as $p) {
            $totalStok += (int)$p['stock'];
        }

        return "📦 **Status Inventori Toko**:\n\n"
             . "- **Varian Produk Aktif**: {$totalVarian} jenis\n"
             . "- **Total Stok Fisik**: {$totalStok} unit\n\n"
             . "Data inventori sinkron secara otomatis dengan stasiun Kasir POS.";
    }

    private function handleOverallSalesQuery(): string
    {
        $summary = $this->appContext->getStoreSummary();
        if ($summary === null) {
            return "Maaf, Anda tidak memiliki izin administratif untuk mengakses laporan akumulasi keuangan toko.";
        }

        $omset = number_format($summary['total_sales'], 0, ',', '.');
        return "💰 **Informasi Akumulasi Finansial Toko (Admin)**:\n\n"
             . "- **Total Akumulasi Omset**: **Rp {$omset}**\n"
             . "- **Total Transaksi Selesai**: **{$summary['total_transactions']} nota**\n\n"
             . "Data dihitung secara akurat dari basis data penjualan sejak toko mulai beroperasi.";
    }

    private function handleOrderCalculation(string $norm): ?string
    {
        $products = $this->appContext->getAllActiveProducts(100);
        if (empty($products)) return null;

        $matched = [];
        foreach ($products as $p) {
            $pName = mb_strtolower($p['product_name']);
            $escaped = preg_quote($pName, '/');

            if (preg_match('/(\d+)\s*(?:porsi|buah|gelas|botol|pcs|unit|x)?\s*' . $escaped . '/i', $norm, $m) ||
                preg_match('/' . $escaped . '\s*(?:sebanyak|jumlah|nya)?\s*(\d+)/i', $norm, $m)) {
                $qty = (int)$m[1];
                if ($qty > 0) {
                    $matched[] = [
                        'name'  => $p['product_name'],
                        'price' => (float)$p['price'],
                        'qty'   => $qty
                    ];
                }
            }
        }

        if (empty($matched)) return null;

        $total = 0;
        $resp = "🧮 **Simulasi Kalkulasi Belanja Pesanan**:\n\n";
        foreach ($matched as $item) {
            $sub = $item['price'] * $item['qty'];
            $total += $sub;
            $hSatuan = number_format($item['price'], 0, ',', '.');
            $hSub    = number_format($sub, 0, ',', '.');
            $resp .= "- **{$item['name']}**: {$item['qty']} unit × Rp {$hSatuan} = **Rp {$hSub}**\n";
        }

        $totalFmt = number_format($total, 0, ',', '.');
        $resp .= "\n💵 **TOTAL TAGIHAN: Rp {$totalFmt}**\n\n"
               . "💡 *Petunjuk*: Pesanan ini dapat langsung diinput dan diproses di menu **Transaksi Kasir**.";
        return $resp;
    }

    private function handleProductSearchQuery(string $norm): ?string
    {
        if (!preg_match('/(apakah ada|berapa harga|cek harga|cek stok|stok|harga|ready|cari)\s+(.+)/i', $norm, $matches)) {
            return null;
        }

        $kw = trim($matches[2]);
        $kw = rtrim($kw, "?.!,");
        $kw = preg_replace('/\s+(sekarang|saat ini|hari ini|di toko|toko saya)$/i', '', $kw);
        $kw = preg_replace('/^(produk|barang)\s+/i', '', $kw);
        $kw = trim($kw);
        if (empty($kw) || in_array($kw, ['transaksi', 'barang', 'produk', 'kasir', 'dashboard', 'projek', 'saya', 'omset', 'kemarin', 'hari ini'])) {
            return null;
        }

        $results = $this->appContext->searchProduct($kw);
        if (empty($results)) {
            return "🔍 Produk dengan nama atau kata kunci **\"{$kw}\"** tidak ditemukan dalam katalog barang aktif.";
        }

        $resp = "🔍 **Hasil Pencarian Produk \"{$kw}\"**:\n\n";
        foreach ($results as $p) {
            $harga = number_format($p['price'], 0, ',', '.');
            if ($this->userContext->isPelanggan()) {
                $status = $p['stock'] > 0 ? "🟢 Tersedia" : "🔴 Stok Habis";
            } else {
                $status = $p['stock'] > 0 ? "🟢 Siap ({$p['stock']} unit)" : "🔴 Habis (0 unit)";
            }
            $resp .= "- **{$p['product_name']}**\n  - Harga: **Rp {$harga}**\n  - Status: {$status}\n";
        }
        return $resp;
    }

    private function handleCashierListQuery(): string
    {
        $cashiers = $this->appContext->getCashiers();
        if (!$this->userContext->canManageUsers()) {
            return "Maaf, informasi daftar staf kasir hanya dapat diakses oleh Administrator.";
        }

        if (empty($cashiers)) {
            return "👥 **Daftar Kasir Toko**:\n\nSaat ini belum ada akun kasir tambahan yang terdaftar. Anda dapat menambahkan kasir baru melalui menu **Manajemen Pengguna**.";
        }

        $resp = "👥 **Daftar Kasir Toko yang Terdaftar**:\n\n";
        foreach ($cashiers as $c) {
            $status = $c['is_active'] ? '🟢 Aktif' : '🔴 Nonaktif';
            $resp .= "- **{$c['nama_lengkap']}** ({$c['email']}) — {$status}\n  No. WA: {$c['whatsapp']}\n";
        }
        $resp .= "\n💡 *Pengelolaan*: Tambah kasir baru atau atur status akun melalui menu **Manajemen Pengguna**.";
        return $resp;
    }

    private function handleFeatureGuideQuery(string $norm): ?string
    {
        $guides = ApplicationKnowledgeService::getGuides();

        // 1. Tambah Barang
        if (preg_match('/(tambah.*barang|menambahkan.*barang|buat.*produk|input.*barang)/i', $norm)) {
            return $this->formatGuide($guides['tambah_barang']);
        }

        // 2. Edit / Ubah Barang
        if (preg_match('/(edit.*barang|ubah.*barang|ganti.*harga|update.*stok|edit.*produk)/i', $norm)) {
            return $this->formatGuide($guides['edit_barang']);
        }

        // 3. Hapus Barang
        if (preg_match('/(hapus.*barang|delete.*barang|menghapus.*produk)/i', $norm)) {
            return $this->formatGuide($guides['hapus_barang']);
        }

        // 4. Transaksi Kasir
        if (preg_match('/(cara.*transaksi|cara.*kasir|melakukan.*penjualan|proses.*pembayaran|buka.*kasir)/i', $norm)) {
            return $this->formatGuide($guides['transaksi_kasir']);
        }

        // 5. Cetak Struk
        if (preg_match('/(cetak.*struk|print.*struk|bagaimana.*struk|cara.*struk|cetak.*nota|print.*nota)/i', $norm)) {
            return $this->formatGuide($guides['cetak_struk']);
        }

        // 6. Hapus Transaksi & Auto-Restock
        if (preg_match('/(hapus.*transaksi|batal.*transaksi|cancel.*transaksi|menghapus.*transaksi)/i', $norm)) {
            return $this->formatGuide($guides['hapus_transaksi']);
        }

        // 7. Laporan Penjualan
        if (preg_match('/(lihat.*laporan|buka.*laporan|database.*penjualan|rekap.*penjualan)/i', $norm)) {
            if (!$this->userContext->isAdmin()) {
                return "Maaf, menu Database Penjualan dan laporan omset lengkap merupakan wewenang eksklusif Administrator.";
            }
            return $this->formatGuide($guides['laporan_penjualan']);
        }

        // 8. Tambah Kasir
        if (preg_match('/(tambah.*kasir|buat.*akun.*kasir|tambah.*pengguna)/i', $norm)) {
            if (!$this->userContext->isAdmin()) {
                return "Maaf, pengelolaan pengguna hanya dapat diakses oleh Administrator.";
            }
            return $this->formatGuide($guides['tambah_kasir']);
        }

        // 9. Perbedaan Role (Admin vs Kasir vs Pelanggan)
        if (preg_match('/(perbedaan.*(admin|kasir)|beda.*(admin|kasir)|hak akses|role.*apa saja)/i', $norm)) {
            return "🔐 **Perbedaan Hak Akses Role di Sistem Acounting**:\n\n"
                 . "1. 👑 **Administrator**:\n"
                 . "   - Mengakses seluruh modul sistem: Dashboard, Manajemen Barang, Kasir, Riwayat Transaksi, Database Penjualan, Manajemen Pengguna, dan AI.\n"
                 . "   - Berwenang menambah/mengedit produk, mengelola akun staf kasir, dan membatalkan transaksi (Auto-Restock).\n\n"
                 . "2. 🛒 **Kasir**:\n"
                 . "   - Mengoperasikan Kasir POS, mengecek ketersediaan stok produk, dan melihat riwayat transaksi kasirnya sendiri.\n"
                 . "   - ⛔ *Tidak memiliki akses ke laporan omset keseluruhan toko atau manajemen pengguna*.\n\n"
                 . "3. 👤 **Pelanggan**:\n"
                 . "   - Mengakses Katalog Barang publik untuk melihat menu, harga, dan ketersediaan barang.\n"
                 . "   - ⛔ *Tidak memiliki akses ke dashboard internal atau fitur kasir administratif*.";
        }

        // 10. Verifikasi Akun
        if (preg_match('/(verifikasi.*email|verifikasi.*wa|otp|verifikasi.*akun)/i', $norm)) {
            return $this->formatGuide($guides['verifikasi_akun']);
        }

        // 11. Tentang Projek & Tech Stack
        if (preg_match('/(tentang.*projek|tentang.*aplikasi|teknologi|tech.*stack|arsitektur)/i', $norm)) {
            return ApplicationKnowledgeService::getAppOverview() . "\n\n"
                 . "✨ **Fitur Unggulan Sistem**:\n"
                 . "- 📊 **Dashboard Real-Time**: Visualisasi data dengan Chart.js\n"
                 . "- 🛒 **Kasir POS Modern**: Perhitungan otomatis & cetak struk thermal\n"
                 . "- 🔄 **Auto-Restock Otomatis**: Stok kembali otomatis saat transaksi dibatalkan\n"
                 . "- 🤖 **AI Assistant Cerdas**: Asisten terintegrasi database untuk UMKM\n"
                 . "- 🛡️ **Keamanan Berlapis**: Proteksi CSRF, Anti-SQL Injection, Anti-XSS, & Verifikasi Ganda.";
        }

        return null;
    }

    private function formatGuide(array $guide): string
    {
        $resp = "📋 **{$guide['title']}**\n"
              . "📍 *Menu Terkait*: **{$guide['menu']}**\n\n"
              . "Langkah-langkah:\n";
        foreach ($guide['steps'] as $idx => $step) {
            $no = $idx + 1;
            $resp .= "{$no}. {$step}\n";
        }
        return $resp;
    }

    private function handleBusinessAdvice(): string
    {
        if ($this->userContext->isPelanggan()) {
            return "Fitur analisis bisnis hanya tersedia untuk pengelola toko (Admin).";
        }

        return "💡 **Rekomendasi Strategi Bisnis UMKM (Aca AI Advisor)**:\n\n"
             . "1. ☕ **Paket Bundling Produk (Cross-Selling)**:\n"
             . "   Kombinasikan produk minuman utama (seperti *Americano* atau *Kopi Susu*) dengan produk pelengkap dalam satu paket harga hemat. Strategi ini terbukti dapat meningkatkan nilai keranjang belanja (*basket size*) rata-rata hingga 25%.\n\n"
             . "2. ⏰ **Program Diskon Jam Sepi (Happy Hour)**:\n"
             . "   Tawarkan potongan harga khusus pada jam-jam toko cenderung sepi (misal pukul 14:00 - 16:00) untuk menjaga perputaran transaksi tetap konsisten.\n\n"
             . "3. 📦 **Terapkan Manajemen Stok FIFO (First In, First Out)**:\n"
             . "   Pastikan stok barang yang masuk lebih awal dijual terlebih dahulu guna menghindari penurunan kualitas barang atau kadaluarsa.\n\n"
             . "4. ⚡ **Kecepatan Pelayanan Kasir POS**:\n"
             . "   Manfaatkan fitur pencarian cepat di menu **Transaksi Kasir** agar pelayanan pelanggan di kasir berjalan cepat tanpa antrean panjang.\n\n"
             . "5. 🎯 **Manfaatkan Widget Dashboard & AI Insight**:\n"
             . "   Rutin periksa grafik tren penjualan 7 hari dan donut chart stok di Dashboard untuk menentukan kapan waktu terbaik melakukan restock barang.";
    }

    private function handleTroubleshootingQuery(string $norm): ?string
    {
        if (preg_match('/(stok.*tidak.*cukup|stok.*kurang|tidak.*bisa.*transaksi|gagal.*transaksi)/i', $norm)) {
            return "🔧 **Penyelesaian Masalah: Transaksi Gagal / Stok Tidak Cukup**:\n\n"
                 . "1. Sistem kasir Acounting menerapkan validasi stok ketat untuk mencegah stok fisik menjadi minus.\n"
                 . "2. Jika muncul peringatan stok tidak cukup, periksa sisa kuantitas barang di menu **Manajemen Barang**.\n"
                 . "3. Tambahkan stok barang melalui menu **Manajemen Barang → Edit**, lalu coba selesaikan transaksi kasir kembali.";
        }

        if (preg_match('/(lupa.*password|ganti.*password|reset.*password)/i', $norm)) {
            return "🔐 **Panduan Pengaturan Password**:\n\n"
                 . "1. Jika Anda sudah login: Buka menu **Profil Saya** di sidebar, lalu isi kolom **Password Baru** pada formulir pengaturan akun.\n"
                 . "2. Jika kasir lupa password: Administrator toko dapat mengatur ulang akun kasir melalui menu **Manajemen Pengguna**.";
        }

        if (preg_match('/(csrf|session.*habis|sesi.*berakhir|invalid.*csrf)/i', $norm)) {
            return "🛡️ **Penyelesaian Kendala CSRF Token Expired**:\n\n"
                 . "1. Pesan ini muncul karena sesi peramban Anda telah diam cukup lama demi keamanan akun.\n"
                 . "2. Cukup muat ulang halaman (*Refresh / Ctrl + F5*) browser Anda untuk memperbarui token keamanan, lalu ulangi tindakan Anda.";
        }

        return null;
    }
}
