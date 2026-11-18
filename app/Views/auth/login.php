<?php
// Flash message sudah diambil di controller dan dilewatkan ke view via $data
$error     = $data['error']     ?? null;
$old_email = $data['old_email'] ?? '';
?>
<div class="container py-5">
    <div class="card auth-card mx-auto">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div class="fs-1 text-primary mb-2"><i class="bi bi-wallet2"></i></div>
                <h4 class="fw-bold mb-1">Selamat Datang Kembali</h4>
                <p class="text-secondary">Silakan masuk ke akun Anda</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger rounded-3 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= BASEURL; ?>/auth/login" method="POST" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">

                <div class="mb-3">
                    <label for="email" class="form-label fw-medium">Email Address</label>
                    <input type="email" class="form-control py-2" id="email" name="email"
                           value="<?= htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8'); ?>"
                           required autocomplete="email" placeholder="nama@contoh.com">
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label fw-medium mb-0">Password</label>
                    </div>
                    <input type="password" class="form-control py-2 mt-2" id="password" name="password"
                           required autocomplete="current-password" placeholder="Masukkan password Anda">
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" id="btnLogin" class="btn btn-primary py-2 fw-semibold rounded-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Sekarang
                    </button>
                </div>

                <div class="text-center">
                    <span class="text-secondary">Belum punya akun? </span>
                    <a href="<?= BASEURL; ?>/register" class="text-decoration-none fw-semibold">Daftar sekarang</a>
                </div>
            </form>
        </div>
    </div>
</div>
