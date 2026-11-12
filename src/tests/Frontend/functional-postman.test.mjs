import assert from 'node:assert/strict';
import test from 'node:test';
import { functionalCollection } from '../../scripts/playwright/build-functional-postman.mjs';

test('functional collection scripts parse and block nonlocal targets before sending', () => {
    const collection = functionalCollection();
    const guard = new Function('pm', collection.event[0].script.exec.join('\n'));
    for (const target of ['https://athenaresearch.app', 'http://example.com:8055', 'http://127.0.0.1:8055/redirect', 'http://127.0.0.1.evil:8055']) {
        let skipped = false;
        const pm = { environment: { get: key => key === 'baseURL' ? target : 'test-key' }, execution: { skipRequest: () => { skipped = true; } } };
        assert.throws(() => guard(pm), /nonlocal/);
        assert.equal(skipped, true);
    }
    guard({ environment: { get: key => key === 'baseURL' ? 'http://127.0.0.1:8055' : 'test-key' }, execution: { skipRequest: () => assert.fail('Local target rejected') } });
    for (const item of collection.item) {
        assert.match(item.request.url, /^\{\{baseURL\}\}\//);
        for (const event of item.event) new Function(event.script.exec.join('\n'));
    }
    assert(collection.item.some(item => item.name.startsWith('DOC-03')));
    assert(collection.item.some(item => item.name.startsWith('BUD-04')));
});
