<?php
require_once __DIR__ . '/../../Services/MenuImageService.php';

$success = $data['success'] ?? null;
$error   = $data['error']   ?? null;
?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Manajemen Barang</h2>
                    <p class="text-muted mb-0 mt-1">Kelola produk dan stok Anda</p>
                </div>
                <a href="<?= BASEURL; ?>/product/create" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Barang
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form Pencarian -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body py-3">
                    <form action="<?= BASEURL; ?>/product" method="get" class="row g-2 align-items-center">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" name="keyword" class="form-control border-start-0 ps-0"
                                       placeholder="Cari nama barang..."
                                       value="<?= $data['keyword']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary flex-grow-1">Cari</button>
                            <?php if(!empty($data['keyword'])): ?>
                                <a href="<?= BASEURL; ?>/product" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Barang -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th width="5%" class="text-center ps-4">No</th>
                                    <th>Nama Barang</th>
                                    <th width="12%" class="text-center">Stok</th>
                                    <th width="16%" class="text-end">Harga (Rp)</th>
                                    <th width="14%" class="text-center">Katalog</th>
                                    <th width="15%" class="text-center pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['products'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="bi bi-box-seam text-muted" style="font-size:3.5rem;"></i>
                                            <h5 class="mt-3 text-muted fw-normal">
                                                <?= !empty($data['keyword']) ? 'Tidak ada barang yang cocok dengan pencarian.' : 'Belum ada barang.'; ?>
                                            </h5>
                                            <?php if (empty($data['keyword'])): ?>
                                                <a href="<?= BASEURL; ?>/product/create" class="btn btn-primary mt-2">
                                                    <i class="bi bi-plus-circle me-2"></i>Tambah Barang Pertama
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $page  = $data['current_page'];
                                    $limit = 10;
                                    $no    = ($page - 1) * $limit + 1;
                                    foreach ($data['products'] as $product):
                                    ?>
                                        <tr>
                                            <td class="text-center text-muted ps-4"><?= $no++; ?></td>
                                            <td class="fw-medium">
                                                <?php $thumb = MenuImageService::resolve($product['product_name'], $product['image'] ?? null); ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= htmlspecialchars($thumb['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                         alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                         class="rounded-3 object-fit-cover border"
                                                         width="46" height="38" loading="lazy"
                                                         style="background:#f8fafc;">
                                                    <span><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $stock = (int)$product['stock'];
                                                $badge = $stock === 0 ? 'bg-danger' : ($stock <= 5 ? 'bg-warning text-dark' : 'bg-success');
                                                ?>
                                                <span class="badge <?= $badge; ?> rounded-pill px-3"><?= $stock; ?></span>
                                            </td>
                                            <td class="text-end">Rp <?= number_format($product['price'], 0, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <?php if ((int)($product['is_active'] ?? 1) === 1): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1"><i class="bi bi-eye me-1"></i>Publik</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1"><i class="bi bi-eye-slash me-1"></i>Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <a href="<?= BASEURL; ?>/product/edit/<?= (int)$product['id']; ?>"
                                                   class="btn btn-sm btn-outline-warning me-1" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="<?= BASEURL; ?>/product/delete/<?= (int)$product['id']; ?>"
                                                      method="POST" class="d-inline"
                                                      onsubmit="return confirm('Hapus barang ini secara permanen?');">
                                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($data['total_pages'] > 1): ?>
                <nav aria-label="Navigasi halaman" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                            <li class="page-item <?= ($i == $data['current_page']) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= BASEURL; ?>/product?page=<?= $i; ?><?= !empty($data['keyword']) ? '&keyword=' . urlencode($data['keyword']) : ''; ?>">
                                    <?= $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
