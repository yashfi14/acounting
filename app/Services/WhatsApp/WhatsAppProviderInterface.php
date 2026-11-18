<?php

/**
 * Interface untuk provider pengiriman pesan WhatsApp.
 * Implementasi baru (Fonnte, Wazzup, Twilio, dll) harus mengikuti kontrak ini.
 */
interface WhatsAppProviderInterface {
    
    /**
     * Kirim pesan WhatsApp.
     * 
     * @param string $phoneNumber Nomor WhatsApp tujuan (format internasional)
     * @param string $message Isi pesan
     * @return bool True jika berhasil dikirim
     */
    public function sendMessage($phoneNumber, $message);
}
