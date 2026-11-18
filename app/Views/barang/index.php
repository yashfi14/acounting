<div class="row mb-4 align-items-center">
    <div class="col">
        <h4 class="mb-0 fw-bold">Manajemen Data Barang</h4>
        <p class="text-secondary mb-0">Kelola stok dan harga barang dagangan Anda</p>
    </div>
    <div class="col-auto">
        <!-- Tombol Tambah Data -->
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Barang
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Kode Barang</th>
                        <th>Nama Barang</th>
                        <th width="10%">Stok</th>
                        <th width="20%">Harga</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach( $data['barang'] as $brg ) : ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><span class="badge bg-secondary-subtle text-secondary px-2 py-1 rounded"><?= $brg['kode_barang']; ?></span></td>
                        <td class="fw-medium"><?= $brg['nama_barang']; ?></td>
                        <td>
                            <?php if($brg['stok'] <= 10): ?>
                                <span class="text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i> <?= $brg['stok']; ?></span>
                            <?php else: ?>
                                <span class="text-success fw-medium"><?= $brg['stok']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>Rp <?= number_format($brg['harga'], 0, ',', '.'); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary rounded-pill me-1 mb-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal<?= $brg['id']; ?>" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="<?= BASEURL; ?>/barang/hapus/<?= $brg['id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill mb-1" onclick="return confirm('Yakin ingin menghapus data ini?');" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal<?= $brg['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 rounded-4 shadow">
                                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                                    <h5 class="modal-title fw-bold" id="editModalLabel">Ubah Data Barang</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <form action="<?= BASEURL; ?>/barang/ubah" method="post">
                                        <input type="hidden" name="id" value="<?= $brg['id']; ?>">
                                        <div class="mb-3">
                                            <label for="kode_barang" class="form-label fw-medium text-secondary">Kode Barang</label>
                                            <input type="text" class="form-control" id="kode_barang" name="kode_barang" value="<?= $brg['kode_barang']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="nama_barang" class="form-label fw-medium text-secondary">Nama Barang</label>
                                            <input type="text" class="form-control" id="nama_barang" name="nama_barang" value="<?= $brg['nama_barang']; ?>" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="stok" class="form-label fw-medium text-secondary">Stok</label>
                                                <input type="number" class="form-control" id="stok" name="stok" value="<?= $brg['stok']; ?>" required>
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <label for="harga" class="form-label fw-medium text-secondary">Harga (Rp)</label>
                                                <input type="number" class="form-control" id="harga" name="harga" value="<?= $brg['harga']; ?>" required>
                                            </div>
                                        </div>
                                </div>
                                <div class="modal-footer border-top-0 pb-4 px-4">
                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="tambahModalLabel">Tambah Data Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="<?= BASEURL; ?>/barang/tambah" method="post">
                    <div class="mb-3">
                        <label for="kode_barang" class="form-label fw-medium text-secondary">Kode Barang</label>
                        <input type="text" class="form-control" id="kode_barang" name="kode_barang" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama_barang" class="form-label fw-medium text-secondary">Nama Barang</label>
                        <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="stok" class="form-label fw-medium text-secondary">Stok Awal</label>
                            <input type="number" class="form-control" id="stok" name="stok" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="harga" class="form-label fw-medium text-secondary">Harga (Rp)</label>
                            <input type="number" class="form-control" id="harga" name="harga" required>
                        </div>
                    </div>
            </div>
            <div class="modal-footer border-top-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">Tambah Data</button>
                </form>
            </div>
        </div>
    </div>
</div>
