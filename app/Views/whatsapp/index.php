<?php
require_once __DIR__ . '/../../Services/WhatsApp/WhatsAppService.php';

$setting       = $data['setting'] ?? null;
$logs          = $data['logs'] ?? [];
$stats         = $data['stats'] ?? ['total' => 0, 'total_sent' => 0, 'total_simulated' => 0, 'total_failed' => 0];
$isConnected   = !empty($data['is_connected']);
$isDevMode     = !empty($data['is_dev_mode']);
$hasRealGateway = !empty($data['has_real_gateway']);
$defaultPhone  = $data['default_phone'] ?? '';
$hasApiKey     = !empty($setting['has_api_key']);
$csrfToken     = CSRF::token();

// Format nomor tampilan
$rawPhone = $setting['phone_number'] ?? $defaultPhone;
$displayPhone = WhatsAppService::formatDisplay($rawPhone);
$cleanInputPhone = preg_replace('/^62/', '0', WhatsAppService::normalizeNumber($rawPhone));
if (empty($cleanInputPhone) || $cleanInputPhone === '0') {
    $cleanInputPhone = '08';
}
?>

<div class="whatsapp-module pb-5">
    <!-- Header Hero Banner -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #075E54 0%, #128C7E 50%, #25D366 100%); color: #fff;">
        <div class="card-body p-4 p-lg-5 position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-3" style="background: rgba(255,255,255,0.18); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.25); font-size: 0.8rem; font-weight: 600;">
                        <i class="bi bi-shield-check"></i> Integrasi Resmi WhatsApp Gateway
                    </div>
                    <h2 class="fw-800 mb-2 display-6" style="font-weight: 800; letter-spacing: -0.5px;">
                        Notifikasi & Koneksi WhatsApp
                    </h2>
                    <p class="mb-0 text-white-50" style="font-size: 1rem; max-width: 650px; color: rgba(255,255,255,0.9) !important;">
                        Hubungkan nomor WhatsApp Anda dengan verifikasi OTP untuk menerima struk kasir otomatis dan peringatan stok menipis secara instan ke HP Anda.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="d-inline-flex flex-column align-items-lg-end gap-2">
                        <?php if ($isConnected): ?>
                            <div class="badge px-4 py-2.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-2" style="background: #ffffff; color: #075E54; font-size: 0.95rem; font-weight: 700;">
                                <span class="wa-pulsing-dot"></span>
                                Terkoneksi & Aktif
                            </div>
                            <span class="text-white small fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-whatsapp me-1"></i> <?= htmlspecialchars($displayPhone) ?>
                            </span>
                        <?php else: ?>
                            <div class="badge px-4 py-2.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-2" style="background: rgba(255,255,255,0.2); color: #ffffff; border: 1px solid rgba(255,255,255,0.4); font-size: 0.95rem; font-weight: 600;">
                                <i class="bi bi-exclamation-circle"></i>
                                Belum Terhubung
                            </div>
                            <span class="text-white-50 small" style="font-size: 0.8rem;">
                                Masukkan Nomor & Verifikasi OTP
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Feedback Container -->
    <div id="waAlertContainer"></div>

    <!-- Status Banner: Info Gateway Fonnte -->
    <?php if (!$hasRealGateway): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: #fffbeb; border-left: 5px solid #f59e0b !important;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background: rgba(245,158,11,0.2); color: #d97706;">
                        <i class="bi bi-info-circle-fill fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                            <h6 class="fw-bold text-dark mb-0">Pengaturan WhatsApp Gateway Nyata (Fonnte)</h6>
                            <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill small">Mode Simulasi & Direct WhatsApp</span>
                        </div>
                        <p class="small text-muted mb-2" style="font-size: 0.83rem; line-height: 1.5;">
                            Agar pesan WhatsApp dapat terkirim <strong>secara otomatis ke HP fisik Anda</strong> tanpa harus membuka WhatsApp Web, sistem menggunakan gateway <strong>Fonnte</strong> (gratis). 
                            Jika Anda belum memiliki token Fonnte, sistem tetap menyediakan tombol <strong>"Buka & Kirim di WhatsApp Web Langsung"</strong> sehingga Anda bisa langsung mengirim pesan ke nomor tujuan kapan saja.
                        </p>
                        <div class="d-flex flex-wrap gap-2 pt-1">
                            <a href="https://fonnte.com" target="_blank" class="btn btn-sm btn-outline-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 0.78rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Daftar Akun Fonnte Gratis (1 Menit)
                            </a>
                            <a href="#collapseApiKey" data-bs-toggle="collapse" class="btn btn-sm btn-link text-decoration-none fw-semibold text-secondary" style="font-size: 0.78rem;">
                                <i class="bi bi-key-fill me-1"></i> Masukkan Token Fonnte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 p-3 mb-4" style="background: #f0fdf4; border-left: 5px solid #16a34a !important;">
            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 42px; height: 42px; background: rgba(22,163,74,0.15); color: #16a34a;">
                <i class="bi bi-check-circle-fill fs-5"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold text-dark" style="font-size: 0.9rem;">WhatsApp Gateway Fonnte Terkonfigurasi</div>
                <div class="small text-muted" style="font-size: 0.82rem;">
                    Token API Fonnte telah terpasang (dari pengaturan tersimpan atau environment). Status pengiriman sebenarnya diverifikasi melalui tombol Kirim Notifikasi; server tidak mengklaim terkirim tanpa respons sukses dari gateway.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Kolom Kiri: Form Koneksi / Status Koneksi -->
        <div class="col-lg-5">
            <?php if (!$isConnected): ?>
                <!-- CARD FORM HUBUNGKAN NOMOR & KIRIM OTP -->
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(37,211,102,0.12); color: #128C7E;">
                                <i class="bi bi-whatsapp fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">Hubungkan WhatsApp</h5>
                                <small class="text-muted" style="font-size: 0.78rem;">Langkah 1: Masukkan nomor untuk verifikasi OTP</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info border-0 rounded-3 p-3 mb-3 d-flex gap-2" style="background: #f0fdf4; color: #166534; font-size: 0.82rem; border-left: 4px solid #22c55e !important;">
                            <i class="bi bi-info-circle-fill fs-6 mt-0.5"></i>
                            <div>
                                Masukkan nomor WhatsApp yang akan digunakan. Sistem akan membuat <strong>kode OTP 6 digit</strong> untuk memverifikasi nomor Anda.
                            </div>
                        </div>

                        <form id="formSendOtp">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">
                                    Nomor WhatsApp Anda <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted fw-bold" style="font-size: 0.9rem;">
                                        <i class="bi bi-whatsapp text-success me-1"></i> +62
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="inputPhoneNumber" 
                                           name="phone_number" 
                                           placeholder="081234567890" 
                                           value="<?= htmlspecialchars($cleanInputPhone) ?>"
                                           required
                                           style="font-size: 0.95rem; font-weight: 500;">
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.75rem;">
                                    Bisa memasukkan awalan <code>08...</code> atau <code>628...</code> (contoh: 087852756647).
                                </div>
                            </div>

                            <!-- Opsi API Token Fonnte -->
                            <div class="mb-4">
                                <a class="text-decoration-none small d-inline-flex align-items-center gap-1 text-primary fw-semibold" data-bs-toggle="collapse" href="#collapseApiKey" role="button" aria-expanded="<?= $hasApiKey ? 'true' : 'false' ?>" style="font-size: 0.8rem;">
                                    <i class="bi bi-sliders"></i> Pengaturan Token Gateway Fonnte (Opsional)
                                </a>
                                <div class="collapse <?= $hasApiKey ? 'show' : '' ?> mt-2" id="collapseApiKey">
                                    <div class="card card-body bg-light border-0 rounded-3 p-3">
                                        <label class="form-label fw-semibold text-dark small mb-1" style="font-size: 0.78rem;">
                                            Fonnte Device Token / API Key
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-sm" 
                                               id="inputApiKey" 
                                               name="api_key" 
                                               autocomplete="off"
                                               placeholder="<?= $hasApiKey ? 'Token sudah tersimpan (tidak ditampilkan)' : 'Contoh: aBcDeF123456GhiJ...' ?>" 
                                               value="">
                                        <div class="form-text text-muted" style="font-size: 0.7rem;">
                                            Dapatkan token gratis di <a href="https://fonnte.com" target="_blank" class="text-success fw-bold text-decoration-none">fonnte.com</a>. Biarkan kosong untuk mempertahankan token yang sudah tersimpan. Token tidak pernah ditampilkan kembali di halaman ini.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2.5 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" id="btnSendOtp" style="background: #128C7E; border-color: #128C7E;">
                                <i class="bi bi-send-fill"></i>
                                <span>Kirim Kode OTP Verifikasi</span>
                            </button>
                        </form>
                    </div>
                </div>

            <?php else: ?>

                <!-- CARD STATUS NOMOR SUDAH TERKONEKSI -->
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(37,211,102,0.15); color: #128C7E;">
                                    <i class="bi bi-check-circle-fill fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">Status Koneksi</h5>
                                    <small class="text-success fw-semibold" style="font-size: 0.78rem;">
                                        <i class="bi bi-shield-fill-check me-1"></i>Terverifikasi & Aktif
                                    </small>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill font-monospace" style="font-size: 0.75rem;">
                                CONNECTED
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 rounded-4 mb-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 48px; height: 48px; background: linear-gradient(135deg, #128C7E, #25D366); flex-shrink: 0; box-shadow: 0 4px 12px rgba(37,211,102,0.3);">
                                    <i class="bi bi-whatsapp fs-4"></i>
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="text-muted small" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Nomor WhatsApp Terhubung</div>
                                    <div class="fw-bold text-dark fs-5 text-truncate"><?= htmlspecialchars($displayPhone) ?></div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                        Terhubung sejak: <?= !empty($setting['verified_at']) ? date('d M Y, H:i', strtotime($setting['verified_at'])) : date('d M Y') ?> WIB
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-2" style="font-size: 0.85rem;">Status Layanan Notifikasi</h6>
                            <ul class="list-group list-group-flush border-0">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                    <span class="text-muted small"><i class="bi bi-check2-circle text-success me-1"></i> Verifikasi OTP</span>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2.5">Terverifikasi</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                    <span class="text-muted small"><i class="bi bi-receipt text-primary me-1"></i> Notifikasi Penjualan Kasir</span>
                                    <span class="badge <?= !empty($setting['notify_transaction']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2.5">
                                        <?= !empty($setting['notify_transaction']) ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                    <span class="text-muted small"><i class="bi bi-box-seam text-warning me-1"></i> Peringatan Stok Menipis</span>
                                    <span class="badge <?= !empty($setting['notify_low_stock']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2.5">
                                        <?= !empty($setting['notify_low_stock']) ? 'Aktif (<= ' . ($setting['low_stock_threshold'] ?? 5) . ' unit)' : 'Nonaktif' ?>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-danger w-100 py-2 rounded-3 small fw-semibold" id="btnDisconnect">
                                <i class="bi bi-link-45deg me-1"></i> Putuskan Koneksi
                            </button>
                            <button type="button" class="btn btn-outline-secondary w-100 py-2 rounded-3 small fw-semibold" id="btnChangeNumber">
                                <i class="bi bi-arrow-repeat me-1"></i> Ganti Nomor
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kolom Kanan: Pengaturan Notifikasi & Uji Coba -->
        <div class="col-lg-7">
            <!-- Tabs Navigasi Fitur -->
            <ul class="nav nav-pills mb-3 gap-2" id="waTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-3 py-2 fw-semibold" id="tab-test-btn" data-bs-toggle="pill" data-bs-target="#tab-test" type="button" role="tab" style="font-size: 0.85rem;">
                        <i class="bi bi-send-check me-1"></i> Kirim Pesan Uji Coba
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-settings-btn" data-bs-toggle="pill" data-bs-target="#tab-settings" type="button" role="tab" style="font-size: 0.85rem;">
                        <i class="bi bi-gear-fill me-1"></i> Pengaturan Otomatisasi
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="waTabsContent">
                <!-- TAB 1: KIRIM PESAN UJI COBA -->
                <div class="tab-pane fade show active" id="tab-test" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                            <h5 class="fw-bold mb-1 text-dark" style="font-size: 1.05rem;">Uji Coba Pengiriman Notifikasi</h5>
                            <small class="text-muted" style="font-size: 0.78rem;">Kirim pesan langsung ke nomor WhatsApp untuk memastikan integrasi berjalan sempurna.</small>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!$isConnected): ?>
                                <div class="text-center py-4">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-muted" style="width: 56px; height: 56px; background: #f1f5f9;">
                                        <i class="bi bi-lock-fill fs-3"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark">Nomor WhatsApp Belum Terkoneksi</h6>
                                    <p class="text-muted small mb-0" style="max-width: 400px; margin: 0 auto;">
                                        Silakan masukkan nomor dan verifikasi kode OTP pada panel sebelah kiri terlebih dahulu sebelum dapat mengirim pesan notifikasi.
                                    </p>
                                </div>
                            <?php else: ?>
                                <form id="formSendTest">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Nomor Tujuan Uji Coba</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted"><i class="bi bi-whatsapp text-success"></i></span>
                                            <input type="text" class="form-control" id="inputTestPhone" name="phone_number" value="<?= htmlspecialchars($cleanInputPhone) ?>" placeholder="081234567890" required>
                                        </div>
                                        <div class="form-text text-muted small" style="font-size: 0.75rem;">Default terisi nomor yang telah terhubung.</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Isi Pesan Notifikasi</label>
                                        <textarea class="form-control" id="inputTestMessage" name="message" rows="4" style="font-size: 0.88rem; line-height: 1.5;" placeholder="Tuliskan pesan yang ingin dikirim...">🔔 *PESAN NOTIFIKASI UJI COBA*
*Sistem Acounting Toko*
----------------------------------------
Halo! Ini adalah notifikasi pengujian koneksi WhatsApp dari aplikasi Toko Acounting Anda.
Status Koneksi  : *AKTIF & BERJALAN*
Waktu           : <?= date('d M Y, H:i') ?> WIB
----------------------------------------
Sistem telah siap mengirimkan notifikasi penjualan kasir dan peringatan stok otomatis.</textarea>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <button type="submit" class="btn btn-success px-4 py-2.5 rounded-3 fw-bold d-inline-flex align-items-center gap-2 shadow-sm" id="btnSendTest" style="background: #128C7E; border-color: #128C7E;">
                                            <i class="bi bi-send-check-fill"></i>
                                            <span>Kirim Notifikasi Sekarang</span>
                                        </button>

                                        <!-- Tombol Langsung WhatsApp Web (Terkirim 100% Nyata) -->
                                        <button type="button" class="btn btn-outline-success px-3 py-2.5 rounded-3 fw-bold d-inline-flex align-items-center gap-2" id="btnDirectWa">
                                            <i class="bi bi-whatsapp"></i>
                                            <span>Buka & Kirim di WhatsApp Web Langsung</span>
                                        </button>
                                    </div>
                                    <div id="testResultFeedback" class="mt-3"></div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: PENGATURAN OTOMATISASI -->
                <div class="tab-pane fade" id="tab-settings" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                            <h5 class="fw-bold mb-1 text-dark" style="font-size: 1.05rem;">Pengaturan Otomatisasi Notifikasi</h5>
                            <small class="text-muted" style="font-size: 0.78rem;">Pilih jenis kejadian yang akan memicu pengiriman pesan WhatsApp otomatis.</small>
                        </div>
                        <div class="card-body p-4">
                            <form id="formSaveSettings">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                                <!-- Switch 1: Transaksi Kasir -->
                                <div class="p-3 rounded-3 mb-3 d-flex align-items-center justify-content-between" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 d-flex align-items-center justify-content-center text-primary" style="width: 40px; height: 40px; background: rgba(99,102,241,0.12);">
                                            <i class="bi bi-receipt fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 0.88rem;">Notifikasi Transaksi Kasir Baru</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Kirim rincian nota & total penjualan ke WhatsApp setiap kasir checkout.</div>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="switchNotifyTx" name="notify_transaction" value="1" <?= (!isset($setting['notify_transaction']) || (int)$setting['notify_transaction'] === 1) ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Switch 2: Peringatan Stok Menipis -->
                                <div class="p-3 rounded-3 mb-3 d-flex align-items-center justify-content-between" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 d-flex align-items-center justify-content-center text-warning" style="width: 40px; height: 40px; background: rgba(245,158,11,0.15);">
                                            <i class="bi bi-box-seam-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 0.88rem;">Peringatan Stok Barang Menipis</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Kirim peringatan darurat jika sisa stok barang berada di bawah ambang batas minimum.</div>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="switchNotifyLow" name="notify_low_stock" value="1" <?= (!isset($setting['notify_low_stock']) || (int)$setting['notify_low_stock'] === 1) ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Threshold Stok -->
                                <div class="mb-3 px-3 py-2 bg-light rounded-3">
                                    <label class="form-label fw-semibold text-dark small mb-1" style="font-size: 0.8rem;">
                                        Batas Minimum Sisa Stok (Unit)
                                    </label>
                                    <div class="input-group input-group-sm" style="max-width: 180px;">
                                        <input type="number" class="form-control" name="low_stock_threshold" min="1" max="100" value="<?= (int)($setting['low_stock_threshold'] ?? 5) ?>">
                                        <span class="input-group-text">Unit</span>
                                    </div>
                                    <div class="form-text text-muted" style="font-size: 0.72rem;">Jika stok <= nilai ini, pesan peringatan akan otomatis terkirim.</div>
                                </div>

                                <!-- API Token Setting -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">Fonnte API Token (Untuk Pengiriman Otomatis ke HP)</label>
                                    <input type="text" class="form-control font-monospace" name="api_key" autocomplete="off" placeholder="<?= $hasApiKey ? 'Token sudah tersimpan (biarkan kosong jika tidak diubah)' : 'Masukkan token Fonnte Anda...' ?>" value="">
                                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                                        Dapatkan token gratis di <a href="https://fonnte.com" target="_blank" class="text-success fw-bold text-decoration-none">fonnte.com</a>. Scan QR WhatsApp di Fonnte, lalu salin token ke sini. Token disimpan terenkripsi dan tidak ditampilkan kembali.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-bold small d-inline-flex align-items-center gap-2 shadow-sm" id="btnSaveSettings">
                                    <i class="bi bi-save-fill"></i>
                                    <span>Simpan Pengaturan</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Log Notifikasi WhatsApp -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-dark" style="font-size: 1.05rem;">
                        <i class="bi bi-clock-history me-1 text-success"></i> Riwayat Log Notifikasi WhatsApp
                    </h5>
                    <small class="text-muted" style="font-size: 0.78rem;">Daftar aktivitas pesan dan status pengiriman notifikasi dari sistem.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill" style="font-size: 0.75rem;">
                        Total: <strong><?= (int)($stats['total'] ?? 0) ?></strong> Pesan
                    </span>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" onclick="window.location.reload();">
                        <i class="bi bi-arrow-clockwise me-1"></i> Muat Ulang
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
                        <tr>
                            <th class="ps-4 py-3 text-secondary fw-semibold">Waktu</th>
                            <th class="py-3 text-secondary fw-semibold">Tipe Notifikasi</th>
                            <th class="py-3 text-secondary fw-semibold">Nomor Tujuan</th>
                            <th class="py-3 text-secondary fw-semibold" style="min-width: 280px;">Ringkasan Pesan</th>
                            <th class="py-3 text-secondary fw-semibold text-center">Status</th>
                            <th class="pe-4 py-3 text-secondary fw-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-chat-square-dots fs-3 d-block mb-2 text-secondary"></i>
                                    Belum ada catatan riwayat pengiriman notifikasi WhatsApp.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $typeBadge = [
                                    'otp'          => ['bg' => 'bg-info-subtle', 'text' => 'text-info', 'label' => 'OTP Verifikasi'],
                                    'otp_verified' => ['bg' => 'bg-success-subtle', 'text' => 'text-success', 'label' => 'Verifikasi Sukses'],
                                    'transaction'  => ['bg' => 'bg-primary-subtle', 'text' => 'text-primary', 'label' => 'Transaksi Kasir'],
                                    'low_stock'    => ['bg' => 'bg-warning-subtle', 'text' => 'text-warning', 'label' => 'Stok Menipis'],
                                    'test'         => ['bg' => 'bg-purple-subtle', 'text' => 'text-purple', 'label' => 'Pesan Uji Coba'],
                                    'custom'       => ['bg' => 'bg-secondary-subtle', 'text' => 'text-secondary', 'label' => 'Sistem'],
                                ];
                                $tb = $typeBadge[$log['type']] ?? ['bg' => 'bg-secondary-subtle', 'text' => 'text-secondary', 'label' => ucfirst($log['type'])];

                                $statusBadge = [
                                    'sent'      => ['bg' => 'bg-success', 'text' => 'text-white', 'label' => 'Terkirim Nyata'],
                                    'simulated' => ['bg' => 'bg-warning text-dark', 'text' => 'text-dark', 'label' => 'Simulasi (Dev)'],
                                    'failed'    => ['bg' => 'bg-danger', 'text' => 'text-white', 'label' => 'Gagal'],
                                ];
                                $sb = $statusBadge[$log['status']] ?? ['bg' => 'bg-secondary', 'text' => 'text-white', 'label' => ucfirst($log['status'])];
                                ?>
                                <tr>
                                    <td class="ps-4 py-3 text-muted text-nowrap" style="font-size: 0.8rem;">
                                        <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $tb['bg'] ?> <?= $tb['text'] ?> px-2.5 py-1 rounded-pill" style="font-size: 0.72rem; font-weight: 600;">
                                            <?= $tb['label'] ?>
                                        </span>
                                    </td>
                                    <td class="font-monospace fw-semibold text-dark" style="font-size: 0.82rem;">
                                        <?= htmlspecialchars(WhatsAppService::formatDisplay($log['phone_number'])) ?>
                                    </td>
                                    <td class="text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($log['message']) ?>">
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($log['message'], 0, 85, '...'))) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $sb['bg'] ?> px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">
                                            <?= $sb['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-muted small" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($log['response_info'] ?: '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VERIFIKASI OTP -->
<div class="modal fade" id="modalVerifyOtp" tabindex="-1" aria-labelledby="modalVerifyOtpLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #075E54, #25D366); flex-shrink: 0;">
                        <i class="bi bi-shield-lock-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark" id="modalVerifyOtpLabel" style="font-size: 1.1rem;">Verifikasi Kode OTP</h5>
                        <p class="text-muted small mb-0" style="font-size: 0.78rem;">Masukkan 6 digit kode OTP untuk menghubungkan nomor</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Hint / Alert Container di dalam modal -->
                <div id="modalOtpNotice"></div>

                <div class="text-center my-3">
                    <div class="text-muted small mb-1" style="font-size: 0.8rem;">Nomor WhatsApp Tujuan:</div>
                    <div class="fw-bold text-success fs-5" id="modalTargetPhone">+62 812-xxxx-xxxx</div>
                </div>

                <div class="text-center mb-3" id="modalDirectWaContainer"></div>

                <form id="formVerifyOtp">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="mb-4">
                        <label class="form-label text-center d-block fw-semibold text-dark small mb-2">
                            Masukkan 6 Digit Kode OTP
                        </label>
                        <div class="d-flex justify-content-center gap-2 otp-inputs">
                            <input type="text" maxlength="6" class="form-control text-center fw-bold fs-3 font-monospace rounded-3 py-2" id="inputOtpCode" name="otp" placeholder="••••••" autocomplete="one-time-code" style="max-width: 220px; letter-spacing: 6px;" required>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-success py-2.5 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" id="btnVerifySubmit" style="background: #128C7E; border-color: #128C7E;">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Verifikasi & Hubungkan Nomor</span>
                        </button>
                        <button type="button" class="btn btn-link text-decoration-none text-muted small py-1" id="btnResendOtp">
                            Belum terima kode? <span class="text-success fw-bold">Kirim Ulang OTP</span> <span id="resendCountdown" class="text-secondary"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.whatsapp-module {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}
.wa-pulsing-dot {
    width: 10px;
    height: 10px;
    background: #25D366;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
    animation: waPulse 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
}
@keyframes waPulse {
    to {
        box-shadow: 0 0 0 10px rgba(37, 211, 102, 0);
    }
}
.nav-pills .nav-link.active {
    background: #128C7E !important;
    color: #fff !important;
}
.nav-pills .nav-link {
    background: #f1f5f9;
    color: #64748b;
    transition: all 0.2s ease;
}
.nav-pills .nav-link:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.bg-purple-subtle {
    background: #f3e8ff;
}
.text-purple {
    color: #7e22ce;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASEURL = '<?= BASEURL ?>';
    let currentCsrf = '<?= $csrfToken ?>';
    let lastPhoneSubmitted = '';
    let lastDirectWaUrl = '';
    let resendTimer = null;
    let resendSeconds = 0;

    const modalElement = document.getElementById('modalVerifyOtp');
    const bsModal = new bootstrap.Modal(modalElement);

    function showAlert(message, type = 'success', autoDismiss = true) {
        const container = document.getElementById('waAlertContainer');
        const icon = type === 'success' ? 'check-circle-fill' : (type === 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill');
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-${icon} fs-5"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        container.innerHTML = alertHtml;
        if (autoDismiss) {
            setTimeout(() => {
                const el = container.querySelector('.alert');
                if (el) {
                    const bsAlert = new bootstrap.Alert(el);
                    bsAlert.close();
                }
            }, 8000);
        }
    }

    function startResendCountdown(seconds = 60) {
        resendSeconds = seconds;
        const btn = document.getElementById('btnResendOtp');
        const countdownSpan = document.getElementById('resendCountdown');
        btn.disabled = true;

        clearInterval(resendTimer);
        resendTimer = setInterval(() => {
            resendSeconds--;
            if (resendSeconds <= 0) {
                clearInterval(resendTimer);
                btn.disabled = false;
                countdownSpan.innerText = '';
            } else {
                countdownSpan.innerText = `(${resendSeconds}s)`;
            }
        }, 1000);
    }

    // 1. Submit Form Send OTP
    const formSendOtp = document.getElementById('formSendOtp');
    if (formSendOtp) {
        formSendOtp.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnSendOtp');
            const phoneInput = document.getElementById('inputPhoneNumber');
            const apiKeyInput = document.getElementById('inputApiKey');

            const phone = phoneInput.value.trim();
            const apiKey = apiKeyInput ? apiKeyInput.value.trim() : '';

            if (!phone) {
                showAlert('Silakan masukkan nomor WhatsApp Anda.', 'danger');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Mengirim OTP...`;

            try {
                const response = await fetch(`${BASEURL}/whatsapp/sendOtp`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: currentCsrf,
                        phone_number: phone,
                        api_key: apiKey
                    })
                });

                const data = await response.json();

                if (data.success) {
                    lastPhoneSubmitted = data.phone || phone;
                    lastDirectWaUrl = data.direct_wa_url || '';
                    document.getElementById('modalTargetPhone').innerText = data.phone_display || lastPhoneSubmitted;

                    let noticeHtml = '';
                    if (data.simulated && data.dev_hint) {
                        noticeHtml = `
                            <div class="alert alert-warning border-0 rounded-3 p-3 mb-3" style="background:#fffbeb; border-left:4px solid #f59e0b !important;">
                                <div class="fw-bold text-dark small"><i class="bi bi-info-circle-fill me-1 text-warning"></i> MODE PENGUJIAN / DIRECT WA</div>
                                <div class="small text-dark mt-1">${data.dev_hint}</div>
                            </div>
                        `;
                    }
                    document.getElementById('modalOtpNotice').innerHTML = noticeHtml;

                    // Direct WhatsApp button inside modal
                    if (lastDirectWaUrl) {
                        document.getElementById('modalDirectWaContainer').innerHTML = `
                            <a href="${lastDirectWaUrl}" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1">
                                <i class="bi bi-whatsapp"></i> Buka WhatsApp & Lihat Pesan OTP
                            </a>
                        `;
                    } else {
                        document.getElementById('modalDirectWaContainer').innerHTML = '';
                    }

                    // Auto-fill dev OTP for convenience in testing
                    document.getElementById('inputOtpCode').value = data.dev_otp || '';
                    bsModal.show();
                    startResendCountdown(60);

                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message || 'Gagal mengirim kode OTP.', 'danger');
                }
            } catch (err) {
                console.error(err);
                showAlert('Terjadi kesalahan jaringan saat mengirim kode OTP.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-send-fill me-2"></i><span>Kirim Kode OTP Verifikasi</span>`;
            }
        });
    }

    // 2. Resend OTP Button in Modal
    const btnResendOtp = document.getElementById('btnResendOtp');
    if (btnResendOtp) {
        btnResendOtp.addEventListener('click', async function () {
            if (resendSeconds > 0 || !lastPhoneSubmitted) return;

            btnResendOtp.disabled = true;
            btnResendOtp.innerHTML = `Mengirim ulang...`;

            try {
                const response = await fetch(`${BASEURL}/whatsapp/sendOtp`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: currentCsrf,
                        phone_number: lastPhoneSubmitted
                    })
                });
                const data = await response.json();
                if (data.success) {
                    if (data.simulated && data.dev_hint) {
                        document.getElementById('modalOtpNotice').innerHTML = `
                            <div class="alert alert-warning border-0 rounded-3 p-3 mb-3">
                                <div class="fw-bold small">KODE OTP BARU</div>
                                <div class="small mt-1">${data.dev_hint}</div>
                            </div>
                        `;
                        document.getElementById('inputOtpCode').value = data.dev_otp || '';
                    }
                    startResendCountdown(60);
                } else {
                    alert(data.message || 'Gagal mengirim ulang OTP.');
                }
            } catch (err) {
                alert('Gagal mengirim ulang OTP.');
            } finally {
                btnResendOtp.disabled = false;
                btnResendOtp.innerHTML = `Belum terima kode? <span class="text-success fw-bold">Kirim Ulang OTP</span> <span id="resendCountdown"></span>`;
            }
        });
    }

    // 3. Submit Form Verify OTP
    const formVerifyOtp = document.getElementById('formVerifyOtp');
    if (formVerifyOtp) {
        formVerifyOtp.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnVerifySubmit');
            const otp = document.getElementById('inputOtpCode').value.trim();

            if (!otp || otp.length !== 6) {
                alert('Masukkan 6 digit angka kode OTP dengan benar.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Memverifikasi...`;

            try {
                const response = await fetch(`${BASEURL}/whatsapp/verifyOtp`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: currentCsrf,
                        otp: otp
                    })
                });

                const data = await response.json();

                if (data.success) {
                    bsModal.hide();
                    showAlert('Verifikasi Berhasil! Nomor WhatsApp Anda telah resmi terhubung.', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    alert(data.message || 'Kode OTP salah atau telah kedaluwarsa.');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat memverifikasi kode OTP.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-check2-circle fs-5 me-1"></i><span>Verifikasi & Hubungkan Nomor</span>`;
            }
        });
    }

    // 4. Send Test Message via Server
    const formSendTest = document.getElementById('formSendTest');
    if (formSendTest) {
        formSendTest.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnSendTest');
            const phone = document.getElementById('inputTestPhone').value.trim();
            const message = document.getElementById('inputTestMessage').value.trim();
            const feedbackContainer = document.getElementById('testResultFeedback');

            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Mengirim Pesan...`;
            feedbackContainer.innerHTML = '';

            try {
                const response = await fetch(`${BASEURL}/whatsapp/sendTest`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: currentCsrf,
                        phone_number: phone,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    if (data.simulated && data.direct_wa_url) {
                        feedbackContainer.innerHTML = `
                            <div class="alert alert-info border-0 rounded-3 p-3 mt-2 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold small"><i class="bi bi-info-circle me-1"></i> Kirim Langsung ke WhatsApp Asli</div>
                                    <div class="small text-muted">Karena Token Fonnte belum dimasukkan, klik tombol berikut untuk membuka WhatsApp Web dan mengirimkan pesan nyata sekarang:</div>
                                </div>
                                <a href="${data.direct_wa_url}" target="_blank" class="btn btn-sm btn-success fw-bold text-nowrap rounded-pill px-3 py-1.5">
                                    <i class="bi bi-whatsapp me-1"></i> Buka WhatsApp & Kirim
                                </a>
                            </div>
                        `;
                    }
                } else {
                    showAlert(data.message || 'Gagal mengirim pesan uji coba.', 'danger');
                    if (data.direct_wa_url) {
                        feedbackContainer.innerHTML = `
                            <div class="alert alert-warning border-0 rounded-3 p-3 mt-2 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold small text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Gateway Error</div>
                                    <div class="small text-muted">${data.message}. Anda tetap bisa mengirim pesan langsung via WhatsApp Web:</div>
                                </div>
                                <a href="${data.direct_wa_url}" target="_blank" class="btn btn-sm btn-success fw-bold text-nowrap rounded-pill px-3 py-1.5">
                                    <i class="bi bi-whatsapp me-1"></i> Buka WhatsApp Web
                                </a>
                            </div>
                        `;
                    }
                }
            } catch (err) {
                console.error(err);
                showAlert('Gagal mengirim pesan uji coba.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-send-check-fill me-2"></i><span>Kirim Notifikasi Sekarang</span>`;
            }
        });
    }

    // 4b. Direct WhatsApp Button
    const btnDirectWa = document.getElementById('btnDirectWa');
    if (btnDirectWa) {
        btnDirectWa.addEventListener('click', function () {
            const phone = document.getElementById('inputTestPhone').value.trim();
            const message = document.getElementById('inputTestMessage').value.trim();
            if (!phone) {
                alert('Masukkan nomor WhatsApp tujuan.');
                return;
            }
            let cleanPhone = phone.replace(/[^0-9]/g, '');
            if (cleanPhone.startsWith('0')) {
                cleanPhone = '62' + cleanPhone.substring(1);
            } else if (cleanPhone.startsWith('8')) {
                cleanPhone = '62' + cleanPhone;
            }
            const encoded = encodeURIComponent(message);
            const url = `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encoded}`;
            window.open(url, '_blank');
        });
    }

    // 5. Save Settings
    const formSaveSettings = document.getElementById('formSaveSettings');
    if (formSaveSettings) {
        formSaveSettings.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveSettings');
            const formData = new FormData(formSaveSettings);
            const dataObj = {};
            formData.forEach((val, key) => dataObj[key] = val);

            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...`;

            try {
                const response = await fetch(`${BASEURL}/whatsapp/saveSettings`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataObj)
                });
                const res = await response.json();
                if (res.success) {
                    showAlert(res.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(res.message || 'Gagal menyimpan pengaturan.', 'danger');
                }
            } catch (err) {
                showAlert('Gagal menyimpan pengaturan.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-save-fill me-2"></i><span>Simpan Pengaturan</span>`;
            }
        });
    }

    // 6. Disconnect WhatsApp
    const btnDisconnect = document.getElementById('btnDisconnect');
    if (btnDisconnect) {
        btnDisconnect.addEventListener('click', async function () {
            if (!confirm('Apakah Anda yakin ingin memutuskan koneksi nomor WhatsApp ini dari sistem?')) {
                return;
            }

            try {
                const response = await fetch(`${BASEURL}/whatsapp/disconnect`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: currentCsrf })
                });
                const data = await response.json();
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    alert(data.message || 'Gagal memutuskan koneksi.');
                }
            } catch (err) {
                alert('Gagal memutuskan koneksi.');
            }
        });
    }

    // 7. Change Number
    const btnChangeNumber = document.getElementById('btnChangeNumber');
    if (btnChangeNumber) {
        btnChangeNumber.addEventListener('click', function () {
            if (confirm('Untuk mengganti nomor, sistem akan memutuskan koneksi nomor saat ini dan meminta verifikasi OTP untuk nomor baru. Lanjutkan?')) {
                btnDisconnect.click();
            }
        });
    }
});
</script>
