<?php

require_once __DIR__ . '/AICryptoService.php';
require_once __DIR__ . '/AIProviderCatalog.php';
require_once __DIR__ . '/AIProviderManager.php';
require_once __DIR__ . '/AIHttpClient.php';

/**
 * AISettingsService — konfigurasi multi-provider Aca per Admin toko.
 *
 * Sumber API key (berurutan):
 * 1. API key dari form (hanya untuk pengujian, tidak pernah dikembalikan).
 * 2. API key per provider di database, terenkripsi AES-256-GCM.
 * 3. Environment variable provider (mis. OPENAI_API_KEY).
 *
 * Relink AI Gateway didukung melalui adapter OpenAI-compatible dan memakai
 * base URL resmi yang dikonfigurasi di AIProviderCatalog. Tidak ada endpoint
 * Relink terpisah yang di-hardcode di service ini. Adapter key baru dapat
 * ditambahkan tanpa mengubah adapter provider.
 *
 * API key tidak pernah dikirim balik ke frontend. Hanya status boolean,
 * sumber key, model, dan status koneksi yang dikembalikan.
 */
class AISettingsService
{
    private ?Database $db = null;
    private AIProviderManager $providers;

    public function __construct()
    {
        try {
            $this->db = new Database();
        } catch (Throwable $e) {
            $this->db = null;
        }
        $this->providers = new AIProviderManager();
    }

    public function isSchemaReady(): bool
    {
        if (!$this->db) {
            return false;
        }
        try {
            $this->db->query("SELECT 1 FROM aca_ai_settings LIMIT 1");
            $this->db->execute();
            $this->db->query("SELECT 1 FROM aca_ai_provider_configs LIMIT 1");
            $this->db->execute();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isAiEnabled(int $adminId): bool
    {
        $row = $this->getRow($adminId);
        if (!$row) {
            return true;
        }
        return !isset($row['is_active']) || (int)$row['is_active'] === 1;
    }

    public function toggleAiStatus(int $adminId, ?bool $newStatus = null): array
    {
        if (!$this->db || !$this->isSchemaReady()) {
            return ['success' => false, 'message' => 'Database belum siap.'];
        }

        $row = $this->getRow($adminId);
        $current = !isset($row['is_active']) || (int)$row['is_active'] === 1;
        $target = $newStatus !== null ? $newStatus : !$current;

        try {
            $this->db->query("UPDATE aca_ai_settings SET is_active = :status WHERE admin_id = :admin_id");
            $this->db->bind('status', $target ? 1 : 0, PDO::PARAM_INT);
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->execute();

            $statusLabel = $target ? 'diaktifkan' : 'dinonaktifkan';
            $this->audit($adminId, 'ai_status_toggled', 'Layanan AI ' . $statusLabel);

            return [
                'success' => true,
                'is_active' => $target,
                'message' => 'Layanan AI berhasil ' . $statusLabel . '.',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Gagal mengubah status layanan AI.'];
        }
    }

    public function getRow(int $adminId): ?array
    {
        if (!$this->db || $adminId <= 0) {
            return null;
        }
        try {
            $this->db->query("SELECT * FROM aca_ai_settings WHERE admin_id = :admin_id LIMIT 1");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $row = $this->db->single();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function getProviderRow(int $adminId, string $provider): ?array
    {
        if (!$this->db || $adminId <= 0 || $provider === '') {
            return null;
        }
        try {
            $this->db->query("SELECT * FROM aca_ai_provider_configs
                              WHERE admin_id = :admin_id AND provider = :provider LIMIT 1");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->bind('provider', $provider);
            $row = $this->db->single();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function getProviderRows(int $adminId): array
    {
        if (!$this->db || $adminId <= 0) {
            return [];
        }
        try {
            $this->db->query("SELECT * FROM aca_ai_provider_configs WHERE admin_id = :admin_id");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $rows = $this->db->resultSet();
            $indexed = [];
            foreach ($rows as $row) {
                $indexed[(string)$row['provider']] = $row;
            }
            return $indexed;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Konfigurasi efektif termasuk API key hasil dekripsi.
     * JANGAN kirim hasil method ini ke frontend.
     *
     * @param array $input api_key/base_url/temperature/max_output_tokens dari
     *                     form (opsional, untuk pengujian sebelum disimpan).
     */
    public function getEffectiveConfig(int $adminId, ?string $providerId = null, ?string $model = null, array $input = []): array
    {
        $settings = $this->getRow($adminId) ?? [];
        $activeProvider = $this->normalizeProvider((string)($settings['provider'] ?? '')) ?: 'gemini';
        $provider = $this->normalizeProvider((string)($providerId ?? '')) ?: $activeProvider;
        $meta = AIProviderCatalog::get($provider);

        if (!$meta) {
            return [
                'admin_id' => $adminId,
                'provider' => $provider,
                'provider_label' => $provider,
                'model' => '',
                'base_url' => '',
                'temperature' => 0.3,
                'max_output_tokens' => 800,
                'api_key' => '',
                'key_source' => 'none',
                'configured' => false,
                'key_error' => 'Provider AI tidak didukung.',
                'model_valid' => false,
                'status' => 'not_configured',
                'status_message' => 'Provider AI tidak didukung.',
                'last_checked_at' => null,
                'is_active' => false,
            ];
        }

        $pRow = $this->getProviderRow($adminId, $provider);
        $isActive = ($provider === $activeProvider);

        $candidateModel = trim((string)($model ?? ''));
        if ($candidateModel === '') {
            if ($isActive && trim((string)($settings['model'] ?? '')) !== '') {
                $candidateModel = trim((string)$settings['model']);
            } elseif (!empty($pRow['model'])) {
                $candidateModel = trim((string)$pRow['model']);
            } elseif ($provider === 'gemini' && trim((string)($settings['model'] ?? '')) !== '') {
                $candidateModel = trim((string)$settings['model']);
            } else {
                $candidateModel = (string)($meta['default_model'] ?? '');
            }
        }
        $candidateModel = AIHttpClient::sanitizeModel($candidateModel);

        $baseInput = trim((string)($input['base_url'] ?? ''));
        $baseUrl = $baseInput;
        if ($baseUrl === '' && !empty($pRow['base_url'])) {
            $baseUrl = trim((string)$pRow['base_url']);
        }
        if ($baseUrl === '') {
            $baseUrl = (string)($meta['base_url'] ?? '');
        }
        $baseUrl = AIHttpClient::normalizeBaseUrl($baseUrl);

        $temperature = $this->clampFloat(
            $input['temperature'] ?? ($settings['temperature'] ?? (defined('AI_TEMPERATURE') ? AI_TEMPERATURE : 0.3)),
            0.0,
            2.0,
            0.3
        );
        $maxTokens = $this->clampInt(
            $input['max_output_tokens'] ?? ($settings['max_output_tokens'] ?? (defined('AI_MAX_OUTPUT_TOKENS') ? AI_MAX_OUTPUT_TOKENS : 800)),
            32,
            8192,
            800
        );

        $apiKey = trim((string)($input['api_key'] ?? ''));
        $keySource = $apiKey !== '' ? 'form' : 'none';
        $keyError = null;

        if ($apiKey === '' && !empty($pRow['api_key_cipher'])) {
            $decrypted = AICryptoService::decrypt((string)$pRow['api_key_cipher']);
            if (is_string($decrypted) && trim($decrypted) !== '') {
                $apiKey = trim($decrypted);
                $keySource = 'database';
            } else {
                $keyError = 'API key tersimpan tidak dapat didekripsi. Periksa AI_ENCRYPTION_KEY atau file storage/ai_encryption.key, lalu simpan ulang API key.';
            }
        }

        // Kompatibilitas data lama: sebelum tabel provider ada, key aktif
        // tersimpan di aca_ai_settings. Jangan gunakan jika baris provider
        // sudah ada, agar penghapusan key tidak "hidup kembali".
        if ($apiKey === '' && !$pRow && $isActive && !empty($settings['api_key_cipher'])) {
            $decrypted = AICryptoService::decrypt((string)$settings['api_key_cipher']);
            if (is_string($decrypted) && trim($decrypted) !== '') {
                $apiKey = trim($decrypted);
                $keySource = 'database';
            } elseif ($keyError === null) {
                $keyError = 'API key tersimpan tidak dapat didekripsi. Periksa konfigurasi enkripsi server.';
            }
        }

        if ($apiKey === '') {
            $envKey = $this->envKey($meta);
            if ($envKey !== '') {
                $apiKey = $envKey;
                $keySource = 'environment';
            }
        }

        $status = 'unverified';
        $statusMessage = null;
        $lastCheckedAt = null;
        if (!empty($pRow['last_status'])) {
            $status = (string)$pRow['last_status'];
            $statusMessage = $pRow['last_status_message'] ?? null;
            $lastCheckedAt = $pRow['last_checked_at'] ?? null;
        } elseif ($isActive && !empty($settings['last_status'])) {
            $status = (string)$settings['last_status'];
            $statusMessage = $settings['last_status_message'] ?? null;
            $lastCheckedAt = $settings['last_checked_at'] ?? null;
        }

        if ($apiKey === '') {
            $status = 'not_configured';
            $statusMessage = $keyError ?: 'API key belum dikonfigurasi.';
        }

        $modelValid = $candidateModel !== '';
        if (!$modelValid) {
            $status = 'model_unavailable';
            $statusMessage = 'Nama model belum diisi atau tidak valid.';
        }

        if ($provider === 'custom_openai' && $baseUrl === '') {
            $status = 'invalid_base_url';
        }

        return [
            'admin_id' => $adminId,
            'provider' => $provider,
            'provider_label' => (string)($meta['label'] ?? $provider),
            'model' => $candidateModel,
            'model_valid' => $modelValid,
            'base_url' => $baseUrl,
            'temperature' => $temperature,
            'max_output_tokens' => $maxTokens,
            'api_key' => $apiKey,
            'key_source' => $keySource,
            'configured' => $apiKey !== '',
            'key_error' => $keyError,
            'status' => $status,
            'status_message' => $statusMessage,
            'last_checked_at' => $lastCheckedAt,
            'is_active' => $isActive,
            'supports_model_list' => !empty($meta['supports_model_list']),
        ];
    }

    public function getActiveConfig(int $adminId): array
    {
        return $this->getEffectiveConfig($adminId);
    }

    public function getFallbackConfig(int $adminId): array
    {
        $row = $this->getRow($adminId) ?? [];
        $provider = $this->normalizeProvider((string)($row['fallback_provider'] ?? ''));
        if ($provider === '') {
            return [
                'enabled' => false,
                'configured' => false,
                'provider' => '',
                'provider_label' => '',
                'model' => '',
                'status' => 'not_configured',
                'status_message' => 'Provider cadangan belum dipilih.',
            ];
        }

        $config = $this->getEffectiveConfig(
            $adminId,
            $provider,
            trim((string)($row['fallback_model'] ?? '')) ?: null
        );
        $config['enabled'] = true;
        return $config;
    }

    /**
     * Data aman untuk halaman Admin. Tidak mengandung API key atau potongannya.
     */
    public function getSafeSettings(int $adminId): array
    {
        $row = $this->getRow($adminId) ?? [];
        $active = $this->getActiveConfig($adminId);
        $fallback = $this->getFallbackConfig($adminId);
        $rows = $this->getProviderRows($adminId);

        $status = (string)($active['status'] ?? 'not_configured');
        $statusMessage = $active['status_message'] ?? null;
        $lastCheckedAt = $active['last_checked_at'] ?? null;

        $providers = [];
        foreach ($this->providers->all() as $id => $meta) {
            $eff = ($id === $active['provider'])
                ? $active
                : $this->getEffectiveConfig($adminId, $id);
            $isFallback = ($id === ($fallback['provider'] ?? '') && !empty($fallback['enabled']));
            $pRow = $rows[$id] ?? null;

            $providerStatus = $eff['configured'] ? (string)($eff['status'] ?? 'unverified') : 'not_configured';
            if ($eff['configured'] && $providerStatus === 'not_configured') {
                $providerStatus = 'unverified';
            }
            $providerMessage = $eff['status_message'] ?? null;
            if (!$eff['configured']) {
                $providerMessage = $eff['key_error'] ?? 'API key belum dikonfigurasi.';
            }

            $providers[] = [
                'id' => $id,
                'label' => (string)($meta['label'] ?? $id),
                'family' => (string)($meta['family'] ?? ''),
                'default_model' => (string)($meta['default_model'] ?? ''),
                'model' => (string)($eff['model'] ?? ''),
                'base_url' => (string)($eff['base_url'] ?? ''),
                'has_api_key' => !empty($eff['configured']),
                'key_source' => (string)($eff['key_source'] ?? 'none'),
                'status' => $providerStatus,
                'status_message' => $providerMessage,
                'last_checked_at' => $eff['last_checked_at'] ?? null,
                'is_active' => ($id === $active['provider']),
                'is_fallback' => $isFallback,
                'supports_model_list' => !empty($meta['supports_model_list']),
                'docs' => (string)($meta['docs'] ?? ''),
                'key_env' => (string)($meta['key_env'] ?? ''),
                'placeholder' => (string)($meta['placeholder'] ?? ''),
                'candidate_model' => !empty($pRow['model']) ? (string)$pRow['model'] : (string)($eff['model'] ?? ''),
            ];
        }

        return [
            'is_enabled' => !isset($row['is_active']) || (int)$row['is_active'] === 1,
            'provider' => $active['provider'],
            'provider_label' => $active['provider_label'],
            'model' => $active['model'],
            'base_url' => $active['base_url'],
            'temperature' => $active['temperature'],
            'max_output_tokens' => $active['max_output_tokens'],
            'has_api_key' => $active['configured'],
            'key_source' => $active['key_source'],
            'key_masked' => $active['configured'] ? '••••••••' : '',
            'status' => $status,
            'status_message' => $statusMessage,
            'last_checked_at' => $lastCheckedAt,
            'updated_at' => $row['updated_at'] ?? null,
            'suggested_model' => $active['configured'] ? $this->extractSuggestedModel($statusMessage) : null,
            'fallback_enabled' => !empty($fallback['enabled']) && !empty($fallback['configured']),
            'fallback_provider' => $fallback['provider'] ?? '',
            'fallback_provider_label' => $fallback['provider_label'] ?? '',
            'fallback_model' => $fallback['model'] ?? '',
            'fallback_status' => $fallback['status'] ?? 'not_configured',
            'fallback_status_message' => $fallback['status_message'] ?? null,
            'fallback_last_checked_at' => $fallback['last_checked_at'] ?? null,
            'providers' => $providers,
            'provider_catalog' => array_values($this->providers->all()),
            'schema_ready' => $this->isSchemaReady(),
            'encryption_ready' => $this->encryptionReady(),
            'relink_detected' => AIProviderCatalog::get('relink') !== null,
        ];
    }

    /**
     * Simpan konfigurasi provider sebagai kandidat tanpa mengaktifkannya.
     * Aktivasi hanya dilakukan oleh testAndActivate() setelah uji nyata sukses.
     *
     * Untuk provider yang sedang aktif, perubahan API key/model/base URL
     * ditolak agar konfigurasi yang belum diuji tidak langsung dipakai.
     * Perubahan tersebut harus melalui testAndActivate().
     */
    public function save(int $adminId, array $input): array
    {
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'message' => 'Tabel multi-provider Aca belum tersedia. Jalankan database/migration_ai_multi_provider.sql.'];
        }

        $provider = $this->normalizeProvider((string)($input['provider'] ?? ''));
        if ($provider === '' || !AIProviderCatalog::get($provider)) {
            return ['success' => false, 'message' => 'Provider AI tidak didukung.'];
        }

        $model = AIHttpClient::sanitizeModel((string)($input['model'] ?? ''));
        if ($model === '') {
            return ['success' => false, 'message' => 'Nama model tidak valid.'];
        }

        $base = $this->validatedBaseUrl($provider, (string)($input['base_url'] ?? ''));
        if ($base === null) {
            return ['success' => false, 'message' => 'Base URL tidak valid. Gunakan HTTPS (HTTP hanya untuk localhost) tanpa kredensial di URL.'];
        }

        $temperature = $this->clampFloat($input['temperature'] ?? 0.3, 0.0, 2.0, 0.3);
        $maxTokens = $this->clampInt($input['max_output_tokens'] ?? 800, 32, 8192, 800);

        $row = $this->getProviderRow($adminId, $provider);
        $removeKey = !empty($input['remove_api_key']);
        $apiKeyInput = trim((string)($input['api_key'] ?? ''));

        if ($apiKeyInput !== '' && !$this->validApiKey($apiKeyInput)) {
            return ['success' => false, 'message' => 'Format API key terlihat tidak valid.'];
        }

        $currentRow = $this->getRow($adminId) ?? [];
        $isActiveProvider = (($currentRow['provider'] ?? '') === $provider);
        $existing = $this->getEffectiveConfig($adminId, $provider);
        $existingModel = $isActiveProvider
            ? (string)($currentRow['model'] ?? '')
            : (string)($existing['model'] ?? '');
        $existingBase = (string)($existing['base_url'] ?? '');
        $modelChanged = $model !== $existingModel;
        $baseChanged = $base !== $existingBase;
        $keyChanged = $apiKeyInput !== '' || $removeKey;

        if ($isActiveProvider && ($modelChanged || $baseChanged || $keyChanged)) {
            return [
                'success' => false,
                'message' => 'Provider ini sedang aktif. Untuk mengganti API key, model, atau base URL, gunakan tombol Uji & Aktifkan agar konfigurasi baru diverifikasi lebih dulu.',
            ];
        }

        $cipher = $row['api_key_cipher'] ?? null;
        $last4 = $row['api_key_last4'] ?? null;
        $keyUpdatedAt = $row['api_key_updated_at'] ?? null;
        $changedKey = false;

        if ($removeKey) {
            $cipher = null;
            $last4 = null;
            $keyUpdatedAt = null;
            $changedKey = true;
        } elseif ($apiKeyInput !== '') {
            try {
                $cipher = AICryptoService::encrypt($apiKeyInput);
                $last4 = substr($apiKeyInput, -4);
                $keyUpdatedAt = date('Y-m-d H:i:s');
                $changedKey = true;
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'Gagal mengenkripsi API key. Periksa konfigurasi enkripsi server.'];
            }
        }

        $hasKey = is_string($cipher) && $cipher !== '';
        $envKey = $this->envKey(AIProviderCatalog::get($provider) ?? []);
        $hasKey = $hasKey || $envKey !== '';

        // Perubahan key, model, atau base URL selalu menandai konfigurasi
        // sebagai belum terverifikasi, termasuk untuk provider non-aktif.
        $configChanged = $changedKey || $modelChanged || $baseChanged;
        $status = $hasKey ? ($configChanged ? 'unverified' : (string)($row['last_status'] ?? 'unverified')) : 'not_configured';
        $statusMessage = $hasKey
            ? ($configChanged ? 'Konfigurasi disimpan. Uji koneksi untuk memverifikasi konfigurasi ini.' : ($row['last_status_message'] ?? 'Konfigurasi disimpan.'))
            : 'API key belum dikonfigurasi.';

        $saved = $this->upsertProviderRow($adminId, $provider, $model, $base, $cipher, $last4, $keyUpdatedAt, $status, $statusMessage, null);
        if (!$saved) {
            return ['success' => false, 'message' => 'Gagal menyimpan konfigurasi provider.'];
        }
        if ($removeKey) {
            // upsertProviderRow memakai COALESCE agar key lama tidak hilang saat
            // pengujian ulang tanpa key baru. Untuk permintaan hapus eksplisit,
            // kosongkan cipher setelah upsert.
            try {
                $this->db->query("UPDATE aca_ai_provider_configs
                                  SET api_key_cipher = NULL, api_key_last4 = NULL, api_key_updated_at = NULL
                                  WHERE admin_id = :admin_id AND provider = :provider");
                $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
                $this->db->bind('provider', $provider);
                $this->db->execute();
                if (($this->getRow($adminId)['provider'] ?? '') === $provider) {
                    $this->db->query("UPDATE aca_ai_settings SET api_key_cipher = NULL, api_key_last4 = NULL, api_key_updated_at = NULL WHERE admin_id = :admin_id");
                    $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
                    $this->db->execute();
                }
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'Gagal menghapus API key provider.'];
            }
        }

        // Parameter generasi hanya milik provider aktif. Jangan mengubah
        // parameter provider lain yang masih berupa kandidat.
        $currentRow = $this->getRow($adminId) ?? [];
        if (($currentRow['provider'] ?? '') === $provider) {
            try {
                $this->db->query("UPDATE aca_ai_settings
                                  SET temperature = :temperature, max_output_tokens = :max_tokens
                                  WHERE admin_id = :admin_id");
                $this->db->bind('temperature', $temperature);
                $this->db->bind('max_tokens', $maxTokens, PDO::PARAM_INT);
                $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
                $this->db->execute();
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'Konfigurasi tersimpan, tetapi parameter generasi gagal disimpan.'];
            }
        }

        $this->audit($adminId, 'provider_saved', 'Menyimpan provider ' . $provider . ' (model=' . $model . ', key_changed=' . ($changedKey ? 'ya' : 'tidak') . ')');

        $providerLabel = AIProviderCatalog::get($provider)['label'] ?? $provider;
        return [
            'success' => true,
            'message' => $isActiveProvider
                ? 'Parameter ' . $providerLabel . ' yang sedang aktif tersimpan. Untuk mengganti API key, model, atau base URL, gunakan Uji & Aktifkan.'
                : 'Konfigurasi ' . $providerLabel . ' tersimpan. Provider belum diganti sampai uji koneksi berhasil.',
            'status' => $status,
        ];
    }

    /**
     * Uji koneksi nyata, lalu simpan/aktifkan hanya jika berhasil.
     *
     * roles: active (provider utama), fallback (cadangan), test (hanya uji).
     */
    public function testAndActivate(int $adminId, array $input): array
    {
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'status' => 'not_configured', 'message' => 'Tabel multi-provider Aca belum tersedia. Jalankan migration terlebih dahulu.'];
        }

        $provider = $this->normalizeProvider((string)($input['provider'] ?? ''));
        if ($provider === '' || !AIProviderCatalog::get($provider)) {
            return ['success' => false, 'status' => 'api_error', 'message' => 'Provider AI tidak didukung.'];
        }

        $role = strtolower(trim((string)($input['activate'] ?? 'test')));
        if (!in_array($role, ['active', 'fallback', 'test'], true)) {
            return ['success' => false, 'status' => 'api_error', 'message' => 'Mode aktivasi tidak valid.'];
        }

        $current = $this->getRow($adminId) ?? [];
        $activeProvider = $this->normalizeProvider((string)($current['provider'] ?? '')) ?: 'gemini';
        if ($role === 'fallback' && $provider === $activeProvider) {
            return ['success' => false, 'status' => 'api_error', 'message' => 'Provider cadangan harus berbeda dari provider utama.'];
        }

        $model = AIHttpClient::sanitizeModel((string)($input['model'] ?? ''));
        if ($model === '') {
            return ['success' => false, 'status' => 'model_unavailable', 'message' => 'Nama model belum diisi atau tidak valid.'];
        }

        $base = $this->validatedBaseUrl($provider, (string)($input['base_url'] ?? ''));
        if ($base === null) {
            return ['success' => false, 'status' => 'invalid_base_url', 'message' => 'Base URL tidak valid. Gunakan HTTPS (HTTP hanya untuk localhost) tanpa kredensial di URL.'];
        }

        $apiKeyInput = trim((string)($input['api_key'] ?? ''));
        if ($apiKeyInput !== '' && !$this->validApiKey($apiKeyInput)) {
            return ['success' => false, 'status' => 'invalid_key', 'message' => 'Format API key terlihat tidak valid.'];
        }

        $config = $this->getEffectiveConfig($adminId, $provider, $model, [
            'api_key' => $apiKeyInput,
            'base_url' => $base,
            'temperature' => $input['temperature'] ?? 0.3,
            'max_output_tokens' => $input['max_output_tokens'] ?? 800,
        ]);

        if (!$config['configured']) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'API key ' . $config['provider_label'] . ' belum dikonfigurasi.',
                'settings' => $this->getSafeSettings($adminId),
            ];
        }

        $activeConfig = ($provider === $activeProvider)
            ? $this->getEffectiveConfig($adminId, $provider)
            : [];
        $candidateChanged = ($provider === $activeProvider) && (
            $apiKeyInput !== ''
            || $model !== (string)($activeConfig['model'] ?? '')
            || $base !== (string)($activeConfig['base_url'] ?? '')
        );

        $result = $this->providers->testConnection($provider, $config);
        $status = !empty($result['success']) ? 'connected' : (string)($result['code'] ?? 'api_error');
        $message = (string)($result['message'] ?? '');
        if (!$result['success'] && !empty($result['suggested_model'])
            && stripos($message, (string)$result['suggested_model']) === false) {
            $message .= ' Model yang disarankan: ' . $result['suggested_model'] . '.';
        }

        if (empty($result['success'])) {
            // Jangan simpan API key baru yang gagal diuji. Jika pengujian
            // memakai key tersimpan, perbarui status agar UI jujur. Untuk
            // kandidat konfigurasi provider aktif, status aktif tidak diubah
            // karena konfigurasi aktif belum tentu sama dengan yang diuji.
            if ($apiKeyInput === '' && !$candidateChanged) {
                $this->setProviderStatus($adminId, $provider, $status, $message, true);
            }
            $this->audit($adminId, 'connection_tested', 'Uji ' . $provider . ' gagal: ' . $status);
            return [
                'success' => false,
                'status' => $status,
                'message' => $message !== '' ? $message : 'Uji koneksi gagal.',
                'suggested_model' => $result['suggested_model'] ?? null,
                'duration_ms' => $result['duration_ms'] ?? null,
                'models' => $result['models'] ?? [],
                'settings' => $this->getSafeSettings($adminId),
            ];
        }

        // Uji koneksi pada provider yang sedang aktif tidak boleh menjadi
        // jalur aktivasi diam-diam. Jika key/model/base berbeda dari yang
        // sedang dipakai, jangan simpan kandidat; Admin harus memakai
        // Uji & Aktifkan agar konfigurasi baru benar-benar diterapkan.
        if ($role === 'test' && $candidateChanged) {
            $this->audit($adminId, 'connection_tested', 'Uji ' . $provider . ' berhasil; kandidat aktif tidak diterapkan.');
            return [
                'success' => true,
                'status' => 'connected',
                'message' => 'Koneksi berhasil diuji. Konfigurasi baru belum diterapkan karena provider ini sedang aktif; gunakan Uji & Aktifkan untuk menerapkannya.',
                'activated' => false,
                'role' => $role,
                'persisted' => false,
                'suggested_model' => $result['suggested_model'] ?? null,
                'duration_ms' => $result['duration_ms'] ?? null,
                'settings' => $this->getSafeSettings($adminId),
            ];
        }

        $cipher = null;
        $last4 = null;
        $keyUpdatedAt = null;
        if ($apiKeyInput !== '') {
            try {
                $cipher = AICryptoService::encrypt($apiKeyInput);
                $last4 = substr($apiKeyInput, -4);
                $keyUpdatedAt = date('Y-m-d H:i:s');
            } catch (Throwable $e) {
                return ['success' => false, 'status' => 'api_error', 'message' => 'Koneksi berhasil, tetapi API key gagal dienkripsi. Periksa konfigurasi enkripsi server.'];
            }
        }

        $saved = $this->upsertProviderRow($adminId, $provider, $model, $base, $cipher, $last4, $keyUpdatedAt, 'connected', $message, true);
        if (!$saved) {
            return ['success' => false, 'status' => 'api_error', 'message' => 'Koneksi berhasil, tetapi konfigurasi gagal disimpan.'];
        }

        $activated = false;
        if ($role === 'active') {
            $temperature = $this->clampFloat($input['temperature'] ?? ($current['temperature'] ?? 0.3), 0.0, 2.0, 0.3);
            $maxTokens = $this->clampInt($input['max_output_tokens'] ?? ($current['max_output_tokens'] ?? 800), 32, 8192, 800);
            if (!$this->updateActiveSettings($adminId, $provider, $model, $temperature, $maxTokens, 'connected', $message)) {
                return ['success' => false, 'status' => 'api_error', 'message' => 'Koneksi berhasil, tetapi gagal mengaktifkan provider.'];
            }
            $activated = true;
            $message = 'Koneksi berhasil. Provider utama sekarang ' . ($config['provider_label'] ?? $provider) . ' dengan model ' . $model . '.';
        } elseif ($role === 'fallback') {
            if (!$this->updateFallbackSettings($adminId, $provider, $model)) {
                return ['success' => false, 'status' => 'api_error', 'message' => 'Koneksi berhasil, tetapi gagal menyimpan provider cadangan.'];
            }
            $activated = true;
            $message = 'Koneksi berhasil. Provider cadangan disimpan: ' . ($config['provider_label'] ?? $provider) . ' / ' . $model . '.';
        } else {
            $message = 'Koneksi berhasil. Konfigurasi ' . ($config['provider_label'] ?? $provider) . ' tersimpan tanpa mengubah provider aktif.';
        }

        $this->audit($adminId, 'connection_tested', 'Uji ' . $provider . ' berhasil; role=' . $role . '; model=' . $model);

        return [
            'success' => true,
            'status' => 'connected',
            'message' => $message,
            'activated' => $activated,
            'role' => $role,
            'suggested_model' => $result['suggested_model'] ?? null,
            'duration_ms' => $result['duration_ms'] ?? null,
            'settings' => $this->getSafeSettings($adminId),
        ];
    }

    public function testConnection(int $adminId, array $input = []): array
    {
        $input['activate'] = $input['activate'] ?? 'test';
        return $this->testAndActivate($adminId, $input);
    }

    public function listModels(int $adminId, array $input = []): array
    {
        $provider = $this->normalizeProvider((string)($input['provider'] ?? ''));
        if ($provider === '') {
            $row = $this->getRow($adminId) ?? [];
            $provider = $this->normalizeProvider((string)($row['provider'] ?? '')) ?: 'gemini';
        }
        if (!AIProviderCatalog::get($provider)) {
            return ['success' => false, 'code' => 'api_error', 'message' => 'Provider AI tidak didukung.', 'models' => []];
        }

        $model = AIHttpClient::sanitizeModel((string)($input['model'] ?? ''));
        $base = $this->validatedBaseUrl($provider, (string)($input['base_url'] ?? ''));
        if ($base === null) {
            return ['success' => false, 'code' => 'invalid_base_url', 'message' => 'Base URL tidak valid.', 'models' => []];
        }
        $apiKeyInput = trim((string)($input['api_key'] ?? ''));
        if ($apiKeyInput !== '' && !$this->validApiKey($apiKeyInput)) {
            return ['success' => false, 'code' => 'invalid_key', 'message' => 'Format API key terlihat tidak valid.', 'models' => []];
        }

        $config = $this->getEffectiveConfig($adminId, $provider, $model !== '' ? $model : null, [
            'api_key' => $apiKeyInput,
            'base_url' => $base,
        ]);

        if (!$config['configured']) {
            $this->audit($adminId, 'models_listed', 'Gagal memuat model ' . $provider . ': API key belum dikonfigurasi.');
            return [
                'success' => false,
                'code' => 'not_configured',
                'message' => 'API key ' . $config['provider_label'] . ' belum dikonfigurasi. Isi API key lalu coba lagi.',
                'models' => [],
            ];
        }

        $result = $this->providers->listModels($provider, $config);
        if (!isset($result['models']) || !is_array($result['models'])) {
            $result['models'] = [];
        }
        if (empty($result['current_model'])) {
            $result['current_model'] = $config['model'];
        }
        $result['provider'] = $provider;
        $result['provider_label'] = $config['provider_label'];
        $result['model_list_supported'] = !empty($config['supports_model_list']);
        $this->audit($adminId, 'models_listed', 'Memuat daftar model ' . $provider . ': ' . (!empty($result['success']) ? 'berhasil' : ($result['code'] ?? 'gagal')));
        return $result;
    }

    public function deleteKey(int $adminId, ?string $provider = null): array
    {
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'message' => 'Tabel multi-provider Aca belum tersedia.'];
        }
        if ($provider === null || $provider === '') {
            $row = $this->getRow($adminId) ?? [];
            $provider = $this->normalizeProvider((string)($row['provider'] ?? '')) ?: 'gemini';
        }
        $provider = $this->normalizeProvider($provider);
        $meta = AIProviderCatalog::get($provider);
        if (!$meta) {
            return ['success' => false, 'message' => 'Provider AI tidak didukung.'];
        }

        $status = $this->envKey($meta) !== '' ? 'unverified' : 'not_configured';
        $message = $this->envKey($meta) !== ''
            ? 'API key database dihapus. Provider memakai API key environment.'
            : 'API key provider telah dihapus.';

        try {
            $this->db->query("UPDATE aca_ai_provider_configs
                              SET api_key_cipher = NULL, api_key_last4 = NULL, api_key_updated_at = NULL,
                                  last_status = :status, last_status_message = :msg, last_checked_at = NULL
                              WHERE admin_id = :admin_id AND provider = :provider");
            $this->db->bind('status', $status);
            $this->db->bind('msg', $message);
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->bind('provider', $provider);
            $this->db->execute();
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Gagal menghapus API key provider.'];
        }

        $current = $this->getRow($adminId) ?? [];
        if (($current['provider'] ?? '') === $provider) {
            try {
                $this->db->query("UPDATE aca_ai_settings
                                  SET api_key_cipher = NULL, api_key_last4 = NULL, api_key_updated_at = NULL
                                  WHERE admin_id = :admin_id");
                $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
                $this->db->execute();
            } catch (Throwable $e) {
                // abaikan; baris provider tetap menjadi sumber utama.
            }
            $this->setSettingsStatus($adminId, $status, $message, false);
        }

        $this->audit($adminId, 'api_key_deleted', 'API key ' . $provider . ' dihapus dari database.');
        return ['success' => true, 'message' => 'API key ' . ($meta['label'] ?? $provider) . ' berhasil dihapus.'];
    }

    public function clearFallback(int $adminId): array
    {
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'message' => 'Tabel multi-provider Aca belum tersedia.'];
        }
        try {
            $this->db->query("UPDATE aca_ai_settings SET fallback_provider = NULL, fallback_model = NULL WHERE admin_id = :admin_id");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->execute();
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Gagal menghapus provider cadangan.'];
        }
        $this->audit($adminId, 'fallback_cleared', 'Provider cadangan dihapus.');
        return ['success' => true, 'message' => 'Provider cadangan berhasil dihapus.'];
    }

    public function audit(int $adminId, string $action, string $detail = ''): void
    {
        if (!$this->db) {
            return;
        }
        try {
            $this->db->query("INSERT INTO aca_ai_audit_logs (admin_id, action, detail, ip_address) VALUES (:admin_id, :action, :detail, :ip)");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->bind('action', substr($action, 0, 60));
            $this->db->bind('detail', mb_substr($detail, 0, 255));
            $this->db->bind('ip', substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $this->db->execute();
        } catch (Throwable $e) {
            // Audit log tidak boleh menggagalkan operasi utama.
        }
    }

    public function getAuditLogs(int $adminId, int $limit = 20): array
    {
        if (!$this->db) {
            return [];
        }
        try {
            $limit = max(1, min(100, $limit));
            $this->db->query("SELECT action, detail, ip_address, created_at FROM aca_ai_audit_logs
                              WHERE admin_id = :admin_id ORDER BY id DESC LIMIT " . $limit);
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            return $this->db->resultSet();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function upsertProviderRow(
        int $adminId,
        string $provider,
        string $model,
        string $baseUrl,
        ?string $cipher,
        ?string $last4,
        ?string $keyUpdatedAt,
        string $status,
        ?string $statusMessage,
        ?bool $checkedNow
    ): bool {
        if (!$this->db) {
            return false;
        }
        try {
            $this->db->query("
                INSERT INTO aca_ai_provider_configs
                    (admin_id, provider, model, base_url, api_key_cipher, api_key_last4,
                     api_key_updated_at, last_status, last_status_message, last_checked_at)
                VALUES
                    (:admin_id, :provider, :model, :base_url, :cipher, :last4,
                     :key_updated, :status, :status_msg, " . ($checkedNow === true ? 'NOW()' : 'NULL') . ")
                ON DUPLICATE KEY UPDATE
                    model = VALUES(model),
                    base_url = VALUES(base_url),
                    api_key_cipher = COALESCE(VALUES(api_key_cipher), api_key_cipher),
                    api_key_last4 = COALESCE(VALUES(api_key_last4), api_key_last4),
                    api_key_updated_at = COALESCE(VALUES(api_key_updated_at), api_key_updated_at),
                    last_status = VALUES(last_status),
                    last_status_message = VALUES(last_status_message),
                    last_checked_at = " . ($checkedNow === true ? 'NOW()' : 'last_checked_at') . "
            ");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->bind('provider', $provider);
            $this->db->bind('model', $model);
            $this->db->bind('base_url', $baseUrl !== '' ? $baseUrl : null);
            $this->db->bind('cipher', $cipher);
            $this->db->bind('last4', $last4);
            $this->db->bind('key_updated', $keyUpdatedAt);
            $this->db->bind('status', substr($status, 0, 40));
            $this->db->bind('status_msg', $statusMessage !== null ? mb_substr($statusMessage, 0, 255) : null);
            $this->db->execute();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function updateActiveSettings(int $adminId, string $provider, string $model, float $temperature, int $maxTokens, string $status, string $message): bool
    {
        if (!$this->db) {
            return false;
        }
        try {
            $this->db->beginTransaction();

            $this->db->query("
                INSERT INTO aca_ai_settings
                    (admin_id, provider, model, temperature, max_output_tokens, last_status, last_status_message, last_checked_at)
                VALUES
                    (:admin_id, :provider, :model, :temperature, :max_tokens, :status, :status_msg, NOW())
                ON DUPLICATE KEY UPDATE
                    provider = VALUES(provider),
                    model = VALUES(model),
                    temperature = VALUES(temperature),
                    max_output_tokens = VALUES(max_output_tokens),
                    last_status = VALUES(last_status),
                    last_status_message = VALUES(last_status_message),
                    last_checked_at = NOW(),
                    fallback_model = IF(fallback_provider = VALUES(provider), NULL, fallback_model),
                    fallback_provider = IF(fallback_provider = VALUES(provider), NULL, fallback_provider)
            ");
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->bind('provider', $provider);
            $this->db->bind('model', $model);
            $this->db->bind('temperature', $temperature);
            $this->db->bind('max_tokens', $maxTokens, PDO::PARAM_INT);
            $this->db->bind('status', substr($status, 0, 40));
            $this->db->bind('status_msg', mb_substr($message, 0, 255));
            $this->db->execute();

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function updateFallbackSettings(int $adminId, string $provider, string $model): bool
    {
        if (!$this->db) {
            return false;
        }
        try {
            $this->db->query("UPDATE aca_ai_settings SET fallback_provider = :provider, fallback_model = :model WHERE admin_id = :admin_id");
            $this->db->bind('provider', $provider);
            $this->db->bind('model', $model);
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->execute();
            if ($this->db->rowCount() === 0) {
                // Belum ada baris settings: buat baris default tanpa mengubah provider.
                $row = $this->getRow($adminId);
                if (!$row) {
                    $active = $this->normalizeProvider((string)(defined('AI_PROVIDER') ? AI_PROVIDER : '')) ?: 'gemini';
                    $this->db->query("INSERT INTO aca_ai_settings
                        (admin_id, provider, model, fallback_provider, fallback_model)
                        VALUES (:admin_id, :provider, :model, :fallback_provider, :fallback_model)");
                    $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
                    $this->db->bind('provider', $active);
                    $this->db->bind('model', (string)(defined('AI_MODEL') ? AI_MODEL : ''));
                    $this->db->bind('fallback_provider', $provider);
                    $this->db->bind('fallback_model', $model);
                    $this->db->execute();
                }
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function setProviderStatus(int $adminId, string $provider, string $status, ?string $message, bool $checked): void
    {
        if (!$this->db) {
            return;
        }
        try {
            $this->db->query("UPDATE aca_ai_provider_configs
                              SET last_status = :status, last_status_message = :msg,
                                  last_checked_at = " . ($checked ? 'NOW()' : 'last_checked_at') . "
                              WHERE admin_id = :admin_id AND provider = :provider");
            $this->db->bind('status', substr($status, 0, 40));
            $this->db->bind('msg', $message !== null ? mb_substr($message, 0, 255) : null);
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->bind('provider', $provider);
            $this->db->execute();
        } catch (Throwable $e) {
            // abaikan
        }

        $row = $this->getRow($adminId) ?? [];
        if (($row['provider'] ?? '') === $provider) {
            $this->setSettingsStatus($adminId, $status, $message, $checked);
        }
    }

    private function setSettingsStatus(int $adminId, string $status, ?string $message, bool $checked): void
    {
        if (!$this->db) {
            return;
        }
        try {
            $this->db->query("UPDATE aca_ai_settings
                              SET last_status = :status, last_status_message = :msg,
                                  last_checked_at = " . ($checked ? 'NOW()' : 'last_checked_at') . "
                              WHERE admin_id = :admin_id");
            $this->db->bind('status', substr($status, 0, 40));
            $this->db->bind('msg', $message !== null ? mb_substr($message, 0, 255) : null);
            $this->db->bind('admin_id', $adminId, PDO::PARAM_INT);
            $this->db->execute();
        } catch (Throwable $e) {
            // abaikan
        }
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return AIProviderCatalog::get($provider) ? $provider : '';
    }

    private function envKey(array $meta): string
    {
        $envName = (string)($meta['key_env'] ?? '');
        if ($envName === '') {
            return '';
        }
        $key = getenv($envName);
        if ($key === false && defined($envName)) {
            $key = (string)constant($envName);
        }
        $key = trim((string)$key);
        if ($key === '' || stripos($key, 'dummy') !== false || $key === 'your_gemini_api_key_here') {
            return '';
        }
        return $key;
    }

    private function validApiKey(string $key): bool
    {
        if (strlen($key) < 20 || strlen($key) > 512) {
            return false;
        }
        return (bool)preg_match('/^[A-Za-z0-9._\-]+$/', $key);
    }

    private function validatedBaseUrl(string $provider, string $input): ?string
    {
        $meta = AIProviderCatalog::get($provider);
        if (!$meta) {
            return null;
        }
        $input = trim($input);
        $base = $input !== '' ? $input : (string)($meta['base_url'] ?? '');
        $normalized = AIHttpClient::normalizeBaseUrl($base);
        if ($normalized === '') {
            return null;
        }
        $parts = parse_url($normalized);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $local)) {
            return null;
        }
        return $normalized;
    }

    private function clampFloat($value, float $min, float $max, float $default): float
    {
        if (!is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (float)$value));
    }

    private function clampInt($value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int)$value));
    }

    private function encryptionReady(): bool
    {
        try {
            $cipher = AICryptoService::encrypt('test');
            return is_string($cipher) && $cipher !== '';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function extractSuggestedModel(?string $message): ?string
    {
        if (!is_string($message) || $message === '') {
            return null;
        }
        if (preg_match('/(?:disarankan(?: Google)?|gunakan model|suggested model)\s*:?\s*([A-Za-z0-9._:\/-]{2,120})/i', $message, $m)) {
            return AIHttpClient::sanitizeModel($m[1]);
        }
        return null;
    }
}
