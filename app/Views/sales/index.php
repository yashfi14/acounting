<!-- Ringkasan Metrik Database Penjualan (Khusus Admin) -->
<div class="row g-4 mb-4">
    <!-- Total Penjualan (Omset) -->
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm rounded-4 stat-card bg-warning text-dark h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-normal text-dark opacity-75 mb-1">Total Penjualan (Omset)</h6>
                    <h3 class="fw-bold mb-0">Rp <?= number_format($data['metrics']['total_sales'] ?? 0, 0, ',', '.') ?></h3>
                    <small class="text-dark opacity-75">Akumulasi pendapatan toko</small>
                </div>
                <div class="fs-1 text-dark opacity-25">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Transaksi -->
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm rounded-4 stat-card bg-primary text-white h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-normal text-white-50 mb-1">Total Transaksi Selesai</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($data['metrics']['total_transactions'] ?? 0, 0, ',', '.') ?></h3>
                    <small class="text-white-50">Frekuensi penjualan tercatat</small>
                </div>
                <div class="fs-1 text-white-50 opacity-50">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Barang Terjual -->
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm rounded-4 stat-card bg-success text-white h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-normal text-white-50 mb-1">Total Barang Terjual</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($data['metrics']['total_items_sold'] ?? 0, 0, ',', '.') ?> unit</h3>
                    <small class="text-white-50">Kuantitas produk keluar</small>
                </div>
                <div class="fs-1 text-white-50 opacity-50">
                    <i class="bi bi-box2-heart"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Nav Tabs: Database Transaksi, Laporan Harian, Laporan Bulanan -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom pt-3 pb-0 px-4">
        <ul class="nav nav-tabs card-header-tabs" id="salesTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="transaksi-tab" data-bs-toggle="tab" data-bs-target="#transaksi-tab-pane" type="button" role="tab">
                    <i class="bi bi-database me-2"></i>Daftar Transaksi Penjualan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily-tab-pane" type="button" role="tab">
                    <i class="bi bi-calendar2-day me-2"></i>Laporan Penjualan Harian
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly-tab-pane" type="button" role="tab">
                    <i class="bi bi-calendar2-month me-2"></i>Laporan Penjualan Bulanan
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        <div class="tab-content" id="salesTabContent">
            
            <!-- TAB 1: DAFTAR TRANSAKSI PENJUALAN -->
            <div class="tab-pane fade show active" id="transaksi-tab-pane" role="tabpanel">
                <!-- Filter Form -->
                <form action="<?= BASEURL; ?>/sales" method="GET" class="row g-2 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $data['start_date']; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $data['end_date']; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Cari No. Transaksi (ID)</label>
                        <input type="text" name="keyword" class="form-control" placeholder="Contoh: 12" value="<?= $data['keyword']; ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <?php if (!empty($data['start_date']) || !empty($data['end_date']) || !empty($data['keyword'])): ?>
                            <a href="<?= BASEURL; ?>/sales" class="btn btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Tabel Transaksi -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Transaksi</th>
                                <th>Waktu Penjualan</th>
                                <th>Kasir Bertugas</th>
                                <th class="text-end">Nominal Pembayaran</th>
                                <th class="text-center" width="12%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['transactions'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        Belum ada data transaksi yang sesuai filter.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['transactions'] as $trx): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary">#TRX-<?= str_pad($trx['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                        </td>
                                        <td class="text-secondary small">
                                            <?= date('d M Y, H:i', strtotime($trx['transaction_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($trx['cashier_name'] ?? 'Admin'); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-end">
                                            Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= BASEURL; ?>/sales/detail/<?= $trx['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="bi bi-eye me-1"></i>Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginasi -->
                <?php if ($data['total_pages'] > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($data['current_page'] <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link rounded-pill me-2 px-3" href="<?= BASEURL; ?>/sales?page=<?= $data['current_page'] - 1; ?><?= !empty($data['keyword']) ? '&keyword=' . $data['keyword'] : ''; ?><?= !empty($data['start_date']) ? '&start_date=' . $data['start_date'] : ''; ?><?= !empty($data['end_date']) ? '&end_date=' . $data['end_date'] : ''; ?>">Sebelumnya</a>
                            </li>
                            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                                <li class="page-item <?= ($i == $data['current_page']) ? 'active' : ''; ?>">
                                    <a class="page-link rounded-circle mx-1 text-center" style="width:40px;height:40px;line-height:24px;" href="<?= BASEURL; ?>/sales?page=<?= $i; ?><?= !empty($data['keyword']) ? '&keyword=' . $data['keyword'] : ''; ?><?= !empty($data['start_date']) ? '&start_date=' . $data['start_date'] : ''; ?><?= !empty($data['end_date']) ? '&end_date=' . $data['end_date'] : ''; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($data['current_page'] >= $data['total_pages']) ? 'disabled' : ''; ?>">
                                <a class="page-link rounded-pill ms-2 px-3" href="<?= BASEURL; ?>/sales?page=<?= $data['current_page'] + 1; ?><?= !empty($data['keyword']) ? '&keyword=' . $data['keyword'] : ''; ?><?= !empty($data['start_date']) ? '&start_date=' . $data['start_date'] : ''; ?><?= !empty($data['end_date']) ? '&end_date=' . $data['end_date'] : ''; ?>">Berikutnya</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>

            <!-- TAB 2: LAPORAN PENJUALAN HARIAN -->
            <div class="tab-pane fade" id="daily-tab-pane" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-graph-up me-2 text-primary"></i>Ringkasan Harian (30 Hari Terakhir)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-center">Jumlah Transaksi</th>
                                <th class="text-center">Total Barang Terjual</th>
                                <th class="text-end">Total Omset Harian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['daily_sales'])): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada riwayat penjualan dalam periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['daily_sales'] as $daily): ?>
                                    <tr>
                                        <td class="fw-medium">
                                            <i class="bi bi-calendar-event me-2 text-secondary"></i>
                                            <?= date('d F Y', strtotime($daily['date'])); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">
                                                <?= (int)$daily['total_transactions']; ?> transaksi
                                            </span>
                                        </td>
                                        <td class="text-center fw-medium">
                                            <?= (int)$daily['total_items']; ?> unit
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            Rp <?= number_format($daily['total_amount'], 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: LAPORAN PENJUALAN BULANAN -->
            <div class="tab-pane fade" id="monthly-tab-pane" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-line-fill me-2 text-success"></i>Laporan Rekapitulasi Bulanan</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Periode Bulan & Tahun</th>
                                <th class="text-center">Total Transaksi</th>
                                <th class="text-center">Total Kuantitas Terjual</th>
                                <th class="text-end">Total Omset Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['monthly_sales'])): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada data bulanan yang tercatat.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['monthly_sales'] as $monthly): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <i class="bi bi-calendar3 me-2 text-primary"></i>
                                            <?= date('F Y', strtotime($monthly['month_year'] . '-01')); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-1 rounded-pill fw-semibold">
                                                <?= (int)$monthly['total_transactions']; ?> transaksi
                                            </span>
                                        </td>
                                        <td class="text-center fw-medium">
                                            <?= (int)$monthly['total_items']; ?> unit
                                        </td>
                                        <td class="text-end fw-bold text-primary fs-6">
                                            Rp <?= number_format($monthly['total_amount'], 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
