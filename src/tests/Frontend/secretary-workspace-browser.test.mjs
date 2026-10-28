import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const read = path => readFileSync(resolve(path), 'utf8');
const script = name => read(`resources/js/${name}.js`).replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '');

test('secretary review decisions show compact instructions only when returning reports', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ProjectReportSecretaryReviewUiTest.php', '--filter=secretary report decisions use explicit choices'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_SECRETARY_REVIEW: '1' },
    });
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css].map(file => read(`public/build/${file}`)).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const parser = await browser.newPage();
        const markup = await parser.evaluate(source => Array.from(new DOMParser().parseFromString(source, 'text/html').querySelectorAll('[data-secretary-report-review]'), form => form.outerHTML).join('\n'), read('storage/framework/testing/secretary-report-decisions.html'));
        await parser.close();
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 800 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${styles}\n[x-cloak]{display:none!important}</style></head><body class="p-4 ${dark ? 'bg-slate-900' : 'bg-white'}"><main class="max-w-4xl space-y-8">${markup}</main></body></html>`);
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await expect(page.locator('[data-secretary-report-review]')).toHaveCount(2);
                for (const [index, form] of (await page.locator('[data-secretary-report-review]').all()).entries()) {
                    const forward = form.getByRole('radio', { name: 'Forward to Research Head', exact: true });
                    const correction = form.getByRole('radio', { name: 'Return for corrections', exact: true });
                    const instructions = form.locator('textarea[name="secretary_remarks"]');
                    await expect(forward).toBeChecked();
                    await expect(instructions).toBeHidden();
                    await expect(instructions).toBeDisabled();
                    await expect(form.getByRole('button', { name: 'Forward to Research Head', exact: true })).toBeVisible();

                    await correction.check();
                    await expect(instructions).toBeVisible();
                    await expect(instructions).toBeEnabled();
                    await expect(instructions).toHaveAttribute('required');
                    await expect(form.getByRole('button', { name: 'Return for corrections', exact: true })).toBeVisible();
                    assert.ok((await instructions.boundingBox()).height <= 90, 'Correction instructions must start compact');
                    if (width === 1440 && ! dark && index === 0) {
                        await form.screenshot({ path: resolve('storage/framework/testing/secretary-correction-choice.png') });
                    }
                    await instructions.fill('Correct the sample totals.');
                    assert.deepEqual(await form.evaluate(element => {
                        const data = new FormData(element);
                        return { decision: data.get('decision'), remarks: data.get('secretary_remarks') };
                    }), { decision: 'revision_requested', remarks: 'Correct the sample totals.' });

                    await forward.check();
                    await expect(instructions).toBeHidden();
                    await expect(instructions).toBeDisabled();
                    await expect(instructions).not.toHaveAttribute('required');
                    assert.equal(await form.evaluate(element => new FormData(element).has('secretary_remarks')), false);
                }
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('secretary pages stay focused, navigate in one tab, and work on phones and in dark mode', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchSecretaryWorkspaceTest.php', '--filter=separate dashboard|report review filters'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_SECRETARY_WORKSPACE: '1' },
    });
    const names = ['dashboard', 'projects', 'reports', 'notices', 'budgets', 'targets'];
    const reportFilters = { all: 'All pending', monitoring: 'Monitoring Tools', progress: 'Progress Reports', returned: 'Returned by Research Head' };
    const sources = Object.fromEntries([...names, ...Object.keys(reportFilters).map(type => `reports-${type}`)].map(name => [name, read(`storage/framework/testing/secretary-${name}.html`)]));
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css].map(file => read(`public/build/${file}`)).join('\n');
    const runtime = ['proposal-preview-workspace', 'proposal-paper-workspace', 'proposal-assessment-preview'].map(script).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const parser = await browser.newPage();
        const fragments = await parser.evaluate(sources => Object.fromEntries(Object.entries(sources).map(([name, source]) => {
            const doc = new DOMParser().parseFromString(source, 'text/html');
            return [name, { header: doc.querySelector('[data-workspace-header-banner]').outerHTML, nav: doc.querySelector('[data-research-secretary-navigation]').outerHTML, content: doc.querySelector('[data-secretary-workspace]').outerHTML }];
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
                    if (route.request().resourceType() === 'fetch') return route.fulfill({ contentType: 'text/html', body: '<html><body><main style="width:816px;min-height:1248px">Submitted report preview</main></body></html>' });
                    const name = url.pathname.split('/').at(-1);
                    if (!fragments[name]) return route.abort();
                    const fragment = fragments[name === 'reports' ? `reports-${url.searchParams.get('type') || 'all'}` : name];
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}\n[x-cloak]{display:none!important}.secretary-test-shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:100vh;background:${dark ? '#0b1220' : '#f5f7fa'}}.secretary-test-sidebar{padding:32px 12px;border-right:1px solid ${dark ? '#2b374b' : '#e4e7ec'};background:${dark ? '#0f172a' : 'white'}}.secretary-test-main{padding:20px 32px 32px;min-width:0}.secretary-test-main>header{margin-bottom:20px}.secretary-test-brand{display:block;margin:0 16px 40px;color:${dark ? '#f4a8b6' : '#7a0019'};font-size:24px;font-weight:800;letter-spacing:.12em}@media(max-width:900px){.secretary-test-shell{grid-template-columns:64px minmax(0,1fr)}.secretary-test-sidebar{padding:24px 6px}.secretary-test-sidebar nav :is(p,span),.secretary-test-brand{display:none}.secretary-test-sidebar nav a{padding:12px;justify-content:center}.secretary-test-main{padding:16px 12px}}</style></head><body class="font-sans" x-data><div class="secretary-test-shell"><aside class="secretary-test-sidebar"><span class="secretary-test-brand">ATHENA</span>${fragment.nav}</aside><main class="secretary-test-main"><header>${fragment.header}</header>${fragment.content}</main></div><script>${runtime}\ndocument.addEventListener('alpine:init',()=>{Alpine.store('sidebar',{open:${width >= 900},setOpen(value){this.open=value}});Alpine.data('proposalAssessmentPreview',proposalAssessmentPreview)});</script><script src="/alpine.js"></script></body></html>` });
                });
                for (const name of names) {
                    await page.goto(`http://localhost/research-secretary/${name}`);
                    await expect(page.locator('[data-secretary-workspace]')).toHaveAttribute('data-secretary-workspace', name);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${name} must fit at ${width}px in ${dark ? 'dark' : 'light'} mode`);
                    await expect(page.locator('[data-research-secretary-navigation] [aria-current="page"]')).toHaveCount(name === 'projects' ? 0 : 1);
                    await expect(page.locator('[data-research-secretary-navigation]').getByRole('link', { name: 'Projects', exact: true })).toHaveCount(0);
                    if (name === 'dashboard') {
                        assert.equal(await page.locator('[data-research-target-board]').count(), 0);
                        assert.ok(await page.locator('[data-secretary-priority-list] article').count() <= 4);
                        const summary = page.getByRole('region', { name: 'Office workload' });
                        await expect(summary.locator('dt')).toHaveCount(4);
                        assert.ok((await summary.boundingBox()).height <= (width >= 900 ? 120 : 220), 'Workload summary must leave room for report reviews');
                        await expect(page.getByRole('link', { name: 'Review reports', exact: true })).toHaveCount(1);
                        await expect(page.locator('[data-secretary-workspace]').getByRole('link', { name: /^Projects/ })).toHaveCount(0);
                    }
                    if (name === 'projects') {
                        await page.getByRole('searchbox', { name: 'Search projects' }).fill('Community');
                        await page.getByRole('button', { name: 'Find projects', exact: true }).click();
                        assert.equal(new URL(page.url()).searchParams.get('q'), 'Community');
                        await expect(page.getByRole('heading', { name: 'Community health information systems', exact: true })).toBeVisible();
                        await expect(page.locator('.secretary-project-row a')).toHaveCount(0);
                        assert.equal(await page.locator('[data-secretary-report-queue]').count(), 0);
                    }
                    if (['dashboard', 'projects', 'budgets'].includes(name)) {
                        assert.equal(await page.getByRole('link', { name: /Complete budget/ }).count(), 0);
                        assert.equal(await page.locator('#budget-worklist').count(), 0);
                    }
                    if (name === 'budgets') {
                        await expect(page.getByRole('heading', { name: 'Budget monitoring', exact: true })).toBeVisible();
                        await expect(page.getByRole('columnheader', { name: 'Reported disbursements' })).toBeVisible();
                        assert.equal(await page.locator('[data-secretary-workspace] form[method="POST"], [data-secretary-workspace] input[name="_method"]').count(), 0);
                        assert.equal(await page.locator('[data-secretary-workspace] input:not([type="search"])').count(), 0);
                    }
                    if (name === 'reports') {
                        const trigger = page.getByRole('button', { name: 'Preview PDF', exact: true }).first();
                        await trigger.click();
                        const panel = page.locator('#secretary-report-preview-panel');
                        await expect(panel).toBeVisible();
                        await expect(panel.getByRole('button', { name: 'Print', exact: true })).toBeEnabled();
                        await page.getByRole('button', { name: 'Close preview', exact: true }).click();
                        await expect(panel).toBeHidden();
                        for (const type of ['monitoring', 'returned', 'all', 'progress']) {
                            await page.getByRole('navigation', { name: 'Filter report reviews' }).getByRole('link', { name: reportFilters[type], exact: true }).click();
                            assert.equal(new URL(page.url()).searchParams.get('type') || 'all', type);
                            const selected = page.locator('[data-report-review-filters] [aria-current="page"]');
                            await expect(selected).toHaveCount(1);
                            await expect(selected).toHaveText(reportFilters[type]);
                            await expect(selected).toHaveCSS('background-color', dark ? 'rgb(255, 255, 255)' : 'rgb(17, 24, 39)');
                            await expect(selected).toHaveCSS('color', dark ? 'rgb(3, 7, 18)' : 'rgb(255, 255, 255)');
                            await expect(selected).toHaveCSS('border-radius', '12px');
                            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                            assert.ok((await selected.boundingBox()).height >= 44);
                            if (type === 'progress') {
                                assert.equal(await page.locator('[data-secretary-report-kind="monitoring"]').count(), 0);
                                assert.ok(await page.locator('[data-secretary-report-kind="progress"]').count() > 0);
                            }
                        }
                    }
                    assert.equal(popups, 0);
                    assert.equal(page.context().pages().length, 1);
                    assert.deepEqual(errors, []);
                    const actionsWithoutButtonShape = await page.locator('[data-secretary-workspace] a').evaluateAll(elements => elements
                        .filter(element => !element.closest('[data-research-target-board]'))
                        .filter(element => {
                            const style = getComputedStyle(element);
                            return style.display !== 'none' && element.getClientRects().length
                                && ((parseFloat(style.borderTopWidth) < 1 && ["rgba(0, 0, 0, 0)", "transparent"].includes(style.backgroundColor)) || parseFloat(style.borderRadius) < 4 || parseFloat(style.paddingLeft) < 8 || element.getBoundingClientRect().height < 44);
                        }).map(element => element.textContent.trim()));
                    assert.deepEqual(actionsWithoutButtonShape, [], `${name}: actions must have a visible button boundary and usable padding`);
                    if (name === 'dashboard' && width === 1440 && !dark) await page.locator('[data-secretary-report-queue]').screenshot({ path: resolve('storage/framework/testing/secretary-review-buttons.png') });
                    if (['dashboard', 'projects', 'reports', 'budgets'].includes(name)) await page.screenshot({ path: resolve(`storage/framework/testing/secretary-${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
