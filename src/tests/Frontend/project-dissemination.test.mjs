import assert from 'node:assert/strict';
import test from 'node:test';
import { projectDissemination } from '../../resources/js/project-dissemination.js';

function fixture(context, fetcher) {
    const previous = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    globalThis.window = { location: { hash: '' } };
    globalThis.document = { querySelector: () => ({ content: 'test-csrf' }) };
    globalThis.fetch = fetcher;
    context.after(() => Object.assign(globalThis, previous));
    return projectDissemination({ endpoints: { conferenceSearch: '/conferences', profile: '/profile' } });
}

test('a conference search reports an HTML login response without breaking the results list', async context => {
    const state = fixture(context, async () => ({ ok: true, json: async () => { throw new SyntaxError('Unexpected HTML'); } }));
    await state.searchConferences();
    assert.deepEqual(state.conferenceResults, []);
    assert.equal(state.conferenceSearched, false);
    assert.match(state.error, /unexpected response/i);
    assert.equal(state.busy, '');
});

test('a login redirect cannot report a saved profile or erase the linked profile', async context => {
    const state = fixture(context, async () => ({ ok: true, json: async () => { throw new SyntaxError('Unexpected HTML'); } }));
    state.profile = { author_id: 'A42', scholar_url: 'https://scholar.google.com/citations?user=test' };
    const profile = state.profile;
    await state.saveScholar();
    assert.equal(state.profile, profile);
    assert.equal(state.message, '');
    assert.match(state.error, /unexpected response/i);
    assert.equal(state.busy, '');
});

test('a failed profile request remains retryable and a valid response updates the linked profile', async context => {
    let fail = true;
    const profile = { author_id: 'A42', scholar_url: 'https://scholar.google.com/citations?user=test' };
    const state = fixture(context, async () => ({
        ok: !fail,
        json: async () => fail ? { errors: { scholar_url: ['Use a valid Scholar profile link.'] } } : { profile },
    }));
    await state.saveScholar();
    assert.equal(state.error, 'Use a valid Scholar profile link.');
    assert.equal(state.busy, '');
    fail = false;
    await state.saveScholar();
    assert.equal(state.profile, profile);
    assert.equal(state.error, '');
    assert.equal(state.message, 'Google Scholar profile link saved.');
});
