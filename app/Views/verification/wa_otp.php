<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['verif_sim_msg'])) : ?>
                <div class="alert alert-warning mb-4 shadow-sm border-0 rounded-3 text-center">
                    <i class="bi bi-terminal-fill me-1"></i> <?= $_SESSION['verif_sim_msg']; ?>
                </div>
                <?php unset($_SESSION['verif_sim_msg']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['verif_error'])) : ?>
                <div class="alert alert-danger mb-4 shadow-sm border-0 rounded-3 text-center">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= $_SESSION['verif_error']; ?>
                </div>
                <?php unset($_SESSION['verif_error']); ?>
            <?php endif; ?>

            <!-- OTP Card -->
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <!-- Header -->
                <div class="text-center pt-5 pb-3 px-4" style="background: linear-gradient(135deg, #198754, #0f5132);">
                    <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-whatsapp text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4 class="text-white mb-1 fw-bold">Verifikasi OTP</h4>
                    <p class="text-white-50 small mb-0">Masukkan 6 digit kode yang dikirim ke WhatsApp Anda</p>
                </div>

                <div class="card-body p-4 pt-4">
                    <?php if (isset($data['has_active_otp']) && $data['has_active_otp']) : ?>
                        <!-- OTP Input Form -->
                        <form action="<?= BASEURL; ?>/verification/verifyWaEndpoint" method="POST" id="otpForm">
                            <div class="mb-4">
                                <input type="text" 
                                       class="form-control form-control-lg text-center fw-bold border-2" 
                                       id="otp" name="otp" 
                                       placeholder="• • • • • •" 
                                       maxlength="6" 
                                       pattern="\d{6}" 
                                       inputmode="numeric"
                                       autocomplete="one-time-code"
                                       required autofocus 
                                       style="letter-spacing: 0.6rem; font-size: 1.8rem; border-radius: 12px;">
                            </div>

                            <!-- Info: Sisa Percobaan -->
                            <?php if (isset($data['attempts_remaining'])) : ?>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i> Berlaku 5 menit
                                    </small>
                                    <small class="<?= $data['attempts_remaining'] <= 2 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                        <i class="bi bi-key me-1"></i> Sisa percobaan: <?= $data['attempts_remaining'] ?>x
                                    </small>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg rounded-3 fw-semibold py-3">
                                    <i class="bi bi-check-circle me-2"></i> Verifikasi
                                </button>
                            </div>
                        </form>

                        <!-- Kirim Ulang OTP -->
                        <div class="text-center pt-2 border-top mt-3">
                            <p class="text-muted small mb-2 mt-3">Belum menerima kode?</p>
                            <?php if (isset($data['wa_cooldown']) && $data['wa_cooldown'] > 0) : ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-4" disabled id="resendCooldownBtn">
                                    <i class="bi bi-arrow-repeat me-1"></i> Kirim ulang (<span id="resendTimer"><?= $data['wa_cooldown'] ?></span>s)
                                </button>
                            <?php else : ?>
                                <a href="<?= BASEURL; ?>/verification/requestWhatsApp" class="btn btn-sm btn-outline-success rounded-pill px-4">
                                    <i class="bi bi-arrow-repeat me-1"></i> Kirim Ulang OTP
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php else : ?>
                        <!-- Tidak ada OTP aktif -->
                        <div class="text-center py-3">
                            <i class="bi bi-exclamation-circle text-warning" style="font-size: 2.5rem;"></i>
                            <h6 class="fw-bold mt-3">Belum Ada Kode OTP Aktif</h6>
                            <p class="text-muted small">Anda belum meminta kode OTP, atau kode sebelumnya sudah kedaluwarsa.</p>
                            <a href="<?= BASEURL; ?>/verification/requestWhatsApp" class="btn btn-success rounded-pill px-4 mt-2">
                                <i class="bi bi-send me-1"></i> Minta Kode OTP Baru
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Back to Status -->
                    <div class="text-center mt-3">
                        <a href="<?= BASEURL; ?>/verification/status" class="text-decoration-none text-muted small">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Status Verifikasi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cooldown Timer & OTP Auto-format Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Resend cooldown timer
    const resendTimer = document.getElementById('resendTimer');
    if (resendTimer) {
        let seconds = parseInt(resendTimer.textContent);
        const interval = setInterval(function() {
            seconds--;
            resendTimer.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(interval);
                location.reload();
            }
        }, 1000);
    }

    // OTP input: hanya angka, auto-submit saat 6 digit
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.addEventListener('input', function(e) {
            // Hanya izinkan angka
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-submit jika 6 digit
            if (this.value.length === 6) {
                document.getElementById('otpForm').submit();
            }
        });
    }
});
</script>
