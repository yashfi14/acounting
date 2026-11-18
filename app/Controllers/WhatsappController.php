<?php

require_once __DIR__ . '/../Services/WhatsApp/WhatsAppService.php';

/**
 * WhatsappController — Manajemen Notifikasi WhatsApp & Koneksi Nomor via OTP.
 * Role: Admin (Pemilik Toko)
 */
class WhatsappController extends Controller {

    public function __construct()
    {
        AuthHelper::checkVerified();
        RoleMiddleware::allow(['admin']);
    }

    /**
     * Halaman Utama Pengaturan Notifikasi WhatsApp
     */
    public function index()
    {
        $data['judul'] = 'Notifikasi WhatsApp';
        $business_id   = AuthHelper::businessId();
        $user          = Auth::user();

        $waSettingModel = $this->model('WaSetting_model');
        $waNotifModel   = $this->model('WaNotification_model');

        $setting = $waSettingModel->getByUserId($business_id);
        $logs    = $waNotifModel->getRecentLogs($business_id, 20);
        $stats   = $waNotifModel->getStats($business_id);

        $data['setting']       = $setting;
        $data['logs']          = $logs;
        $data['stats']         = $stats;
        $data['default_phone'] = $setting['phone_number'] ?? ($user['whatsapp'] ?? '');
        $data['is_connected']  = !empty($setting['is_connected']) && (int)$setting['is_connected'] === 1;

        $waService = new WhatsAppService($waSettingModel->getDecryptedApiKey($business_id));
        $data['is_dev_mode']   = $waService->isDevelopment();
        $data['has_real_gateway'] = $waService->hasRealGateway();

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('whatsapp/index', $data);
        $this->view('templates/footer');
    }

    /**
     * Endpoint AJAX untuk mengirim kode OTP ke nomor WhatsApp baru
     */
    public function sendOtp()
    {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (!$input) {
            $input = $_POST;
        }

        // Validasi CSRF Token
        $csrfToken = $input['csrf_token'] ?? '';
        if (!CSRF::validate($csrfToken)) {
            echo json_encode([
                'success' => false,
                'message' => 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.'
            ]);
            exit;
        }

        $rawPhone = trim($input['phone_number'] ?? '');
        $apiKey   = trim($input['api_key'] ?? '');

        // Normalisasi nomor telepon ke format internasional (628xxx)
        $cleanPhone = WhatsAppService::normalizeNumber($rawPhone);

        // Validasi format nomor telepon (minimal 9 digit, maksimal 16 digit)
        if (empty($cleanPhone) || strlen($cleanPhone) < 9 || strlen($cleanPhone) > 16) {
            echo json_encode([
                'success' => false,
                'message' => 'Nomor WhatsApp tidak valid. Masukkan nomor yang benar (contoh: 081234567890 atau 6281234567890).'
            ]);
            exit;
        }

        $business_id = AuthHelper::businessId();
        $waSettingModel = $this->model('WaSetting_model');
        $waNotifModel   = $this->model('WaNotification_model');

        // Generate OTP 6 digit aman
        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Token dari form (jika diisi) atau token tersimpan yang sudah didekripsi.
        $effectiveKey = $apiKey !== ''
            ? $apiKey
            : $waSettingModel->getDecryptedApiKey($business_id);

        try {
            // Simpan data pending connection (token baru dienkripsi; kosong = pertahankan).
            $waSettingModel->savePendingConnection(
                $business_id,
                $cleanPhone,
                $otp,
                $apiKey !== '' ? $apiKey : null
            );
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Token gateway gagal disimpan secara aman. Periksa konfigurasi enkripsi server.'
            ]);
            exit;
        }

        // Kirim OTP via WhatsAppService
        $waService = new WhatsAppService($effectiveKey);
        $sendSuccess = $waService->sendOtp($cleanPhone, $otp);

        $isMock = $waService->isMock();

        // Jika user menggunakan real API key dan pengiriman gagal, beri tahu alasannya
        if (!$isMock && !$sendSuccess) {
            $errorMsg = $waService->getLastError() ?: 'Gagal mengirim OTP ke nomor WhatsApp.';
            $waNotifModel->logNotification($business_id, $cleanPhone, 'otp', 'Gagal mengirim kode OTP verifikasi.', 'failed', $errorMsg);
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
            exit;
        }

        $status = $sendSuccess ? ($isMock ? 'simulated' : 'sent') : 'failed';
        $respInfo = $sendSuccess 
            ? ($isMock ? 'Kode OTP simulasi development' : 'Permintaan OTP diterima gateway WhatsApp Fonnte') 
            : $waService->getLastError();

        // Catat ke log notifikasi
        $waNotifModel->logNotification(
            $business_id,
            $cleanPhone,
            'otp',
            'Pengiriman kode OTP verifikasi koneksi WhatsApp.',
            $status,
            $respInfo
        );

        // Siapkan direct WhatsApp Web link
        $otpMessage = "🔐 *KODE VERIFIKASI WHATSAPP*\n*Sistem Acounting Toko*\n----------------------------------------\nKode OTP Anda: *{$otp}*\nBerlaku selama 10 menit.";
        $directWaUrl = WhatsAppService::getDirectWaUrl($cleanPhone, $otpMessage);

        $response = [
            'success'       => true,
            'message'       => $isMock 
                ? 'Kode OTP berhasil dibuat. Gunakan kode di bawah atau buka WhatsApp Web untuk verifikasi.' 
                : 'Permintaan pengiriman OTP telah diterima gateway WhatsApp. Jika pesan belum masuk, gunakan tombol Buka di WhatsApp Web.',
            'phone'         => $cleanPhone,
            'phone_display' => WhatsAppService::formatDisplay($cleanPhone),
            'simulated'     => $isMock,
            'direct_wa_url' => $directWaUrl,
        ];

        if ($isMock) {
            $response['dev_otp'] = $otp;
            $response['dev_hint'] = "Kode OTP Anda: <strong class='fs-4 text-success'>{$otp}</strong> (Berlaku 10 menit)";
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Endpoint AJAX untuk memverifikasi kode OTP yang dimasukkan user
     */
    public function verifyOtp()
    {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (!$input) {
            $input = $_POST;
        }

        // Validasi CSRF
        $csrfToken = $input['csrf_token'] ?? '';
        if (!CSRF::validate($csrfToken)) {
            echo json_encode([
                'success' => false,
                'message' => 'Token keamanan (CSRF) tidak valid. Silakan muat ulang halaman.'
            ]);
            exit;
        }

        $otp = trim($input['otp'] ?? '');
        if (empty($otp) || strlen($otp) !== 6 || !ctype_digit($otp)) {
            echo json_encode([
                'success' => false,
                'message' => 'Masukkan 6 digit kode OTP angka yang benar.'
            ]);
            exit;
        }

        $business_id = AuthHelper::businessId();
        $waSettingModel = $this->model('WaSetting_model');
        $waNotifModel   = $this->model('WaNotification_model');

        $result = $waSettingModel->verifyOtp($business_id, $otp);

        if ($result['success']) {
            $phone = $result['phone_number'] ?? '';
            $waNotifModel->logNotification(
                $business_id,
                $phone,
                'otp_verified',
                'Nomor WhatsApp berhasil diverifikasi dan terhubung ke aplikasi.',
                'sent',
                'Verifikasi OTP Berhasil'
            );

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $result['new_csrf'] = $_SESSION['csrf_token'];
        }

        echo json_encode($result);
        exit;
    }

    /**
     * Endpoint untuk menyimpan pengaturan opsi notifikasi otomatis
     */
    public function saveSettings()
    {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (!$input) {
            $input = $_POST;
        }

        $csrfToken = $input['csrf_token'] ?? '';
        if (!CSRF::validate($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
            exit;
        }

        $business_id    = AuthHelper::businessId();
        $waSettingModel = $this->model('WaSetting_model');

        $apiKey = trim($input['api_key'] ?? '');

        $data = [
            'notify_transaction'  => !empty($input['notify_transaction']) ? 1 : 0,
            'notify_low_stock'    => !empty($input['notify_low_stock']) ? 1 : 0,
            'low_stock_threshold' => (int)($input['low_stock_threshold'] ?? 5),
            'api_key'             => $apiKey,
        ];

        try {
            $waSettingModel->updateOptions($business_id, $data);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Pengaturan gagal disimpan. Token gateway tidak dapat dienkripsi; periksa konfigurasi server.'
            ]);
            exit;
        }

        $note = $apiKey !== ''
            ? ' Token Fonnte disimpan terenkripsi. Gunakan tombol Kirim Notifikasi untuk menguji pengiriman.'
            : ' Token Fonnte yang sudah tersimpan tidak diubah.';

        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan notifikasi WhatsApp berhasil disimpan.' . $note
        ]);
        exit;
    }

    /**
     * Endpoint untuk mengirim pesan notifikasi uji coba (Test Message)
     */
    public function sendTest()
    {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (!$input) {
            $input = $_POST;
        }

        $csrfToken = $input['csrf_token'] ?? '';
        if (!CSRF::validate($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
            exit;
        }

        $business_id    = AuthHelper::businessId();
        $waSettingModel = $this->model('WaSetting_model');
        $waNotifModel   = $this->model('WaNotification_model');

        $setting = $waSettingModel->getByUserId($business_id);
        if (!$setting || (int)$setting['is_connected'] !== 1) {
            echo json_encode([
                'success' => false,
                'message' => 'Nomor WhatsApp belum terhubung. Silakan lakukan verifikasi OTP terlebih dahulu.'
            ]);
            exit;
        }

        $rawPhone = !empty($input['phone_number']) ? $input['phone_number'] : $setting['phone_number'];
        $targetPhone = WhatsAppService::normalizeNumber($rawPhone);

        $customMessage = trim($input['message'] ?? '');
        if (empty($customMessage)) {
            $date = date('d M Y, H:i');
            $customMessage = "🔔 *PESAN NOTIFIKASI UJI COBA*\n"
                           . "*Sistem Acounting Toko*\n"
                           . "----------------------------------------\n"
                           . "Halo! Ini adalah pesan pengujian koneksi WhatsApp dari sistem Acounting.\n"
                           . "Waktu Pengujian : {$date} WIB\n"
                           . "Status Koneksi  : *AKTIF & TERHUBUNG*\n"
                           . "----------------------------------------\n"
                           . "Selamat, sistem notifikasi otomatis WhatsApp Anda telah siap digunakan!";
        }

        $waService = new WhatsAppService($waSettingModel->getDecryptedApiKey($business_id));
        $directWaUrl = WhatsAppService::getDirectWaUrl($targetPhone, $customMessage);

        $isMock = $waService->isMock();
        $sent = $waService->sendMessage($targetPhone, $customMessage);

        // Jika user menggunakan gateway nyata dan Fonnte mengembalikan error
        if (!$isMock && !$sent) {
            $errorMsg = $waService->getLastError() ?: 'Gagal mengirim pesan melalui gateway Fonnte.';
            $waNotifModel->logNotification($business_id, $targetPhone, 'test', $customMessage, 'failed', $errorMsg);
            echo json_encode([
                'success'       => false,
                'message'       => $errorMsg,
                'direct_wa_url' => $directWaUrl
            ]);
            exit;
        }

        $status = $sent ? ($isMock ? 'simulated' : 'sent') : 'failed';
        $info = $sent 
            ? ($isMock ? 'Pesan dicatat di sistem (Token Fonnte belum dimasukkan)' : 'Berhasil dikirim nyata ke HP via Fonnte') 
            : ($waService->getLastError() ?: 'Gagal mengirim pesan.');

        $waNotifModel->logNotification($business_id, $targetPhone, 'test', $customMessage, $status, $info);

        echo json_encode([
            'success'       => true,
            'message'       => $isMock 
                ? 'Pesan uji coba berhasil dicatat di sistem. Karena belum ada Token Fonnte, gunakan tombol "Buka di WhatsApp Web" untuk mengirim langsung ke HP.' 
                : 'Pesan notifikasi WhatsApp berhasil dikirim langsung ke nomor ' . WhatsAppService::formatDisplay($targetPhone) . '!',
            'simulated'     => $isMock,
            'status'        => $status,
            'info'          => $info,
            'direct_wa_url' => $directWaUrl,
            'phone_display' => WhatsAppService::formatDisplay($targetPhone)
        ]);
        exit;
    }

    /**
     * Endpoint untuk memutuskan koneksi nomor WhatsApp
     */
    public function disconnect()
    {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (!$input) {
            $input = $_POST;
        }

        $csrfToken = $input['csrf_token'] ?? '';
        if (!CSRF::validate($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
            exit;
        }

        $business_id    = AuthHelper::businessId();
        $waSettingModel = $this->model('WaSetting_model');
        $waNotifModel   = $this->model('WaNotification_model');

        $setting = $waSettingModel->getByUserId($business_id);
        $phone = $setting['phone_number'] ?? '-';

        $waSettingModel->disconnect($business_id);

        $waNotifModel->logNotification(
            $business_id,
            $phone,
            'custom',
            'Koneksi nomor WhatsApp telah diputuskan oleh pengguna.',
            'sent',
            'Koneksi Terputus'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Koneksi nomor WhatsApp berhasil diputuskan. Silakan masukkan nomor baru yang ingin dihubungkan.'
        ]);
        exit;
    }
}
