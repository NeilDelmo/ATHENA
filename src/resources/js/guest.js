const themeStorageKey = 'athena-theme';
const legacyThemeKeys = ['athena-auth-theme', 'theme'];
const themeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

function storedThemePreference() {
    try {
        const theme = localStorage.getItem(themeStorageKey);
        return ['dark', 'light'].includes(theme) ? theme : null;
    } catch {
        return null;
    }
}

function clearLegacyThemePreferences() {
    try {
        legacyThemeKeys.forEach((key) => localStorage.removeItem(key));
    } catch {
        // Local storage may be blocked; theme still follows the system default.
    }
}

function applyTheme(theme = storedThemePreference() ?? (themeMediaQuery.matches ? 'dark' : 'light')) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.style.colorScheme = theme;

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
        toggle.setAttribute('title', theme === 'dark' ? 'Use light theme' : 'Use dark theme');
    });
}

function initializeThemeToggles() {
    clearLegacyThemePreferences();
    applyTheme();

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        if (toggle.dataset.themeToggleReady === 'true') return;

        toggle.dataset.themeToggleReady = 'true';
        toggle.addEventListener('click', () => {
            const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

            try {
                localStorage.setItem(themeStorageKey, nextTheme);
            } catch {
                // The visible theme can still switch even when it cannot persist.
            }

            clearLegacyThemePreferences();
            applyTheme(nextTheme);
        });
    });
}

themeMediaQuery.addEventListener('change', () => {
    if (!storedThemePreference()) applyTheme();
});

initializeThemeToggles();
document.addEventListener('livewire:navigated', initializeThemeToggles);
