import { safeProposalReadinessUrl } from './proposal-submission-readiness.js';

let dispose = () => {};

export default function initializeProposalPdfPreparation() {
    dispose();
    const abort = new AbortController();
    const timers = new Map();
    const roots = [...document.querySelectorAll('[data-proposal-pdf-preparation]')];
    const pending = new Map();
    const pollingStatuses = ['queued', 'preparing', 'retrying', 'stalled', 'unavailable'];

    function render(root, state) {
        if (!root.isConnected) return;
        const message = root.querySelector('[data-pdf-preparation-message]');
        const count = root.querySelector('[data-pdf-preparation-count]');
        const retry = root.querySelector('[data-pdf-preparation-retry]');
        root.hidden = state.status !== 'failed';
        if (message) message.textContent = state.status === 'failed'
            ? "We couldn't prepare your submission documents. Your work is saved."
            : state.message;
        if (count) count.textContent = `${Number(state.prepared) || 0} / ${Number(state.total) || 7} PDFs`;
        if (retry) {
            retry.hidden = state.status !== 'failed';
            retry.textContent = 'Try again';
        }
        root.dataset.pdfState = JSON.stringify(state);
    }

    function later(root, callback, delay = 3000) {
        const url = safeProposalReadinessUrl(root.dataset.stateUrl);
        clearTimeout(timers.get(url));
        timers.set(url, setTimeout(() => { timers.delete(url); void callback(); }, delay));
    }

    async function update(root, start = false, retry = false) {
        const url = safeProposalReadinessUrl(root.dataset.stateUrl);
        const prepare = safeProposalReadinessUrl(root.dataset.prepareUrl);
        if (!url || pending.has(url) || abort.signal.aborted || !root.isConnected || (document.hidden && !(start && prepare))) return;
        pending.set(url, true);
        try {
            if (start && prepare) {
                const response = await fetch(prepare, {
                    method: 'POST', credentials: 'same-origin', signal: abort.signal,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ retry }),
                });
                if (!response.ok) throw new Error('PDF preparation is temporarily unavailable. Your content is saved. Reconnecting automatically…');
                const data = await response.json();
                if (data.preparation) {
                    for (const panel of document.querySelectorAll('[data-proposal-pdf-preparation]')) {
                        if (safeProposalReadinessUrl(panel.dataset.stateUrl) === url) render(panel, data.preparation);
                    }
                }
            } else {
                const response = await fetch(url, { credentials: 'same-origin', signal: abort.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('PDF status is temporarily unavailable. Your content is saved. Reconnecting automatically…');
                const data = await response.json();
                if (!data.preparation) return;
                for (const panel of document.querySelectorAll('[data-proposal-pdf-preparation]')) {
                    if (safeProposalReadinessUrl(panel.dataset.stateUrl) === url) render(panel, data.preparation);
                }
                window.dispatchEvent(new CustomEvent('proposal-readiness-updated', { detail: { data, url, source: 'pdf' } }));
                const review = root.closest('[data-proposal-review-package]') || document.querySelector('[data-proposal-review-package]');
                if (data.preparation.status === 'ready' && review) {
                    const state = review.querySelector('[data-proposal-readiness-state]');
                    if (state && JSON.parse(state.dataset.proposalReadinessState).status !== 'ready') {
                        await window.Livewire?.find(review.getAttribute('wire:id'))?.$refresh();
                    }
                }
            }
            const state = JSON.parse(root.dataset.pdfState);
            if (root.isConnected && pollingStatuses.includes(state.status)) later(root, () => update(root));
            else if (state.status === 'needed' && prepare) later(root, () => update(root, true));
        } catch (error) {
            if (!abort.signal.aborted) {
                for (const panel of document.querySelectorAll('[data-proposal-pdf-preparation]')) {
                    if (safeProposalReadinessUrl(panel.dataset.stateUrl) === url) {
                        const previous = JSON.parse(panel.dataset.pdfState || '{}');
                        render(panel, { ...previous, status: 'unavailable', message: 'PDF status is temporarily unavailable. Your content is saved. Reconnecting automatically…' });
                    }
                }
                if (root.isConnected) later(root, () => update(root), 15000);
            }
        } finally {
            pending.delete(url);
        }
    }

    for (const root of roots) {
        try {
            const state = JSON.parse(root.dataset.pdfState);
            render(root, state);
            if (state.status === 'needed' && root.dataset.prepareUrl) void update(root, true);
            else if (pollingStatuses.includes(state.status)) later(root, () => update(root));
        } catch { /* Saved content remains available if status is unavailable. */ }
    }
    const onReadiness = event => {
        if (event.detail?.source === 'pdf') return;
        for (const root of document.querySelectorAll('[data-proposal-pdf-preparation]')) {
            if (safeProposalReadinessUrl(root.dataset.stateUrl) !== event.detail?.url || !event.detail.data?.preparation) continue;
            render(root, event.detail.data.preparation);
            if (event.detail.data.preparation.status === 'needed' && root.dataset.prepareUrl) void update(root, true);
            else if (pollingStatuses.includes(event.detail.data.preparation.status)) later(root, () => update(root));
        }
    };
    window.addEventListener('proposal-readiness-updated', onReadiness);
    const onRetry = event => {
        const button = event.target.closest('[data-pdf-preparation-retry]');
        const root = button?.closest('[data-proposal-pdf-preparation]');
        if (root) void update(root, true, true);
    };
    document.addEventListener('click', onRetry);
    const onOnline = () => {
        for (const root of document.querySelectorAll('[data-proposal-pdf-preparation]')) void update(root);
    };
    window.addEventListener('online', onOnline);
    const onVisibility = () => {
        if (document.hidden) return;
        for (const root of document.querySelectorAll('[data-proposal-pdf-preparation]')) {
            try {
                if (pollingStatuses.includes(JSON.parse(root.dataset.pdfState).status)) void update(root);
            } catch { /* Saved content remains available if status is unavailable. */ }
        }
    };
    document.addEventListener('visibilitychange', onVisibility);
    dispose = () => {
        abort.abort();
        timers.forEach(clearTimeout);
        window.removeEventListener('proposal-readiness-updated', onReadiness);
        document.removeEventListener('click', onRetry);
        window.removeEventListener('online', onOnline);
        document.removeEventListener('visibilitychange', onVisibility);
    };
    return dispose;
}
