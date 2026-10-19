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

test('manual reader reuses the shared thumbnail and modal with complete search, zoom, citations, and safe guidance across layouts', { timeout: 240000 }, async context => {
    const fixturePath = resolve('storage/framework/testing/manual-reader-review.html');
    if (process.env.ATHENA_MANUAL_READER_FIXTURE_READY !== '1') {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchManualReaderTest.php', '--filter=manual reader pages|edition review compares'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_MANUAL_READER: '1' },
        });
    }
    assert.ok(existsSync(fixturePath), 'Export the rendered ResearchManualReaderTest fixture first.');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const extractor = await browser.newPage();
        const readerHtml = await extractor.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('[data-research-manual-reader]').outerHTML, readFileSync(fixturePath, 'utf8'));
        const indexHtml = await extractor.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('[data-research-manuals]').outerHTML, readFileSync(resolve('storage/framework/testing/manual-reader-index.html'), 'utf8'));
        const comparisonHtml = await extractor.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('[data-research-manual-review]').outerHTML, readFileSync(resolve('storage/framework/testing/manual-comparison.html'), 'utf8'));
        await extractor.close();
        const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
        const datePicker = app.slice(app.indexOf("Alpine.data('datePicker',"), app.indexOf("Alpine.data('dateTimePicker',"));
        const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
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
        for (const viewport of [{ width: 1920, height: 1000 }, { width: 390, height: 1000 }, { width: 1280, height: 1000 }, { width: 1440, height: 1000 }, { width: 667, height: 375 }]) for (const dark of [false, true]) {
            const { width, height } = viewport;
            context.diagnostic(`Checking ${width}×${height} ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', async route => {
                const path = new URL(route.request().url()).pathname;
                if (path === '/fixture' || path === '/index-fixture' || path === '/comparison-fixture') {
                    const content = path === '/index-fixture' ? indexHtml : (path === '/comparison-fixture' ? comparisonHtml : readerHtml);
                    const shell = path === '/index-fixture'
                        ? `<div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px"><main style="padding:${width >= 1024 ? 32 : width >= 640 ? 24 : 16}px"><div class="athena-page-content">${content}</div></main></div>`
                        : `<main class="mx-auto max-w-7xl p-4">${content}</main>`;
                    return route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture-token">${styleLinks}<style>[x-cloak]{display:none!important}</style><body>${shell}<script type="module">import Alpine from '/manual-reader-assets/alpine.js'; import {researchManualReader} from '/manual-reader-assets/reader.js'; import registerPdfAnnotationWorkspace from '/manual-reader-assets/workspace.js'; ${dateHelpers} window.Alpine=Alpine; ${datePicker} Alpine.data('researchManualReader',researchManualReader); registerPdfAnnotationWorkspace(Alpine); Alpine.start();</script></body></html>` });
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
                assert.ok(typeof route.request().postDataJSON().question === 'string' && route.request().postDataJSON().question.length <= 1500);
                return route.fulfill({ json: { reply: 'Review ethics clearance. <img src=x onerror="alert(1)">', edition: 'Review fixture', check_type: 'ai_response', sources: [{ section: 'Ethics clearance', page_start: 2, page_end: 2, url: `${origin}/fixture#page=2`, content: 'Signed consent is required before participant recruitment. <img src=x onerror="alert(1)">' }] } });
            });
            await page.goto(`${origin}/index-fixture`, { waitUntil: 'domcontentloaded', timeout: 20000 });
            const currentManual = page.locator('section[aria-labelledby="current-manual-heading"]');
            const manualWorkspace = page.locator('[data-research-manuals]');
            const upload = page.locator('#upload-manual');
            const currentCover = currentManual.locator('[data-submitted-report-paper]');
            await expect(currentCover.locator('canvas')).toBeVisible({ timeout: 30000 });
            await expect(currentManual.locator('[data-manual-preview-button]')).toHaveCount(1);
            await expect(currentManual.locator('[data-manual-download]')).toHaveCount(1);
            const coverBounds = await currentCover.boundingBox();
            const cardBounds = await currentManual.boundingBox();
            const workspaceBounds = await manualWorkspace.boundingBox();
            const uploadBounds = await upload.boundingBox();
            const historyBounds = await page.locator('section[aria-labelledby="edition-history-heading"]').boundingBox();
            assert.ok(coverBounds.width <= 160, 'The current manual uses a compact cover.');
            if (width >= 640) {
                const headingBounds = await currentManual.locator('#current-manual-heading').boundingBox();
                assert.ok(headingBounds.x > coverBounds.x + coverBounds.width, 'Title and edition details sit beside the cover.');
            }
            if (workspaceBounds.width >= 916) {
                assert.ok(Math.abs(cardBounds.y - uploadBounds.y) < 1, 'Current manual and upload should use the same row when both fit.');
                assert.ok(uploadBounds.x >= cardBounds.x + cardBounds.width, 'The upload form uses the space beside the current manual.');
                assert.ok(Math.abs(uploadBounds.x + uploadBounds.width - workspaceBounds.x - workspaceBounds.width) < 1, 'Both cards use the full available row without an empty extra column.');
                assert.ok(cardBounds.height < uploadBounds.height, 'The current manual card must not stretch to the upload form height.');
            } else {
                assert.ok(uploadBounds.y >= cardBounds.y + cardBounds.height, 'Current manual and upload stack when there is not enough content width.');
            }
            assert.ok(historyBounds.y >= Math.max(cardBounds.y + cardBounds.height, uploadBounds.y + uploadBounds.height), 'Edition history remains below both cards.');
            assert.ok(Math.abs(historyBounds.width - workspaceBounds.width) < 1, 'Edition history uses the full content width.');
            for (const [container, bounds] of [[currentManual, cardBounds], [upload, uploadBounds]]) {
                for (const control of await container.locator('a, button, input:not([type="hidden"])').all()) {
                    if (!await control.isVisible()) continue;
                    const box = await control.boundingBox();
                    assert.ok(box.x >= bounds.x && box.x + box.width <= bounds.x + bounds.width + 1, 'Visible actions and inputs stay inside their card.');
                }
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            const effectiveDate = upload.getByRole('combobox', { name: 'Effective date', exact: true });
            await effectiveDate.click();
            const calendar = upload.getByRole('dialog', { name: 'Choose a date', exact: true });
            await expect(calendar).toBeVisible();
            const calendarBounds = await calendar.boundingBox();
            assert.ok(calendarBounds.x >= 0 && calendarBounds.x + calendarBounds.width <= width + 1, 'The effective date calendar stays inside the viewport.');
            if (width >= 1024) {
                assert.ok(calendarBounds.x >= uploadBounds.x && calendarBounds.x + calendarBounds.width <= uploadBounds.x + uploadBounds.width + 1, 'The effective date calendar stays inside the upload card on desktop.');
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Opening the effective date calendar must not cause page overflow.');
            await effectiveDate.press('Escape');
            await expect(calendar).toBeHidden();
            if (process.env.MANUAL_READER_SCREENSHOTS) {
                mkdirSync(process.env.MANUAL_READER_SCREENSHOTS, { recursive: true });
                await currentManual.screenshot({ path: resolve(process.env.MANUAL_READER_SCREENSHOTS, `current-manual-${width}-${dark ? 'dark' : 'light'}.png`) });
                await manualWorkspace.screenshot({ path: resolve(process.env.MANUAL_READER_SCREENSHOTS, `manual-workspace-${width}-${dark ? 'dark' : 'light'}.png`) });
            }
            await currentManual.locator('[data-manual-preview-button]').click();
            await expect(currentManual.locator('[data-research-manual-preview-modal]')).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(currentManual.locator('[data-manual-preview-button]')).toBeFocused();

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
            await expect(preview.getByRole('searchbox', { name: 'Search manual', exact: true })).toHaveAttribute('placeholder', 'Search manual');
            const download = preview.locator('.proposal-preview-dock-actions .proposal-preview-download');
            await expect(download.locator('svg')).toHaveCount(1);
            await expect(preview.locator('.proposal-preview-search svg')).toHaveCount(1);
            for (const label of ['Previous match', 'Next match']) {
                await expect(preview.getByRole('button', { name: label, exact: true }).locator('svg')).toHaveCount(1);
            }
            const toolbarGeometry = await preview.locator('.proposal-preview-dock-actions').evaluate(element => {
                const download = element.querySelector('.proposal-preview-download');
                const search = element.querySelector('[data-manual-find]');
                const downloadBox = download.getBoundingClientRect();
                const searchBox = search.getBoundingClientRect();
                return {
                    download: { y: downloadBox.y, height: download.offsetHeight },
                    search: { y: searchBox.y, height: search.offsetHeight },
                    downloadColor: getComputedStyle(download).backgroundColor,
                    downloadLineHeight: getComputedStyle(download).lineHeight,
                    searchLineHeight: getComputedStyle(search).lineHeight,
                };
            });
            assert.equal(toolbarGeometry.download.height, 44, 'Download must use the same 44px control height as Search.');
            assert.equal(toolbarGeometry.search.height, toolbarGeometry.download.height);
            assert.equal(toolbarGeometry.searchLineHeight, toolbarGeometry.downloadLineHeight, 'Toolbar text needs consistent vertical metrics.');
            if (width !== 390) {
                assert.ok(Math.abs(toolbarGeometry.download.y - toolbarGeometry.search.y) < 1, 'Download and Search must align on the same row.');
            }
            assert.equal(toolbarGeometry.downloadColor, dark ? 'rgb(253, 164, 175)' : 'rgb(122, 0, 25)', 'Download uses ATHENA\'s red accent in both themes.');
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

            if (width === 390 || width === 1280) {
                await page.goto(`${origin}/comparison-fixture`, { waitUntil: 'domcontentloaded', timeout: 20000 });
                const comparison = page.locator('[data-manual-comparison]');
                const editionReaders = page.locator('[data-research-manual-reader]');
                await expect(editionReaders).toHaveCount(2);
                await expect(editionReaders.nth(1).getByRole('heading', { name: /^Uploaded edition:/ })).toBeVisible();
                for (const side of [0, 1]) {
                    const cover = editionReaders.nth(side).locator('[data-submitted-report-paper]');
                    await cover.scrollIntoViewIfNeeded();
                    await expect(cover.locator('canvas')).toBeVisible({ timeout: 30000 });
                    assert.ok((await cover.boundingBox()).width <= 160, 'Both editions must use the shared compact cover.');
                }
                const passage = comparison.locator('article').first();
                await expect(passage).toContainText('Current edition · 2025');
                await expect(passage).toContainText('Uploaded edition · 2026');
                await expect(passage.locator('mark')).not.toHaveCount(0);
                await expect(passage.locator('script')).toHaveCount(0);
                const columns = await passage.locator('.grid > div').evaluateAll(elements => elements.map(element => {
                    const { x, y } = element.getBoundingClientRect();
                    return { x, y };
                }));
                if (width === 390) {
                    assert.equal(columns[0].x, columns[1].x, 'Mobile comparison passages must stack.');
                    assert.ok(columns[1].y > columns[0].y);
                } else {
                    assert.equal(columns[0].y, columns[1].y, 'Desktop comparison passages must share a row.');
                    assert.ok(columns[1].x > columns[0].x);
                }
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Comparison must not overflow the page.');

                const pageLinks = passage.getByRole('link', { name: 'Page 2', exact: true });
                await expect(pageLinks).toHaveCount(2);
                for (const side of [0, 1]) {
                    const editionReader = editionReaders.nth(side);
                    const editionModal = editionReader.locator('[data-research-manual-preview-modal]');
                    const editionPdfUrl = JSON.parse(await editionReader.locator('[data-submitted-report-paper]').getAttribute('data-pdf-annotation-config')).pdfUrl;
                    const editionVersion = new URL(editionPdfUrl, origin).pathname.split('/').at(-2);
                    await expect(pageLinks.nth(side)).toHaveAttribute('href', new RegExp(`${editionVersion}#page=2$`));
                    await pageLinks.nth(side).click();
                    await expect(editionModal).toBeVisible();
                    await expect(editionReaders.nth(1 - side).locator('[data-research-manual-preview-modal]')).toBeHidden();
                    await expect(editionModal.locator('[data-manual-page]')).toHaveValue('2', { timeout: 30000 });
                    const ids = await page.locator('[id]').evaluateAll(elements => elements.map(element => element.id));
                    assert.equal(new Set(ids).size, ids.length, 'Comparison readers must keep unique toolbar and heading IDs.');
                    await editionModal.getByRole('button', { name: 'Close PDF preview', exact: true }).click();
                }

                const candidateReader = editionReaders.nth(1);
                await candidateReader.locator('[data-manual-guidance] summary').click();
                await candidateReader.locator('[data-manual-question]').fill('What ethics review is required?');
                await candidateReader.getByRole('button', { name: 'Check guidance', exact: true }).click();
                const checks = candidateReader.locator('[data-manual-guidance-result] article');
                await expect(checks).toHaveCount(1);
                await candidateReader.locator('[data-manual-run-checks]').click();
                await expect(candidateReader.locator('[data-manual-run-checks]')).toBeEnabled();
                await expect(checks).not.toHaveCount(1);
                await expect(checks.filter({ hasText: 'What ethics review is required?' })).toHaveCount(1);
                await expect(checks.last()).toContainText('AI answer — review cited passages');
                await expect(checks.last()).toContainText('Signed consent is required before participant recruitment. <img');
                await expect(checks.locator('img')).toHaveCount(0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Completed checks must not overflow the page.');
                if (process.env.MANUAL_READER_SCREENSHOTS) {
                    await page.screenshot({ path: resolve(process.env.MANUAL_READER_SCREENSHOTS, `manual-comparison-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }

                await page.goto(`${origin}/comparison-fixture#page=2`, { waitUntil: 'domcontentloaded', timeout: 20000 });
                await expect(editionReaders.nth(1).locator('[data-research-manual-preview-modal]')).toBeVisible();
                await expect(editionReaders.nth(0).locator('[data-research-manual-preview-modal]')).toBeHidden();
                await expect(editionReaders.nth(1).locator('[data-manual-page]')).toHaveValue('2', { timeout: 30000 });
            }
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
