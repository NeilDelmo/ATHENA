import assert from 'node:assert/strict';
import test from 'node:test';
import {
    filterWorkspacePeople,
    formatPersonName,
    personInitials,
} from '../../resources/js/proposal-people.js';

test('workspace names preserve the official uppercase form', () => {
    assert.equal(formatPersonName('SHEENA LEI DELMO'), 'SHEENA LEI DELMO');
    assert.equal(formatPersonName('Neil Carlo Delmo'), 'Neil Carlo Delmo');
});

test('workspace people can be searched by name or email', () => {
    const people = [
        { name: 'SHEENA LEI DELMO', email: 'sheena@example.edu' },
        { name: 'Neil Carlo Delmo', email: 'neil@example.edu' },
    ];

    assert.deepEqual(filterWorkspacePeople(people, 'sheena'), [people[0]]);
    assert.deepEqual(filterWorkspacePeople(people, 'neil@example'), [people[1]]);
});

test('person initials use the first and last names', () => {
    assert.equal(personInitials('SHEENA LEI DELMO'), 'SD');
});
