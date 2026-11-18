<?php

/**
 * Metadata provider AI yang didukung.
 *
 * Menambah provider baru cukup:
 * 1. tambahkan metadata di sini;
 * 2. buat adapter yang mengimplementasikan AIProviderAdapterInterface;
 * 3. daftarkan adapter di AIProviderManager.
 *
 * Tidak ada model yang "dipaksa" selain default awal. Admin tetap dapat
 * memuat daftar model dari provider atau mengetik nama model secara manual.
 */
class AIProviderCatalog
{
    public static function all(): array
    {
        return [
            'relink' => [
                'id' => 'relink',
                'label' => 'Relink AI Gateway',
                'family' => 'openai',
                'default_model' => 'deepseek-v4-flash',
                'base_url' => 'https://api.relink-gateway.biz.id/v1',
                'key_env' => 'RELINK_API_KEY',
                'docs' => 'https://api.relink-gateway.biz.id',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'sk-relink-... / Bearer token',
            ],
            'gemini' => [
                'id' => 'gemini',
                'label' => 'Google Gemini',
                'family' => 'gemini',
                'default_model' => 'gemini-flash-lite-latest',
                'base_url' => 'https://generativelanguage.googleapis.com',
                'key_env' => 'AI_API_KEY',
                'docs' => 'https://ai.google.dev/gemini-api/docs',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'AIza...',
            ],
            'openai' => [
                'id' => 'openai',
                'label' => 'OpenAI',
                'family' => 'openai',
                'default_model' => 'gpt-4.1-mini',
                'base_url' => 'https://api.openai.com/v1',
                'key_env' => 'OPENAI_API_KEY',
                'docs' => 'https://platform.openai.com/docs',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'sk-...',
            ],
            'deepseek' => [
                'id' => 'deepseek',
                'label' => 'DeepSeek',
                'family' => 'openai',
                'default_model' => 'deepseek-chat',
                'base_url' => 'https://api.deepseek.com/v1',
                'key_env' => 'DEEPSEEK_API_KEY',
                'docs' => 'https://api-docs.deepseek.com',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'sk-...',
            ],
            'anthropic' => [
                'id' => 'anthropic',
                'label' => 'Anthropic Claude',
                'family' => 'anthropic',
                'default_model' => 'claude-sonnet-4-5',
                'base_url' => 'https://api.anthropic.com/v1',
                'key_env' => 'ANTHROPIC_API_KEY',
                'docs' => 'https://docs.anthropic.com',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'sk-ant-...',
            ],
            'openrouter' => [
                'id' => 'openrouter',
                'label' => 'OpenRouter',
                'family' => 'openai',
                'default_model' => 'openai/gpt-4.1-mini',
                'base_url' => 'https://openrouter.ai/api/v1',
                'key_env' => 'OPENROUTER_API_KEY',
                'docs' => 'https://openrouter.ai/docs',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'sk-or-...',
            ],
            'custom_openai' => [
                'id' => 'custom_openai',
                'label' => 'OpenAI-Compatible (Custom)',
                'family' => 'openai',
                'default_model' => '',
                'base_url' => '',
                'key_env' => 'CUSTOM_AI_API_KEY',
                'docs' => '',
                'supports_model_list' => true,
                'supports_fallback' => true,
                'placeholder' => 'API key provider',
            ],
        ];
    }

    public static function get(string $id): ?array
    {
        $all = self::all();
        return $all[$id] ?? null;
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }
}
