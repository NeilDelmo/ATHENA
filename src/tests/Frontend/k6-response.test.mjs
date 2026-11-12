import assert from 'node:assert/strict';
import test from 'node:test';
import { responseError } from '../../scripts/jmeter/k6-response.mjs';

test('k6 rejects redirects, login pages, wrong accounts and invalid documents', () => {
    const user = { workspace: 'faculty', actorId: 4 };
    const body = '<body data-auth-user-id="4" data-active-workspace="faculty">';
    assert.equal(responseError(200, 'text/html', body, {}, user), null);
    assert.match(responseError(302, 'text/html', '', {}, user), /HTTP/);
    assert.match(responseError(200, 'text/html', body.replace('"4"', '"5"'), {}, user), /account/);
    assert.match(responseError(200, 'text/html', body.replace('faculty', 'research_head'), {}, user), /workspace/);
    assert.match(responseError(200, 'text/html', '<form action="/login">', {}, user), /Sign-in/);
    assert.match(responseError(200, 'application/pdf', '', { binary: true }, user), /document/);
    assert.match(responseError(200, 'text/html', body, { binary: true }, user), /document/);
    assert.equal(responseError(200, 'application/pdf', '%PDF-test', { binary: true }, user), null);
});
