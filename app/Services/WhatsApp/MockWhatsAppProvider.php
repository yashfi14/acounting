<?php

require_once __DIR__ . '/WhatsAppProviderInterface.php';

/**
 * Mock WhatsApp Provider untuk mode development.
 * Tidak mengirim pesan sungguhan — hanya log simulasi.
 */
class MockWhatsAppProvider implements WhatsAppProviderInterface {

    public function sendMessage($phoneNumber, $message)
    {
        // Di mode development, kita tidak mengirim WA.
        // OTP akan ditampilkan langsung di halaman sebagai flash message (simulasi).
        
        error_log('[MockWhatsAppProvider] Simulasi pengiriman WhatsApp (mode development; isi pesan tidak dicatat).');
        return true;
    }
}
