let disposePrevious = () => {};

export default function initializeTopicLiveStatus() {
    disposePrevious();
    const host = document.querySelector('[data-topic-live-status]');
    if (!host) return () => {};
    let url;
    let loaded;
    try {
        url = new URL(host.dataset.statusUrl, location.href);
        loaded = JSON.parse(host.dataset.liveSnapshot);
    } catch { return () => {}; }
    if (url.origin !== location.origin) return () => {};
    let previous = loaded;
    let stopped = false;
    let controller = null;
    const notice = host.querySelector('[data-live-status-notice]');
    const message = host.querySelector('[data-live-status-message]');
    const actions = host.querySelector('[data-live-status-actions]');

    for (const element of host.querySelectorAll('form, [data-live-quarter-kind] a')) {
        if (element.tagName === 'FORM' && (element.getAttribute('method') || 'GET').toUpperCase() === 'GET') continue;
        const report = element.closest('[data-live-report-key]');
        element.dataset.liveActionKey ||= report?.dataset.liveReportKey || 'proposal';
        element.dataset.liveActionScope ||= report?.dataset.liveReportScope || loaded.proposal;
    }
    for (const cell of host.querySelectorAll('[data-live-quarter-kind]')) {
        for (const badge of cell.querySelectorAll('.report-review-status')) {
            if (!cell.dataset.liveReportKey) continue;
            badge.dataset.liveReportStatus = cell.dataset.liveReportKey;
            badge.dataset.compact = 'true';
        }
    }

    function actionLink(action) {
        let target;
        try { target = new URL(action.url, location.href); } catch { return null; }
        if (target.origin !== location.origin || typeof action.label !== 'string') return null;
        const link = document.createElement('a');
        link.href = target.href;
        link.textContent = action.label;
        link.className = 'inline-flex min-h-11 items-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-800 hover:bg-red-50 dark:border-red-800 dark:bg-slate-900 dark:text-red-200';
        return link;
    }

    function disableAction(element) {
        element.dataset.liveStale = 'true';
        for (const button of element.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"], input[type="image"]')) button.disabled = true;
        if (element.tagName === 'A') {
            element.removeAttribute('href');
            element.setAttribute('aria-disabled', 'true');
            element.tabIndex = -1;
        }
    }

    function showNotice(text, latestActions = []) {
        if (!notice || !message || !actions) return;
        const focusedUrl = actions.contains(document.activeElement) ? document.activeElement.href : null;
        message.textContent = text;
        actions.replaceChildren();
        const seen = new Set();
        for (const action of latestActions) {
            const link = actionLink(action);
            if (!link || seen.has(link.href)) continue;
            seen.add(link.href);
            actions.append(link);
        }
        notice.hidden = false;
        if (focusedUrl) [...actions.children].find(link => link.href === focusedUrl)?.focus({ preventScroll: true });
    }

    function invalidatePage(text) {
        stopped = true;
        for (const element of host.querySelectorAll('[data-live-action-key]')) disableAction(element);
        showNotice(text);
    }

    async function refresh() {
        if (stopped || controller || document.hidden || !host.isConnected) return;
        controller = new AbortController();
        const timeout = window.setTimeout(() => controller?.abort(), 15000);
        try {
            const response = await fetch(url.href, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
            if ([401, 403, 404].includes(response.status) || response.redirected) {
                invalidatePage('Access to this page changed. Your entries are still here. Reopen the project in your current workspace before submitting.');
                return;
            }
            if (!response.ok) return;
            const state = await response.json();
            if (stopped || !host.isConnected || !state.proposal || typeof state.proposal.scope !== 'string' || typeof state.proposal.label !== 'string' || !state.reports) return;
            if (state.workspace !== host.dataset.workspace || (host.dataset.accountId && String(state.account_id) !== host.dataset.accountId)) {
                invalidatePage('Your account or workspace changed. Your entries are still here. Reopen the project before submitting.');
                return;
            }
            const scopes = { proposal: state.proposal.scope, reports: Object.fromEntries(Object.entries(state.reports).map(([key, record]) => [key, record.scope])) };
            const changed = scopes.proposal !== previous.proposal || [...new Set([...Object.keys(scopes.reports), ...Object.keys(previous.reports || {})])]
                .some(key => scopes.reports[key] !== previous.reports?.[key]);
            for (const badge of document.querySelectorAll('[data-live-proposal-status]')) {
                if (scopes.proposal === previous.proposal) continue;
                badge.textContent = state.proposal.label;
                badge.hidden = false;
                if (typeof state.proposal.class === 'string') badge.className = state.proposal.class;
            }
            for (const cell of host.querySelectorAll('[data-live-quarter-kind]')) {
                const latest = Object.entries(state.reports).filter(([, record]) => record.kind === cell.dataset.liveQuarterKind
                    && String(record.quarter) === cell.dataset.liveQuarter).at(-1);
                if (!latest || (latest[0] === cell.dataset.liveReportKey && latest[1].scope === previous.reports?.[latest[0]])) continue;
                const [key, record] = latest;
                const controls = cell.querySelector('[data-report-controls]');
                if (!controls) continue;
                const badge = document.createElement('span');
                badge.className = 'report-review-status';
                badge.dataset.liveReportStatus = key;
                badge.dataset.compact = 'true';
                const link = actionLink({ url: record.url, label: 'View' });
                if (!link) continue;
                link.className = 'monitoring-report-control quarterly-report-primary';
                link.dataset.liveActionKey = key;
                link.dataset.liveActionScope = record.scope;
                link.setAttribute('aria-label', `View latest ${record.kind === 'monitoring' ? 'Monitoring Tool' : 'Progress Report'}`);
                link.innerHTML = '<span>View</span><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
                link.append(badge);
                controls.replaceChildren(link);
                cell.dataset.liveReportKey = key;
                cell.dataset.liveReportScope = record.scope;
            }
            for (const badge of host.querySelectorAll('[data-live-report-status]')) {
                const record = state.reports[badge.dataset.liveReportStatus];
                if (record && record.scope === previous.reports?.[badge.dataset.liveReportStatus] && badge.hasChildNodes()) continue;
                if (!record) {
                    badge.textContent = 'No longer available in this workspace';
                    badge.dataset.status = 'unavailable';
                    continue;
                }
                if (badge.dataset.liveReportStyle === 'plain') {
                    const label = badge.querySelector('[data-live-report-label]');
                    if (label) label.textContent = record.label;
                    const indicator = badge.querySelector('[data-live-report-indicator]');
                    if (indicator) {
                        indicator.classList.remove('bg-emerald-500', 'bg-red-500', 'bg-amber-400');
                        indicator.classList.add(record.status === 'reviewed' ? 'bg-emerald-500' : record.status === 'revision_requested' ? 'bg-red-500' : 'bg-amber-400');
                    }
                } else {
                    const template = document.createElement('template');
                    template.innerHTML = badge.dataset.compact === 'true' ? record.compact_html : record.html;
                    const replacement = template.content.firstElementChild;
                    if (replacement?.tagName === 'SPAN') badge.replaceChildren(...replacement.childNodes);
                }
                badge.dataset.status = record.status;
                badge.dataset.overdue = 'false';
                badge.title = record.label;
            }
            for (const element of host.querySelectorAll('[data-live-action-key]')) {
                const key = element.dataset.liveActionKey;
                const record = key === 'proposal' ? state.proposal : state.reports[key];
                if (!record || record.current === false || record.scope !== element.dataset.liveActionScope) disableAction(element);
            }
            if (changed) {
                const currentActions = [...(state.proposal.actions || []), ...Object.values(state.reports).flatMap(record => record.actions || [])];
                showNotice('Status updated. Previous actions may be closed. Your current entries are still here; open the latest action to continue.', currentActions);
            }
            previous = scopes;
        } catch {
            // Keep the last known state and unsaved fields during temporary connection failures.
        } finally {
            window.clearTimeout(timeout);
            controller = null;
        }
    }

    const preventStaleSubmit = event => {
        if (!event.target.closest('[data-live-stale="true"]')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
    };
    const refreshVisible = () => { if (!document.hidden) void refresh(); };
    const refreshNotification = event => {
        if (!event.detail?.workspace || event.detail.workspace === host.dataset.workspace) void refresh();
    };
    host.addEventListener('submit', preventStaleSubmit, true);
    document.addEventListener('visibilitychange', refreshVisible);
    window.addEventListener('focus', refreshVisible);
    window.addEventListener('online', refreshVisible);
    window.addEventListener('athena-workspace-actions', refreshNotification);
    const timer = window.setInterval(refreshVisible, 30000);
    void refresh();
    disposePrevious = () => {
        stopped = true;
        controller?.abort();
        window.clearInterval(timer);
        host.removeEventListener('submit', preventStaleSubmit, true);
        document.removeEventListener('visibilitychange', refreshVisible);
        window.removeEventListener('focus', refreshVisible);
        window.removeEventListener('online', refreshVisible);
        window.removeEventListener('athena-workspace-actions', refreshNotification);
    };
    return disposePrevious;
}
