<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-gray-800 fw-bold mb-1">Manajemen Pengguna</h1>
            <p class="text-muted small mb-0">Kelola akun Admin, Kasir, dan Pelanggan UMKM.</p>
        </div>
        <a href="<?= BASE_URL; ?>/users/create" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-person-plus-fill"></i>
            <span>Tambah Pengguna / Kasir</span>
        </a>
    </div>

    <?php Flasher::flash(); ?>

    <!-- Filter & Search Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="<?= BASE_URL; ?>/users" method="GET" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="q" class="form-control border-start-0 ps-0" 
                               placeholder="Cari berdasarkan nama, email, atau no WhatsApp..." 
                               value="<?= htmlspecialchars($data['keyword'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">Semua Role</option>
                        <option value="admin" <?= ($data['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="kasir" <?= ($data['role'] ?? '') === 'kasir' ? 'selected' : ''; ?>>Kasir</option>
                        <option value="pelanggan" <?= ($data['role'] ?? '') === 'pelanggan' ? 'selected' : ''; ?>>Pelanggan</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter me-1"></i> Filter
                    </button>
                    <?php if (!empty($data['keyword']) || !empty($data['role'])): ?>
                        <a href="<?= BASE_URL; ?>/users" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- User Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 60px;">No</th>
                            <th>Pengguna</th>
                            <th>Kontak</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th class="text-end pe-4" style="width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['users'])): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-people display-6 d-block mb-2"></i>
                                    Tidak ada data pengguna yang ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $startNo = (($data['currentPage'] ?? 1) - 1) * 10;
                            foreach ($data['users'] as $index => $u): 
                                $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']);
                            ?>
                                <tr>
                                    <td class="ps-4 text-muted"><?= $startNo + $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm rounded-circle bg-light text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                <?= strtoupper(substr($u['nama_lengkap'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark">
                                                    <?= htmlspecialchars($u['nama_lengkap']); ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="badge bg-secondary-subtle text-secondary ms-1">Anda</span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">ID: #<?= (int)$u['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-dark"><?= htmlspecialchars($u['email']); ?></div>
                                        <div class="small text-muted"><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($u['whatsapp']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-primary px-2.5 py-1.5"><i class="bi bi-shield-lock-fill me-1"></i>Admin</span>
                                        <?php elseif ($u['role'] === 'kasir'): ?>
                                            <span class="badge bg-success px-2.5 py-1.5"><i class="bi bi-cash-stack me-1"></i>Kasir</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark px-2.5 py-1.5"><i class="bi bi-person me-1"></i>Pelanggan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$u['is_active'] === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="bi bi-x-circle-fill me-1"></i>Nonaktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= !empty($u['creator_name']) ? htmlspecialchars($u['creator_name']) : 'Sistem / Mandiri'; ?>
                                        </small>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= BASE_URL; ?>/users/edit/<?= (int)$u['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit Akun">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <?php if (!$isSelf): ?>
                                                <form action="<?= BASE_URL; ?>/users/toggleStatus/<?= (int)$u['id']; ?>" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Apakah Anda yakin ingin <?= (int)$u['is_active'] === 1 ? 'menonaktifkan' : 'mengaktifkan'; ?> akun ini?');">
                                                    <input type="hidden" name="csrf_token" value="<?= CSRF::generate(); ?>">
                                                    <button type="submit" class="btn btn-sm <?= (int)$u['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>" 
                                                            title="<?= (int)$u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                                        <i class="bi <?= (int)$u['is_active'] === 1 ? 'bi-slash-circle' : 'bi-check-circle'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (($data['totalPages'] ?? 0) > 1): ?>
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Total <strong><?= (int)($data['totalUsers'] ?? 0); ?></strong> pengguna
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $data['totalPages']; $i++): ?>
                                <li class="page-item <?= ($i === (int)$data['currentPage']) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?= BASE_URL; ?>/users?page=<?= $i; ?>&q=<?= urlencode($data['keyword'] ?? ''); ?>&role=<?= urlencode($data['role'] ?? ''); ?>">
                                        <?= $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
