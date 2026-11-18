        </div> <!-- /.container-fluid -->
    </div> <!-- /#page-content-wrapper -->
</div> <!-- /#wrapper -->

<!-- Floating AI Chat Box Widget -->
<?php require_once __DIR__ . '/ai_chatbox.php'; ?>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Script -->
<script>
(function () {
    'use strict';

    var wrapper = document.getElementById('wrapper');
    var menuToggle = document.getElementById('menu-toggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    var mobileQuery = window.matchMedia('(max-width: 767px)');

    function isMobile() {
        return mobileQuery.matches;
    }

    function setSidebar(open) {
        if (!wrapper) { return; }
        if (isMobile()) {
            wrapper.classList.toggle('toggled', open);
        } else {
            wrapper.classList.toggle('toggled', !open);
        }
        if (backdrop) {
            backdrop.classList.toggle('active', open && isMobile());
        }
        document.body.classList.toggle('sidebar-open', open && isMobile());
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            var isOpen = isMobile()
                ? wrapper.classList.contains('toggled')
                : !wrapper.classList.contains('toggled');
            setSidebar(!isOpen);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setSidebar(false);
        });
    }

    // Tutup sidebar mobile setelah memilih menu.
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) { setSidebar(false); }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isMobile()) { setSidebar(false); }
    });

    // Sinkronkan warna address bar browser dengan tema aktif.
    function syncThemeMeta(theme) {
        var meta = document.getElementById('themeColorMeta');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#0f172a' : '#f8fafc');
        }
    }
    document.addEventListener('aca:themechange', function (event) {
        syncThemeMeta(event.detail && event.detail.theme === 'dark' ? 'dark' : 'light');
    });
    if (window.AcaTheme) {
        syncThemeMeta(window.AcaTheme.current());
    }
})();
</script>
</body>
</html>
