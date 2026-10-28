import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const read = path => readFileSync(resolve(path), 'utf8');

test('coordinator work pages match the office design and navigate in one tab across sizes and themes', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchCoordinatorWorkspaceTest.php', '--filter=focused work pages'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_COORDINATOR_WORKSPACE: '1' },
    });
    const paths = { dashboard: 'dashboard', proposals: 'proposals', targets: 'targets', members: 'faculty-members' };
    const sources = Object.fromEntries(Object.keys(paths).map(name => [name, read(`storage/framework/testing/coordinator-${name}.html`)]));
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css].map(file => read(`public/build/${file}`)).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const parser = await browser.newPage();
        const fragments = await parser.evaluate(sources => Object.fromEntries(Object.entries(sources).map(([name, source]) => {
            const doc = new DOMParser().parseFromString(source, 'text/html');
            return [name, { header: doc.querySelector('[data-workspace-header-banner]').outerHTML, nav: doc.querySelector('[data-research-office-navigation]').outerHTML, content: doc.querySelector('[data-coordinator-workspace]').outerHTML }];
        })), sources);
        await parser.close();
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                let popups = 0;
                page.on('pageerror', error => errors.push(error.message));
                page.on('popup', () => { popups++; });
                await page.route('**/*', route => {
                    const url = new URL(route.request().url());
                    if (url.pathname === '/alpine.js') return route.fulfill({ contentType: 'text/javascript', body: read('node_modules/alpinejs/dist/cdn.min.js') });
                    const name = Object.keys(paths).find(key => url.pathname === `/research-coordinator/${paths[key]}`);
                    if (!name) return route.abort();
                    const fragment = fragments[name];
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}\n[x-cloak]{display:none!important}.office-test-shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:100vh;background:${dark ? '#0b1220' : '#f5f7fa'}}.office-test-sidebar{padding:32px 12px;border-right:1px solid ${dark ? '#2b374b' : '#e4e7ec'};background:${dark ? '#0f172a' : 'white'}}.office-test-main{padding:20px 32px 32px;min-width:0}.office-test-main>header{margin-bottom:20px}.office-test-brand{display:block;margin:0 16px 40px;color:${dark ? '#f4a8b6' : '#7a0019'};font-size:24px;font-weight:800;letter-spacing:.12em}@media(max-width:900px){.office-test-shell{grid-template-columns:64px minmax(0,1fr)}.office-test-sidebar{padding:24px 6px}.office-test-sidebar nav :is(p,span),.office-test-brand{display:none}.office-test-sidebar nav a{padding:12px;justify-content:center}.office-test-main{padding:16px 12px}}</style></head><body class="font-sans" x-data><div class="office-test-shell"><aside class="office-test-sidebar"><span class="office-test-brand">ATHENA</span>${fragment.nav}</aside><main class="office-test-main"><header>${fragment.header}</header>${fragment.content}</main></div><script>document.addEventListener('alpine:init',()=>Alpine.store('sidebar',{open:${width >= 900},setOpen(value){this.open=value}}));</script><script src="/alpine.js"></script></body></html>` });
                });
                for (const [name, path] of Object.entries(paths)) {
                    await page.goto(`http://localhost/research-coordinator/${path}`);
                    await expect(page.locator('[data-coordinator-workspace]')).toHaveAttribute('data-coordinator-workspace', name);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${name} must fit at ${width}px in ${dark ? 'dark' : 'light'} mode`);
                    await expect(page.locator('[data-research-office-navigation] [aria-current="page"]')).toHaveCount(1);
                    assert.equal(await page.locator('[data-coordinator-workspace] a[target="_blank"]').count(), 0);
                    if (name === 'dashboard') {
                        assert.equal(await page.locator('[data-research-target-board], [data-notice-to-proceed-queue]').count(), 0);
                        assert.ok(await page.locator('[data-coordinator-priority-list] article').count() <= 4);
                        await page.getByRole('link', { name: 'Open LREC queue', exact: false }).click();
                        await expect(page.locator('[data-coordinator-workspace]')).toHaveAttribute('data-coordinator-workspace', 'proposals');
                        await page.goto('http://localhost/research-coordinator/dashboard');
                    }
                    if (name === 'proposals') {
                        await page.getByRole('searchbox', { name: 'Search LREC proposals', exact: true }).fill('Community');
                        await page.getByLabel('Review status', { exact: true }).selectOption('review');
                        await page.getByRole('button', { name: 'Find proposals', exact: true }).click();
                        const url = new URL(page.url());
                        assert.equal(url.searchParams.get('q'), 'Community');
                        assert.equal(url.searchParams.get('status'), 'review');
                        const comments = page.getByRole('link', { name: 'Record comments', exact: false });
                        await expect(comments).toHaveCount(1);
                        assert.ok((await comments.getAttribute('href')).endsWith('#proposal-review'));
                    }
                    if (name === 'members') {
                        await page.getByRole('searchbox', { name: 'Search faculty members', exact: true }).fill('Gabriel');
                        await page.getByRole('button', { name: 'Find members', exact: true }).click();
                        assert.equal(new URL(page.url()).searchParams.get('q'), 'Gabriel');
                        await expect(page.getByRole('heading', { name: 'Gabriel Santos', exact: true })).toBeVisible();
                    }
                    assert.equal(popups, 0);
                    assert.equal(page.context().pages().length, 1);
                    assert.deepEqual(errors, []);
                    const actionsWithoutButtonShape = await page.locator('[data-coordinator-workspace] a').evaluateAll(elements => elements
                        .filter(element => !element.closest('[data-research-target-board]'))
                        .filter(element => {
                            const style = getComputedStyle(element);
                            return style.display !== 'none' && element.getClientRects().length
                                && ((parseFloat(style.borderTopWidth) < 1 && ["rgba(0, 0, 0, 0)", "transparent"].includes(style.backgroundColor)) || parseFloat(style.borderRadius) < 4 || parseFloat(style.paddingLeft) < 8 || element.getBoundingClientRect().height < 44);
                        }).map(element => element.textContent.trim()));
                    assert.deepEqual(actionsWithoutButtonShape, [], `${name}: actions must have a visible button boundary and usable padding`);
                    if (['dashboard', 'proposals', 'members'].includes(name)) await page.screenshot({ path: resolve(`storage/framework/testing/coordinator-${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
