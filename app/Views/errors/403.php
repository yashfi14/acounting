<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Akses Ditolak (Forbidden) | Acounting</title>
    <script>
    (function () {
        try {
            var theme = localStorage.getItem('acounting-theme');
            if (theme !== 'dark' && theme !== 'light') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.style.colorScheme = theme;
        } catch (e) { /* fallback terang */ }
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
        }
        .error-card { 
            background: rgba(255, 255, 255, 0.98); 
            backdrop-filter: blur(12px); 
            border-radius: 24px; 
            padding: 3rem 2.5rem; 
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35); 
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        .error-code { 
            font-size: 7.5rem; 
            font-weight: 800; 
            color: #ef4444; 
            line-height: 1; 
            letter-spacing: -4px; 
        }
        .error-shield { 
            font-size: 3.5rem; 
            color: #ef4444; 
            opacity: 0.85; 
            margin-bottom: 0.5rem;
        }
        .role-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.35rem 0.9rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
            border: 1px solid #e2e8f0;
        }
        .theme-toggle-floating { position: fixed; top: 1rem; right: 1rem; z-index: 10; width: 42px; height: 42px; border-radius: 50%; border: 1px solid rgba(148,163,184,.45); background: rgba(15,23,42,.5); color: #fff; display: flex; align-items: center; justify-content: center; }
        html[data-theme="dark"] body { background: linear-gradient(135deg, #0b1220 0%, #1e1b4b 50%, #312e81 100%); }
        html[data-theme="dark"] .error-card { background: #1e293b; color: #e8eef8; border-color: #2c3b52; }
        html[data-theme="dark"] .error-card h2.text-dark { color: #e8eef8 !important; }
        html[data-theme="dark"] .error-card .text-muted { color: #94a3b8 !important; }
        html[data-theme="dark"] .error-card .text-secondary { color: #cbd5e1 !important; }
        html[data-theme="dark"] .role-badge { background: #243247; color: #cbd5e1; border-color: #2c3b52; }
    </style>
    <script src="<?= BASEURL; ?>/js/theme.js?v=<?= time(); ?>" defer></script>
</head>
<body>
    <button type="button" class="theme-toggle-floating" data-theme-toggle aria-label="Ubah tema" title="Ubah tema">
        <i class="bi bi-moon-stars-fill"></i>
    </button>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="error-card text-center">
                    <div class="error-shield">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div class="error-code mb-1">403</div>
                    <h2 class="fw-bold mb-2 text-dark">Akses Ditolak (Forbidden)</h2>

                    <?php 
                        $currentRole = $_SESSION['user_role'] ?? 'tamu';
                    ?>
                    <div class="role-badge">
                        <i class="bi bi-person-badge me-1"></i> Peran Anda: <strong><?= htmlspecialchars(strtoupper($currentRole)); ?></strong>
                    </div>

                    <p class="text-muted lead mb-4 fs-6">
                        <?= isset($errorMessage) && !empty($errorMessage) 
                            ? htmlspecialchars($errorMessage) 
                            : 'Maaf, akun Anda tidak memiliki hak akses yang memadai untuk membuka fitur atau halaman ini.'; ?>
                        <br>
                        <span class="small text-secondary">Semua batasan hak akses diterapkan ketat di sisi server (Role-Based Access Control).</span>
                    </p>

                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($currentRole === 'pelanggan'): ?>
                                <a href="<?= BASEURL; ?>/catalog" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
                                    <i class="bi bi-grid me-2"></i>Katalog Menu/Barang
                                </a>
                            <?php else: ?>
                                <a href="<?= BASEURL; ?>/dashboard" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
                                    <i class="bi bi-speedometer2 me-2"></i>Kembali ke Dashboard
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= BASEURL; ?>/auth" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </a>
                        <?php endif; ?>

                        <button onclick="history.back()" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                            <i class="bi bi-arrow-left me-2"></i>Kembali
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
