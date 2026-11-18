<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Registrasi Akun Baru</h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (isset($_SESSION['register_errors'])) : ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($_SESSION['register_errors'] as $error) : ?>
                                    <li><?= $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php unset($_SESSION['register_errors']); ?>
                    <?php endif; ?>

                    <?php
                        $old = isset($_SESSION['register_old']) ? $_SESSION['register_old'] : [];
                    ?>

                    <form action="<?= BASEURL; ?>/register/process" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= CSRF::token(); ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= isset($old['name']) ? htmlspecialchars($old['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= isset($old['email']) ? htmlspecialchars($old['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="whatsapp" class="form-label">Nomor WhatsApp</label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?= isset($old['whatsapp']) ? htmlspecialchars($old['whatsapp']) : ''; ?>" required>
                        </div>
                        


                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="6" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Daftar Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                <p>Sudah punya akun? <a href="<?= BASEURL; ?>/auth/login" class="text-decoration-none">Login di sini</a></p>
            </div>
        </div>
    </div>
</div>
