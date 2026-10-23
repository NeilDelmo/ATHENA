export default function registerLazyAlpineComponent(Alpine, name, load) {
    let registered = false;
    let loading;
    const expression = new RegExp(`^${name}(?:\\s*\\(|$)`);

    Alpine.interceptInit(Alpine.skipDuringClone((root, skip) => {
        if (registered || root.hasAttribute('x-ignore') || !expression.test((root.getAttribute('x-data') || '').trim())) return;

        const wasInert = root.inert;
        let cancelled = false;
        const notice = document.createElement('p');
        notice.setAttribute('role', 'status');
        notice.className = 'my-4 text-sm text-slate-600 dark:text-slate-300';
        notice.textContent = 'Loading editor…';
        const blockSubmission = event => { event.preventDefault(); event.stopImmediatePropagation(); };
        const restore = () => Alpine.mutateDom(() => {
            document.removeEventListener('livewire:navigating', cancel);
            root.removeEventListener('submit', blockSubmission, true);
            Alpine.destroyTree(root);
            root.removeAttribute('x-ignore');
            root.removeAttribute('aria-busy');
            root.inert = wasInert;
            notice.remove();
        });
        const cancel = () => { cancelled = true; restore(); };

        Alpine.mutateDom(() => {
            root.setAttribute('x-ignore', '');
            root.setAttribute('aria-busy', 'true');
            root.inert = true;
            root.before(notice);
        });
        root.addEventListener('submit', blockSubmission, true);
        document.addEventListener('livewire:navigating', cancel);
        skip();

        loading ??= Promise.resolve().then(load).then(() => { registered = true; }).catch(error => {
            loading = undefined;
            throw error;
        });
        loading.then(() => {
            if (cancelled) return;
            restore();
            if (root.isConnected) {
                Alpine.mutateDom(() => Alpine.initTree(root));
                Alpine.nextTick(() => {
                    if (root.isConnected) root.dispatchEvent(new CustomEvent('athena:lazy-component-ready', { bubbles: true }));
                });
            }
        }).catch(() => {
            if (cancelled) return;
            if (!root.isConnected) return restore();
            notice.setAttribute('role', 'alert');
            notice.textContent = 'The editor could not load. ';
            const reload = document.createElement('button');
            reload.type = 'button';
            reload.className = 'underline';
            reload.textContent = 'Reload editor';
            reload.addEventListener('click', () => window.location.reload());
            notice.append(reload);
        });
    }));
}
