import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(
    app.indexOf('function formatProposalVersionTimestamp('),
    app.indexOf('\ninitializeProposalVersionMonitors();'),
);

const settle = () => new Promise((resolve) => setImmediate(resolve));

function createMonitor({ field = 'draft_version', version = 0 } = {}) {
    class Element {
        hidden = true;
        textContent = '';
    }

    const warning = new Element();
    const message = new Element();
    const status = new Element();
    const input = { value: String(version) };
    const editorState = { autoSaveInFlight: false };
    const form = {
        querySelector: (selector) => selector === `[name="${field}"]` ? input : null,
    };
    const editor = { querySelector: () => form };
    const monitor = {
        dataset: { loadedVersion: String(version), stateUrl: '/state', documentLabel: 'project details' },
        closest: () => editor,
        querySelector: (selector) => ({
            '[data-proposal-stale-warning]': warning,
            '[data-proposal-stale-message]': message,
            '[data-proposal-monitor-status]': status,
        })[selector],
    };
    let timer;
    let serverState = { version };
    let respond;
    let hold = false;
    let requests = 0;

    runInNewContext(source + '\ninitializeProposalVersionMonitors();', {
        HTMLElement: Element,
        Alpine: { $data: () => editorState },
        document: {
            hidden: false,
            body: { contains: () => true },
            querySelectorAll: () => [monitor],
        },
        window: {
            setInterval: (callback) => { timer = callback; },
            clearInterval() {},
        },
        fetch: async () => {
            requests += 1;
            const snapshot = { ...serverState };
            if (hold) await new Promise((resolve) => { respond = resolve; });

            return { ok: true, json: async () => snapshot };
        },
        Intl,
        Date,
    });

    return {
        warning, message, status, input, editorState, form,
        get requests() { return requests; },
        setServer: (state) => { serverState = state; },
        holdResponse: () => { hold = true; },
        releaseResponse: () => { hold = false; respond(); },
        poll: async () => { timer(); await settle(); },
    };
}

for (const field of ['draft_version', 'document_version']) {
    test(`${field}: own repeated autosaves do not trigger a collaborator warning`, async () => {
        const fixture = createMonitor({ field });
        await settle();
        assert.equal(fixture.warning.hidden, true);

        for (const version of [1, 2, 3]) {
            fixture.input.value = String(version);
            fixture.setServer({ version });
            await fixture.poll();
            assert.equal(fixture.warning.hidden, true);
            assert.match(fixture.status.textContent, /^Up to date/);
        }
    });
}

test('paper monitoring compares the paper version even when the form has a different draft version', async () => {
    const fixture = createMonitor({ field: 'document_version', version: 1 });
    fixture.form.querySelector = (selector) => selector === '[name="document_version"]'
        ? fixture.input
        : { value: '20' };
    await settle();
    fixture.setServer({ version: 2, updated_by: 'Another editor' });
    await fixture.poll();
    assert.equal(fixture.warning.hidden, false);
});

test('a poll response from before a completed autosave cannot mark the saved form stale', async () => {
    const fixture = createMonitor();
    await settle();
    fixture.holdResponse();
    await fixture.poll();
    fixture.input.value = '1';
    fixture.setServer({ version: 1 });
    fixture.releaseResponse();
    await settle();
    assert.equal(fixture.warning.hidden, true);
});

test('polling waits while autosave is pending and ignores a response overlapping a save', async () => {
    const fixture = createMonitor();
    await settle();
    fixture.editorState.autoSaveInFlight = true;
    await fixture.poll();
    assert.equal(fixture.requests, 1);

    fixture.editorState.autoSaveInFlight = false;
    fixture.setServer({ version: 1 });
    fixture.holdResponse();
    await fixture.poll();
    await fixture.poll();
    assert.equal(fixture.requests, 2, 'a slow check must not start a second overlapping request');
    fixture.editorState.autoSaveInFlight = true;
    fixture.releaseResponse();
    await settle();
    assert.equal(fixture.warning.hidden, true);

    fixture.input.value = '1';
    fixture.editorState.autoSaveInFlight = false;
    await fixture.poll();
    assert.equal(fixture.warning.hidden, true);
});

test('a real newer save still warns even without a collaborator or a known author', async () => {
    const fixture = createMonitor({ version: 1 });
    await settle();
    fixture.setServer({ version: 2, updated_by: null });
    await fixture.poll();
    assert.equal(fixture.warning.hidden, false);
    assert.match(fixture.message.textContent, /Version 2 was saved/);
    assert.doesNotMatch(fixture.message.textContent, /teammate/i);
});

test('acknowledging a successful save clears an obsolete warning', async () => {
    const fixture = createMonitor();
    await settle();
    fixture.setServer({ version: 1 });
    await fixture.poll();
    assert.equal(fixture.warning.hidden, false);
    fixture.input.value = '1';
    await fixture.poll();
    assert.equal(fixture.warning.hidden, true);
});

test('removal remains visible even if its version matches the loaded version', async () => {
    const fixture = createMonitor({ version: 2 });
    await settle();
    fixture.setServer({ version: 2, is_removed: true });
    await fixture.poll();
    assert.equal(fixture.warning.hidden, false);
    assert.match(fixture.message.textContent, /was removed/);
});
