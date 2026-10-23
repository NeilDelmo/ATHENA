import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('Research Head clipboard menus select, cut, copy and paste college folders across screen sizes', async () => {
    execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/CollegeReportLibraryWorkspaceTest.php', '--filter=college library renders selectable'], {
        encoding: 'utf8', maxBuffer: 1024 * 1024,
        env: { ...process.env, ATHENA_EXPORT_COLLEGE_LIBRARY: '1' },
    });
    const html = readFileSync(resolve('storage/framework/testing/college-library.html'), 'utf8');
    const css = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(['app', 'project-workspace', 'report-library'].map(name => readFileSync(resolve(`resources/css/${name}.css`), 'utf8')).join('\n'), { from: undefined });
    const modules = ['report-library', 'college-report-library'].map(name => readFileSync(resolve(`resources/js/${name}.js`), 'utf8')
        .replace(/^import .*;\r?\n/gm, '').replaceAll('export ', '')).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                const saves = [];
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                const fragment = await page.evaluate(source => new DOMParser().parseFromString(source, 'text/html').querySelector('[data-report-library]').outerHTML, html);
                await page.route('**/*', async route => {
                    if (route.request().method() === 'PUT') {
                        const payload = route.request().postDataJSON();
                        saves.push(payload);
                        return route.fulfill({ json: { workspace: { ...payload, revision: payload.revision + 1 } } });
                    }
                    if (new URL(route.request().url()).pathname !== '/college-library-browser') return route.abort();
                    return route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css.css}\n[x-cloak]{display:none!important}</style><script>${modules}\ndocument.addEventListener('alpine:init', () => Alpine.data('collegeReportLibrary', collegeReportLibrary));</script></head><body>${fragment}<script src="http://localhost/alpine-test.js"></script></body></html>` });
                });
                await page.route('**/alpine-test.js', route => route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('node_modules/alpinejs/dist/cdn.min.js'), 'utf8') }));
                await page.goto('http://localhost/college-library-browser');
                const folder = name => page.locator('a[data-library-item]').filter({ has: page.locator('.report-library-name', { hasText: new RegExp(`^${name}$`) }) });
                for (const name of ['Open', 'Cut', 'Copy', 'Paste']) {
                    await expect(page.getByRole('button', { name, exact: true }).locator('svg')).toHaveCount(1);
                }
                await folder('CICS').click({ button: 'right' });
                const menu = page.getByRole('menu', { name: 'File actions', exact: true });
                await expect(menu.getByRole('menuitem', { name: /^Cut/ })).toBeEnabled();
                await expect(menu.getByRole('menuitem', { name: /^Copy/ })).toBeEnabled();
                await expect(menu.getByRole('menuitem', { name: /^Paste/ })).toBeDisabled();
                for (const name of ['Cut', 'Copy', 'Paste']) {
                    await expect(menu.getByRole('menuitem', { name: new RegExp(`^${name}`) }).locator('svg:visible')).toHaveCount(1);
                }
                await menu.getByRole('menuitem', { name: /^Cut/ }).click();
                await expect(folder('CICS')).toHaveClass(/opacity-40/);
                await folder('CTE').click({ button: 'right' });
                await expect(menu.getByRole('menuitem', { name: /^Paste/ })).toBeEnabled();
                const sourceId = await folder('CICS').getAttribute('data-library-item');
                const targetId = await folder('CTE').getAttribute('data-library-item');
                await Promise.all([page.waitForEvent('framenavigated'), menu.getByRole('menuitem', { name: /^Paste/ }).click()]);
                await expect.poll(() => saves.length).toBe(1);
                assert.equal(saves[0].items.find(item => item.id === sourceId).parent_id, targetId);
                await page.waitForLoadState();
                await expect(folder('CICS')).not.toHaveClass(/opacity-40/);
                await folder('CICS').click();
                await page.keyboard.press('Control+c');
                await Promise.all([page.waitForEvent('framenavigated'), page.keyboard.press('Control+v')]);
                await expect.poll(() => saves.length).toBe(2);
                assert.ok(saves[1].items.some(item => item.id !== sourceId && item.source_id === sourceId && item.name === 'CICS (copy)'));
                await page.waitForLoadState();
                await folder('CICS').click({ button: 'right' });
                await menu.getByRole('menuitemradio', { name: 'Details', exact: true }).click();
                await expect(page.locator('[data-report-library]')).toHaveAttribute('data-view', 'list');
                const details = folder('CICS').locator('.report-library-details');
                await expect(details.getByText('Dr. Elena Santos', { exact: true })).toBeVisible();
                await expect(details).toContainText('Submitted');
                await expect(details).toContainText('Dr. Elena Santos');
                await expect(details).toContainText('Oct 20, 2026');
                await expect(folder('CTE').locator('.report-library-details')).toContainText('No reports');
                if (width >= 1024) {
                    await Promise.all([page.waitForEvent('framenavigated'), page.getByRole('button', { name: 'Sort by Name', exact: true }).click()]);
                    assert.equal(new URL(page.url()).searchParams.get('sort'), 'name');
                    assert.equal(new URL(page.url()).searchParams.get('direction'), 'desc');
                    await page.waitForLoadState();
                }
                await Promise.all([page.waitForEvent('framenavigated'), page.getByLabel('Sort items by', { exact: true }).selectOption('submitted_at')]);
                assert.equal(new URL(page.url()).searchParams.get('sort'), 'submitted_at');
                assert.equal(new URL(page.url()).searchParams.get('direction'), 'desc');
                await page.waitForLoadState();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                mkdirSync(resolve('storage/framework/testing'), { recursive: true });
                await page.screenshot({ path: resolve(`storage/framework/testing/college-library-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
