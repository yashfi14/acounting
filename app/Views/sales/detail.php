<?php
$trx = $data['transaction'];
?>

<div class="container-fluid mt-2">
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-text text-primary me-2"></i>Detail Transaksi Penjualan</h3>
            <p class="text-muted mb-0">Informasi lengkap rincian barang dan kasir yang memproses transaksi.</p>
        </div>
        <a href="<?= BASEURL; ?>/sales" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Database Penjualan
        </a>
    </div>

    <div class="row g-4">
        <!-- Informasi Transaksi -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white p-3 rounded-top-4">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Data Transaksi</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <small class="text-muted d-block">Nomor Transaksi</small>
                        <span class="fs-5 fw-bold text-primary">#TRX-<?= str_pad($trx['id'], 4, '0', STR_PAD_LEFT); ?></span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">Waktu Transaksi</small>
                        <span class="fw-medium text-dark"><?= date('d F Y, H:i:s', strtotime($trx['transaction_date'])); ?></span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">Kasir yang Bertugas</small>
                        <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                            <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($trx['cashier_name'] ?? 'Admin'); ?>
                        </span>
                    </div>

                    <hr>

                    <div>
                        <small class="text-muted d-block">Total Pembayaran</small>
                        <span class="fs-4 fw-bold text-success">Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rincian Produk yang Dibeli -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white p-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cart-check me-2 text-success"></i>Rincian Barang yang Terjual</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Nama Produk</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pe-4">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['details'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Tidak ada rincian item.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $totalQty = 0;
                                    foreach ($data['details'] as $item): 
                                        $totalQty += (int)$item['quantity'];
                                    ?>
                                        <tr>
                                            <td class="ps-4 fw-medium text-dark">
                                                <?= htmlspecialchars($item['product_name']); ?>
                                            </td>
                                            <td class="text-end">
                                                Rp <?= number_format($item['price'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">
                                                    <?= $item['quantity']; ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4 fw-bold">
                                                Rp <?= number_format($item['subtotal'], 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light fw-bold">
                                        <td class="ps-4" colspan="2">TOTAL ITEM & KESELURUHAN</td>
                                        <td class="text-center text-primary"><?= $totalQty; ?> unit</td>
                                        <td class="text-end pe-4 text-success fs-5">Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
