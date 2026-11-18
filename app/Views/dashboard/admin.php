<?php
/* Widget Greeting + Jam */
$hour = (int)date('H');
$greeting = $hour < 11 ? 'Selamat Pagi' : ($hour < 15 ? 'Selamat Siang' : ($hour < 18 ? 'Selamat Sore' : 'Selamat Malam'));
$greetingIcon = $hour < 11 ? 'bi-sun-fill' : ($hour < 15 ? 'bi-brightness-high-fill' : ($hour < 18 ? 'bi-sunset-fill' : 'bi-moon-stars-fill'));
$nama = htmlspecialchars($_SESSION['user_nama'] ?? 'Admin');
$today = date('l, d F Y');

// Siapkan data JSON untuk grafik Chart.js
$chartLabels = json_encode($data['sales_chart']['labels'] ?? []);
$chartSales  = json_encode($data['sales_chart']['sales'] ?? []);
$chartTrx    = json_encode($data['sales_chart']['transactions'] ?? []);

$stockAman    = (int)($data['stock_dist']['aman'] ?? 0);
$stockMenipis = (int)($data['stock_dist']['menipis'] ?? 0);
$stockHabis   = (int)($data['stock_dist']['habis'] ?? 0);
?>

<!-- ═══ 1. HERO BANNER ADMIN ═══ -->
<div class="quick-banner mb-4" style="background: linear-gradient(135deg, #3730a3 0%, #4f46e5 45%, #7c3aed 100%);">
    <div class="row align-items-center g-3">
        <div class="col-lg-7" style="position:relative; z-index:1;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge px-3 py-1 rounded-pill fw-semibold"
                      style="background:rgba(255,255,255,.16); color:#fff; font-size:.75rem; border:1px solid rgba(255,255,255,.25); backdrop-filter:blur(6px);">
                    <i class="bi bi-shield-check me-1 text-warning"></i> Panel Utama Administrator
                </span>
                <span class="badge px-2 py-1 rounded-pill" style="background:rgba(16,185,129,.25); color:#a7f3d0; font-size:.72rem;">
                    <span class="widget-icon-dot bg-success me-1"></span> Sistem Online
                </span>
            </div>
            <h3 class="fw-800 text-white mb-1" style="font-size:1.6rem; font-weight:800; letter-spacing:-0.5px;">
                <i class="bi <?= $greetingIcon ?> text-warning me-2"></i><?= $greeting ?>, <?= $nama ?>!
            </h3>
            <p class="mb-0 text-white" style="opacity:.8; font-size:.875rem;">
                <i class="bi bi-calendar-event me-1"></i><?= $today ?> &nbsp;·&nbsp; Pantau performa bisnis dan penjualan toko Anda secara real-time.
            </p>
        </div>
        <div class="col-lg-5 d-flex flex-wrap gap-2 justify-content-lg-end" style="position:relative; z-index:1;">
            <a href="<?= BASEURL ?>/cashier" class="quick-btn quick-btn-solid shadow-sm">
                <i class="bi bi-cart-plus-fill text-primary"></i> Buka Kasir POS
            </a>
            <a href="<?= BASEURL ?>/product" class="quick-btn quick-btn-white">
                <i class="bi bi-box-seam"></i> Produk & Stok
            </a>
            <a href="<?= BASEURL ?>/sales" class="quick-btn quick-btn-white">
                <i class="bi bi-graph-up-arrow"></i> Laporan
            </a>
            <a href="<?= BASEURL ?>/users" class="quick-btn quick-btn-white">
                <i class="bi bi-people"></i> Kasir
            </a>
        </div>
    </div>
</div>

<!-- ═══ 2. AI BUSINESS INSIGHT WIDGET ═══ -->
<div class="ai-insight-box d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div class="sparkle-icon">
            <i class="bi bi-stars"></i>
        </div>
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold" style="font-size:.9rem; color:#f8fafc;">Rekomendasi Pintar AI</span>
                <span class="badge" style="background:rgba(168,85,247,.3); color:#e9d5ff; font-size:.65rem; border:1px solid rgba(168,85,247,.4);">AI Smart Analytics</span>
            </div>
            <p class="mb-0 text-white-50 small mt-1">
                <?php if ($stockMenipis > 0 || $stockHabis > 0): ?>
                    Perhatian: Terdapat <strong><?= $stockMenipis + $stockHabis ?> produk</strong> yang membutuhkan restock segera agar transaksi kasir tidak terhambat.
                <?php elseif ($data['total_transactions'] == 0): ?>
                    Sistem siap beroperasi. Lakukan transaksi pertama di menu <strong>Transaksi Kasir</strong> untuk mulai melihat grafik omset harian.
                <?php else: ?>
                    Performa toko optimal! Total akumulasi penjualan mencapai <strong>Rp <?= number_format($data['total_sales'], 0, ',', '.') ?></strong> dari <strong><?= $data['total_transactions'] ?> transaksi</strong>.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASEURL ?>/ai" class="btn btn-sm px-3 py-1 fw-semibold text-white" style="background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); border-radius:50px;">
            <i class="bi bi-chat-dots me-1"></i> Tanya AI
        </a>
    </div>
</div>

<!-- ═══ 3. MODERN STAT CARDS ═══ -->
<div class="row g-3 mb-4">

    <!-- Card 1: Total Omset -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #4f46e5, #7c3aed);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Total Akumulasi Omset</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.5rem; letter-spacing:-0.5px;">
                        Rp <?= number_format($data['total_sales'], 0, ',', '.') ?>
                    </h3>
                </div>
                <div class="stat-icon-glow indigo">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge positive">
                    <i class="bi bi-arrow-up-right"></i> Hari ini: Rp <?= number_format($data['today_stats']['today_omset'] ?? 0, 0, ',', '.') ?>
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Penjualan Toko</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Transaksi -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #059669, #10b981);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Total Transaksi Selesai</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.5rem; letter-spacing:-0.5px;">
                        <?= number_format($data['total_transactions'], 0, ',', '.') ?> <span style="font-size:.9rem; font-weight:500;" class="text-muted">trx</span>
                    </h3>
                </div>
                <div class="stat-icon-glow emerald">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge <?= ($data['today_stats']['today_trx'] ?? 0) > 0 ? 'positive' : 'neutral' ?>">
                    <i class="bi bi-clock-history"></i> Hari ini: <?= $data['today_stats']['today_trx'] ?? 0 ?> trx
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Nota Penjualan</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Total Varian Produk -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #0284c7, #38bdf8);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Total Varian Produk</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.5rem; letter-spacing:-0.5px;">
                        <?= number_format($data['total_products'], 0, ',', '.') ?> <span style="font-size:.9rem; font-weight:500;" class="text-muted">item</span>
                    </h3>
                </div>
                <div class="stat-icon-glow sky">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge positive">
                    <i class="bi bi-check-circle-fill"></i> <?= $stockAman ?> varian aman
                </span>
                <a href="<?= BASEURL ?>/product" class="text-primary small text-decoration-none fw-semibold" style="font-size:.72rem;">
                    Kelola <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Card 4: Total Stok Fisik -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card-pro h-100" style="--card-accent: linear-gradient(90deg, #d97706, #f59e0b);">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-600">Total Stok Fisik</span>
                    <h3 class="fw-800 text-dark mb-0 mt-1" style="font-size:1.5rem; letter-spacing:-0.5px;">
                        <?= number_format($data['total_stock'], 0, ',', '.') ?> <span style="font-size:.9rem; font-weight:500;" class="text-muted">unit</span>
                    </h3>
                </div>
                <div class="stat-icon-glow amber">
                    <i class="bi bi-layers"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="trend-badge <?= ($stockMenipis + $stockHabis) > 0 ? 'warning' : 'positive' ?>">
                    <i class="bi bi-exclamation-triangle"></i> <?= $stockMenipis + $stockHabis ?> perlu restock
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Unit Gudang</span>
            </div>
        </div>
    </div>

</div>

<!-- ═══ 4. VISUAL ANALYTICS ROW (CHARTS) ═══ -->
<div class="row g-4 mb-4">

    <!-- Grafik Tren Penjualan 7 Hari Terakhir -->
    <div class="col-12 col-xl-8">
        <div class="widget-card h-100">
            <div class="widget-header d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="widget-title mb-0">
                        <span class="widget-icon-dot bg-primary"></span>
                        <i class="bi bi-graph-up text-primary"></i>
                        Grafik Tren Penjualan Toko (7 Hari Terakhir)
                    </h6>
                    <small class="text-muted" style="font-size:.75rem;">Pergerakan omset dan volume transaksi harian</small>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="chart-period-btn active" id="btnChartOmset">
                        <i class="bi bi-currency-dollar me-1"></i>Omset (Rp)
                    </button>
                    <button type="button" class="chart-period-btn" id="btnChartTrx">
                        <i class="bi bi-receipt me-1"></i>Transaksi
                    </button>
                </div>
            </div>
            <div class="widget-body">
                <div class="chart-container-wrap">
                    <canvas id="salesTrendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Status & Distribusi Stok Produk -->
    <div class="col-12 col-xl-4">
        <div class="widget-card h-100">
            <div class="widget-header">
                <h6 class="widget-title mb-0">
                    <span class="widget-icon-dot bg-success"></span>
                    <i class="bi bi-pie-chart-fill text-success"></i>
                    Status Inventori Stok
                </h6>
                <a href="<?= BASEURL ?>/product" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                    <i class="bi bi-box-seam me-1"></i>Lihat Stok
                </a>
            </div>
            <div class="widget-body d-flex flex-column justify-content-between">
                <div class="donut-container-wrap mb-3">
                    <canvas id="stockDistributionChart"></canvas>
                </div>
                <div class="p-3 rounded-3" style="background:#f8fafc; border:1px solid #e2e8f0;">
                    <div class="row g-2 text-center">
                        <div class="col-4 border-end">
                            <span class="d-block text-success fw-bold" style="font-size:1.15rem;"><?= $stockAman ?></span>
                            <small class="text-muted" style="font-size:.7rem;">Stok Aman</small>
                        </div>
                        <div class="col-4 border-end">
                            <span class="d-block text-warning fw-bold" style="font-size:1.15rem;"><?= $stockMenipis ?></span>
                            <small class="text-muted" style="font-size:.7rem;">Menipis</small>
                        </div>
                        <div class="col-4">
                            <span class="d-block text-danger fw-bold" style="font-size:1.15rem;"><?= $stockHabis ?></span>
                            <small class="text-muted" style="font-size:.7rem;">Habis (0)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ═══ 5. OPERATIONAL WIDGETS ROW ═══ -->
<div class="row g-4">

    <!-- Transaksi Kasir Terbaru -->
    <div class="col-12 col-lg-7">
        <div class="widget-card h-100">
            <div class="widget-header">
                <h6 class="widget-title mb-0">
                    <span class="widget-icon-dot bg-primary"></span>
                    <i class="bi bi-clock-history text-primary"></i>
                    Riwayat Transaksi Terbaru
                </h6>
                <div class="d-flex gap-2">
                    <a href="<?= BASEURL ?>/sales" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                        <i class="bi bi-bar-chart me-1"></i>Laporan
                    </a>
                    <a href="<?= BASEURL ?>/transaction" class="btn btn-sm btn-primary" style="font-size:.75rem;">
                        <i class="bi bi-list-ul me-1"></i>Semua Nota
                    </a>
                </div>
            </div>

            <?php if (empty($data['recent_transactions'])): ?>
                <div class="widget-body">
                    <div class="empty-state py-4">
                        <div class="empty-state-icon" style="background:#ede9fe;">
                            <i class="bi bi-receipt" style="color:#7c3aed;"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Belum Ada Transaksi Tercatat</h6>
                        <p class="text-muted small mb-3">Mulai proses penjualan kasir pertama toko Anda sekarang.</p>
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
                                <th>No. Transaksi</th>
                                <th>Kasir</th>
                                <th>Waktu</th>
                                <th class="text-center">Item</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['recent_transactions'] as $trx): ?>
                            <tr>
                                <td>
                                    <span class="badge-trx">#TRX-<?= str_pad($trx['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; color:#fff; font-size:.7rem; font-weight:700; flex-shrink:0;">
                                            <?= strtoupper(substr($trx['cashier_name'] ?? 'A', 0, 1)) ?>
                                        </div>
                                        <span class="small fw-medium"><?= htmlspecialchars($trx['cashier_name'] ?? 'Admin') ?></span>
                                    </div>
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

    <!-- Peringatan Stok & Produk Unggulan -->
    <div class="col-12 col-lg-5">
        <div class="widget-card h-100">
            <div class="widget-header">
                <h6 class="widget-title mb-0">
                    <span class="widget-icon-dot bg-danger"></span>
                    <i class="bi bi-bell-fill text-danger"></i>
                    Monitoring & Peringatan Stok
                </h6>
                <a href="<?= BASEURL ?>/product" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                    <i class="bi bi-pencil-square me-1"></i>Update Stok
                </a>
            </div>
            <div class="widget-body">
                <?php if (!empty($data['low_stock_products'])): ?>
                    <p class="text-muted small mb-3">Item berikut mendekati batas minimum (≤ 10 unit):</p>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($data['low_stock_products'] as $item):
                            $pct = min(100, max(0, ($item['stock'] / 10) * 100));
                            $barColor = $item['stock'] == 0 ? '#ef4444' : ($item['stock'] <= 3 ? '#f59e0b' : '#10b981');
                        ?>
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-shrink-0" style="width:36px; height:36px; border-radius:10px; background:#fef2f2; display:flex; align-items:center; justify-content:center;">
                                <i class="bi bi-box-seam text-danger" style="font-size:.9rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold text-dark small text-truncate" style="max-width:180px;">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </span>
                                    <span class="badge rounded-pill px-2 py-1 ms-2 flex-shrink-0"
                                          style="background:<?= $item['stock']==0?'#fee2e2':'#fef3c7' ?>; color:<?= $item['stock']==0?'#b91c1c':'#92400e' ?>; font-size:.72rem;">
                                        <?= $item['stock'] ?> unit
                                    </span>
                                </div>
                                <div class="stock-bar-wrap">
                                    <div class="stock-bar" style="width:<?= $pct ?>%; background:<?= $barColor ?>;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 rounded-3 mb-3" style="background:#ecfdf5; border:1px solid #a7f3d0;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success" style="font-size:1.2rem;"></i>
                            <div>
                                <span class="fw-bold text-success small d-block">Semua Stok Produk Aman!</span>
                                <small class="text-muted" style="font-size:.75rem;">Tidak ada item di bawah batas batas kritis.</small>
                            </div>
                        </div>
                    </div>
                    <!-- Tampilkan 5 Produk Teratas Toko -->
                    <span class="fw-bold text-dark small d-block mb-2">Varian Produk Siap Dijual:</span>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (array_slice($data['top_products'] ?? [], 0, 4) as $tp): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-2" style="background:#f8fafc; border:1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px; height:30px; border-radius:8px; background:#e0e7ff; display:flex; align-items:center; justify-content:center; color:#4f46e5; font-size:.8rem;">
                                    <i class="bi bi-cup-hot-fill"></i>
                                </div>
                                <span class="small fw-semibold text-dark"><?= htmlspecialchars($tp['product_name']) ?></span>
                            </div>
                            <div class="text-end">
                                <span class="badge rounded-pill" style="background:#e0f2fe; color:#0369a1; font-size:.72rem;">
                                    <?= $tp['stock'] ?> unit
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ═══ 6. CHART.JS SCRIPT INITIALIZATION ═══ -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const labels = <?= $chartLabels ?>;
    const salesData = <?= $chartSales ?>;
    const trxData = <?= $chartTrx ?>;

    function themeColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            dark: dark,
            primary: dark ? '#818cf8' : '#4f46e5',
            green: dark ? '#34d399' : '#059669',
            grid: dark ? 'rgba(148, 163, 184, .16)' : 'rgba(226, 232, 240, .7)',
            tick: dark ? '#94a3b8' : '#64748b',
            border: dark ? '#1e293b' : '#ffffff',
            tooltip: dark ? '#0f172a' : '#0f172a'
        };
    }
    let theme = themeColors();

    // Line Chart Tren Penjualan
    const ctxSales = document.getElementById('salesTrendsChart').getContext('2d');
    
    // Gradient fill
    const gradient = ctxSales.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.35)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.00)');

    let currentMode = 'omset';
    const salesChart = new Chart(ctxSales, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Omset Penjualan (Rp)',
                data: salesData,
                borderColor: theme.primary,
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                tension: 0.38,
                pointBackgroundColor: theme.primary,
                pointBorderColor: theme.border,
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            if (currentMode === 'omset') {
                                return 'Omset: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            } else {
                                return 'Transaksi: ' + context.parsed.y + ' nota';
                            }
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    ticks: {
                        color: theme.tick,
                        font: { size: 11 },
                        callback: function(value) {
                            if (currentMode === 'omset') {
                                if (value >= 1000000) return 'Rp ' + (value/1000000) + 'jt';
                                if (value >= 1000) return 'Rp ' + (value/1000) + 'k';
                                return 'Rp ' + value;
                            } else {
                                return value + ' trx';
                            }
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: theme.tick, font: { size: 11 } }
                }
            }
        }
    });

    // Toggle button mode Omset vs Transaksi
    const btnOmset = document.getElementById('btnChartOmset');
    const btnTrx = document.getElementById('btnChartTrx');

    btnOmset.addEventListener('click', function() {
        currentMode = 'omset';
        btnOmset.classList.add('active');
        btnTrx.classList.remove('active');
        salesChart.data.datasets[0].label = 'Omset Penjualan (Rp)';
        salesChart.data.datasets[0].data = salesData;
        theme = themeColors();
        salesChart.data.datasets[0].borderColor = theme.primary;
        salesChart.data.datasets[0].pointBackgroundColor = theme.primary;
        salesChart.update();
    });

    btnTrx.addEventListener('click', function() {
        currentMode = 'trx';
        btnTrx.classList.add('active');
        btnOmset.classList.remove('active');
        salesChart.data.datasets[0].label = 'Jumlah Transaksi';
        salesChart.data.datasets[0].data = trxData;
        theme = themeColors();
        salesChart.data.datasets[0].borderColor = theme.green;
        salesChart.data.datasets[0].pointBackgroundColor = theme.green;
        salesChart.update();
    });

    // Donut Chart Distribusi Stok
    const ctxStock = document.getElementById('stockDistributionChart').getContext('2d');
    const stockAman = <?= $stockAman ?>;
    const stockMenipis = <?= $stockMenipis ?>;
    const stockHabis = <?= $stockHabis ?>;
    
    // Fallback bila produk belum ada sama sekali
    const stockData = (stockAman + stockMenipis + stockHabis === 0) ? [1, 0, 0] : [stockAman, stockMenipis, stockHabis];

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
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, color: theme.tick, font: { size: 11 } }
                }
            }
        }
    });

    // Sesuaikan warna grafik saat tema berubah.
    document.addEventListener('aca:themechange', function () {
        theme = themeColors();
        salesChart.options.scales.y.grid.color = theme.grid;
        salesChart.options.scales.y.ticks.color = theme.tick;
        salesChart.options.scales.x.ticks.color = theme.tick;
        salesChart.data.datasets[0].pointBackgroundColor = currentMode === 'omset' ? theme.primary : theme.green;
        if (currentMode === 'omset') {
            salesChart.data.datasets[0].borderColor = theme.primary;
        } else {
            salesChart.data.datasets[0].borderColor = theme.green;
        }
        salesChart.update('none');

        stockChart.data.datasets[0].borderColor = theme.border;
        stockChart.options.plugins.legend.labels.color = theme.tick;
        stockChart.update('none');
    });
});
</script>
