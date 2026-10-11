import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('the rendered project views preserve monitoring, poster covers, file selection and personal folders in both themes', async () => {
    if (process.env.ATHENA_WORKSPACE_FIXTURE_READY !== '1') {
        execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ProjectWorkspaceTest.php', '--filter=exports project workspace'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_EXPORT_PROJECT_WORKSPACE: '1' },
        });
    }
    const html = readFileSync(resolve('storage/framework/testing/project-workspace.html'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8') + '\n' + readFileSync(resolve('resources/css/project-workspace.css'), 'utf8'), { from: undefined });
    const module = readFileSync(resolve('resources/js/project-workspace.js'), 'utf8').replaceAll('export ', '');
    const posterImage = readFileSync(resolve('public/images/sample-maintenance-poster.png'));
    const documents = [
        { key: 'proposal-pdf', category: 'proposal', title: 'Approved proposal', filename: 'Coastal research proposal.pdf', note: '', source: 'Approved submission', uploaded_by: 'Elena Santos', uploaded_at: '2025-06-01T00:00:00Z', file_size: 24576, view_url: 'http://localhost/files/proposal/view', download_url: 'http://localhost/files/proposal/download', generated_comment_response: true, official: true },
        { key: 'report-docx', category: 'monitoring', title: 'Final report', filename: 'Coastal final report.docx', note: '', source: 'Completed project', uploaded_by: 'Elena Santos', uploaded_at: '2026-09-30T00:00:00Z', file_size: 8192, view_url: 'http://localhost/files/report/view', download_url: 'http://localhost/files/report/download', official: true },
        { key: 'terminal-poster', category: 'monitoring', title: 'Terminal report poster', filename: 'SAMPLE-predictive-maintenance-poster.png', note: 'SAMPLE POSTER — illustrative UI demonstration, not actual research findings.', source: 'Terminal report', uploaded_by: 'Elena Santos', uploaded_at: '2026-09-30T00:00:00Z', file_size: 1655231, view_url: 'http://localhost/files/poster/view', thumbnail_url: 'http://localhost/files/poster/view', download_url: 'http://localhost/files/poster/download', preview_kind: 'image', official: true },
    ];
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1024, 1440]) {
            for (const dark of [false, true]) {
                const context = await browser.newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => { if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text()); });
                const workspaceHtml = await page.evaluate(source => {
                    const rendered = new DOMParser().parseFromString(source, 'text/html');
                    return (rendered.querySelector('[data-project-records-page]') || rendered.querySelector('[data-project-monitoring-list]')).outerHTML;
                }, html);
                let monitoringHtml;
                let persisted;
                let documentRequests = 0;
                let rejectNextSave = false;
                let holdNextSave = false;
                let releaseSave;
                let markSaveHeld;
                const saveHeld = new Promise(resolve => { markSaveHeld = resolve; });
                await page.context().route('**/*', async route => {
                    const request = route.request();
                    if (request.method() === 'PUT') {
                        assert.equal(request.headers()['x-csrf-token'], 'workspace-test-token');
                        if (holdNextSave) {
                            holdNextSave = false;
                            await new Promise(resolve => { releaseSave = resolve; markSaveHeld(); });
                        }
                        if (rejectNextSave) {
                            rejectNextSave = false;
                            return route.fulfill({ status: 409, json: { message: 'Another tab saved first.' } });
                        }
                        const incoming = request.postDataJSON();
                        persisted = { ...incoming, revision: incoming.revision + 1 };
                        return route.fulfill({ json: { workspace: persisted } });
                    }
                    if (/\/project-workspace\/\d+\/documents$/.test(new URL(request.url()).pathname)) {
                        documentRequests++;
                        return route.fulfill({ json: { categories: [{ key: 'proposal', label: 'Proposal documents', description: 'Approved submissions', count: 1 }, { key: 'monitoring', label: 'Monitoring reports', description: 'Project reports', count: 2 }], documents, total: documents.length } });
                    }
                    if (request.url() === documents[2].view_url) {
                        return route.fulfill({ contentType: 'image/png', body: posterImage });
                    }
                    if (request.url() === documents[2].download_url) {
                        return route.fulfill({ contentType: 'image/png', headers: { 'content-disposition': `attachment; filename="${documents[2].filename}"` }, body: posterImage });
                    }
                    if (request.url().endsWith('/workspace-test') || request.url().endsWith('/monitoring-test')) {
                        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="csrf-token" content="workspace-test-token"><style>${styles.css}\n[x-cloak]{display:none!important}\n@media(min-width:640px){main{max-width:calc(100vw - 320px)!important}}</style></head><body class="bg-slate-50 p-4 font-sans dark:bg-slate-950"><main class="mx-auto max-w-7xl">${request.url().endsWith('/monitoring-test') ? monitoringHtml : workspaceHtml}</main></body></html>` });
                    }
                    return route.abort();
                });
                const initialize = async (targetPage, url) => {
                    await targetPage.goto(url);
                    await targetPage.addScriptTag({ content: `${module}\ndocument.addEventListener('alpine:init', () => { Alpine.data('projectWorkspace', projectWorkspace); Alpine.data('pdfAnnotationWorkspace', config => ({loading:false, loadError:'', viewerRefreshRequired:false, init(){this.$el.dataset.previewUrl = config.pdfUrl;}})); });` });
                    await targetPage.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                };
                await initialize(page, 'http://localhost/workspace-test');
                const workspace = page.locator('[data-project-workspace]');
                await workspace.waitFor({ state: 'visible' });
                const filesTab = page.getByRole('tab', { name: 'Files', exact: true });
                const monitoringTab = page.getByRole('tab', { name: 'Monitoring', exact: true });
                await expect(filesTab).toHaveAttribute('aria-selected', 'true');
                await expect(monitoringTab).toHaveAttribute('aria-selected', 'false');
                await expect(page.locator('[data-project-monitoring]')).toBeHidden();
                const monitoringFilters = page.locator('form[method="GET"]');
                await expect(monitoringFilters).toBeHidden();
                await expect(page.locator('[data-kpi-strip]')).toHaveCount(0);
                await expect(page.locator('input[type="search"]:visible')).toHaveCount(1);
                await expect(page.locator('#project-status')).toHaveCount(0);
                await expect(monitoringFilters.getByRole('link', { name: 'Clear', exact: true, includeHidden: true })).toHaveCount(0);
                const actions = workspace.locator('[aria-label="File actions"]');
                const action = name => actions.getByRole('button', { name, exact: true, includeHidden: true });
                for (const name of ['Open', 'Move to…', 'Rename', 'Remove folder', 'Undo']) await expect(action(name)).toBeHidden();
                await expect(page.locator('#project-files-panel')).toHaveAttribute('role', 'tabpanel');
                await expect(page.locator('#project-monitoring-panel')).toHaveAttribute('role', 'tabpanel');
                await filesTab.press('Home');
                await expect(monitoringTab).toHaveAttribute('aria-selected', 'true');
                await expect(monitoringTab).toBeFocused();
                await expect(workspace).toBeHidden();
                await expect(monitoringFilters).toBeVisible();
                await expect(page.locator('[data-kpi-strip]')).toHaveCount(0);
                await expect(page.locator('input[type="search"]:visible')).toHaveCount(1);
                await monitoringTab.press('End');
                await expect(filesTab).toHaveAttribute('aria-selected', 'true');
                await expect(filesTab).toBeFocused();
                await filesTab.press('ArrowLeft');
                await expect(monitoringTab).toHaveAttribute('aria-selected', 'true');
                await monitoringTab.press('ArrowRight');
                await expect(filesTab).toHaveAttribute('aria-selected', 'true');
                await workspace.evaluate((element, posterUrl) => { Alpine.$data(element).projects.find(project => project.title === 'Coastal communities research').poster_url = posterUrl; }, documents[2].view_url);
                const explorerState = targetWorkspace => targetWorkspace.evaluate(element => {
                    const data = Alpine.$data(element);
                    return JSON.parse(JSON.stringify({ location: data.location, query: data.query, selectedDocumentKey: data.selectedDocumentKey, locationHistory: data.locationHistory, historyIndex: data.historyIndex, workspace: data.workspace, documentKeys: data.projectDocuments.map(document => document.key) }));
                });
                const state = () => workspace.evaluate(element => JSON.parse(JSON.stringify(Alpine.$data(element).workspace)));
                const location = () => workspace.evaluate(element => Alpine.$data(element).location);
                const settled = () => page.waitForFunction(() => !Alpine.$data(document.querySelector('[data-project-workspace]')).saving);
                const item = (type, id) => workspace.locator(`[data-workspace-${type}][data-item-id="${id}"]:visible`);
                const projects = workspace.locator('[data-workspace-project]:visible');
                const folderNav = name => page.getByRole('navigation', { name: 'Project folders' }).getByRole('button', { name: `Open folder ${name}`, exact: true });
                const openFolder = async folder => {
                    if (width < 1024) await page.getByLabel('Go to folder').selectOption(folder.id);
                    else await folderNav(folder.name).click();
                };
                const screenshot = async (name, targetPage = page, targetWorkspace = workspace) => {
                    await targetWorkspace.evaluate(async element => {
                        await new Promise(requestAnimationFrame);
                        await Promise.all(element.getAnimations({ subtree: true }).map(animation => animation.finished.catch(() => {})));
                    });
                    assert.equal(await targetPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `overflow at ${width}px in ${name}`);
                    assert.deepEqual(errors, []);
                    if (process.env.PROJECT_WORKSPACE_SCREENSHOT_DIRECTORY) {
                        mkdirSync(process.env.PROJECT_WORKSPACE_SCREENSHOT_DIRECTORY, { recursive: true });
                        await targetPage.screenshot({ path: resolve(process.env.PROJECT_WORKSPACE_SCREENSHOT_DIRECTORY, `project-workspace-${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: false });
                    }
                };
                const readableName = async locator => {
                    assert.equal(await locator.getAttribute('role'), 'button');
                    const font = await locator.locator('.workspace-item-name').evaluate(element => ({ size: parseFloat(getComputedStyle(element).fontSize), family: getComputedStyle(element).fontFamily }));
                    assert.ok(font.size >= 16, `Explorer names should be at least 16px, got ${font.size}px`);
                    assert.match(font.family, /Plus Jakarta Sans/);
                };
                const createFolder = async (name, checkSavingFocus = false) => {
                    await page.getByRole('button', { name: 'New folder', exact: true }).click();
                    const dialog = page.getByRole('dialog', { name: 'New folder', exact: true });
                    await dialog.waitFor({ state: 'visible' });
                    await dialog.getByLabel('Folder name').fill(name);
                    holdNextSave = checkSavingFocus;
                    await dialog.getByRole('button', { name: 'Create folder', exact: true }).click();
                    if (checkSavingFocus) {
                        await saveHeld;
                        await page.waitForFunction(() => Alpine.$data(document.querySelector('[data-project-workspace]')).saving);
                        await expect(action('Undo')).toBeVisible();
                        await expect(action('Undo')).toBeDisabled();
                        await page.keyboard.press('Tab');
                        await page.keyboard.press('Shift+Tab');
                        assert.equal(await dialog.getAttribute('tabindex'), '0');
                        assert.deepEqual(errors, []);
                        releaseSave();
                    }
                    await dialog.waitFor({ state: 'hidden' });
                    await page.waitForFunction(folderName => Alpine.$data(document.querySelector('[data-project-workspace]')).workspace.folders.some(folder => folder.name === folderName), name);
                    return (await state()).folders.find(folder => folder.name === name);
                };

                await expect(workspace.locator('[data-workspace-year]:visible')).toHaveCount(2);
                await expect(projects).toHaveCount(0);
                assert.equal(documentRequests, 0);
                await readableName(item('year', '2025'));
                await screenshot('root');
                const projectSearch = page.getByLabel('Search this location');
                await projectSearch.fill('  sAnToS  ');
                await expect(projects).toHaveCount(18);
                await projectSearch.fill('Neil');
                await expect(projects).toHaveCount(0);
                await expect(workspace.getByText('No matching projects', { exact: true })).toBeVisible();
                await projectSearch.fill('');
                await expect(workspace.getByText('No matching projects', { exact: true })).toBeHidden();
                await expect(workspace.locator('[data-workspace-year]:visible')).toHaveCount(2);
                await item('year', '2025').press('Control+a');
                await expect(action('Move to…')).toBeHidden();
                await page.getByLabel('Browse projects').selectOption('none');
                await settled();
                await expect(projects).toHaveCount(18);
                assert.equal(persisted.grouping, 'none');
                await page.getByRole('button', { name: 'Select project Community study 2', exact: true }).click();
                await page.getByLabel('Browse projects').selectOption('year');
                await settled();
                await expect(projects).toHaveCount(0);
                await expect(action('Move to…')).toBeHidden();
                assert.equal(persisted.grouping, 'year');
                await item('year', '2025').click();
                assert.equal(await location(), 'all');
                await expect(item('year', '2025')).toHaveAttribute('aria-pressed', 'true');
                await expect(action('Open')).toBeVisible();
                for (const name of ['Move to…', 'Rename', 'Remove folder']) await expect(action(name)).toBeHidden();
                await item('year', '2025').press('Enter');
                await expect(projects).toHaveCount(9);
                assert.equal(await location(), 'year:2025');
                if (width < 1024) await expect(page.getByLabel('Go to folder')).toHaveValue('year:2025');
                assert.equal(documentRequests, 0);
                const coastal = page.getByRole('button', { name: 'Select project Coastal communities research', exact: true });
                await readableName(coastal);
                const projectCover = coastal.locator('[data-workspace-project-cover] img');
                await expect(projectCover).toHaveAttribute('src', documents[2].view_url);
                await projectCover.scrollIntoViewIfNeeded();
                await projectCover.evaluate(image => image.decode());
                assert.equal(await projectCover.evaluate(image => image.naturalWidth), 1024);
                assert.equal(await projectCover.evaluate(image => image.naturalHeight), 1536);
                assert.equal(await page.getByRole('button', { name: 'Select project Community study 1', exact: true }).locator('[data-workspace-project-cover] img').count(), 0);
                await screenshot('year');
                await workspace.evaluate(element => { Alpine.$data(element).projects.find(project => project.title === 'Community study 3').progress = null; });
                await page.getByRole('button', { name: 'Select project Community study 3', exact: true }).click();
                await expect(workspace.getByText('No progress report yet', { exact: true })).toBeVisible();
                await coastal.click();
                assert.equal(await location(), 'year:2025');
                assert.equal(documentRequests, 0);
                await expect(coastal).toHaveAttribute('aria-pressed', 'true');
                await expect(action('Open')).toBeVisible();
                await expect(action('Move to…')).toBeVisible();
                for (const name of ['Rename', 'Remove folder']) await expect(action(name)).toBeHidden();
                const completedLink = page.getByRole('link', { name: 'View record: Coastal communities research', exact: true });
                await expect(completedLink).toBeVisible();
                const completedUrl = await completedLink.getAttribute('href');
                assert.equal(completedUrl.includes('#project-monitoring'), false);
                assert.equal(await workspace.getByRole('link', { name: /^Monitor project:/ }).count(), 0);
                await coastal.dblclick();
                await expect(workspace.locator('[data-workspace-document]:visible')).toHaveCount(3);
                await expect(action('New folder')).toBeHidden();
                for (const name of ['Open', 'Move to…', 'Rename', 'Remove folder']) await expect(action(name)).toBeHidden();
                const projectLocation = await location();
                assert.match(projectLocation, /^project:\d+$/);
                if (width < 1024) {
                    await expect(page.getByLabel('Go to folder')).toHaveValue(projectLocation);
                    await expect(page.getByLabel('Go to folder').locator('option:checked')).toHaveText('Coastal communities research');
                }
                assert.equal(documentRequests, 1);
                const pdf = item('document', 'proposal-pdf');
                const docx = item('document', 'report-docx');
                const poster = item('document', 'terminal-poster');
                const projectSummary = workspace.locator('[data-project-summary]');
                await expect(projectSummary).toBeVisible();
                await expect(projectSummary).toContainText('Coastal communities research');
                await expect(projectSummary).toContainText('Elena Santos');
                await expect(projectSummary).toContainText('Completed');
                await expect(projectSummary).toContainText('100% progress');
                await expect(projectSummary.getByText('1 report', { exact: true })).toBeVisible();
                await expect(projectSummary).toContainText('1 awaiting review');
                await expect(projectSummary).toContainText('Project secretary: Elena Santos');
                await expect(projectSummary).toContainText('Tool · Sep 30, 2026');
                assert.equal(await projectSummary.evaluate(element => Boolean(element.compareDocumentPosition(element.closest('[data-project-workspace]').querySelector('[data-workspace-items]')) & Node.DOCUMENT_POSITION_FOLLOWING)), true, 'project summary should appear above the files');
                await readableName(pdf);
                await readableName(docx);
                await readableName(poster);
                assert.match(await pdf.locator('.workspace-file-type').textContent(), /PDF/);
                assert.match(await docx.locator('.workspace-file-type').textContent(), /DOCX/);
                await screenshot('documents');
                await pdf.click();
                assert.equal(await workspace.evaluate(element => Alpine.$data(element).previewDocument), null);
                await expect(pdf).toHaveAttribute('aria-pressed', 'true');
                await expect(action('Open')).toBeVisible();
                for (const name of ['Move to…', 'Rename', 'Remove folder']) await expect(action(name)).toBeHidden();
                await expect(projectSummary.getByRole('link', { name: 'View record: Coastal communities research', exact: true })).toBeVisible();
                await expect(workspace.locator('[aria-label="Item details"]').getByRole('link', { name: 'Download', exact: true })).toHaveAttribute('download', documents[0].filename);
                await page.getByLabel('Search this location').fill('proposal');
                await expect(workspace.locator('[data-workspace-document]:visible')).toHaveCount(1);
                await pdf.click();
                const beforeMonitoring = await explorerState(workspace);
                await monitoringTab.click();
                await expect(workspace).toBeHidden();
                await expect(page.locator('[data-project-monitoring]')).toBeVisible();
                await expect(monitoringFilters).toBeVisible();
                await expect(page.locator('[data-kpi-strip]')).toHaveCount(0);
                const monitoringRow = page.locator(`[data-project-monitoring] [data-project-id="${projectLocation.slice('project:'.length)}"]`);
                await expect(monitoringRow.getByRole('heading', { name: 'Coastal communities research', exact: true })).toBeVisible();
                await expect(monitoringRow.getByText('Elena Santos', { exact: true })).toHaveCount(2);
                await expect(monitoringRow.getByText('Completed', { exact: true })).toBeVisible();
                await expect(monitoringRow.getByText('100%', { exact: true })).toBeVisible();
                await expect(monitoringRow.getByText('Tool · Sep 30, 2026', { exact: true })).toBeVisible();
                await expect(monitoringRow.getByText('1 total', { exact: true })).toBeVisible();
                await expect(monitoringRow.getByText('1 awaiting review', { exact: true })).toBeVisible();
                await expect(monitoringRow.getByRole('link', { name: 'View record: Coastal communities research', exact: true })).toHaveAttribute('href', completedUrl);
                await monitoringRow.scrollIntoViewIfNeeded();
                await screenshot('monitoring');
                await filesTab.click();
                await expect(workspace).toBeVisible();
                await expect(monitoringFilters).toBeHidden();
                await expect(page.locator('[data-kpi-strip]')).toHaveCount(0);
                assert.deepEqual(await explorerState(workspace), beforeMonitoring, 'switching views should preserve location, search, selection, history, preferences and cached files');
                await expect(pdf).toHaveAttribute('aria-pressed', 'true');
                await expect(projectSummary.getByRole('link', { name: 'View record: Coastal communities research', exact: true })).toBeVisible();
                assert.equal(documentRequests, 1);
                await page.getByLabel('Search this location').fill('');
                await pdf.click();
                await page.getByRole('button', { name: 'Open', exact: true }).click();
                await expect(workspace.locator('[data-preview-url="http://localhost/files/proposal/view"]')).toBeVisible();
                const pdfPreview = page.getByRole('dialog', { name: documents[0].filename, exact: true });
                await expect(pdfPreview.getByRole('link', { name: 'Download', exact: true })).toHaveAttribute('download', documents[0].filename);
                await pdfPreview.getByRole('button', { name: 'Close', exact: true }).click();
                await pdfPreview.waitFor({ state: 'hidden' });
                await expect(page.getByRole('button', { name: 'Open', exact: true })).toBeFocused();
                await docx.press('Enter');
                await page.waitForFunction(() => Alpine.$data(document.querySelector('[data-project-workspace]')).previewDocument?.key === 'report-docx');
                const documentPreview = page.getByRole('dialog', { name: documents[1].filename, exact: true });
                await expect(documentPreview.getByRole('link', { name: 'Download', exact: true })).toHaveAttribute('href', documents[1].download_url);
                await documentPreview.getByRole('button', { name: 'Close', exact: true }).click();
                await poster.scrollIntoViewIfNeeded();
                await expect(poster.locator('.workspace-image-graphic img')).toHaveAttribute('src', documents[2].thumbnail_url);
                await poster.locator('img').evaluate(image => image.decode());
                assert.equal(await poster.locator('img').evaluate(image => image.naturalWidth), 1024);
                assert.equal(await poster.locator('img').evaluate(image => image.naturalHeight), 1536);
                await screenshot('poster-thumbnail');
                await poster.dblclick();
                const posterPreview = page.getByRole('dialog', { name: documents[2].filename, exact: true });
                await expect(posterPreview.locator('[data-workspace-image-preview] img')).toBeVisible();
                assert.equal(await posterPreview.locator('.revision-pdf-viewer').count(), 0, 'poster images must not be passed to the PDF viewer');
                await posterPreview.locator('img').evaluate(image => image.decode());
                await expect(posterPreview.locator('img')).toHaveAttribute('src', documents[2].view_url);
                assert.equal(await posterPreview.locator('img').evaluate(image => image.naturalHeight), 1536);
                const posterBounds = await posterPreview.locator('img').boundingBox();
                assert.ok(Math.abs(posterBounds.width / posterBounds.height - 2 / 3) < 0.01, 'poster preview should preserve its portrait aspect ratio');
                await expect(posterPreview.getByRole('link', { name: 'Download', exact: true })).toHaveAttribute('href', documents[2].download_url);
                await screenshot('poster-preview');
                const downloadedPoster = page.waitForEvent('download');
                await posterPreview.getByRole('link', { name: 'Download', exact: true }).click();
                const download = await downloadedPoster;
                assert.equal(download.suggestedFilename(), documents[2].filename);
                assert.deepEqual(readFileSync(await download.path()), posterImage);
                await posterPreview.getByRole('button', { name: 'Close', exact: true }).click();
                await expect(projectSummary.getByRole('link', { name: 'View record: Coastal communities research', exact: true })).toBeVisible();
                await page.getByRole('button', { name: 'Details', exact: true }).click();
                await settled();
                assert.equal(persisted.view, 'list');
                assert.equal(await location(), projectLocation);
                await expect(page.getByRole('button', { name: 'Details', exact: true })).toHaveAttribute('aria-pressed', 'true');
                await page.getByLabel('Search this location').fill('final report');
                await expect(workspace.locator('[data-workspace-document]:visible')).toHaveCount(1);
                await page.getByLabel('Search this location').fill('');
                await page.getByRole('button', { name: 'Up', exact: true }).click();
                assert.equal(await location(), 'year:2025');
                if (width < 1024) await expect(page.getByLabel('Go to folder')).toHaveValue('year:2025');
                await coastal.press('Enter');
                await expect(workspace.locator('[data-workspace-document]:visible')).toHaveCount(3);
                assert.equal(documentRequests, 1, 'opening a cached project should not fetch its documents again');
                await page.getByRole('button', { name: 'Back', exact: true }).click();
                assert.equal(await location(), 'year:2025');
                await page.getByRole('button', { name: 'Up', exact: true }).click();
                assert.equal(await location(), 'all');
                await page.getByRole('button', { name: 'Icons', exact: true }).click();
                await settled();

                const parent = await createFolder('Coastal studies', true);
                await readableName(item('folder', parent.id));
                await item('year', '2025').dblclick();
                await page.getByRole('button', { name: 'Select project Community study 1', exact: true }).click();
                await page.getByRole('button', { name: 'Select project Community study 2', exact: true }).click({ modifiers: ['Control'] });
                await expect(action('Open')).toBeHidden();
                await expect(action('Move to…')).toBeVisible();
                await page.getByRole('button', { name: 'Move to…', exact: true }).click();
                const moveDialog = page.getByRole('dialog', { name: 'Move to a folder', exact: true });
                await moveDialog.getByLabel('Destination').selectOption(parent.id);
                await moveDialog.getByRole('button', { name: 'Move', exact: true }).click();
                await moveDialog.waitFor({ state: 'hidden' });
                await expect(action('New folder')).toBeFocused();
                assert.equal((await state()).assignments.length, 2);
                await openFolder(parent);
                await expect(projects).toHaveCount(2);
                const child = await createFolder('Final reports');
                assert.equal(child.parent_id, parent.id);
                await item('folder', child.id).click();
                assert.equal(await location(), parent.id);
                await item('folder', child.id).press('Enter');
                assert.equal(await location(), child.id);
                await expect(projects).toHaveCount(0);
                await page.getByRole('button', { name: 'Up', exact: true }).click();
                await page.getByRole('button', { name: 'Select project Community study 2', exact: true }).dragTo(item('folder', child.id));
                await page.waitForFunction(childId => Alpine.$data(document.querySelector('[data-project-workspace]')).workspace.assignments.some(assignment => assignment.folder_id === childId), child.id);
                await expect(projects).toHaveCount(1);
                await page.getByRole('button', { name: 'Up', exact: true }).click();
                await page.getByLabel('Search this location').fill('Community study 2');
                const blankPane = workspace.locator('.workspace-items-grid:visible');
                const blankBounds = await blankPane.boundingBox();
                const projectToUnfile = page.getByRole('button', { name: 'Select project Community study 2', exact: true });
                await projectToUnfile.dragTo(blankPane, { targetPosition: { x: blankBounds.width / 2, y: blankBounds.height / 2 } });
                await page.waitForFunction(() => Alpine.$data(document.querySelector('[data-project-workspace]')).workspace.assignments.length === 1);
                assert.equal((await state()).assignments.some(assignment => assignment.folder_id === child.id), false);
                await page.getByRole('button', { name: 'Undo', exact: true }).click();
                await page.waitForFunction(() => Alpine.$data(document.querySelector('[data-project-workspace]')).workspace.assignments.length === 2);
                await settled();
                await page.getByLabel('Search this location').fill('');
                const beforeInvalidDrop = await state();
                if (width >= 1024) {
                    await item('folder', parent.id).dragTo(folderNav('Final reports'));
                } else {
                    await item('folder', parent.id).click();
                    await page.getByRole('button', { name: 'Move to…', exact: true }).click();
                    const folderMove = page.getByRole('dialog', { name: 'Move to a folder', exact: true });
                    assert.equal(await folderMove.getByLabel('Destination').locator(`option[value="${parent.id}"], option[value="${child.id}"]`).count(), 0);
                    await folderMove.getByRole('button', { name: 'Cancel', exact: true }).click();
                }
                await settled();
                assert.deepEqual(await state(), beforeInvalidDrop, 'an ancestor drop must not bubble into the surrounding location');
                const archive = await createFolder('Archive');
                await item('folder', parent.id).dragTo(item('folder', archive.id));
                await page.waitForFunction(({ parentId, archiveId }) => Alpine.$data(document.querySelector('[data-project-workspace]')).workspace.folders.find(folder => folder.id === parentId)?.parent_id === archiveId, { parentId: parent.id, archiveId: archive.id });
                await openFolder(archive);
                await item('folder', parent.id).click();
                for (const name of ['Open', 'Move to…', 'Rename', 'Remove folder']) await expect(action(name)).toBeVisible();
                await page.getByRole('button', { name: 'Rename', exact: true }).click();
                const rename = page.getByRole('dialog', { name: 'Rename folder', exact: true });
                await rename.getByLabel('Folder name').fill('Coastal archive');
                await rename.getByRole('button', { name: 'Save name', exact: true }).click();
                await rename.waitFor({ state: 'hidden' });
                await expect(item('folder', parent.id)).toHaveAttribute('aria-label', 'Select folder Coastal archive');
                await page.getByRole('button', { name: 'Remove folder', exact: true }).click();
                const remove = page.getByRole('dialog', { name: 'Remove folder', exact: true });
                await remove.getByRole('button', { name: 'Remove folder', exact: true }).click();
                await remove.waitFor({ state: 'hidden' });
                await expect(action('New folder')).toBeFocused();
                for (const name of ['Open', 'Move to…', 'Rename', 'Remove folder']) await expect(action(name)).toBeHidden();
                assert.deepEqual((await state()).assignments, []);
                assert.deepEqual((await state()).folders.map(folder => folder.id), [archive.id]);
                await page.getByRole('button', { name: 'Undo', exact: true }).click();
                await expect(item('folder', parent.id)).toHaveAttribute('aria-label', 'Select folder Coastal archive');
                assert.equal((await state()).assignments.length, 2);
                assert.equal((await state()).folders.length, 3);
                await expect(action('Undo')).toBeHidden();
                await page.getByRole('button', { name: 'Up', exact: true }).click();
                await item('year', '2024').dblclick();
                await page.getByLabel('Search this location').fill('Community study 17');
                await expect(projects).toHaveCount(1);
                await page.getByLabel('Search this location').fill('');
                await expect(projects).toHaveCount(9);
                await screenshot('organized');

                rejectNextSave = true;
                await page.getByRole('button', { name: 'Details', exact: true }).click();
                await page.getByRole('button', { name: 'Reload workspace', exact: true }).waitFor({ state: 'visible' });
                assert.equal((await state()).view, 'grid');
                assert.equal(await page.getByRole('button', { name: 'New folder', exact: true }).isDisabled(), true);
                assert.deepEqual(errors, []);

                monitoringHtml = await page.evaluate(source => new DOMParser().parseFromString(source, 'text/html').querySelector('[data-project-records-page]').outerHTML, readFileSync(resolve('storage/framework/testing/project-workspace-monitoring.html'), 'utf8'));
                const activePage = await page.context().newPage();
                activePage.on('pageerror', error => errors.push(error.message));
                activePage.on('console', message => { if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text()); });
                await initialize(activePage, 'http://localhost/monitoring-test');
                const activeWorkspace = activePage.locator('[data-project-workspace]');
                const activeMonitoringTab = activePage.getByRole('tab', { name: 'Monitoring', exact: true });
                const activeFilesTab = activePage.getByRole('tab', { name: 'Files', exact: true });
                await expect(activeMonitoringTab).toHaveAttribute('aria-selected', 'true');
                await expect(activeFilesTab).toHaveAttribute('aria-selected', 'false');
                await expect(activeWorkspace).toBeHidden();
                await expect(activePage.locator('[data-project-monitoring]')).toBeVisible();
                await expect(activePage.locator('form[method="GET"]')).toBeVisible();
                await expect(activePage.locator('[data-kpi-strip]')).toBeVisible();
                await expect(activePage.getByLabel('Project status', { exact: true })).toBeVisible();
                const activeMonitoringRow = activePage.locator('[data-project-monitoring] article').filter({ has: activePage.getByRole('heading', { name: 'Coastal communities research', exact: true }) });
                await expect(activeMonitoringRow.getByText('Elena Santos', { exact: true })).toHaveCount(2);
                await expect(activeMonitoringRow.getByText('Ongoing', { exact: true })).toBeVisible();
                await expect(activeMonitoringRow.getByText('35%', { exact: true })).toBeVisible();
                await expect(activeMonitoringRow.getByText('Tool · Sep 30, 2026', { exact: true })).toBeVisible();
                await expect(activeMonitoringRow.getByText('1 total', { exact: true })).toBeVisible();
                await expect(activeMonitoringRow.getByText('1 awaiting review', { exact: true })).toBeVisible();
                const monitorUrl = await activeMonitoringRow.getByRole('link', { name: 'Monitor project: Coastal communities research', exact: true }).getAttribute('href');
                assert.equal(monitorUrl.endsWith('#project-monitoring'), true);
                await activeMonitoringRow.scrollIntoViewIfNeeded();
                await screenshot('active-monitoring', activePage, activeWorkspace);
                await activeFilesTab.click();
                await expect(activeWorkspace).toBeVisible();
                await expect(activePage.locator('form[method="GET"]')).toBeHidden();
                await expect(activePage.locator('[data-kpi-strip]')).toBeHidden();
                await expect(activePage.locator('input[type="search"]:visible')).toHaveCount(1);
                await activeWorkspace.locator('[data-workspace-year][data-item-id="2025"]').dblclick();
                const activeCoastal = activePage.getByRole('button', { name: 'Select project Coastal communities research', exact: true });
                await activeCoastal.dblclick();
                await expect(activeWorkspace.locator('[data-workspace-document]:visible')).toHaveCount(3);
                await activeWorkspace.locator('[data-workspace-document][data-item-id="proposal-pdf"]:visible').click();
                const activeSummary = activeWorkspace.locator('[data-project-summary]');
                await expect(activeSummary).toBeVisible();
                await expect(activeSummary).toContainText('35% progress');
                await expect(activeSummary.getByRole('link', { name: 'Monitor project: Coastal communities research', exact: true })).toHaveAttribute('href', monitorUrl);
                await expect(activeSummary.getByRole('link', { name: 'Monitor project: Coastal communities research', exact: true })).toBeVisible();
                await activeSummary.scrollIntoViewIfNeeded();
                await screenshot('active-files', activePage, activeWorkspace);
                const beforeActiveMonitoring = await explorerState(activeWorkspace);
                await activeMonitoringTab.click();
                await expect(activeWorkspace).toBeHidden();
                await activeFilesTab.click();
                await expect(activeWorkspace).toBeVisible();
                assert.deepEqual(await explorerState(activeWorkspace), beforeActiveMonitoring);
                await expect(activeSummary.getByRole('link', { name: 'Monitor project: Coastal communities research', exact: true })).toBeVisible();
                assert.deepEqual(errors, []);
                await activePage.close();
                await page.close();
                await context.close();
            }
        }
    } catch (error) {
        if (process.env.PROJECT_WORKSPACE_SCREENSHOT_DIRECTORY) {
            mkdirSync(process.env.PROJECT_WORKSPACE_SCREENSHOT_DIRECTORY, { recursive: true });
            for (const page of browser.contexts().flatMap(context => context.pages())) {
                await page.screenshot({ path: resolve(process.env.PROJECT_WORKSPACE_SCREENSHOT_DIRECTORY, 'project-workspace-failure.png'), fullPage: true });
            }
        }
        throw error;
    } finally {
        await browser.close();
    }
});
