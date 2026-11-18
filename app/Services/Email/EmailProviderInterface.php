<?php

/**
 * Interface untuk provider pengiriman email.
 * Implementasi baru (SMTP, Mailgun, dll) harus mengikuti kontrak ini.
 */
interface EmailProviderInterface {
    
    /**
     * Kirim email verifikasi.
     * 
     * @param string $to Alamat email tujuan
     * @param string $subject Subjek email
     * @param string $body Konten HTML email
     * @return bool True jika berhasil dikirim
     */
    public function send($to, $subject, $body);
}
