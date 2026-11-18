<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Kesalahan Server | Acounting</title>
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
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; }
        .error-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 3rem 2.5rem; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .error-code { font-size: 8rem; font-weight: 700; color: #ef4444; line-height: 1; letter-spacing: -4px; }
        .error-icon { font-size: 4rem; color: #ef4444; opacity: 0.3; }
        .theme-toggle-floating { position: fixed; top: 1rem; right: 1rem; z-index: 10; width: 42px; height: 42px; border-radius: 50%; border: 1px solid rgba(148,163,184,.45); background: rgba(15,23,42,.5); color: #fff; display: flex; align-items: center; justify-content: center; }
        html[data-theme="dark"] body { background: linear-gradient(135deg, #0b1220 0%, #3b0d1a 100%); }
        html[data-theme="dark"] .error-card { background: #1e293b; color: #e8eef8; border: 1px solid #2c3b52; }
        html[data-theme="dark"] .error-card h2 { color: #e8eef8; }
        html[data-theme="dark"] .error-card .text-muted { color: #94a3b8 !important; }
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
                    <div class="error-code mb-0">500</div>
                    <i class="bi bi-exclamation-triangle error-icon"></i>
                    <h2 class="fw-bold mt-3 mb-2">Kesalahan Server</h2>
                    <p class="text-muted lead mb-4">
                        Terjadi kesalahan internal pada server.<br>
                        Tim kami sudah menerima notifikasi. Coba beberapa saat lagi.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?= BASEURL; ?>/dashboard" class="btn btn-danger btn-lg rounded-pill px-4">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?= BASEURL; ?>/auth" class="btn btn-danger btn-lg rounded-pill px-4">
                                <i class="bi bi-house me-2"></i>Beranda
                            </a>
                        <?php endif; ?>
                        <button onclick="location.reload()" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                            <i class="bi bi-arrow-clockwise me-2"></i>Coba Lagi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
