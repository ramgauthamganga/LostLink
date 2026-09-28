/**
 * ==========================================================================
 * LOSTLINK GLOBAL THEME SYSTEM (theme.js)
 * ==========================================================================
 * This file encapsulates the shared theme logic for all pages.
 * It is highly recommended to load this script in the <head> of the document
 * to prevent the Flash of Unstyled Content (FOUC).
 */
(function() {
    const THEME_KEY = 'theme';
    const VALID_THEMES = ['light', 'dark'];
    
    // Safety check for localStorage
    const getSavedTheme = () => {
        try {
            const saved = localStorage.getItem(THEME_KEY);
            return VALID_THEMES.includes(saved) ? saved : null;
        } catch (e) {
            console.warn('LostLink Theme: Cannot read from localStorage', e);
            return null;
        }
    };
    
    const saveTheme = (theme) => {
        try {
            if (VALID_THEMES.includes(theme)) {
                localStorage.setItem(THEME_KEY, theme);
            }
        } catch (e) {
            console.warn('LostLink Theme: Cannot write to localStorage', e);
        }
    };

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
    };

    const getSystemPreference = () => {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    // Initialize Theme immediately to prevent FOUC
    const initTheme = () => {
        const savedTheme = getSavedTheme();
        if (savedTheme) {
            applyTheme(savedTheme);
        } else {
            applyTheme(getSystemPreference());
        }
    };

    const toggleTheme = () => {
        const currentTheme = document.documentElement.getAttribute('data-theme') || getSystemPreference();
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        applyTheme(newTheme);
        saveTheme(newTheme);
    };

    initTheme();

    // Bind to DOM when ready to ensure toggle buttons work globally
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggles = document.querySelectorAll('.theme-toggle');
        themeToggles.forEach(toggle => {
            toggle.addEventListener('click', toggleTheme);
        });

        // Listen for system theme changes in real-time
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!getSavedTheme()) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    });
})();
