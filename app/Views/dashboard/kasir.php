<?php
$hour = (int)date('H');
$greeting = $hour < 11 ? 'Selamat Pagi' : ($hour < 15 ? 'Selamat Siang' : ($hour < 18 ? 'Selamat Sore' : 'Selamat Malam'));
$greetingIcon = $hour < 11 ? 'bi-sun-fill' : ($hour < 15 ? 'bi-brightness-high-fill' : ($hour < 18 ? 'bi-sunset-fill' : 'bi-moon-stars-fill'));
$nama = htmlspecialchars($_SESSION['user_nama'] ?? 'Kasir');
$today = date('l, d F Y');

$stockAman    = (int)($data['stock_dist']['aman'] ?? 0);
$stockMenipis = (int)($data['stock_dist']['menipis'] ?? 0);
$stockHabis   = (int)($data['stock_dist']['habis'] ?? 0);
?>

<!-- ═══ 1. BANNER KASIR OPERASIONAL ═══ -->
<div class="quick-banner mb-4" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 45%, #059669 100%);">
    <div class="row align-items-center g-3">
        <div class="col-lg-7" style="position:relative; z-index:1;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge px-3 py-1 rounded-pill fw-semibold"
                      style="background:rgba(255,255,255,.16); color:#fff; font-size:.75rem; border:1px solid rgba(255,255,255,.25); backdrop-filter:blur(6px);">
                    <i class="bi bi-person-badge me-1 text-warning"></i> Stasiun Kasir & Kas Masuk
                </span>
                <span class="badge px-2 py-1 rounded-pill" style="background:rgba(255,255,255,.2); color:#fff; font-size:.72rem;">
                    <span class="widget-icon-dot bg-warning me-1"></span> Siap Melayani
                </span>
            </div>
            <h3 class="fw-800 text-white mb-1" style="font-size:1.6rem; font-weight:800; letter-spacing:-0.5px;">
                <i class="bi <?= $greetingIcon ?> text-warning me-2"></i><?= $greeting ?>, <?= $nama ?>!
            </h3>
            <p class="mb-0 text-white" style="opacity:.85; font-size:.875rem;">
                <i class="bi bi-calendar-event me-1"></i><?= $today ?> &nbsp;·&nbsp; Selamat bertugas, layani pelanggan dengan cepat dan teliti.
            </p>
        </div>
        <div class="col-lg-5 d-flex flex-wrap gap-2 justify-content-lg-end" style="position:relative; z-index:1;">
            <a href="<?= BASEURL ?>/cashier" class="quick-btn quick-btn-warning shadow-sm">
                <i class="bi bi-cart-plus-fill"></i> Mulai Transaksi Baru
            </a>
            <a href="<?= BASEURL ?>/product" class="quick-btn quick-btn-white">
                <i class="bi bi-box-seam"></i> Cek Stok Barang
            </a>
            <a href="<?= BASEURL ?>/transaction" class="quick-btn quick-btn-white">
                <i class="bi bi-receipt"></i> Nota Saya
            </a>
        </div>
    </div>
</div>

<!-- ═══ 2. QUICK ACTION TILES ═══ -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <a href="<?= BASEURL ?>/cashier" class="quick-action-tile">
            <div class="quick-tile-icon" style="background:#dcfce7; color:#15803d;">
                <i class="bi bi-calculator"></i>
            </div>
            <div>
                <span class="fw-bold d-block" style="font-size:.9rem;">Kasir POS Cepat</span>
                <small class="text-muted" style="font-size:.75rem;">Input pesanan & proses pembayaran kasir</small>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= BASEURL ?>/product" class="quick-action-tile">
            <div class="quick-tile-icon" style="background:#e0e7ff; color:#4338ca;">
                <i class="bi bi-upc-scan"></i>
            </div>
            <div>
                <span class="fw-bold d-block" style="font-size:.9rem;">Katalog & Cek Harga</span>
                <small class="text-muted" style="font-size:.75rem;">Cari harga barang & ketersediaan stok fisik</small>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= BASEURL ?>/transaction" class="quick-action-tile">
            <div class="quick-tile-icon" style="background:#fef3c7; color:#b45309;">
                <i class="bi bi-printer"></i>
            </div>
            <div>
                <span class="fw-bold d-block" style="font-size:.9rem;">Cetak Nota Terakhir</span>
                <small class="text-muted" style="font-size:.75rem;">Lihat daftar transaksi kasir hari ini</small>
            </div>
        </a>
    </div>
</div>

<!-- ═══ 3. STAT CARDS KASIR ═══ -->
<div class="row g-3 mb-4">

    <!-- Transaksi Kasir Hari Ini -->
    <div class="col-12 col-md-4">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #0d9488, #14b8a6);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Transaksi Kasir Anda Hari Ini</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.6rem; letter-spacing:-0.5px;">
                        <?= number_format($data['today_transactions'], 0, ',', '.') ?> <span style="font-size:.9rem; font-weight:500;" class="text-muted">nota</span>
                    </h3>
                </div>
                <div class="stat-icon-glow emerald">
                    <i class="bi bi-check2-all"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge positive">
                    <i class="bi bi-person-check-fill"></i> Akun Anda Aktif
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Sesi Berjalan</span>
            </div>
        </div>
    </div>

    <!-- Total Varian Produk -->
    <div class="col-12 col-md-4">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #4f46e5, #6366f1);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Varian Produk Toko</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.6rem; letter-spacing:-0.5px;">
                        <?= number_format($data['total_products'], 0, ',', '.') ?> <span style="font-size:.9rem; font-weight:500;" class="text-muted">item</span>
                    </h3>
                </div>
                <div class="stat-icon-glow indigo">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge positive">
                    <i class="bi bi-shield-check"></i> <?= $stockAman ?> siap dijual
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Katalog Aktif</span>
            </div>
        </div>
    </div>

    <!-- Total Stok Toko -->
    <div class="col-12 col-md-4">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #0284c7, #38bdf8);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Total Stok Fisik Tersedia</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.6rem; letter-spacing:-0.5px;">
                        <?= number_format($data['total_stock'], 0, ',', '.') ?> <span style="font-size:.9rem; font-weight:500;" class="text-muted">unit</span>
                    </h3>
                </div>
                <div class="stat-icon-glow sky">
                    <i class="bi bi-layers"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge <?= ($stockMenipis + $stockHabis) > 0 ? 'warning' : 'positive' ?>">
                    <i class="bi bi-exclamation-circle"></i> <?= $stockMenipis + $stockHabis ?> stok menipis
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Kondisi Gudang</span>
            </div>
        </div>
    </div>

</div>

<!-- ═══ 4. BOTTOM WIDGETS KASIR ═══ -->
<div class="row g-4">

    <!-- Widget Donut & Monitoring Stok -->
    <div class="col-12 col-lg-5">
        <div class="widget-card h-100">
            <div class="widget-header">
                <h6 class="widget-title mb-0">
                    <span class="widget-icon-dot bg-info"></span>
                    <i class="bi bi-pie-chart-fill text-info"></i>
                    Komposisi Stok Toko
                </h6>
                <a href="<?= BASEURL ?>/product" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                    <i class="bi bi-eye me-1"></i>Lihat Semua
                </a>
            </div>
            <div class="widget-body">
                <div class="donut-container-wrap mb-3">
                    <canvas id="kasirStockChart"></canvas>
                </div>

                <div class="p-3 rounded-3 mb-3" style="background:#f8fafc; border:1px solid #e2e8f0;">
                    <div class="row g-2 text-center">
                        <div class="col-4 border-end">
                            <span class="d-block text-success fw-bold"><?= $stockAman ?></span>
                            <small class="text-muted" style="font-size:.7rem;">Aman (>15)</small>
                        </div>
                        <div class="col-4 border-end">
                            <span class="d-block text-warning fw-bold"><?= $stockMenipis ?></span>
                            <small class="text-muted" style="font-size:.7rem;">Menipis</small>
                        </div>
                        <div class="col-4">
                            <span class="d-block text-danger fw-bold"><?= $stockHabis ?></span>
                            <small class="text-muted" style="font-size:.7rem;">Habis (0)</small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($data['low_stock_products'])): ?>
                    <span class="fw-bold text-dark small d-block mb-2 text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Produk Segera Habis:
                    </span>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (array_slice($data['low_stock_products'], 0, 3) as $item): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-2" style="background:#fef2f2; border:1px solid #fee2e2;">
                            <span class="small fw-semibold text-dark text-truncate" style="max-width:170px;">
                                <?= htmlspecialchars($item['product_name']) ?>
                            </span>
                            <span class="badge rounded-pill" style="background:#fee2e2; color:#b91c1c; font-size:.72rem;">
                                Sisa <?= $item['stock'] ?> unit
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-2 rounded-2 text-center" style="background:#ecfdf5; border:1px solid #a7f3d0;">
                        <span class="text-success small fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i>Semua produk dalam stok mencukupi
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Transaksi Kasir Ini -->
    <div class="col-12 col-lg-7">
        <div class="widget-card h-100">
            <div class="widget-header">
                <h6 class="widget-title mb-0">
                    <span class="widget-icon-dot bg-success"></span>
                    <i class="bi bi-clock-history text-success"></i>
                    Riwayat Transaksi Kasir Anda
                </h6>
                <a href="<?= BASEURL ?>/transaction" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                    <i class="bi bi-list-ul me-1"></i>Buka Riwayat
                </a>
            </div>

            <?php if (empty($data['recent_transactions'])): ?>
                <div class="widget-body">
                    <div class="empty-state py-4">
                        <div class="empty-state-icon" style="background:#f0fdf4;">
                            <i class="bi bi-cart-check text-success"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Belum Ada Transaksi dari Akun Anda</h6>
                        <p class="text-muted small mb-3">Klik tombol di bawah untuk melayani transaksi pelanggan.</p>
                        <a href="<?= BASEURL ?>/cashier" class="btn btn-primary btn-sm px-4">
                            <i class="bi bi-cart-plus me-1"></i>Buka Kasir POS
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>No. Nota</th>
                                <th>Waktu</th>
                                <th class="text-center">Jumlah Item</th>
                                <th class="text-end">Total Belanja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['recent_transactions'] as $trx): ?>
                            <tr>
                                <td>
                                    <span class="badge-trx">#TRX-<?= str_pad($trx['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= date('d M, H:i', strtotime($trx['transaction_date'])) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill px-3" style="background:#f0fdf4; color:#15803d;">
                                        <?= $trx['total_items'] ?> item
                                    </span>
                                </td>
                                <td class="text-end fw-bold" style="color:#0f172a;">
                                    Rp <?= number_format($trx['total_amount'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ═══ 5. DONUT CHART INITIALIZATION ═══ -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctxStock = document.getElementById('kasirStockChart').getContext('2d');
    const stockAman = <?= $stockAman ?>;
    const stockMenipis = <?= $stockMenipis ?>;
    const stockHabis = <?= $stockHabis ?>;
    
    const stockData = (stockAman + stockMenipis + stockHabis === 0) ? [1, 0, 0] : [stockAman, stockMenipis, stockHabis];

    function chartTheme() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            border: dark ? '#1e293b' : '#ffffff',
            tick: dark ? '#94a3b8' : '#64748b'
        };
    }
    let theme = chartTheme();

    const stockChart = new Chart(ctxStock, {
        type: 'doughnut',
        data: {
            labels: ['Stok Aman', 'Stok Menipis', 'Stok Habis'],
            datasets: [{
                data: stockData,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 2,
                borderColor: theme.border,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, color: theme.tick, font: { size: 11 } }
                }
            }
        }
    });

    document.addEventListener('aca:themechange', function () {
        theme = chartTheme();
        stockChart.data.datasets[0].borderColor = theme.border;
        stockChart.options.plugins.legend.labels.color = theme.tick;
        stockChart.update('none');
    });
});
</script>
