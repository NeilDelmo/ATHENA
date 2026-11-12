import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { asActor, confirmAction, fixtures } from './lifecycle-helpers.mjs';
import { writeFunctionalCollection } from '../../scripts/playwright/build-functional-postman.mjs';

test('AUTH-05 [faculty_researcher]: promoted researcher logout protects refreshed pages', async ({ browser }, testInfo) => {
    await asActor(browser, 'faculty', testInfo, async page => {
        await page.goto('research');
        await page.getByRole('button', { name: 'Open account menu', exact: true }).click();
        await page.getByRole('button', { name: 'Log Out', exact: true }).click();
        await confirmAction(page, 'Log out');
        await page.goto('research');
        await expect(page).toHaveURL(/\/login$/);
        await page.goBack();
        await page.reload();
        await page.goto('research');
        await expect(page).toHaveURL(/\/login$/);
    }, 'faculty_researcher');
});

test('POSTMAN: execute workbook endpoint checks with Newman and retain redacted assertion evidence', async ({ baseURL }, testInfo) => {
    test.setTimeout(300_000);
    const require = createRequire(import.meta.url);
    const newman = require('../../test-results/functional-tooling/node_modules/newman');
    const manifest = fixtures();
    const values = {
        baseURL: baseURL.replace(/\/$/, ''), fixtureKey: manifest.key, ...manifest.functional,
        ...Object.fromEntries(Object.entries(manifest.users).map(([actor, user]) => [`${actor}Id`, String(user.id)])),
        facultyId: String(manifest.functional.ownerId),
    };
    const environment = { name: 'ATHENA private isolated local fixtures', values: Object.entries(values).map(([key, value]) => ({ key, value: String(value), enabled: true, type: key === 'fixtureKey' ? 'secret' : 'default' })) };
    writeFileSync('test-results/functional.local.postman_environment.json', JSON.stringify(environment, null, 2));
    mkdirSync('test-results/functional-fixtures', { recursive: true });
    const reference = readFileSync('test-results/lifecycle-fixtures/reference.pdf');
    // Laravel File::max('25mb') uses 25000 KiB, or 25600000 bytes.
    const boundary = Buffer.alloc(25_000 * 1024, 32);
    reference.copy(boundary);
    writeFileSync('test-results/functional-fixtures/boundary.pdf', boundary);
    writeFileSync('test-results/functional-fixtures/oversized.pdf', Buffer.concat([boundary, Buffer.from(' ')]));
    const results = [];
    await new Promise((resolve, reject) => {
        const runner = newman.run({ collection: writeFunctionalCollection(), environment, reporters: [],
            workingDir: process.cwd(), insecure: false, ignoreRedirects: true, timeoutRequest: 120_000,
        }, (error, summary) => {
            if (error) return reject(error);
            const report = { tool: 'Postman collection v2.1 / Newman', version: require('../../test-results/functional-tooling/node_modules/newman/package.json').version,
                environment: baseURL, executedAt: new Date().toISOString(), stats: summary.run.stats, results,
                limitations: 'Local synthetic sessions; Google OAuth and CSRF enforcement are not evaluated. No cookies, keys or request bodies are included in this report.' };
            writeFileSync('test-results/functional-postman.json', `${JSON.stringify(report, null, 2)}\n`);
            resolve();
        });
        runner.on('request', (error, args) => {
            let validationErrors;
            if (args.response?.code === 422) {
                try { validationErrors = args.response.json().errors; } catch { /* A non-JSON validation response is captured by its assertions. */ }
            }
            results.push({ name: args.item.name, status: args.response?.code, validationErrors, error: error?.message, assertions: [] });
        });
        runner.on('assertion', (error, args) => {
            const row = results.at(-1);
            if (row) row.assertions.push({ name: args.assertion, passed: !error, error: error?.message });
        });
    });
    const failures = results.filter(r => r.error || r.assertions.some(a => !a.passed));
    console.log(`Postman requests: ${results.length}; failed request checks: ${failures.length}`);
    for (const failure of failures) console.log(JSON.stringify(failure));
    await testInfo.attach('postman-assertions', { path: 'test-results/functional-postman.json', contentType: 'application/json' });
    expect(failures).toEqual([]);
});
