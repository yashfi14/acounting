<?php

require_once __DIR__ . '/AIDataService.php';

/**
 * DatabaseSchemaService — introspeksi skema database Acounting untuk Aca.
 *
 * Tujuan:
 * - Memberi AI pengetahuan tabel, kolom, primary key, foreign key, dan relasi.
 * - Menyembunyikan kolom sensitif (password, token, API key, OTP) dari prompt.
 * - Menandai tabel legacy/sibling agar AI tidak salah menyimpulkan data.
 *
 * Service ini hanya membaca INFORMATION_SCHEMA. Tidak ada operasi tulis.
 * Hasil di-cache per request agar tidak query berulang.
 */
class DatabaseSchemaService
{
    /** Tabel milik aplikasi lain / legacy yang tidak dipakai alur Acounting. */
    private const LEGACY_TABLES = [
        'barang',
        'ai_settings',
        'ai_audit_log',
    ];

    /** Kolom yang tidak boleh pernah masuk ke prompt / jawaban AI. */
    private const SENSITIVE_COLUMNS = [
        'users' => ['password'],
        'user_verifications' => ['token'],
        'wa_settings' => ['api_key', 'otp_code'],
        'aca_ai_settings' => ['api_key_cipher'],
        'aca_ai_provider_configs' => ['api_key_cipher'],
        'ai_settings' => ['gemini_api_key_encrypted'],
    ];

    /** Deskripsi fungsi tabel untuk grounding AI. */
    private const TABLE_PURPOSE = [
        'users' => 'Akun pengguna dan peran (admin, kasir, pelanggan). admin_id menghubungkan kasir/pelanggan ke admin toko.',
        'products' => 'Barang/produk milik toko (user_id = admin toko), stok terkini, harga jual, status aktif.',
        'transactions' => 'Header transaksi penjualan: toko (user_id), kasir (cashier_id), tanggal, total.',
        'transaction_details' => 'Rincian item per transaksi: produk, kuantitas, harga saat transaksi, subtotal.',
        'user_verifications' => 'Token verifikasi email/WhatsApp (sensitif, tidak untuk analisis bisnis).',
        'wa_notifications' => 'Log notifikasi WhatsApp yang dikirim/digagal/disimulasikan.',
        'wa_settings' => 'Pengaturan koneksi WhatsApp per admin, preferensi notifikasi, dan ambang stok rendah.',
        'ai_conversations' => 'Riwayat pertanyaan dan jawaban chatbot Aca per pengguna.',
        'aca_ai_settings' => 'Konfigurasi provider AI utama dan cadangan per admin (API key terenkripsi; tidak boleh dibuka AI).',
        'aca_ai_provider_configs' => 'Konfigurasi per provider AI: model, base URL, status koneksi, dan API key terenkripsi. Tidak boleh dibuka AI.',
        'aca_ai_audit_logs' => 'Audit perubahan pengaturan Aca oleh admin.',
        'aca_ai_query_logs' => 'Audit metadata query Aca (tanpa isi pertanyaan/jawaban).',
        'barang' => 'Tabel legacy aplikasi lain; tidak dipakai alur Acounting.',
        'ai_settings' => 'Tabel legacy/sibling untuk konfigurasi AI; tidak dipakai alur Acounting.',
        'ai_audit_log' => 'Tabel legacy/sibling untuk audit AI; tidak dipakai alur Acounting.',
    ];

    private $pdo;
    private static $schemaCache = null;
    private static $countCache = null;

    public function __construct($pdo = null)
    {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            $data = new AIDataService();
            $this->pdo = $data->getPdo();
        }
    }

    public function isAvailable(): bool
    {
        return $this->pdo instanceof PDO;
    }

    /**
     * Skema lengkap: tabel => [purpose, legacy, columns[], primary_key, relations[]].
     */
    public function getSchema(): array
    {
        if (self::$schemaCache !== null) {
            return self::$schemaCache;
        }

        if (!$this->isAvailable()) {
            return self::$schemaCache = [];
        }

        $schema = [];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT TABLE_NAME, TABLE_ROWS
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ?
                 ORDER BY TABLE_NAME ASC"
            );
            $stmt->execute([DB_NAME]);
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tables as $tableRow) {
                $table = (string)$tableRow['TABLE_NAME'];
                $schema[$table] = [
                    'name'       => $table,
                    'purpose'    => self::TABLE_PURPOSE[$table] ?? 'Tabel aplikasi.',
                    'legacy'     => in_array($table, self::LEGACY_TABLES, true),
                    'approx_rows' => (int)($tableRow['TABLE_ROWS'] ?? 0),
                    'columns'    => [],
                    'primary_key' => [],
                    'relations'  => [],
                ];
            }

            $stmt = $this->pdo->prepare(
                "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                 ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC"
            );
            $stmt->execute([DB_NAME]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $table = (string)$col['TABLE_NAME'];
                if (!isset($schema[$table])) {
                    continue;
                }
                $column = (string)$col['COLUMN_NAME'];
                $schema[$table]['columns'][] = [
                    'name'       => $column,
                    'type'       => (string)$col['COLUMN_TYPE'],
                    'nullable'   => ((string)$col['IS_NULLABLE'] === 'YES'),
                    'key'        => (string)$col['COLUMN_KEY'],
                    'sensitive'  => $this->isSensitiveColumn($table, $column),
                ];
                if ((string)$col['COLUMN_KEY'] === 'PRI') {
                    $schema[$table]['primary_key'][] = $column;
                }
            }

            $stmt = $this->pdo->prepare(
                "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                 ORDER BY TABLE_NAME ASC, COLUMN_NAME ASC"
            );
            $stmt->execute([DB_NAME]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fk) {
                $table = (string)$fk['TABLE_NAME'];
                if (!isset($schema[$table])) {
                    continue;
                }
                $schema[$table]['relations'][] = [
                    'column'           => (string)$fk['COLUMN_NAME'],
                    'references_table' => (string)$fk['REFERENCED_TABLE_NAME'],
                    'references_column'=> (string)$fk['REFERENCED_COLUMN_NAME'],
                ];
            }
        } catch (Throwable $e) {
            error_log('[DatabaseSchemaService] Introspeksi gagal: ' . $e->getMessage());
            return self::$schemaCache = $schema;
        }

        return self::$schemaCache = $schema;
    }

    /**
     * Ringkasan skema untuk prompt provider AI/knowledge lokal.
     */
    public function getKnowledgeSummary(): string
    {
        $schema = $this->getSchema();
        if (empty($schema)) {
            return 'Skema database tidak tersedia saat ini.';
        }

        $lines = [];
        foreach ($schema as $table => $meta) {
            if (!empty($meta['legacy'])) {
                continue;
            }

            $cols = [];
            foreach ($meta['columns'] as $col) {
                if (!empty($col['sensitive'])) {
                    continue;
                }
                $mark = '';
                if ($col['key'] === 'PRI') {
                    $mark = ' PK';
                } elseif ($col['key'] === 'UNI') {
                    $mark = ' UNIQUE';
                } elseif ($col['key'] === 'MUL') {
                    $mark = ' INDEX';
                }
                $cols[] = $col['name'] . ' ' . $col['type'] . $mark;
            }

            $relations = [];
            foreach ($meta['relations'] as $rel) {
                $relations[] = $rel['column'] . ' -> ' . $rel['references_table'] . '.' . $rel['references_column'];
            }

            $line = '- ' . $table . ' (' . $meta['purpose'] . '): ' . implode(', ', $cols);
            if (!empty($relations)) {
                $line .= ' | Relasi: ' . implode(', ', $relations);
            }
            $lines[] = $line;
        }

        $lines[] = '- Catatan: tidak ada tabel kategori, supplier, pemasukan/pengeluaran, pergerakan stok, metode pembayaran, pajak, atau diskon.';
        $lines[] = '- Catatan: kolom password, token verifikasi, API key, dan OTP disembunyikan dan tidak boleh diminta/ditampilkan.';

        return implode("\n", $lines);
    }

    /**
     * Jumlah baris aktual per tabel (untuk ringkasan aplikasi).
     * Tabel legacy dikecualikan.
     */
    public function getRowCounts(): array
    {
        if (self::$countCache !== null) {
            return self::$countCache;
        }
        if (!$this->isAvailable()) {
            return self::$countCache = [];
        }

        $counts = [];
        foreach ($this->getSchema() as $table => $meta) {
            if (!empty($meta['legacy'])) {
                continue;
            }
            try {
                $stmt = $this->pdo->query('SELECT COUNT(*) AS c FROM `' . str_replace('`', '', $table) . '`');
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $counts[$table] = (int)($row['c'] ?? 0);
            } catch (Throwable $e) {
                $counts[$table] = 0;
            }
        }
        return self::$countCache = $counts;
    }

    /**
     * Daftar nama tabel yang boleh dirujuk AI (tanpa legacy).
     */
    public function getActiveTableNames(): array
    {
        $names = [];
        foreach ($this->getSchema() as $table => $meta) {
            if (empty($meta['legacy'])) {
                $names[] = $table;
            }
        }
        return $names;
    }

    public function isLegacyTable(string $table): bool
    {
        return in_array($table, self::LEGACY_TABLES, true);
    }

    public function isSensitiveColumn(string $table, string $column): bool
    {
        return isset(self::SENSITIVE_COLUMNS[$table])
            && in_array($column, self::SENSITIVE_COLUMNS[$table], true);
    }

    /**
     * Kolom aman untuk ditampilkan (tanpa password/token/API key/OTP).
     */
    public function getSafeColumns(string $table): array
    {
        $schema = $this->getSchema();
        if (!isset($schema[$table])) {
            return [];
        }
        $safe = [];
        foreach ($schema[$table]['columns'] as $col) {
            if (empty($col['sensitive'])) {
                $safe[] = $col['name'];
            }
        }
        return $safe;
    }
}
