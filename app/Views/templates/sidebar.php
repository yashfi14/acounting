    <!-- Sidebar -->
    <?php 
    $role = AuthHelper::role(); 
    $user = Auth::user();
    $judul = $data['judul'] ?? '';
    function isActive($judul, $keyword) {
        return strpos($judul, $keyword) !== false ? 'active-nav' : '';
    }
    ?>
    <div id="sidebar-wrapper">
        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="bi bi-wallet2 text-white" style="font-size:1rem;"></i>
                </div>
                <h5 class="mb-0">Acounting</h5>
            </div>
            <?php if ($role === 'admin'): ?>
                <span class="badge px-2 py-1 rounded-pill" style="background:rgba(99,102,241,.2); color:#a5b4fc; font-size:.68rem; border:1px solid rgba(99,102,241,.3);">
                    <i class="bi bi-shield-check me-1"></i>Administrator
                </span>
            <?php elseif ($role === 'kasir'): ?>
                <span class="badge px-2 py-1 rounded-pill" style="background:rgba(16,185,129,.2); color:#6ee7b7; font-size:.68rem; border:1px solid rgba(16,185,129,.3);">
                    <i class="bi bi-cash-stack me-1"></i>Kasir
                </span>
            <?php else: ?>
                <span class="badge px-2 py-1 rounded-pill" style="background:rgba(6,182,212,.2); color:#67e8f9; font-size:.68rem; border:1px solid rgba(6,182,212,.3);">
                    <i class="bi bi-person me-1"></i>Pelanggan
                </span>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">

            <?php if ($role === 'admin' || $role === 'kasir'): ?>
                <span class="sidebar-section-title">Utama</span>
                <a href="<?= BASEURL ?>/dashboard" class="nav-link <?= isActive($judul, 'Dashboard') ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="<?= BASEURL ?>/product" class="nav-link <?= isActive($judul, 'Barang') ?>">
                    <i class="bi bi-box-seam"></i> Manajemen Barang
                </a>
                <a href="<?= BASEURL ?>/cashier" class="nav-link <?= isActive($judul, 'Kasir') ?>">
                    <i class="bi bi-cart-check"></i> Transaksi Kasir
                </a>
                <a href="<?= BASEURL ?>/transaction" class="nav-link <?= isActive($judul, 'Riwayat') ?>">
                    <i class="bi bi-receipt"></i> Riwayat Transaksi
                </a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <span class="sidebar-section-title">Laporan & Manajemen</span>
                <a href="<?= BASEURL ?>/sales" class="nav-link <?= isActive($judul, 'Penjualan') ?>">
                    <i class="bi bi-graph-up-arrow"></i> Database Penjualan
                </a>
                <a href="<?= BASEURL ?>/users" class="nav-link <?= isActive($judul, 'Pengguna') ?>">
                    <i class="bi bi-people-fill"></i> Manajemen Pengguna
                </a>
                <a href="<?= BASEURL ?>/whatsapp" class="nav-link <?= isActive($judul, 'WhatsApp') ?>">
                    <i class="bi bi-whatsapp" style="color: #25D366;"></i> Notifikasi WhatsApp
                </a>
                <a href="<?= BASEURL ?>/aisettings" class="nav-link <?= isActive($judul, 'Pengaturan Aca') ?>">
                    <i class="bi bi-gear-fill"></i> Pengaturan Aca
                </a>
            <?php endif; ?>

            <?php if ($role === 'pelanggan'): ?>
                <span class="sidebar-section-title">Toko</span>
                <a href="<?= BASEURL ?>/catalog" class="nav-link <?= isActive($judul, 'Katalog') ?>">
                    <i class="bi bi-grid-fill"></i> Katalog Barang
                </a>
            <?php endif; ?>

            <span class="sidebar-section-title">Lainnya</span>
            <a href="<?= BASEURL ?>/ai" class="nav-link <?= isActive($judul, 'AI') ?>">
                <i class="bi bi-robot"></i> AI Assistant
                <span class="badge ms-auto px-2 py-0" style="background:rgba(99,102,241,.2); color:#a5b4fc; font-size:.6rem; border-radius:6px;">Beta</span>
            </a>
            <a href="<?= BASEURL ?>/profile" class="nav-link <?= isActive($judul, 'Profil') ?>">
                <i class="bi bi-person-circle"></i> Profil Saya
            </a>

            <div style="margin: 1rem 0; border-top: 1px solid rgba(255,255,255,.06);"></div>

            <a href="<?= BASEURL ?>/auth/logout" class="nav-link" style="color:#f87171;">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
        </nav>
    </div>
    <!-- /#sidebar-wrapper -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <!-- Page Content -->
    <div id="page-content-wrapper">
        <!-- Top Navbar -->
        <nav class="navbar top-navbar px-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button class="btn p-1 border-0" id="menu-toggle" style="color:#64748b; background:transparent; line-height:1;">
                    <i class="bi bi-list" style="font-size:1.4rem;"></i>
                </button>
                <h5 class="mb-0 fw-700" style="font-size:1rem; color:#0f172a; font-weight:700;"><?= htmlspecialchars($judul) ?></h5>
            </div>

            <div class="d-flex align-items-center gap-2 gap-md-3">
                <!-- Theme Toggle -->
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Ubah tema" title="Ubah tema">
                    <i class="bi bi-moon-stars-fill"></i>
                </button>

                <!-- Role & Status Akun Dropdown (Satu Akun Satu Peran Tetap) -->
                <?php 
                $opStatus = $_SESSION['operational_status'] ?? 'aktif'; 
                $statusColors = [
                    'aktif'     => ['dot' => '#10b981', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#a7f3d0', 'label' => 'Aktif'],
                    'istirahat' => ['dot' => '#f59e0b', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fde68a', 'label' => 'Istirahat'],
                    'offline'   => ['dot' => '#94a3b8', 'bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#e2e8f0', 'label' => 'Offline'],
                ];
                $curStat = $statusColors[$opStatus] ?? $statusColors['aktif'];
                ?>
                <div class="dropdown">
                    <button class="btn btn-sm p-0 border-0 d-flex align-items-center" type="button" id="dropdownStatusAkun" data-bs-toggle="dropdown" aria-expanded="false" style="background:transparent; cursor:pointer;" title="Klik untuk mengubah status akun">
                        <?php if ($role === 'admin'): ?>
                            <span class="badge px-3 py-1.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-1.5" style="background:#ede9fe; color:#5b21b6; font-size:.78rem; font-weight:600; border: 1px solid #ddd6fe; transition: all .2s;">
                                <i class="bi bi-shield-lock-fill me-1"></i>Admin
                                <span style="width:6px; height:6px; border-radius:50%; background:<?= $curStat['dot'] ?>; display:inline-block; margin:0 3px;"></span>
                                <span style="font-size:0.7rem; font-weight:500; opacity:0.9;"><?= $curStat['label'] ?></span>
                                <i class="bi bi-chevron-down ms-1" style="font-size:0.6rem; opacity:0.7;"></i>
                            </span>
                        <?php elseif ($role === 'kasir'): ?>
                            <span class="badge px-3 py-1.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-1.5" style="background:<?= $curStat['bg'] ?>; color:<?= $curStat['text'] ?>; font-size:.78rem; font-weight:600; border: 1px solid <?= $curStat['border'] ?>; transition: all .2s;">
                                <i class="bi bi-cash-stack me-1"></i>Kasir
                                <span style="width:6px; height:6px; border-radius:50%; background:<?= $curStat['dot'] ?>; display:inline-block; margin:0 3px;"></span>
                                <span style="font-size:0.7rem; font-weight:500; opacity:0.9;"><?= $curStat['label'] ?></span>
                                <i class="bi bi-chevron-down ms-1" style="font-size:0.6rem; opacity:0.7;"></i>
                            </span>
                        <?php else: ?>
                            <span class="badge px-3 py-1.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-1.5" style="background:#cffafe; color:#164e63; font-size:.78rem; font-weight:600; border: 1px solid #a5f3fc; transition: all .2s;">
                                <i class="bi bi-person-fill me-1"></i>Pelanggan
                                <span style="width:6px; height:6px; border-radius:50%; background:<?= $curStat['dot'] ?>; display:inline-block; margin:0 3px;"></span>
                                <span style="font-size:0.7rem; font-weight:500; opacity:0.9;"><?= $curStat['label'] ?></span>
                                <i class="bi bi-chevron-down ms-1" style="font-size:0.6rem; opacity:0.7;"></i>
                            </span>
                        <?php endif; ?>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="dropdownStatusAkun" style="min-width: 250px; border-radius: 12px; margin-top: 8px;">
                        <li class="px-3 py-1 text-uppercase text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                            Pilihan Status Akun
                        </li>
                        
                        <li class="px-3 py-1 mb-1">
                            <small class="text-muted" style="font-size: 0.72rem;">Peran: <strong><?= ucfirst($role) ?></strong> (1 Akun 1 Peran Tetap)</small>
                        </li>

                        <li><hr class="dropdown-divider my-1"></li>

                        <!-- Status 1: Aktif -->
                        <li>
                            <a class="dropdown-item rounded-2 py-2 d-flex align-items-center justify-content-between <?= $opStatus === 'aktif' ? 'active bg-success text-white' : '' ?>" href="<?= BASEURL ?>/auth/switchStatus/aktif">
                                <div class="d-flex align-items-center gap-2">
                                    <span style="width:10px; height:10px; border-radius:50%; background:<?= $opStatus === 'aktif' ? '#ffffff' : '#10b981' ?>; display:inline-block; flex-shrink:0;"></span>
                                    <div>
                                        <div class="fw-semibold <?= $opStatus === 'aktif' ? 'text-white' : 'text-dark' ?>" style="font-size: 0.85rem;">Aktif</div>
                                        <div class="small <?= $opStatus === 'aktif' ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.7rem;">Siap melayani transaksi kasir</div>
                                    </div>
                                </div>
                                <?php if ($opStatus === 'aktif'): ?>
                                    <i class="bi bi-check-circle-fill text-white ms-2"></i>
                                <?php endif; ?>
                            </a>
                        </li>

                        <!-- Status 2: Istirahat -->
                        <li>
                            <a class="dropdown-item rounded-2 py-2 d-flex align-items-center justify-content-between <?= $opStatus === 'istirahat' ? 'active bg-warning text-dark' : '' ?>" href="<?= BASEURL ?>/auth/switchStatus/istirahat">
                                <div class="d-flex align-items-center gap-2">
                                    <span style="width:10px; height:10px; border-radius:50%; background:<?= $opStatus === 'istirahat' ? '#1e293b' : '#f59e0b' ?>; display:inline-block; flex-shrink:0;"></span>
                                    <div>
                                        <div class="fw-semibold <?= $opStatus === 'istirahat' ? 'text-dark' : 'text-dark' ?>" style="font-size: 0.85rem;">Istirahat</div>
                                        <div class="small <?= $opStatus === 'istirahat' ? 'text-dark-50' : 'text-muted' ?>" style="font-size: 0.7rem;">Sedang istirahat sementara</div>
                                    </div>
                                </div>
                                <?php if ($opStatus === 'istirahat'): ?>
                                    <i class="bi bi-check-circle-fill text-dark ms-2"></i>
                                <?php endif; ?>
                            </a>
                        </li>

                        <!-- Status 3: Offline / Selesai Shift -->
                        <li>
                            <a class="dropdown-item rounded-2 py-2 d-flex align-items-center justify-content-between <?= $opStatus === 'offline' ? 'active bg-secondary text-white' : '' ?>" href="<?= BASEURL ?>/auth/switchStatus/offline">
                                <div class="d-flex align-items-center gap-2">
                                    <span style="width:10px; height:10px; border-radius:50%; background:<?= $opStatus === 'offline' ? '#ffffff' : '#94a3b8' ?>; display:inline-block; flex-shrink:0;"></span>
                                    <div>
                                        <div class="fw-semibold <?= $opStatus === 'offline' ? 'text-white' : 'text-dark' ?>" style="font-size: 0.85rem;">Offline / Tutup</div>
                                        <div class="small <?= $opStatus === 'offline' ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.7rem;">Selesai bertugas / shift tutup</div>
                                    </div>
                                </div>
                                <?php if ($opStatus === 'offline'): ?>
                                    <i class="bi bi-check-circle-fill text-white ms-2"></i>
                                <?php endif; ?>
                            </a>
                        </li>

                        <li><hr class="dropdown-divider my-1"></li>
                        
                        <li class="px-3 py-1">
                            <small class="text-muted" style="font-size: 0.68rem; line-height: 1.3; display: block;">
                                <i class="bi bi-info-circle me-1"></i>Satu akun hanya memiliki satu peran tetap. Untuk peran lain, login dengan akun yang bersangkutan.
                            </small>
                        </li>
                    </ul>
                </div>

                <!-- User Avatar -->
                <a href="<?= BASEURL ?>/profile" class="text-decoration-none d-flex align-items-center gap-2">
                    <span class="d-none d-md-block fw-600 text-dark" style="font-size:.875rem; font-weight:600;">
                        <?= $user ? htmlspecialchars($user['name']) : 'User' ?>
                    </span>
                    <div style="width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:.9rem; flex-shrink:0; box-shadow: 0 2px 8px rgba(99,102,241,.4);">
                        <?= strtoupper(substr($user ? $user['name'] : 'U', 0, 1)) ?>
                    </div>
                </a>
            </div>
        </nav>
        
        <!-- Main Content Container -->
        <div class="container-fluid content-container">
