<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Detail Transaksi #<?= $data['transaction']['id']; ?></h2>
                <a href="<?= BASEURL; ?>/transaction" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
                </a>
            </div>

            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body bg-light rounded">
                    <div class="row">
                        <div class="col-sm-6">
                            <h6 class="text-muted mb-1">Tanggal Transaksi</h6>
                            <p class="fw-bold fs-5 mb-0"><?= date('d F Y, H:i', strtotime($data['transaction']['transaction_date'])); ?></p>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                            <h6 class="text-muted mb-1">Total Pesanan</h6>
                            <p class="text-success fw-bold fs-4 mb-0">Rp <?= number_format($data['transaction']['total_amount'], 0, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Daftar Barang</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th width="5%" class="text-center">No</th>
                                    <th>Nama Barang</th>
                                    <th width="20%" class="text-end">Harga Satuan</th>
                                    <th width="15%" class="text-center">Kuantitas</th>
                                    <th width="20%" class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data['details'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Detail barang tidak ditemukan.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($data['details'] as $detail): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td class="fw-medium"><?= htmlspecialchars($detail['product_name']); ?></td>
                                            <td class="text-end">Rp <?= number_format($detail['price'], 0, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary px-3 py-2"><?= $detail['quantity']; ?></span>
                                            </td>
                                            <td class="text-end fw-bold">
                                                Rp <?= number_format($detail['subtotal'], 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold py-3">TOTAL KESELURUHAN</td>
                                    <td class="text-end text-success fw-bold fs-5 py-3">
                                        Rp <?= number_format($data['transaction']['total_amount'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 d-flex justify-content-end gap-2">
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Struk
                </button>
                <form action="<?= BASEURL; ?>/transaction/delete/<?= $data['transaction']['id']; ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Stok barang akan dikembalikan.');">
                    <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Hapus Transaksi
                    </button>
                </form>
            </div>
            
        </div>
    </div>
</div>
