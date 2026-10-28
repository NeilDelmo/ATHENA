import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const read = path => readFileSync(resolve(path), 'utf8');
const script = name => read(`resources/js/${name}.js`).replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '');

function reportPdf() {
    const stream = 'BT /F1 20 Tf 40 540 Td (RMS quarterly report) Tj ET';
    const objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`];
    let pdf = '%PDF-1.7\n';
    const offsets = [];
    objects.forEach((object, index) => { offsets.push(pdf.length); pdf += `${index + 1} 0 obj\n${object}\nendobj\n`; });
    const xref = pdf.length;
    pdf += `xref\n0 6\n0000000000 65535 f \n${offsets.map(offset => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')}trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
    return Buffer.from(pdf);
}

test('coordinator and RMS editors share the automatic right-side proposal paper preview', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchCoordinatorReportTest.php', 'tests/Feature/ResearchHeadRmsReportTest.php', '--filter=the quarterly report has editable manual fields|RMS editor exposes labeled'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_EXPORT_QUARTERLY_REPORT: '1', ATHENA_EXPORT_RMS_UI: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
    });
    const sources = { college: read('storage/framework/testing/college-quarterly-report.html'), rms: read('storage/framework/testing/rms-editor.html') };
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css].map(file => read(`public/build/${file}`)).join('\n');
    const runtime = ['document-writing-toolbar', 'quarterly-report-form', 'rms-quarterly-report-form', 'proposal-preview-workspace', 'proposal-paper-workspace', 'proposal-assessment-preview', 'report-paper-preview'].map(script).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const name of ['college', 'rms']) {
            for (const width of [1440, 390]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                const requests = [];
                let openedTabs = 0;
                let rejectPreview = false;
                page.on('popup', () => { openedTabs++; });
                page.on('pageerror', error => errors.push(error.message));
                const fragment = await page.evaluate(source => {
                    const doc = new DOMParser().parseFromString(source, 'text/html');
                    doc.querySelectorAll('[data-assistant-report-editor]').forEach(editor => editor.remove());
                    return doc.querySelector('[data-quarterly-reports], [data-rms-quarterly-reports]').outerHTML;
                }, sources[name]);
                await page.route('**/workspace', route => route.fulfill({ contentType: 'text/html', body: `<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}\n[x-cloak]{display:none!important}</style></head><body class="p-4">${fragment}</body></html>` }));
                await page.route('**/pdf.mjs', route => route.fulfill({ contentType: 'text/javascript', body: read('node_modules/pdfjs-dist/build/pdf.mjs') }));
                await page.route('**/pdf.worker.mjs', route => route.fulfill({ contentType: 'text/javascript', body: read('node_modules/pdfjs-dist/build/pdf.worker.mjs') }));
                await page.route('**/reports/preview', async route => {
                    const request = route.request();
                    const data = await new Response(request.postDataBuffer(), { headers: { 'Content-Type': request.headers()['content-type'] } }).formData();
                    requests.push(Object.fromEntries(data));
                    if (rejectPreview) return route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { campus: ['Preview validation failed.'] } }) });
                    if (name === 'rms') return route.fulfill({ contentType: 'application/pdf', body: reportPdf() });
                    return route.fulfill({ contentType: 'text/html', body: `<html><head><style>body{width:816px;margin:0}main{min-height:1248px;padding:72px}</style></head><body><main>Accomplishment report: ${String(data.get('other_accomplishments') || '').replaceAll('<', '&lt;')}</main></body></html>` });
                });
                await page.route('**/rms-quarterly-reports/preview', async route => {
                    const request = route.request();
                    const data = await new Response(request.postDataBuffer(), { headers: { 'Content-Type': request.headers()['content-type'] } }).formData();
                    requests.push(Object.fromEntries(data));
                    return route.fulfill(rejectPreview ? { status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { campus: ['Preview validation failed.'] } }) } : { contentType: 'application/pdf', body: reportPdf() });
                });
                await page.goto('http://localhost/workspace');
                await page.addScriptTag({ type: 'module', content: `import * as pdfJs from '/pdf.mjs'; pdfJs.GlobalWorkerOptions.workerSrc='/pdf.worker.mjs'; window.loadPdfJs=()=>Promise.resolve(pdfJs);` });
                await page.waitForFunction(() => typeof window.loadPdfJs === 'function');
                await page.addScriptTag({ content: `${runtime}\ndocument.addEventListener('alpine:init', () => { Alpine.data('quarterlyReportForm', quarterlyReportForm); Alpine.data('rmsQuarterlyReportForm', rmsQuarterlyReportForm); Alpine.data('reportPaperPreview', config => { const state=reportPaperPreview(config); const init=state.init; state.init=function(){window.reportPreview=this;init.call(this)}; return state; }); });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                const panel = page.locator(name === 'college' ? '#quarterly-report-preview-panel' : '#rms-report-preview-panel');
                if (width < 1024) {
                    assert.equal(requests.length, 0);
                    await page.getByRole('button', { name: 'Preview paper', exact: true }).click();
                }
                await page.waitForFunction(() => window.reportPreview?.previewReady || window.reportPreview?.previewError);
                assert.equal(await page.evaluate(() => window.reportPreview.previewError), '');
                if (width >= 1024) {
                    const editor = await page.locator('.proposal-edit-pane').boundingBox();
                    const dock = await panel.boundingBox();
                    assert.ok(dock.x >= editor.x + editor.width, 'The paper must be beside the editor on the right.');
                    await panel.getByRole('button', { name: 'Enlarge the paper preview' }).click();
                }
                await expect(panel).toHaveAttribute('role', 'dialog');
                await panel.getByRole('button', { name: 'Fit page', exact: true }).click();
                await panel.getByRole('button', { name: 'Zoom in', exact: true }).click();
                await expect(panel.locator('output')).toHaveText('110%');
                await panel.getByRole('button', { name: 'Fit width', exact: true }).click();
                if (name === 'rms') await expect(panel.locator('iframe').contentFrame().locator('.assessment-page img')).toBeVisible();
                await page.keyboard.press('Escape');
                if (width < 1024) await expect(panel).toBeHidden();
                if (name === 'rms') await page.getByText('Report details and signatories', { exact: true }).click();
                const field = page.locator(name === 'college' ? '[name="other_accomplishments"]' : '[name="campus"]');
                await field.fill('Current unsaved report');
                if (width < 1024) await page.getByRole('button', { name: 'Preview paper', exact: true }).click();
                await expect.poll(() => requests.at(-1)?.[name === 'college' ? 'other_accomplishments' : 'campus']).toBe('Current unsaved report');
                await expect.poll(() => page.evaluate(() => window.reportPreview.previewReady)).toBe(true);
                assert.equal(requests.at(-1).intent, 'preview');
                assert.ok(requests.at(-1)[name === 'college' ? 'report_details_json' : 'row_details_json']);
                if (width < 1024) await page.keyboard.press('Escape');
                rejectPreview = true;
                await field.fill('Rejected unsaved report');
                if (width < 1024) await page.getByRole('button', { name: 'Preview paper', exact: true }).click();
                await expect(panel.getByRole('alert')).toHaveText('Preview validation failed.');
                rejectPreview = false;
                await panel.getByRole('button', { name: 'Retry preview', exact: true }).click();
                await expect.poll(() => page.evaluate(() => window.reportPreview.previewReady)).toBe(true);
                await panel.locator('iframe').evaluate(frame => { window.reportPrintCalls = 0; frame.contentWindow.print = () => { window.reportPrintCalls++; }; });
                await panel.getByRole('button', { name: 'Print', exact: true }).click();
                assert.equal(await page.evaluate(() => window.reportPrintCalls), 1);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.screenshot({ path: resolve(`storage/framework/testing/${name}-paper-preview-${width}.png`), fullPage: true });
                assert.equal(openedTabs, 0, 'Report previews must not open another tab.');
                assert.equal(page.context().pages().length, 1);
                assert.equal(page.url(), 'http://localhost/workspace');
                assert.equal(await page.locator('[data-report-preview-form]').getAttribute('target'), null);
                assert.equal(await page.locator('button[name="intent"][value="preview"]').getAttribute('formtarget'), null);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
