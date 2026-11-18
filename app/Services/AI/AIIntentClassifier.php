<?php

require_once __DIR__ . '/AIConversationService.php';

/**
 * AIIntentClassifier — klasifikasi maksud pertanyaan Aca secara lokal.
 *
 * Classifier ini sengaja ringan dan tidak memanggil Gemini/database Accounting.
 * Database hanya dipakai untuk konteks riwayat percakapan dan (opsional) nama
 * produk yang dikirim AIService setelah pertanyaan percakapan disaring.
 */
class AIIntentClassifier
{
    public const IDENTITY = 'identity';
    public const GREETING = 'greeting';
    public const CAPABILITY = 'capability';
    public const GENERAL_CONVERSATION = 'general_conversation';
    public const APPLICATION_HELP = 'application_help';
    public const ACCOUNTING_QUERY = 'accounting_query';
    public const OUT_OF_SCOPE = 'out_of_scope';

    /** Intent yang tidak boleh menyentuh pencarian data Accounting. */
    public const CONVERSATIONAL_INTENTS = [
        self::IDENTITY,
        self::GREETING,
        self::CAPABILITY,
        self::GENERAL_CONVERSATION,
    ];

    private const IDENTITY_PATTERNS = [
        '/\b(?:siapa|siapakah)\s+(?:anda|kamu|kau|elo|lo|aca|asisten|chatbot|bot|ai)\b/i',
        '/\b(?:kamu|anda|kau|elo|lo)\s+(?:ini\s+)?(?:siapa|apa)\b/i',
        '/\b(?:apa|apakah)\s+(?:nama|namamu|nama\s+kamu|nama\s+anda)\b/i',
        '/\b(?:namamu|nama\s+kamu|nama\s+anda)\s+(?:siapa|apa)\b/i',
        '/\b(?:siapa|siapakah)\s+yang\s+(?:membuat|menciptakan|mengembangkan|merancang|membangun)\s+(?:kamu|anda|aca|aplikasi\s+ini)\b/i',
        '/\b(?:apakah|apa)\s+(?:kamu|anda)\s+(?:ini\s+)?(?:ai|robot|bot|chatbot|manusia|asisten)\b/i',
        '/\b(?:kamu|anda)\s+(?:itu\s+)?(?:ai|robot|bot|chatbot)\b/i',
        '/\b(?:apa|apakah)\s+(?:tugas|peran|fungsi)\s+(?:kamu|anda|aca)\b/i',
        '/\b(?:aca|asisten|ai|bot|chatbot)\s+(?:itu\s+)?siapa\b/i',
        '/\b(?:apa\s+itu\s+aca|tentang\s+aca|aca\s+itu\s+apa)\b/i',
    ];

    private const CAPABILITY_PATTERNS = [
        '/\bapa\s+yang\s+(?:bisa|dapat)\s+(?:kamu|anda)\s+lakukan\b/i',
        '/\b(?:kamu|anda)\s+(?:bisa|dapat)\s+(?:melakukan\s+)?apa\b/i',
        '/\b(?:bisa|dapat)\s+(?:bantu|membantu)\s+apa\b/i',
        '/\b(?:kamu|anda)\s+(?:bisa|dapat)\s+(?:membuat|buat|membantu|menjawab|mengerjakan)\b/i',
        '/\b(?:fitur|kemampuan|kapabilitas)\s+(?:apa\s+saja|apa|kamu|anda)\b/i',
        '/\b(?:aplikasi|sistem|acounting)\s+(?:bisa|dapat)\s+apa\b/i',
        '/\b(?:menu|fitur)\s+apa\s+saja\b/i',
        '/\bapa\s+saja\s+(?:yang\s+)?(?:bisa|dapat)\s+(?:kamu|anda|aplikasi)\b/i',
    ];

    private const GENERAL_CONVERSATION_PATTERNS = [
        '/^(?:terima\s*kasih|terimakasih|makasih|thanks|thank\s*you|thx)\b[\s!.,?]*$/iu',
        '/^(?:oke?|ok|baik|baiklah|siap|mengerti|paham|dimengerti|noted|sip|mantap)\b[\s!.,?]*$/iu',
        '/^(?:sampai\s+jumpa|selamat\s+tinggal|bye|goodbye|dadah|sampai\s+ketemu)\b[\s!.,?]*$/iu',
        '/^(?:maaf|mohon\s+maaf|sorry)\b[\s!.,?]*$/iu',
        '/^(?:boleh|bisa)\s+(?:minta\s+)?bantuan\b[\s!.,?]*$/iu',
    ];

    private const GREETING_PATTERN = '/^(?:halo|hallo|hai|hello|hi|hey|assalamualaikum|selamat\s+(?:pagi|siang|sore|malam)|apa\s+kabar|pagi|siang|sore|malam)(?:\s+aca)?[\s!.,?]*$/iu';

    private const GENERAL_TOPIC_PATTERNS = [
        '/\b(?:presiden|wapres|pemilu|politik|pilpres|dpr|menteri|partai|pemerintah|gubernur|kampanye)\b/i',
        '/\b(?:cuaca|prakiraan\s+cuaca|hujan|gempa|banjir|suhu\s+udara|musim)\b/i',
        '/\b(?:resep|masakan|bumbu|kue|rendang|nasi\s+goreng|cara\s+memasak|makanan\s+enak)\b/i',
        '/\b(?:piala\s+dunia|world\s+cup|sepak\s*bola|bola|champions|liga|ronaldo|messi|olahraga|pertandingan)\b/i',
        '/\b(?:film|bioskop|lagu|lirik|chord|artis|drama|novel|cerita|puisi|anime)\b/i',
        '/\b(?:sejarah|penemu|planet|astronomi|fisika|kimia|matematika|soal|pr|ujian|sekolah|geografi)\b/i',
        '/\b(?:kesehatan|obat|penyakit|dokter|rumah\s+sakit|diet)\b/i',
        '/\b(?:agama|sholat|doa|kitab|ceramah|ustadz|ustad)\b/i',
        '/\b(?:terjemahkan|translate|bahasa\s+inggris|bahasa\s+arab|buat\s+puisi|buat\s+cerita|buat\s+esai)\b/i',
        '/\b(?:game|gaming|mobile\s+legends|free\s+fire|pubg|valorant|steam|minecraft)\b/i',
        '/\b(?:crypto|bitcoin|saham|forex|investasi|trading|reksadana)\b/i',
        '/\b(?:apa\s+itu\s+ai|kecerdasan\s+buatan|machine\s+learning|deep\s+learning)\b/i',
    ];

    private const PROGRAMMING_TOPIC_PATTERNS = [
        '/\b(?:php|javascript|js|typescript|python|java|c\+\+|c#|golang|rust|ruby|kotlin|swift)\b/i',
        '/\b(?:html|css|bootstrap|tailwind|react|vue|angular|node\.?js|laravel|codeigniter|symfony|django|flask|spring)\b/i',
        '/\b(?:coding|koding|programming|source\s+code|kode\s+program|script|syntax|debugging|stack\s*trace|compile|deploy|github|git)\b/i',
        '/\b(?:buatkan|buat|tulis|tuliskan|perbaiki|fix)\b.{0,30}\b(?:kode|program|aplikasi|website|web|script|fungsi|query)\b/i',
    ];

    /**
     * @param array $context      Konteks dari AIConversationService::buildContext().
     * @param array $productTerms Nama produk aktif (opsional, hanya untuk pertanyaan non-percakapan).
     */
    public static function classify(string $question, array $context = [], array $productTerms = []): array
    {
        $raw = trim($question);
        if ($raw === '') {
            return self::result(self::OUT_OF_SCOPE, 1.0, 'empty', 'Pertanyaan kosong.');
        }

        $q = mb_strtolower($raw, 'UTF-8');
        $q = preg_replace('/\s+/u', ' ', $q);
        $q = trim($q);

        $typoMap = [
            'terimakasih' => 'terima kasih',
            'makasi'      => 'makasih',
            'sapa'        => 'siapa',
            'sipa'        => 'siapa',
            'kamu sapa'   => 'kamu siapa',
            'siapa and'   => 'siapa anda',
        ];
        foreach ($typoMap as $wrong => $right) {
            $q = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/u', $right, $q);
        }

        // 1. Identitas AI. Jangan pernah masuk ke pencarian data Accounting.
        foreach (self::IDENTITY_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                $subtype = preg_match('/\b(?:tugas|peran|fungsi)\b/i', $q) ? 'role' : 'identity';
                return self::result(self::IDENTITY, 0.98, $subtype, 'Pola pertanyaan identitas AI.');
            }
        }

        // 2. Kemampuan AI/aplikasi.
        foreach (self::CAPABILITY_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                return self::result(self::CAPABILITY, 0.96, 'capability', 'Pola pertanyaan kemampuan.');
            }
        }

        // 3. Percakapan umum (harus berupa kalimat pendek/utuh).
        foreach (self::GENERAL_CONVERSATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                $subtype = 'acknowledgement';
                if (preg_match('/terima\s*kasih|makasih|thanks|thank\s*you/i', $q)) {
                    $subtype = 'thanks';
                } elseif (preg_match('/sampai\s+jumpa|selamat\s+tinggal|bye|dadah/i', $q)) {
                    $subtype = 'goodbye';
                } elseif (preg_match('/maaf|sorry/i', $q)) {
                    $subtype = 'apology';
                }
                return self::result(self::GENERAL_CONVERSATION, 0.95, $subtype, 'Pola percakapan umum.');
            }
        }

        // 4. Salam.
        if (preg_match(self::GREETING_PATTERN, $q)) {
            return self::result(self::GREETING, 0.97, 'greeting', 'Pola salam.');
        }

        // 5. Topik umum/pemrograman di luar Acounting.
        foreach (self::GENERAL_TOPIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                return self::result(self::OUT_OF_SCOPE, 0.93, 'general_topic', 'Topik umum di luar Acounting.');
            }
        }
        foreach (self::PROGRAMMING_TOPIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                // Konteks "skema database aplikasi" tetap dianggap fitur aplikasi.
                $isAppSchema = preg_match('/\b(?:skema|struktur|relasi|kolom|tabel)\b/i', $q)
                    && preg_match('/\b(?:aplikasi|acounting|aca|sistem|database)\b/i', $q)
                    && !preg_match('/\b(?:sql|query|select|insert|update|delete|drop|alter|join|foreign\s+key|migration)\b/i', $q);
                if (!$isAppSchema) {
                    return self::result(self::OUT_OF_SCOPE, 0.9, 'programming', 'Topik pemrograman di luar Acounting.');
                }
            }
        }

        // 6. Bantuan penggunaan aplikasi.
        if (self::looksLikeApplicationHelp($q)) {
            return self::result(self::APPLICATION_HELP, 0.9, 'application_help', 'Pertanyaan panduan fitur aplikasi.');
        }

        // 7. Pertanyaan data/Accounting.
        if (self::looksLikeAccountingQuery($q)) {
            return self::result(self::ACCOUNTING_QUERY, 0.88, 'accounting', 'Pertanyaan data/Accounting.');
        }

        // 8. Follow-up yang masih membawa konteks percakapan.
        $followUp = self::classifyFollowUp($q, $context);
        if ($followUp !== null) {
            return $followUp;
        }

        // 9. Nama produk yang dikenal (mis. "mineral", "americano").
        foreach ($productTerms as $term) {
            $term = mb_strtolower(trim((string)$term), 'UTF-8');
            if (mb_strlen($term, 'UTF-8') >= 3 && mb_strpos($q, $term, 0, 'UTF-8') !== false) {
                return self::result(self::ACCOUNTING_QUERY, 0.78, 'product', 'Menyebut produk yang dikenal.');
            }
        }

        // 10. Tidak dikenali: biarkan AIService memutuskan dengan konteks produk.
        return self::result(self::OUT_OF_SCOPE, 0.4, 'unknown', 'Tidak ada pola yang cocok.');
    }

    private static function looksLikeApplicationHelp(string $q): bool
    {
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

        // Perintah langsung yang jelas meminta panduan, bukan data.
        return (bool)preg_match(
            '/\b(?:tambah|menambah|menambahkan|buat|membuat|ubah|edit|hapus|batal|batalkan|cetak|print|atur|setting|pengaturan)\b.{0,30}\b(?:barang|produk|transaksi|kasir|pengguna|user|akun|laporan|struk|nota|stok|harga|password|whatsapp|fitur)\b/i',
            $q
        );
    }

    private static function looksLikeAccountingQuery(string $q): bool
    {
        // Identitas pengguna sendiri adalah data akun, bukan identitas AI.
        if (preg_match('/\b(?:siapa\s+saya|siapa\s+aku|nama\s+saya|role\s+saya|hak\s+akses\s+saya|profil\s+saya)\b/i', $q)) {
            return true;
        }

        $dataSignal = (bool)preg_match(
            '/\b(?:berapa|total|jumlah|tampilkan|tampil|lihat|lihatkan|cek|daftar|rincian|detail|riwayat|rekap|statistik|laporan|omzet|omset|pendapatan|penjualan|pemasukan|stok|stock|harga|jual|beli|untung|laba|rugi|keuntungan|transaksi|produk|barang|pelanggan|kasir|pengguna|user|akun|varian|terlaris|termahal|termurah|terjual|menipis|habis|performa|kinerja|analisis)\b/i',
            $q
        );

        if ($dataSignal) {
            return true;
        }

        // Pertanyaan singkat yang secara implisit menanyakan data.
        return (bool)preg_match('/\b(?:apakah\s+ada|ada\s+tidak|masih\s+ada|berapa\s+sisa|sisa\s+berapa)\b/i', $q);
    }

    private static function classifyFollowUp(string $q, array $context): ?array
    {
        $lastQuestion = trim((string)($context['last_question'] ?? ''));
        $lastTopic = (string)($context['last_topic'] ?? '');
        if ($lastQuestion === '' && $lastTopic === '') {
            return null;
        }

        if (mb_strlen($q, 'UTF-8') > 90) {
            return null;
        }

        $range = AIConversationService::detectDateRange($q);
        $isFollowUp = $range !== null || (bool)preg_match(
            '/^(?:kalau|kalo|gimana\s+kalau|bagaimana\s+dengan|bagaimana\s+kalau|yang|itu|tersebut|terus|dan|sama|mana|berapa|tampilkan|lihat|detail|rincian|yang\s+tadi|yang\s+itu)\b/i',
            $q
        );

        if (!$isFollowUp) {
            return null;
        }

        if (in_array($lastTopic, ['transaksi', 'stok', 'produk', 'pengguna', 'laporan'], true)) {
            return self::result(self::ACCOUNTING_QUERY, 0.8, 'follow_up', 'Follow-up konteks Accounting.');
        }

        if ($lastTopic === 'panduan') {
            return self::result(self::APPLICATION_HELP, 0.75, 'follow_up', 'Follow-up konteks panduan aplikasi.');
        }

        return null;
    }

    private static function result(string $intent, float $confidence, string $subtype, string $reason): array
    {
        return [
            'intent'     => $intent,
            'confidence' => $confidence,
            'subtype'    => $subtype,
            'reason'     => $reason,
        ];
    }
}
