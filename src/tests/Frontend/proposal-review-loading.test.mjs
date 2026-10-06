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
        env: { ...process.env, ATHENA_REVIEW_BROWSER_FIXTURE: fixturePath },
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
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const url = new URL(route.request().url());
            const asset = url.pathname.replace(/^\/build\//, '');
            if (assets.has(asset)) {
                await route.fulfill({ body: readFileSync(resolve('public/build', asset)), contentType: asset.endsWith('.css') ? 'text/css' : 'text/javascript' });
            } else if (url.hostname === 'fonts.bunny.net') {
                await route.fulfill({ contentType: 'text/css', body: '' });
            } else if (route.request().method() === 'POST' && url.pathname.endsWith('/update')) {
                const body = route.request().postDataJSON();
                assert.equal(body.components[0].calls[0].method, '__lazyLoad');
                reviewRequests++;
                await route.fulfill({ contentType: 'application/json', body: loadedReview });
            } else if (route.request().isNavigationRequest()) {
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
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: 'proposal-review' })));
        await page.locator('#review-papers-heading').waitFor({ state: 'visible' });
        assert.equal(reviewRequests, 1);
        await page.getByRole('button', { name: 'Close review and turn in' }).click();
        await page.locator('#review-papers-heading').waitFor({ state: 'hidden' });
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: 'proposal-review' })));
        await page.locator('#review-papers-heading').waitFor({ state: 'visible' });
        assert.equal(reviewRequests, 1);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
