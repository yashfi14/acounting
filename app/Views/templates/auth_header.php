<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f8fafc" id="themeColorMeta">
    <title>Acounting | <?= $data['judul']; ?></title>
    <!-- Terapkan tema sebelum render untuk mencegah kedipan warna (FOUC) -->
    <script>
    (function () {
        try {
            var key = 'acounting-theme';
            var saved = localStorage.getItem(key);
            var theme = (saved === 'dark' || saved === 'light')
                ? saved
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.style.colorScheme = theme;
        } catch (e) { /* fallback ke tema terang */ }
    })();
    </script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASEURL; ?>/css/style.css?v=<?= time(); ?>" rel="stylesheet">
    <script src="<?= BASEURL; ?>/js/theme.js?v=<?= time(); ?>" defer></script>
</head>
<body class="auth-body">
    <button type="button" class="theme-toggle theme-toggle-floating" data-theme-toggle aria-label="Ubah tema" title="Ubah tema">
        <i class="bi bi-moon-stars-fill"></i>
    </button>
