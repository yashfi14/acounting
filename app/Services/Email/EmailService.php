<?php

require_once __DIR__ . '/MockEmailProvider.php';
// require_once __DIR__ . '/SmtpEmailProvider.php'; // Uncomment saat production

/**
 * EmailService — Factory yang memilih provider berdasarkan APP_ENV.
 * 
 * Cara integrasi production:
 * 1. Buat file SmtpEmailProvider.php yang implements EmailProviderInterface
 * 2. Uncomment require_once di atas
 * 3. Set APP_ENV=production di file .env
 * 4. Pastikan SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_PORT sudah dikonfigurasi
 */
class EmailService {
    private $provider;

    public function __construct()
    {
        $env = defined('APP_ENV') ? APP_ENV : 'development';

        switch ($env) {
            case 'production':
                // $this->provider = new SmtpEmailProvider(SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_PORT);
                // Fallback ke mock jika belum diimplementasi
                $this->provider = new MockEmailProvider();
                break;
            case 'development':
            default:
                $this->provider = new MockEmailProvider();
                break;
        }
    }

    /**
     * Kirim email verifikasi dengan link.
     * 
     * @param string $to Email tujuan
     * @param string $name Nama penerima
     * @param string $verificationLink Link verifikasi lengkap
     * @return bool
     */
    public function sendVerificationEmail($to, $name, $verificationLink)
    {
        $subject = 'Verifikasi Email Akun Acounting Anda';
        
        $body = "
            <div style='font-family: Inter, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #0d6efd, #0a58ca); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;'>
                    <h1 style='color: #fff; margin: 0; font-size: 24px;'>Acounting</h1>
                    <p style='color: rgba(255,255,255,0.8); margin: 5px 0 0;'>Sistem Kasir & Manajemen Stok</p>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #e9ecef; border-top: none;'>
                    <h2 style='color: #212529; margin-top: 0;'>Halo, {$name}!</h2>
                    <p style='color: #6c757d; line-height: 1.6;'>Terima kasih telah mendaftar. Klik tombol di bawah untuk memverifikasi alamat email Anda:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verificationLink}' style='background: #0d6efd; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;'>Verifikasi Email Saya</a>
                    </div>
                    <p style='color: #6c757d; font-size: 14px;'>Atau salin link berikut ke browser:</p>
                    <p style='color: #0d6efd; word-break: break-all; font-size: 13px;'>{$verificationLink}</p>
                    <hr style='border: none; border-top: 1px solid #e9ecef; margin: 20px 0;'>
                    <p style='color: #adb5bd; font-size: 12px;'>Link ini akan kedaluwarsa dalam 60 menit. Jika Anda tidak mendaftar di Acounting, abaikan email ini.</p>
                </div>
            </div>
        ";

        return $this->provider->send($to, $subject, $body);
    }

    /**
     * Cek apakah sedang di mode development (simulasi).
     */
    public function isDevelopment()
    {
        $env = defined('APP_ENV') ? APP_ENV : 'development';
        return $env === 'development';
    }
}
