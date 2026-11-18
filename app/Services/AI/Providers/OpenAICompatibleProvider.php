<?php

require_once __DIR__ . '/../AIProviderAdapterInterface.php';
require_once __DIR__ . '/../AIHttpClient.php';
require_once __DIR__ . '/../AIProviderCatalog.php';
require_once __DIR__ . '/../AISharedPromptService.php';

/**
 * Adapter untuk provider yang memakai format OpenAI Chat Completions:
 * OpenAI, DeepSeek, OpenRouter, dan endpoint OpenAI-compatible custom.
 */
class OpenAICompatibleProvider implements AIProviderAdapterInterface
{
    private string $providerId;
    private array $meta;

    public function __construct(string $providerId)
    {
        $meta = AIProviderCatalog::get($providerId);
        if (!$meta) {
            throw new InvalidArgumentException('Provider tidak dikenal: ' . $providerId);
        }
        $this->providerId = $providerId;
        $this->meta = $meta;
    }

    public function listModels(array $config): array
    {
        $base = $this->baseUrl($config);
        if ($base === '') {
            return [
                'success' => false,
                'code' => 'invalid_base_url',
                'message' => 'Base URL provider belum valid. Gunakan URL HTTPS, tanpa kredensial di URL.',
                'models' => [],
            ];
        }
        $key = trim((string)($config['api_key'] ?? ''));
        if ($key === '') {
            return [
                'success' => false,
                'code' => 'not_configured',
                'message' => 'API key belum dikonfigurasi untuk ' . $this->meta['label'] . '.',
                'models' => [],
            ];
        }

        $response = AIHttpClient::request(
            'GET',
            $base . '/models',
            $this->headers($key),
            null,
            20,
            8
        );

        if (!$response['ok']) {
            return [
                'success' => false,
                'code' => AIHttpClient::errorCode($response),
                'message' => AIHttpClient::errorMessage($response, $this->meta['label']),
                'models' => [],
            ];
        }

        $models = $this->parseModels($response['json']);
        if (empty($models)) {
            return [
                'success' => false,
                'code' => 'model_list_unavailable',
                'message' => 'Provider tidak mengembalikan daftar model. Isi nama model secara manual lalu lakukan uji koneksi.',
                'models' => [],
            ];
        }

        return [
            'success' => true,
            'code' => 'ok',
            'message' => count($models) . ' model tersedia.',
            'models' => $models,
            'recommended_model' => $models[0]['id'] ?? null,
            'current_model' => trim((string)($config['model'] ?? '')),
            'current_available' => $this->modelExists((string)($config['model'] ?? ''), $models),
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
                'message' => 'Nama model belum diisi atau tidak valid.',
                'duration_ms' => 0,
            ];
        }

        $result = $this->chat(
            $config,
            [
                ['role' => 'system', 'content' => 'Ini adalah uji koneksi. Balas tepat satu kata: OK'],
                ['role' => 'user', 'content' => 'OK'],
            ],
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

        $text = trim((string)$result['content']);
        if ($text === '') {
            return [
                'success' => false,
                'code' => 'api_error',
                'message' => 'Provider merespons, tetapi tidak mengembalikan teks jawaban.',
                'duration_ms' => (int)round((microtime(true) - $start) * 1000),
            ];
        }

        return [
            'success' => true,
            'code' => 'connected',
            'message' => 'Koneksi ke ' . $this->meta['label'] . ' berhasil. Model merespons.',
            'model' => $model,
            'duration_ms' => (int)round((microtime(true) - $start) * 1000),
        ];
    }

    public function generate(string $question, array $config, array $context = [], array $history = []): string
    {
        $model = AIHttpClient::sanitizeModel((string)($config['model'] ?? ''));
        if ($model === '') {
            throw new RuntimeException('Model ' . $this->meta['label'] . ' belum diisi atau tidak valid.');
        }

        $messages = [
            ['role' => 'system', 'content' => AISharedPromptService::buildSystemPrompt($context)],
        ];

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

        $result = $this->chat(
            $config,
            $messages,
            max(32, min(8192, (int)($config['max_output_tokens'] ?? 800))),
            max(0.0, min(2.0, (float)($config['temperature'] ?? 0.3)))
        );

        if (!$result['success']) {
            throw new RuntimeException($result['message']);
        }

        $text = trim((string)$result['content']);
        if ($text === '') {
            throw new RuntimeException('Provider AI tidak mengembalikan jawaban.');
        }
        return $text;
    }

    private function chat(array $config, array $messages, int $maxTokens, float $temperature): array
    {
        $base = $this->baseUrl($config);
        if ($base === '') {
            return [
                'success' => false,
                'code' => 'invalid_base_url',
                'message' => 'Base URL provider belum valid. Gunakan URL HTTPS, tanpa kredensial di URL.',
            ];
        }
        $key = trim((string)($config['api_key'] ?? ''));
        if ($key === '') {
            return [
                'success' => false,
                'code' => 'not_configured',
                'message' => 'API key belum dikonfigurasi untuk ' . $this->meta['label'] . '.',
            ];
        }
        $model = AIHttpClient::sanitizeModel((string)($config['model'] ?? ''));
        if ($model === '') {
            return [
                'success' => false,
                'code' => 'model_unavailable',
                'message' => 'Nama model belum diisi atau tidak valid.',
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $response = AIHttpClient::request(
            'POST',
            $base . '/chat/completions',
            $this->headers($key),
            $payload,
            45,
            10
        );

        // Sebagian model OpenAI terbaru menolak max_tokens dan temperature.
        // Ulangi dengan parameter yang kompatibel tanpa mengubah konfigurasi admin.
        if (!$response['ok'] && (int)$response['http'] === 400) {
            $body = mb_strtolower((string)$response['body']);
            if (strpos($body, 'max_tokens') !== false || strpos($body, 'max_completion_tokens') !== false) {
                unset($payload['max_tokens']);
                $payload['max_completion_tokens'] = $maxTokens;
            }
            if (strpos($body, 'temperature') !== false) {
                unset($payload['temperature']);
            }
            $response = AIHttpClient::request(
                'POST',
                $base . '/chat/completions',
                $this->headers($key),
                $payload,
                45,
                10
            );
        }

        if (!$response['ok']) {
            return [
                'success' => false,
                'code' => AIHttpClient::errorCode($response),
                'message' => AIHttpClient::errorMessage($response, $this->meta['label']),
            ];
        }

        $content = $response['json']['choices'][0]['message']['content'] ?? '';
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $parts[] = (string)$part['text'];
                } elseif (is_string($part)) {
                    $parts[] = $part;
                }
            }
            $content = implode("\n", $parts);
        }

        return [
            'success' => true,
            'code' => 'connected',
            'content' => (string)$content,
            'message' => '',
        ];
    }

    private function headers(string $apiKey): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->providerId === 'openrouter') {
            $headers['HTTP-Referer'] = defined('BASEURL') ? BASEURL : 'https://localhost';
            $headers['X-Title'] = 'Acounting Aca';
        }

        return $headers;
    }

    private function baseUrl(array $config): string
    {
        $base = trim((string)($config['base_url'] ?? ''));
        if ($base === '') {
            $base = (string)($this->meta['base_url'] ?? '');
        }
        return AIHttpClient::normalizeBaseUrl($base);
    }

    private function parseModels(array $json): array
    {
        $raw = [];
        if (isset($json['data']) && is_array($json['data'])) {
            $raw = $json['data'];
        } elseif (isset($json['models']) && is_array($json['models'])) {
            $raw = $json['models'];
        }

        $models = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $id = AIHttpClient::sanitizeModel($item);
                $name = $id;
            } elseif (is_array($item)) {
                $id = AIHttpClient::sanitizeModel((string)($item['id'] ?? $item['name'] ?? ''));
                $name = (string)($item['name'] ?? $item['display_name'] ?? $id);
            } else {
                continue;
            }
            if ($id === '') {
                continue;
            }
            $models[$id] = [
                'id' => $id,
                'name' => trim($name) !== '' ? trim($name) : $id,
            ];
        }

        $models = array_values($models);
        usort($models, static function ($a, $b) {
            return strnatcasecmp($a['id'], $b['id']);
        });
        return $models;
    }

    private function modelExists(string $model, array $models): bool
    {
        $model = AIHttpClient::sanitizeModel($model);
        if ($model === '') {
            return false;
        }
        foreach ($models as $item) {
            if (($item['id'] ?? '') === $model) {
                return true;
            }
        }
        return false;
    }
}
