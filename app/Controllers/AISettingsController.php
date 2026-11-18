<?php

require_once __DIR__ . '/../Services/AI/AISettingsService.php';

/**
 * AISettingsController — Pengaturan Aca multi-provider khusus Admin.
 *
 * API key hanya dikirim satu arah (frontend -> backend), tidak pernah
 * dikembalikan ke browser. Semua endpoint JSON memerlukan CSRF + role Admin.
 */
class AISettingsController extends Controller
{
    private AISettingsService $settings;

    public function __construct()
    {
        AuthHelper::check();
        $this->settings = new AISettingsService();
    }

    public function index()
    {
        if (!AuthHelper::isAdmin()) {
            header('Location: ' . BASEURL . '/dashboard');
            exit;
        }

        $adminId = (int)($_SESSION['user_id'] ?? 0);

        $data['judul'] = 'Pengaturan Aca';
        $data['settings'] = $this->settings->getSafeSettings($adminId);
        $data['audit_logs'] = $this->settings->getAuditLogs($adminId, 20);
        $data['models'] = [];

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('ai/settings', $data);
        $this->view('templates/footer');
    }

    public function save()
    {
        $input = $this->guardPost();
        if ($input === null) return;

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->settings->save($adminId, [
            'provider'          => $input['provider'] ?? 'gemini',
            'model'             => $input['model'] ?? '',
            'base_url'          => $input['base_url'] ?? '',
            'temperature'       => $input['temperature'] ?? 0.3,
            'max_output_tokens' => $input['max_output_tokens'] ?? 800,
            'api_key'           => $input['api_key'] ?? '',
            'remove_api_key'    => $input['remove_api_key'] ?? false,
        ]);

        $result['settings'] = $this->settings->getSafeSettings($adminId);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Uji koneksi nyata. Mendukung pengujian nilai form yang belum disimpan.
     * Provider hanya diaktifkan jika pengujian berhasil.
     */
    public function test()
    {
        $input = $this->guardPost();
        if ($input === null) return;

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->settings->testAndActivate($adminId, [
            'provider'          => $input['provider'] ?? 'gemini',
            'model'             => $input['model'] ?? '',
            'base_url'          => $input['base_url'] ?? '',
            'temperature'       => $input['temperature'] ?? 0.3,
            'max_output_tokens' => $input['max_output_tokens'] ?? 800,
            'api_key'           => $input['api_key'] ?? '',
            'activate'          => $input['activate'] ?? 'test',
        ]);
        $result['settings'] = $this->settings->getSafeSettings($adminId);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    public function models()
    {
        $input = $this->guardPost();
        if ($input === null) return;

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->settings->listModels($adminId, [
            'provider' => $input['provider'] ?? '',
            'model'    => $input['model'] ?? '',
            'base_url' => $input['base_url'] ?? '',
            'api_key'  => $input['api_key'] ?? '',
        ]);
        $this->json($result, !empty($result['success']) ? 200 : 422);
    }

    public function deleteKey()
    {
        $input = $this->guardPost();
        if ($input === null) return;

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->settings->deleteKey($adminId, (string)($input['provider'] ?? ''));
        $result['settings'] = $this->settings->getSafeSettings($adminId);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    public function clearFallback()
    {
        $input = $this->guardPost();
        if ($input === null) return;

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->settings->clearFallback($adminId);
        $result['settings'] = $this->settings->getSafeSettings($adminId);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    public function toggleStatus()
    {
        $input = $this->guardPost();
        if ($input === null) return;

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $newStatus = isset($input['is_active']) ? (bool)$input['is_active'] : null;
        $result = $this->settings->toggleAiStatus($adminId, $newStatus);
        $result['settings'] = $this->settings->getSafeSettings($adminId);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Validasi role Admin + CSRF untuk request JSON.
     */
    private function guardPost(): ?array
    {
        if (!AuthHelper::isAdmin()) {
            $this->json(['success' => false, 'message' => 'Akses ditolak. Hanya Admin yang dapat mengubah pengaturan Aca.'], 403);
            return null;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
            return null;
        }

        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->json(['success' => false, 'message' => 'Format permintaan tidak valid.'], 400);
            return null;
        }
        if (!isset($input['csrf_token']) || !CSRF::validate($input['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'Token keamanan (CSRF) tidak valid. Refresh halaman lalu coba lagi.'], 403);
            return null;
        }

        return $input;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
