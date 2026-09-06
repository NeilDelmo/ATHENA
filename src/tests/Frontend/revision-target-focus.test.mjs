import assert from 'node:assert/strict';
import test from 'node:test';
import initializeRevisionTargetFocus, { findRevisionTarget, focusRevisionTarget } from '../../resources/js/revision-target-focus.js';

class Element {
    constructor(tag = 'div') {
        this.tag = tag;
        this.dataset = {};
        this.attributes = new Map();
        this.offsetParent = {};
        this.isConnected = true;
        this.classes = new Set();
        this.classList = { add: (name) => this.classes.add(name), remove: (name) => this.classes.delete(name) };
    }

    closest() { return this.details || null; }
    setAttribute(key, value) { this.attributes.set(key, value); }
    removeAttribute(key) { this.attributes.delete(key); }
    hasAttribute(key) { return this.attributes.has(key); }
    getAttribute(key) { return this.attributes.get(key) || null; }
    matches(selector) { return selector === ':disabled' ? !!this.disabled : ['input', 'textarea', 'select', 'button'].includes(this.tag); }
    querySelector() { return this.child || null; }
    before(cue) { this.cue = cue; }
    scrollIntoView(options) { this.scrollOptions = options; }
    focus(options) { this.focusOptions = options; }
    remove() { this.removed = true; }
}

class Textarea extends Element {
    constructor() { super('textarea'); }
}

function environment(t, { target, context = null, search = '?revision_target=activity-1' } = {}) {
    const original = Object.fromEntries(['HTMLElement', 'HTMLTextAreaElement', 'document', 'window'].map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    t.after(() => {
        for (const [key, descriptor] of Object.entries(original)) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    });
    globalThis.HTMLElement = Element;
    globalThis.HTMLTextAreaElement = Textarea;
    globalThis.document = {
        querySelector: (selector) => selector === '[data-revision-context]' ? context : null,
        querySelectorAll: () => [],
        getElementById: () => target,
        createElement: () => {
            const cue = new Element();
            cue.child = new Element('span');
            return cue;
        },
    };
    globalThis.window = {
        location: { search },
        CSS: { escape: (value) => value },
        matchMedia: () => ({ matches: true }),
        requestAnimationFrame: (callback) => callback(),
        setTimeout: (callback) => callback(),
    };
}

test('rich-text revision links focus the visible editor, not its hidden textarea', (t) => {
    const textarea = new Textarea();
    textarea.offsetParent = null;
    textarea._semanticEditor = new Element();
    textarea._semanticEditor.parentElement = new Element();
    environment(t, { target: textarea });

    assert.equal(focusRevisionTarget(textarea, 'rationale'), true);
    assert.deepEqual(textarea._semanticEditor.focusOptions, { preventScroll: true });
    assert.equal(textarea.focusOptions, undefined);
    assert.equal(textarea._semanticEditor.parentElement.classes.has('revision-target-active'), true);
    assert.equal(textarea._semanticEditor.parentElement.cue.scrollOptions.behavior, 'auto');
    assert.equal(textarea._semanticEditor.getAttribute('aria-describedby'), 'proposal-revision-target-cue');
});

test('revision links open nested sections and retain the reviewer instruction beside the field', (t) => {
    const outer = new Element('details');
    const inner = new Element('details');
    inner.parentElement = { closest: () => outer };
    const target = new Element('input');
    target.setAttribute('aria-describedby', 'existing-field-help');
    target.details = inner;
    const context = new Element('section');
    context.instruction = 'Explain the sampling method.';
    context.dataset.revisionTarget = 'activity-1';
    environment(t, { target, context });

    initializeRevisionTargetFocus();

    assert.equal(outer.hasAttribute('open'), true);
    assert.equal(inner.hasAttribute('open'), true);
    assert.equal(target.cue, context);
    assert.equal(context.instruction, 'Explain the sampling method.');
    assert.deepEqual(target.focusOptions, { preventScroll: true });
    assert.equal(target.getAttribute('aria-describedby'), 'existing-field-help proposal-revision-target-cue');
});

test('disabled and hidden revision targets do not receive focus', (t) => {
    const target = new Element('input');
    environment(t, { target });
    target.disabled = true;
    assert.equal(focusRevisionTarget(target, 'mooe-total-override'), false);
    target.disabled = false;
    target.offsetParent = null;
    assert.equal(focusRevisionTarget(target, 'mooe-total-override'), false);
    assert.equal(target.focusOptions, undefined);
});

test('unavailable fields keep the instructions visible and show a useful fallback', (t) => {
    const context = new Element('section');
    context.dataset.revisionTarget = 'activity-99';
    context.child = new Element('p');
    context.child.setAttribute('hidden', '');
    environment(t, { context });

    initializeRevisionTargetFocus();

    assert.equal(context.child.hasAttribute('hidden'), false);
    assert.equal(context.removed, undefined);
});

test('a stale server-checked target never falls back to the URL field', (t) => {
    const target = new Element('input');
    const context = new Element('section');
    context.dataset.revisionTarget = '';
    environment(t, { target, context });

    initializeRevisionTargetFocus();

    assert.equal(target.focusOptions, undefined);
});

test('embedded field focus scrolls only the editor document and keeps the outer feedback page in place', (t) => {
    const target = new Element('input');
    const context = new Element('section');
    context.getBoundingClientRect = () => ({ top: 600 });
    environment(t, { target, context });
    window.scrollY = 100;
    window.innerHeight = 600;
    let scrolling;
    window.scrollTo = (options) => { scrolling = options; };
    assert.equal(focusRevisionTarget(target, 'activity-1', { withinDocument: true }), true);
    assert.equal(context.scrollOptions, undefined);
    assert.deepEqual(scrolling, { top: 500, behavior: 'auto' });
    assert.deepEqual(target.focusOptions, { preventScroll: true });
});


test('section destinations reveal the category, show its comment, scroll and briefly emphasize it', (t) => {
    const section = new Element('section');
    const details = new Element('details');
    section.details = details;
    environment(t, { target: null });
    document.querySelector = (selector) => selector === '[data-revision-section~="section-sdgs"]' ? section : null;
    const timers = [];
    window.clearTimeout = () => {};
    window.setTimeout = (callback, delay) => { if (delay === 4000) timers.push(callback); else callback(); };
    const target = findRevisionTarget(document, 'section-sdgs');
    assert.equal(target, section);
    assert.equal(focusRevisionTarget(target, 'section-sdgs', { label: 'III. Sustainable Development Goal', comment: 'Explain the selected goals.' }), true);
    assert.equal(details.hasAttribute('open'), true);
    assert.equal(section.cue.child.textContent, 'Explain the selected goals.');
    assert.equal(section.cue.scrollOptions.block, 'center');
    assert.equal(section.classes.has('revision-target-active'), true);
    timers[0]();
    assert.equal(section.classes.has('revision-target-active'), false);
    assert.equal(section.cue.removed, undefined);
});
