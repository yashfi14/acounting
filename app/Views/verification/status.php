<?php 
$verified_count = 0;
if ($_SESSION['email_verified']) $verified_count++;
if ($_SESSION['whatsapp_verified']) $verified_count++;
$progress_percent = ($verified_count / 2) * 100;
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['verif_success'])) : ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= $_SESSION['verif_success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['verif_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['verif_error'])) : ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= $_SESSION['verif_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['verif_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['verif_sim_msg'])) : ?>
                <div class="alert alert-warning mb-4 shadow-sm border-0 rounded-3">
                    <i class="bi bi-terminal-fill me-2"></i> <?= $_SESSION['verif_sim_msg']; ?>
                </div>
                <?php unset($_SESSION['verif_sim_msg']); ?>
            <?php endif; ?>

            <!-- Main Card -->
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <!-- Header with Gradient -->
                <div class="card-header bg-gradient text-white pt-4 pb-4 px-4" style="background: linear-gradient(135deg, #0d6efd, #6610f2);">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold">Verifikasi Akun</h4>
                            <small class="text-white-50">Selesaikan untuk mengakses semua fitur</small>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-white-50">Progress Verifikasi</small>
                            <small class="fw-bold"><?= $verified_count ?>/2 Selesai</small>
                        </div>
                        <div class="progress" style="height: 8px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                            <div class="progress-bar bg-white" role="progressbar" style="width: <?= $progress_percent ?>%; border-radius: 10px;" 
                                 aria-valuenow="<?= $progress_percent ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <p class="text-muted mb-4">Verifikasi Email dan WhatsApp diperlukan untuk memastikan keamanan akun Anda.</p>
                    
                    <!-- Email Verification Card -->
                    <div class="border rounded-3 p-3 mb-3 <?= $_SESSION['email_verified'] ? 'border-success bg-success bg-opacity-10' : 'border-secondary' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle p-2 me-3 d-flex align-items-center justify-content-center <?= $_SESSION['email_verified'] ? 'bg-success' : 'bg-primary' ?> bg-opacity-10" style="width: 48px; height: 48px;">
                                    <i class="bi bi-envelope-fill fs-5 <?= $_SESSION['email_verified'] ? 'text-success' : 'text-primary' ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Verifikasi Email</h6>
                                    <small class="text-muted"><?= htmlspecialchars($data['user']['email']) ?></small>
                                </div>
                            </div>
                            <div>
                                <?php if ($_SESSION['email_verified']) : ?>
                                    <span class="badge bg-success py-2 px-3 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Terverifikasi
                                    </span>
                                <?php else : ?>
                                    <?php if (isset($data['email_cooldown']) && $data['email_cooldown'] > 0) : ?>
                                        <button class="btn btn-sm btn-outline-secondary px-3 rounded-pill" disabled id="emailCooldownBtn">
                                            <i class="bi bi-clock me-1"></i> <span id="emailTimer"><?= $data['email_cooldown'] ?></span>s
                                        </button>
                                    <?php else : ?>
                                        <a href="<?= BASEURL; ?>/verification/requestEmail" class="btn btn-sm btn-primary px-3 rounded-pill">
                                            <i class="bi bi-send me-1"></i> Kirim Link
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Verification Card -->
                    <div class="border rounded-3 p-3 mb-3 <?= $_SESSION['whatsapp_verified'] ? 'border-success bg-success bg-opacity-10' : 'border-secondary' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle p-2 me-3 d-flex align-items-center justify-content-center <?= $_SESSION['whatsapp_verified'] ? 'bg-success' : 'bg-success' ?> bg-opacity-10" style="width: 48px; height: 48px;">
                                    <i class="bi bi-whatsapp fs-5 <?= $_SESSION['whatsapp_verified'] ? 'text-success' : 'text-success' ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Verifikasi WhatsApp</h6>
                                    <small class="text-muted"><?= htmlspecialchars($data['user']['whatsapp']) ?></small>
                                </div>
                            </div>
                            <div>
                                <?php if ($_SESSION['whatsapp_verified']) : ?>
                                    <span class="badge bg-success py-2 px-3 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Terverifikasi
                                    </span>
                                <?php else : ?>
                                    <?php if (isset($data['wa_cooldown']) && $data['wa_cooldown'] > 0) : ?>
                                        <button class="btn btn-sm btn-outline-secondary px-3 rounded-pill" disabled id="waCooldownBtn">
                                            <i class="bi bi-clock me-1"></i> <span id="waTimer"><?= $data['wa_cooldown'] ?></span>s
                                        </button>
                                    <?php else : ?>
                                        <a href="<?= BASEURL; ?>/verification/requestWhatsApp" class="btn btn-sm btn-success px-3 rounded-pill">
                                            <i class="bi bi-send me-1"></i> Kirim OTP
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($verified_count === 2) : ?>
                        <!-- Semua terverifikasi -->
                        <div class="text-center mt-4 pt-3 border-top">
                            <div class="mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="fw-bold text-success mb-2">Akun Terverifikasi Sepenuhnya!</h5>
                            <p class="text-muted mb-3">Semua verifikasi telah selesai. Anda sekarang dapat mengakses semua fitur.</p>
                            <a href="<?= BASEURL; ?>/dashboard" class="btn btn-primary px-4 py-2 rounded-pill">
                                <i class="bi bi-speedometer2 me-2"></i> Masuk ke Dashboard
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-light border-0 bg-light rounded-3 mt-3 mb-0">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Anda perlu menyelesaikan kedua verifikasi di atas untuk dapat mengakses fitur utama aplikasi.
                            </small>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cooldown Timer JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Email cooldown timer
    const emailTimer = document.getElementById('emailTimer');
    if (emailTimer) {
        let emailSeconds = parseInt(emailTimer.textContent);
        const emailInterval = setInterval(function() {
            emailSeconds--;
            emailTimer.textContent = emailSeconds;
            if (emailSeconds <= 0) {
                clearInterval(emailInterval);
                location.reload(); // Reload untuk menampilkan tombol kirim
            }
        }, 1000);
    }

    // WhatsApp cooldown timer
    const waTimer = document.getElementById('waTimer');
    if (waTimer) {
        let waSeconds = parseInt(waTimer.textContent);
        const waInterval = setInterval(function() {
            waSeconds--;
            waTimer.textContent = waSeconds;
            if (waSeconds <= 0) {
                clearInterval(waInterval);
                location.reload();
            }
        }, 1000);
    }
});
</script>
