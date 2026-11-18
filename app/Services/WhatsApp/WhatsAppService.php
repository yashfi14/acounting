<?php

require_once __DIR__ . '/WhatsAppProviderInterface.php';
require_once __DIR__ . '/MockWhatsAppProvider.php';
require_once __DIR__ . '/FonnteProvider.php';

/**
 * WhatsAppService — Factory & Facade untuk pengiriman WhatsApp.
 * Otomatis mendeteksi provider (Fonnte jika API Key aktif, atau Mock di lokal/development).
 */
class WhatsAppService {

    private $provider;
    private $isMock = false;
    private $lastError = null;
    private $apiKey = '';

    public function __construct($customApiKey = null, $customApiUrl = null)
    {
        $this->apiKey = $customApiKey ?: (defined('WA_API_KEY') ? WA_API_KEY : '');
        $apiUrl = $customApiUrl ?: (defined('WA_API_URL') ? WA_API_URL : '');

        $hasRealKey = !empty($this->apiKey) && $this->apiKey !== 'dummy_wa_key_12345' && $this->apiKey !== 'your_wa_api_key_here';

        if ($hasRealKey) {
            $this->provider = new FonnteProvider($this->apiKey, $apiUrl);
            $this->isMock = false;
        } else {
            $this->provider = new MockWhatsAppProvider();
            $this->isMock = true;
        }
    }

    /**
     * Standarisasi format nomor HP ke format internasional (628xxx)
     */
    public static function normalizeNumber($phone)
    {
        return FonnteProvider::normalizeNumber($phone);
    }

    /**
     * Format nomor HP untuk tampilan yang rapi (misal: 0812-3456-7890)
     */
    public static function formatDisplay($phone)
    {
        $num = self::normalizeNumber($phone);
        if (strpos($num, '62') === 0) {
            $local = '0' . substr($num, 2);
        } else {
            $local = $num;
        }

        if (strlen($local) >= 10) {
            return substr($local, 0, 4) . '-' . substr($local, 4, 4) . '-' . substr($local, 8);
        }
        return $local;
    }

    /**
     * Buat URL langsung untuk membuka WhatsApp Web / Aplikasi WhatsApp (wa.me)
     */
    public static function getDirectWaUrl($phone, $message)
    {
        $target = self::normalizeNumber($phone);
        $encodedMsg = rawurlencode($message);
        return "https://api.whatsapp.com/send?phone={$target}&text={$encodedMsg}";
    }

    /**
     * Kirim kode OTP untuk koneksi akun / verifikasi.
     * 
     * @param string $phoneNumber
     * @param string $otp
     * @return bool
     */
    public function sendOtp($phoneNumber, $otp)
    {
        $message = "🔐 *KODE VERIFIKASI WHATSAPP*\n"
                 . "*Sistem Acounting Toko*\n"
                 . "----------------------------------------\n"
                 . "Kode OTP Anda: *{$otp}*\n\n"
                 . "Kode ini digunakan untuk menghubungkan nomor WhatsApp Anda dengan notifikasi sistem toko.\n"
                 . "Berlaku selama *10 menit*. Jangan berikan kode ini kepada siapa pun!\n"
                 . "----------------------------------------\n"
                 . "_Pesan otomatis oleh Sistem Acounting_";

        return $this->sendMessage($phoneNumber, $message);
    }

    /**
     * Kirim pesan teks WhatsApp umum.
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return bool
     */
    public function sendMessage($phoneNumber, $message)
    {
        $this->lastError = null;
        try {
            $result = $this->provider->sendMessage($phoneNumber, $message);
            if (!$result && method_exists($this->provider, 'getLastError')) {
                $this->lastError = $this->provider->getLastError();
            }
            return $result;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[WhatsAppService] Error: ' . $this->lastError);
            return false;
        }
    }

    /**
     * Kirim notifikasi transaksi baru dari kasir.
     * 
     * @param string $phoneNumber
     * @param array $trxData [id, cashier_name, total, items => [[name, qty, subtotal]]]
     * @return bool
     */
    public function sendTransactionNotification($phoneNumber, array $trxData)
    {
        $id = $trxData['id'] ?? '-';
        $cashier = $trxData['cashier_name'] ?? 'Kasir';
        $totalFormatted = 'Rp ' . number_format($trxData['total'] ?? 0, 0, ',', '.');
        $date = date('d M Y, H:i');

        $itemLines = "";
        if (!empty($trxData['items']) && is_array($trxData['items'])) {
            foreach ($trxData['items'] as $item) {
                $subFormatted = 'Rp ' . number_format($item['subtotal'] ?? 0, 0, ',', '.');
                $itemLines .= "• {$item['qty']}x {$item['name']} ({$subFormatted})\n";
            }
        } else {
            $itemLines = "• Transaksi penjualan kasir\n";
        }

        $message = "🧾 *NOTIFIKASI TRANSAKSI BARU*\n"
                 . "*Sistem Acounting Toko*\n"
                 . "----------------------------------------\n"
                 . "No. Transaksi : #TRX-{$id}\n"
                 . "Waktu         : {$date} WIB\n"
                 . "Kasir         : {$cashier}\n"
                 . "----------------------------------------\n"
                 . "*Rincian Pembelian:*\n"
                 . $itemLines
                 . "----------------------------------------\n"
                 . "*Total Belanja : {$totalFormatted}*\n"
                 . "----------------------------------------\n"
                 . "Status: *LUNAS & BERHASIL*\n"
                 . "_Terima kasih telah menggunakan sistem kami._";

        return $this->sendMessage($phoneNumber, $message);
    }

    /**
     * Kirim notifikasi peringatan stok menipis.
     * 
     * @param string $phoneNumber
     * @param array $productData [name, stock, min_stock]
     * @return bool
     */
    public function sendLowStockNotification($phoneNumber, array $productData)
    {
        $name = $productData['name'] ?? 'Barang';
        $stock = $productData['stock'] ?? 0;
        $min = $productData['min_stock'] ?? 5;
        $date = date('d M Y, H:i');

        $message = "⚠️ *PERINGATAN STOK MENIPIS*\n"
                 . "*Sistem Acounting Toko*\n"
                 . "----------------------------------------\n"
                 . "Produk      : *{$name}*\n"
                 . "Sisa Stok   : *{$stock} unit*\n"
                 . "Batas Min.  : {$min} unit\n"
                 . "Waktu Cek   : {$date} WIB\n"
                 . "----------------------------------------\n"
                 . "⚠️ Stok produk ini sudah menipis atau di bawah batas minimum.\n"
                 . "Silakan segera lakukan pengadaan atau penambahan stok!\n"
                 . "----------------------------------------\n"
                 . "_Pesan otomatis Sistem Acounting_";

        return $this->sendMessage($phoneNumber, $message);
    }

    public function isDevelopment()
    {
        return $this->isMock;
    }

    public function isMock()
    {
        return $this->isMock;
    }

    public function hasRealGateway()
    {
        return !$this->isMock;
    }

    public function getLastError()
    {
        return $this->lastError;
    }
}
