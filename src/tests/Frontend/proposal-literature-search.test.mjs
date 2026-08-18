import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildLiteratureSearchQuery,
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

test('research trail uses a readable title while retaining the full query as a tooltip', () => {
    const query = 'Book Management System automated cataloging module rapid indexing searching circulation borrowing';

    assert.equal(literatureSearchHistoryTitle(query, 'Book Management System'), 'Book Management System');
    assert.equal(literatureSearchHistoryTitle('library cataloging circulation'), 'library cataloging circulation');
});
