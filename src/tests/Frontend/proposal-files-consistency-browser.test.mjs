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
const script = path => read(path).replace(/^import .*;\r?\n/gm, '').replaceAll('export ', '');

test('proposal lists and required papers share readable file icons while retaining review and editing actions', async () => {
    if (process.env.ATHENA_CONSISTENCY_FIXTURE_READY !== '1') {
        for (const [file, filter] of [
            ['FacultyWorkspaceNavigationTest.php', 'faculty workspace separates submissions'],
            ['ResearchHeadProposalSubmissionTest.php', 'research heads can view every initial'],
            ['ProposalDraftWorkflowTest.php', 'draft attachment previews use stored prepared PDFs'],
        ]) {
            execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', `tests/Feature/${file}`, `--filter=${filter}`], {
                encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
                env: { ...process.env, ATHENA_EXPORT_PROPOSAL_CONSISTENCY: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
            });
        }
    }
    const fixtures = {};
    for (const name of ['submissions', 'review', 'drafts', 'draft']) {
        const path = resolve(`storage/framework/testing/proposal-consistency-${name}.html`);
        assert.ok(existsSync(path), `Export the ${name} proposal fixture before running this browser test.`);
        fixtures[name] = readFileSync(path, 'utf8');
    }
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css') + '\n' + read('resources/css/project-workspace.css'), { from: undefined });
    const previewRuntime = ['proposal-preview-workspace', 'proposal-paper-workspace', 'proposal-assessment-preview']
        .map(module => script(`resources/js/${module}.js`)).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                const previewRequests = [];
                let mutations = 0;
                let pageHtml;
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                await page.route('**/*', route => {
                    const request = route.request();
                    if (request.method() !== 'GET') {
                        mutations++;
                        return route.abort();
                    }
                    if (request.url().endsWith('/proposal-consistency-test')) {
                        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}\n[x-cloak]{display:none!important}\n@media(min-width:640px){main{max-width:calc(100vw - 320px)!important}}</style></head><body class="font-sans bg-slate-50 dark:bg-slate-950"><main class="mx-auto max-w-7xl px-4 py-6"><div class="athena-page-content w-full">${pageHtml}</div></main></body></html>` });
                    }
                    if (request.resourceType() === 'fetch') {
                        previewRequests.push(request.url());
                        return route.fulfill({ contentType: 'text/html', body: '<html><body><article class="paper-preview-fixture" style="width:612px;min-height:792px">Prepared proposal paper</article></body></html>' });
                    }
                    return route.abort();
                });
                const initialize = async (name, selector, reduceDraft = false) => {
                    pageHtml = await page.evaluate(({ source, selector, reduceDraft }) => {
                        const document = new DOMParser().parseFromString(source, 'text/html');
                        const root = document.querySelector(selector);
                        if (!reduceDraft) return root.outerHTML;
                        const reduced = root.cloneNode(false);
                        reduced.append(root.querySelector('[role="tablist"]').cloneNode(true));
                        for (const panel of root.querySelectorAll(':scope > section[role="tabpanel"]')) {
                            if (panel.id === 'required-pdf-attachments-tab') reduced.append(panel.cloneNode(true));
                            else {
                                const emptyPanel = panel.cloneNode(false);
                                const heading = panel.querySelector('h2,h3');
                                if (heading) emptyPanel.append(heading.cloneNode(true));
                                reduced.append(emptyPanel);
                            }
                        }
                        return reduced.outerHTML;
                    }, { source: fixtures[name], selector, reduceDraft });
                    await page.goto('http://localhost/proposal-consistency-test');
                    await page.addScriptTag({ content: `${previewRuntime}\ndocument.addEventListener('alpine:init', () => Alpine.data('proposalAssessmentPreview', proposalAssessmentPreview)); window.proposalReviewEvents = 0; window.addEventListener('open-modal', event => { if (event.detail === 'proposal-review') window.proposalReviewEvents++; });` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                };
                const readable = async (locator, minimum = 14) => {
                    const font = await locator.evaluate(element => ({ size: parseFloat(getComputedStyle(element).fontSize), family: getComputedStyle(element).fontFamily }));
                    assert.ok(font.size >= minimum, `${await locator.textContent()} should be at least ${minimum}px, got ${font.size}px`);
                    assert.match(font.family, /Plus Jakarta Sans/);
                };
                const sharedIcon = async row => {
                    const icon = row.locator('[data-document-file-icon]');
                    await expect(icon).toHaveCount(1);
                    await expect(icon).toHaveAttribute('aria-hidden', 'true');
                    await expect(icon).toBeVisible();
                    assert.equal(await icon.locator('svg').getAttribute('viewBox'), '0 0 56 64');
                    assert.match(await icon.locator('path').first().getAttribute('d'), /h27l13 13/);
                };
                const screenshot = async name => {
                    await page.locator('main').evaluate(async element => {
                        await new Promise(requestAnimationFrame);
                        await Promise.all(element.getAnimations({ subtree: true }).map(animation => animation.finished.catch(() => {})));
                    });
                    assert.deepEqual(errors, []);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `overflow in ${name} at ${width}px`);
                    if (process.env.PROPOSAL_CONSISTENCY_SCREENSHOT_DIRECTORY) {
                        mkdirSync(process.env.PROPOSAL_CONSISTENCY_SCREENSHOT_DIRECTORY, { recursive: true });
                        await page.screenshot({ path: resolve(process.env.PROPOSAL_CONSISTENCY_SCREENSHOT_DIRECTORY, `proposal-${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: false });
                    }
                };

                await initialize('submissions', '.athena-page-content > div');
                const submissions = page.locator('[data-submitted-proposal]');
                await expect(submissions).toHaveCount(12);
                const submission = submissions.first();
                await sharedIcon(submission);
                await readable(submission.locator('h3'), 16);
                await readable(submission.locator('h3 + span'));
                await readable(submission.locator('p').last());
                await readable(submission.getByRole('link', { name: 'View proposal', exact: true }), 16);
                const revise = page.locator('[data-revision-action-required]');
                await expect(revise).toBeVisible();
                await readable(revise, 16);
                assert.match(await revise.getAttribute('href'), /\/revision/);
                await expect(page.getByRole('navigation', { name: 'Proposal categories' })).toBeVisible();
                await screenshot('submissions');

                await initialize('review', '[data-proposal-submissions]');
                await expect(page.locator('[data-proposal-queue-layout]')).toHaveAttribute('data-proposal-queue-layout', 'rows');
                const queueRow = page.locator('[data-proposal-id]').first();
                await sharedIcon(queueRow);
                await readable(queueRow.locator('h4'), 16);
                await readable(queueRow.locator('[data-proposal-status-label]'));
                await readable(queueRow.locator('time'));
                const reviewLink = queueRow.getByRole('link', { name: /^Review:/ });
                await expect(reviewLink).toBeVisible();
                await readable(reviewLink, 16);
                assert.match(await reviewLink.getAttribute('href'), /\/topics\/\d+/);
                const workflow = page.locator('[data-submission-workflow-toggle]');
                await workflow.click();
                await expect(workflow).toHaveAttribute('aria-expanded', 'true');
                await workflow.click();
                await expect(workflow).toHaveAttribute('aria-expanded', 'false');
                const history = page.locator('[data-submission-history]');
                await history.locator(':scope > summary').click();
                await expect(page.locator('[data-submission-history-layout]')).toBeVisible();
                await expect(page.locator('[data-submission-id]')).toHaveCount(2);
                await sharedIcon(page.locator('[data-submission-id]').first());
                await expect(page.getByRole('link', { name: /^View history for/ }).first()).toBeVisible();
                await screenshot('review');

                await initialize('drafts', '.athena-page-content > div');
                const draft = page.locator('[data-proposal-draft]').first();
                await sharedIcon(draft);
                await readable(draft.locator('h3'), 16);
                await readable(draft.locator('[data-draft-readiness]'));
                const resume = draft.getByRole('link', { name: 'Resume', exact: true });
                await expect(resume).toBeVisible();
                await readable(resume, 16);
                assert.match(await resume.getAttribute('href'), /\/proposal-drafts\/\d+/);
                await expect(draft.getByRole('button', { name: 'Delete', exact: true })).toBeVisible();
                await screenshot('drafts');

                await initialize('draft', '[data-workspace-palette]', true);
                const attachmentTab = page.locator('#required-pdf-attachments-tab-button');
                await readable(attachmentTab, 16);
                await attachmentTab.click();
                await expect(attachmentTab).toHaveAttribute('aria-selected', 'true');
                const requiredPapers = page.locator('[data-editable-proposal-papers]');
                const papers = requiredPapers.locator('article[data-proposal-paper]');
                await expect(papers).toHaveCount(5);
                const automatic = page.locator('[data-automatic-assessment-forms]');
                await expect(automatic.locator('[data-document-file-icon]')).toHaveCount(2);
                for (let index = 0; index < 5; index++) {
                    const paper = papers.nth(index);
                    await sharedIcon(paper);
                    await expect(paper.locator('[data-document-file-icon]').getByText('PDF', { exact: true })).toBeVisible();
                    await readable(paper.locator('h4'), 16);
                    await readable(paper.locator('h4 + span'));
                    await expect(paper.locator('a[aria-label]').last()).toBeVisible();
                }
                const previews = requiredPapers.locator('[data-proposal-paper-preview-trigger]');
                await expect(previews).toHaveCount(5);
                await screenshot('papers');

                const openPreview = async (trigger, panelId) => {
                    const requestsBefore = previewRequests.length;
                    await trigger.click();
                    const panel = page.locator(`#${panelId}`);
                    await expect(panel).toHaveAttribute('role', 'dialog');
                    await expect(panel).toBeVisible();
                    for (const surface of [panel, panel.locator('header')]) {
                        assert.notEqual(await surface.evaluate(element => getComputedStyle(element).backgroundColor), 'rgba(0, 0, 0, 0)', 'The preview panel and toolbar need opaque themed surfaces.');
                    }
                    await expect(panel.locator('h3')).toHaveText((await trigger.getAttribute('aria-label')).replace(/^Preview /, ''));
                    await readable(panel.locator('h3'), 16);
                    await readable(panel.locator('header p'));
                    for (const control of await panel.locator('button:visible, a:visible').all()) {
                        await readable(control, 16);
                        assert.ok((await control.boundingBox()).height >= 44, 'Preview controls need a 44px touch target.');
                    }
                    await readable(panel.locator('output'));
                    await expect(panel.locator('iframe').contentFrame().locator('.paper-preview-fixture')).toBeVisible();
                    assert.equal(previewRequests.length, requestsBefore + 1);
                    assert.equal(await page.locator('body').evaluate(body => body.style.overflow), 'hidden');
                    const close = panel.getByRole('button', { name: 'Close preview', exact: true });
                    await expect(close).toBeFocused();
                    if (panelId === 'draft-paper-preview-panel' && requestsBefore === 0) await screenshot('paper-preview');
                    await close.press('Shift+Tab');
                    await expect(panel.getByRole('button', { name: 'Fit width', exact: true })).toBeFocused();
                    await page.keyboard.press('Escape');
                    await expect(panel).toBeHidden();
                    await expect(trigger).toBeFocused();
                    assert.equal(await page.locator('body').evaluate(body => body.style.overflow), '');
                    return previewRequests.at(-1);
                };
                for (let index = 0; index < 5; index++) {
                    const trigger = previews.nth(index);
                    await readable(trigger, 16);
                    assert.match(await openPreview(trigger, 'draft-paper-preview-panel'), /\/submission-files\/(detailed-proposal|work-plan|expense-breakdown|line-item-budget|curriculum-vitae)$/);
                }
                const assessmentPreviews = automatic.locator('[data-assessment-preview-trigger]');
                await expect(assessmentPreviews).toHaveCount(2);
                for (let index = 0; index < 2; index++) {
                    assert.match(await openPreview(assessmentPreviews.nth(index), 'assessment-form-preview-panel'), /\/(gad-checklist|initial-screening-form)\/preview$/);
                }
                await page.getByRole('button', { name: 'Review & turn in', exact: true }).click();
                assert.equal(await page.evaluate(() => window.proposalReviewEvents), 1);
                await page.locator('#proposal-collaborators-tab-button').click();
                await expect(page.locator('#proposal-collaborators-tab')).toBeVisible();
                await attachmentTab.click();
                await expect(papers).toHaveCount(5);
                assert.equal(mutations, 0);
                await screenshot('papers-after-preview');
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
