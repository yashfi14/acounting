<?php
// Data dari controller: $data['errors'], $data['old_input']
$errors    = $data['errors']    ?? [];
$old       = $data['old_input'] ?? [];

function esc($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<div class="container mt-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white text-center py-3">
                    <i class="bi bi-wallet2 fs-3 me-2"></i>
                    <h4 class="mb-0 d-inline">Daftar Akun Acounting</h4>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong><i class="bi bi-exclamation-triangle me-1"></i>Terdapat kesalahan:</strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASEURL; ?>/register/process" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap"
                                   name="nama_lengkap" required maxlength="100"
                                   value="<?= esc($old['nama_lengkap'] ?? ''); ?>"
                                   autocomplete="name">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email"
                                   name="email" required
                                   value="<?= esc($old['email'] ?? ''); ?>"
                                   autocomplete="email">
                        </div>

                        <div class="mb-3">
                            <label for="whatsapp" class="form-label">Nomor WhatsApp <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="whatsapp"
                                   name="whatsapp" required maxlength="20"
                                   placeholder="Contoh: 08123456789"
                                   value="<?= esc($old['whatsapp'] ?? ''); ?>"
                                   autocomplete="tel">
                        </div>


                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password"
                                   name="password" minlength="8" required
                                   autocomplete="new-password">
                            <div class="form-text">Minimal 8 karakter.</div>
                        </div>

                        <div class="mb-4">
                            <label for="konfirmasi_password" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="konfirmasi_password"
                                   name="konfirmasi_password" minlength="8" required
                                   autocomplete="new-password">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-person-plus me-2"></i>Daftar Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <p>Sudah punya akun? <a href="<?= BASEURL; ?>/auth" class="text-decoration-none fw-semibold">Login di sini</a></p>
            </div>
        </div>
    </div>
</div>
