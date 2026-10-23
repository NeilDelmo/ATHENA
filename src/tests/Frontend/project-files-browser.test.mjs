import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('project folders preserve the folder grid, mouse scrolling, previews and queued PDF uploads', async () => {
    if (process.env.ATHENA_FILES_FIXTURE_READY !== '1') {
        execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ProjectDocumentLibraryTest.php', '--filter=faculty researcher files use an inline'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_PROJECT_FILES: '1' },
        });
    }
    const fixturePath = resolve('storage/framework/testing/project-files-faculty.html');
    assert.ok(existsSync(fixturePath), 'Export the ProjectDocumentLibraryTest faculty Files fixture before running this browser test.');
    const html = readFileSync(fixturePath, 'utf8');
    const readerHtml = readFileSync(resolve('storage/framework/testing/project-files-reader.html'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8') + '\n' + readFileSync(resolve('resources/css/project-workspace.css'), 'utf8'), { from: undefined });
    const module = readFileSync(resolve('resources/js/report-library.js'), 'utf8').replaceAll('export ', '') + '\n'
        + readFileSync(resolve('resources/js/project-document-drawer.js'), 'utf8').replace(/^import .*;\r?\n/gm, '').replaceAll('export ', '');
    const posterImage = readFileSync(resolve('public/images/sample-maintenance-poster.png'));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                let posts = 0;
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                const fixture = await page.evaluate(source => {
                    const document = new DOMParser().parseFromString(source, 'text/html');
                    const root = document.querySelector('[data-topic-workspace]');
                    const files = root.querySelector('#project-files-tab');
                    const navigation = root.querySelector('[data-project-workspace-navigation]') || root.querySelector('nav[aria-label="Proposal workspace sections"]');
                    const reducedRoot = root.cloneNode(false);
                    reducedRoot.append(navigation.cloneNode(true));
                    for (const panel of root.querySelectorAll(':scope > section[role="tabpanel"]')) {
                        if (panel === files) {
                            reducedRoot.append(panel.cloneNode(true));
                        } else {
                            const reducedPanel = panel.cloneNode(false);
                            reducedPanel.removeAttribute('x-data');
                            const heading = panel.querySelector('h2, h3');
                            if (heading) reducedPanel.append(heading.cloneNode(true));
                            reducedRoot.append(reducedPanel);
                        }
                    }
                    const poster = files.querySelector('[data-project-document-key^="terminal-report-poster-"]');
                    const posterSelection = files.querySelector(`[data-project-file-selection="${poster.dataset.projectDocumentKey}"]`);
                    return {
                        html: reducedRoot.outerHTML,
                        floatingTriggers: document.querySelectorAll('[data-project-documents-floating-trigger]').length,
                        fileBrowsers: document.querySelectorAll('[data-project-document-browser]').length,
                        posterKey: poster.dataset.projectDocumentKey,
                        posterView: poster.querySelector('img').getAttribute('src'),
                        posterDownload: posterSelection.querySelector('a').getAttribute('href'),
                    };
                }, html);
                let pageHtml = fixture.html;
                assert.equal(fixture.floatingTriggers, 0);
                assert.equal(fixture.fileBrowsers, 1);
                await page.route('**/*', route => {
                    const request = route.request();
                    if (request.method() === 'POST') {
                        posts++;
                        return route.abort();
                    }
                    if (request.url() === fixture.posterView) {
                        return route.fulfill({ contentType: 'image/png', body: posterImage });
                    }
                    if (request.url() === fixture.posterDownload) {
                        return route.fulfill({ contentType: 'image/png', headers: { 'content-disposition': 'attachment; filename="sample-project-poster.png"' }, body: posterImage });
                    }
                    if (request.url().endsWith('/project-files-test')) {
                        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="csrf-token" content="files-test-token"><style>${styles.css}\n[x-cloak]{display:none!important}\n@media(min-width:640px){main{max-width:calc(100vw - 320px)!important}}</style></head><body class="bg-slate-50 p-4 font-sans dark:bg-slate-950"><main class="mx-auto max-w-7xl">${pageHtml}</main></body></html>` });
                    }
                    return route.fulfill({ contentType: 'application/pdf', headers: { 'content-disposition': 'attachment; filename="terminal-report.pdf"' }, body: '%PDF-1.7\nBrowser fixture' });
                });
                const initialize = async () => {
                    await page.goto('http://localhost/project-files-test');
                    await page.addScriptTag({ content: `${module}\ndocument.addEventListener('alpine:init', () => { Alpine.data('projectDocumentDrawer', projectDocumentDrawer); Alpine.data('pdfAnnotationWorkspace', () => ({ loading:false, loadError:'', viewerRefreshRequired:false, init(){ this.$el.dataset.previewUrl=JSON.parse(this.$el.dataset.pdfAnnotationConfig).pdfUrl; } })); });` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                };
                await initialize();
                const topic = page.locator('[data-topic-workspace]');
                const files = page.locator('#project-files-tab');
                const fileBrowser = files.locator('[data-project-document-browser]');
                const filesTab = page.locator('#project-files-tab-button');
                const chooseView = async (target, name) => {
                    await target.click({ button: 'right' });
                    await expect(page.getByRole('menu', { name: 'File actions', exact: true })).toBeVisible();
                    await page.getByRole('menuitemradio', { name, exact: true }).click();
                };
                const drawerState = () => fileBrowser.evaluate(element => {
                    const state = Alpine.$data(element);
                    return { inline: state.inline, open: state.open, view: state.view, size: state.size, category: state.activeCategory, selected: state.selectedItem, queued: state.selectedFiles.map(file => file.name), error: state.fileError };
                });
                const unlocked = async () => {
                    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('overflow-hidden') || body.classList.contains('overflow-y-hidden')), false);
                };
                const wheelScrollsPage = async target => {
                    await page.evaluate(() => {
                        const spacer = document.createElement('div');
                        spacer.dataset.wheelTestSpacer = '';
                        spacer.style.height = '100vh';
                        document.body.append(spacer);
                    });
                    await target.hover();
                    const before = await page.evaluate(() => window.scrollY);
                    await page.mouse.wheel(0, 400);
                    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(before);
                    await page.evaluate(() => {
                        document.querySelector('[data-wheel-test-spacer]').remove();
                        window.scrollTo(0, 0);
                    });
                };
                const screenshot = async name => {
                    await page.locator('main').evaluate(async element => {
                        await new Promise(requestAnimationFrame);
                        await Promise.all(element.getAnimations({ subtree: true }).map(animation => animation.finished.catch(() => {})));
                    });
                    assert.deepEqual(errors, []);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `overflow at ${width}px (${name})`);
                    if (process.env.PROJECT_FILES_SCREENSHOT_DIRECTORY) {
                        mkdirSync(process.env.PROJECT_FILES_SCREENSHOT_DIRECTORY, { recursive: true });
                        await page.screenshot({ path: resolve(process.env.PROJECT_FILES_SCREENSHOT_DIRECTORY, `project-files-${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: false });
                    }
                };

                await filesTab.click();
                await expect(filesTab).toHaveAttribute('aria-selected', 'true');
                await expect(files).toBeVisible();
                assert.equal((await drawerState()).inline, true);
                await expect(fileBrowser.locator('[data-project-file-category]:visible')).toHaveCount(2);
                await expect(fileBrowser.locator('[data-project-document-key]:visible')).toHaveCount(0);
                await unlocked();
                await wheelScrollsPage(fileBrowser.locator('[data-project-file-category]').first());
                await screenshot('folders');

                const reportsFolder = fileBrowser.locator('[data-project-file-category="monitoring_reports"]');
                await reportsFolder.click();
                assert.equal((await drawerState()).category, 'all');
                await expect(reportsFolder).toHaveAttribute('aria-pressed', 'true');
                await chooseView(reportsFolder, 'Small icons');
                const smallFolderWidth = await reportsFolder.locator('.workspace-item-graphic').evaluate(element => element.getBoundingClientRect().width);
                await chooseView(reportsFolder, 'Large icons');
                assert.ok(await reportsFolder.locator('.workspace-item-graphic').evaluate(element => element.getBoundingClientRect().width) > smallFolderWidth);
                await chooseView(reportsFolder, 'Details');
                await expect(reportsFolder.locator('..')).toHaveClass('workspace-items-list');
                await expect(reportsFolder).toHaveAttribute('aria-pressed', 'true');
                assert.equal((await drawerState()).category, 'all');
                await chooseView(reportsFolder, 'Medium icons');
                const nameSize = await reportsFolder.locator('.workspace-item-name').evaluate(element => parseFloat(getComputedStyle(element).fontSize));
                assert.ok(nameSize >= 16);
                await fileBrowser.getByRole('button', { name: 'Open folder', exact: true }).click();
                assert.equal((await drawerState()).category, 'monitoring_reports');
                await expect(fileBrowser.locator('[data-project-file-category]:visible')).toHaveCount(0);
                await expect(fileBrowser.locator('[data-project-document-key]:visible')).toHaveCount(2);

                const poster = fileBrowser.locator(`[data-project-document-key="${fixture.posterKey}"]`);
                const posterSelection = fileBrowser.locator(`[data-project-file-selection="${fixture.posterKey}"]`);
                await poster.click();
                await expect(poster).toHaveAttribute('aria-pressed', 'true');
                await expect(page.locator('[data-project-document-preview-content]')).toHaveCount(0);
                await expect(posterSelection.getByRole('button', { name: 'Preview', exact: true })).toBeVisible();
                const thumbnail = poster.locator('img');
                assert.ok(await poster.locator('.workspace-item-name').evaluate(element => parseFloat(getComputedStyle(element).fontSize)) >= 16);
                await thumbnail.evaluate(image => image.decode());
                assert.equal(await thumbnail.evaluate(image => image.naturalWidth), 1024);
                assert.equal(await thumbnail.evaluate(image => image.naturalHeight), 1536);
                await screenshot('reports');
                await poster.dblclick();
                const preview = page.locator('[data-project-document-preview-content]:visible');
                const previewImage = preview.locator('[data-project-document-image-preview]');
                await expect(previewImage).toHaveAttribute('src', fixture.posterView);
                await previewImage.evaluate(image => image.decode());
                await expect(preview.locator('[data-pdf-annotation-config]')).toHaveCount(0);
                await screenshot('poster');
                await preview.getByRole('button', { name: 'Close', exact: true }).click();
                await expect(page.locator('[data-project-document-preview-content]')).toHaveCount(0);
                await unlocked();
                await expect(poster).toBeFocused();
                await wheelScrollsPage(poster);
                await poster.press('Enter');
                await expect(previewImage).toBeVisible();
                await previewImage.evaluate(image => image.dispatchEvent(new Event('error')));
                await expect(preview.getByRole('alert')).toContainText('The image could not be loaded');
                await expect(previewImage).toBeHidden();
                await page.keyboard.press('Escape');
                await expect(page.locator('[data-project-document-preview-content]')).toHaveCount(0);
                await unlocked();
                const downloadPromise = page.waitForEvent('download');
                await posterSelection.getByRole('link', { name: 'Download', exact: true }).click();
                const downloadedPoster = await downloadPromise;
                assert.equal(downloadedPoster.suggestedFilename(), 'sample-project-poster.png');
                const chunks = [];
                for await (const chunk of await downloadedPoster.createReadStream()) chunks.push(chunk);
                assert.deepEqual(Buffer.concat(chunks), posterImage);
                await thumbnail.evaluate(image => image.dispatchEvent(new Event('error')));
                await expect(poster.getByText('IMG', { exact: true })).toBeVisible();

                const terminal = fileBrowser.locator('[data-project-document-key^="narrative-report-"]');
                await terminal.click();
                const terminalSelection = fileBrowser.locator(`[data-project-file-selection="${await terminal.getAttribute('data-project-document-key')}"]`);
                await expect(terminalSelection.getByRole('button', { name: 'Preview', exact: true })).toBeVisible();
                await terminal.press('Enter');
                await expect(page.locator('[data-project-document-preview-content]:visible [data-pdf-annotation-config]')).toBeVisible();
                await page.keyboard.press('Escape');
                const terminalDownload = page.waitForEvent('download');
                await terminalSelection.getByRole('link', { name: 'Download', exact: true }).click();
                assert.equal((await terminalDownload).suggestedFilename(), 'terminal-report.pdf');

                await page.getByRole('button', { name: 'Up to project folders', exact: true }).click();
                assert.equal((await drawerState()).category, 'all');
                const proposalFolder = fileBrowser.locator('[data-project-file-category="proposal_papers"]');
                await proposalFolder.dblclick();
                const proposal = fileBrowser.locator('[data-project-document-key^="proposal-version-file-"]');
                await expect(proposal).toBeVisible();
                await proposal.click();
                const proposalSelection = fileBrowser.locator(`[data-project-file-selection="${await proposal.getAttribute('data-project-document-key')}"]`);
                const proposalDownloadUrl = await proposalSelection.getByRole('link', { name: 'Download', exact: true }).getAttribute('href');
                assert.match(proposalDownloadUrl, /\/files\//);
                await proposalSelection.getByRole('button', { name: 'Preview', exact: true }).click();
                const pdfPreview = page.locator('[data-project-document-preview-content]:visible [data-pdf-annotation-config]');
                await expect(pdfPreview).toBeVisible();
                const previewConfiguration = await pdfPreview.evaluate(element => JSON.parse(element.dataset.pdfAnnotationConfig));
                assert.equal(previewConfiguration.canAnnotate, false);
                assert.equal(await pdfPreview.getAttribute('data-preview-url'), previewConfiguration.pdfUrl);
                await page.keyboard.press('Escape');
                await unlocked();

                await fileBrowser.getByRole('button', { name: 'Add PDF files', exact: false }).click();
                const upload = fileBrowser.locator('#project-file-upload');
                await expect(upload).toBeVisible();
                await upload.locator('input[type="file"]').setInputFiles({ name: 'supporting-evidence.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.7\nEvidence') });
                const dropZone = upload.locator('label:has(input[type="file"])');
                await dropZone.evaluate(element => {
                    const transfer = new DataTransfer();
                    transfer.items.add(new File(['%PDF-1.7\nSecond evidence'], 'second-evidence.pdf', { type: 'application/pdf' }));
                    transfer.items.add(new File(['image'], 'ordinary-image.png', { type: 'image/png' }));
                    element.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer }));
                });
                assert.deepEqual((await drawerState()).queued, ['supporting-evidence.pdf', 'second-evidence.pdf']);
                assert.match((await drawerState()).error, /1 non-PDF file was not added/);
                await expect(upload.getByRole('button', { name: 'Upload 2 PDFs', exact: true })).toBeEnabled();
                assert.equal(await upload.locator('input[type="file"]').evaluate(input => input.files.length), 2);
                await upload.getByRole('button', { name: 'Remove second-evidence.pdf', exact: true }).click();
                assert.deepEqual((await drawerState()).queued, ['supporting-evidence.pdf']);
                await expect(upload.getByRole('button', { name: 'Upload 1 PDF', exact: true })).toBeEnabled();
                const selectedBeforeLayout = (await drawerState()).selected;
                await chooseView(proposal, 'Large icons');
                await chooseView(proposal, 'Details');
                await expect(proposal.locator('..')).toHaveClass('workspace-items-list');
                assert.equal((await drawerState()).selected, selectedBeforeLayout);
                assert.equal((await drawerState()).category, 'proposal_papers');
                assert.deepEqual((await drawerState()).queued, ['supporting-evidence.pdf']);
                assert.equal(await upload.locator('input[type="file"]').evaluate(input => input.files.length), 1);
                assert.equal(posts, 0);
                await fileBrowser.getByRole('button', { name: 'Add PDF files', exact: false }).click();

                const recordMenu = page.locator('[data-project-record-menu]');
                await recordMenu.locator('summary').click();
                await page.locator('#proposal-details-tab-button').click();
                await expect(files).toBeHidden();
                await expect(page.locator('#proposal-details-tab')).toBeVisible();
                await unlocked();
                await topic.evaluate(element => element.dispatchEvent(new CustomEvent('open-project-documents', { bubbles: true, detail: { category: 'monitoring_reports' } })));
                await expect(filesTab).toHaveAttribute('aria-selected', 'true');
                await expect(files).toBeVisible();
                assert.equal((await drawerState()).category, 'monitoring_reports');
                await expect(poster).toBeVisible();
                await unlocked();
                await expect(page.locator('[data-project-documents-floating-trigger]')).toHaveCount(0);
                await screenshot('shortcut');
                assert.deepEqual(errors, []);

                await initialize();
                await filesTab.click();
                await expect(files.locator('[data-workspace-sizing]')).toHaveAttribute('data-size', 'large');
                assert.equal((await drawerState()).view, 'list');
                await expect(reportsFolder.locator('..')).toHaveClass('workspace-items-list');

                pageHtml = await page.evaluate(source => {
                    const document = new DOMParser().parseFromString(source, 'text/html');
                    const root = document.querySelector('[data-topic-workspace]');
                    const reducedRoot = root.cloneNode(false);
                    reducedRoot.append(root.querySelector('[data-project-workspace-navigation]').cloneNode(true));
                    reducedRoot.append(root.querySelector('#project-files-tab').cloneNode(true));
                    return reducedRoot.outerHTML;
                }, readerHtml);
                await initialize();
                await filesTab.click();
                await expect(files).toBeVisible();
                await expect(fileBrowser.getByRole('button', { name: 'Add PDF files', exact: false })).toHaveCount(0);
                await expect(fileBrowser.locator('#project-file-upload')).toHaveCount(0);
                await expect(fileBrowser.locator('[data-project-file-category]:visible')).toHaveCount(2);
                await expect(files.locator('[data-workspace-sizing]')).toHaveAttribute('data-size', 'medium');
                assert.equal((await drawerState()).view, 'grid');
                await unlocked();
                await screenshot('reader');
                assert.equal(posts, 0);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
