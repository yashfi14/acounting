<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= BASE_URL; ?>/users" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 text-gray-800 fw-bold mb-0">Edit Pengguna</h1>
            <p class="text-muted small mb-0">Perbarui data, role, atau status pengguna.</p>
        </div>
    </div>

    <?php Flasher::flash(); ?>

    <?php $u = $data['user']; ?>
    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="<?= BASE_URL; ?>/users/update/<?= (int)$u['id']; ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= CSRF::generate(); ?>">

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required 
                                   maxlength="100" autocomplete="name"
                                   value="<?= htmlspecialchars($u['nama_lengkap']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   maxlength="100" inputmode="email" autocomplete="email" spellcheck="false"
                                   value="<?= htmlspecialchars($u['email']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="whatsapp" class="form-label fw-semibold">Nomor WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" required 
                                   maxlength="20" inputmode="tel" autocomplete="tel"
                                   value="<?= htmlspecialchars($u['whatsapp']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label fw-semibold">Role Pengguna <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin (Akses penuh)</option>
                                <option value="kasir" <?= $u['role'] === 'kasir' ? 'selected' : ''; ?>>Kasir (Transaksi & stok)</option>
                                <option value="pelanggan" <?= $u['role'] === 'pelanggan' ? 'selected' : ''; ?>>Pelanggan (Hanya katalog)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                       <?= (int)$u['is_active'] === 1 ? 'checked' : ''; ?>
                                       <?= ((int)$u['id'] === (int)$_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="is_active">Akun Aktif</label>
                            </div>
                            <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                <small class="text-muted d-block mt-1">Anda tidak dapat menonaktifkan akun Anda sendiri.</small>
                                <input type="hidden" name="is_active" value="1">
                            <?php else: ?>
                                <small class="text-muted d-block mt-1">Jika dinonaktifkan, pengguna tidak dapat login ke sistem.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4 pt-3 border-top">
                            <label for="password" class="form-label fw-semibold">Ubah Password</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6"
                                   placeholder="Kosongkan jika tidak ingin mengubah password">
                            <small class="text-muted">Isi minimal 6 karakter hanya jika ingin mengganti password.</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="<?= BASE_URL; ?>/users" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
