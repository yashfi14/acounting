<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Riwayat Transaksi</h2>
                <a href="<?= BASEURL; ?>/cashier" class="btn btn-primary">
                    <i class="bi bi-cart-plus"></i> Transaksi Baru
                </a>
            </div>

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['flash_success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['flash_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form action="<?= BASEURL; ?>/transaction" method="get" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="keyword" class="form-label">ID Transaksi</label>
                            <input type="text" class="form-control" id="keyword" name="keyword" placeholder="Contoh: 12" value="<?= htmlspecialchars($data['keyword']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($data['start_date']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($data['end_date']); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th width="10%" class="text-center">ID Transaksi</th>
                                    <th width="30%">Tanggal Transaksi</th>
                                    <th width="30%">Total Pesanan</th>
                                    <th width="30%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['transactions'])): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="text-center py-5">
                                                <i class="bi bi-receipt fs-1 text-muted"></i>
                                                <h5 class="mt-3 text-muted">Belum ada transaksi ditemukan</h5>
                                                <p class="text-muted">Coba ubah filter pencarian Anda atau buat transaksi baru.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($data['transactions'] as $trx): ?>
                                        <tr>
                                            <td class="text-center fw-bold">#<?= $trx['id']; ?></td>
                                            <td><?= date('d M Y H:i', strtotime($trx['transaction_date'])); ?></td>
                                            <td class="text-success fw-bold">
                                                Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="<?= BASEURL; ?>/transaction/detail/<?= $trx['id']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-eye"></i> Lihat Detail
                                                </a>
                                                <form action="<?= BASEURL; ?>/transaction/delete/<?= $trx['id']; ?>" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Stok barang akan dikembalikan.');">
                                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Hapus
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
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php 
                        $query_params = '';
                        if(!empty($data['keyword'])) $query_params .= '&keyword=' . urlencode($data['keyword']);
                        if(!empty($data['start_date'])) $query_params .= '&start_date=' . urlencode($data['start_date']);
                        if(!empty($data['end_date'])) $query_params .= '&end_date=' . urlencode($data['end_date']);
                        ?>
                        
                        <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                            <li class="page-item <?= ($i == $data['current_page']) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= BASEURL; ?>/transaction?page=<?= $i; ?><?= $query_params; ?>">
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
