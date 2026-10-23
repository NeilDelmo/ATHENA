import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const origin = 'http://athena-lazy-editor.test';
const loaderPath = resolve('resources/js/lazy-alpine-component.js');

async function fixture({ fail = false } = {}) {
    assert.ok(existsSync(loaderPath), 'Editors need a loader that keeps their code off ordinary pages');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    const errors = [];
    let requests = 0;
    let release;
    const response = new Promise(resolve => { release = resolve; });
    page.on('pageerror', error => errors.push(error.message));
    await page.route(`${origin}/**`, async route => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/loader.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync(loaderPath) });
        if (path === '/alpine.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('node_modules/alpinejs/dist/module.esm.js')) });
        if (path === '/editor.js') {
            requests++;
            await response;
            return route.fulfill(fail ? { status: 503, body: 'Unavailable' } : { contentType: 'text/javascript', body: `
                export default Alpine => Alpine.data('heavyEditor', config => ({
                    value: config.value,
                    init() { window.editorStarts = (window.editorStarts || 0) + 1; },
                    destroy() { window.editorStops = (window.editorStops || 0) + 1; }
                }));
            ` });
        }
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body>
            <main x-data="{ label: 'Saved proposal', open: false }">
                <button @click="open = !open">Toggle editor</button>
                <template x-if="open"><section x-data="heavyEditor({value: label})">
                    <form><input x-model="value"><output x-text="value"></output><button>Save</button></form>
                </section></template>
            </main>
            <script type="module">
                import Alpine from '/alpine.js';
                import register from '/loader.js';
                register(Alpine, 'heavyEditor', () => import('/editor.js').then(module => module.default(Alpine)));
                window.Alpine = Alpine;
                window.saves = 0;
                document.addEventListener('athena:lazy-component-ready', event => { window.readyEditorValue = event.target.querySelector('input').value; });
                document.addEventListener('submit', event => { event.preventDefault(); window.saves++; });
                Alpine.start();
            </script>
        </body></html>` });
    });
    await page.goto(origin);
    await page.waitForFunction(() => window.Alpine);
    return { browser, page, errors, release, requests: () => requests };
}

test('ordinary pages skip editor code and delayed editors preserve scope without unsafe submissions or duplicate initialization', async () => {
    const context = await fixture();
    const { browser, page, errors, release, requests } = context;
    try {
        assert.equal(requests(), 0);
        await page.getByRole('button', { name: 'Toggle editor' }).click();
        await expect.poll(requests).toBe(1);
        await expect(page.locator('section')).toHaveAttribute('aria-busy', 'true');
        await expect(page.locator('[role="status"]')).toContainText('Loading');
        await page.locator('form').evaluate(form => form.requestSubmit());
        assert.equal(await page.evaluate(() => window.saves), 0, 'Pending editor forms must not submit incomplete state');

        await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigating')));
        assert.equal(await page.locator('section').getAttribute('x-ignore'), null, 'Navigation snapshots must not retain a temporary ignore attribute');
        await page.getByRole('button', { name: 'Toggle editor' }).click();
        await page.getByRole('button', { name: 'Toggle editor' }).click();
        release();
        await expect(page.locator('output')).toHaveText('Saved proposal');
        await expect.poll(() => page.evaluate(() => window.readyEditorValue)).toBe('Saved proposal');
        assert.equal(await page.evaluate(() => window.editorStarts), 1, 'The detached pending editor must not initialize');
        await page.getByRole('textbox').fill('Edited proposal');
        await expect(page.locator('output')).toHaveText('Edited proposal');
        await page.getByRole('button', { name: 'Save', exact: true }).click();
        assert.equal(await page.evaluate(() => window.saves), 1);
        await expect(page.locator('[role="status"]')).toHaveCount(0);
        await expect(page.locator('section')).not.toHaveAttribute('inert');

        await page.getByRole('button', { name: 'Toggle editor' }).click();
        assert.equal(await page.evaluate(() => window.editorStops), 1);
        await page.getByRole('button', { name: 'Toggle editor' }).click();
        await expect(page.locator('output')).toHaveText('Saved proposal');
        assert.equal(requests(), 1, 'Later editors must reuse the loaded module');
        assert.equal(await page.evaluate(() => window.editorStarts), 2);
        assert.deepEqual(errors, []);
    } finally {
        release();
        await browser.close();
    }
});

test('an unavailable editor keeps its form blocked and offers a visible recovery action', async () => {
    const { browser, page, errors, release } = await fixture({ fail: true });
    try {
        await page.getByRole('button', { name: 'Toggle editor' }).click();
        release();
        await expect(page.getByRole('alert')).toContainText('could not load');
        await expect(page.getByRole('button', { name: 'Reload editor' })).toBeVisible();
        await page.locator('form').evaluate(form => form.requestSubmit());
        assert.equal(await page.evaluate(() => window.saves), 0);
        assert.deepEqual(errors, []);
    } finally {
        release();
        await browser.close();
    }
});
