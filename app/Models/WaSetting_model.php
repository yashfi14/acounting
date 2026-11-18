<?php

require_once __DIR__ . '/../Services/AI/AICryptoService.php';

class WaSetting_model {
    private $table = 'wa_settings';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    /**
     * Ambil pengaturan WA yang aman untuk ditampilkan.
     * api_key mentah TIDAK pernah dikembalikan ke controller/view.
     */
    public function getByUserId($user_id)
    {
        $this->db->query("SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1");
        $this->db->bind('user_id', (int)$user_id);
        $row = $this->db->single();

        if (!$row) {
            return $row;
        }

        $row['has_api_key'] = !empty($row['api_key']);
        unset($row['api_key']);
        return $row;
    }

    /**
     * API key gateway hasil dekripsi, hanya untuk request backend.
     * Jangan pernah mengirim nilai ini ke view/frontend.
     */
    public function getDecryptedApiKey($user_id): ?string
    {
        $this->db->query("SELECT api_key FROM {$this->table} WHERE user_id = :user_id LIMIT 1");
        $this->db->bind('user_id', (int)$user_id);
        $row = $this->db->single();
        if (!$row || empty($row['api_key'])) {
            return null;
        }

        return $this->decryptStoredKey((string)$row['api_key']);
    }

    private function encryptKey(string $plain): string
    {
        try {
            return AICryptoService::encrypt($plain);
        } catch (Throwable $e) {
            throw new RuntimeException('Token gateway tidak dapat dienkripsi. Periksa konfigurasi AI_ENCRYPTION_KEY / storage.');
        }
    }

    private function decryptStoredKey(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        try {
            $decrypted = AICryptoService::decrypt($stored);
            if (is_string($decrypted) && trim($decrypted) !== '') {
                return trim($decrypted);
            }
        } catch (Throwable $e) {
            // Jatuh ke mode kompatibilitas data lama di bawah.
        }

        // Kompatibilitas data lama: token yang tersimpan sebelum enkripsi.
        // Hanya terima bentuk token yang wajar, bukan ciphertext base64.
        if (preg_match('/^[A-Za-z0-9._\-]{16,}$/', $stored)) {
            return $stored;
        }

        return null;
    }

    /**
     * Simpan / perbarui permintaan koneksi nomor WA baru dengan kode OTP.
     * Token gateway dienkripsi; token kosong tidak menghapus token lama.
     */
    public function savePendingConnection($user_id, $phoneNumber, $otp, $apiKey = null)
    {
        require_once __DIR__ . '/../Services/WhatsApp/WhatsAppService.php';
        $phoneNumber = WhatsAppService::normalizeNumber($phoneNumber);
        $existing = $this->getByUserId($user_id);
        $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $newKey = is_string($apiKey) ? trim($apiKey) : '';
        $encryptedKey = $newKey !== '' ? $this->encryptKey($newKey) : null;

        if ($existing) {
            if ($encryptedKey !== null) {
                $sql = "UPDATE {$this->table}
                        SET phone_number = :phone_number,
                            otp_code = :otp_code,
                            otp_expires_at = :expires_at,
                            otp_attempts = 0,
                            is_connected = 0,
                            api_key = :api_key,
                            updated_at = NOW()
                        WHERE user_id = :user_id";
            } else {
                $sql = "UPDATE {$this->table}
                        SET phone_number = :phone_number,
                            otp_code = :otp_code,
                            otp_expires_at = :expires_at,
                            otp_attempts = 0,
                            is_connected = 0,
                            updated_at = NOW()
                        WHERE user_id = :user_id";
            }
        } else {
            $sql = "INSERT INTO {$this->table}
                    (user_id, phone_number, api_key, is_connected, otp_code, otp_expires_at, otp_attempts, notify_transaction, notify_low_stock, low_stock_threshold)
                    VALUES
                    (:user_id, :phone_number, :api_key, 0, :otp_code, :expires_at, 0, 1, 1, 5)";
        }

        $this->db->query($sql);
        $this->db->bind('user_id', (int)$user_id);
        $this->db->bind('phone_number', $phoneNumber);
        $this->db->bind('otp_code', $hashedOtp);
        $this->db->bind('expires_at', $expiresAt);
        if ($encryptedKey !== null) {
            $this->db->bind('api_key', $encryptedKey);
        } elseif (!$existing) {
            // Placeholder :api_key pada INSERT tetap harus di-bind.
            $this->db->bind('api_key', null);
        }
        $this->db->execute();

        return $this->db->rowCount() >= 0;
    }

    /**
     * Verifikasi kode OTP yang dimasukkan user
     */
    public function verifyOtp($user_id, $inputOtp)
    {
        $setting = $this->getByUserId($user_id);
        if (!$setting) {
            return ['success' => false, 'message' => 'Pengaturan nomor WhatsApp tidak ditemukan.'];
        }

        if (empty($setting['otp_code'])) {
            return ['success' => false, 'message' => 'Tidak ada kode OTP aktif. Silakan minta kode baru.'];
        }

        if (strtotime($setting['otp_expires_at']) < time()) {
            return ['success' => false, 'message' => 'Kode OTP telah kedaluwarsa. Silakan kirim ulang kode baru.'];
        }

        if ((int)$setting['otp_attempts'] >= 5) {
            return ['success' => false, 'message' => 'Terlalu banyak percobaan gagal. Silakan masukkan nomor dan kirim ulang kode OTP.'];
        }

        // Cek kecocokan OTP (password_verify atau perbandingan langsung jika plain)
        $isValid = password_verify($inputOtp, $setting['otp_code']) || ($setting['otp_code'] === $inputOtp);

        if (!$isValid) {
            $newAttempts = (int)$setting['otp_attempts'] + 1;
            $this->db->query("UPDATE {$this->table} SET otp_attempts = :attempts WHERE user_id = :user_id");
            $this->db->bind('attempts', $newAttempts);
            $this->db->bind('user_id', (int)$user_id);
            $this->db->execute();

            $sisa = 5 - $newAttempts;
            return [
                'success' => false,
                'message' => "Kode OTP salah. Sisa kesempatan: {$sisa} kali."
            ];
        }

        // Berhasil verifikasi: tandai nomor sebagai terkoneksi
        $this->db->query("UPDATE {$this->table}
                          SET is_connected = 1,
                              verified_at = NOW(),
                              otp_code = NULL,
                              otp_expires_at = NULL,
                              otp_attempts = 0,
                              updated_at = NOW()
                          WHERE user_id = :user_id");
        $this->db->bind('user_id', (int)$user_id);
        $this->db->execute();

        return [
            'success' => true,
            'message' => 'Nomor WhatsApp berhasil diverifikasi dan terhubung!',
            'phone_number' => $setting['phone_number']
        ];
    }

    /**
     * Update opsi notifikasi (transaksi, stok, dsb).
     * Token kosong = pertahankan token yang sudah tersimpan.
     */
    public function updateOptions($user_id, array $data)
    {
        $notifyTx   = isset($data['notify_transaction']) ? (int)$data['notify_transaction'] : 1;
        $notifyLow  = isset($data['notify_low_stock']) ? (int)$data['notify_low_stock'] : 1;
        $threshold  = isset($data['low_stock_threshold']) ? max(1, (int)$data['low_stock_threshold']) : 5;
        $apiKey     = isset($data['api_key']) ? trim((string)$data['api_key']) : '';

        if ($apiKey !== '') {
            $encrypted = $this->encryptKey($apiKey);
            $this->db->query("UPDATE {$this->table}
                              SET notify_transaction = :tx,
                                  notify_low_stock = :low,
                                  low_stock_threshold = :th,
                                  api_key = :api_key,
                                  updated_at = NOW()
                              WHERE user_id = :user_id");
            $this->db->bind('api_key', $encrypted);
        } else {
            $this->db->query("UPDATE {$this->table}
                              SET notify_transaction = :tx,
                                  notify_low_stock = :low,
                                  low_stock_threshold = :th,
                                  updated_at = NOW()
                              WHERE user_id = :user_id");
        }

        $this->db->bind('tx', $notifyTx);
        $this->db->bind('low', $notifyLow);
        $this->db->bind('th', $threshold);
        $this->db->bind('user_id', (int)$user_id);
        $this->db->execute();

        return $this->db->rowCount() >= 0;
    }

    /**
     * Putuskan koneksi WhatsApp
     */
    public function disconnect($user_id)
    {
        $this->db->query("UPDATE {$this->table}
                          SET is_connected = 0,
                              otp_code = NULL,
                              otp_expires_at = NULL,
                              otp_attempts = 0,
                              updated_at = NOW()
                          WHERE user_id = :user_id");
        $this->db->bind('user_id', (int)$user_id);
        $this->db->execute();

        return $this->db->rowCount() >= 0;
    }
}
