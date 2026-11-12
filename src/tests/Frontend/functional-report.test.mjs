import assert from 'node:assert/strict';
import test from 'node:test';
import { collectAttempts, currentCaseStatus, mappedIds } from '../../scripts/playwright/functional-report.mjs';

test('workbook IDs are mapped without confusing guest test IDs with source case IDs', () => {
    assert.deepEqual(mappedIds('ACCESS-01: signed-out Faculty dashboard', 'capstone-guest.spec.js'), ['ACCESS-04']);
    assert.deepEqual(mappedIds('AUTH-ROLES: identities', 'lifecycle.spec.js'), ['AUTH-01', 'ACCESS-01']);
    assert.deepEqual(mappedIds('DOC-02 DOC-03 [invalid uploads not stored]'), ['DOC-02', 'DOC-03']);
});

test('evidence collection keeps case IDs, skips and endpoint failures separate from runner setup', () => {
    const browser = { stats: { startTime: '2026-11-08T08:00:00Z' }, suites: [{ specs: [
        { file: 'capstone-guest.spec.js', title: 'ACCESS-01: protected page', tests: [{ results: [{ status: 'passed' }] }] },
        { file: 'functional-cases.spec.js', title: 'DOC-05 [missing]', tests: [{ results: [{ status: 'skipped' }] }] },
    ] }] };
    const api = { executedAt: '2026-11-08T08:01:00Z', results: [
        { name: 'SETUP session faculty', assertions: [] },
        { name: 'BUD-03 [invalid]', status: 500, assertions: [{ passed: false, error: 'Expected 422' }] },
    ] };
    const { attempts } = collectAttempts(browser, api, 'browser.json', 'api.json');
    assert.deepEqual(attempts.map(a => [a.id, a.status]), [['ACCESS-04', 'Passed'], ['DOC-05', 'Not run'], ['BUD-03', 'Failed']]);
    assert.equal(attempts[2].actor, 'functional_owner');
    assert.equal(attempts[2].evidence, 'api.json :: BUD-03 [invalid]');
});

test('functional case status does not turn partial coverage or blocked credentials into a pass', () => {
    assert.equal(currentCaseStatus({ completeScope: false }, [{ status: 'Passed' }]), 'Not run');
    assert.equal(currentCaseStatus({ completeScope: true }, []), 'Not run');
    assert.equal(currentCaseStatus({ completeScope: true }, [{ status: 'Passed' }, { status: 'Failed' }]), 'Failed');
    assert.equal(currentCaseStatus({ completeScope: true }, [{ status: 'Passed' }]), 'Passed');
    assert.equal(currentCaseStatus({ blocked: true }, [{ status: 'Passed' }]), 'Blocked');
    assert.equal(currentCaseStatus({ completeScope: true, requiredEvidence: [{ tool: 'Playwright', variation: 'not executed' }] }, [{ status: 'Passed' }]), 'Not run');
});
