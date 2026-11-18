<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= BASE_URL; ?>/users" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 text-gray-800 fw-bold mb-0">Tambah Akun Pengguna / Kasir</h1>
            <p class="text-muted small mb-0">Buat akun untuk kasir toko atau admin baru.</p>
        </div>
    </div>

    <?php Flasher::flash(); ?>

    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="<?= BASE_URL; ?>/users/store" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= CSRF::generate(); ?>">

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required 
                                   placeholder="Contoh: Siti Aisyah">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="kasir@toko.com">
                            <small class="text-muted">Digunakan untuk login ke sistem.</small>
                        </div>

                        <div class="mb-3">
                            <label for="whatsapp" class="form-label fw-semibold">Nomor WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" required 
                                   placeholder="Contoh: 081234567890">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6"
                                   placeholder="Minimal 6 karakter">
                            <small class="text-muted">Password akan dienkripsi secara aman (BCrypt).</small>
                        </div>

                        <div class="mb-4">
                            <label for="role" class="form-label fw-semibold">Role Pengguna <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="kasir" selected>Kasir (Dapat transaksi & kelola barang, tanpa akses database penjualan)</option>
                                <option value="pelanggan">Pelanggan (Hanya katalog & AI assistant)</option>
                                <option value="admin">Admin (Akses penuh ke seluruh sistem)</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="<?= BASE_URL; ?>/users" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Simpan Pengguna
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
