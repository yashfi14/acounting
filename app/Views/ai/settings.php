<?php
$settings = $data['settings'] ?? [];
$logs = $data['audit_logs'] ?? [];
$providers = $settings['providers'] ?? [];
$status = (string)($settings['status'] ?? 'not_configured');
$schemaReady = !empty($settings['schema_ready']);

$statusMap = [
    'connected'            => ['label' => 'Terhubung', 'class' => 'success', 'icon' => 'check-circle-fill'],
    'testing'              => ['label' => 'Menguji Koneksi', 'class' => 'info', 'icon' => 'arrow-repeat'],
    'unverified'           => ['label' => 'Belum Diverifikasi', 'class' => 'warning', 'icon' => 'question-circle-fill'],
    'not_configured'       => ['label' => 'Belum Dikonfigurasi', 'class' => 'secondary', 'icon' => 'dash-circle'],
    'invalid_key'          => ['label' => 'API Key Tidak Valid', 'class' => 'danger', 'icon' => 'x-circle-fill'],
    'model_unavailable'    => ['label' => 'Model Tidak Tersedia', 'class' => 'danger', 'icon' => 'x-circle-fill'],
    'model_list_unavailable' => ['label' => 'Daftar Model Tidak Tersedia', 'class' => 'warning', 'icon' => 'list'],
    'invalid_base_url'     => ['label' => 'Base URL Tidak Valid', 'class' => 'danger', 'icon' => 'link-45deg'],
    'rate_limited'         => ['label' => 'Kuota / Rate Limit Habis', 'class' => 'warning', 'icon' => 'hourglass-split'],
    'billing_error'        => ['label' => 'Billing / Izin Bermasalah', 'class' => 'danger', 'icon' => 'credit-card-2-front'],
    'permission_denied'    => ['label' => 'Izin Ditolak', 'class' => 'danger', 'icon' => 'shield-lock'],
    'connection_error'     => ['label' => 'Gangguan Koneksi', 'class' => 'danger', 'icon' => 'wifi-off'],
    'server_error'         => ['label' => 'Kesalahan Server Provider', 'class' => 'danger', 'icon' => 'exclamation-octagon-fill'],
    'api_error'            => ['label' => 'Error API', 'class' => 'danger', 'icon' => 'exclamation-triangle-fill'],
];
$badge = $statusMap[$status] ?? $statusMap['api_error'];
$keySourceLabel = [
    'database'    => 'Tersimpan terenkripsi di database',
    'environment' => 'Dari environment (.env)',
    'form'        => 'Dari form (belum disimpan)',
    'none'        => 'Belum ada API key',
][$settings['key_source'] ?? 'none'] ?? 'Belum ada API key';

$fallbackBadge = $statusMap[(string)($settings['fallback_status'] ?? 'not_configured')] ?? $statusMap['not_configured'];
$catalogJson = json_encode(
    $settings['provider_catalog'] ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<style>
    .aca-provider-key { font-size: .78rem; }
    .aca-status-table td, .aca-status-table th { vertical-align: middle; }
    .aca-model-input { min-width: 220px; }
    .aca-base-url { font-size: .78rem; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Pengaturan AI Assistant</h1>
            <p class="text-muted small mb-0">Manajemen Multi-Provider AI (Relink Gateway, OpenAI, DeepSeek, Gemini, Claude), Model Dinamis, dan Database Intelligence.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge rounded-pill bg-<?= !empty($settings['is_enabled']) ? 'success' : 'danger' ?>-subtle text-<?= !empty($settings['is_enabled']) ? 'success' : 'danger' ?> border border-<?= !empty($settings['is_enabled']) ? 'success' : 'danger' ?>-subtle px-3 py-2" id="acaServiceStatusBadge">
                <i class="bi bi-<?= !empty($settings['is_enabled']) ? 'check-circle-fill' : 'power' ?> me-1"></i>Layanan AI: <span id="acaServiceStatusText"><?= !empty($settings['is_enabled']) ? 'Aktif' : 'Nonaktif' ?></span>
            </span>
            <button type="button" class="btn btn-sm btn-outline-<?= !empty($settings['is_enabled']) ? 'danger' : 'success' ?>" id="toggleAiStatusBtn" title="Aktifkan atau nonaktifkan layanan AI secara instan">
                <i class="bi bi-power me-1"></i><span id="toggleAiStatusBtnText"><?= !empty($settings['is_enabled']) ? 'Nonaktifkan' : 'Aktifkan' ?></span>
            </button>
            <span class="badge rounded-pill bg-<?= $badge['class'] ?>-subtle text-<?= $badge['class'] ?> border border-<?= $badge['class'] ?>-subtle px-3 py-2" id="acaStatusBadge">
                <i class="bi bi-<?= $badge['icon'] ?> me-1"></i><span id="acaStatusBadgeText"><?= htmlspecialchars($badge['label']) ?></span>
            </span>
        </div>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Tabel multi-provider Aca belum tersedia. Jalankan <code>database/migration_ai_assistant.sql</code> dan <code>database/migration_ai_multi_provider.sql</code> terlebih dahulu.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1"><i class="bi bi-sliders me-2 text-primary"></i>Provider Utama</h5>
                    <p class="text-muted small mb-4">
                        Aca tetap menjawab dari database terlebih dahulu. Provider AI hanya dipakai untuk pertanyaan Accounting
                        yang belum dapat dijawab secara lokal. Provider baru aktif setelah uji koneksi nyata berhasil.
                    </p>

                    <form id="acaSettingsForm" autocomplete="off">
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold small">Provider</label>
                                <select class="form-select" id="acaProvider">
                                    <?php foreach ($providers as $p): ?>
                                        <option value="<?= htmlspecialchars($p['id']) ?>" <?= !empty($p['is_active']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="acaProviderHint">Pilih provider, isi model dan API key, lalu uji.</div>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold small">Model</label>
                                <div class="input-group">
                                    <input type="text" class="form-control aca-model-input" id="acaModel" list="acaModelList"
                                           value="<?= htmlspecialchars($settings['model'] ?? '') ?>"
                                           placeholder="Nama model" required>
                                    <button class="btn btn-outline-secondary" type="button" id="acaLoadModelsBtn">
                                        <i class="bi bi-arrow-repeat me-1"></i>Muat Model
                                    </button>
                                </div>
                                <datalist id="acaModelList"></datalist>
                                <div class="form-text" id="acaModelHint">Muat daftar model dari provider, atau isi nama model secara manual.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Base URL <span class="text-muted fw-normal">(opsional)</span></label>
                            <input type="url" class="form-control aca-base-url" id="acaBaseUrl"
                                   value="<?= htmlspecialchars($settings['base_url'] ?? '') ?>"
                                   placeholder="Kosongkan untuk memakai default provider">
                            <div class="form-text">Gunakan HTTPS. HTTP hanya diizinkan untuk localhost. Jangan masukkan kredensial di URL.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small">Temperature</label>
                                <input type="number" class="form-control" id="acaTemperature" min="0" max="2" step="0.05"
                                       value="<?= htmlspecialchars((string)($settings['temperature'] ?? 0.3)) ?>">
                                <div class="form-text">0 = fokus, 2 = kreatif.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small">Maks. Output Token</label>
                                <input type="number" class="form-control" id="acaMaxTokens" min="32" max="8192" step="1"
                                       value="<?= htmlspecialchars((string)($settings['max_output_tokens'] ?? 800)) ?>">
                                <div class="form-text">32 – 8192 token.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">API Key <span id="acaProviderKeyLabel"><?= htmlspecialchars($settings['provider_label'] ?? '') ?></span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="acaApiKey" autocomplete="new-password"
                                       placeholder="<?= !empty($settings['has_api_key']) ? 'Kosongkan jika tidak ingin mengubah' : 'Masukkan API key provider' ?>">
                                <button class="btn btn-outline-secondary" type="button" id="toggleApiKeyVisibility" title="Tampilkan / Sembunyikan API Key">
                                    <i class="bi bi-eye" id="toggleApiKeyIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Status key: <strong id="acaKeyStatus"><?= htmlspecialchars($settings['key_masked'] ?: 'Belum ada') ?></strong>
                                &middot; <span id="acaKeySource"><?= htmlspecialchars($keySourceLabel) ?></span>.
                                API key disimpan terenkripsi AES-256-GCM dan tidak pernah dikembalikan ke browser.
                            </div>
                        </div>

                        <div class="alert alert-info border-0 py-2 px-3 small mb-3">
                            <i class="bi bi-shield-check me-1"></i>
                            Untuk provider yang sedang aktif, perubahan API key, model, atau base URL harus melalui
                            <strong>Uji &amp; Aktifkan</strong> agar konfigurasi baru tidak dipakai sebelum lolos uji koneksi.
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary px-3" id="acaActivateBtn">
                                <i class="bi bi-plug me-1"></i>Uji &amp; Aktifkan
                            </button>
                            <button type="button" class="btn btn-outline-primary px-3" id="acaTestOnlyBtn">
                                <i class="bi bi-broadcast me-1"></i>Uji Koneksi
                            </button>
                            <button type="submit" class="btn btn-outline-secondary" id="acaSaveBtn">
                                <i class="bi bi-save me-1"></i>Simpan Konfigurasi
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="acaDeleteKeyBtn">
                                <i class="bi bi-trash3 me-1"></i>Hapus Key
                            </button>
                        </div>
                    </form>

                    <div id="acaSettingsAlert" class="alert mt-3 mb-0 d-none" role="status"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-activity me-2 text-success"></i>Status Provider Utama</h5>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Status Layanan</dt>
                        <dd class="col-7 fw-semibold" id="acaActiveServiceStatus">
                            <span class="badge bg-<?= !empty($settings['is_enabled']) ? 'success' : 'danger' ?>" id="acaServiceStatusBadgeSmall"><?= !empty($settings['is_enabled']) ? 'Aktif' : 'Nonaktif' ?></span>
                        </dd>

                        <dt class="col-5 text-muted">Provider</dt>
                        <dd class="col-7 fw-semibold" id="acaActiveProvider"><?= htmlspecialchars($settings['provider_label'] ?? '-') ?></dd>

                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7 fw-semibold text-<?= $badge['class'] ?>" id="acaStatusDetail"><?= htmlspecialchars($badge['label']) ?></dd>

                        <dt class="col-5 text-muted">Model aktif</dt>
                        <dd class="col-7"><code id="acaActiveModel"><?= htmlspecialchars($settings['model'] ?? '-') ?></code></dd>

                        <dt class="col-5 text-muted">Sumber API key</dt>
                        <dd class="col-7" id="acaActiveKeySource"><?= htmlspecialchars($keySourceLabel) ?></dd>

                        <dt class="col-5 text-muted">Terakhir diperbarui</dt>
                        <dd class="col-7" id="acaLastUpdated"><?= !empty($settings['updated_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($settings['updated_at']))) : 'Belum pernah' ?></dd>

                        <dt class="col-5 text-muted">Terakhir diuji</dt>
                        <dd class="col-7" id="acaLastChecked"><?= !empty($settings['last_checked_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($settings['last_checked_at']))) : 'Belum pernah' ?></dd>

                        <dt class="col-5 text-muted">Pesan</dt>
                        <dd class="col-7" id="acaStatusMessage"><?= htmlspecialchars($settings['status_message'] ?? '-') ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Catatan Keamanan</h5>
                    <ul class="small text-muted mb-0 ps-3">
                        <li class="mb-2">API key per provider disimpan terenkripsi AES-256-GCM; kunci enkripsi berada di luar database.</li>
                        <li class="mb-2">Semua panggilan provider dilakukan dari backend PHP, bukan dari browser.</li>
                        <li class="mb-2">API key tidak pernah dikirim balik ke halaman ini.</li>
                        <li class="mb-2">Provider hanya diganti setelah uji koneksi nyata berhasil.</li>
                        <li>Hasil uji koneksi dan perubahan konfigurasi dicatat di audit log.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1"><i class="bi bi-shield-plus me-2 text-warning"></i>Provider Cadangan</h5>
                    <p class="text-muted small mb-4">Dipakai hanya jika provider utama gagal. Provider cadangan juga wajib lolos uji koneksi.</p>

                    <form id="acaFallbackForm" autocomplete="off">
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold small">Provider Cadangan</label>
                                <select class="form-select" id="acaFallbackProvider">
                                    <option value="">— Tanpa cadangan —</option>
                                    <?php foreach ($providers as $p): ?>
                                        <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($settings['fallback_provider'] ?? '') === $p['id'] ? 'selected' : '' ?>>
                                             <?= htmlspecialchars($p['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold small">Model Cadangan</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="acaFallbackModel" list="acaFallbackModelList"
                                           value="<?= htmlspecialchars($settings['fallback_model'] ?? '') ?>"
                                           placeholder="Nama model cadangan">
                                    <button class="btn btn-outline-secondary" type="button" id="acaFallbackModelsBtn">
                                        <i class="bi bi-arrow-repeat me-1"></i>Muat
                                    </button>
                                </div>
                                <datalist id="acaFallbackModelList"></datalist>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Base URL Cadangan <span class="text-muted fw-normal">(opsional)</span></label>
                            <input type="url" class="form-control aca-base-url" id="acaFallbackBaseUrl" placeholder="Kosongkan untuk default provider">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">API Key Cadangan</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="acaFallbackApiKey" autocomplete="new-password"
                                       placeholder="Kosongkan jika key provider ini sudah tersimpan">
                                <button class="btn btn-outline-secondary" type="button" id="toggleFallbackApiKeyVisibility" title="Tampilkan / Sembunyikan API Key">
                                    <i class="bi bi-eye" id="toggleFallbackApiKeyIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary" id="acaFallbackTestBtn">
                                <i class="bi bi-plug me-1"></i>Uji &amp; Simpan Cadangan
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="acaFallbackClearBtn">
                                <i class="bi bi-x-circle me-1"></i>Hapus Cadangan
                            </button>
                        </div>
                    </form>

                    <div class="alert alert-<?= $fallbackBadge['class'] ?> border-0 mt-3 mb-0 small" id="acaFallbackStatus">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <span>
                                <i class="bi bi-<?= $fallbackBadge['icon'] ?> me-1"></i>
                                <strong id="acaFallbackProviderLabel"><?= htmlspecialchars($settings['fallback_provider_label'] ?: 'Belum ada provider cadangan') ?></strong>
                                <span id="acaFallbackModelLabel"><?= !empty($settings['fallback_model']) ? ' / ' . htmlspecialchars($settings['fallback_model']) : '' ?></span>
                            </span>
                            <span class="text-muted" id="acaFallbackStatusLabel"><?= htmlspecialchars($fallbackBadge['label']) ?></span>
                        </div>
                        <div class="mt-1" id="acaFallbackMessage"><?= htmlspecialchars($settings['fallback_status_message'] ?? '') ?></div>
                    </div>

                    <div id="acaFallbackAlert" class="alert mt-3 mb-0 d-none" role="status"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-router me-2 text-info"></i>Status Semua Provider</h5>
                    <div class="table-responsive">
                        <table class="table table-sm aca-status-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="acaProviderStatusBody">
                                <?php foreach ($providers as $p): ?>
                                    <?php $pb = $statusMap[(string)$p['status']] ?? $statusMap['api_error']; ?>
                                    <tr data-provider-row="<?= htmlspecialchars($p['id']) ?>">
                                        <td>
                                            <div class="fw-semibold small">
                                                <?= htmlspecialchars($p['label']) ?>
                                                <span class="badge bg-primary-subtle text-primary ms-1<?= !empty($p['is_active']) ? '' : ' d-none' ?>" data-provider-active-badge>Utama</span>
                                                <span class="badge bg-warning-subtle text-warning ms-1<?= !empty($p['is_fallback']) ? '' : ' d-none' ?>" data-provider-fallback-badge>Cadangan</span>
                                            </div>
                                            <div class="text-muted" style="font-size:.72rem;" data-provider-model><?= htmlspecialchars($p['model'] ?: '-') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $pb['class'] ?>-subtle text-<?= $pb['class'] ?>" data-provider-status>
                                                <?= htmlspecialchars($pb['label']) ?>
                                            </span>
                                            <div class="text-muted" style="font-size:.72rem;" data-provider-key>
                                                <?= !empty($p['has_api_key']) ? 'Key tersedia' : 'Belum ada key' ?>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary aca-test-provider"
                                                    data-provider="<?= htmlspecialchars($p['id']) ?>"
                                                    data-model="<?= htmlspecialchars($p['candidate_model'] ?: $p['model']) ?>"
                                                    data-base-url="<?= htmlspecialchars($p['base_url']) ?>">
                                                Uji
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI DATABASE INTELLIGENCE & KNOWLEDGE BASE -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-database-check me-2 text-primary"></i>AI Database Intelligence &amp; Knowledge Base</h5>
                    <p class="text-muted small mb-0">Pemetaan skema database aktual, glosarium istilah akuntansi, dan pemahaman logika bisnis lokal.</p>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                    <i class="bi bi-shield-lock-fill me-1"></i>Database-First &amp; Safe SQL Engine Aktif
                </span>
            </div>

            <ul class="nav nav-pills mb-3" id="acaKbTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active small py-2 px-3" id="acaKbGlossaryTab" data-bs-toggle="pill" data-bs-target="#acaKbGlossary" type="button" role="tab">
                        <i class="bi bi-journal-bookmark me-1"></i>Glosarium Akuntansi
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link small py-2 px-3" id="acaKbSchemaTab" data-bs-toggle="pill" data-bs-target="#acaKbSchema" type="button" role="tab">
                        <i class="bi bi-diagram-3 me-1"></i>Skema Database Aktual
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link small py-2 px-3" id="acaKbEvalTab" data-bs-toggle="pill" data-bs-target="#acaKbEval" type="button" role="tab">
                        <i class="bi bi-check2-all me-1"></i>Evaluasi 10 Pertanyaan
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="acaKbContent">
                <!-- Glosarium Tab -->
                <div class="tab-pane fade show active" id="acaKbGlossary" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:200px;">Istilah Bisnis</th>
                                    <th>Definisi Akuntansi</th>
                                    <th style="width:220px;">Rumus / Sumber Data</th>
                                    <th>Konteks Aplikasi Saat Ini</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <tr>
                                    <td class="fw-semibold">Omzet Penjualan</td>
                                    <td>Total penerimaan kotor dari transaksi penjualan barang.</td>
                                    <td><code>SUM(transactions.total_amount)</code></td>
                                    <td>Dihitung dari seluruh transaksi penjualan yang diselesaikan kasir.</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Laba Kotor (Gross Profit)</td>
                                    <td>Penjualan bersih dikurangi Harga Pokok Penjualan (HPP).</td>
                                    <td><code>Omzet − HPP</code></td>
                                    <td>Tabel produk saat ini baru mencatat harga jual. Karena modal beli belum dicatat terpisah, laba kotor setara omzet kotor.</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Piutang Usaha</td>
                                    <td>Hak tagih atas penjualan tempo yang belum dilunasi pembeli.</td>
                                    <td><code>Rp 0 (Tunai Langsung)</code></td>
                                    <td>Seluruh transaksi kasir diselesaikan tunai saat checkout, sehingga piutang tertunggak = Rp 0.</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Utang Usaha</td>
                                    <td>Kewajiban finansial atas pengadaan barang tempo ke supplier.</td>
                                    <td><code>Rp 0 (Belum Dicatat)</code></td>
                                    <td>Pengadaan barang dicatat langsung di modul produk tanpa modul utang tempo, sehingga utang tercatat = Rp 0.</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Pembelian Barang</td>
                                    <td>Pengadaan stok barang dagangan dari supplier.</td>
                                    <td><code>Menu Produk (Stok)</code></td>
                                    <td>Belum ada tabel faktur beli terpisah; persediaan dikelola langsung via kuantitas stok produk.</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Saldo Kas &amp; Bank</td>
                                    <td>Akumulasi uang kas yang terkumpul dari penjualan kasir.</td>
                                    <td><code>SUM(transactions.total_amount)</code></td>
                                    <td>Mencerminkan arus kas masuk dari seluruh checkout kasir retail.</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Persediaan (Stok)</td>
                                    <td>Fisik barang dagangan siap jual dan nilai jualnya.</td>
                                    <td><code>SUM(products.stock)</code></td>
                                    <td>Stok otomatis berkurang secara konsisten setiap transaksi kasir berhasil.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Skema Database Tab -->
                <div class="tab-pane fade" id="acaKbSchema" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold mb-2"><i class="bi bi-box me-1 text-primary"></i>tabel <code>products</code></h6>
                                <p class="text-muted small mb-2">Penyimpanan katalog produk &amp; stok toko.</p>
                                <ul class="small mb-0 ps-3">
                                    <li><code>id</code> (PK, bigint unsigned)</li>
                                    <li><code>user_id</code> (FK -&gt; users.id, pemilik toko)</li>
                                    <li><code>product_name</code> (varchar 255)</li>
                                    <li><code>stock</code> (int, kuantitas unit fisik)</li>
                                    <li><code>price</code> (decimal 15,2, harga jual per unit)</li>
                                    <li><code>is_active</code> (tinyint 1, status aktif)</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold mb-2"><i class="bi bi-receipt me-1 text-success"></i>tabel <code>transactions</code></h6>
                                <p class="text-muted small mb-2">Header transaksi penjualan kasir POS.</p>
                                <ul class="small mb-0 ps-3">
                                    <li><code>id</code> (PK, bigint unsigned)</li>
                                    <li><code>user_id</code> (FK -&gt; users.id, pemilik toko)</li>
                                    <li><code>cashier_id</code> (FK -&gt; users.id, kasir pemroses)</li>
                                    <li><code>transaction_date</code> (datetime, waktu transaksi)</li>
                                    <li><code>total_amount</code> (decimal 15,2, total nota/omzet)</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold mb-2"><i class="bi bi-list-check me-1 text-info"></i>tabel <code>transaction_details</code></h6>
                                <p class="text-muted small mb-2">Item per transaksi penjualan.</p>
                                <ul class="small mb-0 ps-3">
                                    <li><code>id</code> (PK, bigint unsigned)</li>
                                    <li><code>transaction_id</code> (FK -&gt; transactions.id)</li>
                                    <li><code>product_id</code> (FK -&gt; products.id)</li>
                                    <li><code>quantity</code> (int, jumlah unit terjual)</li>
                                    <li><code>price</code> (decimal 15,2, harga saat transaksi)</li>
                                    <li><code>subtotal</code> (decimal 15,2, quantity * price)</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold mb-2"><i class="bi bi-people me-1 text-warning"></i>tabel <code>users</code></h6>
                                <p class="text-muted small mb-2">Manajemen akun pengguna &amp; peran RBAC.</p>
                                <ul class="small mb-0 ps-3">
                                    <li><code>id</code> (PK, bigint unsigned)</li>
                                    <li><code>nama_lengkap</code>, <code>email</code>, <code>whatsapp</code></li>
                                    <li><code>role</code> (enum: 'admin', 'kasir', 'pelanggan')</li>
                                    <li><code>admin_id</code> (FK -&gt; users.id, pemilik toko)</li>
                                    <li><span class="text-danger">Kolom password &amp; token disembunyikan secara otomatis dari AI.</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evaluasi 10 Pertanyaan Tab -->
                <div class="tab-pane fade" id="acaKbEval" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th style="width:260px;">Pertanyaan Evaluasi</th>
                                    <th>Sumber Data &amp; Logika Kueri Database</th>
                                    <th style="width:120px;">Status</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <tr>
                                    <td>1</td>
                                    <td class="fw-semibold">Berapa total penjualan hari ini?</td>
                                    <td>Kueri <code>transactions</code> dengan filter <code>DATE(transaction_date) = CURDATE()</code>.</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td class="fw-semibold">Berapa total penjualan bulan ini?</td>
                                    <td>Kueri <code>transactions</code> dengan filter <code>YEAR() = YEAR(NOW()) AND MONTH() = MONTH(NOW())</code>.</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td class="fw-semibold">Berapa total pembelian bulan ini?</td>
                                    <td>Mengecek modul pembelian; menjelaskan secara jujur stok dikelola via tabel produk (tercatat Rp 0).</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td class="fw-semibold">Berapa jumlah barang aktif?</td>
                                    <td>Kueri <code>products</code> dengan <code>COUNT(*)</code> dan <code>SUM(stock) WHERE is_active = 1</code>.</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>5</td>
                                    <td class="fw-semibold">Barang mana yang stoknya hampir habis?</td>
                                    <td>Kueri <code>products WHERE stock &lt;= 10 AND is_active = 1 ORDER BY stock ASC</code>.</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>6</td>
                                    <td class="fw-semibold">Siapa pelanggan dengan transaksi terbesar?</td>
                                    <td>Kueri <code>transactions ORDER BY total_amount DESC</code> dan kueri <code>users WHERE role = 'pelanggan'</code>.</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>7</td>
                                    <td class="fw-semibold">Berapa total piutang?</td>
                                    <td>Menjelaskan bahwa transaksi kasir POS tunai langsung (Rp 0 piutang tertunggak).</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>8</td>
                                    <td class="fw-semibold">Berapa total utang?</td>
                                    <td>Menjelaskan bahwa belum ada modul utang tempo ke supplier (tercatat Rp 0).</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>9</td>
                                    <td class="fw-semibold">Berapa laba kotor?</td>
                                    <td>Menghitung total omzet penjualan dan menjelaskan batasan kolom modal beli pada tabel produk.</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                                <tr>
                                    <td>10</td>
                                    <td class="fw-semibold">Berapa saldo kas dan bank?</td>
                                    <td>Kueri akumulasi kas dari total transaksi penjualan kasir (<code>SUM(total_amount)</code>).</td>
                                    <td><span class="badge bg-success">Tervalidasi</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-secondary"></i>Audit Log</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:170px;">Waktu</th>
                            <th style="width:180px;">Aksi</th>
                            <th>Detail</th>
                            <th style="width:130px;">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada aktivitas pengaturan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted small"><?= htmlspecialchars(date('d M Y, H:i', strtotime($log['created_at']))) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td class="small"><?= htmlspecialchars($log['detail'] ?? '-') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const BASEURL = '<?= BASEURL ?>';
    const CSRF = '<?= CSRF::token() ?>';
    const CATALOG = <?= $catalogJson ?: '[]' ?>;

    const STATUS = {
        connected:               { label: 'Terhubung',                  cls: 'success', icon: 'check-circle-fill' },
        testing:                 { label: 'Menguji Koneksi',            cls: 'info',    icon: 'arrow-repeat' },
        unverified:              { label: 'Belum Diverifikasi',         cls: 'warning', icon: 'question-circle-fill' },
        not_configured:          { label: 'Belum Dikonfigurasi',        cls: 'secondary', icon: 'dash-circle' },
        invalid_key:             { label: 'API Key Tidak Valid',         cls: 'danger',  icon: 'x-circle-fill' },
        model_unavailable:       { label: 'Model Tidak Tersedia',        cls: 'danger',  icon: 'x-circle-fill' },
        model_list_unavailable:  { label: 'Daftar Model Tidak Tersedia', cls: 'warning', icon: 'list' },
        invalid_base_url:        { label: 'Base URL Tidak Valid',        cls: 'danger',  icon: 'link-45deg' },
        rate_limited:            { label: 'Kuota / Rate Limit Habis',    cls: 'warning', icon: 'hourglass-split' },
        billing_error:           { label: 'Billing / Izin Bermasalah',   cls: 'danger',  icon: 'credit-card-2-front' },
        permission_denied:       { label: 'Izin Ditolak',                cls: 'danger',  icon: 'shield-lock' },
        connection_error:        { label: 'Gangguan Koneksi',            cls: 'danger',  icon: 'wifi-off' },
        server_error:            { label: 'Kesalahan Server Provider',   cls: 'danger',  icon: 'exclamation-octagon-fill' },
        api_error:               { label: 'Error API',                   cls: 'danger',  icon: 'exclamation-triangle-fill' }
    };

    const KEY_SOURCE = {
        database: 'Tersimpan terenkripsi di database',
        environment: 'Dari environment (.env)',
        form: 'Dari form (belum disimpan)',
        none: 'Belum ada API key'
    };

    const form = document.getElementById('acaSettingsForm');
    const fallbackForm = document.getElementById('acaFallbackForm');
    const alertBox = document.getElementById('acaSettingsAlert');
    const fallbackAlertBox = document.getElementById('acaFallbackAlert');
    const providerSelect = document.getElementById('acaProvider');
    const modelInput = document.getElementById('acaModel');
    const modelList = document.getElementById('acaModelList');
    const baseUrlInput = document.getElementById('acaBaseUrl');
    const apiKeyInput = document.getElementById('acaApiKey');
    const saveBtn = document.getElementById('acaSaveBtn');
    const activateBtn = document.getElementById('acaActivateBtn');
    const deleteBtn = document.getElementById('acaDeleteKeyBtn');
    const modelsBtn = document.getElementById('acaLoadModelsBtn');
    const fallbackProvider = document.getElementById('acaFallbackProvider');
    const fallbackModel = document.getElementById('acaFallbackModel');
    const fallbackBaseUrl = document.getElementById('acaFallbackBaseUrl');
    const fallbackApiKey = document.getElementById('acaFallbackApiKey');
    const fallbackModelsBtn = document.getElementById('acaFallbackModelsBtn');
    const fallbackTestBtn = document.getElementById('acaFallbackTestBtn');
    const fallbackClearBtn = document.getElementById('acaFallbackClearBtn');

    let currentSettings = <?= json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function metaFor(providerId) {
        return CATALOG.find(function (m) { return m.id === providerId; }) || null;
    }

    function providerInfo(providerId) {
        const list = (currentSettings && currentSettings.providers) || [];
        return list.find(function (p) { return p.id === providerId; }) || null;
    }

    function providerHint(info) {
        if (!info) return 'Pilih provider, isi model dan API key, lalu uji.';
        if (info.is_active) {
            return 'Provider aktif: gunakan Uji & Aktifkan untuk mengganti API key, model, atau base URL.';
        }
        return info.supports_model_list
            ? 'Provider ini mendukung daftar model.'
            : 'Isi nama model secara manual.';
    }

    function showAlert(box, type, message) {
        if (!box) return;
        box.className = 'alert alert-' + type + ' mt-3 mb-0';
        box.textContent = message;
        box.classList.remove('d-none');
    }

    function setBusy(btn, busy, label) {
        if (!btn) return;
        if (busy) {
            btn.dataset.original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (label || 'Memproses...');
        } else {
            btn.disabled = false;
            if (btn.dataset.original) btn.innerHTML = btn.dataset.original;
        }
    }

    async function post(url, payload) {
        const res = await fetch(BASEURL + url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf_token: CSRF }, payload || {}))
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            data = { success: false, message: 'Respons server tidak valid.' };
        }
        return data;
    }

    function dateLabel(value) {
        if (!value) return 'Belum pernah';
        const d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return value;
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        const pad = function (n) { return String(n).padStart(2, '0'); };
        return pad(d.getDate()) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ', ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function setStatus(status, message, checkedAt) {
        const meta = STATUS[status] || STATUS.api_error;
        const badge = document.getElementById('acaStatusBadge');
        if (badge) {
            badge.className = 'badge rounded-pill bg-' + meta.cls + '-subtle text-' + meta.cls +
                ' border border-' + meta.cls + '-subtle px-3 py-2';
            badge.innerHTML = '<i class="bi bi-' + meta.icon + ' me-1"></i><span id="acaStatusBadgeText">' + meta.label + '</span>';
        }
        const detail = document.getElementById('acaStatusDetail');
        if (detail) {
            detail.textContent = meta.label;
            detail.className = 'col-7 fw-semibold text-' + meta.cls;
        }
        if (typeof message !== 'undefined' && message !== null) {
            const el = document.getElementById('acaStatusMessage');
            if (el) el.textContent = message || '-';
        }
        if (checkedAt) {
            const el = document.getElementById('acaLastChecked');
            if (el) el.textContent = dateLabel(checkedAt);
        }
    }

    function fillProviderForm(providerId, useCandidate) {
        const info = providerInfo(providerId);
        const meta = metaFor(providerId);
        if (!info) return;

        const modelValue = useCandidate ? (info.candidate_model || info.model) : info.model;
        if (modelValue) modelInput.value = modelValue;
        if (baseUrlInput) baseUrlInput.value = info.base_url || (meta ? meta.base_url : '');
        if (apiKeyInput) {
            apiKeyInput.value = '';
            apiKeyInput.placeholder = info.has_api_key ? 'Kosongkan jika tidak ingin mengubah' : 'Masukkan API key provider';
        }

        const keyLabel = document.getElementById('acaProviderKeyLabel');
        if (keyLabel) keyLabel.textContent = info.label;
        const keyStatus = document.getElementById('acaKeyStatus');
        if (keyStatus) keyStatus.textContent = info.has_api_key ? '••••••••' : 'Belum ada';
        const keySource = document.getElementById('acaKeySource');
        if (keySource) keySource.textContent = KEY_SOURCE[info.key_source] || KEY_SOURCE.none;
        const hint = document.getElementById('acaProviderHint');
        if (hint) hint.textContent = providerHint(info);
    }

    function updateProviderRows(settings) {
        const list = (settings && settings.providers) || [];
        list.forEach(function (p) {
            const row = document.querySelector('[data-provider-row="' + p.id + '"]');
            if (!row) return;
            const statusEl = row.querySelector('[data-provider-status]');
            if (statusEl) {
                const meta = STATUS[p.status] || STATUS.api_error;
                statusEl.className = 'badge bg-' + meta.cls + '-subtle text-' + meta.cls;
                statusEl.textContent = meta.label;
            }
            const keyEl = row.querySelector('[data-provider-key]');
            if (keyEl) keyEl.textContent = p.has_api_key ? 'Key tersedia' : 'Belum ada key';
            const modelEl = row.querySelector('[data-provider-model]');
            if (modelEl) modelEl.textContent = p.model || '-';
            const activeBadge = row.querySelector('[data-provider-active-badge]');
            if (activeBadge) activeBadge.classList.toggle('d-none', !p.is_active);
            const fallbackBadge = row.querySelector('[data-provider-fallback-badge]');
            if (fallbackBadge) fallbackBadge.classList.toggle('d-none', !p.is_fallback);
            const btn = row.querySelector('.aca-test-provider');
            if (btn) {
                btn.dataset.model = p.candidate_model || p.model || '';
                btn.dataset.baseUrl = p.base_url || '';
            }
        });
    }

    function updateFallbackPanel(settings) {
        if (!settings) return;
        const fallbackStatus = settings.fallback_status || 'not_configured';
        const meta = STATUS[fallbackStatus] || STATUS.not_configured;
        const box = document.getElementById('acaFallbackStatus');
        if (box) box.className = 'alert alert-' + meta.cls + ' border-0 mt-3 mb-0 small';
        const label = document.getElementById('acaFallbackProviderLabel');
        if (label) label.textContent = settings.fallback_provider_label || 'Belum ada provider cadangan';
        const model = document.getElementById('acaFallbackModelLabel');
        if (model) model.textContent = settings.fallback_model ? ' / ' + settings.fallback_model : '';
        const statusLabel = document.getElementById('acaFallbackStatusLabel');
        if (statusLabel) statusLabel.textContent = meta.label;
        const message = document.getElementById('acaFallbackMessage');
        if (message) message.textContent = settings.fallback_status_message || '';
    }

    function updateSelectedProviderMeta() {
        const info = providerInfo(providerSelect.value);
        if (!info) return;
        const keyLabel = document.getElementById('acaProviderKeyLabel');
        if (keyLabel) keyLabel.textContent = info.label;
        const keyStatus = document.getElementById('acaKeyStatus');
        if (keyStatus) keyStatus.textContent = info.has_api_key ? '••••••••' : 'Belum ada';
        const keySource = document.getElementById('acaKeySource');
        if (keySource) keySource.textContent = KEY_SOURCE[info.key_source] || KEY_SOURCE.none;
        const hint = document.getElementById('acaProviderHint');
        if (hint) hint.textContent = providerHint(info);
    }

    function applySettings(settings) {
        if (!settings) return;
        currentSettings = settings;

        const isEnabled = Boolean(settings.is_enabled);
        const sBadge = document.getElementById('acaServiceStatusBadge');
        if (sBadge) {
            sBadge.className = 'badge rounded-pill ' + (isEnabled ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle') + ' px-3 py-2';
        }
        const sText = document.getElementById('acaServiceStatusText');
        if (sText) sText.textContent = isEnabled ? 'Aktif' : 'Nonaktif';
        const tBtn = document.getElementById('toggleAiStatusBtn');
        if (tBtn) {
            tBtn.className = 'btn btn-sm ' + (isEnabled ? 'btn-outline-danger' : 'btn-outline-success');
        }
        const tBtnText = document.getElementById('toggleAiStatusBtnText');
        if (tBtnText) tBtnText.textContent = isEnabled ? 'Nonaktifkan' : 'Aktifkan';
        const smallBadge = document.getElementById('acaServiceStatusBadgeSmall');
        if (smallBadge) {
            smallBadge.className = 'badge ' + (isEnabled ? 'bg-success' : 'bg-danger');
            smallBadge.textContent = isEnabled ? 'Aktif' : 'Nonaktif';
        }

        const activeProvider = document.getElementById('acaActiveProvider');
        if (activeProvider) activeProvider.textContent = settings.provider_label || '-';
        const activeModel = document.getElementById('acaActiveModel');
        if (activeModel) activeModel.textContent = settings.model || '-';
        const activeKeySource = document.getElementById('acaActiveKeySource');
        if (activeKeySource) activeKeySource.textContent = KEY_SOURCE[settings.key_source] || KEY_SOURCE.none;
        const activeUpdated = document.getElementById('acaLastUpdated');
        if (activeUpdated) activeUpdated.textContent = dateLabel(settings.updated_at);

        setStatus(settings.status || 'not_configured', settings.status_message || '', settings.last_checked_at || null);
        updateFallbackPanel(settings);
        updateProviderRows(settings);

        if (fallbackProvider) {
            fallbackProvider.value = settings.fallback_provider || '';
            if (!fallbackProvider.value) {
                fallbackModel.value = '';
                fallbackBaseUrl.value = '';
            }
        }
        updateSelectedProviderMeta();
    }

    function applySuggestedModel(data) {
        if (data.success || !data.suggested_model) return;
        const ok = confirm('Model saat ini tidak tersedia.\n\nGunakan model yang disarankan: ' + data.suggested_model + '?');
        if (ok) {
            modelInput.value = data.suggested_model;
            showAlert(alertBox, 'warning', 'Model diganti menjadi ' + data.suggested_model + '. Klik Uji & Aktifkan untuk memverifikasi.');
        }
    }

    providerSelect.addEventListener('change', function () {
        fillProviderForm(providerSelect.value, true);
        showAlert(alertBox, 'info', 'Konfigurasi provider dipilih. Uji koneksi sebelum mengaktifkannya.');
    });

    fallbackProvider.addEventListener('change', function () {
        const info = providerInfo(fallbackProvider.value);
        const meta = metaFor(fallbackProvider.value);
        if (info) {
            fallbackModel.value = info.candidate_model || info.model || '';
            fallbackBaseUrl.value = info.base_url || (meta ? meta.base_url : '');
        } else {
            fallbackModel.value = '';
            fallbackBaseUrl.value = '';
        }
        fallbackApiKey.value = '';
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        setBusy(saveBtn, true, 'Menyimpan...');
        try {
            const data = await post('/aisettings/save', {
                provider: providerSelect.value,
                model: modelInput.value.trim(),
                base_url: baseUrlInput.value.trim(),
                temperature: document.getElementById('acaTemperature').value,
                max_output_tokens: document.getElementById('acaMaxTokens').value,
                api_key: apiKeyInput.value
            });
            if (data.success) apiKeyInput.value = '';
            showAlert(alertBox, data.success ? 'success' : 'danger', data.message || 'Selesai.');
            applySettings(data.settings);
        } catch (err) {
            showAlert(alertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(saveBtn, false);
        }
    });

    const testOnlyBtn = document.getElementById('acaTestOnlyBtn');
    const toggleStatusBtn = document.getElementById('toggleAiStatusBtn');
    const toggleApiKeyBtn = document.getElementById('toggleApiKeyVisibility');
    const toggleFallbackApiKeyBtn = document.getElementById('toggleFallbackApiKeyVisibility');

    if (toggleApiKeyBtn && apiKeyInput) {
        toggleApiKeyBtn.addEventListener('click', function () {
            const icon = document.getElementById('toggleApiKeyIcon');
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                apiKeyInput.type = 'password';
                if (icon) icon.className = 'bi bi-eye';
            }
        });
    }

    if (toggleFallbackApiKeyBtn && fallbackApiKey) {
        toggleFallbackApiKeyBtn.addEventListener('click', function () {
            const icon = document.getElementById('toggleFallbackApiKeyIcon');
            if (fallbackApiKey.type === 'password') {
                fallbackApiKey.type = 'text';
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                fallbackApiKey.type = 'password';
                if (icon) icon.className = 'bi bi-eye';
            }
        });
    }

    if (toggleStatusBtn) {
        toggleStatusBtn.addEventListener('click', async function () {
            setBusy(toggleStatusBtn, true, 'Memproses...');
            try {
                const data = await post('/aisettings/toggleStatus', {});
                showAlert(alertBox, data.success ? 'success' : 'danger', data.message || 'Status layanan AI diperbarui.');
                applySettings(data.settings);
            } catch (err) {
                showAlert(alertBox, 'danger', 'Gagal mengubah status layanan AI.');
            } finally {
                setBusy(toggleStatusBtn, false);
            }
        });
    }

    if (testOnlyBtn) {
        testOnlyBtn.addEventListener('click', async function () {
            setBusy(testOnlyBtn, true, 'Menguji...');
            setStatus('testing', 'Menguji koneksi provider...');
            try {
                const data = await post('/aisettings/test', {
                    provider: providerSelect.value,
                    model: modelInput.value.trim(),
                    base_url: baseUrlInput.value.trim(),
                    temperature: document.getElementById('acaTemperature').value,
                    max_output_tokens: document.getElementById('acaMaxTokens').value,
                    api_key: apiKeyInput.value,
                    activate: 'test'
                });
                if (data.success && data.persisted !== false) apiKeyInput.value = '';
                showAlert(alertBox, data.success ? 'success' : 'danger', (data.success ? 'Uji koneksi BERHASIL: ' : 'Uji koneksi GAGAL: ') + (data.message || 'Selesai.'));
                applySettings(data.settings);
                if (!data.success) {
                    applySuggestedModel(data);
                }
            } catch (err) {
                showAlert(alertBox, 'danger', 'Gagal menghubungi server.');
            } finally {
                setBusy(testOnlyBtn, false);
            }
        });
    }

    activateBtn.addEventListener('click', async function () {
        if (!confirm('Uji koneksi dan aktifkan provider ini jika berhasil?')) return;
        setBusy(activateBtn, true, 'Menguji...');
        setStatus('testing', 'Menguji koneksi provider...');
        try {
            const data = await post('/aisettings/test', {
                provider: providerSelect.value,
                model: modelInput.value.trim(),
                base_url: baseUrlInput.value.trim(),
                temperature: document.getElementById('acaTemperature').value,
                max_output_tokens: document.getElementById('acaMaxTokens').value,
                api_key: apiKeyInput.value,
                activate: 'active'
            });
            if (data.success) apiKeyInput.value = '';
            showAlert(alertBox, data.success ? 'success' : 'danger', data.message || 'Selesai.');
            applySettings(data.settings);
            if (data.success && data.activated && data.role === 'active' && data.settings) {
                providerSelect.value = data.settings.provider;
                fillProviderForm(providerSelect.value, false);
            }
            if (!data.success) {
                applySuggestedModel(data);
            }
        } catch (err) {
            showAlert(alertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(activateBtn, false);
        }
    });

    modelsBtn.addEventListener('click', async function () {
        setBusy(modelsBtn, true, 'Memuat...');
        try {
            const data = await post('/aisettings/models', {
                provider: providerSelect.value,
                model: modelInput.value.trim(),
                base_url: baseUrlInput.value.trim(),
                api_key: apiKeyInput.value
            });
            const hint = document.getElementById('acaModelHint');
            if (data.success && Array.isArray(data.models)) {
                modelList.innerHTML = '';
                data.models.forEach(function (m) {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.label = (m.name || m.id) + ' — ' + m.id;
                    modelList.appendChild(opt);
                });
                let text = data.models.length + ' model tersedia. Pilih dari daftar atau isi manual.';
                if (data.current_model && data.current_available === false) {
                    text = 'Model "' + data.current_model + '" tidak ada dalam daftar. ' +
                        (data.recommended_model ? 'Contoh model yang tersedia: ' + data.recommended_model + '. ' : '') +
                        'Pilih model lain lalu uji koneksi.';
                    showAlert(alertBox, 'warning', text);
                } else {
                    showAlert(alertBox, 'success', data.message || (data.models.length + ' model dimuat.'));
                }
                if (hint) hint.textContent = text;
            } else {
                showAlert(alertBox, 'danger', data.message || 'Gagal memuat daftar model.');
                if (hint) hint.textContent = data.message || 'Gagal memuat daftar model.';
            }
        } catch (err) {
            showAlert(alertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(modelsBtn, false);
        }
    });

    deleteBtn.addEventListener('click', async function () {
        if (!confirm('Hapus API key provider yang sedang dipilih dari database?')) return;
        setBusy(deleteBtn, true, 'Menghapus...');
        try {
            const data = await post('/aisettings/deleteKey', { provider: providerSelect.value });
            showAlert(alertBox, data.success ? 'success' : 'danger', data.message || 'Selesai.');
            apiKeyInput.value = '';
            applySettings(data.settings);
        } catch (err) {
            showAlert(alertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(deleteBtn, false);
        }
    });

    fallbackModelsBtn.addEventListener('click', async function () {
        if (!fallbackProvider.value) {
            showAlert(fallbackAlertBox, 'warning', 'Pilih provider cadangan terlebih dahulu.');
            return;
        }
        setBusy(fallbackModelsBtn, true, 'Memuat...');
        try {
            const data = await post('/aisettings/models', {
                provider: fallbackProvider.value,
                model: fallbackModel.value.trim(),
                base_url: fallbackBaseUrl.value.trim(),
                api_key: fallbackApiKey.value
            });
            if (data.success && Array.isArray(data.models)) {
                const list = document.getElementById('acaFallbackModelList');
                list.innerHTML = '';
                data.models.forEach(function (m) {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.label = (m.name || m.id) + ' — ' + m.id;
                    list.appendChild(opt);
                });
                showAlert(fallbackAlertBox, 'success', data.message || (data.models.length + ' model dimuat.'));
            } else {
                showAlert(fallbackAlertBox, 'danger', data.message || 'Gagal memuat daftar model cadangan.');
            }
        } catch (err) {
            showAlert(fallbackAlertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(fallbackModelsBtn, false);
        }
    });

    fallbackTestBtn.addEventListener('click', async function () {
        if (!fallbackProvider.value) {
            showAlert(fallbackAlertBox, 'warning', 'Pilih provider cadangan terlebih dahulu.');
            return;
        }
        setBusy(fallbackTestBtn, true, 'Menguji...');
        try {
            const data = await post('/aisettings/test', {
                provider: fallbackProvider.value,
                model: fallbackModel.value.trim(),
                base_url: fallbackBaseUrl.value.trim(),
                api_key: fallbackApiKey.value,
                activate: 'fallback'
            });
            if (data.success) fallbackApiKey.value = '';
            showAlert(fallbackAlertBox, data.success ? 'success' : 'danger', data.message || 'Selesai.');
            applySettings(data.settings);
        } catch (err) {
            showAlert(fallbackAlertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(fallbackTestBtn, false);
        }
    });

    fallbackClearBtn.addEventListener('click', async function () {
        if (!confirm('Hapus provider cadangan?')) return;
        setBusy(fallbackClearBtn, true, 'Menghapus...');
        try {
            const data = await post('/aisettings/clearFallback', {});
            showAlert(fallbackAlertBox, data.success ? 'success' : 'danger', data.message || 'Selesai.');
            applySettings(data.settings);
        } catch (err) {
            showAlert(fallbackAlertBox, 'danger', 'Gagal menghubungi server.');
        } finally {
            setBusy(fallbackClearBtn, false);
        }
    });

    document.querySelectorAll('.aca-test-provider').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            setBusy(btn, true, 'Uji...');
            try {
                const data = await post('/aisettings/test', {
                    provider: btn.dataset.provider,
                    model: btn.dataset.model || '',
                    base_url: btn.dataset.baseUrl || '',
                    activate: 'test'
                });
                applySettings(data.settings);
                const providerRow = ((data.settings && data.settings.providers) || []).find(function (p) { return p.id === btn.dataset.provider; });
                showAlert(alertBox, data.success ? 'success' : 'danger',
                    (providerRow ? providerRow.label : btn.dataset.provider) + ': ' + (data.message || 'Selesai.'));
            } catch (err) {
                showAlert(alertBox, 'danger', 'Gagal menghubungi server.');
            } finally {
                setBusy(btn, false);
            }
        });
    });

    // Isi form awal sesuai provider aktif.
    fillProviderForm(providerSelect.value, false);
    if (fallbackProvider.value) {
        const fb = providerInfo(fallbackProvider.value);
        if (fb) {
            fallbackBaseUrl.value = fb.base_url || '';
        }
    }
})();
</script>
