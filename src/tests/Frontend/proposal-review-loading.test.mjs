import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { chromium } from 'playwright';

test('the proposal review loads when its dialog opens and stays mounted on reopening', async () => {
    const fixturePath = resolve('../tmp/proposal-review-loading.json');
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/OtherPageLoadingPerformanceTest.php', '--filter=the proposal hub'], {
        cwd: resolve('.'), encoding: 'utf8',
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_REVIEW_BROWSER_FIXTURE: fixturePath },
    });
    const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));
    const html = fixture.html.replaceAll('http://localhost', 'http://athena-review.test');
    const loadedReview = JSON.stringify(fixture.loaded).replaceAll('http://localhost', 'http://athena-review.test');
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const assets = new Set(Object.values(manifest).map((entry) => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        let reviewRequests = 0;
        let reviewRefreshRequests = 0;
        let readinessRequests = 0;
        let guideToMissingField = true;
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const url = new URL(route.request().url());
            const asset = url.pathname.replace(/^\/build\//, '');
            if (assets.has(asset)) {
                await route.fulfill({ body: readFileSync(resolve('public/build', asset)), contentType: asset.endsWith('.css') ? 'text/css' : 'text/javascript' });
            } else if (url.hostname === 'fonts.bunny.net') {
                await route.fulfill({ contentType: 'text/css', body: '' });
            } else if (route.request().method() === 'GET' && route.request().headers().accept?.includes('application/json') && url.pathname.endsWith('/review')) {
                readinessRequests++;
                await route.fulfill({ json: {
                    status: guideToMissingField ? 'incomplete' : 'prepare', can_guide: true,
                    ready_to_prepare: !guideToMissingField, ready_to_submit: false,
                    blockers: guideToMissingField ? [{
                        key: 'project_details', paper: 'details', field: 'duration_months',
                        owner: 'faculty', reason: 'validation', message: 'Set the project duration.',
                        url: 'http://athena-review.test/proposal?proposal_requirement=duration_months#project-details',
                    }] : [],
                } });
            } else if (route.request().method() === 'POST' && url.pathname.endsWith('/update')) {
                const body = route.request().postDataJSON();
                const method = body.components[0].calls[0].method;
                if (method === '__lazyLoad') reviewRequests++;
                else {
                    assert.equal(method, '$refresh');
                    reviewRefreshRequests++;
                }
                await route.fulfill({ contentType: 'application/json', body: loadedReview });
            } else if (route.request().isNavigationRequest() || url.pathname === '/proposal') {
                await route.fulfill({ contentType: 'text/html', body: html });
            } else {
                await route.fulfill({ json: { notifications: [], unread_count: 0 } });
            }
        });
        await page.goto('http://athena-review.test/proposal');
        await page.waitForFunction(() => window.Alpine?.store('sidebar'));
        await page.getByRole('heading', { name: 'Large saved proposal', exact: true }).waitFor();
        assert.equal(reviewRequests, 0);
        assert.equal(await page.locator('[data-proposal-review-package]').count(), 0);
        await page.getByRole('button', { name: 'Review & turn in', exact: true }).first().click();
        await page.waitForFunction(() => document.activeElement?.id === 'duration_months');
        assert.match(page.url(), /proposal_requirement=duration_months/);
        assert.equal(await page.locator('#duration_months').getAttribute('aria-invalid'), 'true');
        assert.equal(await page.locator('[data-proposal-review-package]').count(), 0);
        assert.equal(readinessRequests, 2);
        guideToMissingField = false;
        await page.getByRole('button', { name: 'Review & turn in', exact: true }).first().click();
        await page.locator('#review-papers-heading').waitFor({ state: 'visible' });
        assert.equal(reviewRequests, 1);
        assert.equal(reviewRefreshRequests, 0);
        await page.getByRole('button', { name: 'Close review and turn in' }).click();
        await page.locator('#review-papers-heading').waitFor({ state: 'hidden' });
        await page.getByRole('button', { name: 'Review & turn in', exact: true }).first().click();
        await page.locator('#review-papers-heading').waitFor({ state: 'visible' });
        assert.equal(reviewRequests, 1);
        assert.equal(reviewRefreshRequests, 1);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
