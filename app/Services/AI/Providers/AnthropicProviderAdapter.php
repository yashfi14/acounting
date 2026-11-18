<?php

require_once __DIR__ . '/../AIProviderAdapterInterface.php';
require_once __DIR__ . '/../AIHttpClient.php';
require_once __DIR__ . '/../AIProviderCatalog.php';
require_once __DIR__ . '/../AISharedPromptService.php';

/**
 * Adapter Anthropic Claude (Messages API).
 */
class AnthropicProviderAdapter implements AIProviderAdapterInterface
{
    private array $meta;

    public function __construct()
    {
        $meta = AIProviderCatalog::get('anthropic');
        if (!$meta) {
            throw new RuntimeException('Metadata provider Anthropic tidak ditemukan.');
        }
        $this->meta = $meta;
    }

    public function listModels(array $config): array
    {
        $base = $this->baseUrl($config);
        if ($base === '') {
            return [
                'success' => false,
                'code' => 'invalid_base_url',
                'message' => 'Base URL Anthropic tidak valid.',
                'models' => [],
            ];
        }
        $key = trim((string)($config['api_key'] ?? ''));
        if ($key === '') {
            return [
                'success' => false,
                'code' => 'not_configured',
                'message' => 'API key Anthropic belum dikonfigurasi.',
                'models' => [],
            ];
        }

        $response = AIHttpClient::request(
            'GET',
            $base . '/models?limit=100',
            $this->headers($key),
            null,
            20,
            8
        );
        if (!$response['ok']) {
            return [
                'success' => false,
                'code' => AIHttpClient::errorCode($response),
                'message' => AIHttpClient::errorMessage($response, 'Anthropic Claude'),
                'models' => [],
            ];
        }

        $models = [];
        foreach (($response['json']['data'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = AIHttpClient::sanitizeModel((string)($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $models[$id] = [
                'id' => $id,
                'name' => (string)($item['display_name'] ?? $id),
            ];
        }
        $models = array_values($models);
        usort($models, static function ($a, $b) {
            return strnatcasecmp($a['id'], $b['id']);
        });

        if (empty($models)) {
            return [
                'success' => false,
                'code' => 'model_list_unavailable',
                'message' => 'Daftar model tidak tersedia dari API. Isi nama model Claude secara manual lalu uji koneksi.',
                'models' => [],
            ];
        }

        $current = (string)($config['model'] ?? '');
        $currentAvailable = false;
        foreach ($models as $item) {
            if ($item['id'] === $current) {
                $currentAvailable = true;
                break;
            }
        }

        return [
            'success' => true,
            'code' => 'ok',
            'message' => count($models) . ' model tersedia.',
            'models' => $models,
            'recommended_model' => $models[0]['id'] ?? null,
            'current_model' => $current,
            'current_available' => $currentAvailable,
        ];
    }

    public function testConnection(array $config): array
    {
        $start = microtime(true);
        $model = AIHttpClient::sanitizeModel((string)($config['model'] ?? ''));
        if ($model === '') {
            return [
                'success' => false,
                'code' => 'model_unavailable',
                'message' => 'Nama model Claude belum diisi atau tidak valid.',
                'duration_ms' => 0,
            ];
        }

        $result = $this->messages(
            $config,
            'Ini adalah uji koneksi. Balas tepat satu kata: OK',
            [['role' => 'user', 'content' => 'OK']],
            24,
            0.0
        );
        if (!$result['success']) {
            return [
                'success' => false,
                'code' => $result['code'],
                'message' => $result['message'],
                'duration_ms' => (int)round((microtime(true) - $start) * 1000),
            ];
        }
        if (trim((string)$result['content']) === '') {
            return [
                'success' => false,
                'code' => 'api_error',
                'message' => 'Anthropic merespons, tetapi tidak mengembalikan teks jawaban.',
                'duration_ms' => (int)round((microtime(true) - $start) * 1000),
            ];
        }

        return [
            'success' => true,
            'code' => 'connected',
            'message' => 'Koneksi ke Anthropic Claude berhasil. Model merespons.',
            'model' => $model,
            'duration_ms' => (int)round((microtime(true) - $start) * 1000),
        ];
    }

    public function generate(string $question, array $config, array $context = [], array $history = []): string
    {
        $messages = [];
        foreach ($history as $turn) {
            $role = strtolower((string)($turn['role'] ?? ''));
            if ($role === 'model') {
                $role = 'assistant';
            }
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $text = trim((string)($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 1200) {
                $text = mb_substr($text, 0, 1200) . '...';
            }
            $messages[] = ['role' => $role, 'content' => $text];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $result = $this->messages(
            $config,
            AISharedPromptService::buildSystemPrompt($context),
            $messages,
            max(32, min(8192, (int)($config['max_output_tokens'] ?? 800))),
            max(0.0, min(1.0, (float)($config['temperature'] ?? 0.3)))
        );

        if (!$result['success']) {
            throw new RuntimeException($result['message']);
        }
        $text = trim((string)$result['content']);
        if ($text === '') {
            throw new RuntimeException('Anthropic tidak mengembalikan jawaban.');
        }
        return $text;
    }

    private function messages(array $config, string $system, array $messages, int $maxTokens, float $temperature): array
    {
        $base = $this->baseUrl($config);
        if ($base === '') {
            return [
                'success' => false,
                'code' => 'invalid_base_url',
                'message' => 'Base URL Anthropic tidak valid.',
            ];
        }
        $key = trim((string)($config['api_key'] ?? ''));
        if ($key === '') {
            return [
                'success' => false,
                'code' => 'not_configured',
                'message' => 'API key Anthropic belum dikonfigurasi.',
            ];
        }
        $model = AIHttpClient::sanitizeModel((string)($config['model'] ?? ''));
        if ($model === '') {
            return [
                'success' => false,
                'code' => 'model_unavailable',
                'message' => 'Nama model Claude belum diisi atau tidak valid.',
            ];
        }

        $payload = [
            'model' => $model,
            'system' => $system,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        $response = AIHttpClient::request(
            'POST',
            $base . '/messages',
            $this->headers($key),
            $payload,
            45,
            10
        );

        if (!$response['ok']) {
            return [
                'success' => false,
                'code' => AIHttpClient::errorCode($response),
                'message' => AIHttpClient::errorMessage($response, 'Anthropic Claude'),
            ];
        }

        $parts = [];
        foreach (($response['json']['content'] ?? []) as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'text') {
                $parts[] = (string)($item['text'] ?? '');
            }
        }

        return [
            'success' => true,
            'code' => 'connected',
            'content' => implode("\n", $parts),
            'message' => '',
        ];
    }

    private function headers(string $apiKey): array
    {
        return [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function baseUrl(array $config): string
    {
        $base = trim((string)($config['base_url'] ?? ''));
        if ($base === '') {
            $base = (string)($this->meta['base_url'] ?? '');
        }
        return AIHttpClient::normalizeBaseUrl($base);
    }
}
