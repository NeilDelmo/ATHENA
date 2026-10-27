const prefix = 'athena-proposal-recovery:';
const accountKey = 'athena-proposal-recovery-account';
const maximumLength = 1000000;
const lifetime = 86400000;
let cleanupRecovery = () => {};

export function validProposalRecoveryRecord(record, now = Date.now()) {
    return Boolean(record) && Number.isFinite(record.savedAt) && record.savedAt >= now - lifetime && record.savedAt <= now
        && Number.isSafeInteger(record.loadedVersion) && record.loadedVersion >= 0
        && Array.isArray(record.fields) && record.fields.length <= 2000
        && record.fields.every(field => typeof field.name === 'string' && typeof field.label === 'string'
            && ['text', 'multiple'].includes(field.type)
            && (field.type === 'text' ? typeof field.value === 'string'
                : Array.isArray(field.value) && field.value.every(value => typeof value === 'string'))
            && (field.rowIdentity === undefined || typeof field.rowIdentity === 'string'))
        && JSON.stringify(record).length <= maximumLength;
}

export async function freshProposalRecoveryVersion(url, version, request = fetch, expectedScope = null) {
    const response = await request(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok || response.redirected) throw new Error('The latest saved version could not be checked.');
    const state = await response.json();
    if (!Number.isSafeInteger(state.version) || state.version < 0) throw new Error('The latest saved version could not be checked.');
    return { current: state.version === version && !state.is_removed && state.is_editable !== false
        && (expectedScope === null || (state.scope === expectedScope && state.is_editable === true)), state };
}

function editableFields(form) {
    return [...form.querySelectorAll('input, textarea, select')].filter(field => field.name && !field.disabled && !field.readOnly
        && !['password', 'file', 'submit', 'button', 'reset'].includes(field.type)
        && (field.type !== 'hidden' || field.parentElement.querySelector('[role="combobox"]')));
}

function fieldValues(form) {
    const values = new Map();
    const rowIdentities = new Map();
    for (const control of form.querySelectorAll('input, textarea, select')) {
        const group = control.name?.match(/^[^[]+\[\d+\]/)?.[0];
        if (!group || ['file', 'password', 'submit', 'button'].includes(control.type)) continue;
        if (!rowIdentities.has(group)) rowIdentities.set(group, []);
        rowIdentities.get(group).push([control.name.slice(group.length), control.type === 'checkbox' || control.type === 'radio' ? control.checked : control.value]);
    }
    for (const field of editableFields(form)) {
        const grouped = ['checkbox', 'radio', 'select-multiple'].includes(field.type);
        const value = grouped ? (field.type === 'select-multiple' ? [...field.selectedOptions].map(option => option.value)
            : field.checked ? [field.value] : []) : field.value;
        if (grouped && values.has(field.name)) values.get(field.name).value.push(...value);
        else values.set(field.name, {
            name: field.name, value, type: grouped ? 'multiple' : 'text',
            richText: Boolean(field._semanticEditor),
            rowIdentity: JSON.stringify(rowIdentities.get(field.name.match(/^[^[]+\[\d+\]/)?.[0]) || null),
            label: [field.closest('[data-repeatable-entry], [data-cv-person-id]')?.querySelector('h4, h3')?.textContent?.trim(),
                field.labels?.[0]?.textContent?.trim() || field.getAttribute('aria-label') || field.name].filter(Boolean).join(' · '),
        });
    }
    return values;
}

function sameValue(first, second) { return JSON.stringify(first) === JSON.stringify(second); }

export default function initializeProposalConflictRecovery({ resumeSave = () => {} } = {}) {
    cleanupRecovery();
    const cleanups = [];
    let storage;
    try { storage = window.localStorage; } catch {
        for (const host of document.querySelectorAll('[data-proposal-conflict-recovery]')) {
            host.hidden = false;
            host.textContent = 'This browser can’t keep a copy of your changes. Keep this page open and copy your work before leaving.';
            host.className = 'mt-3 text-sm leading-6 text-red-800 dark:text-red-200';
        }
        return () => {};
    }
    const userId = document.querySelector('[data-auth-user-id]')?.dataset.authUserId;
    let sessionActive = Boolean(userId);
    function clearPrivateCopies(target = storage) {
        try { Object.keys(target).filter(key => key.startsWith(prefix)).forEach(key => target.removeItem(key)); } catch { /* Keep editing available when browser storage is disabled. */ }
    }
    try {
        for (const key of Object.keys(storage).filter(key => key.startsWith(prefix))) {
            let copy;
            try { copy = JSON.parse(storage.getItem(key) || 'null'); } catch { /* Invalid copies are removed below. */ }
            if (!userId || !key.startsWith(`${prefix}${userId}:`) || !validProposalRecoveryRecord(copy)) storage.removeItem(key);
        }
        if (userId) storage.setItem(accountKey, userId);
        else storage.removeItem(accountKey);
        // Preserve copies created before recovery persisted across browser restarts.
        for (const key of Object.keys(window.sessionStorage).filter(key => key.startsWith(prefix))) {
            let copy;
            try { copy = JSON.parse(window.sessionStorage.getItem(key) || 'null'); } catch { /* Invalid copies are removed below. */ }
            if (userId && key.startsWith(`${prefix}${userId}:`) && validProposalRecoveryRecord(copy) && !storage.getItem(key)) storage.setItem(key, JSON.stringify(copy));
            window.sessionStorage.removeItem(key);
        }
    } catch { /* Per-editor writes report unavailable storage without interrupting editing. */ }

    for (const monitor of document.querySelectorAll('[data-proposal-version-monitor], [data-report-version-monitor]')) {
        const report = monitor.hasAttribute('data-report-version-monitor');
        const root = monitor.closest('[data-paper-editor], [data-monitoring-paper-workspace]');
        if (root?.hasAttribute('x-ignore')) continue;
        const form = root?.querySelector('[data-paper-form], [data-monitoring-tool-autosave-form], [data-narrative-progress-autosave-form]');
        const host = monitor.querySelector('[data-proposal-conflict-recovery]');
        const warning = monitor.querySelector('[data-proposal-stale-warning]');
        if (!userId || !form || !host || !warning || !monitor.dataset.stateUrl) continue;
        let scope = new URL(monitor.dataset.stateUrl, location.href);
        if (scope.origin !== location.origin) continue;
        const revision = new URL(location.href).searchParams.get('revision_embed') === '1' ? 'embedded' : 'normal';
        const scopeKey = `${prefix}${userId}:${scope.pathname}:${revision}`;
        const pageId = crypto.randomUUID();
        // Keep each open editor's unsynced copy separate so another tab cannot replace it.
        let liveKey = `${scopeKey}:${pageId}`;
        let key = liveKey;
        const loadedVersion = Number(monitor.dataset.loadedVersion || 0);
        let baseline = fieldValues(form);
        let record = null;
        let liveRecord = null;
        let restoredCopies = [];
        let storageFailed = false;
        let needsCheck = report || navigator.onLine === false;
        let verifiedVersion = null;
        let checking = null;
        let disposed = false;
        const discardedFiles = new WeakSet();
        try {
            restoredCopies = Object.keys(storage).filter(item => item === scopeKey || item.startsWith(`${scopeKey}:`))
                .map(item => ({ key: item, record: JSON.parse(storage.getItem(item)) }))
                .filter(copy => validProposalRecoveryRecord(copy.record)).sort((first, second) => second.record.savedAt - first.record.savedAt);
            if (restoredCopies.length) {
                ({ key, record } = restoredCopies[0]);
                needsCheck = true;
            }
        } catch { storageFailed = true; }
        const currentVersion = () => Number(form.querySelector('[name="document_version"], [name="draft_version"]')?.value || loadedVersion);

        function syncReportPeriod() {
            const date = form.querySelector('[name="reporting_date"]')?.value;
            if (!report || !/^\d{4}-\d{2}-\d{2}$/.test(date || '')) return;
            const path = scope.pathname.replace(/\/(monitoring|progress)\/[^/]+\/(\d+)\/edit-state$/, `/$1/${date}/$2/edit-state`);
            if (path === scope.pathname) return;
            scope = new URL(path, scope.origin);
            liveKey = `${prefix}${userId}:${path}:${revision}:${pageId}`;
            try {
                restoredCopies = Object.keys(storage).filter(item => item.startsWith(`${prefix}${userId}:${path}:${revision}:`))
                    .map(item => ({ key: item, record: JSON.parse(storage.getItem(item)) }))
                    .filter(copy => validProposalRecoveryRecord(copy.record)).sort((first, second) => second.record.savedAt - first.record.savedAt);
            } catch { restoredCopies = []; storageFailed = true; }
            liveRecord = null;
            ({ key, record } = restoredCopies[0] || { key: liveKey, record: null });
            baseline = fieldValues(form);
            needsCheck = true;
            verifiedVersion = null;
        }

        function persist(targetRecord = record, targetKey = key) {
            try {
                if (!sessionActive) return;
                if (targetRecord && (targetRecord.fields.length || targetRecord.hasFiles)) storage.setItem(targetKey, JSON.stringify(targetRecord));
                else storage.removeItem(targetKey);
                storageFailed = false;
            } catch { storageFailed = true; }
        }

        function element(tag, text, classes = '') {
            const node = document.createElement(tag);
            if (text) node.textContent = text;
            node.className = classes;
            return node;
        }

        function localStatus(message) {
            const status = root.querySelector('[data-proposal-autosave-message]');
            if (status) status.textContent = message;
            const indicator = root.querySelector('[data-proposal-autosave-indicator]');
            if (indicator) {
                indicator.classList.remove('bg-green-600', 'bg-gray-400', 'bg-amber-500');
                indicator.classList.add('bg-red-600');
            }
        }

        function recoveryControls() {
            const controls = element('div', '', 'flex shrink-0 flex-wrap items-center gap-3');
            if (needsCheck && warning.hidden) {
                const retry = element('button', key === liveKey ? 'Try saving again' : 'Check saved version', 'min-h-11 rounded-lg bg-red-900 px-4 py-2 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700');
                retry.type = 'button';
                retry.addEventListener('click', async () => {
                    retry.disabled = true;
                    if (await verify(true)) { render(); if (key === liveKey) resumeSave(root); }
                    retry.disabled = false;
                });
                controls.append(retry);
            }
            const discard = element('button', 'Delete browser copy', 'min-h-11 rounded px-1 text-xs text-slate-500 underline decoration-slate-300 underline-offset-4 transition-colors hover:text-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:text-slate-400 dark:decoration-slate-600 dark:hover:text-red-200');
            discard.type = 'button';
            discard.addEventListener('click', () => {
                try { storage.removeItem(key); } catch { storageFailed = true; render(); return; }
                restoredCopies = restoredCopies.filter(copy => copy.key !== key);
                if (key === liveKey) {
                    liveRecord = null;
                    baseline = fieldValues(form);
                    for (const field of form.querySelectorAll('input[type="file"]')) for (const file of field.files || []) discardedFiles.add(file);
                }
                ({ key, record } = restoredCopies[0] || { key: liveKey, record: liveRecord });
                render();
            });
            controls.append(discard);
            return controls;
        }

        function render(message = '') {
            host.replaceChildren();
            host.hidden = !sessionActive || (!record?.fields.length && !record?.hasFiles && !storageFailed);
            if (host.hidden) return;
            host.className = 'mt-3 space-y-3 border-t border-slate-200 py-3 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200';
            const summary = element('div', '', 'flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-6');
            const description = element('div', '', 'min-w-0 space-y-1');
            description.append(element('p', storageFailed ? 'This browser couldn’t save your changes. Keep this page open and copy your work before leaving.'
                : 'Not saved to ATHENA yet', storageFailed ? 'font-medium text-red-800 dark:text-red-200' : 'font-medium'));
            if (!storageFailed) description.append(element('p', 'Your changes are kept in this browser for 24 hours.', 'text-xs leading-5 text-slate-500 dark:text-slate-400'));
            summary.append(description);
            host.append(summary);
            if (!record) return;
            if (restoredCopies.length > 1) {
                const label = element('label', 'Choose a saved copy', 'block font-semibold');
                const select = element('select', '', 'mt-1 block max-w-full rounded-lg border-slate-300 text-sm dark:bg-slate-800');
                select.id = `proposal-recovery-copy-${liveKey.split(':').at(-1)}`;
                label.htmlFor = select.id;
                for (const copy of restoredCopies) {
                    const option = element('option', new Date(copy.record.savedAt).toLocaleString());
                    option.value = copy.key;
                    option.selected = copy.key === key;
                    select.append(option);
                }
                select.addEventListener('change', () => { ({ key, record } = restoredCopies.find(copy => copy.key === select.value)); render(); });
                host.append(label, select);
            }
            if (record.hasFiles) host.append(element('p', 'Select image and attachment files again. This browser only keeps your text and choices.', 'text-xs leading-5'));
            const controls = recoveryControls();
            if (key === liveKey) {
                if (needsCheck) description.append(element('p', 'Keep editing. We’ll check the latest version before saving.', 'text-xs leading-5 text-slate-500 dark:text-slate-400'));
                summary.append(controls);
                return;
            }
            const current = fieldValues(form);
            const isLatest = !needsCheck && verifiedVersion === currentVersion() && warning.hidden;
            if (!isLatest) {
                host.append(element('p', warning.hidden ? 'Check what’s saved in ATHENA before restoring your changes.'
                    : 'Load the latest saved work below to compare it with your changes.', 'text-xs leading-5'));
                host.append(controls);
                return;
            }
            host.append(element('p', 'Choose which changes to restore. Anything you leave unchecked will stay as it is.', 'leading-6'));
            const selections = [];
            for (const field of record.fields) {
                const comparison = current.get(field.name);
                const sameRow = comparison && (!field.rowIdentity || comparison.rowIdentity === field.rowIdentity);
                const row = element('div', '', 'space-y-2 border-t border-slate-200 pt-3 dark:border-slate-700');
                const label = element('label', '', 'flex items-center gap-2 font-semibold');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'rounded border-slate-300 text-red-800 focus:ring-red-700';
                checkbox.disabled = !sameRow || comparison.type !== field.type;
                label.append(checkbox, document.createTextNode(field.label));
                row.append(label);
                if (comparison && !sameRow) row.append(element('p', 'This row changed or moved. Copy your text into the correct row.', 'text-xs leading-5 text-red-800 dark:text-red-200'));
                const columns = element('div', '', 'grid min-w-0 gap-3 sm:grid-cols-2');
                for (const [title, value] of [[`Latest on this page${comparison ? ` · ${comparison.label}` : ''}`, comparison?.value ?? 'This field or row no longer exists. Add the row first, or copy your text below.'], ['Your changes', field.value]]) {
                    const column = element('div', '', 'min-w-0');
                    column.append(element('p', title, 'mb-1 text-xs font-semibold text-slate-500 dark:text-slate-400'));
                    const writtenText = field.richText && typeof value === 'string' ? new DOMParser().parseFromString(value, 'text/html').body.textContent : value;
                    const text = element('p', Array.isArray(writtenText) ? writtenText.join(', ') || '(No choices)' : String(writtenText || '(Empty)'), 'max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm leading-6 dark:bg-slate-800');
                    column.append(text);
                    columns.append(column);
                }
                row.append(columns);
                host.append(row);
                selections.push({ checkbox, field, comparedValue: comparison?.value, comparedRowIdentity: comparison?.rowIdentity });
            }
            const apply = element('button', 'Apply selected changes', 'min-h-11 rounded-lg bg-red-900 px-4 py-2 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700');
            apply.type = 'button';
            apply.addEventListener('click', async () => {
                apply.disabled = true;
                if (!await verify(true)) { apply.disabled = false; return; }
                let skipped = 0;
                let applied = 0;
                const beforeApplying = fieldValues(form);
                for (const { checkbox, field, comparedValue, comparedRowIdentity } of selections) {
                    if (!checkbox.checked) continue;
                    const now = beforeApplying.get(field.name);
                    if (!now || !sameValue(now.value, comparedValue) || now.rowIdentity !== comparedRowIdentity) { skipped++; continue; }
                    for (const control of editableFields(form).filter(control => control.name === field.name)) {
                        if (['checkbox', 'radio'].includes(control.type)) control.checked = field.value.includes(control.value);
                        else if (control.type === 'select-multiple') [...control.options].forEach(option => { option.selected = field.value.includes(option.value); });
                        else control.value = String(field.value);
                        control.dispatchEvent(new Event('input', { bubbles: true }));
                        control.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    field.applied = true;
                    applied++;
                }
                if (applied) root.dataset.paperDirty = 'true';
                persist();
                capture(false);
                render(`${applied} field${applied === 1 ? '' : 's'} restored. ${skipped ? `${skipped} field${skipped === 1 ? ' was' : 's were'} left alone because you edited it after opening this comparison.` : 'These changes will save with your draft.'}`);
            });
            controls.prepend(apply);
            host.append(controls);
            if (message) {
                const status = element('p', message, 'text-sm leading-6');
                status.setAttribute('role', 'status');
                host.append(status);
            }
        }

        function capture(updateDisplay = true) {
            if (!sessionActive || root.dataset.paperDiscarding === 'true') return;
            syncReportPeriod();
            const fields = [...fieldValues(form).values()].filter(field => !sameValue(field.value, baseline.get(field.name)?.value))
                .map(field => ({ ...field, rowIdentity: baseline.get(field.name)?.rowIdentity || field.rowIdentity }));
            const candidate = { savedAt: Date.now(), loadedVersion: currentVersion(), fields, hasFiles: [...form.querySelectorAll('input[type="file"]')].some(field => [...(field.files || [])].some(file => !discardedFiles.has(file))) };
            if (!validProposalRecoveryRecord(candidate)) {
                storageFailed = true;
                render();
                return;
            }
            const displayChanged = Boolean(liveRecord?.fields.length || liveRecord?.hasFiles) !== Boolean(fields.length || candidate.hasFiles);
            liveRecord = candidate;
            if (!restoredCopies.length) { record = liveRecord; key = liveKey; }
            persist(liveRecord, liveKey);
            if (updateDisplay && (storageFailed || (displayChanged && !restoredCopies.length))) render();
        }

        async function verify(force = false) {
            if (!sessionActive || disposed) return false;
            syncReportPeriod();
            if (!force && !needsCheck) return true;
            if (checking) return checking;
            checking = Promise.resolve().then(async () => {
                try {
                    if (navigator.onLine === false) throw new Error('Offline');
                    syncReportPeriod();
                    const version = currentVersion();
                    const checkedScope = scope.href;
                    const { current, state } = await freshProposalRecoveryVersion(checkedScope, version, fetch, report ? monitor.dataset.stateScope : null);
                    if (!sessionActive || disposed || currentVersion() !== version || scope.href !== checkedScope) return false;
                    if (!current) {
                        if (warning.hidden) warning.hidden = false;
                        const message = monitor.querySelector('[data-proposal-stale-message]');
                        if (message) message.textContent = state.is_editable === false ? 'This report is no longer editable. Load the latest saved work to check its status.'
                            : state.is_removed ? 'This paper was removed. Load the latest saved work before continuing.' : 'The saved version or report scope has changed. Load the latest saved work before continuing.';
                        const alpine = window.Alpine?.$data?.(root);
                        if (alpine) alpine.autoSaveBlocked = true;
                        localStatus('The saved version changed. Your changes are still in this browser.');
                        render();
                        return false;
                    }
                    verifiedVersion = version;
                    needsCheck = false;
                    return true;
                } catch {
                    needsCheck = true;
                    capture(false);
                    localStatus(storageFailed ? 'Could not save locally. Keep this page open and copy your entries.' : 'Saved locally only. Connect and check the latest saved version before saving to ATHENA.');
                    render();
                    return false;
                } finally { checking = null; }
            });
            return checking;
        }

        const context = {
            beforeSave: () => verify(),
            connectionFailed: () => { needsCheck = true; capture(); render(); },
            conflict: () => { needsCheck = true; warning.hidden = false; capture(); render(); },
        };
        root._proposalRecovery = context;
        const changed = () => queueMicrotask(() => capture());
        root.addEventListener('input', changed);
        root.addEventListener('change', changed);
        const beforeReload = event => { if (event.target.closest('[data-proposal-load-latest]')) capture(); };
        root.addEventListener('click', beforeReload, true);
        const pagehide = () => capture(false);
        const offline = () => {
            needsCheck = true;
            capture();
            render();
            if (record) localStatus(storageFailed ? 'Could not save locally. Keep this page open and copy your entries.' : 'Saved locally only. Waiting for a connection.');
        };
        const online = async () => {
            needsCheck = true;
            if (await verify(true)) {
                render();
                if (liveRecord?.fields.length && root.dataset.paperDirty === 'true') resumeSave(root);
            }
        };
        window.addEventListener('pagehide', pagehide);
        window.addEventListener('offline', offline);
        window.addEventListener('online', online);
        const observer = new MutationObserver(records => {
            if (records.some(item => item.target === warning && !warning.hidden)) { needsCheck = true; capture(); render(); }
            if (records.some(item => item.target === root && item.oldValue === 'true' && root.dataset.paperDirty === 'false')
                && root.dataset.paperDiscarding !== 'true' && root.dataset.paperSubmitting !== 'true') {
                const current = fieldValues(form);
                for (const copy of [...restoredCopies, ...(liveRecord ? [{ key: liveKey, record: liveRecord }] : [])]) {
                    copy.record.fields = copy.record.fields.filter(field => !sameValue(current.get(field.name)?.value, field.value)
                        || (field.rowIdentity && field.rowIdentity !== 'null' && !field.applied && current.get(field.name)?.rowIdentity !== field.rowIdentity));
                    if ([...form.querySelectorAll('input[type="file"]')].some(field => field.files?.length)) copy.record.hasFiles = false;
                    persist(copy.record, copy.key);
                }
                restoredCopies = restoredCopies.filter(copy => copy.record.fields.length || copy.record.hasFiles);
                if (liveRecord && !liveRecord.fields.length && !liveRecord.hasFiles) liveRecord = null;
                ({ key, record } = restoredCopies.find(copy => copy.key === key) || restoredCopies[0] || { key: liveKey, record: liveRecord });
                baseline = current;
                render();
            }
        });
        observer.observe(warning, { attributes: true, attributeFilter: ['hidden'] });
        observer.observe(root, { attributes: true, attributeFilter: ['data-paper-dirty'], attributeOldValue: true });
        render();
        if (restoredCopies.length) void verify(true).then(() => { if (!disposed) render(); });
        cleanups.push(() => {
            capture(false);
            disposed = true;
            delete root._proposalRecovery;
            observer.disconnect();
            root.removeEventListener('input', changed);
            root.removeEventListener('change', changed);
            root.removeEventListener('click', beforeReload, true);
            window.removeEventListener('pagehide', pagehide);
            window.removeEventListener('offline', offline);
            window.removeEventListener('online', online);
        });
    }

    const logout = event => {
        const form = event.target;
        if (!event.defaultPrevented && form instanceof HTMLFormElement && new URL(form.action, location.href).pathname.endsWith('/logout')) {
            sessionActive = false;
            clearPrivateCopies();
            try { clearPrivateCopies(window.sessionStorage); storage.removeItem(accountKey); } catch { /* Storage may have been disabled. */ }
            for (const host of document.querySelectorAll('[data-proposal-conflict-recovery]')) { host.replaceChildren(); host.hidden = true; }
        }
    };
    const accountChanged = event => {
        if (event.key === accountKey && event.newValue !== userId) {
            sessionActive = false;
            for (const host of document.querySelectorAll('[data-proposal-conflict-recovery]')) { host.replaceChildren(); host.hidden = true; }
        }
    };
    document.addEventListener('submit', logout);
    window.addEventListener('storage', accountChanged);
    cleanups.push(() => { document.removeEventListener('submit', logout); window.removeEventListener('storage', accountChanged); });
    cleanupRecovery = () => cleanups.forEach(cleanup => cleanup());
    return cleanupRecovery;
}
