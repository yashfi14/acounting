<?php

/**
 * AIConversationService — Pengelolaan konteks percakapan Aca.
 *
 * - Konteks dibangun dari riwayat milik pengguna yang login (tidak bercampur).
 * - Hanya beberapa turn terakhir yang dipakai (hemat token & relevan).
 * - Memberi sinyal topik/entitas untuk memahami follow-up seperti
 *   "kalau produk B?", "yang kasir Udin saja", atau "kalau minggu ini?".
 */
class AIConversationService
{
    public const MAX_HISTORY_TURNS = 6;

    /**
     * @param array $history Baris ai_conversations (question, answer, created_at), urut lama→baru.
     */
    public static function buildContext(array $history): array
    {
        $lastQuestion = '';
        $previousQuestion = '';

        foreach ($history as $row) {
            if (!empty($row['question'])) {
                $previousQuestion = $lastQuestion;
                $lastQuestion = (string)$row['question'];
            }
        }

        $lastLower = mb_strtolower($lastQuestion, 'UTF-8');
        $previousLower = mb_strtolower($previousQuestion, 'UTF-8');

        // Pertahankan topik melintasi follow-up tanggal/pronoun. Contoh:
        // "total penjualan bulan ini?" -> "kalau hari ini?" -> "kalau minggu ini?"
        // Pertanyaan follow-up tidak punya kata topik, jadi telusuri riwayat
        // sampai menemukan topik nyata terakhir.
        $lastTopic = self::detectTopic($lastLower) ?: self::detectTopic($previousLower);
        if ($lastTopic === null) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $hq = mb_strtolower((string)($history[$i]['question'] ?? ''), 'UTF-8');
                $topic = self::detectTopic($hq);
                if ($topic !== null) {
                    $lastTopic = $topic;
                    break;
                }
            }
        }

        return [
            'last_question'   => $lastQuestion,
            'previous_question' => $previousQuestion,
            'last_topic'      => $lastTopic,
            'last_date_range' => self::detectDateRange($lastLower),
            'last_cashier'    => self::extractCashierTerm($lastLower),
            'last_product'    => self::extractProductTerm($lastLower),
        ];
    }

    /**
     * Konversi riwayat lokal ke format provider (role user/model), dibatasi.
     */
    public static function toProviderHistory(array $history): array
    {
        $turns = [];
        foreach (array_slice($history, -self::MAX_HISTORY_TURNS) as $row) {
            $question = trim((string)($row['question'] ?? ''));
            $answer   = trim((string)($row['answer'] ?? ''));
            if ($question !== '') {
                $turns[] = ['role' => 'user', 'text' => $question];
            }
            if ($answer !== '') {
                // Jangan kirim jawaban yang sangat panjang sebagai konteks.
                $turns[] = ['role' => 'model', 'text' => mb_substr($answer, 0, 1000)];
            }
        }
        return $turns;
    }

    public static function detectTopic(string $q): ?string
    {
        $map = [
            'transaksi'    => '/\b(transaksi|nota|struk|riwayat|penjualan|omzet|omset)\b/i',
            'stok'         => '/\b(stok|stock|persediaan|inventaris|habis|menipis|restock|restok)\b/i',
            'produk'       => '/\b(produk|barang|katalog|harga|termahal|termurah|varian)\b/i',
            'pengguna'     => '/\b(pengguna|user|akun|kasir|admin|pelanggan|role)\b/i',
            'laporan'      => '/\b(laporan|ringkasan|performa|analisis|statistik)\b/i',
            'panduan'      => '/\b(cara|panduan|langkah|bagaimana|gimana|fitur|menu)\b/i',
        ];

        foreach ($map as $topic => $pattern) {
            if (preg_match($pattern, $q)) {
                return $topic;
            }
        }
        return null;
    }

    /**
     * Mengembalikan rentang tanggal ['start'=>'Y-m-d','end'=>'Y-m-d','label'=>string] atau null.
     */
    public static function detectDateRange(string $q): ?array
    {
        $today = new DateTimeImmutable('today');

        if (preg_match('/\b(hari ini|today)\b/i', $q)) {
            return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'hari ini'];
        }
        if (preg_match('/\b(kemarin|kemaren|yesterday)\b/i', $q)) {
            $d = $today->modify('-1 day');
            return ['start' => $d->format('Y-m-d'), 'end' => $d->format('Y-m-d'), 'label' => 'kemarin'];
        }
        if (preg_match('/\b(minggu lalu|pekan lalu|minggu kemarin|pekan kemarin)\b/i', $q)) {
            $start = $today->modify('monday last week');
            $end   = $start->modify('+6 days');
            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'label' => 'minggu lalu'];
        }
        if (preg_match('/\b(minggu ini|pekan ini)\b/i', $q)) {
            $start = $today->modify('monday this week');
            return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'minggu ini'];
        }
        if (preg_match('/\b(\d{1,2})\s*hari\s*(terakhir|lalu)\b/i', $q, $m)) {
            $days = max(1, min(365, (int)$m[1]));
            $start = $today->modify('-' . ($days - 1) . ' days');
            return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => $days . ' hari terakhir'];
        }
        if (preg_match('/\b(bulan lalu|bulan kemarin|last month)\b/i', $q)) {
            $first = $today->modify('first day of last month');
            $last  = $today->modify('last day of last month');
            return ['start' => $first->format('Y-m-d'), 'end' => $last->format('Y-m-d'), 'label' => 'bulan lalu'];
        }
        if (preg_match('/\b(bulan ini|this month)\b/i', $q)) {
            $start = $today->modify('first day of this month');
            return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'bulan ini'];
        }
        if (preg_match('/\b(tahun lalu|tahun kemarin|last year)\b/i', $q)) {
            $lastYear = (int)$today->format('Y') - 1;
            $start = $today->setDate($lastYear, 1, 1);
            $end = $today->setDate($lastYear, 12, 31);
            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'label' => 'tahun lalu'];
        }
        if (preg_match('/\b(tahun ini|this year)\b/i', $q)) {
            $start = $today->setDate((int)$today->format('Y'), 1, 1);
            return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'tahun ini'];
        }

        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $q, $m)) {
            $d = DateTimeImmutable::createFromFormat('Y-m-d', $m[1] . '-' . $m[2] . '-' . $m[3]);
            if ($d && $d->format('Y-m-d') === $m[0]) {
                return ['start' => $d->format('Y-m-d'), 'end' => $d->format('Y-m-d'), 'label' => $d->format('d/m/Y')];
            }
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $q, $m)) {
            $d = DateTimeImmutable::createFromFormat('d-m-Y', $m[1] . '-' . $m[2] . '-' . $m[3]);
            if ($d) {
                return ['start' => $d->format('Y-m-d'), 'end' => $d->format('Y-m-d'), 'label' => $d->format('d/m/Y')];
            }
        }

        // Rentang "tanggal 1 sampai 5" pada bulan berjalan.
        if (preg_match('/\b(?:tanggal|tgl)\s*(\d{1,2})\s*(?:sampai|s\.?d\.?|hingga|-)\s*(\d{1,2})\b/i', $q, $m)) {
            $startDay = max(1, min(31, (int)$m[1]));
            $endDay   = max(1, min(31, (int)$m[2]));
            if ($startDay > $endDay) {
                [$startDay, $endDay] = [$endDay, $startDay];
            }
            $start = $today->setDate((int)$today->format('Y'), (int)$today->format('m'), $startDay);
            $end   = $today->setDate((int)$today->format('Y'), (int)$today->format('m'), $endDay);
            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'label' => 'tanggal ' . $startDay . '–' . $endDay];
        }

        return null;
    }

    public static function extractCashierTerm(string $q): ?string
    {
        if (preg_match('/\b(?:kasir|oleh|dilakukan oleh)\s+(?:bernama\s+)?([a-z][a-z\.\']{1,40})\b/i', $q, $m)) {
            $term = trim($m[1]);
            $stop = ['saja', 'itu', 'tersebut', 'tertentu', 'yang', 'ini', 'dan', 'aja'];
            if (!in_array($term, $stop, true)) {
                return $term;
            }
        }
        return null;
    }

    public static function extractProductTerm(string $q): ?string
    {
        if (preg_match('/\b(?:produk|barang|stok|harga)\s+([a-z0-9][a-z0-9\s\.\-]{1,40})/i', $q, $m)) {
            $term = trim(preg_replace('/\b(yang|itu|tersebut|saja|sekarang|saat ini|berapa|dong)\b/i', '', $m[1]));
            return $term !== '' ? $term : null;
        }
        return null;
    }
}
