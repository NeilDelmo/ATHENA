import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('analytics KPI and chart clicks morph matching records, focus results, and keep responsive layouts', async () => {
    if (process.env.ATHENA_ANALYTICS_FIXTURE_READY !== '1') {
        execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ResearchHeadAnalyticsPresentationTest.php', '--filter=analytics drilldowns return'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_EXPORT_ANALYTICS: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
        });
    }
    const fixtures = JSON.parse(readFileSync(resolve('storage/framework/testing/analytics-interactions.json'), 'utf8'));
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const runtime = readFileSync(resolve('vendor/livewire/livewire/dist/livewire.esm.js'), 'utf8').replace(/export \{[\s\S]*?\};/, '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const errors = [];
    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                for (const fixture of fixtures) {
                    const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                    page.on('pageerror', error => errors.push(error.message));
                    let requests = 0;
                    await page.route('**/*', async route => {
                        const request = route.request();
                        if (request.method() === 'POST') {
                            const payload = request.postDataJSON();
                            if (requests === 1 && fixture.label === 'faculty') {
                                assert.equal(payload.components[0].updates.showFaculty, false);
                                requests++;
                                await route.fulfill({ contentType: 'application/json', body: JSON.stringify(fixture.close_response) });
                                return;
                            }
                            assert.deepEqual(payload.components[0].calls.map(({ method, params }) => ({ method, params })), [{ method: fixture.method, params: fixture.params }]);
                            requests++;
                            await route.fulfill({ contentType: 'application/json', body: JSON.stringify(fixture.response) });
                        } else if (request.url().includes('/analytics-browser-test')) {
                            await route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head><body class="bg-slate-50 font-sans dark:bg-slate-950"><main class="mx-auto max-w-7xl p-4 sm:p-6">${fixture.initial}</main></body></html>` });
                        } else {
                            await route.abort();
                        }
                    });
                    await page.goto('http://localhost/analytics-browser-test?academicYear=2026-2027');
                    await page.addScriptTag({ content: `window.livewireScriptConfig = { uri: '/livewire/update', csrf: 'fixture' }; (() => { ${runtime}\nwindow.Livewire.start(); })();` });
                    let control;
                    if (fixture.label === 'month') {
                        control = page.getByRole('button', { name: /^Sep 2026: 1 new proposals/ });
                    } else if (fixture.label === 'pipeline') {
                        control = page.locator('[aria-labelledby="pipeline-heading"]').getByRole('button', { name: /^Submitted/ });
                    } else if (fixture.label === 'distribution') {
                        control = page.locator('[data-project-distribution-chart]').getByRole('button', { name: /^Filter Ongoing projects/ });
                    } else {
                        const labels = { review: 'Awaiting your review', active: 'Active projects', delayed: 'Delayed / overdue', completed: 'Completed projects', faculty: 'Faculty in research' };
                        control = page.locator('[data-dashboard-kpi-band]').getByRole('button', { name: new RegExp(`^${labels[fixture.label]}`) });
                    }
                    if (fixture.label === 'delayed') {
                        await control.focus();
                        await page.keyboard.press('Enter');
                    } else {
                        await control.click();
                    }
                    const results = page.locator(`#${fixture.target}`);
                    await expect(results.getByRole('heading', { name: fixture.heading, exact: true })).toBeVisible();
                    await expect(results).toBeFocused();
                    for (const text of fixture.records) await expect(results).toContainText(text);
                    assert.equal(requests, 1, `${fixture.label} should make one action request`);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${fixture.label} overflows at ${width}px`);
                    assert.ok((await results.boundingBox()).y >= -1 && (await results.boundingBox()).y < 220, `${fixture.label} must scroll the result heading into view`);
                    await expect(control).toHaveAttribute('aria-pressed', 'true');
                    if (fixture.label === 'delayed') {
                        await expect(results).not.toContainText('Campus biodiversity survey');
                        await expect(results).toContainText('14 days overdue');
                        assert.ok((await results.boundingBox()).y < (await page.locator('#monthly-trend-heading').boundingBox()).y);
                    }
                    if (fixture.label === 'faculty') await expect(results).not.toContainText('Archived completed study');
                    if (process.env.ANALYTICS_SCREENSHOT_DIRECTORY && ['delayed', 'faculty'].includes(fixture.label)) {
                        mkdirSync(process.env.ANALYTICS_SCREENSHOT_DIRECTORY, { recursive: true });
                        await page.screenshot({ path: resolve(process.env.ANALYTICS_SCREENSHOT_DIRECTORY, `${fixture.label}-${width}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    if (fixture.label === 'faculty') {
                        await results.getByRole('button', { name: 'Close researcher list' }).click();
                        await expect(results).toHaveCount(0);
                        await expect(control).toBeFocused();
                        await expect(control).toHaveAttribute('aria-pressed', 'false');
                        assert.equal(requests, 2);
                    }
                    await page.close();
                }
            }
        }
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
