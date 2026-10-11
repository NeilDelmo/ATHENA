import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { projectStatusManager } from '../../resources/js/project-status-manager.js';

test('the rendered monitoring workspace prioritizes reports and supports disclosure and tab navigation in both themes', async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ProjectMonitoringTest.php', 'tests/Feature/MonitoringObjectiveAlertTest.php', 'tests/Feature/PostApprovalUiDemoSeederTest.php', '--filter=post approval UI|monitoring prioritizes|progress quarter rows|a revised Monitoring Tool remains|monitoring alerts are visible'], {
        encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_MONITORING_LAYOUT: '1' },
    });
    const overdueAlert = readFileSync(resolve('storage/framework/testing/monitoring-overdue-alert.html'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8') + '\n' + readFileSync(resolve('resources/css/proposal-paper-workspace.css'), 'utf8'), { from: undefined });
    const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
    const secretaryPicker = app.slice(app.indexOf("Alpine.data('researchSecretaryPicker',"), app.indexOf("Alpine.data('budgetUtilizationForm',"));
    const pdfWorkspace = readFileSync(resolve('resources/js/pdf-annotation-workspace.js'), 'utf8')
        .replaceAll('export default ', '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const fixture of ['completed-faculty_researcher', 'completed-research_head', 'active', 'history', 'delayed-head']) {
            const html = readFileSync(resolve(`storage/framework/testing/monitoring-${fixture}.html`), 'utf8');
            for (const width of (fixture === 'delayed-head' ? [390, 1440] : [320, 390, 768, 1440])) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.route('**/*', route => route.abort());
                    const rendered = await page.evaluate(({ source, alertHtml }) => {
                        const document = new DOMParser().parseFromString(source, 'text/html');
                        const workspace = document.querySelector('[data-topic-workspace]');
                        const frame = document.createElement('div');
                        frame.className = workspace.className;
                        frame.setAttribute('data-topic-workspace', '');
                        frame.setAttribute('x-data', workspace.getAttribute('x-data'));
                        frame.setAttribute('@hashchange.window', 'syncTopicTab()');
                        frame.append(workspace.querySelector('nav').cloneNode(true));
                        for (const tab of ['proposal-details', 'proposal-review', 'notice-to-proceed', 'version-history']) {
                            const panel = document.createElement('section');
                            panel.id = `${tab}-tab`;
                            panel.setAttribute('x-cloak', '');
                            const key = { 'proposal-details': 'details', 'proposal-review': 'review', 'notice-to-proceed': 'notice', 'version-history': 'history' }[tab];
                            panel.setAttribute('x-show', `activeTopicTab === '${key}'`);
                            panel.textContent = `${tab} content`;
                            frame.append(panel);
                        }
                        const monitoring = workspace.querySelector('#project-monitoring-tab').cloneNode(true);
                        if (alertHtml) {
                            const alert = new DOMParser().parseFromString(alertHtml, 'text/html').querySelector('[data-monitoring-work-plan-alert]');
                            monitoring.querySelector('[data-monitoring-work-plan-alert]')?.remove();
                            const budget = monitoring.querySelector('[data-monitoring-budget-section]');
                            budget.before(alert);
                        }
                        frame.append(monitoring);
                        frame.append(workspace.querySelector('[x-ref="workspaceBackLink"]').cloneNode(true));
                        return document.querySelector('.project-page-header').outerHTML + frame.outerHTML;
                    }, { source: html, alertHtml: fixture === 'active' ? overdueAlert : '' });
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n[x-cloak]{display:none!important}</style><body class="bg-slate-50 p-4 dark:bg-slate-950"><main class="mx-auto max-w-7xl space-y-6">${rendered}</main></body></html>`);
                    await page.addScriptTag({ content: pdfWorkspace });
                    await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => {
                        Alpine.store('researchAssistant', { drawerOpen: false, workspaceOpen: false });
                        Alpine.data('projectStatusManager', ${projectStatusManager.toString()});
                        ${secretaryPicker}
                        let factory;
                        registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
                        Alpine.data('pdfAnnotationWorkspace', () => {
                            const state = factory();
                            state.init = function () { this.loading = false; };
                            return state;
                        });
                    });` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });

                    const monitoring = page.locator('#project-monitoring-tab');
                    const tab = page.locator('#project-monitoring-tab-button');
                    await monitoring.waitFor({ state: 'visible' });
                    assert.equal(await tab.getAttribute('aria-selected'), 'true');
                    assert.equal(await page.getByRole('tab').first().getAttribute('id'), 'project-monitoring-tab-button');
                    assert.equal(await page.locator('[data-reporting-quarter]').count(), ['history', 'delayed-head'].includes(fixture) ? 4 : 3);
                    assert.equal(await page.locator('#terminal-reports').isVisible(), true);
                    assert.equal(await page.locator('[data-project-details]').count(), 0);
                    assert.equal(await page.locator('[data-monitoring-budget-section]').count(), ['completed-research_head', 'history', 'delayed-head'].includes(fixture) ? 0 : 1);
                    const recordMenu = page.locator('[data-project-record-menu]');
                    assert.equal(await recordMenu.getAttribute('open'), null);
                    assert.equal(await page.getByRole('tab', { name: 'Proposal', exact: true }).isVisible(), false);
                    assert.equal(await page.locator('#terminal-reports ol').count(), 0);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);

                    const rows = page.locator('[data-reporting-quarter]');
                    for (let index = 0; index < await rows.count(); index++) {
                        assert.equal(await rows.nth(index).locator('[data-monitoring-quarter]').count(), 1);
                        assert.equal(await rows.nth(index).locator('[data-progress-quarter]').count(), 1);
                        assert.equal(await rows.nth(index).locator('[data-monitoring-report-status]').isVisible(), true);
                        assert.equal(await rows.nth(index).locator('[data-narrative-report-status]').isVisible(), true);
                        const toolStatus = await rows.nth(index).locator('[data-monitoring-report-status]').boundingBox();
                        const progressStatus = await rows.nth(index).locator('[data-narrative-report-status]').boundingBox();
                        const controlGroups = rows.nth(index).locator('[data-report-controls]');
                        const controls = [controlGroups.nth(0).locator('.quarterly-report-primary'), controlGroups.nth(1).locator('.quarterly-report-primary')];
                        const boxes = [];
                        for (const control of controls) {
                            assert.equal(await control.count(), 1);
                            assert.equal(await control.locator('.report-review-status').isVisible(), true);
                            const box = await control.boundingBox();
                            assert.equal(box.height, 44);
                            assert.ok(box.width >= 120 && box.width <= 144);
                            assert.equal(await control.locator('.report-review-status').evaluate(el => getComputedStyle(el).borderTopWidth), '0px');
                            const status = await control.locator('.report-review-status').boundingBox();
                            assert.ok(status.x >= box.x && status.x + status.width <= box.x + box.width);
                            assert.ok(Math.abs(status.y + status.height / 2 - box.y - box.height / 2) <= 1);
                            boxes.push(box);
                        }
                        assert.equal(boxes[0].width, boxes[1].width);
                        assert.ok(Math.abs(boxes[0].y - boxes[1].y) <= 1);
                        assert.equal(toolStatus.height, progressStatus.height);
                        if (width >= 640) {
                            for (const [column, control] of [[1, boxes[0]], [2, boxes[1]]]) {
                                const heading = await page.locator('thead th .quarterly-report-rail').nth(column - 1).boundingBox();
                                assert.ok(Math.abs(heading.x - control.x) <= 1);
                            }
                        }

                        if (fixture.startsWith('completed')) {
                            const bounds = await rows.nth(index).boundingBox();
                            assert.ok(bounds.height <= (width < 640 ? 230 : 140));
                        }
                    }
                    const heading = await page.locator('[data-project-monitoring-heading] h3').boundingBox();
                    const tabBounds = await tab.boundingBox();
                    const quarterlyHeading = await page.locator('#quarterly-reporting-schedule-heading').boundingBox();
                    assert.ok(Math.abs(heading.x - tabBounds.x) <= 1);
                    assert.ok(Math.abs(heading.x - quarterlyHeading.x) <= 1);
                    if (fixture.startsWith('completed') || fixture === 'history') {
                        const action = rows.first().getByRole('button', { name: /View Q1 monitoring tool/ });
                        const id = await action.getAttribute('aria-controls');
                        assert.equal(await page.locator(`#${id}`).isVisible(), false);
                        await action.focus();
                        await page.keyboard.press('Enter');
                        await page.locator(`#${id}`).waitFor({ state: 'visible' });
                        assert.equal(await action.getAttribute('aria-expanded'), 'true');
                        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                        assert.equal(await page.locator(`[id="${id}"]`).count(), 1);
                        if (process.env.MONITORING_SCREENSHOT_DIRECTORY && fixture === 'completed-research_head' && [390, 1440].includes(width)) {
                            await page.locator(`#${id}`).screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `opened-monitoring-tool-${width}-${dark ? 'dark' : 'light'}.png`) });
                        }
                        await action.click();
                        await page.locator(`#${id}`).waitFor({ state: 'hidden' });
                        await page.evaluate(hash => { location.hash = hash; }, `#${id}`);
                        await page.locator(`#${id}`).waitFor({ state: 'visible' });
                        await action.click();
                        if (fixture.startsWith('completed')) {
                            const progressAction = rows.first().getByRole('button', { name: /View Q1 progress report/ });
                            const progressId = await progressAction.getAttribute('aria-controls');
                            const toolPanel = page.locator(`#${id}`);
                            const progressPanel = page.locator(`#${progressId}`);
                            const checkPanel = async panel => {
                                const inlinePaper = panel.locator('[data-submitted-report-paper]');
                                await inlinePaper.waitFor({ state: 'visible' });
                                const thumbnailBounds = await inlinePaper.boundingBox();
                                assert.ok(thumbnailBounds.width <= 193 && thumbnailBounds.height <= 273);
                                if (width === 768) {
                                    const schedule = page.locator('#progress-reports');
                                    await schedule.evaluate(element => { element.style.maxWidth = '28rem'; });
                                    const summary = await inlinePaper.evaluate(element => element.previousElementSibling.getBoundingClientRect().toJSON());
                                    const constrainedPaper = await inlinePaper.boundingBox();
                                    assert.ok(Math.abs(constrainedPaper.y - summary.y) <= 1);
                                    assert.ok(constrainedPaper.x >= summary.x + summary.width);
                                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                                    await schedule.evaluate(element => { element.style.maxWidth = ''; });
                                }
                                const closeReport = panel.getByRole('button', { name: 'Close report', exact: true });
                                const closeReportBounds = await closeReport.boundingBox();
                                const panelBounds = await panel.boundingBox();
                                assert.equal(closeReportBounds.width, 44);
                                assert.equal(closeReportBounds.height, 44);
                                assert.ok(panelBounds.height <= (width < 640 ? 590 : 330), `Expanded report is too tall: ${panelBounds.height}px`);
                                assert.ok(closeReportBounds.x + closeReportBounds.width <= panelBounds.x + panelBounds.width);
                                const badge = panel.locator('[data-quarter-report-heading] .report-review-status');
                                assert.equal(await badge.locator('svg').count(), 1);
                                assert.equal(await badge.evaluate(element => getComputedStyle(element).borderTopStyle), 'solid');
                                assert.equal(await inlinePaper.getByRole('group', { name: 'Document zoom controls' }).count(), 0);
                                assert.equal(await panel.getByRole('progressbar').getAttribute('aria-valuenow'), '25');
                                assert.equal(await panel.getByRole('heading', { name: 'Results', exact: true }).count(), 0);
                                const preview = panel.getByRole('button', { name: 'Preview submitted PDF', exact: true });
                                const download = panel.getByRole('link', { name: /Download .* PDF/ });
                                const previewBounds = await preview.boundingBox();
                                const downloadBounds = await download.boundingBox();
                                assert.equal(previewBounds.height, downloadBounds.height);
                                assert.ok(Math.abs(previewBounds.y - downloadBounds.y) <= 1);
                                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                                const maximize = inlinePaper.getByRole('button', { name: 'Enlarge the paper preview', exact: true });
                                await maximize.focus();
                                await page.keyboard.press('Enter');
                                const dialog = panel.getByRole('dialog');
                                await dialog.waitFor({ state: 'visible' });
                                assert.match(await dialog.evaluate(element => element.closest('[data-pdf-annotation-config]').dataset.pdfAnnotationConfig), /"canAnnotate":false/);
                                assert.equal(await dialog.locator('.proposal-preview-dock-heading').count(), 1);
                                const zoom = dialog.getByRole('group', { name: 'Document zoom controls' });
                                assert.equal(await zoom.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                                await zoom.getByRole('button', { name: 'Zoom in', exact: true }).click();
                                assert.equal(await zoom.locator('output').innerText(), '110%');
                                await zoom.getByRole('button', { name: 'Zoom out', exact: true }).click();
                                assert.equal(await zoom.locator('output').innerText(), '100%');
                                await zoom.getByRole('button', { name: 'Fit width', exact: true }).click();
                                assert.equal(await zoom.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                                await zoom.getByRole('button', { name: 'Fit page', exact: true }).click();
                                const paperBounds = await dialog.getByLabel(/Q1 .* PDF/).boundingBox();
                                const zoomBounds = await zoom.boundingBox();
                                assert.ok(paperBounds.y + paperBounds.height <= zoomBounds.y);
                                for (const button of await zoom.getByRole('button').all()) {
                                    const bounds = await button.boundingBox();
                                    assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
                                    assert.ok(bounds.y >= 0 && bounds.y + bounds.height <= 1000);
                                }
                                if (process.env.MONITORING_SCREENSHOT_DIRECTORY && fixture === 'completed-research_head' && [390, 1440].includes(width)) {
                                    await dialog.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `pdf-preview-${await panel.getAttribute('id')}-${width}-${dark ? 'dark' : 'light'}.png`) });
                                }
                                const back = page.locator('[data-fixed-back-link]');
                                assert.ok(await dialog.evaluate(element => Number(getComputedStyle(element.closest('[data-quarter-pdf-preview-modal]')).zIndex)) > await back.evaluate(element => Number(getComputedStyle(element).zIndex)));
                                const close = dialog.getByRole('button', { name: 'Close PDF preview', exact: true });
                                const closeBounds = await close.boundingBox();
                                assert.ok(closeBounds.x >= 0 && closeBounds.x + closeBounds.width <= width);
                                await page.keyboard.press('Escape');
                                await dialog.waitFor({ state: 'hidden' });
                                await panel.locator('[data-quarter-pdf-preview-modal]').waitFor({ state: 'hidden' });
                                assert.equal(await panel.isVisible(), true);
                                await page.waitForFunction(element => document.activeElement === element, await maximize.elementHandle());
                                assert.equal(await maximize.evaluate(element => document.activeElement === element), true);
                            };
                            await action.click();
                            await toolPanel.waitFor({ state: 'visible' });
                            await checkPanel(toolPanel);
                            const headerClass = await toolPanel.locator('[data-quarter-report-heading]').getAttribute('class');
                            const actionsClass = await toolPanel.locator('[data-quarter-report-actions]').getAttribute('class');
                            await progressAction.click();
                            await progressPanel.waitFor({ state: 'visible' });
                            await toolPanel.waitFor({ state: 'hidden' });
                            assert.equal(await action.getAttribute('aria-expanded'), 'false');
                            assert.equal(await progressAction.getAttribute('aria-expanded'), 'true');
                            assert.equal(await progressPanel.getAttribute('class'), await toolPanel.getAttribute('class'));
                            assert.equal(await progressPanel.locator('[data-quarter-report-heading]').getAttribute('class'), headerClass);
                            assert.equal(await progressPanel.locator('[data-quarter-report-actions]').getAttribute('class'), actionsClass);
                            assert.equal(await page.locator(`[id="${progressId}"]`).count(), 1);
                            await checkPanel(progressPanel);
                            assert.equal(await progressPanel.getByRole('link', { name: 'Read full Q1 progress report', exact: true }).isVisible(), true);
                            if (process.env.MONITORING_SCREENSHOT_DIRECTORY && fixture === 'completed-research_head' && [390, 1440].includes(width)) {
                                await progressPanel.locator('[data-quarter-report-heading] h4').click();
                                await progressPanel.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `opened-progress-report-${width}-${dark ? 'dark' : 'light'}.png`) });
                            }
                            await progressPanel.getByRole('button', { name: 'Close report', exact: true }).click();
                            await progressPanel.waitFor({ state: 'hidden' });
                            assert.equal(await progressAction.getAttribute('aria-expanded'), 'false');
                            assert.equal(await progressAction.evaluate(element => document.activeElement === element), true);
                            await progressAction.click();
                            await progressPanel.waitFor({ state: 'visible' });
                            await progressPanel.getByRole('button', { name: 'Close report', exact: true }).focus();
                            await page.keyboard.press('Escape');
                            await progressPanel.waitFor({ state: 'hidden' });
                            assert.equal(await progressAction.evaluate(element => document.activeElement === element), true);
                            await page.evaluate(hash => { location.hash = hash; }, `#${progressId}`);
                            await progressPanel.waitFor({ state: 'visible' });
                            await page.evaluate(hash => { location.hash = hash; }, `#${id}`);
                            await toolPanel.waitFor({ state: 'visible' });
                            await progressPanel.waitFor({ state: 'hidden' });
                            await action.click();
                        }
                    } else if (fixture === 'delayed-head') {
                        const manager = page.locator('[data-project-status-manager]');
                        assert.equal(await manager.evaluate(element => getComputedStyle(element).position), 'static');
                        assert.equal(await rows.first().getByRole('progressbar').getAttribute('aria-valuenow'), '100');
                        const trigger = manager.getByRole('button', { name: 'Manage status', exact: true });
                        await trigger.click();
                        const panel = manager.getByRole('region');
                        await panel.waitFor({ state: 'visible' });
                        const bounds = await panel.boundingBox();
                        assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
                        await page.keyboard.press('Escape');
                        await panel.waitFor({ state: 'hidden' });
                        assert.equal(await trigger.evaluate(element => document.activeElement === element), true);
                    } else {
                        assert.match(await page.locator('[data-project-monitoring-details]').innerText(), /Q1 · Open now/);
                        assert.equal(await rows.nth(1).getByRole('link', { name: 'Revise Q2 progress report' }).isVisible(), true);
                        assert.equal(await page.getByRole('link', { name: 'Open terminal report' }).isVisible(), true);
                    }
                    if (fixture === 'history') {
                        const historical = page.locator('#narrative-report-history article[id^="monitoring-tool-"]').first();
                        const id = await historical.getAttribute('id');
                        assert.equal(await historical.isVisible(), false);
                        await page.evaluate(hash => { location.hash = hash; }, `#${id}`);
                        await historical.waitFor({ state: 'visible' });
                        assert.equal(await page.locator(`[id="${id}"]`).count(), 1);
                        await page.locator('#narrative-report-history summary').click();
                    }
                    if (fixture.startsWith('completed')) {
                        const historicalProgress = page.locator('#narrative-report-history [data-narrative-report-summary]').first();
                        assert.equal(await historicalProgress.isVisible(), false);
                        await page.evaluate(hash => { location.hash = hash; }, `#${await historicalProgress.getAttribute('id')}`);
                        await historicalProgress.waitFor({ state: 'visible' });
                        await page.locator('#narrative-report-history summary').click();
                    }

                    for (const summary of await page.locator('.monitoring-disclosure > summary').all()) {
                        assert.equal(await summary.evaluate(element => getComputedStyle(element).listStyleType), 'none');
                        assert.equal(await summary.evaluate(element => getComputedStyle(element, '::marker').content), '""');
                        assert.equal(await summary.locator('svg').count(), 1);
                    }
                    const noticeLink = page.locator('[data-monitoring-notice-link]');
                    assert.equal(await noticeLink.evaluate(element => getComputedStyle(element).borderTopStyle), 'solid');
                    const budget = page.locator('[data-monitoring-budget-section]');
                    if (fixture === 'active') {
                        const alert = page.locator('[data-monitoring-work-plan-alert]');
                        const disclosure = alert.locator('details');
                        assert.equal(await alert.evaluate(element => getComputedStyle(element).backgroundColor), dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                        assert.equal(await disclosure.getAttribute('open'), null);
                        await disclosure.locator('summary').focus();
                        await page.keyboard.press('Enter');
                        assert.equal(await disclosure.getAttribute('open'), '');
                        assert.equal(await alert.locator('[data-overdue-work-plan-index]').first().isVisible(), true);
                        if (process.env.MONITORING_SCREENSHOT_DIRECTORY && [390, 1440].includes(width)) {
                            await alert.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `work-plan-${width}-${dark ? 'dark' : 'light'}.png`) });
                        }
                        await page.keyboard.press('Space');
                        assert.equal(await disclosure.getAttribute('open'), null);
                        const pickerTrigger = budget.getByRole('button', { name: 'Select project staff' });
                        await pickerTrigger.click();
                        const search = budget.getByRole('searchbox');
                        await search.waitFor({ state: 'visible' });
                        const bounds = await search.boundingBox();
                        assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
                        await page.keyboard.press('Escape');
                        await search.waitFor({ state: 'hidden' });
                        assert.equal(await pickerTrigger.evaluate(element => document.activeElement === element), true);
                    }
                    await recordMenu.locator('summary').focus();
                    await page.keyboard.press('Enter');
                    assert.equal(await page.getByRole('tab', { name: 'Proposal', exact: true }).isVisible(), true);
                    await page.keyboard.press('Escape');
                    assert.equal(await recordMenu.getAttribute('open'), null);
                    await recordMenu.locator('summary').click();
                    await page.getByRole('tab', { name: 'Proposal', exact: true }).click();
                    await monitoring.waitFor({ state: 'hidden' });
                    assert.equal(await recordMenu.getAttribute('open'), null);
                    assert.match(await recordMenu.locator('summary').innerText(), /Proposal/);
                    assert.equal(await recordMenu.locator('summary').evaluate(element => document.activeElement === element), true);
                    for (const recordId of ['proposal-review', 'notice-to-proceed', 'version-history']) {
                        const recordTab = page.locator(`#${recordId}-tab-button`);
                        if (await recordTab.count() === 0) continue;
                        await recordMenu.locator('summary').click();
                        await recordTab.click();
                        await page.locator(`#${recordId}-tab`).waitFor({ state: 'visible' });
                        assert.equal(await recordMenu.getAttribute('open'), null);
                        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    }
                    await page.evaluate(() => { location.hash = '#terminal-reports'; });
                    await monitoring.waitFor({ state: 'visible' });
                    assert.equal(await tab.getAttribute('aria-selected'), 'true');
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    assert.deepEqual(errors, []);

                    if (process.env.MONITORING_SCREENSHOT_DIRECTORY && [390, 1440].includes(width)) {
                        await page.evaluate(() => scrollTo(0, 0));
                        await page.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `monitoring-${fixture}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                        await page.locator('#progress-reports').screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `quarterly-${fixture}-${width}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});

test('submitted report previews render real PDFs and resize their pages with the proposal controls', async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ProjectMonitoringTest.php', '--filter=monitoring prioritizes|report reader shows'], {
        encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_MONITORING_LAYOUT: '1' },
    });
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8') + '\n' + readFileSync(resolve('resources/css/proposal-paper-workspace.css'), 'utf8'), { from: undefined });
    const pdfWorkspace = readFileSync(resolve('resources/js/pdf-annotation-workspace.js'), 'utf8')
        .replaceAll('export default ', '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const [kind, selector, fixture, sample] of [
            ['monitoring', '[data-quarter-pdf-preview-modal][data-report-kind="monitoring"]', 'monitoring-completed-research_head', 'monitoring-19.pdf'],
            ['progress', '[data-quarter-pdf-preview-modal][data-report-kind="progress"]', 'monitoring-completed-research_head', 'progress-q1.pdf'],
            ['terminal', '[data-narrative-pdf-preview-modal]', 'report-reader-terminal-completed', 'terminal-6.pdf'],
            ['inline-monitoring', '#progress-reports', 'monitoring-completed-research_head', 'monitoring-19.pdf'],
            ['inline-progress', '#progress-reports', 'monitoring-completed-research_head', 'progress-q1.pdf'],
        ]) {
            const inline = kind.startsWith('inline-');
            const source = readFileSync(resolve(`storage/framework/testing/${fixture}.html`), 'utf8');
            const pdf = readFileSync(process.env.MONITORING_SAMPLE_PDF_DIRECTORY ? resolve(process.env.MONITORING_SAMPLE_PDF_DIRECTORY, sample) : resolve('public/images/index.pdf'));
            for (const [width, height] of [[390, 900], [1440, 900], [844, 390]]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    const rendered = await page.evaluate(({ source, selector }) => {
                        const document = new DOMParser().parseFromString(source, 'text/html');
                        const modal = document.querySelector(selector);
                        if (selector === '#progress-reports') return modal.outerHTML;
                        const name = modal.getAttribute('x-on:open-modal.window').match(/== '([^']+)'/)[1];
                        return `<div x-data><button type="button" @click="$dispatch('open-modal', '${name}')">Preview PDF</button>${modal.outerHTML}</div>`;
                    }, { source, selector });
                    await page.route('**/*', route => {
                        const path = new URL(route.request().url()).pathname;
                        if (path === '/pdf.mjs' || path === '/pdf.worker.mjs') {
                            return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('node_modules/pdfjs-dist/build', path.slice(1))) });
                        }
                        if (path === '/workspace') {
                            return route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><style>${styles.css}\n[x-cloak]{display:none!important}</style><body class="${inline ? 'bg-slate-100 p-4 dark:bg-slate-950' : ''}">${rendered.replaceAll('http://localhost', 'http://report-preview.test')}</body></html>` });
                        }
                        return route.fulfill({ contentType: 'application/pdf', body: pdf });
                    });
                    await page.goto('http://report-preview.test/workspace');
                    await page.addScriptTag({ type: 'module', content: `import * as pdfJs from 'http://report-preview.test/pdf.mjs'; pdfJs.GlobalWorkerOptions.workerSrc = 'http://report-preview.test/pdf.worker.mjs'; window.reportPdfJs = pdfJs;` });
                    await page.waitForFunction(() => Boolean(window.reportPdfJs));
                    await page.addScriptTag({ content: `${pdfWorkspace}\nloadPdfJs = () => Promise.resolve(window.reportPdfJs); document.addEventListener('alpine:init', () => registerPdfAnnotationWorkspace(Alpine));` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                    const trigger = inline ? page.getByRole('button', { name: kind === 'inline-monitoring' ? /View Q1 monitoring tool/ : /View Q1 progress report/ }) : page.getByRole('button', { name: 'Preview PDF', exact: true });
                    if (inline) assert.equal(await page.locator('.pdf-annotation-page').count(), 0);
                    await trigger.click();
                    const panel = inline ? page.locator(`#${await trigger.getAttribute('aria-controls')}`) : null;
                    let dialog = inline ? panel : page.getByRole('dialog');
                    await dialog.waitFor({ state: 'visible' });
                    if (inline) {
                        const quarter = page.locator('[data-reporting-quarter="1"]');
                        if (width < 640) {
                            const period = await quarter.locator('[data-reporting-period]').boundingBox();
                            assert.ok(period.width >= width - 75);
                            assert.ok((await quarter.boundingBox()).height <= 230);
                        }
                        await dialog.locator('[data-submitted-report-paper]').scrollIntoViewIfNeeded();
                    }
                    let documentSelector = inline ? `[data-${kind === 'inline-monitoring' ? 'monitoring' : 'progress'}-report-detail] [data-submitted-report-paper]` : '[data-submitted-report-preview]';
                    await page.waitForFunction(selector => !Alpine.$data(document.querySelector(selector)).loading, documentSelector);
                    assert.equal(await page.evaluate(selector => Alpine.$data(document.querySelector(selector)).loadError, documentSelector), '');
                    const count = await page.evaluate(async selector => {
                        const configuration = JSON.parse(document.querySelector(selector).dataset.pdfAnnotationConfig);
                        const loadingTask = window.reportPdfJs.getDocument({ url: configuration.pdfUrl });
                        const pdfDocument = await loadingTask.promise;
                        const count = pdfDocument.numPages;
                        await loadingTask.destroy();
                        return count;
                    }, documentSelector);
                    const waitForPages = (expectedCount = count) => page.waitForFunction(({ selector, count }) => document.querySelector(selector).querySelectorAll('.pdf-annotation-page').length === count, { selector: documentSelector, count: expectedCount });
                    let maximize;
                    if (inline) {
                        await waitForPages(1);
                        const paper = dialog.locator('[data-submitted-report-paper]');
                        const bounds = await paper.boundingBox();
                        assert.ok(bounds.width <= 193 && bounds.height <= 273);
                        assert.equal(await paper.locator('canvas').count(), 1);
                        const previewPage = await paper.locator('.pdf-annotation-page').boundingBox();
                        const paperFrame = await paper.locator('.proposal-preview-paper').boundingBox();
                        assert.ok(paperFrame.height - previewPage.height <= 32);
                        assert.ok((await paper.locator('.textLayer').allTextContents()).join(' ').length > 50);
                        assert.equal(await paper.getByRole('group', { name: 'Document zoom controls' }).count(), 0);
                        assert.equal(await dialog.getByRole('progressbar').getAttribute('aria-valuenow'), '25');
                        if (process.env.MONITORING_SCREENSHOT_DIRECTORY) {
                            await page.locator('#progress-reports').screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `rendered-${kind}-${width}-${height}-${dark ? 'dark' : 'light'}.png`) });
                        }
                        maximize = paper.getByRole('button', { name: 'Enlarge the paper preview', exact: true });
                        await maximize.click();
                        dialog = panel.getByRole('dialog');
                        await dialog.waitFor({ state: 'visible' });
                        documentSelector = `#${await panel.getAttribute('id')} [data-submitted-report-preview]`;
                        await page.waitForFunction(selector => !Alpine.$data(document.querySelector(selector)).loading, documentSelector);
                        assert.equal(await page.evaluate(selector => Alpine.$data(document.querySelector(selector)).loadError, documentSelector), '');
                        assert.equal(await page.evaluate(selector => JSON.parse(document.querySelector(selector).dataset.pdfAnnotationConfig).firstPageOnly, documentSelector), false);
                    }
                    await waitForPages();
                    const pages = dialog.locator('.pdf-annotation-page');
                    assert.equal(await pages.count(), count);
                    const zoom = dialog.getByRole('group', { name: 'Document zoom controls' });
                    const initial = await pages.first().boundingBox();
                    const viewer = await dialog.locator('.pdf-annotation-viewer').boundingBox();
                    assert.ok(initial.width <= viewer.width && initial.width >= viewer.width - 70, 'The enlarged paper must open at readable page width, even in a short window');
                    assert.equal(await zoom.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                    if (process.env.MONITORING_SCREENSHOT_DIRECTORY) {
                        await dialog.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `default-${kind}-${width}-${height}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    const scrollViewport = dialog.locator('.pdf-annotation-viewer');
                    await scrollViewport.evaluate(element => { element.scrollTop = 0; });
                    const scrollBounds = await scrollViewport.evaluate(element => ({ viewportHeight: element.clientHeight, contentHeight: element.scrollHeight, paperHeight: element.parentElement.clientHeight }));
                    assert.ok(scrollBounds.viewportHeight <= scrollBounds.paperHeight, JSON.stringify(scrollBounds));
                    if (scrollBounds.contentHeight > scrollBounds.viewportHeight) {
                        await scrollViewport.hover();
                        await page.mouse.wheel(0, 240);
                        await page.waitForFunction(selector => document.querySelector(selector).querySelector('.pdf-annotation-viewer').scrollTop > 0, documentSelector, { timeout: 3000 });
                        await scrollViewport.evaluate(element => { element.scrollTop = 0; });
                    }
                    await zoom.getByRole('button', { name: 'Zoom in', exact: true }).click();
                    await page.waitForFunction(({ selector, width }) => document.querySelector(selector).querySelector('.pdf-annotation-page')?.getBoundingClientRect().width > width + 1, { selector: documentSelector, width: initial.width });
                    await waitForPages();
                    assert.equal(await zoom.locator('output').innerText(), '110%');
                    const buttonZoomWidth = (await pages.first().boundingBox()).width;
                    await scrollViewport.hover();
                    await page.keyboard.down('Control');
                    try {
                        await page.mouse.wheel(0, -120);
                        await page.waitForFunction(selector => Alpine.$data(document.querySelector(selector)).previewZoom === 120, documentSelector, { timeout: 3000 });
                        await page.waitForFunction(({ selector, width }) => document.querySelector(selector).querySelector('.pdf-annotation-page')?.getBoundingClientRect().width > width + 1, { selector: documentSelector, width: buttonZoomWidth });
                        await waitForPages();
                        assert.equal(await zoom.locator('output').innerText(), '120%');
                        await page.mouse.wheel(0, 120);
                        await page.waitForFunction(selector => Alpine.$data(document.querySelector(selector)).previewZoom === 110, documentSelector, { timeout: 3000 });
                        await page.waitForFunction(({ selector, width }) => Math.abs(document.querySelector(selector).querySelector('.pdf-annotation-page')?.getBoundingClientRect().width - width) < 2, { selector: documentSelector, width: buttonZoomWidth });
                        await waitForPages();
                    } finally {
                        await page.keyboard.up('Control');
                    }
                    await zoom.getByRole('button', { name: 'Fit width', exact: true }).click();
                    await page.waitForFunction(selector => {
                        const paper = document.querySelector(selector);
                        return paper.querySelector('.pdf-annotation-page')?.getBoundingClientRect().width >= paper.querySelector('.pdf-annotation-viewer').clientWidth - 70;
                    }, documentSelector);
                    await waitForPages();
                    assert.equal(await zoom.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                    if (width === 390 && !dark) {
                        await page.evaluate(selector => Alpine.$data(document.querySelector(selector)).setPaperZoom(390), documentSelector);
                        assert.equal(await zoom.getByRole('button', { name: 'Zoom in', exact: true }).isEnabled(), true);
                        await zoom.getByRole('button', { name: 'Zoom in', exact: true }).click();
                        await page.waitForFunction(({ selector, width }) => Math.abs(document.querySelector(selector).querySelector('.pdf-annotation-page')?.getBoundingClientRect().width - width * 4) < 2, { selector: documentSelector, width: initial.width });
                        await waitForPages();
                        assert.equal(await zoom.locator('output').innerText(), '400%');
                        assert.equal(await zoom.getByRole('button', { name: 'Zoom in', exact: true }).isDisabled(), true);
                        const reachable = await scrollViewport.evaluate(element => {
                            element.scrollLeft = element.scrollWidth;
                            element.scrollTop = element.scrollHeight;
                            const viewport = element.getBoundingClientRect();
                            const last = element.querySelector('.pdf-annotation-page:last-child').getBoundingClientRect();
                            return element.scrollLeft > 0 && Math.abs(last.right - viewport.right) <= 50 && Math.abs(last.bottom - viewport.bottom) <= 50;
                        });
                        assert.equal(reachable, true, 'At maximum zoom the right edge and last page must remain reachable');
                    }
                    await zoom.getByRole('button', { name: 'Fit page', exact: true }).click();
                    const fitPageWidth = Math.min(initial.width, initial.width * (viewer.height - 32) / initial.height);
                    await page.waitForFunction(({ selector, width }) => Math.abs(document.querySelector(selector).querySelector('.pdf-annotation-page')?.getBoundingClientRect().width - width) < 2, { selector: documentSelector, width: fitPageWidth });
                    await waitForPages();
                    assert.equal(await pages.count(), count);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    if (process.env.MONITORING_SCREENSHOT_DIRECTORY) {
                        await zoom.getByRole('button', { name: 'Fit page', exact: true }).blur();
                        await dialog.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `rendered-${kind}${inline ? '-maximized' : ''}-${width}-${height}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    await dialog.getByRole('button', { name: 'Close PDF preview', exact: true }).click();
                    if (inline) {
                        await panel.locator('[data-submitted-report-preview-modal]').waitFor({ state: 'hidden' });
                        await page.waitForFunction(element => element === document.activeElement, await maximize.elementHandle());
                        await trigger.click();
                        await panel.waitFor({ state: 'hidden' });
                    } else {
                        await page.locator('[data-submitted-report-preview-modal]').waitFor({ state: 'hidden' });
                    }
                    assert.equal(await trigger.evaluate(element => element === document.activeElement), true);
                    assert.deepEqual(errors, []);
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});

test('opened report readers keep a readable document, aligned actions and accessible figure and PDF dialogs', async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ProjectMonitoringTest.php', '--filter=report reader shows'], {
        encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_MONITORING_LAYOUT: '1' },
    });
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8') + '\n' + readFileSync(resolve('resources/css/proposal-paper-workspace.css'), 'utf8'), { from: undefined });
    const pdfWorkspace = readFileSync(resolve('resources/js/pdf-annotation-workspace.js'), 'utf8')
        .replaceAll('export default ', '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const fixture of ['progress-review', 'progress-completed', 'terminal-review', 'terminal-completed']) {
            const html = readFileSync(resolve(`storage/framework/testing/report-reader-${fixture}.html`), 'utf8');
            for (const [width, height] of [[320, 1000], [390, 1000], [768, 1000], [1440, 1000], [844, 390]]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.route('**/*', route => {
                        const match = route.request().url().match(/\/photos\/(\d+)\/preview/);
                        return match ? route.fulfill({ contentType: 'image/png', body: readFileSync(resolve(`public/images/progress-report-demo/figure-${Number(match[1]) + 1}.png`)) }) : route.abort();
                    });
                    const rendered = await page.evaluate(source => {
                        const document = new DOMParser().parseFromString(source, 'text/html');
                        return {
                            reader: document.querySelector('.report-page-header').outerHTML + document.querySelector('[data-report-reader-page]').outerHTML,
                            launcher: document.querySelector('#research-assistant-launcher').outerHTML,
                        };
                    }, html);
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n[x-cloak]{display:none!important}</style><body data-app-shell x-data class="bg-slate-50 dark:bg-slate-950"><aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 w-[76px] border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 sm:w-[280px]"></aside><div data-app-content-shell class="pl-[76px] sm:pl-[280px]"><main class="athena-page-content mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">${rendered.reader}</main></div>${rendered.launcher}</body></html>`);
                    await page.addScriptTag({ content: pdfWorkspace });
                    await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => {
                        Alpine.store('researchAssistant', {
                            drawerOpen: false, workspaceOpen: false,
                            toggleDrawer() { this.drawerOpen = !this.drawerOpen; }
                        });
                        let factory;
                        registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
                        Alpine.data('pdfAnnotationWorkspace', () => {
                            const state = factory();
                            state.init = function () { this.loading = false; };
                            return state;
                        });
                    });` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                    const reader = page.locator('[data-narrative-report-card]');
                    const back = page.getByRole('link', { name: 'Back to monitoring', exact: true });
                    const launcher = page.locator('#research-assistant-launcher');
                    const assertBackPlacement = async () => {
                        const backBounds = await back.boundingBox();
                        const launcherBounds = await launcher.boundingBox();
                        const sidebarBounds = await page.locator('#app-sidebar').boundingBox();
                        assert.equal(await back.evaluate(element => getComputedStyle(element).position), 'fixed');
                        assert.ok(backBounds.x >= sidebarBounds.width + 12);
                        assert.ok(backBounds.x + backBounds.width + 12 <= launcherBounds.x);
                        assert.ok(Math.abs(backBounds.y + backBounds.height - launcherBounds.y - launcherBounds.height) <= 1);
                        assert.ok(height - backBounds.y - backBounds.height >= 16);
                    };
                    await back.waitFor({ state: 'visible' });
                    assert.equal(await page.getByRole('link', { name: 'Back to monitoring', exact: true }).count(), 1);
                    await assertBackPlacement();
                    await launcher.hover();
                    const tip = await launcher.locator(':scope > span').boundingBox();
                    const backBounds = await back.boundingBox();
                    assert.ok(tip.x >= backBounds.x + backBounds.width + 8);
                    await launcher.click();
                    await back.waitFor({ state: 'hidden' });
                    await launcher.click();
                    await back.waitFor({ state: 'visible' });
                    await page.evaluate(() => { Alpine.store('researchAssistant').workspaceOpen = true; });
                    await back.waitFor({ state: 'hidden' });
                    await page.evaluate(() => { Alpine.store('researchAssistant').workspaceOpen = false; });
                    await back.waitFor({ state: 'visible' });
                    if (width >= 640) {
                        await page.evaluate(() => {
                            document.documentElement.dataset.sidebarCollapsed = 'true';
                            document.querySelector('#app-sidebar').style.width = '76px';
                            document.querySelector('[data-app-content-shell]').style.paddingLeft = '76px';
                        });
                        await assertBackPlacement();
                        await page.evaluate(() => {
                            document.documentElement.dataset.sidebarCollapsed = 'false';
                            document.querySelector('#app-sidebar').style.removeProperty('width');
                            document.querySelector('[data-app-content-shell]').style.removeProperty('padding-left');
                        });
                    }
                    assert.equal(await reader.locator('figure button').count(), 7);
                    assert.equal(await page.locator('[data-narrative-report-review]').count(), fixture.endsWith('review') ? 1 : 0);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    const actions = page.locator('[data-report-reader-actions]');
                    const preview = actions.getByRole('button', { name: 'Preview PDF', exact: true });
                    const download = actions.getByRole('link', { name: /Download .* PDF/ });
                    const previewBounds = await preview.boundingBox();
                    const downloadBounds = await download.boundingBox();
                    assert.ok(Math.abs(previewBounds.y + previewBounds.height / 2 - downloadBounds.y - downloadBounds.height / 2) <= 1);
                    const navigation = page.locator('[data-report-reader-navigation]');
                    const content = page.locator('[data-narrative-report-content]');
                    const navBounds = await navigation.boundingBox();
                    const contentBounds = await content.boundingBox();
                    assert.equal(width >= 1024 ? navBounds.x >= contentBounds.x + contentBounds.width : navBounds.y + navBounds.height <= contentBounds.y, true);
                    await navigation.getByRole('link', { name: 'Methodology', exact: true }).click();
                    assert.match(await page.evaluate(() => location.hash), /-methodology$/);
                    const figure = reader.getByRole('button', { name: 'Preview figure 1', exact: true });
                    await figure.focus();
                    await page.keyboard.press('Enter');
                    const figureDialog = page.getByRole('dialog', { name: 'Figure 1', exact: true });
                    await figureDialog.waitFor({ state: 'visible' });
                    assert.equal(await back.evaluate(element => {
                        const bounds = element.getBoundingClientRect();
                        return document.elementFromPoint(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2)?.closest('[data-fixed-back-link]') === element;
                    }), false);
                    await figureDialog.getByRole('button', { name: 'Close figure preview' }).focus();
                    await page.keyboard.press('Escape');
                    await figureDialog.waitFor({ state: 'hidden' });
                    await page.waitForFunction(() => document.activeElement?.getAttribute('aria-label') === 'Preview figure 1');
                    await preview.click();
                    const pdfDialog = page.getByRole('dialog', { name: /report preview$/ });
                    await pdfDialog.waitFor({ state: 'visible' });
                    assert.equal(await back.evaluate(element => {
                        const bounds = element.getBoundingClientRect();
                        return document.elementFromPoint(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2)?.closest('[data-fixed-back-link]') === element;
                    }), false);
                    assert.equal(await pdfDialog.getByLabel('Submitted report PDF').isVisible(), true);
                    const zoom = pdfDialog.getByRole('group', { name: 'Document zoom controls' });
                    assert.equal(await zoom.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                    await zoom.getByRole('button', { name: 'Zoom in', exact: true }).click();
                    assert.equal(await zoom.locator('output').innerText(), '110%');
                    await zoom.getByRole('button', { name: 'Fit width', exact: true }).click();
                    assert.equal(await zoom.locator('output').innerText(), '100%');
                    assert.equal(await zoom.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                    const paperBounds = await pdfDialog.getByLabel('Submitted report PDF').boundingBox();
                    const zoomBounds = await zoom.boundingBox();
                    assert.ok(paperBounds.height >= 100);
                    assert.ok(paperBounds.y + paperBounds.height <= zoomBounds.y);
                    for (const button of await zoom.getByRole('button').all()) {
                        const bounds = await button.boundingBox();
                        assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
                        assert.ok(bounds.y >= 0 && bounds.y + bounds.height <= height);
                    }
                    await pdfDialog.getByRole('button', { name: 'Close PDF preview' }).click();
                    await pdfDialog.waitFor({ state: 'hidden' });
                    await page.locator('[data-narrative-pdf-preview-modal]').waitFor({ state: 'hidden' });
                    await page.waitForFunction(() => document.activeElement?.textContent.includes('Preview PDF'));
                    await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
                    await assertBackPlacement();
                    const contentEnd = await content.boundingBox();
                    const finalBackBounds = await back.boundingBox();
                    assert.ok(contentEnd.y + contentEnd.height + 4 <= finalBackBounds.y);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    assert.deepEqual(errors, []);
                    if (process.env.MONITORING_SCREENSHOT_DIRECTORY && fixture === 'progress-completed' && [390, 1440].includes(width)) {
                        await page.evaluate(() => { scrollTo(0, 0); document.querySelector('[data-report-reader-navigation]').scrollLeft = 0; });
                        await page.screenshot({ path: resolve(process.env.MONITORING_SCREENSHOT_DIRECTORY, `report-reader-${width}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});
