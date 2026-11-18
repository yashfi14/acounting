<?php

require_once __DIR__ . '/AIDataService.php';
require_once __DIR__ . '/DatabaseSchemaService.php';

/**
 * AIAccountingContextEngine
 *
 * Engine pemahaman istilah dan konteks akuntansi UMKM untuk aplikasi Acounting.
 * Mengutamakan kebenaran data aktual dari database lokal (Database First Principle).
 *
 * Menangani pemetaan istilah:
 * - Omzet / Penjualan: Total penerimaan kotor dari transaksi penjualan kasir (SUM(total_amount))
 * - Pendapatan: Pendapatan operasional dari penjualan barang
 * - HPP (Harga Pokok Penjualan): Konsep modal barang. Menjelaskan secara transparan jika tabel produk
 *   belum mencatat modal beli, sehingga laba kotor dihitung berdasarkan omzet dikurangi modal tercatat.
 * - Laba Kotor: Penjualan - HPP
 * - Piutang: Tagihan penjualan tempo. Menjelaskan bahwa transaksi kasir diselesaikan tunai langsung (Rp 0 piutang).
 * - Utang: Kewajiban ke supplier. Menjelaskan bahwa pengadaan barang dicatat langsung di menu produk (Rp 0 utang tercatat).
 * - Pembelian: Transaksi pengadaan persediaan dari pemasok.
 * - Saldo Kas: Akumulasi penerimaan kas dari transaksi kasir.
 * - Pelanggan Terbesar: Pelanggan dengan frekuensi atau nominal transaksi tertinggi.
 */
class AIAccountingContextEngine
{
    private AIDataService $dataService;
    private DatabaseSchemaService $schemaService;
    private ?PDO $pdo = null;

    public function __construct(?AIDataService $dataService = null)
    {
        $this->dataService = $dataService ?: new AIDataService();
        $this->pdo = $this->dataService->getPdo();
        $this->schemaService = new DatabaseSchemaService($this->pdo);
    }

    /**
     * Glosarium resmi istilah akuntansi pada aplikasi Acounting.
     */
    public static function getGlossary(): array
    {
        return [
            'omzet' => [
                'term' => 'Omzet / Penjualan Kotor',
                'definition' => 'Total seluruh nilai uang yang diterima dari transaksi penjualan barang dalam periode tertentu.',
                'formula' => 'SUM(transactions.total_amount)',
                'table' => 'transactions',
                'notes' => 'Dihitung berdasarkan transaksi selesai yang diproses kasir.',
            ],
            'pendapatan' => [
                'term' => 'Pendapatan Usaha',
                'definition' => 'Arus masuk kas dari aktivitas utama penjualan barang dagangan toko.',
                'formula' => 'SUM(transactions.total_amount)',
                'table' => 'transactions',
                'notes' => 'Sama dengan omzet penjualan pada model bisnis retail kasir langsung.',
            ],
            'hpp' => [
                'term' => 'HPP (Harga Pokok Penjualan)',
                'definition' => 'Total biaya atau modal perolehan barang yang terjual.',
                'formula' => 'SUM(transaction_details.quantity * modal_produk)',
                'table' => 'products / transaction_details',
                'notes' => 'Pada skema database saat ini, tabel products hanya mencatat kolom price (harga jual) dan belum memiliki kolom harga_beli (modal). Nilai HPP belum dapat dihitung otomatis hingga kolom modal ditambahkan.',
            ],
            'laba_kotor' => [
                'term' => 'Laba Kotor (Gross Profit)',
                'definition' => 'Selisih antara total penjualan (omzet) dengan Harga Pokok Penjualan (HPP).',
                'formula' => 'Omzet Penjualan - HPP',
                'table' => 'transactions & products',
                'notes' => 'Karena modal beli belum dicatat pada database saat ini, laba kotor setara dengan omzet kotor sebelum dikurangi estimasi modal barang.',
            ],
            'piutang' => [
                'term' => 'Piutang Usaha (Accounts Receivable)',
                'definition' => 'Hak tagih atas penjualan barang yang pembayarannya belum dilunasi oleh pelanggan.',
                'formula' => 'SUM(transaksi_kredit_tertunggak)',
                'table' => 'transactions',
                'notes' => 'Semua transaksi kasir di Acounting diselesaikan secara tunai langsung (lunas seketika). Tidak ada termin penjualan kredit, sehingga saldo piutang tercatat = Rp 0.',
            ],
            'utang' => [
                'term' => 'Utang Usaha (Accounts Payable)',
                'definition' => 'Kewajiban finansial toko kepada supplier atas pengadaan barang tempo.',
                'formula' => 'SUM(faktur_pembelian_belum_lunas)',
                'table' => '-',
                'notes' => 'Sistem belum memiliki modul pencatatan utang supplier atau faktur tempo. Stok dimasukkan langsung pada manajemen produk, sehingga saldo utang tercatat = Rp 0.',
            ],
            'pembelian' => [
                'term' => 'Pembelian / Pengadaan Barang',
                'definition' => 'Transaksi perolehan persediaan barang dari supplier.',
                'formula' => 'SUM(faktur_beli.total)',
                'table' => '-',
                'notes' => 'Sistem mencatat persediaan langsung pada tabel products (kolom stock). Belum ada tabel terpisah untuk histori faktur pembelian/Purchase Order.',
            ],
            'saldo_kas' => [
                'term' => 'Saldo Kas Penjualan',
                'definition' => 'Total uang tunai yang terkumpul dari penerimaan transaksi penjualan kasir.',
                'formula' => 'SUM(transactions.total_amount)',
                'table' => 'transactions',
                'notes' => 'Mencerminkan arus kas masuk dari seluruh transaksi kasir yang berhasil diproses.',
            ],
            'stok_persediaan' => [
                'term' => 'Persediaan Barang Dagang (Inventory)',
                'definition' => 'Kuantitas fisik barang yang siap dijual serta estimasi nilai jualnya.',
                'formula' => 'Total Unit: SUM(products.stock) | Nilai Jual: SUM(products.stock * products.price)',
                'table' => 'products',
                'notes' => 'Stok berkurang otomatis setiap kasir menyelesaikan transaksi checkout.',
            ],
        ];
    }

    /**
     * Hitung total pembelian.
     */
    public function getPurchasesContext(int $ownerId, ?array $dateRange = null): array
    {
        $periodLabel = $dateRange['label'] ?? 'periode ini';
        return [
            'type' => 'purchases',
            'period' => $periodLabel,
            'amount' => 0,
            'explanation' => "Pada sistem Acounting saat ini, transaksi pengadaan/pembelian barang dari supplier belum dicatat dalam tabel faktur pembelian tersendiri. Stok barang dimasukkan dan diperbarui langsung melalui menu **Manajemen Produk**. Dengan demikian, total nominal pembelian tercatat pada database adalah **Rp 0**.",
        ];
    }

    /**
     * Hitung total piutang usaha.
     */
    public function getReceivablesContext(int $ownerId): array
    {
        return [
            'type' => 'receivables',
            'amount' => 0,
            'explanation' => "Seluruh transaksi pada kasir Acounting diselesaikan secara **tunai langsung (lunas saat checkout)**. Sistem tidak menyediakan penjualan tempo/kredit, sehingga total piutang usaha toko Anda adalah **Rp 0** (tidak ada piutang atau tagihan tertunggak).",
        ];
    }

    /**
     * Hitung total utang usaha.
     */
    public function getPayablesContext(int $ownerId): array
    {
        return [
            'type' => 'payables',
            'amount' => 0,
            'explanation' => "Sistem Acounting saat ini fokus pada kasir Point of Sale (POS) dan stok barang. Belum ada modul pencatatan utang usaha atau kewajiban tempo ke supplier. Dengan demikian, total kewajiban utang usaha yang tercatat di database adalah **Rp 0**.",
        ];
    }

    /**
     * Hitung estimasi laba kotor.
     */
    public function getGrossProfitContext(int $ownerId, ?array $dateRange = null): array
    {
        if (!$this->pdo) {
            return [
                'type' => 'gross_profit',
                'revenue' => 0,
                'cogs' => 0,
                'gross_profit' => 0,
                'explanation' => 'Database tidak dapat diakses.',
            ];
        }

        $startDate = $dateRange['start'] ?? null;
        $endDate = $dateRange['end'] ?? null;
        $periodLabel = $dateRange['label'] ?? 'seluruh waktu';

        $sales = $this->dataService->getSalesSummary($ownerId, $startDate, $endDate);
        $totalSales = (float)($sales['total_sales'] ?? 0);
        $trxCount = (int)($sales['transactions'] ?? 0);
        $itemsSold = (int)($sales['units'] ?? 0);

        $explanation = "Rumus Laba Kotor = **Omzet Penjualan − HPP (Modal Barang)**.\n\n"
            . "- Total omzet penjualan ({$periodLabel}): **Rp " . number_format($totalSales, 0, ',', '.') . "** dari **{$trxCount} transaksi** ({$itemsSold} item).\n"
            . "- **Catatan Database:** Tabel produk saat ini mencatat harga jual dan belum mencatat modal beli (HPP) per barang. Maka laba kotor setara dengan omzet kotor sebelum dikurangi biaya modal produk.\n"
            . "- Estimasi pendapatan kotor tercatat: **Rp " . number_format($totalSales, 0, ',', '.') . "**.";

        return [
            'type' => 'gross_profit',
            'period' => $periodLabel,
            'revenue' => $totalSales,
            'transactions' => $trxCount,
            'items_sold' => $itemsSold,
            'explanation' => $explanation,
        ];
    }

    /**
     * Hitung saldo kas dari penerimaan penjualan.
     */
    public function getCashBalanceContext(int $ownerId): array
    {
        if (!$this->pdo) {
            return [
                'type' => 'cash_balance',
                'amount' => 0,
                'explanation' => 'Database tidak dapat diakses.',
            ];
        }

        $totalSalesData = $this->dataService->getTotalSales($ownerId);
        $todaySalesData = $this->dataService->getTodaySales($ownerId);
        $monthSalesData = $this->dataService->getMonthSales($ownerId);

        $totalSales = (float)($totalSalesData['total'] ?? 0);
        $todaySales = (float)($todaySalesData['total'] ?? 0);
        $monthSales = (float)($monthSalesData['total'] ?? 0);

        $formattedTotal = 'Rp ' . number_format($totalSales, 0, ',', '.');
        $formattedToday = 'Rp ' . number_format($todaySales, 0, ',', '.');
        $formattedMonth = 'Rp ' . number_format($monthSales, 0, ',', '.');

        $explanation = "Berdasarkan pencatatan kasir POS (seluruh transaksi diselesaikan tunai):\n\n"
            . "- **Total Saldo Kas Masuk (Akumulasi):** **{$formattedTotal}**\n"
            . "- Penerimaan Kas Bulan Ini: **{$formattedMonth}**\n"
            . "- Penerimaan Kas Hari Ini: **{$formattedToday}**\n\n"
            . "_Catatan: Nilai di atas merupakan akumulasi kas dari transaksi penjualan. Pengeluaran kas operasional di luar transaksi kasir belum dicatat dalam database saat ini._";

        return [
            'type' => 'cash_balance',
            'total_cash' => $totalSales,
            'today_cash' => $todaySales,
            'month_cash' => $monthSales,
            'explanation' => $explanation,
        ];
    }

    /**
     * Analisis pelanggan dan transaksi terbesar.
     */
    public function getTopCustomersContext(int $ownerId, int $limit = 5): array
    {
        if (!$this->pdo) {
            return [
                'type' => 'top_customers',
                'customers' => [],
                'explanation' => 'Database tidak dapat diakses.',
            ];
        }

        // 1. Cek transaksi yang memiliki nominal terbesar
        $stmtTrx = $this->pdo->prepare("
            SELECT t.id, t.total_amount, t.transaction_date, u.nama_lengkap as cashier_name
            FROM transactions t
            LEFT JOIN users u ON t.cashier_id = u.id
            WHERE t.user_id = ?
            ORDER BY t.total_amount DESC
            LIMIT ?
        ");
        $stmtTrx->bindValue(1, $ownerId, PDO::PARAM_INT);
        $stmtTrx->bindValue(2, $limit, PDO::PARAM_INT);
        $stmtTrx->execute();
        $biggestTransactions = $stmtTrx->fetchAll(PDO::FETCH_ASSOC);

        // 2. Daftar pelanggan terdaftar di sistem
        $stmtPelanggan = $this->pdo->prepare("
            SELECT id, nama_lengkap, email, whatsapp, created_at
            FROM users
            WHERE role = 'pelanggan' AND (admin_id = ? OR admin_id IS NULL)
            ORDER BY id ASC
            LIMIT ?
        ");
        $stmtPelanggan->bindValue(1, $ownerId, PDO::PARAM_INT);
        $stmtPelanggan->bindValue(2, $limit, PDO::PARAM_INT);
        $stmtPelanggan->execute();
        $pelangganList = $stmtPelanggan->fetchAll(PDO::FETCH_ASSOC);

        $out = "Analisis Transaksi Terbesar Toko:\n\n";

        if (!empty($biggestTransactions)) {
            $out .= "**Transaksi dengan Nominal Terbesar:**\n";
            $headers = ['No. Nota', 'Nominal', 'Tanggal', 'Kasir'];
            $rows = [];
            foreach ($biggestTransactions as $t) {
                $rows[] = [
                    '#TRX-' . $t['id'],
                    'Rp ' . number_format((float)$t['total_amount'], 0, ',', '.'),
                    date('d M Y H:i', strtotime($t['transaction_date'])),
                    $t['cashier_name'] ?: 'Kasir'
                ];
            }
            $out .= $this->renderMarkdownTable($headers, $rows) . "\n\n";
        } else {
            $out .= "Belum ada transaksi yang tercatat di toko Anda.\n\n";
        }

        if (!empty($pelangganList)) {
            $out .= "**Pelanggan Terdaftar di Sistem (" . count($pelangganList) . " akun):**\n";
            $headers = ['Nama Pelanggan', 'Email', 'WhatsApp'];
            $rows = [];
            foreach ($pelangganList as $p) {
                $rows[] = [
                    $p['nama_lengkap'],
                    $p['email'] ?: '-',
                    $p['whatsapp'] ? substr($p['whatsapp'], 0, 4) . '****' . substr($p['whatsapp'], -3) : '-'
                ];
            }
            $out .= $this->renderMarkdownTable($headers, $rows) . "\n";
            $out .= "_Catatan: Pada sistem kasir saat ini, transaksi dicatat per nota kasir tanpa kolom wajib id_pelanggan pada struk kasir retail._";
        }

        return [
            'type' => 'top_customers',
            'biggest_transactions' => $biggestTransactions,
            'customers' => $pelangganList,
            'explanation' => $out,
        ];
    }

    private function renderMarkdownTable(array $headers, array $rows): string
    {
        $escape = static function ($val) {
            $t = str_replace(["\r\n", "\r", "\n"], ' ', (string)$val);
            return trim(str_replace('|', '\\|', $t));
        };

        $out = '| ' . implode(' | ', array_map($escape, $headers)) . " |\n";
        $out .= '|' . implode('|', array_fill(0, count($headers), '---')) . "|\n";
        foreach ($rows as $row) {
            $out .= '| ' . implode(' | ', array_map($escape, $row)) . " |\n";
        }
        return trim($out);
    }
}
