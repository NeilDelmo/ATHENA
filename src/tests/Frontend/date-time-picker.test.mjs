import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('reopening a deadline preserves its saved minute when changing the hour', async () => {
    const html = execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Blade::render('<x-date-time-picker name="closes_at" value="2026-11-01T23:59" />');
    `], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const controller = app.slice(app.indexOf("Alpine.data('dateTimePicker',"), app.indexOf('function normalizeIsoDate('));
    const helpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<style>[x-cloak]{display:none!important}</style>${html}`);
        await page.addScriptTag({ content: `${helpers}\ndocument.addEventListener('alpine:init', () => { ${controller} });` });
        await page.addScriptTag({ path: 'node_modules/alpinejs/dist/cdn.js' });
        await expect(page.getByLabel('Minute')).toHaveValue('59');
        await page.getByRole('combobox').first().click();
        await page.getByLabel('Hour', { exact: true }).selectOption('22');
        await expect(page.locator('[name="closes_at"]')).toHaveValue('2026-11-01T22:59');
        assert.equal(await page.getByLabel('Minute').inputValue(), '59');
    } finally {
        await browser.close();
    }
});
