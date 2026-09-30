import assert from 'node:assert/strict';
import test from 'node:test';
import { journalFinder } from '../../resources/js/journal-finder.js';

const response = (payload, ok = true, status = 200) => ({ ok, status, json: async () => payload });

test('an abstract alone can run a journal search and retain indexing evidence', async () => {
    let request;
    globalThis.document = { querySelector: () => ({ content: 'test-csrf' }) };
    globalThis.fetch = async (url, options) => {
        request = JSON.parse(options.body);
        return response({ results: [{ id: 'S1', scopus: { status: 'active' } }], warnings: ['Dated coverage'], keywords: ['diabetes'], related_articles: 4 });
    };
    const finder = journalFinder({ endpoint: '/search', context: 'Diabetes prevention and community health.' });
    await finder.search();
    assert.equal(request.query, null);
    assert.equal(request.indexing, 'prefer_scopus');
    assert.equal(finder.results[0].scopus.status, 'active');
    assert.deepEqual(finder.warnings, ['Dated coverage']);
    assert.equal(finder.isLoading, false);
});

test('clearing a pending search prevents its late response from replacing the cleared state', async () => {
    let finish;
    globalThis.fetch = () => new Promise((resolve) => { finish = resolve; });
    const finder = journalFinder({ endpoint: '/search', query: 'diabetes health' });
    const searching = finder.search();
    finder.clear();
    finish(response({ results: [{ id: 'S1' }] }));
    await searching;
    assert.deepEqual(finder.results, []);
    assert.equal(finder.hasSearched, false);
    assert.equal(finder.isLoading, false);
});

test('failed journal searches display the provider error and keep loading state usable', async () => {
    globalThis.fetch = async () => response({ errors: { indexing: ['Scopus is unavailable.'] } }, false, 422);
    const finder = journalFinder({ endpoint: '/search', query: 'diabetes health' });
    await finder.search();
    assert.equal(finder.error, 'Scopus is unavailable.');
    assert.equal(finder.isLoading, false);
    assert.deepEqual(finder.results, []);
});

test('an HTML login response cannot become a successful empty search', async () => {
    globalThis.fetch = async () => ({ ok: true, json: async () => { throw new SyntaxError('Unexpected HTML'); } });
    const finder = journalFinder({ endpoint: '/search', query: 'diabetes health' });
    await finder.search();
    assert.match(finder.error, /unexpected response/);
    assert.equal(finder.isLoading, false);
});
