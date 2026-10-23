import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = path => readFileSync(resolve(path), 'utf8');

test('rendered RMS reports keep quarter downloads, editable categories and validation usable across sizes and themes', async () => {
    if (process.env.ATHENA_RMS_UI_FIXTURES_READY !== '1') {
        for (const [file, filter] of [
            ['ReportFolderLibraryTest.php', 'RMS overview groups all four quarters by year'],
            ['ResearchHeadRmsReportTest.php', 'RMS editor exposes labeled|invalid input reopens|missing colleges clearly shown'],
        ]) {
            execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', `tests/Feature/${file}`, `--filter=${filter}`], {
                encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
                env: { ...process.env, ATHENA_EXPORT_RMS_UI: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
            });
        }
    }
    const fixtures = Object.fromEntries(['overview', 'editor', 'editor-errors', 'editor-pending'].map(name => {
        const path = resolve(`storage/framework/testing/rms-${name}.html`);
        assert.ok(existsSync(path), `Export the missing RMS ${name} fixture with ATHENA_EXPORT_RMS_UI=1 before running this browser test.`);
        return [name, readFileSync(path, 'utf8')];
    }));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const modules = ['research-assistant-markdown', 'proposal-paper-autosave', 'research-assistant-report-changes', 'assistant-report-editor', 'rms-quarterly-report-form']
        .map(name => read(`resources/js/${name}.js`).replace(/^import .+;\r?\n/gm, '').replace(/^export /gm, '')).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    mkdirSync(resolve('storage/framework/testing'), { recursive: true });

    try {
        for (const width of [390, 1024, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                let mutations = 0;
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                const fragments = await page.evaluate(sources => Object.fromEntries(Object.entries(sources).map(([name, source]) => {
                    const rendered = new DOMParser().parseFromString(source, 'text/html');
                    const header = rendered.querySelector('[data-page-header-container]');
                    const workspace = rendered.querySelector('[data-rms-report-overview], [data-rms-quarterly-reports]');
                    if (!header || !workspace) throw new Error(`Missing RMS header or workspace in ${name} fixture.`);
                    return [name, `${header.outerHTML}<main class="${rendered.querySelector('main').className}"><div class="athena-page-content w-full">${workspace.outerHTML}</div></main>`];
                })), fixtures);
                await page.route('**/*', route => {
                    if (route.request().method() !== 'GET') {
                        mutations++;
                        return route.abort();
                    }
                    const name = new URL(route.request().url()).pathname.match(/^\/rms-(overview|editor|editor-errors|editor-pending)-test$/)?.[1];
                    if (!name) return route.abort();
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}\n[x-cloak]{display:none!important}</style></head><body class="bg-slate-50 font-sans dark:bg-slate-950"><div class="flex min-h-screen min-w-0 flex-col pl-[76px] sm:pl-[280px]">${fragments[name]}</div></body></html>` });
                });
                const open = async name => {
                    await page.goto(`http://localhost/rms-${name}-test`);
                    await page.addScriptTag({ content: `${modules}\ndocument.addEventListener('alpine:init', () => { Alpine.data('rmsQuarterlyReportForm', rmsQuarterlyReportForm); Alpine.data('assistantReportEditor', assistantReportEditor); }); document.addEventListener('submit', event => { event.preventDefault(); window.submittedRmsData = Object.fromEntries(new FormData(event.target, event.submitter)); });` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                };
                const fits = async () => {
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `RMS page must fit at ${width}px in ${dark ? 'dark' : 'light'} mode.`);
                    assert.deepEqual(errors, []);
                };

                await open('overview');
                const overview = page.locator('[data-rms-report-overview]');
                await expect(page.getByRole('heading', { name: 'RMS quarterly reports', exact: true })).toBeVisible();
                await expect(overview.getByLabel('Reporting year', { exact: true })).toBeVisible();
                await overview.getByLabel('Reporting year', { exact: true }).selectOption('2025');
                await expect.poll(() => page.evaluate(() => window.submittedRmsData?.year)).toBe('2025');
                await overview.getByLabel('Reporting year', { exact: true }).selectOption('');
                await expect.poll(() => page.evaluate(() => window.submittedRmsData?.year)).toBe('');
                await expect(overview.getByRole('button', { name: 'Apply filter', exact: true })).toHaveCount(0);
                await expect(overview.locator('[data-library-view-surface], [role="menu"], input[type="search"]')).toHaveCount(0);
                await expect(overview.locator('[data-rms-report-period]')).toHaveCount(8);
                await expect(overview.getByRole('link', { name: /^Download Excel for / })).toHaveCount(1);
                await expect(overview.getByRole('button', { name: /^Download Excel for / })).toHaveCount(7);
                for (const row of await overview.locator('[data-rms-report-period]').all()) {
                    const [, year, quarter] = (await row.getAttribute('data-rms-report-period')).match(/^(\d+)-Q(\d)$/);
                    const ready = year === '2026' && quarter === '3';
                    for (const label of ready ? ['Open report', 'Download Excel'] : ['Open report']) {
                        const link = row.getByRole('link', { name: `${label} for ${year} Q${quarter}`, exact: true });
                        await expect(link).toBeVisible();
                        const url = new URL(await link.getAttribute('href'));
                        assert.equal(url.searchParams.get('year'), year);
                        assert.equal(url.searchParams.get('quarter'), quarter);
                        assert.ok((await link.boundingBox()).height >= 44, 'Quarter actions need 44px touch targets.');
                    }
                    const readiness = row.locator('[data-rms-readiness]');
                    const statuses = readiness.getByRole('list', { name: `College submission status for ${year} Q${quarter}`, exact: true }).getByRole('listitem');
                    await expect(readiness).toHaveAttribute('data-rms-readiness', ready ? 'ready' : 'waiting');
                    await expect(statuses).toHaveCount(6);
                    if (ready) {
                        await expect(row).toContainText('6 of 6 college reports received');
                        await expect(readiness).toContainText('All college reports received');
                        for (const status of await statuses.all()) await expect(status).toContainText('Received');
                    } else {
                        const download = row.getByRole('button', { name: `Download Excel for ${year} Q${quarter}`, exact: true });
                        await expect(download).toBeVisible();
                        await expect(download).toBeDisabled();
                        assert.equal(await download.getAttribute('href'), null);
                        await expect(download).toHaveAttribute('aria-describedby', `rms-readiness-${year}-${quarter}`);
                        await expect(row.locator(`#rms-readiness-${year}-${quarter}`)).toContainText('Waiting for all colleges to submit');
                        await expect(row).toContainText('0 of 6 college reports received');
                        for (const status of await statuses.all()) await expect(status).toContainText('Pending');
                        assert.ok((await download.boundingBox()).height >= 44, 'Disabled downloads need readable touch targets.');
                    }
                }
                await expect(overview.locator('[data-rms-report-year]').first()).toHaveCSS('background-color', dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                await fits();
                if (width === 1440 && !dark) await page.screenshot({ path: resolve('storage/framework/testing/rms-overview-desktop.png'), fullPage: true });

                await open('editor');
                const editor = page.locator('[data-rms-quarterly-reports]');
                const form = editor.locator('#rms-report-form');
                const navigation = form.getByRole('navigation', { name: 'Report sections', exact: true });
                const button = section => navigation.locator(`[aria-controls="rms-${section}"]`);
                const panel = section => form.locator(`#rms-${section}`);
                const categories = ['presentations', 'publications', 'ip_assets'];
                const active = async selected => {
                    for (const section of categories) {
                        await expect(button(section)).toHaveAttribute('aria-pressed', String(section === selected));
                        if (section === selected) await expect(panel(section)).toBeVisible();
                        else await expect(panel(section)).toBeHidden();
                    }
                    await fits();
                };
                await expect(page.getByRole('heading', { name: 'RMS quarterly report · Q3 2026', exact: true })).toBeVisible();
                await expect(page.getByRole('link', { name: 'All RMS reports', exact: true })).toBeVisible();
                await expect(editor.getByRole('button', { name: 'Save draft', exact: true })).toBeVisible();
                const download = editor.getByRole('button', { name: 'Save & download Excel', exact: true });
                await expect(download).toBeVisible();
                await expect(download).toBeEnabled();
                await expect(editor.locator('[data-rms-readiness]')).toHaveAttribute('data-rms-readiness', 'ready');
                await expect(editor).toContainText('6 of 6 college reports received');
                await active('presentations');
                await expect(editor.locator('section').first()).toHaveCSS('background-color', dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                if (width === 390 && !dark) await page.screenshot({ path: resolve('storage/framework/testing/rms-editor-mobile.png'), fullPage: true });
                const changed = [];
                for (const section of categories) {
                    await button(section).click();
                    await active(section);
                    await panel(section).locator('details > summary').first().click();
                    const field = panel(section).locator('textarea[name]').first();
                    await expect(field).toBeVisible();
                    const value = `Retained ${section} details`;
                    await field.fill(value);
                    changed.push({ name: await field.getAttribute('name'), value });
                    await fits();
                }
                await button('presentations').click();
                await active('presentations');
                await expect(panel('presentations').locator('textarea[name]').first()).toHaveValue(changed[0].value);
                await download.click();
                await expect.poll(() => page.evaluate(() => window.submittedRmsData?.intent)).toBe('download');
                const submitted = await page.evaluate(() => window.submittedRmsData);
                const rows = JSON.parse(submitted.row_details_json);
                assert.equal(Object.keys(rows).length, 3, 'Serialization must include hidden report categories.');
                for (const { name, value } of changed) {
                    const [, key, field] = name.match(/^row_details\[([^\]]+)\]\[([^\]]+)\]$/);
                    assert.equal(rows[key][field], value);
                    assert.equal(submitted[name], undefined);
                }
                assert.equal(submitted.reporting_year, '2026');
                assert.equal(submitted.reporting_quarter, '3');
                assert.equal(submitted.form_complete, '1');
                assert.equal(mutations, 0);

                await open('editor-errors');
                await expect(page.getByRole('alert')).toContainText('Check the report before saving.');
                await active('publications');
                const invalid = panel('publications').locator('[aria-invalid="true"]');
                await expect(invalid).toBeVisible();
                await expect(invalid).toHaveValue('not-a-link');
                await expect(editor.getByRole('button', { name: 'Save & download Excel', exact: true })).toBeDisabled();
                await fits();

                await open('editor-pending');
                await expect(editor.getByRole('button', { name: 'Save draft', exact: true })).toBeEnabled();
                const pendingDownload = editor.getByRole('button', { name: 'Save & download Excel', exact: true });
                await expect(pendingDownload).toBeVisible();
                await expect(pendingDownload).toBeDisabled();
                await expect(pendingDownload).toHaveAttribute('aria-describedby', 'rms-export-readiness');
                await expect(editor.locator('#rms-export-readiness')).toContainText('Waiting for all colleges to submit');
                await expect(editor).toContainText('0 of 6 college reports received');
                await expect(editor.locator('[data-rms-readiness]')).toHaveAttribute('data-rms-readiness', 'waiting');
                const pendingStatuses = editor.getByRole('list', { name: 'College submission status', exact: true }).getByRole('listitem');
                await expect(pendingStatuses).toHaveCount(6);
                for (const status of await pendingStatuses.all()) await expect(status).toContainText('Pending');
                assert.equal(await page.evaluate(() => window.submittedRmsData), undefined);
                assert.equal(mutations, 0);
                await fits();
                if (width === 390 && !dark) await page.screenshot({ path: resolve('storage/framework/testing/rms-editor-pending-mobile.png'), fullPage: true });
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
