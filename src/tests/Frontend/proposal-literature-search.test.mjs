import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildLiteratureSearchQuery,
    buildLiteratureSearchPayload,
    fetchLiteratureSearch,
    literatureSearchHistoryTitle,
} from '../../resources/js/proposal-literature-search.js';

test('suggested literature searches stay focused on meaningful proposal terms', () => {
    const query = buildLiteratureSearchQuery([
        { key: 'title', value: 'Book Management System' },
        { key: 'generalObjective', value: 'To design, develop, and deploy a web-based Book Management System.' },
        { key: 'specificObjectives', value: 'To implement an automated book cataloging module for rapid indexing, searching, circulation, and borrowing.' },
    ]);

    assert.equal(query, 'Book Management System automated cataloging module rapid indexing searching circulation borrowing');
    assert.ok(query.split(' ').length <= 16);
    assert.doesNotMatch(query, /\b(?:to|and|a|the)\b/i);
});

test('selected proposal details and filters reach the search endpoint without empty numeric filters', () => {
    const payload = buildLiteratureSearchPayload('  diabetes prevention  ', [
        { key: 'title', value: '<p>Community Diabetes Prevention</p>' },
        { key: 'generalObjective', value: 'To reduce diabetes risks in rural adults.' },
    ], { year_from: '2022', year_to: '', min_citations: '0', open_access: true });

    assert.deepEqual(payload, {
        query: 'diabetes prevention',
        context: 'Community Diabetes Prevention\nTo reduce diabetes risks in rural adults',
        year_from: 2022,
        min_citations: 0,
        open_access: true,
    });
    assert.deepEqual(buildLiteratureSearchPayload('diabetes prevention'), { query: 'diabetes prevention' });
});

test('long objectives leave room for other selected proposal details', () => {
    const payload = buildLiteratureSearchPayload('community monitoring', [
        { key: 'generalObjective', value: 'Long objective '.repeat(1000) },
        { key: 'specificObjectives', value: 'Mangrove survival and water quality' },
    ]);

    assert.ok(payload.context.length <= 6000);
    assert.match(payload.context, /Mangrove survival and water quality/);
});

test('search sends context with the session and returns actual results', async () => {
    const result = await fetchLiteratureSearch('/literature-search', 'csrf-token', { query: 'diabetes prevention' }, async (url, options) => {
        assert.equal(url, '/literature-search');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.deepEqual(JSON.parse(options.body), { query: 'diabetes prevention' });
        return { ok: true, status: 200, json: async () => ({ results: [{ title: 'Diabetes prevention' }] }) };
    });

    assert.equal(result.results[0].title, 'Diabetes prevention');
});

test('validation and provider errors are reported instead of appearing as no matches', async () => {
    await assert.rejects(fetchLiteratureSearch('/search', '', {}, async () => ({
        ok: false, status: 422, json: async () => ({ errors: { year_from: ['Invalid year range.'] } }),
    })), /Invalid year range/);
    await assert.rejects(fetchLiteratureSearch('/search', '', {}, async () => ({
        ok: false, status: 503, json: async () => ({ message: 'Indexes are unavailable.' }),
    })), /Indexes are unavailable/);
});

test('expired sessions and malformed successful responses cannot look like empty searches', async () => {
    await assert.rejects(fetchLiteratureSearch('/search', '', {}, async () => ({
        ok: true, status: 200, redirected: true,
    })), /session has expired/);
    await assert.rejects(fetchLiteratureSearch('/search', '', {}, async () => ({
        ok: true, status: 200, json: async () => { throw new SyntaxError('HTML response'); },
    })), /unexpected response/);
    await assert.rejects(fetchLiteratureSearch('/search', '', {}, async () => ({
        ok: true, status: 200, json: async () => ({}),
    })), /unexpected response/);
});

test('research trail uses a readable title while retaining the full query as a tooltip', () => {
    const query = 'Book Management System automated cataloging module rapid indexing searching circulation borrowing';

    assert.equal(literatureSearchHistoryTitle(query, 'Book Management System'), 'Book Management System');
    assert.equal(literatureSearchHistoryTitle('library cataloging circulation'), 'library cataloging circulation');
});
