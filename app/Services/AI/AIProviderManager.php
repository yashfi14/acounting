<?php

require_once __DIR__ . '/AIProviderCatalog.php';
require_once __DIR__ . '/AIProviderAdapterInterface.php';
require_once __DIR__ . '/Providers/GeminiProviderAdapter.php';
require_once __DIR__ . '/Providers/OpenAICompatibleProvider.php';
require_once __DIR__ . '/Providers/AnthropicProviderAdapter.php';

/**
 * Registry/provider manager Aca.
 *
 * Provider baru cukup ditambahkan di AIProviderCatalog + adapter + method
 * factory di sini. AIService dan halaman pengaturan tidak perlu diubah.
 */
class AIProviderManager
{
    private array $adapters = [];

    public function all(): array
    {
        return AIProviderCatalog::all();
    }

    public function getMeta(string $providerId): ?array
    {
        return AIProviderCatalog::get($providerId);
    }

    public function has(string $providerId): bool
    {
        return AIProviderCatalog::get($providerId) !== null;
    }

    public function adapter(string $providerId): AIProviderAdapterInterface
    {
        if (!isset($this->adapters[$providerId])) {
            $this->adapters[$providerId] = $this->makeAdapter($providerId);
        }
        return $this->adapters[$providerId];
    }

    public function testConnection(string $providerId, array $config): array
    {
        try {
            return $this->adapter($providerId)->testConnection($config);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'code' => 'api_error',
                'message' => $this->safeExceptionMessage($e),
            ];
        }
    }

    public function listModels(string $providerId, array $config): array
    {
        try {
            return $this->adapter($providerId)->listModels($config);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'code' => 'api_error',
                'message' => $this->safeExceptionMessage($e),
                'models' => [],
            ];
        }
    }

    public function generate(string $providerId, string $question, array $config, array $context = [], array $history = []): string
    {
        return $this->adapter($providerId)->generate($question, $config, $context, $history);
    }

    private function makeAdapter(string $providerId): AIProviderAdapterInterface
    {
        $meta = AIProviderCatalog::get($providerId);
        if (!$meta) {
            throw new InvalidArgumentException('Provider AI tidak didukung.');
        }

        return match ($meta['family'] ?? '') {
            'gemini' => new GeminiProviderAdapter(),
            'anthropic' => new AnthropicProviderAdapter(),
            'openai' => new OpenAICompatibleProvider($providerId),
            default => throw new InvalidArgumentException('Adapter provider belum tersedia.'),
        };
    }

    private function safeExceptionMessage(Throwable $e): string
    {
        $message = (string)$e->getMessage();
        $message = preg_replace('/\b(sk|AIza|sk-ant|sk-or)[A-Za-z0-9_\-\.]{8,}\b/i', '[REDACTED]', $message);
        $message = preg_replace('/key=[^\s&]+/i', 'key=[REDACTED]', (string)$message);
        $message = mb_substr(trim((string)$message), 0, 240);
        return $message !== '' ? $message : 'Terjadi kesalahan pada provider AI.';
    }
}
