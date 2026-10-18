import assert from 'node:assert/strict';
import test from 'node:test';
import { researchManualReader } from '../../resources/js/research-manual-reader.js';

const config = {
    title: 'Research Manual', edition: '2026 edition', version: 'a'.repeat(64),
    page_count: 28, pdf_url: '/manuals/1/pdf', read_url: '/manuals/1/read',
    guidance_url: '/manuals/1/guidance', unindexed_pages: [7],
};

function fixture(context, { hash = '', configuration = config, openOnLoad = false, fetchResponse, fetcher } = {}) {
    const original = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    globalThis.window = Object.assign(new EventTarget(), { location: { hash } });
    globalThis.document = Object.assign(new EventTarget(), { querySelector: () => ({ content: 'csrf-token' }) });
    const events = [];
    const requests = [];
    for (const name of ['open-modal', 'pdf-preview-page']) {
        window.addEventListener(name, event => events.push({ name, detail: event.detail }));
    }
    globalThis.fetch = async (...args) => {
        requests.push(args);
        return fetcher ? fetcher(...args) : fetchResponse ?? {
            ok: true,
            json: async () => ({ reply: '<b>Review ethics.</b>', edition: '2026 edition', sources: [{ title: 'Research Manual', section: 'Ethics', page_start: 19, page_end: 20, url: '/manuals/1/read#page=19' }] }),
        };
    };
    const state = researchManualReader(configuration, openOnLoad);
    state.$dispatch = (name, detail) => window.dispatchEvent(new CustomEvent(name, { detail }));
    state.$nextTick = async callback => callback?.();
    context.after(() => {
        state.destroy();
        Object.assign(globalThis, original);
    });
    return { state, events, requests };
}

function expectedPreview(page) {
    return [
        { name: 'open-modal', detail: `research-manual-preview-${config.version}` },
        { name: 'pdf-preview-page', detail: { url: config.pdf_url, page } },
    ];
}

test('a plain manual page waits for a preview request and then opens the shared modal', async context => {
    const f = fixture(context);
    await f.state.init();
    assert.deepEqual(f.events, []);
    assert.equal(f.state.manualPage, 1);
    await f.state.openPreview(7);
    assert.equal(f.state.manualPage, 7);
    assert.deepEqual(f.events, expectedPreview(7));
});

test('a cited hash opens its page and later hash changes clamp valid page values', async context => {
    const f = fixture(context, { hash: '#page=19' });
    await f.state.init();
    assert.deepEqual(f.events, expectedPreview(19));
    f.events.length = 0;
    for (const [hash, page] of [['#page=100', 28], ['#page=0', 1], ['#page=2.9', 2]]) {
        window.location.hash = hash;
        window.dispatchEvent(new Event('hashchange'));
        await Promise.resolve();
        assert.deepEqual(f.events.splice(0), expectedPreview(page));
    }
    for (const hash of ['', '#other=3', '#page=invalid']) {
        window.location.hash = hash;
        window.dispatchEvent(new Event('hashchange'));
        await Promise.resolve();
        assert.deepEqual(f.events, []);
    }
});

test('manual page requests only open the matching edition', async context => {
    const f = fixture(context);
    await f.state.init();
    for (const detail of [{ version: 'another-edition', page: 7 }, null]) {
        window.dispatchEvent(new CustomEvent('research-manual:page', { detail }));
    }
    await Promise.resolve();
    assert.deepEqual(f.events, []);
    window.dispatchEvent(new CustomEvent('research-manual:page', { detail: { version: config.version, page: 7 } }));
    await Promise.resolve();
    assert.deepEqual(f.events, expectedPreview(7));
});

test('the standalone reader opens the first page when no citation hash is present', async context => {
    const f = fixture(context, { openOnLoad: true });
    await f.state.init();
    assert.deepEqual(f.events, expectedPreview(1));
});

test('component cleanup removes manual listeners and repeated cleanup is safe', async context => {
    const f = fixture(context);
    await f.state.init();
    f.state.destroy();
    f.state.destroy();
    window.location.hash = '#page=9';
    window.dispatchEvent(new Event('hashchange'));
    window.dispatchEvent(new CustomEvent('research-manual:page', { detail: { version: config.version, page: 7 } }));
    await f.state.openPreview(2);
    await Promise.resolve();
    assert.deepEqual(f.events, []);
});

test('edition guidance sends only the trimmed question with CSRF and preserves plain text and page sources', async context => {
    const f = fixture(context);
    f.state.question = '  What ethics review is required?  ';
    await f.state.askGuidance();
    assert.equal(f.requests[0][0], '/manuals/1/guidance');
    assert.equal(f.requests[0][1].headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(f.requests[0][1].credentials, 'same-origin');
    assert.deepEqual(JSON.parse(f.requests[0][1].body), { question: 'What ethics review is required?' });
    assert.equal(f.state.reply, '<b>Review ethics.</b>');
    assert.equal(f.state.sources[0].url, '/manuals/1/read#page=19');
    assert.equal(f.state.guidanceEdition, '2026 edition');
    assert.equal(f.state.guidanceLoading, false);
    assert.equal(f.state.sourceLabel(f.state.sources[0]), 'Ethics · Pages 19–20');
    assert.equal(f.state.sourceLabel({ title: 'Manual', page_start: 7, page_end: 7 }), 'Manual · Page 7');
});

test('guidance keeps the question available after a rejected request', async context => {
    const f = fixture(context, { fetchResponse: { ok: false, status: 422, json: async () => ({ message: 'Choose a question from this edition.' }) } });
    f.state.question = 'Ethics review?';
    await f.state.askGuidance();
    assert.equal(f.state.question, 'Ethics review?');
    assert.equal(f.state.reply, '');
    assert.equal(f.state.guidanceError, 'Choose a question from this edition.');
    assert.equal(f.state.guidanceLoading, false);
});

test('guidance prevents empty and duplicate requests', async context => {
    const f = fixture(context);
    await f.state.askGuidance('   ');
    f.state.guidanceLoading = true;
    await f.state.askGuidance('Ethics review?');
    assert.equal(f.requests.length, 0);
});

test('guidance is disabled without an edition endpoint', async context => {
    const disabled = fixture(context, { configuration: { ...config, guidance_url: null } });
    await disabled.state.askGuidance('Ethics review?');
    assert.equal(disabled.requests.length, 0);
});

test('component cleanup aborts pending guidance and ignores its late response', async context => {
    let resolveResponse;
    const f = fixture(context, { fetcher: () => new Promise(resolve => { resolveResponse = resolve; }) });
    await f.state.init();
    const pending = f.state.askGuidance('Ethics review?');
    const signal = f.requests[0][1].signal;
    assert.equal(signal.aborted, false);
    f.state.destroy();
    assert.equal(signal.aborted, true);
    resolveResponse({ ok: true, json: async () => ({ reply: 'Stale answer', edition: 'Old edition', sources: [] }) });
    await pending;
    assert.equal(f.state.reply, '');
    assert.equal(f.state.guidanceError, '');
    await f.state.askGuidance('Another question');
    assert.equal(f.requests.length, 1);
});

test('aborted guidance stays quiet and a network error allows another question', async context => {
    const f = fixture(context, { fetcher: async () => { throw new DOMException('Aborted', 'AbortError'); } });
    await f.state.askGuidance('Ethics review?');
    assert.equal(f.state.guidanceError, '');
    assert.equal(f.state.guidanceLoading, false);
    globalThis.fetch = async () => { throw new Error('Connection unavailable'); };
    await f.state.askGuidance('Ethics review?');
    assert.equal(f.state.guidanceError, 'Connection unavailable');
    assert.equal(f.state.guidanceLoading, false);
});
