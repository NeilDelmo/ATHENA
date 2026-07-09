<script>
    (() => {
        const storageKey = 'athena-theme';
        const legacyKeys = ['athena-auth-theme', 'theme'];

        const storedTheme = (() => {
            try {
                const theme = localStorage.getItem(storageKey);
                return ['dark', 'light'].includes(theme) ? theme : null;
            } catch {
                return null;
            }
        })();

        try {
            legacyKeys.forEach((key) => localStorage.removeItem(key));
        } catch {
            // Local storage can be unavailable in privacy-restricted browsers.
        }

        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const resolvedTheme = storedTheme ?? (prefersDark ? 'dark' : 'light');

        document.documentElement.classList.toggle('dark', resolvedTheme === 'dark');
        document.documentElement.style.colorScheme = resolvedTheme;
    })();
</script>
