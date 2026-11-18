<?php

require_once '../app/Services/Email/EmailService.php';
require_once '../app/Services/WhatsApp/WhatsAppService.php';

class VerificationController extends Controller {

    public function __construct()
    {
        // Hanya butuh login, belum perlu terverifikasi (ini halaman verifikasi itu sendiri)
        AuthHelper::check();
    }

    // ==========================================
    //  HALAMAN STATUS VERIFIKASI
    // ==========================================
    public function status()
    {
        // Jika sudah terverifikasi sepenuhnya, langsung arahkan sesuai role
        if (!empty($_SESSION['email_verified']) && !empty($_SESSION['whatsapp_verified'])) {
            if (AuthHelper::role() === 'pelanggan') {
                header('Location: ' . BASEURL . '/catalog');
            } else {
                header('Location: ' . BASEURL . '/dashboard');
            }
            exit;
        }

        $data['judul'] = 'Status Verifikasi';
        $data['user'] = Auth::user();

        // Hitung cooldown untuk ditampilkan di view
        $verifModel = $this->model('Verification_model');
        $data['email_cooldown'] = $verifModel->getCooldownRemaining($_SESSION['user_id'], 'email');
        $data['wa_cooldown'] = $verifModel->getCooldownRemaining($_SESSION['user_id'], 'whatsapp');
        
        $this->view('templates/header', $data);
        $this->view('verification/status', $data);
        $this->view('templates/footer');
    }

    // ==========================================
    //  VERIFIKASI EMAIL
    // ==========================================
    
    /**
     * Request link verifikasi email.
     * Dilengkapi cooldown 2 menit antar pengiriman.
     */
    public function requestEmail()
    {
        if ($_SESSION['email_verified']) {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $verifModel = $this->model('Verification_model');

        // Cek cooldown: apakah masih harus menunggu
        $cooldown = $verifModel->getCooldownRemaining($user_id, 'email');
        if ($cooldown > 0) {
            $_SESSION['verif_error'] = "Mohon tunggu {$cooldown} detik sebelum mengirim ulang link verifikasi email.";
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        // Cek apakah sudah ada token aktif (resend)
        $existingToken = $verifModel->getActiveToken($user_id, 'email');

        // Generate token aman
        $token = bin2hex(random_bytes(32));
        $link = BASEURL . "/verification/verifyEmailEndpoint/" . $token;

        if ($existingToken) {
            // Cek batas resend
            if ($verifModel->isMaxResendReached($user_id, 'email')) {
                $_SESSION['verif_error'] = "Anda telah mencapai batas maksimal pengiriman ulang. Silakan coba lagi nanti.";
                header('Location: ' . BASEURL . '/verification/status');
                exit;
            }
            // Update token yang sudah ada (resend)
            $verifModel->resendToken($user_id, 'email', $token, 60);
        } else {
            // Buat token baru (pertama kali)
            $verifModel->createToken($user_id, 'email', $token, 60); // 60 menit expired
        }

        // Kirim email menggunakan EmailService
        $emailService = new EmailService();
        $user = Auth::user();
        $emailService->sendVerificationEmail($user['email'], $user['name'], $link);

        // Tampilkan simulasi di mode development
        if ($emailService->isDevelopment()) {
            $_SESSION['verif_sim_msg'] = "<strong>[MODE DEVELOPMENT]</strong> Link verifikasi email: <a href='$link' class='alert-link'>Klik di sini untuk verifikasi</a>";
        } else {
            $_SESSION['verif_sim_msg'] = "Link verifikasi telah dikirim ke <strong>" . htmlspecialchars($user['email']) . "</strong>. Cek folder inbox atau spam Anda.";
        }
        
        header('Location: ' . BASEURL . '/verification/status');
        exit;
    }

    /**
     * Endpoint verifikasi email — dipanggil ketika user klik link di email.
     */
    public function verifyEmailEndpoint($token = '')
    {
        if (empty($token)) {
            $_SESSION['verif_error'] = "Token verifikasi tidak valid.";
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $verifModel = $this->model('Verification_model');
        $dataToken = $verifModel->getToken($user_id, 'email', $token);

        if ($dataToken) {
            // Berhasil verifikasi email
            $this->model('User_model')->verifyEmail($user_id);
            $verifModel->deleteToken($user_id, 'email');
            
            $_SESSION['email_verified'] = true;
            $_SESSION['verif_success'] = "Email berhasil diverifikasi! ✅";
        } else {
            $_SESSION['verif_error'] = "Token email kedaluwarsa atau tidak valid. Silakan minta link baru.";
        }

        header('Location: ' . BASEURL . '/verification/status');
        exit;
    }

    // ==========================================
    //  VERIFIKASI WHATSAPP (OTP)
    // ==========================================

    /**
     * Request OTP WhatsApp.
     * Dilengkapi cooldown 60 detik dan batas resend 5x.
     */
    public function requestWhatsApp()
    {
        if ($_SESSION['whatsapp_verified']) {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $verifModel = $this->model('Verification_model');

        // Cek cooldown
        $cooldown = $verifModel->getCooldownRemaining($user_id, 'whatsapp');
        if ($cooldown > 0) {
            $_SESSION['verif_error'] = "Mohon tunggu {$cooldown} detik sebelum mengirim ulang kode OTP.";
            header('Location: ' . BASEURL . '/verification/waOtpForm');
            exit;
        }

        // Cek apakah sudah ada token aktif
        $existingToken = $verifModel->getActiveToken($user_id, 'whatsapp');

        // Generate OTP 6 digit yang aman (menggunakan random_int, bukan rand)
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($existingToken) {
            // Cek batas resend
            if ($verifModel->isMaxResendReached($user_id, 'whatsapp')) {
                $_SESSION['verif_error'] = "Anda telah mencapai batas maksimal pengiriman OTP. Silakan coba lagi nanti.";
                header('Location: ' . BASEURL . '/verification/status');
                exit;
            }
            // Update token (resend) — OTP akan di-hash di dalam model
            $verifModel->resendToken($user_id, 'whatsapp', $otp, 5);
        } else {
            // Buat token baru — OTP akan di-hash di dalam model
            $verifModel->createToken($user_id, 'whatsapp', $otp, 5); // 5 menit expired
        }

        // Kirim OTP menggunakan WhatsAppService
        $waService = new WhatsAppService();
        $user = Auth::user();
        $waService->sendOtp($user['whatsapp'], $otp);

        // Tampilkan simulasi di mode development
        if ($waService->isDevelopment()) {
            $_SESSION['verif_sim_msg'] = "<strong>[MODE DEVELOPMENT]</strong> Kode OTP WhatsApp Anda: <strong class='fs-5'>{$otp}</strong>";
        } else {
            $_SESSION['verif_sim_msg'] = "Kode OTP telah dikirim ke nomor WhatsApp Anda.";
        }
        
        header('Location: ' . BASEURL . '/verification/waOtpForm');
        exit;
    }

    /**
     * Tampilkan halaman form input OTP WhatsApp.
     */
    public function waOtpForm()
    {
        if ($_SESSION['whatsapp_verified']) {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        $data['judul'] = 'Verifikasi WhatsApp OTP';
        
        // Data untuk tampilan rate limiting
        $verifModel = $this->model('Verification_model');
        $user_id = $_SESSION['user_id'];
        $activeToken = $verifModel->getActiveToken($user_id, 'whatsapp');
        
        $data['attempts_remaining'] = $activeToken 
            ? max(0, Verification_model::MAX_OTP_ATTEMPTS - $activeToken['attempts']) 
            : Verification_model::MAX_OTP_ATTEMPTS;
        $data['wa_cooldown'] = $verifModel->getCooldownRemaining($user_id, 'whatsapp');
        $data['has_active_otp'] = $activeToken ? true : false;
        
        $this->view('templates/header', $data);
        $this->view('verification/wa_otp', $data);
        $this->view('templates/footer');
    }

    /**
     * Proses verifikasi kode OTP WhatsApp.
     * Dilengkapi batas 5 percobaan — setelahnya OTP dihapus.
     */
    public function verifyWaEndpoint()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        $otp = trim($_POST['otp']);
        $user_id = $_SESSION['user_id'];
        
        $verifModel = $this->model('Verification_model');

        // Cek apakah percobaan sudah melebihi batas
        if ($verifModel->isMaxAttemptsReached($user_id, 'whatsapp')) {
            $verifModel->deleteToken($user_id, 'whatsapp'); // Hapus OTP yang terkunci
            $_SESSION['verif_error'] = "Terlalu banyak percobaan gagal. Kode OTP telah dihapus. Silakan minta kode baru.";
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        }

        $dataToken = $verifModel->getToken($user_id, 'whatsapp', $otp);

        if ($dataToken) {
            // OTP cocok — verifikasi berhasil!
            $this->model('User_model')->verifyWhatsApp($user_id);
            $verifModel->deleteToken($user_id, 'whatsapp');
            
            $_SESSION['whatsapp_verified'] = true;
            $_SESSION['verif_success'] = "Nomor WhatsApp berhasil diverifikasi! ✅";
            header('Location: ' . BASEURL . '/verification/status');
            exit;
        } else {
            // OTP salah — increment percobaan
            $verifModel->incrementAttempt($user_id, 'whatsapp');
            
            // Hitung sisa percobaan
            $activeToken = $verifModel->getActiveToken($user_id, 'whatsapp');
            $remaining = $activeToken 
                ? max(0, Verification_model::MAX_OTP_ATTEMPTS - $activeToken['attempts']) 
                : 0;

            if ($remaining <= 0) {
                $verifModel->deleteToken($user_id, 'whatsapp');
                $_SESSION['verif_error'] = "Terlalu banyak percobaan gagal. Kode OTP telah dihapus. Silakan minta kode baru.";
                header('Location: ' . BASEURL . '/verification/status');
            } else {
                $_SESSION['verif_error'] = "Kode OTP salah atau telah kedaluwarsa. Sisa percobaan: <strong>{$remaining}x</strong>";
                header('Location: ' . BASEURL . '/verification/waOtpForm');
            }
            exit;
        }
    }
}
