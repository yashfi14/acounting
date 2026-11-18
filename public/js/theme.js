/**
 * AcaTheme — pengelola tema Acounting (light/dark).
 *
 * - Menyimpan preferensi di localStorage.
 * - Menghormati prefers-color-scheme saat pertama kali dibuka.
 * - Menyinkronkan atribut data-theme (CSS internal) dan data-bs-theme
 *   (mode warna native Bootstrap 5.3).
 * - Memancarkan event "aca:themechange" agar grafik/komponen dinamis
 *   dapat menyesuaikan warna.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'acounting-theme';
    var root = document.documentElement;
    var listenersBound = false;

    function systemTheme() {
        try {
            return global.matchMedia && global.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } catch (e) {
            return 'light';
        }
    }

    function storedTheme() {
        try {
            var value = global.localStorage.getItem(STORAGE_KEY);
            return value === 'dark' || value === 'light' ? value : null;
        } catch (e) {
            return null;
        }
    }

    function current() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function updateButtons(theme) {
        var isDark = theme === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            btn.setAttribute('title', isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
            btn.setAttribute('aria-label', btn.getAttribute('title'));
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            }
            var label = btn.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = isDark ? 'Mode Terang' : 'Mode Gelap';
            }
        });
    }

    function apply(theme, persist) {
        theme = theme === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-bs-theme', theme);
        root.style.colorScheme = theme;
        if (persist !== false) {
            try { global.localStorage.setItem(STORAGE_KEY, theme); } catch (e) { /* ignore */ }
        }
        updateButtons(theme);
        try {
            document.dispatchEvent(new CustomEvent('aca:themechange', { detail: { theme: theme } }));
        } catch (e) { /* CustomEvent tidak didukung: abaikan */ }
        return theme;
    }

    function toggle() {
        return apply(current() === 'dark' ? 'light' : 'dark', true);
    }

    function bindButtons() {
        if (listenersBound) { return; }
        listenersBound = true;
        document.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-theme-toggle]');
            if (!btn) { return; }
            event.preventDefault();
            toggle();
        });
    }

    global.AcaTheme = {
        current: current,
        apply: apply,
        toggle: toggle,
        key: STORAGE_KEY
    };

    // Terapkan preferensi tersimpan (atau preferensi sistem) secepat mungkin.
    if (!root.getAttribute('data-theme')) {
        apply(storedTheme() || systemTheme(), false);
    } else {
        apply(current(), false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindButtons();
            updateButtons(current());
        });
    } else {
        bindButtons();
        updateButtons(current());
    }
})(window);
