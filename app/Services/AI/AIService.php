<?php

require_once __DIR__ . '/AIDataService.php';
require_once __DIR__ . '/SmartLocalAIProvider.php';
require_once __DIR__ . '/AIScopeGuard.php';
require_once __DIR__ . '/AIConversationService.php';
require_once __DIR__ . '/AIConversationResponder.php';
require_once __DIR__ . '/AIIntentClassifier.php';
require_once __DIR__ . '/AISettingsService.php';
require_once __DIR__ . '/AIProviderManager.php';
require_once __DIR__ . '/DatabaseSchemaService.php';

/**
 * AIService — Orkestrasi Aca dengan urutan LOCAL-FIRST:
 *
 *   1. Autentikasi & role dari database
 *   2. Pemeriksaan keamanan (jailbreak/prompt injection)
 *   3. Klasifikasi intent lokal (identity, greeting, capability, conversation,
 *      application_help, accounting_query, out_of_scope)
 *   4. Percakapan umum dijawab responder lokal tanpa query data Accounting
 *   5. Scope guard untuk pertanyaan aplikasi/Accounting
 *   6. Query service ke database (dengan scoping pemilik/role)
 *   7. Provider AI aktif HANYA fallback untuk Accounting yang belum terjawab lokal
 *   8. Provider cadangan dicoba bila provider aktif gagal
 *   9. Fallback lokal yang jujur bila semua provider tidak tersedia/gagal
 */
class AIService
{
    private AIDataService $dataService;
    private SmartLocalAIProvider $localProvider;
    private AISettingsService $settingsService;
    private DatabaseSchemaService $schemaService;

    private ?int $userId = null;
    private string $role = 'pelanggan';
    private string $name = 'Pengguna';
    private int $ownerId = 1;

    private string $lastProvider = 'local';
    private ?string $lastFallbackReason = null;
    private string $lastIntent = 'unknown';

    public function __construct($userId = null)
    {
        $this->dataService = new AIDataService();
        $this->localProvider = new SmartLocalAIProvider($userId);
        $this->settingsService = new AISettingsService();
        $this->schemaService = new DatabaseSchemaService($this->dataService->getPdo());

        $this->resolveIdentity($userId);
    }

    public function getLastProvider(): string
    {
        return $this->lastProvider;
    }

    public function getLastFallbackReason(): ?string
    {
        return $this->lastFallbackReason;
    }

    public function getLastIntent(): string
    {
        return $this->lastIntent;
    }

    /**
     * Catat metadata query Aca untuk audit (tanpa isi pertanyaan/jawaban).
     */
    public function logQuery(int $userId, string $role, string $question, int $durationMs, bool $success = true): void
    {
        $this->dataService->logQuery(
            $userId,
            $role,
            $this->lastProvider,
            $this->lastFallbackReason,
            mb_strlen($question, 'UTF-8'),
            $durationMs,
            $success
        );
    }

    public function getAnswer($question, $userId = null)
    {
        $this->lastProvider = 'local';
        $this->lastFallbackReason = null;
        $this->lastIntent = 'unknown';
        $this->resolveIdentity($userId);

        $question = trim((string)$question);
        if ($question === '') {
            return 'Silakan ketik pertanyaan Anda.';
        }

        $settingsAdminId = $this->role === 'admin' ? (int)$this->userId : $this->ownerId;
        if (!$this->settingsService->isAiEnabled($settingsAdminId)) {
            $this->lastProvider = 'system';
            $this->lastIntent = 'deactivated';
            return 'Layanan AI Assistant saat ini sedang dinonaktifkan oleh Administrator toko.';
        }

        $history = $this->userId
            ? $this->dataService->getRecentHistory($this->userId, 8)
            : [];
        $conversationContext = AIConversationService::buildContext($history);

        // 1. Keamanan selalu diperiksa lebih dulu, tetapi bukan filter domain.
        $security = AIScopeGuard::securityCheck($question);
        if ($security !== null) {
            $this->lastProvider = 'scope';
            $this->lastIntent = 'blocked';
            return (string)($security['message'] ?? AIScopeGuard::SECURITY_MESSAGE);
        }

        // 2. Klasifikasi intent lokal. Pertanyaan percakapan tidak boleh
        //    menyentuh query produk/transaksi.
        $intentResult = AIIntentClassifier::classify($question, $conversationContext);
        $intent = (string)$intentResult['intent'];
        $subtype = (string)($intentResult['subtype'] ?? '');

        // Jika classifier belum yakin, coba cocokkan nama produk yang dikenal.
        if ($intent === AIIntentClassifier::OUT_OF_SCOPE && $subtype === 'unknown') {
            $productTerms = $this->getProductTerms();
            if (!empty($productTerms)) {
                $retry = AIIntentClassifier::classify($question, $conversationContext, $productTerms);
                if ($retry['intent'] === AIIntentClassifier::ACCOUNTING_QUERY) {
                    $intentResult = $retry;
                    $intent = (string)$retry['intent'];
                    $subtype = (string)($retry['subtype'] ?? '');
                }
            }
        }
        $this->lastIntent = $intent;

        // 3. Percakapan umum, identitas, salam, dan kemampuan dijawab lokal.
        if (in_array($intent, AIIntentClassifier::CONVERSATIONAL_INTENTS, true)) {
            $responder = new AIConversationResponder($this->name, $this->role);
            $this->lastProvider = 'conversation';
            return $responder->answer($intent, $question, $conversationContext);
        }

        // 4. Topik di luar ruang lingkup: jawab sopan tanpa query database.
        if ($intent === AIIntentClassifier::OUT_OF_SCOPE) {
            $responder = new AIConversationResponder($this->name, $this->role);
            $this->lastProvider = 'scope';
            $this->lastIntent = AIIntentClassifier::OUT_OF_SCOPE;
            return $responder->answer(AIIntentClassifier::OUT_OF_SCOPE, $question, $conversationContext);
        }

        // 5. Pertanyaan aplikasi/Accounting baru boleh memakai konteks produk
        //    dan scope guard. Urutan ini mencegah penolakan dini pada percakapan.
        $conversationContext['product_terms'] = $intent === AIIntentClassifier::ACCOUNTING_QUERY
            ? $this->getProductTerms()
            : [];
        $scope = AIScopeGuard::evaluate($question, $conversationContext, $intent);
        if (empty($scope['allowed'])) {
            $this->lastProvider = 'scope';
            return (string)($scope['message'] ?? AIScopeGuard::REJECTION_MESSAGE);
        }

        // 6. Mesin lokal + query database (RBAC diterapkan di dalam provider).
        if ($this->userId) {
            $this->localProvider->setUserContext($this->userId, $this->role, $this->name);
        }
        $localAnswer = $this->localProvider->ask($question, $history);
        $this->lastProvider = 'local';

        if (!$this->localProvider->isFallback()) {
            return $localAnswer;
        }

        // 7. Bantuan aplikasi tidak dikirim ke provider AI: dokumentasi lokal adalah
        //    sumber kebenaran. Jika tidak ada panduan spesifik, beri arahan menu.
        if ($intent === AIIntentClassifier::APPLICATION_HELP) {
            $this->lastProvider = 'local';
            $this->lastFallbackReason = 'application_help_not_found';
            $responder = new AIConversationResponder($this->name, $this->role);
            return $responder->applicationHelpFallback();
        }

        // 8. Hanya pertanyaan Accounting yang belum terjawab lokal -> provider.
        $settingsAdminId = $this->role === 'admin' ? (int)$this->userId : $this->ownerId;
        $activeConfig = $this->settingsService->getActiveConfig($settingsAdminId);
        $fallbackConfig = $this->settingsService->getFallbackConfig($settingsAdminId);
        $activeConfigured = !empty($activeConfig['configured']);
        $fallbackUsable = !empty($fallbackConfig['enabled'])
            && !empty($fallbackConfig['configured'])
            && (string)$fallbackConfig['provider'] !== (string)$activeConfig['provider'];

        if (!$activeConfigured && !$fallbackUsable) {
            $this->lastFallbackReason = 'not_configured';
            return $localAnswer;
        }

        $context = [
            'user_role' => $this->role,
            'user_name' => $this->name,
            'intent' => $intent,
            'grounding' => $this->buildGrounding($this->role, $this->ownerId, $this->userId),
            'schema_knowledge' => $this->role === 'pelanggan' ? '' : $this->schemaService->getKnowledgeSummary(),
        ];
        $providerHistory = AIConversationService::toProviderHistory($history);
        $manager = new AIProviderManager();

        if ($activeConfigured) {
            try {
                $this->lastProvider = (string)$activeConfig['provider'];
                return $manager->generate(
                    (string)$activeConfig['provider'],
                    $question,
                    $activeConfig,
                    $context,
                    $providerHistory
                );
            } catch (Throwable $e) {
                $this->logFailure($e, (string)$activeConfig['provider']);
            }
        }

        // 9. Provider cadangan hanya dipakai jika sudah pernah lolos uji.
        if ($fallbackUsable) {
            try {
                $this->lastProvider = (string)$fallbackConfig['provider'];
                $this->lastFallbackReason = $activeConfigured ? 'primary_failed' : 'primary_not_configured';
                return $manager->generate(
                    (string)$fallbackConfig['provider'],
                    $question,
                    $fallbackConfig,
                    $context,
                    $providerHistory
                );
            } catch (Throwable $e) {
                $this->logFailure($e, (string)$fallbackConfig['provider']);
            }
        }

        $this->lastProvider = 'local';
        $this->lastFallbackReason = $fallbackUsable ? 'providers_error' : 'primary_error';
        return $localAnswer . "\n\nLayanan AI tambahan sedang tidak dapat dihubungi, sehingga Aca hanya menampilkan hasil pencarian lokal dari database.";
    }

    private function resolveIdentity($userId): void
    {
        if (!$userId && session_status() === PHP_SESSION_ACTIVE) {
            $userId = $_SESSION['user_id'] ?? null;
        }

        $this->userId = $userId ? (int)$userId : null;

        if ($this->userId) {
            $user = $this->dataService->getUserContext($this->userId);
            if ($user) {
                $this->role = strtolower((string)($user['role'] ?? 'pelanggan'));
                $this->name = (string)($user['nama_lengkap'] ?? 'Pengguna');
                $this->ownerId = (int)$this->dataService->resolveOwnerId($user['id'], $this->role);
                return;
            }
        }

        $this->role = 'pelanggan';
        $this->name = 'Pengguna';
        $this->ownerId = 1;
    }

    /**
     * Grounding minimum sesuai role. Data mentah yang tidak diizinkan tidak
     * pernah dimasukkan, sehingga provider AI tidak dapat membocorkannya.
     */
    private function buildGrounding(string $role, int $ownerId, ?int $userId): array
    {
        if ($role === 'pelanggan') {
            return [
                'katalog_publik' => $this->limitRows($this->dataService->getPublicCatalog($ownerId, 20), 20),
                'catatan' => 'Hanya katalog publik (nama, harga, ketersediaan).',
                'modul_tersedia' => ['katalog', 'profil', 'ai', 'panduan'],
            ];
        }

        $grounding = [
            'ringkasan_stok' => $this->dataService->getStockOverview($ownerId),
            'ringkasan_inventaris' => $this->dataService->getInventorySummary($ownerId),
            'stok_menipis' => $this->limitRows($this->dataService->getLowStockProducts($ownerId, 10), 10),
            'produk' => $this->limitRows($this->dataService->getAllProducts($ownerId, 20), 20),
        ];

        if ($role === 'kasir') {
            $grounding['transaksi_anda'] = $this->limitRows(
                $this->dataService->getTransactionsFiltered($ownerId, ['cashier_id' => (int)$userId], 5),
                5
            );
            $grounding['penjualan_anda'] = $this->dataService->getSalesSummary($ownerId, null, null, (int)$userId);
            $grounding['ringkasan_persediaan'] = $this->dataService->getInventorySummary($ownerId);
            $grounding['skema_database'] = $this->schemaService->getKnowledgeSummary();
            $grounding['catatan'] = 'Hanya data operasional dan transaksi milik kasir ini. Jangan berikan omzet toko menyeluruh atau data pengguna lain.';
            return $grounding;
        }

        $grounding['ringkasan_aplikasi'] = $this->dataService->getApplicationOverview($ownerId);
        $grounding['penjualan_hari_ini'] = $this->dataService->getTodaySales($ownerId);
        $grounding['penjualan_bulan_ini'] = $this->dataService->getMonthSales($ownerId);
        $grounding['total_penjualan'] = $this->dataService->getTotalSales($ownerId);
        $grounding['produk_terlaris'] = $this->limitRows($this->dataService->getBestSellingProducts($ownerId, 5), 5);
        $grounding['transaksi_terakhir'] = $this->limitRows($this->dataService->getRecentTransactions($ownerId, 5), 5);
        $grounding['aktor_transaksi'] = $this->limitRows($this->dataService->getTransactionActors($ownerId, null, null, 10), 10);
        $grounding['jumlah_pengguna'] = $this->dataService->getUserCounts(null);
        $grounding['performa_kasir'] = $this->limitRows($this->dataService->getCashierPerformance($ownerId, null, null, 10), 10);
        $grounding['stok_per_produk'] = $this->limitRows($this->dataService->getStockByProduct($ownerId, 20), 20);
        $grounding['notifikasi_whatsapp'] = $this->dataService->getNotificationSummary($ownerId, 3);
        $grounding['pengaturan_whatsapp'] = $this->dataService->getWaSettingsSummary($ownerId);
        $grounding['skema_database'] = $this->schemaService->getKnowledgeSummary();
        $grounding['jumlah_baris_tabel'] = $this->schemaService->getRowCounts();
        $grounding['batasan_data'] = 'Tidak ada tabel kategori, supplier, pemasukan/pengeluaran, pergerakan stok, metode pembayaran, pajak, atau diskon.';

        return $grounding;
    }

    /**
     * Nama produk untuk membantu scope guard mengenali pertanyaan katalog.
     */
    private function getProductTerms(): array
    {
        try {
            $rows = $this->role === 'pelanggan'
                ? $this->dataService->getPublicCatalog($this->ownerId, 50)
                : $this->dataService->getAllProducts($this->ownerId, 50);

            $terms = [];
            foreach ($rows as $row) {
                $name = mb_strtolower(trim((string)($row['product_name'] ?? '')), 'UTF-8');
                if ($name !== '') {
                    $terms[] = $name;
                }
            }
            return $terms;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function limitRows($rows, int $max): array
    {
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($rows, 0, $max);
    }

    private function logFailure(Throwable $e, string $provider = 'unknown'): void
    {
        $message = preg_replace('/\b(sk|AIza|sk-ant|sk-or)[A-Za-z0-9_\-\.]{8,}\b/i', '[REDACTED]', (string)$e->getMessage());
        $message = preg_replace('/key=[^\s&]+/i', 'key=[REDACTED]', (string)$message);
        error_log('[AIService] Provider ' . $provider . ' gagal: ' . mb_substr((string)$message, 0, 300));
    }
}
