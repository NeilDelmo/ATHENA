function readWorkspaceLocations(storage, key) {
    try {
        const value = JSON.parse(storage.getItem(key) || '{}');
        return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    } catch { return {}; }
}

export function workspacePageState(section) {
    function context() {
        if (typeof document === 'undefined' || typeof window === 'undefined') return null;
        const user = document.querySelector('[data-auth-user-id]')?.dataset?.authUserId;
        const workspace = document.querySelector('[data-active-workspace]')?.dataset?.activeWorkspace;
        return user && workspace ? { key: `athena-workspace-locations:${user}`, page: `${workspace}:${window.location.pathname}` } : null;
    }
    return {
        read() {
            const current = context();
            try {
                const page = readWorkspaceLocations(sessionStorage, current?.key).pages?.[current?.page];
                return page?.savedAt > Date.now() - 86400000 ? page[section] : null;
            } catch { return null; }
        },
        write(value) {
            const current = context();
            if (!current || JSON.stringify(value).length > 3000) return;
            try {
                const stored = readWorkspaceLocations(sessionStorage, current.key);
                const pages = Object.entries(stored.pages || {}).filter(([, page]) => page.savedAt > Date.now() - 86400000);
                const previous = stored.pages?.[current.page] || {};
                const next = { ...previous, [section]: value, savedAt: Date.now() };
                stored.pages = Object.fromEntries([...pages.filter(([key]) => key !== current.page).slice(-19), [current.page, next]]);
                sessionStorage.setItem(current.key, JSON.stringify(stored));
            } catch { /* The page remains usable without browser storage. */ }
        },
    };
}

export function initializeWorkspaceSwitching(navigate, doc = document, browser = window) {
    let pending = null;
    let checkingAccount = null;
    const locationPrefix = 'athena-workspace-locations:';

    function checkAccount() {
        const shell = doc.querySelector('[data-auth-user-id][data-session-check-url]');
        if (!shell?.dataset.authUserId) return Promise.resolve(null);
        if (checkingAccount) return checkingAccount;
        let destination;
        try { destination = new URL(shell.dataset.sessionCheckUrl, browser.location.href); } catch { return Promise.resolve(null); }
        if (destination.origin !== browser.location.origin) return Promise.resolve(null);
        checkingAccount = browser.fetch(destination.href, {
            credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
            signal: browser.AbortSignal.timeout(10000),
        }).then(async response => {
            if (response.status === 401 || response.redirected) {
                browser.location.replace(destination.href);
                return false;
            }
            if (!response.ok) return null;
            const current = await response.json();
            if (!Number.isInteger(current?.user_id) || (current.workspace !== null && typeof current.workspace !== 'string')) return null;
            if (String(current.user_id) !== shell.dataset.authUserId
                || (shell.hasAttribute('data-active-workspace') && (current.workspace || '') !== shell.dataset.activeWorkspace)) {
                browser.location.replace(destination.href);
                return false;
            }
            return true;
        }).catch(() => null).finally(() => { checkingAccount = null; });
        return checkingAccount;
    }

    function recheckVisiblePage() {
        if (doc.visibilityState === 'visible') void checkAccount();
    }

    function recheckRestoredPage(event) {
        if (!event.persisted) return;
        doc.documentElement.style.visibility = 'hidden';
        void checkAccount().then(current => {
            if (current !== false) browser.location.reload();
        });
    }

    function workspaceLocations() {
        const userId = doc.querySelector('[data-auth-user-id]')?.dataset.authUserId;
        try {
            if (!userId) {
                Object.keys(browser.sessionStorage).filter(item => item.startsWith(locationPrefix)).forEach(item => browser.sessionStorage.removeItem(item));
                return null;
            }
            const key = locationPrefix + userId;
            Object.keys(browser.sessionStorage).filter(item => item.startsWith(locationPrefix) && item !== key)
                .forEach(item => browser.sessionStorage.removeItem(item));
            return { key, values: readWorkspaceLocations(browser.sessionStorage, key) };
        } catch { return null; }
    }

    function rememberLocation() {
        const workspace = doc.querySelector('[data-active-workspace]')?.dataset.activeWorkspace;
        const stored = workspaceLocations();
        if (!workspace || !stored || doc.querySelector('[data-revision-embedded]')) return;
        stored.values[workspace] = { url: browser.location.href, savedAt: Date.now() };
        try { browser.sessionStorage.setItem(stored.key, JSON.stringify(stored.values)); } catch { /* Server page history remains available. */ }
    }

    function resumeUrl(workspace) {
        const saved = workspaceLocations()?.values[workspace];
        if (!saved || saved.savedAt < Date.now() - 86400000) return null;
        try {
            const url = new URL(saved.url, browser.location.href);
            return ['http:', 'https:'].includes(url.protocol) && url.origin === browser.location.origin ? url.href : null;
        } catch { return null; }
    }

    function showMessage(selector, message, destination = null) {
        const status = selector.querySelector('[data-workspace-switch-status]');
        const link = selector.querySelector('[data-workspace-switch-continue]');
        selector.querySelector('[data-workspace-switch-message]').textContent = message;
        status.hidden = false;
        link.hidden = !destination;
        if (destination) link.href = destination;
    }

    function reset() {
        if (!pending) return;
        const openedWorkspace = pending.destination && !pending.selector.isConnected;
        browser.clearTimeout(pending.timer);
        pending.selector.removeAttribute('aria-busy');
        pending.controls.forEach(({ element, disabled }) => { element.disabled = disabled; });
        pending.button.textContent = pending.label;
        pending = null;
        if (openedWorkspace) doc.dispatchEvent(new browser.CustomEvent('athena:workspace-switched'));
    }

    async function submit(event) {
        const form = event.target.closest?.('form[data-workspace-switch]');
        if (!form || event.defaultPrevented || typeof navigate !== 'function') return;
        event.preventDefault();
        if (pending) return;
        if (await checkAccount() === false || pending) return;

        const selector = form.closest('[data-workspace-selector]');
        const button = event.submitter || form.querySelector('button[type="submit"]');
        const data = new browser.FormData(form);
        const workspace = data.get('workspace') || (data.get('role') === 'research_coordinator' ? 'research_office' : data.get('role'));
        const resume = resumeUrl(workspace);
        if (resume) data.set('resume_url', resume);
        const controls = [...selector.querySelectorAll('button[type="submit"]')]
            .map((element) => ({ element, disabled: element.disabled }));
        pending = { selector, button, controls, label: button.textContent, timer: null, destination: null };
        const operation = pending;
        controls.forEach(({ element }) => { element.disabled = true; });
        selector.setAttribute('aria-busy', 'true');
        button.textContent = 'Opening workspace…';
        showMessage(selector, 'Opening your workspace…');

        try {
            const response = await browser.fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
                signal: browser.AbortSignal.timeout(30000),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = [401, 419].includes(response.status)
                    ? 'Your session has expired. Refresh this page and sign in again.'
                    : Object.values(payload.errors || {}).flat()[0] || payload.message || 'Could not switch workspaces. Please try again.';
                throw new Error(message);
            }

            const destination = new URL(payload.redirect, browser.location.href);
            if (!payload.redirect || destination.origin !== browser.location.origin) {
                throw new Error('Could not open the workspace. Please try again.');
            }
            if (pending !== operation || !selector.isConnected) return;

            operation.destination = destination.href;
            operation.timer = browser.setTimeout(() => {
                selector.removeAttribute('aria-busy');
                showMessage(selector, 'Your workspace is selected. Open it to continue.', destination.href);
            }, 15000);
            navigate(destination.href);
        } catch (error) {
            if (pending !== operation || !selector.isConnected) return;
            showMessage(selector, error.name === 'TimeoutError' || error.name === 'TypeError'
                ? 'Could not reach ATHENA. Please try again.'
                : error.message || 'Could not switch workspaces. Please try again.');
            reset();
        }
    }

    function guardNavigation(event) {
        if (pending && event.detail.url.toString() !== pending.destination) {
            event.preventDefault();
            return;
        }

        if (event.detail.history) {
            event.preventDefault();
            browser.location.replace(event.detail.url.toString());
        }
    }

    function clearOnLogout(event) {
        if (!event.defaultPrevented && event.target instanceof browser.HTMLFormElement && new URL(event.target.action, browser.location.href).pathname.endsWith('/logout')) {
            try { Object.keys(browser.sessionStorage).filter(item => item.startsWith(locationPrefix)).forEach(item => browser.sessionStorage.removeItem(item)); } catch { /* Browser storage is optional. */ }
        }
    }

    doc.addEventListener('submit', submit);
    doc.addEventListener('submit', clearOnLogout);
    doc.addEventListener('livewire:navigate', guardNavigation);
    doc.addEventListener('livewire:navigated', reset);
    doc.addEventListener('livewire:navigating', rememberLocation);
    doc.addEventListener('livewire:navigated', rememberLocation);
    browser.addEventListener('hashchange', rememberLocation);
    browser.addEventListener('popstate', rememberLocation);
    browser.addEventListener('focus', checkAccount);
    browser.addEventListener('pageshow', recheckRestoredPage);
    doc.addEventListener('visibilitychange', recheckVisiblePage);
    rememberLocation();

    return () => {
        reset();
        doc.removeEventListener('submit', submit);
        doc.removeEventListener('submit', clearOnLogout);
        doc.removeEventListener('livewire:navigate', guardNavigation);
        doc.removeEventListener('livewire:navigated', reset);
        doc.removeEventListener('livewire:navigating', rememberLocation);
        doc.removeEventListener('livewire:navigated', rememberLocation);
        browser.removeEventListener('hashchange', rememberLocation);
        browser.removeEventListener('popstate', rememberLocation);
        browser.removeEventListener('focus', checkAccount);
        browser.removeEventListener('pageshow', recheckRestoredPage);
        doc.removeEventListener('visibilitychange', recheckVisiblePage);
    };
}
