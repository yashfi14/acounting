<?php

require_once __DIR__ . '/../Services/AI/AIService.php';

class AIController extends Controller {

    private const MAX_QUESTION_LENGTH = 1000;
    private const RATE_LIMIT_MAX = 20;
    private const RATE_LIMIT_WINDOW = 60;

    public function __construct()
    {
        AuthHelper::check();
    }

    public function index()
    {
        $data['judul'] = 'AI Assistant';
        $user_id = $_SESSION['user_id'];

        $aiModel = $this->model('AIConversation_model');
        $data['history'] = $aiModel->getHistory($user_id);

        $this->view('templates/header', $data);
        $this->view('templates/sidebar', $data);
        $this->view('ai/index', $data);
        $this->view('templates/footer');
    }

    public function ask()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
            return;
        }

        $input = $this->jsonInput();

        if (!isset($input['csrf_token']) || !CSRF::validate($input['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan refresh halaman.'], 403);
            return;
        }

        $question = isset($input['question']) ? trim((string)$input['question']) : '';
        if ($question === '') {
            $this->json(['success' => false, 'message' => 'Pertanyaan tidak boleh kosong.'], 422);
            return;
        }

        $length = mb_strlen($question, 'UTF-8');
        if ($length > self::MAX_QUESTION_LENGTH) {
            $this->json([
                'success' => false,
                'message' => 'Pertanyaan terlalu panjang (maksimal ' . self::MAX_QUESTION_LENGTH . ' karakter).',
            ], 422);
            return;
        }

        if (!$this->allowRequest()) {
            $this->json([
                'success' => false,
                'message' => 'Terlalu banyak pertanyaan dalam waktu singkat. Tunggu sekitar satu menit lalu coba lagi.',
            ], 429);
            return;
        }

        $user_id = (int)$_SESSION['user_id'];
        $startedAt = microtime(true);

        try {
            $aiService = new AIService($user_id);
            $answer = $aiService->getAnswer($question, $user_id);

            $aiModel = $this->model('AIConversation_model');
            $aiModel->saveConversation($user_id, $question, $answer);

            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            $aiService->logQuery($user_id, (string)($_SESSION['user_role'] ?? 'pelanggan'), $question, $durationMs, true);

            $this->json([
                'success'  => true,
                'answer'   => $answer,
                'provider' => $aiService->getLastProvider(),
                'intent'   => $aiService->getLastIntent(),
                'fallback' => $aiService->getLastFallbackReason(),
            ]);
        } catch (Throwable $e) {
            error_log('[AIController] Gagal memproses pertanyaan: ' . $e->getMessage());
            try {
                $logger = new AIDataService();
                $logger->logQuery(
                    $user_id,
                    (string)($_SESSION['user_role'] ?? 'pelanggan'),
                    'error',
                    'exception',
                    mb_strlen($question, 'UTF-8'),
                    (int)round((microtime(true) - $startedAt) * 1000),
                    false
                );
            } catch (Throwable $ignored) {
                // Audit tidak boleh menutupi error utama.
            }
            $this->json([
                'success' => false,
                'message' => 'Maaf, terjadi kendala saat memproses pertanyaan. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Riwayat percakapan untuk floating widget.
     */
    public function history()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
            return;
        }

        try {
            $user_id = (int)$_SESSION['user_id'];
            $aiModel = $this->model('AIConversation_model');
            $rows = $aiModel->getHistory($user_id);
            $rows = array_slice($rows, -50);

            $history = [];
            foreach ($rows as $row) {
                $history[] = [
                    'question'   => $row['question'] ?? '',
                    'answer'     => $row['answer'] ?? '',
                    'created_at' => $row['created_at'] ?? null,
                ];
            }

            $this->json(['success' => true, 'history' => $history]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Gagal memuat riwayat percakapan.', 'history' => []], 500);
        }
    }

    public function clear()
    {
        $this->clearHistory();
    }

    public function clearHistory()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
            return;
        }

        $input = $this->jsonInput();
        if (!isset($input['csrf_token']) || !CSRF::validate($input['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'Token keamanan (CSRF) tidak valid.'], 403);
            return;
        }

        try {
            $user_id = (int)$_SESSION['user_id'];
            $aiModel = $this->model('AIConversation_model');
            $aiModel->clearHistory($user_id);
            $this->json(['success' => true, 'message' => 'Riwayat percakapan berhasil dibersihkan.']);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Gagal menghapus riwayat percakapan.'], 500);
        }
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function allowRequest(): bool
    {
        $now = time();
        $bucket = $_SESSION['ai_rate_limit'] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }

        $bucket = array_values(array_filter($bucket, static function ($ts) use ($now) {
            return is_int($ts) && ($now - $ts) < self::RATE_LIMIT_WINDOW;
        }));

        if (count($bucket) >= self::RATE_LIMIT_MAX) {
            $_SESSION['ai_rate_limit'] = $bucket;
            return false;
        }

        $bucket[] = $now;
        $_SESSION['ai_rate_limit'] = $bucket;
        return true;
    }
}
