<?php

require_once __DIR__ . '/../AIProviderAdapterInterface.php';
require_once __DIR__ . '/../GeminiService.php';

/**
 * Adapter Gemini. Implementasi HTTP dan pemetaan error tetap memakai
 * GeminiService yang sudah teruji, agar perilaku Gemini tidak berubah.
 */
class GeminiProviderAdapter implements AIProviderAdapterInterface
{
    private GeminiService $gemini;

    public function __construct()
    {
        $this->gemini = new GeminiService();
    }

    public function listModels(array $config): array
    {
        $result = $this->gemini->listModels($config, true);
        if (!isset($result['code'])) {
            $result['code'] = !empty($result['success']) ? 'ok' : 'api_error';
        }
        if (!isset($result['message'])) {
            $result['message'] = !empty($result['success']) ? 'Daftar model dimuat.' : 'Gagal memuat daftar model.';
        }
        if (!isset($result['models']) || !is_array($result['models'])) {
            $result['models'] = [];
        }
        return $result;
    }

    public function testConnection(array $config): array
    {
        return $this->gemini->testConnection($config);
    }

    public function generate(string $question, array $config, array $context = [], array $history = []): string
    {
        return $this->gemini->generate($question, $config, $context, $history);
    }
}
