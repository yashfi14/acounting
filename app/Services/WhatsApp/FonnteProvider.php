<?php

require_once __DIR__ . '/WhatsAppProviderInterface.php';

/**
 * Fonnte WhatsApp Provider.
 * Mengirim pesan WhatsApp nyata melalui gateway Fonnte (https://fonnte.com).
 */
class FonnteProvider implements WhatsAppProviderInterface {

    private $apiKey;
    private $apiUrl;
    private $lastResponse = null;
    private $lastError = null;

    public function __construct($apiKey = null, $apiUrl = null)
    {
        $this->apiKey = $apiKey ?: (defined('WA_API_KEY') ? WA_API_KEY : '');
        $url = $apiUrl ?: (defined('WA_API_URL') && WA_API_URL ? WA_API_URL : '');
        if (empty($url) || strpos($url, 'whatsapp.test') !== false) {
            $url = 'https://api.fonnte.com/send';
        }
        $this->apiUrl = $url;
    }

    /**
     * Standarisasi format nomor HP Indonesia ke format internasional (628xxx)
     */
    public static function normalizeNumber($phone)
    {
        $clean = preg_replace('/[^0-9]/', '', (string)$phone);
        if (strpos($clean, '62') === 0) {
            return $clean;
        }
        if (strpos($clean, '0') === 0) {
            return '62' . substr($clean, 1);
        }
        if (strpos($clean, '8') === 0) {
            return '62' . $clean;
        }
        return $clean;
    }

    /**
     * Kirim pesan WhatsApp nyata melalui API Fonnte.
     * 
     * @param string $phoneNumber Nomor tujuan
     * @param string $message Isi pesan
     * @return bool True jika berhasil dikirim
     */
    public function sendMessage($phoneNumber, $message)
    {
        $this->lastError = null;
        $this->lastResponse = null;

        if (empty($this->apiKey) || $this->apiKey === 'your_wa_api_key_here' || $this->apiKey === 'dummy_wa_key_12345') {
            $this->lastError = 'API Token Fonnte belum dimasukkan. Masukkan API Token dari fonnte.com pada menu pengaturan agar pesan terkirim ke WhatsApp Anda.';
            error_log('[FonnteProvider] ' . $this->lastError);
            return false;
        }

        // Format nomor ke format internasional (misal 6281234567890)
        $target = self::normalizeNumber($phoneNumber);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . trim($this->apiKey),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($curlError) {
            $this->lastError = 'Gagal menghubungi server WhatsApp: cURL Error: ' . $curlError;
            error_log('[FonnteProvider] ' . $this->lastError);
            return false;
        }

        $this->lastResponse = $response;
        $result = json_decode($response, true);

        // Fonnte returns {"status": true, ...} on success
        if (is_array($result) && isset($result['status']) && $result['status'] === true) {
            return true;
        }

        // Tangkap detail alasan error dari Fonnte (misal device disconnect, kuota habis, dsb)
        $reason = $result['reason'] ?? ($result['detail'] ?? ($result['message'] ?? 'Gagal mengirim pesan melalui provider Fonnte.'));
        $this->lastError = "Fonnte (HTTP {$httpCode}): {$reason}";
        error_log('[FonnteProvider] ' . $this->lastError);

        return false;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }
}
