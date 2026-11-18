<?php

class Verification_model {
    private $table = 'user_verifications';
    private $db;

    // Konstanta rate limiting
    const MAX_OTP_ATTEMPTS = 5;        // Maks percobaan OTP
    const MAX_RESEND_COUNT = 5;        // Maks pengiriman ulang per sesi
    const EMAIL_COOLDOWN_SECONDS = 120; // Cooldown kirim ulang email (2 menit)
    const WA_COOLDOWN_SECONDS = 60;     // Cooldown kirim ulang WA OTP (1 menit)

    public function __construct()
    {
        $this->db = new Database;
    }

    /**
     * Buat token baru. Untuk tipe 'whatsapp', token (OTP) di-hash sebelum disimpan.
     * Token lama akan dihapus terlebih dahulu.
     */
    public function createToken($user_id, $type, $token, $expires_in_minutes = 60)
    {
        // Hapus token lama untuk tipe yang sama
        $this->db->query("DELETE FROM " . $this->table . " WHERE user_id = :user_id AND type = :type");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        $this->db->execute();

        // Hash token OTP WhatsApp untuk keamanan, email token tetap plain (karena dikirim via URL)
        $stored_token = ($type === 'whatsapp') ? password_hash($token, PASSWORD_DEFAULT) : $token;

        $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));
        
        $this->db->query("INSERT INTO " . $this->table . " (user_id, type, token, expires_at, last_resend_at) 
                          VALUES (:user_id, :type, :token, :expires_at, NOW())");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        $this->db->bind('token', $stored_token);
        $this->db->bind('expires_at', $expires_at);
        $this->db->execute();
        
        return $this->db->rowCount() > 0;
    }

    /**
     * Ambil token yang valid (belum expired) berdasarkan user_id dan type.
     * Untuk email: token dicocokkan langsung (plain text).
     */
    public function getToken($user_id, $type, $token)
    {
        $this->db->query("SELECT * FROM " . $this->table . " WHERE user_id = :user_id AND type = :type AND expires_at > NOW()");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        $result = $this->db->single();

        if (!$result) return null;

        // Untuk email, cocokkan langsung
        if ($type === 'email') {
            return ($result['token'] === $token) ? $result : null;
        }

        // Untuk whatsapp, verifikasi dengan password_verify (karena di-hash)
        if ($type === 'whatsapp') {
            return password_verify($token, $result['token']) ? $result : null;
        }

        return null;
    }

    /**
     * Ambil data token aktif tanpa mencocokkan nilainya (untuk cek cooldown & rate limit).
     */
    public function getActiveToken($user_id, $type)
    {
        $this->db->query("SELECT * FROM " . $this->table . " WHERE user_id = :user_id AND type = :type AND expires_at > NOW()");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        return $this->db->single();
    }

    /**
     * Increment percobaan verifikasi (OTP salah).
     */
    public function incrementAttempt($user_id, $type)
    {
        $this->db->query("UPDATE " . $this->table . " SET attempts = attempts + 1 WHERE user_id = :user_id AND type = :type");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        $this->db->execute();
    }

    /**
     * Cek apakah percobaan sudah melebihi batas maksimal.
     */
    public function isMaxAttemptsReached($user_id, $type)
    {
        $token = $this->getActiveToken($user_id, $type);
        if (!$token) return false;
        return $token['attempts'] >= self::MAX_OTP_ATTEMPTS;
    }

    /**
     * Cek apakah masih dalam periode cooldown (belum boleh kirim ulang).
     * Return sisa detik cooldown, atau 0 jika sudah boleh kirim ulang.
     */
    public function getCooldownRemaining($user_id, $type)
    {
        $token = $this->getActiveToken($user_id, $type);
        if (!$token || !$token['last_resend_at']) return 0;

        $cooldown = ($type === 'whatsapp') ? self::WA_COOLDOWN_SECONDS : self::EMAIL_COOLDOWN_SECONDS;
        $last_resend = strtotime($token['last_resend_at']);
        $elapsed = time() - $last_resend;
        $remaining = $cooldown - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Cek apakah sudah melebihi batas pengiriman ulang.
     */
    public function isMaxResendReached($user_id, $type)
    {
        $token = $this->getActiveToken($user_id, $type);
        if (!$token) return false;
        return $token['resend_count'] >= self::MAX_RESEND_COUNT;
    }

    /**
     * Update token dengan yang baru (untuk resend), increment resend_count.
     * Untuk whatsapp, token baru di-hash.
     */
    public function resendToken($user_id, $type, $new_token, $expires_in_minutes)
    {
        $stored_token = ($type === 'whatsapp') ? password_hash($new_token, PASSWORD_DEFAULT) : $new_token;
        $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

        $this->db->query("UPDATE " . $this->table . " 
                          SET token = :token, 
                              expires_at = :expires_at, 
                              attempts = 0,
                              resend_count = resend_count + 1, 
                              last_resend_at = NOW() 
                          WHERE user_id = :user_id AND type = :type");
        $this->db->bind('token', $stored_token);
        $this->db->bind('expires_at', $expires_at);
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        $this->db->execute();
        
        return $this->db->rowCount() > 0;
    }

    /**
     * Hapus token setelah berhasil dipakai.
     */
    public function deleteToken($user_id, $type)
    {
        $this->db->query("DELETE FROM " . $this->table . " WHERE user_id = :user_id AND type = :type");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('type', $type);
        $this->db->execute();
    }
}
