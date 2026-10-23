import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const origin = 'http://athena-page-assets.test';
const fonts = readFileSync(resolve('resources/views/components/app-fonts.blade.php'), 'utf8').replace(/\{\{--[\s\S]*?--\}\}/g, '');
const fontUrl = 'https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap';
const fontCss = 'main { letter-spacing: 7px; }';
const fontPage = `<!doctype html><html><head>${fonts}<style>body { margin: 20px; font: 20px sans-serif; }</style></head><body><main>ATHENA projects are ready</main></body></html>`;

test('page content paints while the optional font stylesheet is still pending and applies it when loaded', { timeout: 15000 }, async context => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    let releaseFonts;
    const fontResponse = new Promise(resolve => { releaseFonts = resolve; });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const fontRequested = page.waitForRequest(fontUrl);
        await page.route('**/*', async route => {
            if (route.request().url() === fontUrl) {
                await fontResponse;
                return route.fulfill({ contentType: 'text/css', body: fontCss, headers: { 'Timing-Allow-Origin': '*' } });
            }
            return route.fulfill({ contentType: 'text/html', body: fontPage });
        });
        await page.goto(origin, { waitUntil: 'commit' });
        await fontRequested;
        try {
            await expect.poll(() => page.evaluate(() => performance.getEntriesByName('first-contentful-paint').length), { timeout: 1500 }).toBe(1);
            assert.equal(await page.locator('main').evaluate(element => getComputedStyle(element).letterSpacing), 'normal');
        } finally {
            releaseFonts();
        }
        await expect(page.locator('main')).toHaveCSS('letter-spacing', '7px');
        const timing = await page.evaluate(url => ({
            paint: performance.getEntriesByName('first-contentful-paint')[0].startTime,
            fontResponse: performance.getEntriesByName(url).at(-1).responseEnd,
        }), fontUrl);
        assert.ok(timing.paint < timing.fontResponse, 'Font CSS must not hold back the first content paint');
        context.diagnostic(`Content paint ${timing.paint.toFixed(1)}ms; font CSS response ${timing.fontResponse.toFixed(1)}ms`);
        assert.deepEqual(errors, []);
    } finally {
        releaseFonts();
        await browser.close();
    }
});

test('font styling still applies when JavaScript is disabled', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ javaScriptEnabled: false });
        await page.route('**/*', route => route.fulfill(route.request().url() === fontUrl
            ? { contentType: 'text/css', body: fontCss }
            : { contentType: 'text/html', body: fontPage }));
        await page.goto(origin);
        await expect(page.locator('main')).toHaveCSS('letter-spacing', '7px');
    } finally {
        await browser.close();
    }
});

test('page navigation reuses the loaded font stylesheet', async () => {
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const appEntry = manifest['resources/js/app.js'];
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => {
            if (route.request().url() === fontUrl) return route.fulfill({ contentType: 'text/css', body: fontCss });
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) {
                return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            }
            const number = Number(path) || 1;
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                ${fonts}
                <meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <script data-navigate-once>window.livewireScriptConfig = { csrf: 'test', uri: '/livewire/update', progressBar: '' };</script>
                <script type="module" src="/${appEntry.file}"></script>
            </head><body x-data data-app-shell data-auth-user-id="1">
                <main>Page ${number}</main><a wire:navigate href="/${number + 1}">Next page</a>
            </body></html>` });
        });
        await page.goto(`${origin}/1`);
        await page.waitForFunction(() => window.Alpine?.store('researchAssistant'));
        for (const number of [1, 2, 3]) {
            await expect(page.locator('main')).toHaveText(`Page ${number}`);
            await expect(page.locator('main')).toHaveCSS('letter-spacing', '7px');
            await expect(page.locator('head > link[rel="stylesheet"][href*="fonts.bunny.net"]')).toHaveCount(1);
            if (number < 3) await page.getByRole('link', { name: 'Next page' }).click();
        }
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

for (const width of [390, 1280]) {
    test(`the decorative header image loads only when shown at ${width}px`, async context => {
        const layout = readFileSync(resolve('resources/views/layouts/app.blade.php'), 'utf8');
        const header = layout.match(/<div\s+class="pointer-events-none absolute inset-y-0 left-0 hidden w-\[30rem\][\s\S]*?<img\b[^>]*>/)?.[0];
        assert.ok(header, 'Render the actual responsive header image markup');
        const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
        const css = readFileSync(resolve('public/build', manifest['resources/css/app.css'].file), 'utf8');
        const image = readFileSync(resolve('public/images/front.jpg'));
        const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style></head><body><nav class="relative h-[120px]">${header.replace(/\{\{\s*asset\('images\/front\.jpg'\)\s*\}\}/, '/images/front.jpg')}</div></nav><main>ATHENA dashboard</main></body></html>`;
        const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
        try {
            const page = await browser.newPage({ viewport: { width, height: 800 } });
            const requests = [];
            await page.route('**/*', route => {
                if (new URL(route.request().url()).pathname === '/images/front.jpg') {
                    requests.push(route.request().url());
                    return route.fulfill({ contentType: 'image/jpeg', body: image });
                }
                return route.fulfill({ contentType: 'text/html', body: html });
            });
            await page.goto(origin, { waitUntil: 'networkidle' });
            if (width < 768) {
                await expect(page.locator('img')).toBeHidden();
                assert.equal(requests.length, 0, 'Hidden mobile decoration must not compete with page content');
            } else {
                await expect(page.locator('img')).toBeVisible();
                await expect.poll(() => page.locator('img').evaluate(element => element.naturalWidth)).toBeGreaterThan(0);
                assert.equal(requests.length, 1);
            }
            context.diagnostic(`Header image requests: ${requests.length}; transferred asset bytes: ${requests.length * image.length}`);
        } finally {
            await browser.close();
        }
    });
}
