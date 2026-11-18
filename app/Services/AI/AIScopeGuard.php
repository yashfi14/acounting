<?php

/**
 * AIScopeGuard — Penjaga ruang lingkup Aca di backend.
 *
 * Aca melayani pertanyaan seputar aplikasi Acounting, tetapi pertanyaan
 * percakapan (identitas, salam, kemampuan, terima kasih) diklasifikasikan lebih
 * dulu dan dijawab lokal. Guard domain hanya dijalankan untuk pertanyaan
 * aplikasi/Accounting, setelah pemeriksaan keamanan. Gemini tetap tidak dapat
 * dipakai untuk topik di luar Acounting.
 */
class AIScopeGuard
{
    public const REJECTION_MESSAGE = 'Maaf, saya hanya dapat membantu terkait penggunaan dan fitur aplikasi Acounting. Ada yang ingin Anda tanyakan tentang barang, transaksi, atau laporan?';

    public const SECURITY_MESSAGE = 'Permintaan tersebut tidak dapat saya proses. Saya hanya membantu penggunaan dan data aplikasi Acounting sesuai hak akses akun Anda.';

    /** Kata kunci yang menandakan pertanyaan masih berkaitan dengan Acounting. */
    private const DOMAIN_KEYWORDS = [
        'acounting', 'aca', 'aplikasi', 'fitur', 'menu', 'dashboard', 'barang', 'produk', 'katalog',
        'stok', 'stock', 'persediaan', 'inventaris', 'gudang', 'restock', 'restok', 'harga', 'jual', 'beli',
        'transaksi', 'nota', 'struk', 'kasir', 'pos', 'penjualan', 'omzet', 'omset', 'pendapatan', 'pemasukan',
        'laba', 'untung', 'laporan', 'riwayat', 'pelanggan', 'customer', 'pengguna', 'user', 'akun', 'profil',
        'password', 'role', 'admin', 'kasir', 'verifikasi', 'otp', 'whatsapp', 'wa', 'email', 'login', 'logout',
        'barang masuk', 'barang keluar', 'terjual', 'terlaris', 'paling laku', 'jarang terjual', 'tidak laku',
        'stok menipis', 'stok habis', 'hampir habis', 'stok minimum', 'harga modal', 'harga jual', 'diskon',
        'metode pembayaran', 'tunai', 'kembalian', 'cetak', 'ekspor', 'impor', 'pengaturan aca', 'gemini',
        'openai', 'deepseek', 'claude', 'anthropic', 'openrouter', 'provider ai', 'api key', 'model ai',
        'siapa saya', 'nama saya', 'siapa nama saya', 'role saya', 'hak akses',
        'cara', 'panduan', 'bantuan', 'gagal', 'error', 'batal', 'hapus', 'tambah', 'edit', 'ubah', 'minimum',
        'tabel', 'skema', 'struktur', 'relasi', 'kolom', 'database', 'notifikasi', 'supplier', 'kategori',
    ];

    /** Topik pemrograman/teknis yang harus ditolak. */
    private const PROGRAMMING_PATTERNS = [
        '/\b(php|javascript|js|typescript|python|java|c\+\+|c#|golang|rust|ruby|kotlin|swift)\b/i',
        '/\b(html|css|bootstrap|tailwind|react|vue|angular|node\.?js|laravel|codeigniter|symfony|django|flask|spring)\b/i',
        '/\b(sql|mysql|mariadb|postgres|mongodb|query|database schema|foreign key|join table)\b/i',
        '/\b(coding|koding|program(ming)?|source code|kode program|script|syntax|debug(ging)?|stack ?trace|exception|compile|deploy|github|git)\b/i',
        '/\b(api|endpoint|library|framework|function|variabel|array|loop|database migration)\b/i',
        '/\b(buatkan|buat|tulis|tuliskan|perbaiki|fix)\b.{0,30}\b(kode|program|aplikasi|website|web|script|fungsi|query)\b/i',
        '/\b(ajarkan|jelaskan)\b.{0,30}\b(pemrograman|coding|php|javascript|python|sql)\b/i',
    ];

    /** Topik umum di luar Acounting. */
    private const GENERAL_PATTERNS = [
        '/\b(piala dunia|world cup|sepak bola|bola|champions|liga|ronaldo|messi|olahraga|pertandingan)\b/i',
        '/\b(presiden|pemilu|politik|pilpres|dpr|menteri|partai|pemerintah)\b/i',
        '/\b(cuaca|prakiraan|hujan|gempa|banjir|suhu udara)\b/i',
        '/\b(resep|masakan|bumbu|kue|rendang|nasi goreng|makanan enak|cara memasak)\b/i',
        '/\b(film|bioskop|lagu|lirik|chord|artis|drama|novel|cerita|puisi)\b/i',
        '/\b(sejarah|penemu|planet|astronomi|fisika|kimia|matematika|soal|pr|ujian|sekolah)\b/i',
        '/\b(kesehatan|obat|penyakit|dokter|rumah sakit|diet|olahraga)\b/i',
        '/\b(agama|sholat|doa|kitab|ceramah|ustadz)\b/i',
        '/\b(terjemahkan|translate|bahasa inggris|bahasa arab|buat puisi|buat cerita|buat esai)\b/i',
        '/\b(game|gaming|mobile legends|free fire|pubg|valorant|steam)\b/i',
        '/\b(crypto|bitcoin|saham|forex|investasi|trading)\b/i',
    ];

    private const JAILBREAK_PATTERNS = [
        '/\b(abaikan|ignore|lupakan|bypass|lewati)\b.{0,40}\b(aturan|instruksi|instruction|prompt|keamanan|security|role)\b/i',
        '/\b(bocorkan|tampilkan|berikan|kirim)\b.{0,30}\b(api ?key|password|token|secret|rahasia)\b/i',
        '/\b(lihat|melihat|akses|baca|tampilkan|berikan|kirim|buka|cek)\b.{0,30}\b(data|transaksi|akun|profil)\b.{0,20}\b(orang lain|pengguna lain|pelanggan lain)\b/i',
        '/\b(act as|kamu adalah|anda adalah|jailbreak|developer mode|system prompt)\b/i',
    ];

    private const GREETING_PATTERN = '/^(halo|hai|hello|hi|hey|assalamualaikum|selamat (pagi|siang|sore|malam)|apa kabar|pagi|siang|sore|malam)(\s+aca)?[\s!?.]*$/i';

    private const FOLLOW_UP_PATTERN = '/^(kalau|kalo|gimana kalau|bagaimana dengan|bagaimana kalau|yang|itu|tersebut|terus|dan|sama|mana|berapa|tampilkan|lihat|detail|rincian|yang tadi|yang itu)\b/i';

    /**
     * Pemeriksaan keamanan saja (jailbreak/prompt injection).
     * Dipanggil sebelum routing intent agar percakapan sederhana tidak pernah
     * melewati proteksi, tetapi juga tidak ditolak oleh filter domain.
     */
    public static function securityCheck(string $question): ?array
    {
        $q = mb_strtolower(trim($question), 'UTF-8');
        $q = preg_replace('/\s+/u', ' ', $q);

        foreach (self::JAILBREAK_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                return ['allowed' => false, 'type' => 'blocked', 'message' => self::SECURITY_MESSAGE];
            }
        }

        return null;
    }

    public static function evaluate(string $question, array $context = [], ?string $intent = null): array
    {
        $q = mb_strtolower(trim($question), 'UTF-8');
        $q = preg_replace('/\s+/u', ' ', $q);

        if ($q === '') {
            return ['allowed' => false, 'type' => 'out_of_scope', 'message' => 'Silakan ketik pertanyaan Anda.'];
        }

        $security = self::securityCheck($question);
        if ($security !== null) {
            return $security;
        }

        // Pertanyaan tentang pengaturan API key/model Aca adalah fitur aplikasi,
        // bukan permintaan pemrograman. Kecualikan sebelum cek programming.
        $isAcaSettings = (bool)preg_match('/\b(api ?key|model|gemini|openai|deepseek|claude|anthropic|openrouter|provider|pengaturan aca|konfigurasi aca)\b/i', $q)
            && (bool)preg_match('/\b(aca|aplikasi|acounting|pengaturan|konfigurasi|admin|gemini|openai|deepseek|claude|anthropic|openrouter|provider|api ?key)\b/i', $q);

        // Pertanyaan tentang struktur tabel/skema aplikasi adalah fitur Acounting,
        // bukan permintaan pemrograman/SQL. Tetap tolak bila meminta query/kode.
        $isAppSchema = (bool)preg_match('/\b(skema|struktur|relasi|kolom|tabel)\b/i', $q)
            && (bool)preg_match('/\b(aplikasi|acounting|aca|sistem|database|data)\b/i', $q)
            && !(bool)preg_match('/\b(sql|query|select|insert|update|delete|drop|alter|join|foreign key|php|coding|kode|program|migration)\b/i', $q);

        if (!$isAcaSettings && !$isAppSchema) {
            foreach (self::PROGRAMMING_PATTERNS as $pattern) {
                if (preg_match($pattern, $q)) {
                    return ['allowed' => false, 'type' => 'out_of_scope', 'message' => self::REJECTION_MESSAGE];
                }
            }
        }

        foreach (self::GENERAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                return ['allowed' => false, 'type' => 'out_of_scope', 'message' => self::REJECTION_MESSAGE];
            }
        }

        // Intent yang sudah diklasifikasikan lokal diizinkan melewati filter
        // keyword. Ini mencegah pertanyaan seperti "Siapa Anda?" atau
        // "Bagaimana cara menambahkan pengguna?" ditolak karena tidak memiliki
        // kata kunci Accounting yang eksplisit.
        $allowedIntents = [
            'identity',
            'greeting',
            'capability',
            'general_conversation',
            'application_help',
            'accounting_query',
        ];
        if ($intent !== null && in_array($intent, $allowedIntents, true)) {
            return ['allowed' => true, 'type' => 'in_scope', 'reason' => 'intent:' . $intent];
        }

        if (preg_match(self::GREETING_PATTERN, $q)) {
            return ['allowed' => true, 'type' => 'in_scope', 'reason' => 'greeting'];
        }

        // Follow-up pendek yang masih membawa konteks percakapan Acounting.
        $hasContext = !empty($context['last_topic']) || !empty($context['last_question']);
        if ($hasContext && mb_strlen($q) <= 90 && preg_match(self::FOLLOW_UP_PATTERN, $q)) {
            return ['allowed' => true, 'type' => 'in_scope', 'reason' => 'follow_up'];
        }

        // Nama produk toko yang dikenal (dari database) tetap dalam ruang lingkup Acounting.
        $productTerms = $context['product_terms'] ?? [];
        if (is_array($productTerms)) {
            foreach ($productTerms as $term) {
                $term = mb_strtolower(trim((string)$term), 'UTF-8');
                if (mb_strlen($term, 'UTF-8') >= 3 && mb_strpos($q, $term, 0, 'UTF-8') !== false) {
                    return ['allowed' => true, 'type' => 'in_scope', 'reason' => 'product:' . $term];
                }
            }
        }

        foreach (self::DOMAIN_KEYWORDS as $keyword) {
            if (mb_strlen($keyword, 'UTF-8') <= 4) {
                $matched = (bool)preg_match('/\b' . preg_quote($keyword, '/') . '\b/iu', $q);
            } else {
                $matched = mb_strpos($q, $keyword, 0, 'UTF-8') !== false;
            }
            if ($matched) {
                return ['allowed' => true, 'type' => 'in_scope', 'reason' => 'keyword:' . $keyword];
            }
        }

        // Pertanyaan singkat tanpa kata kunci namun jelas menanyakan Aca.
        if (preg_match('/\b(aca|asisten|chatbot|bot)\b/i', $q)) {
            return ['allowed' => true, 'type' => 'in_scope', 'reason' => 'aca'];
        }

        return ['allowed' => false, 'type' => 'out_of_scope', 'message' => self::REJECTION_MESSAGE];
    }
}
