<?php

/**
 * Flasher — Session-based Flash Message Helper
 *
 * Digunakan untuk menampilkan pesan sukses/error sekali tampil
 * setelah redirect (Post-Redirect-Get pattern).
 *
 * Penggunaan:
 *   - Set pesan : Flasher::setFlash('Berhasil disimpan!', 'success');
 *   - Tampilkan : Flasher::flash();
 */
class Flasher
{
    private const SESSION_KEY = '_flash_msg';

    /**
     * Simpan pesan flash ke session.
     *
     * @param string $message Pesan yang akan ditampilkan
     * @param string $type    Bootstrap alert type: success|danger|warning|info
     */
    public static function setFlash(string $message, string $type = 'info'): void
    {
        if (!session_id()) {
            session_start();
        }
        $_SESSION[self::SESSION_KEY] = [
            'message' => $message,
            'type'    => $type,
        ];
    }

    /**
     * Render pesan flash dari session, lalu hapus.
     * Panggil sekali di awal view.
     */
    public static function flash(): void
    {
        if (!session_id()) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            return;
        }

        $flash   = $_SESSION[self::SESSION_KEY];
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        $type    = in_array($flash['type'], ['success', 'danger', 'warning', 'info', 'primary', 'secondary'], true)
                   ? $flash['type']
                   : 'info';

        unset($_SESSION[self::SESSION_KEY]);

        $icons = [
            'success'   => 'check-circle-fill',
            'danger'    => 'exclamation-triangle-fill',
            'warning'   => 'exclamation-circle-fill',
            'info'      => 'info-circle-fill',
            'primary'   => 'info-circle-fill',
            'secondary' => 'chat-square-text-fill',
        ];
        $icon = $icons[$type] ?? 'info-circle-fill';

        echo <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-{$icon}"></i>
            {$message}
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
        HTML;
    }

    /**
     * Cek apakah ada pesan flash yang menunggu.
     */
    public static function has(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY] ?? null);
    }
}
