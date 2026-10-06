export function initializeWorkspaceSwitching(navigate, doc = document, browser = window) {
    let pending = null;
    let workspaceChanged = false;

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

        const selector = form.closest('[data-workspace-selector]');
        const button = event.submitter || form.querySelector('button[type="submit"]');
        const data = new browser.FormData(form);
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

            workspaceChanged = true;
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

        if (workspaceChanged && event.detail.history) {
            event.preventDefault();
            browser.location.replace(event.detail.url.toString());
        }
    }

    doc.addEventListener('submit', submit);
    doc.addEventListener('livewire:navigate', guardNavigation);
    doc.addEventListener('livewire:navigated', reset);

    return () => {
        reset();
        doc.removeEventListener('submit', submit);
        doc.removeEventListener('livewire:navigate', guardNavigation);
        doc.removeEventListener('livewire:navigated', reset);
    };
}
