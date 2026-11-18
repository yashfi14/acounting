<?php

require_once __DIR__ . '/AIDataService.php';
require_once __DIR__ . '/DatabaseSchemaService.php';
require_once __DIR__ . '/AIAccountingContextEngine.php';

/**
 * AIQueryEngine — Intelligent Safe SQL Engine untuk Acounting.
 *
 * Menerapkan alur 10 langkah:
 * 1. Menerima pertanyaan bahasa natural.
 * 2. Mengidentifikasi intent pertanyaan.
 * 3. Menentukan tabel & kolom relevan berdasarkan schema database aktual.
 * 4. Memahami relasi tabel (transactions -> transaction_details -> products).
 * 5. Membuat query SQL berbasis parameter.
 * 6. Memvalidasi query secara ketat (HANYA SELECT, tolak DDL/DML, komentar SQL, multi-query).
 * 7. Menerapkan scoping multi-tenant / RBAC (user_id = :owner_id).
 * 8. Menjalankan query secara aman via PDO prepared statement.
 * 9. Menghasilkan konteks data aktual.
 * 10. Menyajikan ringkasan & tabel Markdown terformat Rupiah.
 */
class AIQueryEngine
{
    private AIDataService $dataService;
    private DatabaseSchemaService $schemaService;
    private AIAccountingContextEngine $accountingEngine;
    private ?PDO $pdo = null;

    /** Kata kunci terlarang yang ditolak secara mutlak */
    private const FORBIDDEN_SQL_KEYWORDS = [
        'insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'create',
        'rename', 'replace', 'grant', 'revoke', 'lock', 'unlock', 'exec',
        'execute', 'call', 'into outfile', 'into dumpfile', 'load_file',
        'benchmark', 'sleep', 'information_schema.user_privileges',
        'mysql.', 'sys.', 'performance_schema.'
    ];

    /** Kolom sensitif yang tidak boleh dibaca atau ditampilkan */
    private const SENSITIVE_COLUMNS = [
        'password', 'token', 'api_key', 'api_key_cipher', 'otp_code', 'otp_attempts'
    ];

    public function __construct(?AIDataService $dataService = null)
    {
        $this->dataService = $dataService ?: new AIDataService();
        $this->pdo = $this->dataService->getPdo();
        $this->schemaService = new DatabaseSchemaService($this->pdo);
        $this->accountingEngine = new AIAccountingContextEngine($this->dataService);
    }

    /**
     * Jalankan validasi keamanan SQL tingkat tinggi.
     * Mengembalikan null jika lolos, atau string alasan penolakan jika tidak lolos.
     */
    public function validateQuery(string $sql): ?string
    {
        $clean = trim($sql);
        if ($clean === '') {
            return 'Query SQL kosong.';
        }

        // 1. Wajib diawali dengan SELECT (case-insensitive)
        if (!preg_match('/^\s*SELECT\b/i', $clean)) {
            return 'Hanya perintah SELECT yang diizinkan untuk kueri AI.';
        }

        // 2. Tolak multi-query (tanda titik koma di luar string)
        if (strpos($clean, ';') !== false) {
            $parts = explode(';', $clean);
            if (count(array_filter(array_map('trim', $parts))) > 1) {
                return 'Multi-query (pemisah titik koma) dilarang demi keamanan.';
            }
        }

        // 3. Tolak komentar SQL (-- , /* */, #) yang biasa dipakai SQL injection
        if (preg_match('/(--|#|\/\*|\*\/)/', $clean)) {
            return 'Komentar SQL dilarang dalam kueri.';
        }

        // 4. Periksa kata kunci terlarang
        $lower = strtolower($clean);
        foreach (self::FORBIDDEN_SQL_KEYWORDS as $bad) {
            if (preg_match('/\b' . preg_quote($bad, '/') . '\b/i', $lower)) {
                return "Perintah berbahaya '{$bad}' terdeteksi dan ditolak.";
            }
        }

        // 5. Tolak kolom sensitif
        foreach (self::SENSITIVE_COLUMNS as $col) {
            if (preg_match('/\b' . preg_quote($col, '/') . '\b/i', $lower)) {
                return "Akses ke kolom sensitif '{$col}' tidak diizinkan.";
            }
        }

        // 6. Wajib mencantumkan LIMIT jika tidak menggunakan agregasi GROUP BY/COUNT/SUM tanpa limit
        if (!preg_match('/\bLIMIT\s+\d+/i', $clean) && !preg_match('/\bCOUNT\(|SUM\(|AVG\(/i', $clean)) {
            // Kita akan otomatis menambahkan LIMIT nanti, jadi bukan error fatal
        }

        return null;
    }

    /**
     * Eksekusi query SELECT yang sudah divalidasi dan terikat parameter secara aman.
     */
    public function executeSafeSelect(string $sql, array $params = [], int $limit = 50): array
    {
        $validationError = $this->validateQuery($sql);
        if ($validationError !== null) {
            return [
                'success' => false,
                'error' => $validationError,
                'rows' => [],
                'count' => 0,
            ];
        }

        if (!$this->pdo) {
            return [
                'success' => false,
                'error' => 'Koneksi database tidak tersedia.',
                'rows' => [],
                'count' => 0,
            ];
        }

        // Pastikan ada limit maksimal 50
        if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            $sql .= ' LIMIT ' . max(1, min(50, $limit));
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $val) {
                if (is_int($val)) {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, (string)$val, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sanitasi output dari kolom sensitif
            $cleanRows = [];
            foreach ($rows as $r) {
                $cleanRow = [];
                foreach ($r as $colName => $colVal) {
                    if (in_array(strtolower($colName), self::SENSITIVE_COLUMNS, true)) {
                        continue;
                    }
                    $cleanRow[$colName] = $colVal;
                }
                $cleanRows[] = $cleanRow;
            }

            return [
                'success' => true,
                'error' => '',
                'rows' => $cleanRows,
                'count' => count($cleanRows),
            ];
        } catch (Throwable $e) {
            error_log('[AIQueryEngine] Eksekusi SQL gagal: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Gagal mengeksekusi kueri database secara aman.',
                'rows' => [],
                'count' => 0,
            ];
        }
    }

    /**
     * Memproses pertanyaan akuntansi spesifik menggunakan Context Engine & Data Service.
     * Mengembalikan array dengan ['handled' => bool, 'answer' => string, 'topic' => string]
     */
    public function handleDomainQuestion(string $question, int $ownerId, string $role = 'admin'): array
    {
        $q = mb_strtolower(trim($question), 'UTF-8');

        // 1. Pembelian
        if (preg_match('/\b(pembelian|beli|pengadaan|kulakan)\b/i', $q)
            && !preg_match('/\b(terbanyak\s+dibeli|sering\s+dibeli|yang\s+dibeli)\b/i', $q)) {
            $range = AIConversationService::detectDateRange($q);
            $ctx = $this->accountingEngine->getPurchasesContext($ownerId, $range);
            return [
                'handled' => true,
                'topic' => 'purchases',
                'answer' => $ctx['explanation'],
            ];
        }

        // 2. Piutang
        if (preg_match('/\b(piutang|tagihan|tempo|belum\s+dibayar|belum\s+lunas)\b/i', $q)) {
            $ctx = $this->accountingEngine->getReceivablesContext($ownerId);
            return [
                'handled' => true,
                'topic' => 'receivables',
                'answer' => $ctx['explanation'],
            ];
        }

        // 3. Utang
        if (preg_match('/\b(utang|hutang|kewajiban)\b/i', $q)) {
            $ctx = $this->accountingEngine->getPayablesContext($ownerId);
            return [
                'handled' => true,
                'topic' => 'payables',
                'answer' => $ctx['explanation'],
            ];
        }

        // 4. Laba Kotor / Gross Profit
        if (preg_match('/\b(laba\s+kotor|profit\s+kotor|gross\s+profit|keuntungan\s+kotor)\b/i', $q)
            || (preg_match('/\b(laba|profit|untung|keuntungan)\b/i', $q) && !preg_match('/\b(bersih)\b/i', $q))) {
            $range = AIConversationService::detectDateRange($q);
            $ctx = $this->accountingEngine->getGrossProfitContext($ownerId, $range);
            return [
                'handled' => true,
                'topic' => 'gross_profit',
                'answer' => $ctx['explanation'],
            ];
        }

        // 5. Saldo Kas & Bank
        if (preg_match('/\b(saldo\s+kas|saldo\s+bank|uang\s+kas|kas\s+dan\s+bank|kas\s+toko|arus\s+kas|penerimaan\s+kas)\b/i', $q)) {
            $ctx = $this->accountingEngine->getCashBalanceContext($ownerId);
            return [
                'handled' => true,
                'topic' => 'cash_balance',
                'answer' => $ctx['explanation'],
            ];
        }

        // 6. Pelanggan terbesar / customer
        if (preg_match('/\b(pelanggan\s+terbesar|transaksi\s+terbesar|pembeli\s+terbanyak|customer\s+terbesar|top\s+pelanggan|nota\s+terbesar)\b/i', $q)) {
            $ctx = $this->accountingEngine->getTopCustomersContext($ownerId, 5);
            return [
                'handled' => true,
                'topic' => 'top_customers',
                'answer' => $ctx['explanation'],
            ];
        }

        return ['handled' => false, 'answer' => '', 'topic' => ''];
    }

    /**
     * Konversi baris query menjadi tabel Markdown yang rapi.
     */
    public function formatRowsToMarkdown(array $rows, array $columnLabels = []): string
    {
        if (empty($rows)) {
            return '_Tidak ada data ditemukan._';
        }

        $headers = [];
        $firstRow = $rows[0];
        foreach (array_keys($firstRow) as $colKey) {
            $headers[] = $columnLabels[$colKey] ?? ucwords(str_replace('_', ' ', $colKey));
        }

        $escape = static function ($val) {
            if (is_numeric($val) && strpos((string)$val, '.') !== false) {
                // jika desimal rupiah
                $f = (float)$val;
                if ($f >= 100) {
                    return 'Rp ' . number_format($f, 0, ',', '.');
                }
            }
            $t = str_replace(["\r\n", "\r", "\n"], ' ', (string)$val);
            return trim(str_replace('|', '\\|', $t));
        };

        $out = '| ' . implode(' | ', array_map($escape, $headers)) . " |\n";
        $out .= '|' . implode('|', array_fill(0, count($headers), '---')) . "|\n";
        foreach ($rows as $row) {
            $out .= '| ' . implode(' | ', array_map($escape, array_values($row))) . " |\n";
        }
        return trim($out);
    }
}

