import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium, expect } from '@playwright/test';

test('automatic manual check results update without reloading the PDF and stop polling when complete', { timeout: 30000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    let requests = 0;
    let unavailable = true;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://athena.test/**', route => {
        if (route.request().url().endsWith('.js')) {
            return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js/research-manual-checks.js'), 'utf8') });
        }
        if (route.request().headers().accept === 'application/json') {
            requests++;
            if (unavailable) return route.fulfill({ status: 503 });
            return route.fulfill({ json: {
                status: 'completed', html: '<section data-manual-checks data-check-status="completed"><p>Checks completed</p></section>',
            } });
        }
        return route.fulfill({ contentType: 'text/html', body: '<input aria-label="PDF page" value="7"><section data-manual-checks data-check-status="pending" data-status-url="/review"><p>Queued automatically</p><p data-manual-checks-poll-error hidden>Reconnecting automatically</p></section>' });
    });
    try {
        await page.goto('https://athena.test/review');
        await page.clock.install();
        await page.addScriptTag({ type: 'module', content: "import init from '/research-manual-checks.js'; init(); window.checksLoaded = true;" });
        await page.waitForFunction(() => window.checksLoaded);
        await page.clock.fastForward(4000);
        await expect(page.locator('[data-manual-checks-poll-error]')).toBeVisible();
        unavailable = false;
        await page.clock.fastForward(15000);
        await expect(page.locator('[data-manual-checks]')).toContainText('Checks completed');
        await expect(page.getByLabel('PDF page')).toHaveValue('7');
        const completedRequests = requests;
        await page.clock.fastForward(15000);
        assert.equal(requests, completedRequests);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

test('manual result refresh preserves focused questions and source links without replacing unchanged content', { timeout: 30000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    let result = 'Waiting for an answer';
    let requests = 0;
    const panel = () => `<section data-manual-checks data-check-status="running" data-status-url="/review"><p>${result}</p><details data-check-number="0"><summary>First question</summary><a href="/manual#page=3">First source</a><a href="/manual#page=7">Second source</a></details><details data-check-number="1"><summary>Second question</summary><a href="/manual#page=3">First source</a></details></section>`;
    await page.route('https://athena.test/**', route => {
        if (route.request().url().endsWith('.js')) {
            return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js/research-manual-checks.js'), 'utf8') });
        }
        if (route.request().headers().accept === 'application/json') {
            requests++;
            return route.fulfill({ json: { status: 'running', html: panel() } });
        }
        return route.fulfill({ contentType: 'text/html', body: panel() });
    });
    try {
        await page.goto('https://athena.test/review');
        await page.clock.install();
        await page.addScriptTag({ type: 'module', content: "import init from '/research-manual-checks.js'; init(); window.checksLoaded = true;" });
        await page.waitForFunction(() => window.checksLoaded);
        const question = page.getByText('First question', { exact: true });
        await question.click();
        await page.evaluate(() => window.originalQuestion = document.activeElement);
        await page.clock.fastForward(4000);
        await expect.poll(() => requests).toBe(1);
        await expect(question).toBeFocused();
        assert.equal(await page.evaluate(() => document.activeElement === window.originalQuestion), true);
        await expect(page.locator('details[data-check-number="0"]')).toHaveAttribute('open', '');

        result = 'The first answer is ready';
        await page.clock.fastForward(4000);
        await expect(page.locator('[data-manual-checks]')).toContainText(result);
        await expect(question).toBeFocused();

        const source = page.getByRole('link', { name: 'Second source', exact: true });
        await source.focus();
        result = 'The second answer is ready';
        await page.clock.fastForward(4000);
        await expect(page.locator('[data-manual-checks]')).toContainText(result);
        await expect(source).toBeFocused();
        await expect(page.locator('details[data-check-number="0"]')).toHaveAttribute('open', '');
        await expect(page.locator('details[data-check-number="1"]')).not.toHaveAttribute('open', '');
    } finally {
        await browser.close();
    }
});

test('manual checks pause while hidden and resume once without retaining disposed or completed polling', { timeout: 30000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    let requests = 0;
    let holdResponse = true;
    let completed = false;
    let releaseResponse;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://athena.test/**', async route => {
        if (route.request().url().endsWith('.js')) {
            return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js/research-manual-checks.js'), 'utf8') });
        }
        if (route.request().headers().accept === 'application/json') {
            const request = ++requests;
            if (holdResponse) await new Promise(resolve => { releaseResponse = resolve; });
            const status = completed ? 'completed' : 'running';
            return route.fulfill({ json: {
                status, html: `<section data-manual-checks data-check-status="${status}" data-status-url="/review"><p>Result ${request}</p></section>`,
            } });
        }
        return route.fulfill({ contentType: 'text/html', body: '<section data-manual-checks data-check-status="pending" data-status-url="/review"><p>Queued</p></section>' });
    });
    try {
        await page.goto('https://athena.test/review');
        await page.clock.install();
        await page.evaluate(() => {
            window.testHidden = true;
            Object.defineProperty(document, 'hidden', { configurable: true, get: () => window.testHidden });
        });
        await page.addScriptTag({ type: 'module', content: "import init from '/research-manual-checks.js'; window.initializeChecks = init; window.disposeChecks = init(); window.checksLoaded = true;" });
        await page.waitForFunction(() => window.checksLoaded);
        await page.clock.fastForward(60000);
        await page.waitForTimeout(50);
        assert.equal(requests, 0, 'hidden tabs must not request manual-check status');

        await page.evaluate(() => {
            window.testHidden = false;
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await expect.poll(() => requests).toBe(1);
        await page.evaluate(() => {
            document.dispatchEvent(new Event('visibilitychange'));
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await page.clock.fastForward(4000);
        await page.waitForTimeout(50);
        assert.equal(requests, 1, 'visibility changes must share the pending request');
        holdResponse = false;
        releaseResponse();
        await expect(page.locator('[data-manual-checks]')).toContainText('Result 1');

        await page.evaluate(() => {
            window.testHidden = true;
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await page.clock.fastForward(60000);
        await page.waitForTimeout(50);
        assert.equal(requests, 1, 'hiding an active panel must pause its next request');
        await page.evaluate(() => {
            window.testHidden = false;
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await expect(page.locator('[data-manual-checks]')).toContainText('Result 2');
        await page.evaluate(() => window.disposeChecks());
        await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
        await page.clock.fastForward(60000);
        await page.waitForTimeout(50);
        assert.equal(requests, 2, 'disposed panels must release their timer and visibility listener');

        completed = true;
        await page.evaluate(() => { window.disposeChecks = window.initializeChecks(); });
        await page.clock.fastForward(4000);
        await expect(page.locator('[data-manual-checks]')).toContainText('Result 3');
        await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
        await page.clock.fastForward(60000);
        await page.waitForTimeout(50);
        assert.equal(requests, 3, 'completed checks must remain stopped after a visibility change');
        assert.deepEqual(errors, []);
    } finally {
        releaseResponse?.();
        await browser.close();
    }
});
