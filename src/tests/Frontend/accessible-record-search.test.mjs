import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('shared search entry and results fit mobile and desktop with native keyboard controls', { timeout: 120000 }, async context => {
    if (process.env.ATHENA_SEARCH_FIXTURE_READY !== '1') {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/AccessibleRecordSearchTest.php', '--filter=search paginates before hydrating'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_SEARCH: '1' },
        });
    }
    const fixturePath = resolve('storage/framework/testing/search-results.html');
    assert.ok(existsSync(fixturePath), 'Export the fixture with ATHENA_EXPORT_SEARCH=1 php artisan test --compact tests/Feature/AccessibleRecordSearchTest.php');
    const fixture = readFileSync(fixturePath, 'utf8');
    const topbar = fixture.match(/<nav data-app-topbar[\s\S]*?<\/nav>/)?.[0];
    const main = fixture.match(/<main[\s\S]*?<\/main>/)?.[0];
    assert.ok(topbar && main, 'Use the actual rendered shared header and search page');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || (existsSync(edge) ? edge : undefined) });
    try {
        for (const width of [390, 768, 1280]) for (const dark of [false, true]) {
            context.diagnostic(`${width}px ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            await page.route('**/*', route => route.abort());
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}\n[x-cloak]{display:none!important}</style></head><body class="bg-[#F5F7FA] dark:bg-slate-950"><div class="min-h-screen pl-[76px] sm:pl-[280px]">${topbar}${main}</div></body></html>`);
            const query = page.getByLabel('Search words', { exact: true });
            await expect(query).toHaveValue('Mangrove');
            await query.focus();
            await expect(query).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(page.getByLabel('Look in', { exact: true })).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(page.getByRole('button', { name: 'Search', exact: true })).toBeFocused();
            await expect(page.getByRole('search', { name: 'Search accessible records' })).toHaveAttribute('method', 'GET');
            const clear = page.getByRole('link', { name: 'Clear search' });
            assert.equal(new URL(await clear.getAttribute('href')).search, '');
            const nextPage = page.locator('a[href*="page=2"]').first();
            const nextUrl = new URL(await nextPage.getAttribute('href'));
            assert.equal(nextUrl.searchParams.get('q'), 'Mangrove');
            assert.equal(nextUrl.searchParams.get('category'), 'projects');
            await expect(page.locator('[data-search-result]').first()).toContainText('<img src=x onerror=alert(1)>');
            await expect(page.locator('[data-record-search] img')).toHaveCount(0);
            if (width < 1024) {
                await expect(page.getByRole('link', { name: 'Search ATHENA', exact: true })).toBeVisible();
            } else {
                await expect(page.getByRole('search', { name: 'Quick search' })).toBeVisible();
                await expect(page.locator('#header-search-query')).toHaveValue('Mangrove');
            }
            const bounds = await page.locator('.athena-header-actions').boundingBox();
            assert.ok(bounds.x >= 76 && bounds.x + bounds.width <= width + 1, 'Header actions stay within the content area');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'No horizontal scrolling');
            if (width !== 768) {
                await page.screenshot({ path: resolve(`storage/framework/testing/search-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            }
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
