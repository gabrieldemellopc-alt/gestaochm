(function () {
    'use strict';

    var storageKey = 'chm-theme';
    var allowedThemes = ['dark', 'corporate-light'];
    var root = document.documentElement;

    function isAllowedTheme(theme) {
        return allowedThemes.indexOf(theme) !== -1;
    }

    function getCurrentTheme() {
        var theme = root.getAttribute('data-chm-theme');

        return isAllowedTheme(theme) ? theme : 'dark';
    }

    function saveTheme(theme) {
        try {
            localStorage.setItem(storageKey, theme);
        } catch (error) {
            // Keep the selected theme for this page when storage is unavailable.
        }
    }

    function updateToggle(toggle, theme) {
        var isCorporateLight = theme === 'corporate-light';
        var label = toggle.querySelector('[data-chm-theme-label]');
        var actionLabel = isCorporateLight
            ? 'Ativar modo escuro'
            : 'Ativar modo claro corporativo';

        toggle.setAttribute('aria-label', actionLabel);
        toggle.setAttribute('title', actionLabel);
        toggle.setAttribute('aria-pressed', isCorporateLight ? 'true' : 'false');

        if (label) {
            label.textContent = isCorporateLight ? 'Claro' : 'Escuro';
        }
    }

    function applyTheme(theme, persist) {
        var validTheme = isAllowedTheme(theme) ? theme : 'dark';

        root.setAttribute('data-chm-theme', validTheme);

        document.querySelectorAll('[data-chm-theme-toggle]').forEach(function (toggle) {
            updateToggle(toggle, validTheme);
        });

        if (persist) {
            saveTheme(validTheme);
        }
    }

    function initializeThemeToggle() {
        applyTheme(getCurrentTheme(), false);

        document.querySelectorAll('[data-chm-theme-toggle]').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var nextTheme = getCurrentTheme() === 'dark'
                    ? 'corporate-light'
                    : 'dark';

                applyTheme(nextTheme, true);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeThemeToggle);
    } else {
        initializeThemeToggle();
    }
})();
