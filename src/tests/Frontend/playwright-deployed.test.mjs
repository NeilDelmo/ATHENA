import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, rmdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { deployedURL, savedSession, sessionPath } from '../../scripts/playwright/deployed-environment.mjs';
import BenchmarkReporter from '../../scripts/playwright/benchmark-reporter.mjs';

test('deployed benchmark requires an HTTPS origin without credentials or URL data', () => {
    assert.equal(deployedURL('https://athenaresearch.app'), 'https://athenaresearch.app/');
    for (const value of ['http://athenaresearch.app', 'https://localhost', 'https://127.0.0.1',
        'https://athenaresearch.app/path', 'https://user:secret@athenaresearch.app', 'https://athenaresearch.app/?key=secret']) {
        assert.throws(() => deployedURL(value), /HTTPS website origin/);
    }
});

test('deployed sessions exclude foreign cookies and cannot reuse local synthetic sessions', () => {
    const original = process.cwd();
    const directory = mkdtempSync(join(tmpdir(), 'athena-deployed-session-'));
    process.chdir(directory);
    try {
        assert.equal(savedSession(), null);
        mkdirSync('playwright/.auth/deployed', { recursive: true });
        const cookie = { name: 'athena_session', value: 'test-only', domain: 'athenaresearch.app', path: '/', expires: -1, httpOnly: true, secure: true, sameSite: 'Lax' };
        writeFileSync(sessionPath(), JSON.stringify({ cookies: [cookie, { ...cookie, domain: 'accounts.google.com' }], origins: [{ origin: 'https://accounts.google.com' }] }));
        assert.deepEqual(savedSession(), { cookies: [cookie], origins: [] });
        assert.throws(() => savedSession('https://staging.example/'), /does not belong/);
        writeFileSync(sessionPath(), JSON.stringify({ cookies: [{ ...cookie, domain: '127.0.0.1' }] }));
        assert.throws(() => savedSession(), /does not belong/);
    } finally {
        rmSync(join(directory, 'playwright/.auth/deployed/account.json'), { force: true });
        for (const path of ['playwright/.auth/deployed', 'playwright/.auth', 'playwright']) rmdirSync(join(directory, path));
        process.chdir(original);
        rmdirSync(directory);
    }
});

test('benchmark reports keep failed and skipped pages distinct from measured results', () => {
    const original = process.cwd();
    const directory = mkdtempSync(join(tmpdir(), 'athena-deployed-report-'));
    process.chdir(directory);
    try {
        const reporter = new BenchmarkReporter;
        reporter.onTestEnd({ title: 'Faculty dashboard' }, { status: 'passed', attachments: [{ name: 'browser-timings', body: Buffer.from(JSON.stringify({ workspace: 'faculty', page: 'faculty/dashboard', medianReadyMs: 120 })) }] });
        reporter.onTestEnd({ title: 'Head dashboard' }, { status: 'failed', attachments: [], error: { message: 'Session expired' } });
        reporter.onTestEnd({ title: 'Secretary dashboard' }, { status: 'skipped', attachments: [] });
        reporter.onEnd({ status: 'failed' });
        const report = JSON.parse(readFileSync('test-results/deployed-benchmark.json', 'utf8'));
        assert.equal(report.status, 'failed');
        assert.equal(report.results[0].medianReadyMs, 120);
        assert.equal(report.results[1].medianReadyMs, undefined);
        assert.equal(report.results[2].status, 'skipped');
        assert.match(readFileSync('test-results/deployed-benchmark.csv', 'utf8'), /"faculty","faculty\/dashboard","passed","120"/);
    } finally {
        for (const file of ['deployed-benchmark.json', 'deployed-benchmark.csv']) rmSync(join(directory, 'test-results', file), { force: true });
        rmdirSync(join(directory, 'test-results'));
        process.chdir(original);
        rmdirSync(directory);
    }
});
