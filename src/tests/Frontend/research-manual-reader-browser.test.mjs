import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import { build } from 'vite';

function searchableManual() {
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
        'BT /F1 18 Tf 50 720 Td (ETHICS approval before recruitment.) Tj ET',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>',
        'BT /F1 18 Tf 50 720 Td (ETHICS second-page evidence.) Tj ET',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objects.forEach((object, index) => {
        offsets.push(Buffer.byteLength(pdf));
        const body = [3, 5].includes(index) ? `<< /Length ${Buffer.byteLength(object)} >>\nstream\n${object}\nendstream` : object;
        pdf += `${index + 1} 0 obj\n${body}\nendobj\n`;
    });
    const start = Buffer.byteLength(pdf);
    pdf += `xref\n0 8\n0000000000 65535 f \n${offsets.slice(1).map(offset => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')}trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n${start}\n%%EOF`;
    return Buffer.from(pdf);
}

test('manual reader reuses the shared thumbnail and modal with complete search, zoom, citations, and safe guidance across layouts', { timeout: 180000 }, async context => {
    const fixturePath = resolve('storage/framework/testing/manual-reader-review.html');
    if (process.env.ATHENA_MANUAL_READER_FIXTURE_READY !== '1') {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchManualReaderTest.php', '--filter=manual reader pages'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_EXPORT_MANUAL_READER: '1' },
        });
    }
    assert.ok(existsSync(fixturePath), 'Export the rendered ResearchManualReaderTest fixture first.');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const extractor = await browser.newPage();
        const readerHtml = await extractor.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('[data-research-manual-reader]').outerHTML, readFileSync(fixturePath, 'utf8'));
        await extractor.close();
        const compiled = await build({
            configFile: false, logLevel: 'silent', base: '/manual-reader-assets/',
            build: { write: false, rollupOptions: {
                input: {
                    reader: resolve('resources/js/research-manual-reader.js'),
                    workspace: resolve('resources/js/pdf-annotation-workspace.js'),
                    alpine: resolve('node_modules/alpinejs/dist/module.esm.js'),
                    app: resolve('resources/css/app.css'),
                    sharedStyles: resolve('resources/css/proposal-paper-workspace.css'),
                    projectStyles: resolve('resources/css/project-workspace.css'),
                },
                preserveEntrySignatures: 'strict', output: { entryFileNames: '[name].js' },
            } },
        });
        const assets = new Map(compiled.output.map(asset => [asset.fileName, asset]));
        const styleLinks = compiled.output.filter(asset => asset.type === 'asset' && asset.fileName.endsWith('.css'))
            .map(asset => `<link rel="stylesheet" href="/manual-reader-assets/${asset.fileName}">`).join('');
        const origin = 'http://localhost';
        for (const viewport of [{ width: 390, height: 1000 }, { width: 1440, height: 1000 }, { width: 667, height: 375 }]) for (const dark of [false, true]) {
            const { width, height } = viewport;
            context.diagnostic(`Checking ${width}×${height} ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', async route => {
                const path = new URL(route.request().url()).pathname;
                if (path === '/fixture') {
                    return route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture-token">${styleLinks}<style>[x-cloak]{display:none!important}</style><body><main class="mx-auto max-w-7xl p-4">${readerHtml}</main><script type="module">import Alpine from '/manual-reader-assets/alpine.js'; import {researchManualReader} from '/manual-reader-assets/reader.js'; import registerPdfAnnotationWorkspace from '/manual-reader-assets/workspace.js'; window.Alpine=Alpine; Alpine.data('researchManualReader',researchManualReader); registerPdfAnnotationWorkspace(Alpine); Alpine.start();</script></body></html>` });
                }
                const asset = assets.get(path.replace('/manual-reader-assets/', ''));
                if (!asset) return route.abort();
                const contentType = /\.m?js$/.test(path) ? 'text/javascript' : path.endsWith('.css') ? 'text/css' : 'application/octet-stream';
                return route.fulfill({ contentType, body: asset.type === 'chunk' ? asset.code : Buffer.from(asset.source) });
            });
            await page.route('**/research-manuals/*/file*', route => route.fulfill({ contentType: 'application/pdf', body: searchableManual() }));
            await page.route('**/guidance-check', route => {
                assert.equal(route.request().method(), 'POST');
                assert.equal(route.request().headers()['x-csrf-token'], 'fixture-token');
                assert.equal(route.request().postDataJSON().question, 'What ethics review is required?');
                return route.fulfill({ json: { reply: 'Review ethics clearance. <img src=x onerror="alert(1)">', edition: 'Review fixture', sources: [{ section: 'Ethics clearance', page_start: 2, page_end: 2, url: `${origin}/fixture#page=2` }] } });
            });
            await page.goto(`${origin}/fixture`, { waitUntil: 'domcontentloaded', timeout: 20000 });
            const reader = page.locator('[data-research-manual-reader]');
            const thumbnail = reader.locator('[data-submitted-report-paper]');
            const thumbnailButton = thumbnail.locator('[data-report-paper-maximize]');
            const modal = reader.locator('[data-research-manual-preview-modal]');
            const preview = modal.locator('[data-submitted-report-preview]');
            const launch = reader.locator('[data-manual-preview-button]');
            await expect(thumbnail.locator('canvas')).toBeVisible({ timeout: 30000 });
            await expect(thumbnail.locator('.pdf-annotation-page')).toHaveCount(1);
            await expect(modal).toBeHidden();
            await expect(preview).toHaveCount(0);
            await expect(reader.locator('.pdfViewer')).toHaveCount(0);
            await expect(reader.getByRole('button', { name: /Fullscreen/ })).toHaveCount(0);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Page overflow at ${width}px`);
            if (process.env.MANUAL_READER_SCREENSHOTS) {
                mkdirSync(process.env.MANUAL_READER_SCREENSHOTS, { recursive: true });
                await page.screenshot({ path: resolve(process.env.MANUAL_READER_SCREENSHOTS, `manual-reader-${width}-${dark ? 'dark' : 'light'}-thumbnail.png`), fullPage: true });
            }

            await thumbnailButton.click();
            await expect(modal).toBeVisible();
            await expect(preview.locator('.pdf-annotation-page')).toHaveCount(2, { timeout: 30000 });
            await expect(preview.locator('[data-manual-page]')).toBeEnabled();
            assert.ok(await preview.locator('[x-ref="viewer"]').evaluate(element => element.clientHeight) > 80, 'The shared paper needs a usable viewport.');
            assert.equal(await page.evaluate(() => document.body.classList.contains('overflow-y-hidden')), true);
            const find = preview.locator('[data-manual-find]');
            if (process.env.MANUAL_READER_SCREENSHOTS) {
                await page.screenshot({ path: resolve(process.env.MANUAL_READER_SCREENSHOTS, `manual-reader-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            }
            if (width === 390) {
                const findWidth = (await find.boundingBox()).width;
                assert.ok(findWidth >= 180, `Mobile Find needs enough width for readable search (received ${findWidth}px).`);
            }
            await find.fill('ETHICS');
            await expect(preview.locator('[data-manual-find-status]')).toContainText('of 2 matches');
            await expect(preview.locator('.pdf-search-highlight')).toHaveCount(2);
            const searchViewerHeight = await preview.locator('[x-ref="viewer"]').evaluate(element => element.clientHeight);
            if (process.env.MANUAL_READER_SCREENSHOTS) {
                await page.screenshot({ path: resolve(process.env.MANUAL_READER_SCREENSHOTS, `manual-reader-${width}-${dark ? 'dark' : 'light'}-search.png`), fullPage: true });
            }
            assert.ok(searchViewerHeight > 80, `Find results must leave room to read the paper (received ${searchViewerHeight}px).`);
            const firstMatchPage = await preview.locator('[data-manual-page]').inputValue();
            await preview.getByRole('button', { name: 'Next match', exact: true }).click();
            await expect(preview.locator('[data-manual-page]')).not.toHaveValue(firstMatchPage);
            await preview.getByRole('button', { name: 'Previous match', exact: true }).click();
            await expect(preview.locator('[data-manual-page]')).toHaveValue(firstMatchPage);
            await expect(preview.locator('.pdf-search-highlight[data-search-current="true"]')).toBeVisible();
            await find.fill('not-in-this-manual');
            await expect(preview.locator('[data-manual-find-status]')).toHaveText('No matches');
            await find.fill('');
            await expect(preview.locator('[data-manual-find-status]')).toHaveText('');
            await expect(preview.locator('.pdf-search-highlight')).toHaveCount(0);
            await expect(preview.getByRole('button', { name: 'Next match', exact: true })).toBeDisabled();
            await preview.locator('[data-manual-page]').fill('1');
            await preview.locator('[data-manual-page]').press('Enter');
            await expect(preview.locator('[data-manual-page]')).toHaveValue('1');
            for (const input of ['0', '']) {
                await preview.locator('[data-manual-page]').fill(input);
                await preview.locator('[data-manual-page]').press('Enter');
                await expect(preview.locator('[data-manual-page]')).toHaveValue('1');
            }
            await preview.locator('[data-manual-page]').fill('2');
            await preview.locator('[data-manual-page]').press('Enter');
            await expect(preview.locator('[data-manual-page]')).toHaveValue('2');
            await preview.locator('[data-manual-page]').fill('999');
            await preview.locator('[data-manual-page]').press('Enter');
            await expect(preview.locator('[data-manual-page]')).toHaveValue('2');
            const paper = preview.locator('.pdf-annotation-page').first();
            const beforeZoom = await paper.evaluate(element => element.getBoundingClientRect().width);
            await preview.getByRole('button', { name: 'Zoom in', exact: true }).click();
            await expect.poll(() => paper.evaluate(element => element.getBoundingClientRect().width)).toBeGreaterThan(beforeZoom);
            await preview.getByRole('button', { name: 'Fit page', exact: true }).click();
            await expect(preview.getByRole('button', { name: 'Fit page', exact: true })).toHaveAttribute('aria-pressed', 'true');
            await expect.poll(() => preview.locator('[x-ref="viewer"]').evaluate(element => (element.querySelector('.pdf-annotation-page')?.getBoundingClientRect().height ?? Infinity) / element.clientHeight)).toBeLessThanOrEqual(1);
            await preview.getByRole('button', { name: 'Fit width', exact: true }).click();
            await expect(preview.getByRole('button', { name: 'Fit width', exact: true })).toHaveAttribute('aria-pressed', 'true');
            const zoomOutput = preview.locator('.proposal-preview-zoom output');
            const beforeWheel = Number((await zoomOutput.innerText()).replace('%', ''));
            assert.equal(await preview.locator('[x-ref="viewer"]').evaluate(element => {
                const event = new WheelEvent('wheel', { ctrlKey: true, deltaY: -120, bubbles: true, cancelable: true, clientX: 100, clientY: 200 });
                element.dispatchEvent(event);
                return event.defaultPrevented;
            }), true);
            await expect.poll(async () => Number((await zoomOutput.innerText()).replace('%', ''))).toBeGreaterThan(beforeWheel);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Preview overflow at ${width}px`);
            await preview.getByRole('button', { name: 'Close PDF preview', exact: true }).click();
            await expect(modal).toBeHidden();
            await expect(thumbnailButton).toBeFocused();
            assert.equal(await page.evaluate(() => document.body.classList.contains('overflow-y-hidden')), false);
            await launch.click();
            await expect(modal).toBeVisible();
            await expect(preview.locator('[data-manual-page]')).toBeEnabled({ timeout: 30000 });
            await page.keyboard.press('Escape');
            await expect(modal).toBeHidden();
            await expect(launch).toBeFocused();
            assert.equal(await page.evaluate(() => document.body.classList.contains('overflow-y-hidden')), false);

            await reader.locator('[data-manual-guidance] summary').click();
            await expect(reader.getByLabel('Ask about this edition', { exact: true })).toHaveAttribute('data-manual-question', '');
            await reader.locator('[data-manual-question]').fill('What ethics review is required?');
            await reader.getByRole('button', { name: 'Check guidance', exact: true }).click();
            const answer = reader.locator('[data-manual-guidance-result]');
            await expect(answer).toContainText('Review ethics clearance. <img');
            await expect(answer.locator('img')).toHaveCount(0);
            await expect(answer.getByRole('link')).toHaveAttribute('href', `${origin}/fixture#page=2`);
            const pdfUrl = JSON.parse(await thumbnail.getAttribute('data-pdf-annotation-config')).pdfUrl;
            const version = new URL(pdfUrl, origin).pathname.split('/').at(-2);
            await page.evaluate(version => window.dispatchEvent(new CustomEvent('research-manual:page', { detail: { version, page: 2 } })), version);
            await expect(modal).toBeVisible();
            await expect(preview.locator('[data-manual-page]')).toHaveValue('2', { timeout: 30000 });
            await page.goto(`${origin}/fixture?initial=1#page=2`, { waitUntil: 'domcontentloaded', timeout: 20000 });
            await expect(modal).toBeVisible();
            await expect(preview.locator('[data-manual-page]')).toHaveValue('2', { timeout: 30000 });
            await expect(preview.locator('.pdf-annotation-page')).toHaveCount(2);
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
