import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = path => readFileSync(resolve(path), 'utf8').replaceAll('\r\n', '\n');
const app = read('resources/js/app.js');
const previewSource = app.slice(app.indexOf('const documentPreviewForm ='), app.indexOf("Alpine.data('monitoringToolForm',"));
const controllers = app.slice(app.indexOf("Alpine.data('monitoringToolForm',"), app.indexOf("Alpine.data('noticeToProceedForm',"));
const datePicker = app.slice(app.indexOf("Alpine.data('datePicker',"), app.indexOf("Alpine.data('dateTimePicker',"));
const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
const previewHelpers = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '')
    + read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');

test('the real monitoring deadline alert fits both themes and activity warnings react to completion', async () => {
    const fixtureDirectory = resolve('storage/app/preview-zoom-qa');
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/MonitoringObjectiveAlertTest.php', '--filter=the monitoring editor shows official overdue alerts'], {
        encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
        env: { ...process.env, ATHENA_MONITORING_ALERT_BROWSER_FIXTURE_DIRECTORY: fixtureDirectory },
    });
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}</style><body data-app-shell><main class="mx-auto max-w-[90rem] p-4">${readFileSync(resolve(fixtureDirectory, 'monitoring-objective-alert.html'), 'utf8')}</main></body></html>`);
                await page.addScriptTag({ content: `${previewHelpers}\n${previewSource}\n${dateHelpers}
                    window.fetch=async()=>({ok:true,status:200,json:async()=>({draft_version:1})});
                    document.addEventListener('alpine:init',()=>{${datePicker}\n${controllers}});` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                const alert = page.locator('[data-monitoring-work-plan-alert]');
                await alert.getByText('Work Plan targets need attention', { exact: true }).waitFor();
                await alert.locator('summary').click();
                assert.equal(await alert.getByText('Establish the baseline', { exact: true }).isVisible(), true);
                assert.equal(await alert.getByText('Build the prototype', { exact: true }).count(), 0, 'Multi-month activities are not overdue before their final target date');
                assert.ok((await alert.boundingBox()).width <= width - 16);
                const activities = page.locator('[data-monitoring-activity]');
                await activities.first().waitFor();
                await activities.first().locator('[data-monitoring-activity-overdue]').waitFor();
                assert.equal(await activities.nth(1).locator('[data-monitoring-activity-overdue]').isVisible(), false);
                const evidence = activities.first().locator('input[type="file"]');
                await evidence.setInputFiles({ name: 'baseline-evidence.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.7\nBaseline evidence') });
                await activities.first().locator('[data-monitoring-activity-overdue]').waitFor({ state: 'hidden' });
                await evidence.setInputFiles([]);
                await activities.first().locator('[data-monitoring-activity-overdue]').waitFor();
                assert.equal(await alert.isVisible(), true, 'Draft edits do not clear the official submitted-progress alert');
                await page.evaluate(() => scrollTo(0, 0));
                await page.screenshot({ path: resolve(fixtureDirectory, `monitoring-objective-alert-${width}-${dark ? 'dark' : 'light'}.png`) });
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
