<div class="container-fluid py-4 px-4" style="max-width: 1000px;">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fw-bold mb-1" style="font-size:1.5rem; color:#0f172a;">Profil Saya</h1>
            <p class="text-muted small mb-0">Kelola informasi akun pribadi Anda.</p>
        </div>
        <span class="badge rounded-pill px-3 py-2" style="background:rgba(99,102,241,.1); color:#6366f1; font-size:.75rem;">
            <i class="bi bi-person-check me-1"></i>Akun Aktif
        </span>
    </div>

    <?php Flasher::flash(); ?>
    <?php $u = $data['user']; ?>
    <?php $role = $u['role'] ?? 'pelanggan'; ?>

    <div class="row g-4">
        <!-- ======= LEFT PANEL: Kartu Profil ======= -->
        <div class="col-lg-4">

            <!-- Avatar + Info Card -->
            <div class="card border-0 text-center p-4" style="border-radius:20px; box-shadow:0 4px 24px rgba(0,0,0,.07);">
                <!-- Avatar Circle -->
                <div class="mx-auto mb-3 position-relative" style="width:90px; height:90px;">
                    <div id="avatarCircle" style="width:90px; height:90px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:2.2rem; box-shadow:0 4px 20px rgba(99,102,241,.4); cursor:pointer; transition:opacity .2s;" title="Klik untuk ubah foto">
                        <?= strtoupper(substr($u['nama_lengkap'], 0, 1)); ?>
                    </div>
                </div>

                <h5 class="fw-bold mb-1" style="color:#0f172a;"><?= htmlspecialchars($u['nama_lengkap']); ?></h5>
                <p class="text-muted small mb-3"><?= htmlspecialchars($u['email']); ?></p>

                <!-- Role Badge -->
                <?php if ($role === 'admin'): ?>
                    <span class="badge px-3 py-2 rounded-pill mb-3" style="background:#ede9fe; color:#5b21b6; font-size:.78rem; border:1px solid #ddd6fe;">
                        <i class="bi bi-shield-lock-fill me-1"></i>Administrator
                    </span>
                <?php elseif ($role === 'kasir'): ?>
                    <span class="badge px-3 py-2 rounded-pill mb-3" style="background:#d1fae5; color:#065f46; font-size:.78rem; border:1px solid #a7f3d0;">
                        <i class="bi bi-cash-stack me-1"></i>Kasir
                    </span>
                <?php else: ?>
                    <span class="badge px-3 py-2 rounded-pill mb-3" style="background:#cffafe; color:#164e63; font-size:.78rem; border:1px solid #a5f3fc;">
                        <i class="bi bi-person-fill me-1"></i>Pelanggan
                    </span>
                <?php endif; ?>

                <!-- Info List -->
                <ul class="list-group list-group-flush text-start small mt-1">
                    <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0 border-bottom">
                        <span class="text-muted"><i class="bi bi-hash me-1"></i>User ID</span>
                        <span class="fw-semibold text-dark">#<?= (int)$u['id']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0 border-bottom">
                        <span class="text-muted"><i class="bi bi-whatsapp me-1"></i>WhatsApp</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($u['whatsapp'] ?? '-'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0 border-bottom">
                        <span class="text-muted"><i class="bi bi-circle-fill me-1" style="font-size:.5rem; color:#10b981;"></i>Status</span>
                        <span class="badge" style="background:#d1fae5; color:#065f46; font-size:.72rem;">Aktif</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                        <span class="text-muted"><i class="bi bi-shield-check me-1"></i>Verifikasi</span>
                        <?php if (!empty($u['email_verified_at']) && !empty($u['whatsapp_verified_at'])): ?>
                            <span class="badge" style="background:#dbeafe; color:#1e40af; font-size:.72rem;">Terverifikasi</span>
                        <?php else: ?>
                            <span class="badge" style="background:#fee2e2; color:#991b1b; font-size:.72rem;">Belum Lengkap</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                        <span class="text-muted"><i class="bi bi-calendar me-1"></i>Bergabung</span>
                        <span class="fw-semibold text-dark"><?= date('d M Y', strtotime($u['created_at'])); ?></span>
                    </li>
                </ul>
            </div>

            <!-- Pintasan / Aksi Cepat -->
            <div class="card border-0 mt-3 p-3" style="border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.06);">
                <p class="fw-semibold small text-muted mb-2" style="letter-spacing:.5px; text-transform:uppercase; font-size:.68rem;">AKSI CEPAT</p>
                <a href="<?= BASE_URL; ?>/auth/logout" class="btn btn-sm w-100 text-start py-2 px-3 rounded-2 mb-1"
                   style="color:#ef4444; background:rgba(239,68,68,.06); border:1px solid rgba(239,68,68,.15);"
                   onclick="return confirm('Yakin ingin keluar?')">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </a>
            </div>

        </div>

        <!-- ======= RIGHT PANEL: Form Edit ======= -->
        <div class="col-lg-8">

            <!-- Tab Nav -->
            <ul class="nav nav-pills mb-3 gap-1" id="profileTab" role="tablist" style="background:#f1f5f9; border-radius:12px; padding:4px;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-2" id="info-tab" data-bs-toggle="pill" data-bs-target="#info-panel"
                        type="button" style="border-radius:9px; font-size:.85rem; font-weight:600;">
                        <i class="bi bi-person me-1"></i>Info Akun
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-2" id="pass-tab" data-bs-toggle="pill" data-bs-target="#pass-panel"
                        type="button" style="border-radius:9px; font-size:.85rem; font-weight:600;">
                        <i class="bi bi-lock me-1"></i>Ganti Password
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ===== TAB 1: Info Akun ===== -->
                <div class="tab-pane fade show active" id="info-panel" role="tabpanel">
                    <div class="card border-0" style="border-radius:20px; box-shadow:0 4px 24px rgba(0,0,0,.07);">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-4" style="color:#0f172a;">
                                <i class="bi bi-person-gear me-2" style="color:#6366f1;"></i>Edit Informasi Akun
                            </h6>

                            <form action="<?= BASE_URL; ?>/profile/update" method="POST" id="formProfil" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= CSRF::generate(); ?>">
                                <input type="hidden" name="action" value="update_info">

                                <!-- Nama Lengkap -->
                                <div class="mb-4">
                                    <label for="nama_lengkap" class="form-label fw-semibold small" style="color:#374151;">
                                        Nama Lengkap <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background:#f8fafc; border-color:#e2e8f0;">
                                            <i class="bi bi-person text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control border-start-0 ps-0" id="nama_lengkap" name="nama_lengkap"
                                               required minlength="3" maxlength="100"
                                               value="<?= htmlspecialchars($u['nama_lengkap']); ?>"
                                               style="border-color:#e2e8f0;"
                                               oninput="previewName(this.value)">
                                    </div>
                                    <div class="invalid-feedback">Nama minimal 3 karakter.</div>
                                </div>

                                <!-- Email (readonly) -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold small" style="color:#374151;">
                                        Alamat Email
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background:#f1f5f9; border-color:#e2e8f0;">
                                            <i class="bi bi-envelope text-muted"></i>
                                        </span>
                                        <input type="email" class="form-control border-start-0 ps-0 bg-light"
                                               value="<?= htmlspecialchars($u['email']); ?>" readonly
                                               style="border-color:#e2e8f0; color:#64748b;">
                                    </div>
                                    <small class="text-muted" style="font-size:.75rem;">
                                        <i class="bi bi-lock-fill me-1"></i>Email tidak dapat diubah secara mandiri.
                                    </small>
                                </div>

                                <!-- WhatsApp -->
                                <div class="mb-4">
                                    <label for="whatsapp" class="form-label fw-semibold small" style="color:#374151;">
                                        Nomor WhatsApp <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background:#f8fafc; border-color:#e2e8f0;">
                                            <i class="bi bi-whatsapp text-muted"></i>
                                        </span>
                                        <input type="tel" class="form-control border-start-0 ps-0" id="whatsapp" name="whatsapp"
                                               required pattern="[0-9]{9,15}"
                                               value="<?= htmlspecialchars($u['whatsapp'] ?? ''); ?>"
                                               placeholder="Contoh: 081234567890"
                                               style="border-color:#e2e8f0;">
                                    </div>
                                    <div class="invalid-feedback">Masukkan nomor WhatsApp yang valid (9–15 digit).</div>
                                </div>

                                <!-- Role (locked) -->
                                <div class="mb-4 p-3 rounded-3" style="background:#f8fafc; border:1px solid #e2e8f0;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <p class="fw-semibold small mb-0" style="color:#374151;">Peran / Role</p>
                                            <p class="text-muted mb-0" style="font-size:.75rem;">Dikelola oleh Administrator</p>
                                        </div>
                                        <?php if ($role === 'admin'): ?>
                                            <span class="badge px-3 py-2" style="background:#ede9fe; color:#5b21b6; font-size:.8rem;">
                                                <i class="bi bi-shield-lock-fill me-1"></i>Administrator
                                            </span>
                                        <?php elseif ($role === 'kasir'): ?>
                                            <span class="badge px-3 py-2" style="background:#d1fae5; color:#065f46; font-size:.8rem;">
                                                <i class="bi bi-cash-stack me-1"></i>Kasir
                                            </span>
                                        <?php else: ?>
                                            <span class="badge px-3 py-2" style="background:#cffafe; color:#164e63; font-size:.8rem;">
                                                <i class="bi bi-person-fill me-1"></i>Pelanggan
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="d-flex justify-content-end gap-2 pt-2">
                                    <button type="button" class="btn btn-sm px-4 py-2"
                                            style="background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:10px;"
                                            onclick="resetForm()">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                    </button>
                                    <button type="submit" class="btn btn-sm px-4 py-2 fw-semibold"
                                            style="background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; border-radius:10px; box-shadow:0 4px 12px rgba(99,102,241,.35);">
                                        <i class="bi bi-save me-1"></i>Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ===== TAB 2: Ganti Password ===== -->
                <div class="tab-pane fade" id="pass-panel" role="tabpanel">
                    <div class="card border-0" style="border-radius:20px; box-shadow:0 4px 24px rgba(0,0,0,.07);">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-4" style="color:#0f172a;">
                                <i class="bi bi-lock-fill me-2" style="color:#6366f1;"></i>Ganti Password
                            </h6>

                            <form action="<?= BASE_URL; ?>/profile/update" method="POST" id="formPassword" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= CSRF::generate(); ?>">
                                <input type="hidden" name="action" value="update_password">

                                <!-- Password Lama -->
                                <div class="mb-4">
                                    <label for="password_lama" class="form-label fw-semibold small" style="color:#374151;">
                                        Password Saat Ini <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background:#f8fafc; border-color:#e2e8f0;">
                                            <i class="bi bi-lock text-muted"></i>
                                        </span>
                                        <input type="password" class="form-control border-start-0 border-end-0 ps-0"
                                               id="password_lama" name="password_lama" required
                                               placeholder="Masukkan password saat ini"
                                               style="border-color:#e2e8f0;">
                                        <button class="btn border border-start-0" type="button"
                                                style="border-color:#e2e8f0; background:#f8fafc;"
                                                onclick="togglePass('password_lama', this)">
                                            <i class="bi bi-eye text-muted"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Password Baru -->
                                <div class="mb-4">
                                    <label for="password_baru" class="form-label fw-semibold small" style="color:#374151;">
                                        Password Baru <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background:#f8fafc; border-color:#e2e8f0;">
                                            <i class="bi bi-key text-muted"></i>
                                        </span>
                                        <input type="password" class="form-control border-start-0 border-end-0 ps-0"
                                               id="password_baru" name="password" required minlength="6"
                                               placeholder="Minimal 6 karakter"
                                               style="border-color:#e2e8f0;"
                                               oninput="checkStrength(this.value)">
                                        <button class="btn border border-start-0" type="button"
                                                style="border-color:#e2e8f0; background:#f8fafc;"
                                                onclick="togglePass('password_baru', this)">
                                            <i class="bi bi-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <!-- Strength Indicator -->
                                    <div class="mt-2">
                                        <div class="d-flex gap-1 mb-1">
                                            <div class="strength-bar flex-fill rounded" id="s1" style="height:4px; background:#e2e8f0; transition:background .3s;"></div>
                                            <div class="strength-bar flex-fill rounded" id="s2" style="height:4px; background:#e2e8f0; transition:background .3s;"></div>
                                            <div class="strength-bar flex-fill rounded" id="s3" style="height:4px; background:#e2e8f0; transition:background .3s;"></div>
                                            <div class="strength-bar flex-fill rounded" id="s4" style="height:4px; background:#e2e8f0; transition:background .3s;"></div>
                                        </div>
                                        <small id="strengthLabel" class="text-muted" style="font-size:.72rem;"></small>
                                    </div>
                                </div>

                                <!-- Konfirmasi Password -->
                                <div class="mb-4">
                                    <label for="password_konfirm" class="form-label fw-semibold small" style="color:#374151;">
                                        Konfirmasi Password Baru <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background:#f8fafc; border-color:#e2e8f0;">
                                            <i class="bi bi-check-circle text-muted"></i>
                                        </span>
                                        <input type="password" class="form-control border-start-0 border-end-0 ps-0"
                                               id="password_konfirm" name="password_confirm" required
                                               placeholder="Ulangi password baru"
                                               style="border-color:#e2e8f0;"
                                               oninput="checkMatch()">
                                        <button class="btn border border-start-0" type="button"
                                                style="border-color:#e2e8f0; background:#f8fafc;"
                                                onclick="togglePass('password_konfirm', this)">
                                            <i class="bi bi-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <small id="matchLabel" class="mt-1 d-block" style="font-size:.72rem;"></small>
                                </div>

                                <!-- Info Box -->
                                <div class="alert border-0 p-3 mb-4 rounded-3" style="background:#eff6ff; font-size:.8rem; color:#1e40af;">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Password baru harus minimal 6 karakter. Gunakan kombinasi huruf, angka, dan simbol untuk keamanan lebih baik.
                                </div>

                                <!-- Submit -->
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm px-4 py-2"
                                            style="background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:10px;"
                                            onclick="document.getElementById('formPassword').reset(); resetStrength();">
                                        <i class="bi bi-x me-1"></i>Batal
                                    </button>
                                    <button type="submit" id="btnSavePass" class="btn btn-sm px-4 py-2 fw-semibold" disabled
                                            style="background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; border-radius:10px; box-shadow:0 4px 12px rgba(99,102,241,.35); opacity:.6; transition:opacity .2s;">
                                        <i class="bi bi-lock-fill me-1"></i>Perbarui Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-content -->
        </div><!-- /col-lg-8 -->
    </div><!-- /row -->
</div>

<script>
// === Preview nama di avatar & heading ===
function previewName(val) {
    const initial = val.trim().charAt(0).toUpperCase() || '?';
    document.getElementById('avatarCircle').textContent = initial;
}

// === Reset form ke nilai awal ===
function resetForm() {
    document.getElementById('formProfil').reset();
    previewName('<?= addslashes($u['nama_lengkap']); ?>');
}

// === Toggle show/hide password ===
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash text-muted';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye text-muted';
    }
}

// === Password Strength ===
function checkStrength(val) {
    const bars = ['s1','s2','s3','s4'];
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val) && /[^a-zA-Z0-9]/.test(val)) score++;

    const colors = ['#ef4444','#f59e0b','#10b981','#6366f1'];
    const labels = ['Sangat Lemah','Lemah','Sedang','Kuat'];
    bars.forEach((b, i) => {
        document.getElementById(b).style.background = i < score ? colors[score - 1] : '#e2e8f0';
    });
    label.textContent = val.length ? labels[score - 1] || '' : '';
    label.style.color = score ? colors[score - 1] : '#94a3b8';
    checkMatch();
    updateSaveBtn();
}

function resetStrength() {
    ['s1','s2','s3','s4'].forEach(b => document.getElementById(b).style.background = '#e2e8f0');
    document.getElementById('strengthLabel').textContent = '';
    document.getElementById('matchLabel').textContent = '';
    document.getElementById('btnSavePass').disabled = true;
    document.getElementById('btnSavePass').style.opacity = '.6';
}

// === Cek match konfirmasi password ===
function checkMatch() {
    const pw = document.getElementById('password_baru').value;
    const cf = document.getElementById('password_konfirm').value;
    const lbl = document.getElementById('matchLabel');
    if (!cf) { lbl.textContent = ''; return; }
    if (pw === cf) {
        lbl.textContent = '✓ Password cocok';
        lbl.style.color = '#10b981';
    } else {
        lbl.textContent = '✗ Password tidak cocok';
        lbl.style.color = '#ef4444';
    }
    updateSaveBtn();
}

function updateSaveBtn() {
    const pw  = document.getElementById('password_baru').value;
    const cf  = document.getElementById('password_konfirm').value;
    const old = document.getElementById('password_lama').value;
    const btn = document.getElementById('btnSavePass');
    const ok  = pw.length >= 6 && pw === cf && old.length > 0;
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.6';
}

document.getElementById('password_lama').addEventListener('input', updateSaveBtn);

// === Validasi form profil sebelum submit ===
document.getElementById('formProfil').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama_lengkap').value.trim();
    const wa   = document.getElementById('whatsapp').value.replace(/\D/g,'');
    let ok = true;
    if (nama.length < 3) {
        document.getElementById('nama_lengkap').classList.add('is-invalid');
        ok = false;
    } else {
        document.getElementById('nama_lengkap').classList.remove('is-invalid');
    }
    if (wa.length < 9 || wa.length > 15) {
        document.getElementById('whatsapp').classList.add('is-invalid');
        ok = false;
    } else {
        document.getElementById('whatsapp').classList.remove('is-invalid');
    }
    if (!ok) e.preventDefault();
});

// === Validasi form password sebelum submit ===
document.getElementById('formPassword').addEventListener('submit', function(e) {
    const pw = document.getElementById('password_baru').value;
    const cf = document.getElementById('password_konfirm').value;
    const ol = document.getElementById('password_lama').value;
    if (!ol || pw.length < 6 || pw !== cf) {
        e.preventDefault();
        if (!ol) document.getElementById('password_lama').classList.add('is-invalid');
        if (pw.length < 6) document.getElementById('password_baru').classList.add('is-invalid');
        if (pw !== cf) document.getElementById('password_konfirm').classList.add('is-invalid');
    }
});
</script>
