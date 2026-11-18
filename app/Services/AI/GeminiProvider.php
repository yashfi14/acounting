<?php

require_once __DIR__ . '/AIProviderInterface.php';

class GeminiProvider implements AIProviderInterface {
    private $apiKey;
    private $model;

    public function __construct($apiKey, $model = 'gemini-flash-lite-latest') {
        $this->apiKey = trim($apiKey);
        $this->model = $model;
    }

    public function ask($question, array $context = []): string {
        if (empty($this->apiKey) || $this->apiKey === 'dummy_ai_api_key' || strpos($this->apiKey, 'dummy') !== false) {
            throw new Exception("Gemini API key belum dikonfigurasi atau masih menggunakan dummy key.");
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey);

        $systemInstruction = "Anda adalah Aca, asisten AI resmi untuk aplikasi web Acounting (aplikasi kasir dan manajemen stok UMKM berbasis PHP dan MySQL).\n"
            . "Tugas Anda adalah membantu pemilik usaha (Admin), Kasir, atau Pelanggan dengan bahasa Indonesia yang ramah, profesional, dan ringkas.\n"
            . "ATURAN PENTING:\n"
            . "1. Gunakan HANYA data aktual aplikasi yang disediakan di bawah ini. JANGAN MENGARANG angka, produk, atau transaksi.\n"
            . "2. Hormati peran pengguna (Role-Based Access Control). Kasir tidak boleh melihat omzet keseluruhan toko. Pelanggan tidak boleh melihat data keuangan internal atau kuantitas stok rahasia.\n"
            . "3. Jika fitur yang ditanyakan belum ada di aplikasi (seperti scan barcode, marketplace, kasbon, export excel), jelaskan secara jujur bahwa fitur tersebut belum tersedia.\n"
            . "4. Format jawaban dengan markdown rapi (poin bullet, bold).\n\n";

        // Tambahkan DB grounding context jika tersedia
        $groundingText = "";
        if (!empty($context['grounding'])) {
            $groundingText = "DATA AKTUAL APLIKASI SAAT INI (REAL-TIME DARI DATABASE):\n" . json_encode($context['grounding'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }

        $userRoleText = "PENGGUNA SAAT INI:\n"
            . "- Nama: " . ($context['user_name'] ?? 'Pengguna') . "\n"
            . "- Role: " . ($context['user_role'] ?? 'pelanggan') . "\n\n";

        $fullPrompt = $systemInstruction . $userRoleText . $groundingText . "PERTANYAAN PENGGUNA: " . $question;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 800
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            throw new Exception("Koneksi ke Gemini API gagal: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception("Gemini API mengembalikan kode HTTP " . $httpCode . ": " . substr($response, 0, 150));
        }

        $resJson = json_decode($response, true);
        $answer = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($answer)) {
            throw new Exception("Respon Gemini API kosong atau format tidak sesuai.");
        }

        return trim($answer);
    }
}
