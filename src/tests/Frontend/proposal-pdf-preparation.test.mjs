import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium, expect } from '@playwright/test';

test('PDFs prepare automatically once and report progress, readiness and retryable errors without submitting', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let posts = 0;
    let phase = 'queued';
    await page.route('https://athena.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js', path.slice(1)), 'utf8') });
        if (path === '/prepare') {
            assert.equal(route.request().method(), 'POST');
            assert.equal(route.request().headers()['x-csrf-token'], 'csrf');
            posts++;
            assert.equal(route.request().postDataJSON().retry, posts > 1);
            phase = 'queued';
            return route.fulfill({ status: 202, json: { preparation: { status: phase, message: 'Changes saved. PDFs will update automatically.', prepared: 0, total: 7 } } });
        }
        if (path === '/review') return route.fulfill({ json: { status: phase === 'ready' ? 'ready' : 'prepare', blockers: [], preparation: {
            status: phase, message: phase === 'ready' ? 'Submission PDFs are up to date.' : 'Preparing the latest submission PDFs…', prepared: phase === 'ready' ? 7 : 2, total: 7,
        } } });
        return route.fulfill({ contentType: 'text/html', body: `<meta name="csrf-token" content="csrf">
            <div data-proposal-pdf-preparation data-state-url="/review" data-prepare-url="/prepare"
                data-pdf-state='{"status":"needed","message":"Waiting","prepared":0,"total":7}'>
                <p data-pdf-preparation-message></p><span data-pdf-preparation-count></span>
                <button data-pdf-preparation-retry hidden>Retry PDF preparation</button>
            </div>` });
    });
    try {
        await page.goto('https://athena.test/editor');
        await page.addScriptTag({ type: 'module', content: "import init from '/proposal-pdf-preparation.js'; window.initPdfPreparation = init; init(); window.pdfLoaded = true;" });
        await page.waitForFunction(() => window.pdfLoaded);
        await expect.poll(() => posts).toBe(1);
        await expect(page.locator('[data-pdf-preparation-message]')).toContainText('automatically');
        phase = 'preparing';
        await expect(page.locator('[data-pdf-preparation-count]')).toHaveText('2 / 7 PDFs');
        phase = 'ready';
        await expect(page.locator('[data-pdf-preparation-message]')).toContainText('up to date');
        assert.equal(posts, 1);
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('proposal-readiness-updated', { detail: {
            url: 'https://athena.test/review', data: { preparation: { status: 'failed', message: 'PDF preparation failed. Your saved content is safe.', prepared: 2, total: 7 } },
        } })));
        await expect(page.getByRole('button', { name: 'Retry PDF preparation' })).toBeVisible();
        await page.getByRole('button', { name: 'Retry PDF preparation' }).click();
        await expect.poll(() => posts).toBe(2);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

test('PDF status reconnects after a temporary outage and keeps polling stalled and retrying work without resetting its budget', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    let unavailable = true;
    let phase = 'stalled';
    let posts = 0;
    await page.route('https://athena.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js', path.slice(1)), 'utf8') });
        if (path === '/prepare') {
            posts++;
            return route.fulfill({ status: 500 });
        }
        if (path === '/review') {
            if (unavailable) return route.fulfill({ status: 503 });
            return route.fulfill({ json: { preparation: { status: phase, message: phase === 'ready' ? 'Submission PDFs are up to date.' : phase, prepared: phase === 'ready' ? 7 : 2, total: 7 } } });
        }
        return route.fulfill({ contentType: 'text/html', body: `<meta name="csrf-token" content="csrf">
            <div data-proposal-pdf-preparation data-state-url="/review" data-prepare-url="/prepare"
                data-pdf-state='{"status":"preparing","message":"Preparing","prepared":2,"total":7}'>
                <p data-pdf-preparation-message></p><span data-pdf-preparation-count></span>
                <button data-pdf-preparation-retry hidden>Retry PDF preparation</button>
            </div>` });
    });
    try {
        await page.goto('https://athena.test/editor');
        await page.clock.install();
        await page.addScriptTag({ type: 'module', content: "import init from '/proposal-pdf-preparation.js'; init(); window.pdfLoaded = true;" });
        await page.waitForFunction(() => window.pdfLoaded);
        await page.clock.fastForward(3000);
        await expect(page.locator('[data-pdf-preparation-message]')).toContainText('Reconnecting automatically');
        await expect(page.locator('[data-pdf-preparation-count]')).toHaveText('2 / 7 PDFs');
        await expect(page.getByRole('button', { name: 'Retry PDF preparation' })).toBeHidden();
        unavailable = false;
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        await expect(page.locator('[data-pdf-preparation-message]')).toHaveText('stalled');
        phase = 'retrying';
        await page.clock.fastForward(3000);
        await expect(page.locator('[data-pdf-preparation-message]')).toHaveText('retrying');
        phase = 'ready';
        await page.clock.fastForward(3000);
        await expect(page.locator('[data-pdf-preparation-message]')).toContainText('up to date');
        assert.equal(posts, 0);
    } finally {
        await browser.close();
    }
});
