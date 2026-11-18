<?php

require_once __DIR__ . '/ApplicationKnowledgeService.php';
require_once __DIR__ . '/AISharedPromptService.php';

/**
 * GeminiService — Integrasi resmi Google Gemini API dari backend PHP.
 *
 * Perbaikan penting (2026):
 * - ListModels saja TIDAK cukup: Google masih mengembalikan model lama
 *   (mis. gemini-2.5-flash) di daftar model, tetapi generateContent dapat
 *   membalas 404 "no longer available to new users". Karena itu validasi
 *   model dilakukan dengan probe generateContent sungguhan.
 * - Error Google dipetakan ke status yang spesifik (invalid key, model
 *   tidak tersedia, kuota/rate limit, billing/permission, gangguan server).
 * - Jika Google menyarankan model pengganti, sarannya diteruskan ke UI.
 * - API key hanya dikirim via header x-goog-api-key, tidak pernah ke URL,
 *   frontend, atau log. Semua pesan error disanitasi.
 * - Mendukung pagination ListModels dan fallback API version (v1beta -> v1)
 *   hanya untuk error versi API, bukan untuk model yang sudah pensiun.
 */
class GeminiService
{
    private const API_VERSIONS = ['v1beta', 'v1'];
    private const API_HOST = 'https://generativelanguage.googleapis.com';
    private const HTTP_TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 8;
    private const MAX_PAGES = 10;

    /**
     * Daftar model yang mendukung generateContent.
     *
     * @param array $config        Konfigurasi efektif (api_key, model, ...)
     * @param bool  $probeCurrent  True = probe model aktif dengan generateContent
     *                             untuk memastikan model masih benar-benar hidup.
     */
    public function listModels(array $config, bool $probeCurrent = false): array
    {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return [
                'success' => false,
                'code'    => 'not_configured',
                'message' => 'API key Gemini belum dikonfigurasi. Simpan API key terlebih dahulu.',
                'models'  => [],
            ];
        }

        $models = [];
        $pageToken = '';
        $pages = 0;

        do {
            $path = '/models?pageSize=200';
            if ($pageToken !== '') {
                $path .= '&pageToken=' . rawurlencode($pageToken);
            }

            $response = $this->requestWithVersionFallback('GET', $path, $apiKey);
            if (!$response['ok']) {
                return [
                    'success' => false,
                    'code'    => $this->mapErrorCode($response),
                    'message' => $this->mapErrorMessage($response),
                    'models'  => [],
                ];
            }

            foreach (($response['json']['models'] ?? []) as $model) {
                $name = (string)($model['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $id = preg_replace('#^models/#', '', $name);
                $methods = $model['supportedGenerationMethods'] ?? [];
                if (!is_array($methods) || !in_array('generateContent', $methods, true)) {
                    continue;
                }
                $models[$id] = [
                    'id'                 => $id,
                    'name'               => (string)($model['displayName'] ?? $id),
                    'description'        => (string)($model['description'] ?? ''),
                    'version'            => (string)($model['version'] ?? ''),
                    'input_token_limit'  => isset($model['inputTokenLimit']) ? (int)$model['inputTokenLimit'] : null,
                    'output_token_limit' => isset($model['outputTokenLimit']) ? (int)$model['outputTokenLimit'] : null,
                ];
            }

            $pageToken = (string)($response['json']['nextPageToken'] ?? '');
            $pages++;
        } while ($pageToken !== '' && $pages < self::MAX_PAGES);

        $models = array_values($models);
        usort($models, static fn($a, $b) => strcmp($a['id'], $b['id']));

        $result = [
            'success' => true,
            'code'    => 'ok',
            'message' => count($models) . ' model mendukung generateContent.',
            'models'  => $models,
        ];

        $currentModel = $this->normalizeModel($config['model'] ?? '');
        $result['current_model'] = $currentModel;

        if ($currentModel !== '') {
            $availableIds = array_column($models, 'id');
            if (in_array($currentModel, $availableIds, true)) {
                $result['current_available'] = true;
                if ($probeCurrent) {
                    $probe = $this->probeModel($config, $currentModel);
                    $result['current_available'] = (bool)$probe['available'];
                    $result['current_probe'] = [
                        'available' => (bool)$probe['available'],
                        'code'      => $probe['code'],
                        'message'   => $probe['message'],
                    ];
                    if (!$probe['available']) {
                        if (!empty($probe['suggested_model'])) {
                            $result['suggested_model'] = $probe['suggested_model'];
                        }
                        $result['message'] = 'Daftar model dimuat, tetapi model aktif "' . $currentModel . '" gagal diuji: ' . $probe['message'];
                    }
                }
            } else {
                $result['current_available'] = false;
                $result['message'] = 'Daftar model dimuat, tetapi model aktif "' . $currentModel . '" tidak ada dalam daftar untuk API key ini.';
            }
        }

        if (isset($result['current_available']) && $result['current_available'] === false) {
            $ids = array_values(array_diff(array_column($models, 'id'), [$currentModel]));
            $recommended = $this->recommendModel($ids);
            if ($recommended !== '') {
                $result['recommended_model'] = $recommended;
            }
        }

        return $result;
    }

    /**
     * Validasi model benar-benar tersedia dan masih bisa dipakai generateContent.
     */
    public function validateModel(array $config): array
    {
        $selected = $this->normalizeModel($config['model'] ?? '');
        if ($selected === '') {
            return [
                'success' => false,
                'code'    => 'model_unavailable',
                'message' => 'Model Gemini belum dipilih.',
                'models'  => [],
            ];
        }

        $list = $this->listModels($config);
        if (!$list['success']) {
            return $list;
        }

        $available = array_column($list['models'], 'id');
        if (!in_array($selected, $available, true)) {
            $suggestion = $this->recommendModel($available);
            $message = 'Model "' . $selected . '" tidak tersedia untuk API key ini.';
            if ($suggestion !== '') {
                $message .= ' Model yang disarankan: ' . $suggestion . '.';
            }
            return [
                'success'          => false,
                'code'             => 'model_unavailable',
                'message'          => $message,
                'models'           => $list['models'],
                'suggested_model'  => $suggestion,
            ];
        }

        // ListModels dapat memuat model yang sudah pensiun untuk pengguna baru.
        $probe = $this->probeModel($config, $selected);
        if (!$probe['available']) {
            return [
                'success'          => false,
                'code'             => $probe['code'],
                'message'          => $probe['message'],
                'models'           => $list['models'],
                'suggested_model'  => $probe['suggested_model'] ?? $this->recommendModel(array_values(array_diff($available, [$selected]))),
            ];
        }

        return [
            'success' => true,
            'code'    => 'ok',
            'message' => 'Model "' . $selected . '" tersedia dan merespons.',
            'models'  => $list['models'],
        ];
    }

    /**
     * Uji koneksi nyata: generateContent singkat ke model yang dipilih.
     * Status "Terhubung" hanya diberikan jika Google benar-benar merespons.
     */
    public function testConnection(array $config): array
    {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return ['success' => false, 'code' => 'not_configured', 'message' => 'API key Gemini belum dikonfigurasi.'];
        }

        $model = $this->normalizeModel($config['model'] ?? '');
        if ($model === '') {
            return ['success' => false, 'code' => 'model_unavailable', 'message' => 'Model Gemini belum dipilih.'];
        }

        $started = microtime(true);
        $probe = $this->probeModel($config, $model);
        $elapsed = (int)round((microtime(true) - $started) * 1000);

        if (!$probe['available']) {
            return [
                'success'          => false,
                'code'             => $probe['code'],
                'message'          => $probe['message'],
                'suggested_model'  => $probe['suggested_model'] ?? null,
                'duration_ms'      => $elapsed,
            ];
        }

        $detail = 'Koneksi ke Gemini berhasil. Model "' . $model . '" merespons';
        if (!empty($probe['finish_reason'])) {
            $detail .= ' (finishReason: ' . $probe['finish_reason'] . ')';
        }
        $detail .= '.';

        return [
            'success'      => true,
            'code'         => 'connected',
            'message'      => $detail,
            'model'        => $model,
            'duration_ms'  => $elapsed,
        ];
    }

    /**
     * Menghasilkan jawaban Gemini dengan konteks minimum yang sudah diotorisasi.
     *
     * @param array $config  api_key, model, temperature, max_output_tokens
     * @param array $context user_role, user_name, grounding, knowledge (opsional)
     * @param array $history [['role'=>'user'|'model','text'=>string], ...]
     */
    public function generate(string $question, array $config, array $context = [], array $history = []): string
    {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini belum dikonfigurasi.');
        }

        $model = $this->normalizeModel($config['model'] ?? '');
        if ($model === '') {
            throw new RuntimeException('Model Gemini tidak valid.');
        }

        $systemPrompt = $this->buildSystemPrompt($context);
        $contents = [];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string)($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 1200) {
                $text = mb_substr($text, 0, 1200) . '…';
            }
            $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => "PERTANYAAN PENGGUNA:\n" . $question]]];

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => $contents,
            'generationConfig'  => [
                'temperature'     => $this->clampTemperature($config['temperature'] ?? 0.3),
                'maxOutputTokens' => $this->clampTokens($config['max_output_tokens'] ?? 800),
            ],
        ];

        $response = $this->requestWithVersionFallback(
            'POST',
            '/models/' . rawurlencode($model) . ':generateContent',
            $apiKey,
            $payload
        );

        if (!$response['ok']) {
            throw new RuntimeException($this->mapErrorMessage($response));
        }

        $text = trim($this->extractText($response['json']));
        if ($text === '') {
            $finish = $this->finishReason($response['json']);
            if ($finish !== '') {
                throw new RuntimeException('Gemini tidak memberikan jawaban (finishReason: ' . $finish . ').');
            }
            throw new RuntimeException('Gemini tidak memberikan jawaban.');
        }

        return $text;
    }

    /**
     * Probe generateContent sangat singkat untuk memastikan model hidup.
     * Model yang sudah pensiun akan dibalas HTTP 404 walau masih ada di ListModels.
     */
    private function probeModel(array $config, string $model): array
    {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return ['available' => false, 'code' => 'not_configured', 'message' => 'API key Gemini belum dikonfigurasi.', 'suggested_model' => null];
        }

        $payload = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => 'Balas dengan tepat satu kata: OK']],
            ]],
            'generationConfig' => [
                'temperature'     => 0,
                'maxOutputTokens' => 64,
            ],
        ];

        $response = $this->requestWithVersionFallback(
            'POST',
            '/models/' . rawurlencode($model) . ':generateContent',
            $apiKey,
            $payload
        );

        if (!$response['ok']) {
            return [
                'available'       => false,
                'code'            => $this->mapErrorCode($response),
                'message'         => $this->mapErrorMessage($response),
                'suggested_model' => $this->suggestedModelFromBody($response['body'], $model),
            ];
        }

        return [
            'available'       => true,
            'code'            => 'connected',
            'message'         => 'Model merespons.',
            'finish_reason'   => $this->finishReason($response['json']),
            'suggested_model' => null,
        ];
    }

    private function buildSystemPrompt(array $context): string
    {
        return AISharedPromptService::buildSystemPrompt($context);
    }

    /**
     * Request dengan fallback versi API. Fallback hanya dilakukan jika error
     * memang menandakan versi API tidak mendukung endpoint/model, bukan model pensiun.
     */
    private function requestWithVersionFallback(string $method, string $path, string $apiKey, ?array $payload = null): array
    {
        $last = null;
        foreach (self::API_VERSIONS as $index => $version) {
            $response = $this->request($method, self::API_HOST . '/' . $version . $path, $apiKey, $payload);
            $last = $response;
            if ($response['ok']) {
                $response['api_version'] = $version;
                return $response;
            }

            $isLast = $index === count(self::API_VERSIONS) - 1;
            if ($isLast || !$this->isApiVersionMismatch($response)) {
                break;
            }
        }

        return $last;
    }

    private function isApiVersionMismatch(array $response): bool
    {
        if ((int)($response['http'] ?? 0) !== 404) {
            return false;
        }
        $body = mb_strtolower((string)($response['body'] ?? ''));

        // "models/xxx is not found for API version v1beta" berarti model tidak
        // ada, bukan versi API salah. Jangan retry ke v1 untuk kasus ini.
        if (strpos($body, 'models/') !== false) {
            return false;
        }

        return strpos($body, 'method not found') !== false
            || strpos($body, 'not supported in') !== false
            || (strpos($body, 'api version') !== false && strpos($body, 'not found') === false);
    }

    private function request(string $method, string $url, string $apiKey, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'http' => 0, 'body' => '', 'json' => [], 'error' => 'Ekstensi cURL tidak tersedia.'];
        }

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-goog-api-key: ' . $apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'Acounting-Aca/1.0 (+PHP cURL)',
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $body      = curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlNo    = (int)curl_errno($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            // Jangan sertakan URL/header (berisi API key) pada pesan error.
            $detail = 'Koneksi ke Gemini gagal. Periksa jaringan server.';
            if (in_array($curlNo, [CURLE_OPERATION_TIMEDOUT, 28], true)) {
                $detail = 'Koneksi ke Gemini melewati batas waktu. Coba lagi.';
            }
            return [
                'ok'    => false,
                'http'  => 0,
                'body'  => '',
                'json'  => [],
                'error' => $detail,
            ];
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            $json = [];
        }

        return [
            'ok'    => $httpCode >= 200 && $httpCode < 300,
            'http'  => $httpCode,
            'body'  => (string)$body,
            'json'  => $json,
            'error' => '',
        ];
    }

    private function extractText(array $json): string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }
        return $text;
    }

    private function finishReason(array $json): string
    {
        return (string)($json['candidates'][0]['finishReason'] ?? '');
    }

    private function mapErrorCode(array $response): string
    {
        $http = (int)($response['http'] ?? 0);
        $body = (string)($response['body'] ?? '');
        $lower = mb_strtolower($body);

        if ($http === 0) {
            return 'connection_error';
        }

        $isKeyError = $http === 401
            || strpos($lower, 'api key not valid') !== false
            || strpos($lower, 'api_key_invalid') !== false
            || strpos($lower, 'invalid api key') !== false;

        $isBilling = strpos($lower, 'billing') !== false
            || strpos($lower, 'permission denied') !== false
            || strpos($lower, 'permission_denied') !== false
            || strpos($lower, 'service_disabled') !== false
            || strpos($lower, 'has not been used in project') !== false;

        if ($isKeyError || ($http === 403 && strpos($lower, 'api key') !== false)) {
            return 'invalid_key';
        }

        if ($http === 404) {
            return 'model_unavailable';
        }

        if ($http === 429 || strpos($lower, 'resource_exhausted') !== false || strpos($lower, 'exceeded your current quota') !== false) {
            return 'rate_limited';
        }

        if ($isBilling) {
            return 'billing_error';
        }

        if ($http === 400) {
            if (strpos($lower, 'not supported') !== false
                || strpos($lower, 'no longer available') !== false
                || strpos($lower, 'is not found') !== false) {
                return 'model_unavailable';
            }
            if (strpos($lower, 'api key') !== false) {
                return 'invalid_key';
            }
            return 'api_error';
        }

        if ($http === 401 || $http === 403) {
            return $isKeyError ? 'invalid_key' : 'billing_error';
        }

        if ($http >= 500) {
            return 'server_error';
        }

        return 'api_error';
    }

    private function mapErrorMessage(array $response): string
    {
        $http       = (int)($response['http'] ?? 0);
        $body       = (string)($response['body'] ?? '');
        $apiKey     = '';
        $json       = json_decode($body, true);
        $apiMessage = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
        $apiMessage = $this->sanitize($apiMessage, $apiKey);

        switch ($this->mapErrorCode($response)) {
            case 'connection_error':
                return $response['error'] ?? 'Tidak dapat terhubung ke Gemini. Periksa koneksi internet server.';
            case 'invalid_key':
                return 'API key Gemini tidak valid atau tidak memiliki izin. Periksa kembali API key dari Google AI Studio.';
            case 'model_unavailable':
                $message = 'Model Gemini tidak tersedia untuk API key ini.';
                $suggestion = $this->suggestedModelFromBody($body, '');
                if ($suggestion !== '') {
                    $message .= ' Model yang disarankan Google: ' . $suggestion . '. Klik Muat Model lalu pilih model tersebut.';
                } elseif ($apiMessage !== '') {
                    $message .= ' ' . mb_substr($apiMessage, 0, 200);
                }
                return $message;
            case 'rate_limited':
                return 'Kuota atau rate limit Gemini habis. Tunggu beberapa saat lalu coba lagi.';
            case 'billing_error':
                return 'Billing atau izin Google Cloud bermasalah untuk API key ini. Periksa billing/aktifkan Generative Language API di Google Cloud.';
            case 'server_error':
                return 'Server Gemini sedang bermasalah (HTTP ' . $http . '). Coba lagi beberapa saat lagi.';
            default:
                return $apiMessage !== ''
                    ? 'Gemini API error: ' . mb_substr($apiMessage, 0, 200)
                    : 'Terjadi kesalahan pada Gemini API (HTTP ' . $http . ').';
        }
    }

    /**
     * Ambil saran model pengganti dari pesan Google, mis.
     * "Please update your code to use models/gemini-3.8-flash".
     */
    private function suggestedModelFromBody(string $body, string $currentModel): string
    {
        $json = json_decode($body, true);
        $message = is_array($json) ? (string)($json['error']['message'] ?? '') : '';

        if ($message === '') {
            return '';
        }

        $patterns = [
            '/use\s+models\/([A-Za-z0-9._-]+)/i',
            '/update[^.]*?models\/([A-Za-z0-9._-]+)/i',
            '/migrate[^.]*?models\/([A-Za-z0-9._-]+)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m)) {
                $candidate = $this->normalizeModel($m[1]);
                if ($candidate !== '' && $candidate !== $currentModel) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    /**
     * Saran model chat yang wajar dari daftar yang tersedia.
     * Hanya saran UI — tidak pernah mengganti model secara otomatis.
     */
    public function recommendModel(array $available): string
    {
        $nonChat = ['tts', 'image', 'audio', 'transcribe', 'robotics', 'lyria', 'computer-use', 'deep-research', 'antigravity', 'nano-banana'];

        $ranked = [];
        foreach ($available as $id) {
            $id = (string)$id;
            if ($id === '') {
                continue;
            }
            $lower = strtolower($id);
            foreach ($nonChat as $marker) {
                if (strpos($lower, $marker) !== false) {
                    continue 2;
                }
            }

            $score = 0;
            if ($id === 'gemini-flash-lite-latest') $score += 20;
            if (substr($id, -7) === '-latest') $score += 6;
            if (strpos($id, 'lite') !== false) $score += 3;
            if (strpos($id, 'flash') !== false) $score += 2;
            if (strpos($id, 'preview') !== false) $score -= 1;

            $ranked[] = ['id' => $id, 'score' => $score];
        }

        usort($ranked, static function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($b['id'], $a['id']);
            }
            return $b['score'] <=> $a['score'];
        });

        return $ranked[0]['id'] ?? '';
    }

    private function sanitize(string $text, string $apiKey): string
    {
        if ($apiKey !== '') {
            $text = str_replace($apiKey, '[REDACTED]', $text);
        }
        $text = preg_replace('/AIza[0-9A-Za-z\-_]{20,}/', '[REDACTED]', $text);
        $text = preg_replace('/\bAQ\.[0-9A-Za-z._\-]{20,}/', '[REDACTED]', $text);
        $text = preg_replace('/key=[^\s&]+/i', 'key=[REDACTED]', (string)$text);
        return trim(strip_tags((string)$text));
    }

    private function normalizeModel(string $model): string
    {
        $model = trim($model);
        $model = preg_replace('#^models/#', '', $model);
        if (!preg_match('/^[A-Za-z0-9._-]{2,100}$/', $model)) {
            return '';
        }
        return $model;
    }

    private function clampTemperature($value): float
    {
        $value = (float)$value;
        if ($value < 0) return 0.0;
        if ($value > 2) return 2.0;
        return round($value, 2);
    }

    private function clampTokens($value): int
    {
        $value = (int)$value;
        if ($value < 32) return 32;
        if ($value > 8192) return 8192;
        return $value;
    }
}
