<?php

require_once __DIR__ . '/EmailProviderInterface.php';

/**
 * Mock Email Provider untuk mode development.
 * Tidak mengirim email sungguhan — hanya mengembalikan link verifikasi
 * yang bisa ditampilkan di halaman (simulasi).
 */
class MockEmailProvider implements EmailProviderInterface {

    public function send($to, $subject, $body)
    {
        // Di mode development, kita tidak mengirim email.
        // Link verifikasi akan ditampilkan langsung di halaman sebagai flash message.
        // Method ini hanya menandai bahwa "pengiriman" berhasil.
        
        error_log("[MockEmailProvider] Simulasi kirim email ke: $to | Subject: $subject");
        return true;
    }
}
