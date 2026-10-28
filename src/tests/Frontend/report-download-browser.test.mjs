import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('Word and Excel downloads refresh the actual editor token and allow another native save', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchCoordinatorReportTest.php', 'tests/Feature/ResearchHeadRmsReportTest.php', '--filter=saving and downloading'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_EXPORT_REPORT_DOWNLOAD: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
    });
    const sources = Object.fromEntries(['college', 'rms'].map(name => [name, Object.fromEntries(['before', 'after'].map(state => [state,
        readFileSync(resolve(`storage/framework/testing/${name}-download-${state}.html`), 'utf8'),
    ]))]));
    const modules = ['document-writing-toolbar', 'quarterly-report-form', 'rms-quarterly-report-form', 'proposal-preview-workspace', 'proposal-paper-workspace', 'proposal-assessment-preview', 'report-paper-preview'].map(name =>
        readFileSync(resolve(`resources/js/${name}.js`), 'utf8').replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '')).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const parser = await browser.newPage();
    const fixtures = await parser.evaluate(sources => Object.fromEntries(Object.entries(sources).map(([name, states]) => [name,
        Object.fromEntries(Object.entries(states).map(([state, html]) => {
            const document = new DOMParser().parseFromString(html, 'text/html');
            document.querySelectorAll('[data-assistant-report-editor]').forEach(editor => editor.remove());
            return [state, document.querySelector('[data-quarterly-reports], [data-rms-quarterly-reports]').outerHTML];
        })),
    ])), sources);
    await parser.close();
    const saves = { college: [], rms: [] };
    const server = createServer(async (request, response) => {
        if (request.url === '/alpine.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(readFileSync(resolve('node_modules/alpinejs/dist/cdn.min.js')));
            return;
        }
        if (request.url.endsWith('/preview')) {
            response.setHeader('Content-Type', 'text/html');
            response.end('<html><body><main>Report preview</main></body></html>');
            return;
        }
        const name = request.url.startsWith('/research-coordinator/') ? 'college' : 'rms';
        if (request.url.includes('/download?')) {
            response.writeHead(200, { 'Content-Type': 'application/octet-stream', 'Content-Disposition': `attachment; filename="report.${name === 'college' ? 'docx' : 'xlsx'}"` });
            response.end('Browser attachment transport');
            return;
        }
        if (request.method === 'POST') {
            const chunks = [];
            for await (const chunk of request) chunks.push(chunk);
            saves[name].push(Buffer.concat(chunks).toString());
            response.writeHead(303, { Location: `${request.url}?year=2026&quarter=3` });
            response.end();
            return;
        }
        const origin = `http://127.0.0.1:${server.address().port}`;
        let html = fixtures[name][saves[name].length ? 'after' : 'before'].replaceAll('http://localhost', origin);
        if (saves[name].length > 1) html = html.replace('x-init="window.location.assign($el.href)"', '');
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(`<html><body>${html}<script>${modules}\ndocument.addEventListener('alpine:init', () => { Alpine.data('quarterlyReportForm', quarterlyReportForm); Alpine.data('rmsQuarterlyReportForm', rmsQuarterlyReportForm); Alpine.data('reportPaperPreview', reportPaperPreview); });</script><script src="/alpine.js"></script></body></html>`);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        for (const [name, path, button, field] of [
            ['college', '/research-coordinator/reports', 'Save and download Word', 'other_accomplishments'],
            ['rms', '/research-head/rms-quarterly-reports', 'Save & download Excel', 'campus'],
        ]) {
            const page = await browser.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(`http://127.0.0.1:${server.address().port}${path}?year=2026&quarter=3`);
            const originalToken = await page.locator('[name="draft_token"]').inputValue();
            const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: button, exact: true }).click()]);
            assert.equal(await download.failure(), null);
            await expect(page.locator('[name="draft_token"]')).not.toHaveValue(originalToken);
            const freshToken = await page.locator('[name="draft_token"]').inputValue();
            await expect(page.getByRole('link', { name: /Download (Word|Excel) again/ })).toBeVisible();
            if (name === 'rms') await page.getByText('Report details and signatories', { exact: true }).click();
            await page.locator(`[name="${field}"]`).fill('Another edit after download');
            await page.getByRole('button', { name: 'Save draft', exact: true }).click();
            await expect.poll(() => saves[name].length).toBe(2);
            assert.ok(saves[name][0].includes(originalToken));
            assert.ok(saves[name][1].includes(freshToken));
            const savedText = name === 'rms' ? new URLSearchParams(saves[name][1]).get(field) : saves[name][1];
            assert.ok(savedText.includes('Another edit after download'));
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
});
