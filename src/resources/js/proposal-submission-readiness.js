let disposeReadiness = () => {};
let cueSequence = 0;

export function safeProposalReadinessUrl(value, base = window.location.href) {
    if (typeof value !== 'string' || !value.trim()) return null;
    try {
        const url = new URL(value, base);
        return ['http:', 'https:'].includes(url.protocol) && url.origin === new URL(base).origin ? url.href : null;
    } catch {
        return null;
    }
}

function fieldTarget(root, field) {
    if (!field || field === '__paper__') return null;
    const name = field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
    const fields = [...root.querySelectorAll('input:not([type="hidden"]), textarea, select')];
    const control = fields.find(element => element.name === name)
        || fields.find(element => element.name.startsWith(`${name}[`));
    if (control) return control._semanticEditor || control;
    const identified = document.getElementById(field);
    if (identified && root.contains(identified) && identified.type !== 'hidden') return identified;
    if (field.startsWith('methodology.specific_methods') || field.startsWith('specific_method_objectives')) {
        return root.querySelector('#methodology-specific-methods');
    }
    return null;
}

async function revealRequirement(root, blocker) {
    const state = window.Alpine?.$data(root);
    const [collection, index] = String(blocker.field || '').split('.');
    if (collection === 'entries' && state?.entries?.[index]) state.expandedEntryId = state.entries[index].id;
    if (collection === 'items' && state?.items?.[index]) state.expandedItemId = state.items[index].id;
    if (collection === 'people' && state?.people?.[index]) {
        if (typeof state.focusPerson === 'function') state.focusPerson(Number(index), { focus: false });
        else state.activeCvPersonId = state.people[index].id;
    }
    const focusBeforeHelper = document.activeElement;
    const hasFocusHelper = typeof state?.focusProposalRequirement === 'function';
    if (hasFocusHelper) state.focusProposalRequirement(blocker.field || '__paper__');
    await window.Alpine?.nextTick();
    await new Promise(resolve => requestAnimationFrame(resolve));
    let target = fieldTarget(root, blocker.field);
    if ((!target || !target.getClientRects().length) && hasFocusHelper && document.activeElement !== focusBeforeHelper
        && root.contains(document.activeElement) && document.activeElement !== root) {
        target = document.activeElement;
    }
    if (!target && !blocker.field) {
        target = [...root.querySelectorAll('input:not([type="hidden"]), textarea, select')].find(element => !element.disabled);
    }
    if (!target) return null;
    for (let ancestor = target.parentElement; ancestor && root.contains(ancestor); ancestor = ancestor.parentElement) {
        if (ancestor.tagName === 'DETAILS') ancestor.open = true;
    }
    await window.Alpine?.nextTick();
    if (target.disabled || !target.getClientRects().length) target = target.closest('fieldset, section, [data-revision-section]');
    if (!target || !target.getClientRects().length) return null;
    if (!target.matches('input, textarea, select, button, [contenteditable]')) target.setAttribute('tabindex', '-1');
    target.scrollIntoView({ block: 'center', behavior: 'instant' });
    target.focus({ preventScroll: true });
    return target;
}

export default function initProposalSubmissionReadiness({
    beforeContinue = async () => true,
    navigate = url => window.location.assign(url),
} = {}) {
    disposeReadiness();
    const controller = new AbortController();
    const editors = [...document.querySelectorAll('[data-proposal-paper][data-proposal-readiness-url]')];
    const cleanups = [];
    const cues = new Map();

    function clearCues(root) {
        for (const { target, cue, invalid, describedBy } of cues.get(root) || []) {
            if (invalid === null) target.removeAttribute('aria-invalid');
            else target.setAttribute('aria-invalid', invalid);
            if (describedBy === null) target.removeAttribute('aria-describedby');
            else target.setAttribute('aria-describedby', describedBy);
            cue.remove();
        }
        cues.delete(root);
    }

    function renderCues(root, data, focusedBlocker = null, focusedTarget = null) {
        clearCues(root);
        if (!data.can_guide) return;
        const rendered = [];
        for (const blocker of data.blockers || []) {
            if (blocker.owner !== 'faculty' || blocker.paper !== root.dataset.proposalPaper) continue;
            const target = blocker === focusedBlocker ? focusedTarget : fieldTarget(root, blocker.field);
            if (!target) continue;
            const existing = rendered.find(item => item.target === target);
            if (existing) {
                existing.cue.textContent += ` ${blocker.message}`;
                continue;
            }
            const cue = document.createElement('p');
            cue.id = `proposal-readiness-cue-${++cueSequence}`;
            cue.dataset.proposalReadinessCue = '';
            cue.className = 'mt-2 text-sm leading-6 text-red-800 dark:text-red-200';
            cue.textContent = blocker.message;
            const invalid = target.getAttribute('aria-invalid');
            const describedBy = target.getAttribute('aria-describedby');
            target.setAttribute('aria-invalid', 'true');
            target.setAttribute('aria-describedby', [describedBy, cue.id].filter(Boolean).join(' '));
            target.insertAdjacentElement('afterend', cue);
            rendered.push({ target, cue, invalid, describedBy });
        }
        cues.set(root, rendered);
    }

    function render(data, url) {
        for (const panel of document.querySelectorAll('[data-proposal-readiness]')) {
            if (safeProposalReadinessUrl(panel.dataset.proposalReadinessUrl) !== url) continue;
            const label = panel.querySelector('[data-proposal-readiness-label]');
            if (label) label.textContent = {
                incomplete: 'Required information needs attention', office: 'Awaiting Research Office setup',
                prepare: 'Content complete · PDFs need preparation', ready: 'Ready to turn in',
            }[data.status] || 'Review submission requirements';
            const list = panel.querySelector('[data-proposal-readiness-issues]');
            if (list) {
                list.replaceChildren(...(data.blockers || []).map(blocker => {
                    const item = document.createElement('li');
                    item.textContent = blocker.owner === 'research_office' ? `Research Office action required: ${blocker.message}` : blocker.message;
                    return item;
                }));
                list.hidden = !data.blockers?.length;
            }
        }
        for (const root of editors) {
            if (safeProposalReadinessUrl(root.dataset.proposalReadinessUrl) === url) renderCues(root, data);
        }
    }

    function renderError(url) {
        for (const panel of document.querySelectorAll('[data-proposal-readiness]')) {
            if (safeProposalReadinessUrl(panel.dataset.proposalReadinessUrl) !== url) continue;
            const label = panel.querySelector('[data-proposal-readiness-label]');
            if (label) label.textContent = 'Could not check submission readiness. You can still review the requirements.';
        }
    }

    async function refresh(url) {
        if (!url) throw new Error('Submission readiness URL is unavailable.');
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
        if (!response.ok) throw new Error('Submission readiness is unavailable.');
        const data = await response.json();
        if (controller.signal.aborted) return null;
        render(data, url);
        window.dispatchEvent(new CustomEvent('proposal-readiness-updated', { detail: { data, url, source: 'readiness' } }));
        return data;
    }

    async function refreshMountedReview() {
        const review = document.querySelector('[data-proposal-review-package]');
        const id = review?.getAttribute('wire:id');
        if (!id) return;
        await window.Livewire?.find(id)?.$refresh();
    }

    for (const panel of document.querySelectorAll('[data-proposal-readiness]')) {
        if (!panel.dataset.proposalReadinessState) continue;
        try {
            render(JSON.parse(panel.dataset.proposalReadinessState), safeProposalReadinessUrl(panel.dataset.proposalReadinessUrl));
        } catch {
            renderError(safeProposalReadinessUrl(panel.dataset.proposalReadinessUrl));
        }
    }

    for (const button of document.querySelectorAll('[data-proposal-readiness-continue]')) {
        let pending = false;
        const proceed = async event => {
            event.preventDefault();
            if (pending) return;
            pending = true;
            const disabled = button.disabled;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const url = safeProposalReadinessUrl(button.dataset.url || button.closest('[data-proposal-readiness-url]')?.dataset.proposalReadinessUrl);
            try {
                if (!await beforeContinue()) return;
                const data = await refresh(url);
                if (!data) return;
                const blocker = data.can_guide && data.blockers?.find(item => item.owner === 'faculty');
                const destination = blocker && safeProposalReadinessUrl(blocker.url);
                if (destination) navigate(destination);
                else {
                    await refreshMountedReview();
                    if (!controller.signal.aborted) window.dispatchEvent(new CustomEvent('open-modal', { detail: 'proposal-review' }));
                }
            } catch {
                if (controller.signal.aborted) return;
                renderError(url);
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'proposal-review' }));
            } finally {
                pending = false;
                button.disabled = disabled;
                button.removeAttribute('aria-busy');
            }
        };
        button.addEventListener('click', proceed);
        cleanups.push(() => button.removeEventListener('click', proceed));
    }

    const requestedField = new URL(window.location.href).searchParams.get('proposal_requirement');
    for (const root of editors) {
        const url = safeProposalReadinessUrl(root.dataset.proposalReadinessUrl);
        if (requestedField) {
            refresh(url).then(async data => {
                if (!data?.can_guide) return;
                let blockers = data.blockers?.filter(item => item.owner === 'faculty' && item.paper === root.dataset.proposalPaper) || [];
                let blocker = blockers.find(item => item.field === requestedField) || blockers[0];
                if (blocker?.reason === 'save_confirmation') {
                    if (!await beforeContinue({ recheckCompletion: true })) return;
                    data = await refresh(url);
                    if (!data?.can_guide) return;
                    blockers = data.blockers?.filter(item => item.owner === 'faculty' && item.paper === root.dataset.proposalPaper) || [];
                    blocker = blockers.find(item => item.field === requestedField) || blockers[0];
                }
                if (!blocker) return;
                const target = await revealRequirement(root, blocker);
                if (!controller.signal.aborted) renderCues(root, data, blocker, target);
            }).catch(() => { if (!controller.signal.aborted) renderError(url); });
        }
        const observer = new MutationObserver(records => {
            if (!records.some(record => record.oldValue === 'true' && record.target.dataset.paperDirty === 'false')) return;
            refresh(url).catch(() => { if (!controller.signal.aborted) renderError(url); });
        });
        observer.observe(root, { attributes: true, subtree: true, attributeFilter: ['data-paper-dirty'], attributeOldValue: true });
        cleanups.push(() => { observer.disconnect(); clearCues(root); });
    }

    disposeReadiness = () => {
        controller.abort();
        cleanups.forEach(cleanup => cleanup());
    };
    const onReadiness = event => {
        if (event.detail?.source === 'pdf') render(event.detail.data, event.detail.url);
    };
    window.addEventListener('proposal-readiness-updated', onReadiness);
    cleanups.push(() => window.removeEventListener('proposal-readiness-updated', onReadiness));
    return disposeReadiness;
}
