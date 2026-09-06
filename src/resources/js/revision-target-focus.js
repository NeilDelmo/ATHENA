export function findRevisionTarget(documentRoot, targetId) {
    if (!targetId || !/^[a-zA-Z0-9_-]+$/.test(targetId)) return null;
    return documentRoot.getElementById(targetId)
        || documentRoot.querySelector?.(`[data-revision-section~="${targetId}"]`)
        || null;
}

function targetLabel(target, targetId) {
    const escapedId = window.CSS?.escape ? window.CSS.escape(targetId) : targetId;
    const explicitLabel = document.querySelector(`label[for="${escapedId}"]`);

    if (explicitLabel?.textContent?.trim()) return explicitLabel.textContent.trim();

    return target.getAttribute('aria-label')
        || target.querySelector('h3, h4, summary, label')?.textContent?.trim()
        || 'the requested field';
}

function revealTarget(target) {
    let details = target.closest('details');
    while (details) {
        details.setAttribute('open', '');
        details = details.parentElement?.closest('details');
    }
}

function visualTarget(target) {
    if (target instanceof HTMLTextAreaElement && target._semanticEditor instanceof HTMLElement) {
        return target._semanticEditor;
    }

    return target;
}

export function focusRevisionTarget(target, targetId, { withinDocument = false, comment = null, label = null } = {}) {
    revealTarget(target);
    if (targetId.startsWith('section-')) target.classList.add('revision-section-revealed');

    const visual = visualTarget(target);
    if (!(visual instanceof HTMLElement) || visual.offsetParent === null || visual.matches(':disabled')) return false;
    const highlight = visual !== target && visual.parentElement instanceof HTMLElement ? visual.parentElement : visual;

    const context = document.querySelector('[data-revision-context]');
    const previousCue = document.querySelector('[data-revision-target-cue]');
    if (previousCue && previousCue !== context) previousCue.remove();
    document.querySelectorAll('.revision-target-active').forEach((element) => {
        element.classList.remove('revision-target-active');
    });

    const cue = context || document.createElement('div');
    cue.id ||= 'proposal-revision-target-cue';
    cue.dataset.revisionTargetCue = 'true';
    cue.classList.add('revision-target-cue');
    cue.setAttribute('role', 'status');
    if (!context) {
        cue.innerHTML = '<strong>Research Head revision target</strong><span></span>';
        cue.querySelector('strong').textContent = label || 'Research Head revision comment';
        cue.querySelector('span').textContent = comment || targetLabel(target, targetId);
    }
    context?.querySelector('[data-revision-target-unavailable]')?.setAttribute('hidden', '');
    highlight.before(cue);
    highlight.classList.add('revision-target-active');
    if (targetId.startsWith('section-')) {
        window.clearTimeout(highlight._revisionEmphasisTimer);
        highlight._revisionEmphasisTimer = window.setTimeout(() => highlight.classList.remove('revision-target-active'), 4000);
    }
    const descriptions = new Set((visual.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    descriptions.add(cue.id);
    visual.setAttribute('aria-describedby', [...descriptions].join(' '));

    const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    if (withinDocument) {
        window.scrollTo({ top: cue.getBoundingClientRect().top + window.scrollY - window.innerHeight / 3, behavior });
    } else {
        cue.scrollIntoView({ behavior, block: 'center' });
    }

    window.setTimeout(() => {
        if (!visual.isConnected) return;
        const focusable = visual;
        if (!focusable.hasAttribute('tabindex') && !focusable.matches('input, textarea, select, button')) {
            focusable.setAttribute('tabindex', '-1');
        }
        focusable.focus({ preventScroll: true });
    }, behavior === 'smooth' ? 450 : 0);

    return true;
}

let focusRun = 0;

export default function initializeRevisionTargetFocus() {
    const currentRun = ++focusRun;
    const context = document.querySelector('[data-revision-context]');
    const targetId = context ? context.dataset.revisionTarget : new URLSearchParams(window.location.search).get('revision_target');
    if (!targetId) return;

    if (!/^[a-zA-Z0-9_-]+$/.test(targetId)) return;
    let attempts = 0;

    const tryFocus = () => {
        if (currentRun !== focusRun) return;

        attempts += 1;
        const target = findRevisionTarget(document, targetId);

        if (target instanceof HTMLElement && focusRevisionTarget(target, targetId)) return;
        if (attempts < 12) window.setTimeout(tryFocus, attempts < 5 ? 100 : 250);
        else context?.querySelector('[data-revision-target-unavailable]')?.removeAttribute('hidden');
    };

    window.requestAnimationFrame(tryFocus);
}
