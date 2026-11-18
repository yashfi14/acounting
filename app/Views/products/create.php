<?php
require_once __DIR__ . '/../../Services/MenuImageService.php';

$errors    = $data['errors']    ?? [];
$old       = $data['old_input'] ?? [];
$preview   = MenuImageService::resolve($old['name'] ?? '', null)['url'];
function esc_p($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Barang</h2>
                <a href="<?= BASEURL; ?>/product" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3">
                    <strong><i class="bi bi-exclamation-triangle me-1"></i>Periksa inputan Anda:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/product/store" method="post" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label fw-medium">Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   maxlength="255"
                                   value="<?= esc_p($old['name'] ?? ''); ?>"
                                   placeholder="Masukkan nama barang">
                        </div>

                        <div class="mb-3">
                            <label for="stock" class="form-label fw-medium">Stok Awal <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="stock" name="stock"
                                   required min="0" max="999999"
                                   value="<?= esc_p($old['stock'] ?? '0'); ?>">
                            <div class="form-text">Stok tidak boleh bernilai negatif.</div>
                        </div>

                        <div class="mb-4">
                            <label for="price" class="form-label fw-medium">Harga (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="price" name="price"
                                       required min="0" step="1"
                                       value="<?= esc_p($old['price'] ?? ''); ?>"
                                       placeholder="Contoh: 15000">
                            </div>
                            <div class="form-text">Harga harus berupa angka valid dan tidak boleh negatif.</div>
                        </div>

                        <div class="mb-4">
                            <label for="image" class="form-label fw-medium">Gambar Menu <span class="text-muted fw-normal">(opsional)</span></label>
                            <div class="d-flex align-items-center gap-3 p-3 rounded-3 border bg-light">
                                <img id="imagePreview" src="<?= esc_p($preview); ?>" alt="Pratinjau gambar menu"
                                     class="rounded-3 object-fit-cover shadow-sm flex-shrink-0"
                                     style="width: 92px; height: 72px; background:#fff;">
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="image" name="image"
                                           accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text mb-0">JPG, PNG, atau WEBP. Maksimal 3 MB. Tanpa gambar, katalog memakai ilustrasi otomatis.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <label class="form-label fw-medium d-block mb-1">Status Publik (Katalog Pelanggan)</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (!isset($old['is_active']) || $old['is_active'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-secondary small" for="is_active">
                                    Tampilkan barang ini di Katalog Menu/Barang Pelanggan
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= BASEURL; ?>/product" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-2"></i>Simpan Barang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('image');
    var preview = document.getElementById('imagePreview');
    if (!input || !preview) { return; }
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { return; }
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            alert('Format gambar harus JPG, PNG, atau WEBP.');
            input.value = '';
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 3 MB.');
            input.value = '';
            return;
        }
        preview.src = URL.createObjectURL(file);
    });
})();
</script>
