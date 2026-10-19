let dispose = () => {};

export default function initializeResearchManualChecks() {
    dispose();
    const abort = new AbortController();
    let timer;
    let panel = document.querySelector('[data-manual-checks]');
    if (!panel || !['pending', 'running'].includes(panel.dataset.checkStatus)) return;
    const url = new URL(panel.dataset.statusUrl, window.location.href);
    if (url.origin !== window.location.origin) return;

    async function update() {
        if (!panel.isConnected || abort.signal.aborted) return;
        let delay = 4000;
        try {
            const response = await fetch(url.href, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: abort.signal });
            if (!response.ok) throw new Error('Results unavailable');
            const data = await response.json();
            const next = new DOMParser().parseFromString(data.html, 'text/html').querySelector('[data-manual-checks]');
            if (!next) throw new Error('Results unavailable');
            for (const detail of panel.querySelectorAll('details[open]')) {
                next.querySelector(`details[data-check-number="${detail.dataset.checkNumber}"]`)?.setAttribute('open', '');
            }
            if (!panel.isEqualNode(next)) {
                const focused = panel.contains(document.activeElement) ? document.activeElement : null;
                const focusedCheck = focused?.closest('details[data-check-number]');
                const nextCheck = focusedCheck && [...next.querySelectorAll('details[data-check-number]')]
                    .find(detail => detail.dataset.checkNumber === focusedCheck.dataset.checkNumber);
                const sourceUrl = focused?.getAttribute('href');
                const nextFocus = nextCheck && ([...nextCheck.querySelectorAll('a[href]')]
                    .find(link => sourceUrl && link.getAttribute('href') === sourceUrl) || nextCheck.querySelector('summary'));
                panel.replaceWith(next);
                panel = next;
                nextFocus?.focus({ preventScroll: true });
            }
            if (!['pending', 'running'].includes(data.status)) return;
        } catch {
            if (abort.signal.aborted) return;
            const error = panel.querySelector('[data-manual-checks-poll-error]');
            if (error) error.hidden = false;
            delay = 15000;
        }
        timer = setTimeout(update, delay);
    }

    timer = setTimeout(update, 4000);
    dispose = () => { abort.abort(); clearTimeout(timer); };
    return dispose;
}
