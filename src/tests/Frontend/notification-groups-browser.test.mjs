import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('grouped inbox preserves accessible history filters and explicit group reads across layouts', { timeout: 120000 }, async context => {
    if (process.env.ATHENA_NOTIFICATION_GROUPS_FIXTURE_READY !== '1') {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/NotificationGroupingTest.php', '--filter=related project updates show'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_NOTIFICATION_GROUPS: '1' },
        });
    }
    const fixture = readFileSync(resolve('storage/framework/testing/notification-groups.html'), 'utf8');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const extractor = await browser.newPage();
        const inbox = await extractor.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('[data-notification-inbox]').outerHTML, fixture);
        await extractor.close();
        for (const width of [390, 1280]) for (const dark of [false, true]) {
            context.diagnostic(`${width}px ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}\n[x-cloak]{display:none!important}</style></head><body class="bg-[#F5F7FA] dark:bg-slate-950"><main class="pl-[76px] sm:pl-[280px]">${inbox}</main></body></html>`);
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
            await expect(page.locator('[data-notification-item]')).toHaveCount(1);
            const group = page.locator('[data-notification-item]');
            await expect(group).toContainText('2 updates · 1 unread notifications');
            const history = group.locator('[data-notification-history]');
            const summary = history.locator('summary');
            await summary.focus();
            await page.keyboard.press('Enter');
            await expect(history).toHaveAttribute('open', '');
            await expect(history).toContainText('Earlier review feedback.');
            await expect(history.locator('form')).toHaveCount(2);
            await expect(history.locator('[name="notification_ids[]"]')).toHaveCount(0);
            await expect(group.locator(':scope > form [name="notification_ids[]"]')).toHaveCount(2);
            await expect(group.locator(':scope > form [name="_token"]')).toHaveCount(1);
            await page.getByRole('searchbox', { name: 'Search notifications', exact: true }).fill('Earlier review');
            await expect(group).toBeVisible();
            await page.getByRole('searchbox', { name: 'Search notifications', exact: true }).fill('No matching term');
            await expect(group).toBeHidden();
            await page.getByRole('button', { name: 'Clear filters', exact: true }).click();
            await expect(group).toBeVisible();
            await page.getByRole('button', { name: /^Needs your action/ }).click();
            await expect(page.locator('[data-workspace-action-items]')).toBeVisible();
            await expect(page.locator('[data-workspace-action-items]')).toContainText('Address proposal revisions');
            await page.getByRole('button', { name: 'All activity', exact: true }).click();
            await expect(summary).toBeVisible();
            const overflow = await page.evaluate(() => [...document.querySelectorAll('*')].filter(element => {
                const box = element.getBoundingClientRect();
                return box.width && box.right > innerWidth + 1;
            }).slice(0, 12).map(element => ({ tag: element.tagName, class: element.className, width: element.getBoundingClientRect().width })));
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Inbox must not scroll horizontally: ${JSON.stringify(overflow)}`);
            assert.deepEqual(errors, []);
            await page.screenshot({ path: resolve(`storage/framework/testing/notification-groups-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
