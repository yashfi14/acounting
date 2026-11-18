<?php require_once __DIR__ . '/../../Services/MenuImageService.php'; ?>

<!-- Welcome Banner untuk Pelanggan -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-white text-primary px-3 py-1 rounded-pill mb-2 fw-semibold">
                        <i class="bi bi-shop me-1"></i> Katalog Menu & Produk
                    </span>
                    <h3 class="fw-bold mb-1">Daftar Barang & Harga Terkini</h3>
                    <p class="mb-0 text-white-50">Temukan barang atau menu pilihan Anda dengan ketersediaan stok yang selalu diperbarui.</p>
                </div>
                <div>
                    <span class="badge px-3 py-2 rounded-pill fs-6" style="background:rgba(255,255,255,.18); color:#fff; border:1px solid rgba(255,255,255,.32);">
                        <i class="bi bi-box-seam me-1"></i> Total <?= number_format($data['total_items'] ?? 0, 0, ',', '.'); ?> Produk
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bar Pencarian Produk -->
<div class="row mb-4">
    <div class="col-12 col-md-6 col-lg-5">
        <form action="<?= BASEURL; ?>/catalog" method="GET" class="d-flex gap-2">
            <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white">
                <span class="input-group-text bg-white border-0 ps-3 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input 
                    type="text" 
                    name="keyword" 
                    class="form-control border-0 ps-2" 
                    placeholder="Cari nama barang atau menu..." 
                    value="<?= $data['keyword'] ?? ''; ?>"
                    autocomplete="off"
                >
                <?php if (!empty($data['keyword'])): ?>
                    <a href="<?= BASEURL; ?>/catalog" class="btn btn-white border-0 text-muted d-flex align-items-center pe-3">
                        <i class="bi bi-x-circle-fill"></i>
                    </a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                Cari
            </button>
        </form>
    </div>
</div>

<!-- Grid Daftar Produk -->
<?php if (empty($data['products'])): ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-4">
        <div class="py-4">
            <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
            <h5 class="fw-bold text-dark">Barang Tidak Ditemukan</h5>
            <p class="text-muted mb-3">
                <?= !empty($data['keyword']) 
                    ? 'Tidak ada produk yang cocok dengan kata kunci "' . $data['keyword'] . '".' 
                    : 'Saat ini belum ada produk aktif yang tersedia di katalog.'; ?>
            </p>
            <?php if (!empty($data['keyword'])): ?>
                <a href="<?= BASEURL; ?>/catalog" class="btn btn-outline-primary rounded-pill px-4">
                    Tampilkan Semua Produk
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-4">
        <?php foreach ($data['products'] as $p): ?>
            <?php 
                $isAvailable = (int)$p['stock'] > 0;
            ?>
            <div class="col">
                <?php $menuImage = MenuImageService::resolve($p['product_name'], $p['image'] ?? null); ?>
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden product-card menu-card transition-all">
                    <!-- Foto / Ilustrasi Menu -->
                    <div class="menu-card-media position-relative overflow-hidden">
                        <img src="<?= htmlspecialchars($menuImage['url'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?= htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="menu-card-img w-100 h-100 object-fit-cover"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='<?= BASEURL; ?>/img/menu/default.svg';">

                        <div class="menu-card-shade"></div>

                        <!-- Status Badge di atas foto -->
                        <div class="position-absolute top-0 end-0 m-3">
                            <?php if ($isAvailable): ?>
                                <span class="badge bg-success shadow-sm rounded-pill px-3 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Tersedia
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger shadow-sm rounded-pill px-3 py-1">
                                    <i class="bi bi-x-circle-fill me-1"></i> Habis
                                </span>
                            <?php endif; ?>
                        </div>

                        <span class="menu-card-caption">
                            <i class="bi bi-cup-hot me-1"></i><?= $menuImage['is_fallback'] ? 'Menu Pilihan' : 'Foto Produk'; ?>
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($p['product_name']); ?>">
                                <?= htmlspecialchars($p['product_name']); ?>
                            </h5>
                            <span class="text-muted small">Menu / Barang</span>
                        </div>
                        <div class="mt-3 pt-3 border-top d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small d-block">Harga Satuan</span>
                                <span class="fs-5 fw-bold text-primary">
                                    Rp <?= number_format($p['price'], 0, ',', '.'); ?>
                                </span>
                            </div>
                            <?php if ($isAvailable): ?>
                                <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">
                                    Stok: <?= (int)$p['stock']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginasi -->
    <?php if ($data['total_pages'] > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <!-- Tombol Sebelumnya -->
                <li class="page-item <?= ($data['current_page'] <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill me-2 px-3" href="<?= BASEURL; ?>/catalog?page=<?= $data['current_page'] - 1; ?><?= !empty($data['keyword']) ? '&keyword=' . urlencode($data['keyword']) : ''; ?>">
                        <i class="bi bi-chevron-left me-1"></i>Sebelumnya
                    </a>
                </li>

                <!-- Nomor Halaman -->
                <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                    <li class="page-item <?= ($i == $data['current_page']) ? 'active' : ''; ?>">
                        <a class="page-link rounded-circle mx-1 text-center" style="width: 40px; height: 40px; line-height: 24px;" href="<?= BASEURL; ?>/catalog?page=<?= $i; ?><?= !empty($data['keyword']) ? '&keyword=' . urlencode($data['keyword']) : ''; ?>">
                            <?= $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <!-- Tombol Berikutnya -->
                <li class="page-item <?= ($data['current_page'] >= $data['total_pages']) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill ms-2 px-3" href="<?= BASEURL; ?>/catalog?page=<?= $data['current_page'] + 1; ?><?= !empty($data['keyword']) ? '&keyword=' . urlencode($data['keyword']) : ''; ?>">
                        Berikutnya<i class="bi bi-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <p class="text-center text-muted small mt-4 mb-0">
        Sebagian foto menu berasal dari Unsplash dan Wikimedia Commons.
        <a href="<?= BASEURL; ?>/img/menu/README.txt" target="_blank" rel="noopener" class="text-decoration-none">Atribusi &amp; lisensi</a>.
    </p>
<?php endif; ?>
