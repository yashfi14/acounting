<?php

require_once __DIR__ . '/ApplicationKnowledgeService.php';

/**
 * AIConversationResponder â€” jawaban lokal untuk percakapan non-Accounting.
 *
 * Handler ini tidak pernah melakukan query ke tabel produk/transaksi. Dengan
 * demikian pertanyaan identitas, salam, kemampuan, dan percakapan umum tidak
 * dianggap sebagai pencarian data Accounting.
 */
class AIConversationResponder
{
    private string $userName;
    private string $userRole;

    public function __construct(string $userName = 'Pengguna', string $userRole = 'pelanggan')
    {
        $this->userName = trim($userName) !== '' ? trim($userName) : 'Pengguna';
        $this->userRole = strtolower(trim($userRole)) ?: 'pelanggan';
    }

    public function answer(string $intent, string $question, array $context = []): string
    {
        switch ($intent) {
            case AIIntentClassifier::IDENTITY:
                return $this->identityAnswer($question);
            case AIIntentClassifier::GREETING:
                return $this->greetingAnswer($question);
            case AIIntentClassifier::CAPABILITY:
                return $this->capabilityAnswer($question);
            case AIIntentClassifier::GENERAL_CONVERSATION:
                return $this->generalAnswer($question);
            case AIIntentClassifier::OUT_OF_SCOPE:
            default:
                return $this->outOfScopeAnswer();
        }
    }

    private function identityAnswer(string $question): string
    {
        $q = mb_strtolower($question, 'UTF-8');

        if (preg_match('/\b(?:siapa\s+yang\s+)?(?:membuat|menciptakan|mengembangkan|merancang|membangun)\s+(?:kamu|anda|aca|aplikasi\s+ini)\b/i', $q)) {
            return "Saya adalah AI Assistant yang dikembangkan sebagai bagian dari aplikasi **Acounting**. Saya terintegrasi langsung dengan fitur dan data aplikasi untuk membantu Anda bekerja lebih cepat.";
        }

        if (preg_match('/\b(?:apakah|apa)\s+(?:kamu|anda)\s+(?:ini\s+)?(?:ai|robot|bot|chatbot|manusia|asisten)\b/i', $q)
            || preg_match('/\b(?:kamu|anda)\s+(?:itu\s+)?(?:ai|robot|bot|chatbot)\b/i', $q)) {
            return "Ya, saya adalah **AI Assistant** yang membantu Anda menggunakan aplikasi Acounting dan memahami informasi yang tersedia di dalamnya.";
        }

        if (preg_match('/\b(?:tugas|peran|fungsi)\s+(?:kamu|anda|aca)\b/i', $q)) {
            return "Tugas saya adalah membantu Anda menggunakan aplikasi **Acounting**: mencari informasi barang dan stok, memeriksa transaksi, memahami laporan penjualan, serta menjelaskan cara memakai fitur aplikasi. Saya juga bisa menjawab pertanyaan umum tentang identitas dan kemampuan saya.";
        }

        return "Saya adalah **AI Assistant** yang terintegrasi dengan aplikasi **Acounting** ini. Saya siap membantu Anda mencari informasi barang, memeriksa transaksi, memahami laporan keuangan, serta membantu penggunaan fitur aplikasi.";
    }

    private function greetingAnswer(string $question = ''): string
    {
        $q = mb_strtolower($question, 'UTF-8');

        if (preg_match('/assalamualaikum/i', $q)) {
            return 'Waalaikumsalam warahmatullahi wabarakatuh. Ada yang bisa saya bantu hari ini?';
        }
        if (preg_match('/apa\s+kabar/i', $q)) {
            return 'Baik, terima kasih. Saya siap membantu. Ada yang bisa saya bantu hari ini?';
        }

        if (preg_match('/\bselamat\s+pagi\b|^pagi\b/i', $q)) {
            $waktu = 'pagi';
        } elseif (preg_match('/\bselamat\s+siang\b|^siang\b/i', $q)) {
            $waktu = 'siang';
        } elseif (preg_match('/\bselamat\s+sore\b|^sore\b/i', $q)) {
            $waktu = 'sore';
        } elseif (preg_match('/\bselamat\s+malam\b|^malam\b/i', $q)) {
            $waktu = 'malam';
        } else {
            $hour = (int)date('H');
            if ($hour >= 5 && $hour < 11) {
                $waktu = 'pagi';
            } elseif ($hour >= 11 && $hour < 15) {
                $waktu = 'siang';
            } elseif ($hour >= 15 && $hour < 18) {
                $waktu = 'sore';
            } else {
                $waktu = 'malam';
            }
        }

        $name = $this->userName !== 'Pengguna' ? ' ' . $this->userName : '';
        return "Selamat {$waktu}{$name}! Ada yang bisa saya bantu hari ini?";
    }

    private function capabilityAnswer(string $question): string
    {
        $q = mb_strtolower($question, 'UTF-8');

        if (preg_match('/\b(?:laporan|keuangan|omzet|penjualan)\b/i', $q)) {
            if ($this->userRole === 'admin') {
                return "Ya. Saya dapat membantu Anda memahami laporan penjualan, omzet, performa kasir, dan data transaksi dari database Acounting. Saya juga bisa menunjukkan menu **Database Penjualan** dan menjelaskan cara memfilter laporan. Saya tidak akan mengarang angka jika datanya tidak tersedia.";
            }
            return "Saya dapat menjelaskan cara membaca laporan, tetapi akses laporan keuangan menyeluruh hanya tersedia untuk Admin. Untuk akun " . ucfirst($this->userRole) . ", saya dapat membantu dengan katalog, transaksi yang menjadi hak akses Anda, dan panduan penggunaan aplikasi.";
        }

        $common = "Saya dapat membantu Anda:\n"
            . "- Mencari informasi barang, harga, dan stok.\n"
            . "- Memeriksa transaksi dan riwayat penjualan sesuai hak akses.\n"
            . "- Memahami laporan penjualan dan performa toko.\n"
            . "- Menjelaskan cara memakai fitur aplikasi Acounting.\n"
            . "- Menjawab pertanyaan tentang identitas dan kemampuan saya.";

        if ($this->userRole === 'admin') {
            return $common . "\n\nSebagai Admin, Anda juga dapat menanyakan ringkasan seluruh aktivitas, manajemen pengguna, dan pengaturan Aca.";
        }

        if ($this->userRole === 'kasir') {
            return $common . "\n\nSebagai Kasir, fokus data saya adalah transaksi milik Anda, stok operasional, dan panduan kasir. Laporan keuangan menyeluruh serta manajemen pengguna hanya dapat diakses Admin.";
        }

        return "Saya dapat membantu Anda melihat katalog barang, harga, dan ketersediaan stok, serta menjelaskan cara menggunakan aplikasi Acounting. Data internal toko dan laporan keuangan hanya tersedia untuk Admin atau Kasir sesuai hak akses.";
    }

    private function generalAnswer(string $question): string
    {
        $q = mb_strtolower($question, 'UTF-8');
        $name = $this->userName !== 'Pengguna' ? ' ' . $this->userName : '';

        if (preg_match('/terima\s*kasih|makasih|thanks|thank\s*you|thx/i', $q)) {
            return "Sama-sama{$name}. Senang bisa membantu. Jika ada pertanyaan lain tentang aplikasi Acounting, silakan tanyakan.";
        }

        if (preg_match('/sampai\s+jumpa|selamat\s+tinggal|bye|goodbye|dadah|sampai\s+ketemu/i', $q)) {
            return "Sampai jumpa{$name}. Semoga harinya lancar!";
        }

        if (preg_match('/maaf|sorry/i', $q)) {
            return "Tidak masalah. Silakan lanjutkan jika ada yang ingin Anda tanyakan tentang aplikasi Acounting.";
        }

        if (preg_match('/\b(?:boleh|bisa)\s+(?:minta\s+)?bantuan\b/i', $q)) {
            return "Tentu. Silakan sampaikan pertanyaan atau kendala Anda tentang aplikasi Acounting, dan saya akan bantu jelaskan.";
        }

        return "Baik{$name}. Ada lagi yang ingin Anda tanyakan tentang aplikasi Acounting?";
    }

    private function outOfScopeAnswer(): string
    {
        return "Pertanyaan itu di luar fokus saya. Saya adalah AI Assistant aplikasi **Acounting**, jadi saya paling tepat membantu untuk informasi barang, stok, transaksi, laporan penjualan, dan cara menggunakan fitur aplikasi. Jika Anda ingin, saya bisa membantu dengan salah satu topik tersebut.";
    }

    public function applicationHelpFallback(): string
    {
        $menus = ApplicationKnowledgeService::getOfficialMenus();
        $available = [];
        foreach ($menus as $menu) {
            $roles = $menu['role'] ?? [];
            if (in_array($this->userRole, $roles, true)) {
                $available[] = '**' . $menu['name'] . '**';
            }
        }

        $list = implode(', ', array_slice($available, 0, 8));
        return "Saya belum menemukan panduan spesifik untuk pertanyaan itu. Menu yang dapat Anda akses saat ini: {$list}. Silakan sebutkan menu atau langkah yang ingin Anda pelajari, misalnya \"cara menambahkan barang\" atau \"cara membuat transaksi\".";
    }
}
