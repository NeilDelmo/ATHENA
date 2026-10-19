import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('trash archive and automatic manual results fit both themes and retain keyboard actions', { timeout: 60000 }, async context => {
    const read = path => readFileSync(resolve(path), 'utf8');
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css]
        .map(file => read(`public/build/${file}`)).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const parser = await browser.newPage();
        const fixtures = {};
        for (const [name, filename, selector] of [
            ['trash', 'proposal-trash.html', 'main'],
            ['manual-pending', 'manual-checks-pending.html', '[data-manual-checks]'],
            ['manual-completed', 'manual-checks-completed.html', '[data-manual-checks]'],
            ['archive', 'project-archive-page.html', '[data-page-header-variant]'],
        ]) {
            fixtures[name] = await parser.evaluate(({ html, selector }) => new DOMParser().parseFromString(html, 'text/html').querySelector(selector)?.outerHTML,
                { html: read(`storage/framework/testing/${filename}`), selector });
            assert.ok(fixtures[name], `Rendered ${name} fixture is available`);
        }
        await parser.close();
        for (const width of [390, 1280]) for (const dark of [false, true]) for (const [name, html] of Object.entries(fixtures)) {
            context.diagnostic(`${name} ${width}px ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            await page.route('**/*', route => route.abort());
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}\n[x-cloak]{display:none!important}</style></head><body class="bg-[#F5F7FA] dark:bg-slate-950"><div class="pl-[76px] sm:pl-[280px]"><div class="p-4 sm:p-6">${html}</div></div></body></html>`);
            if (name === 'trash') {
                const restore = page.getByRole('button', { name: 'Restore', exact: true });
                await expect(restore).toBeVisible();
                await expect(restore.locator('xpath=..')).toHaveAttribute('method', 'POST');
                await expect(restore.locator('xpath=..').locator('[name="_token"]')).toHaveCount(1);
                await restore.focus();
                await expect(restore).toBeFocused();
            } else if (name === 'manual-pending') {
                await expect(page.locator('[data-manual-checks]')).toContainText('Checks queued automatically');
                await expect(page.locator('[data-manual-checks] button, [data-manual-checks] input')).toHaveCount(0);
            } else if (name === 'manual-completed') {
                const section = page.locator('[data-manual-checks]');
                await expect(section).toContainText('Checks completed');
                await expect(section).toContainText('No matching evidence found');
                const details = section.locator('details').last();
                await details.locator('summary').focus();
                await page.keyboard.press('Enter');
                await expect(details).toHaveAttribute('open', '');
                await expect(details.getByRole('link').first()).toHaveAttribute('href', /research-manuals\/[a-f0-9]{64}#page=2$/);
            } else {
                const archive = page.locator('[data-project-archive]');
                await expect(archive).toBeVisible();
                await expect(archive).toHaveAttribute('href', /\/topics\/\d+\/archive$/);
                assert.equal(await archive.getAttribute('wire:navigate'), null);
                await archive.focus();
                await expect(archive).toBeFocused();
            }
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${name} must not overflow at ${width}px`);
            await page.screenshot({ path: resolve(`storage/framework/testing/${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            await page.close();
        }
    } finally { await browser.close(); }
});
