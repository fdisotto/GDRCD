/**
 * GDRCD Theme Toggle
 * Toggles the `dark` class on <html> and persists user preference
 * in localStorage under the key `gdrcd_theme`.
 *
 * The initial theme is applied via an inline script in the layout
 * BEFORE this script runs, to avoid the flash of incorrect theme.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'gdrcd_theme';

    function getCurrentTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            // localStorage may be disabled; ignore.
        }
        updateButton(theme);
    }

    function updateButton(theme) {
        var btn = document.getElementById('gdrcd-theme-toggle');
        if (!btn) return;
        var nextLabel = (theme === 'dark')
            ? 'Passa al tema chiaro'
            : 'Passa al tema scuro';
        btn.setAttribute('aria-label', nextLabel);
        btn.setAttribute('title', nextLabel);
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }

    function init() {
        var btn = document.getElementById('gdrcd-theme-toggle');
        if (!btn) return;
        updateButton(getCurrentTheme());
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var next = getCurrentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
