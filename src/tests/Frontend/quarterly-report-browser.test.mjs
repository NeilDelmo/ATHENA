import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('the rendered coordinator form submits native rows and files without losing fields in both themes and sizes', async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchCoordinatorReportTest.php', '--filter=the quarterly report has editable manual fields'], {
        encoding: 'utf8', maxBuffer: 1024 * 1024,
        env: { ...process.env, ATHENA_EXPORT_QUARTERLY_REPORT: '1' },
    });
    const source = readFileSync(resolve('storage/framework/testing/college-quarterly-report.html'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const modules = ['document-writing-toolbar', 'quarterly-report-form', 'proposal-preview-workspace', 'proposal-paper-workspace', 'proposal-assessment-preview', 'report-paper-preview'].map(name => readFileSync(resolve(`resources/js/${name}.js`), 'utf8')
        .replace(/^import .*;\r?\n/gm, '').replaceAll('export ', '')).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                const fragment = await page.evaluate(source => {
                    const document = new DOMParser().parseFromString(source, 'text/html');
                    document.querySelectorAll('[data-assistant-report-editor]').forEach(editor => editor.remove());
                    return document.querySelector('[data-quarterly-reports]').outerHTML;
                }, source);
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.css}\n${readFileSync(resolve('resources/css/proposal-paper-workspace.css'), 'utf8')}\n[x-cloak]{display:none!important}</style></head><body><main class="p-4">${fragment}</main></body></html>`);
                await page.addScriptTag({ content: `${modules}\ndocument.addEventListener('alpine:init', () => { Alpine.data('quarterlyReportForm', quarterlyReportForm); Alpine.data('reportPaperPreview', reportPaperPreview); });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                for (const name of ['Add presentation', 'Add publication', 'Add seminar or training', 'Add research project', 'Add IP asset']) {
                    await page.getByRole('button', { name, exact: true }).click();
                }
                await page.locator('[name="manual_research[0][title]"]').fill('Externally funded research');
                await page.locator('[name="publications[0][event]"]').fill('Journal of Applied Research');
                await page.evaluate(() => {
                    const form = document.querySelector('#quarterly-report-form');
                    for (const field of form.querySelectorAll('[required]')) {
                        if (field.value) continue;
                        field.value = field.tagName === 'SELECT' ? [...field.options].find(option => option.value)?.value
                            : field.type === 'date' ? '2026-08-15' : 'Research details';
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    form.addEventListener('submit', event => {
                        event.preventDefault();
                        const data = new FormData(form, event.submitter);
                        window.submittedReport = {
                            details: JSON.parse(data.get('report_details_json')),
                            intent: data.get('intent'), complete: data.get('form_complete'), files: data.get('evidence_file_count'), draftToken: data.get('draft_token'),
                            photo: data.get('evidence_images[]').name, names: [...data.keys()],
                        };
                    });
                });
                await page.locator('#evidence-images').setInputFiles({ name: 'proof.png', mimeType: 'image/png', buffer: Buffer.from('proof') });
                await page.getByRole('button', { name: 'Save draft', exact: true }).click();
                await expect.poll(() => page.evaluate(() => window.submittedReport?.intent)).toBe('save');
                const saved = await page.evaluate(() => window.submittedReport);
                for (const section of ['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets']) assert.equal(saved.details[section].length, 1);
                assert.equal(saved.details.manual_research[0].title, 'Externally funded research');
                assert.equal(saved.details.publications[0].event, 'Journal of Applied Research');
                assert.equal(saved.complete, '1');
                assert.match(saved.draftToken, /^[a-f0-9]{64}$/);
                assert.equal(saved.files, '1');
                assert.equal(saved.photo, 'proof.png');
                assert.equal(saved.names.some(name => /^(presentations|publications|seminars|manual_research|ip_assets)\[/.test(name)), false);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
