import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
const methodSource = name => {
    const match = app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing assistant method: ${name}`);
    return app.slice(match.index, match.index + match[0].length);
};
const uiMethods = ['renderMessage', 'selectedContext', 'hasPaperContext', 'paperContextLabel', 'hasConversation',
    'hasContextOptions', 'resizeComposer', 'handleComposerKeydown', 'closeDocumentPicker', 'sourceUrl',
    'contextPayload', 'replyContext', 'clearConversation', 'selectProjectContext', 'stop',
    'openWorkspace', 'collapseWorkspace', 'closeWorkspace', 'closeDrawer']
    .map(methodSource).join('\n');
const helpers = readFileSync(resolve('resources/js/research-assistant-review.js'), 'utf8').replace(/^export /gm, '');
const markdown = readFileSync(resolve('resources/js/research-assistant-markdown.js'), 'utf8').replace(/^export /gm, '');
const citations = readFileSync(resolve('resources/js/research-assistant-citations.js'), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '');
const projectTitle = 'Water quality research for rural coastal communities';

function textContrast(element) {
    const channels = color => color.match(/[\d.]+/g).map(Number);
    const luminance = color => channels(color).slice(0, 3).map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    }).reduce((value, channel, index) => value + channel * [0.2126, 0.7152, 0.0722][index], 0);
    let background = 'rgb(255, 255, 255)';
    for (let ancestor = element; ancestor; ancestor = ancestor.parentElement) {
        const color = getComputedStyle(ancestor).backgroundColor;
        const values = channels(color);
        if (values.length === 3 || values[3] === 1) { background = color; break; }
    }
    const text = luminance(getComputedStyle(element).color);
    const surface = luminance(background);
    return (Math.max(text, surface) + 0.05) / (Math.min(text, surface) + 0.05);
}

function reviewMetadata(draft) {
    return {
        summary: 'The methods and work plan leave the second objective unsupported. Add an analysis activity before submitting the next revision.',
        record_basis: draft ? 'private_saved_draft' : 'latest_submitted_version',
        version_number: draft ? null : 3,
        reviewed_at: '2026-10-13T04:00:00Z',
        papers: [
            { reference: 'Attachment A: Work Plan', title: 'Attachment A: Work Plan',
                url: 'https://athena.test/evidence/work-plan', coverage: 'saved_structured_fields',
                omitted_fields: 2, truncated_fields: ['Research design'] },
            { reference: 'Detailed Research Proposal', title: 'Detailed Research Proposal',
                url: 'https://athena.test/evidence/proposal', coverage: 'uploaded_content_not_read',
                omitted_fields: 0, truncated_fields: [] },
            { reference: 'Attachment B: Line-Item Budget', title: 'Attachment B: Line-Item Budget',
                url: null, coverage: 'paper_not_available', omitted_fields: 0, truncated_fields: [] },
        ],
        findings: [
            { title: 'Objective 2 needs a matching activity', status: 'not_evidenced',
                explanation: 'The saved work plan includes data collection but no activity to analyze the survey results.',
                next_edit: 'Add a dated analysis activity and identify the responsible project staff.',
                evidence: [{ reference: 'Attachment A: Work Plan', location: 'Activity 1',
                    quote: 'Quarterly coastal transects <img src=x onerror=alert(1)>',
                    url: 'https://athena.test/evidence/work-plan' }] },
            { title: 'Field visits need a clearer schedule', status: 'partly_addressed',
                explanation: 'Quarterly sampling addresses the frequency requested by the reviewer, but the months and sites remain unspecified.',
                next_edit: 'List the sampling months and identify the coastal sites for each visit.',
                evidence: [{ reference: 'Attachment A: Work Plan', location: 'Activity 1',
                    quote: 'Conduct quarterly coastal transects.', url: 'https://athena.test/evidence/work-plan' }] },
            { title: 'The revised budget cannot be checked yet', status: 'cannot_verify',
                explanation: 'The line-item budget is unavailable in the saved records used for this review.',
                next_edit: 'Save the revised line-item budget, then check that its sampling costs match the work plan.',
                evidence: [] },
        ],
        limitations: ['Uploaded PDF content was not read.', 'Unsaved edits are not included in this review.'],
    };
}

async function mountWorkspace(page, css, fixture, draft) {
    const review = reviewMetadata(draft.isDraft);
    const workspace = await page.evaluate(({ html, withDrawer }) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const workspace = parsed.querySelector('[data-assistant-workspace]');
        if (!workspace) throw new Error('The exported ResearchAssistantUiTest fixture needs the full workspace');
        const surfaces = withDrawer ? [parsed.querySelector('[data-assistant-full-workspace]'),
            parsed.getElementById('research-assistant-panel').parentElement] : [workspace];
        surfaces.forEach(surface => {
            [surface, ...surface.querySelectorAll('*')].forEach(element => {
                Array.from(element.attributes).forEach(attribute => {
                    if (attribute.name.startsWith('x-trap')) element.removeAttribute(attribute.name);
                });
            });
        });
        return surfaces.map(surface => surface.outerHTML).join('');
    }, { html: fixture, withDrawer: Boolean(draft.withDrawer) });
    await page.setContent(`<!doctype html><html class="${draft.dark ? 'dark' : ''}"><head><style>${css}\n[x-cloak]{display:none!important}</style></head><body x-data class="font-sans" style="height:100dvh;overflow:hidden" data-app-shell>${workspace}</body></html>`);
    await page.addScriptTag({ content: `${helpers}\n${markdown}\n${citations}\n
        document.addEventListener('alpine:init', () => Alpine.data('researchAssistantCitations', researchAssistantCitations));
        document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
            ...({${uiMethods}}), ...researchAssistantReview(),
            workspaceOpen: true, drawerOpen: false, draft: '', isLoading: false, sendInProgress: false,
            retryAfter: 0, error: '', errorTitle: '', documentPickerOpen: false, documentLoading: false,
            documentOptions: [], selectedDocumentToken: '', documentError: '', documentRequestSequence: 0,
            contextEnabled: ${!draft.isDraft}, selectedContextId: ${draft.isDraft ? 0 : 24},
            proposalDraftId: ${draft.isDraft ? 42 : 0}, paperContext: null, activePaperField: null,
            requestSequence: 0, nextMessageId: 3, abortController: null, retryAction: null, retryContext: null,
            reviewContext: ${JSON.stringify({ ...(draft.isDraft ? { proposal_draft_id: 42 } : { topic_id: 24 }),
                workflow_scope: 'review', review_focus: 'revision_response', review_label: projectTitle })},
            contextOptions: [{ id: 24, label: ${JSON.stringify(projectTitle)}, status: 'revision_requested', meta: 'Environment · 2026-2027 · v3' }],
            contextOptionsLoading: false, contextOptionsError: '', contextOptionsLoaded: true,
            messages: [{ id: 1, role: 'user', content: 'Check the revision responses against the saved proposal.' },
                { id: 2, role: 'assistant', content: 'A readable review of the saved proposal.', sources: [],
                    literature: null, dissemination: null, review: ${JSON.stringify(review)} }],
            history: [{ id: 8, title: 'Earlier research discussion', preview: 'Sampling plan and budget', updated_at: '2026-10-12T04:00:00Z' }],
            currentConversationId: 8, historyLoading: false, historyLoadError: '', historySearchOpen: false,
            historySearchLoading: false, historySearchResults: [], historySearchQuery: '',
            copiedConversation: false, copiedMessageId: null,
            canSelectDocuments() { return false; }, hasLiveFormContext() { return false; },
            formatHistoryDate() { return 'Yesterday'; }, starterPrompts() { return []; },
            hasStarterPrompts() { return false; }, starterPromptHeading() { return 'Research support'; },
            starterPromptDescription() { return 'Choose a proposal to begin.'; },
            newConversation() {}, copyMessage() {},
            copyConversation() {}, exportConversation() {}, send() {},
            saveConversation() { return Promise.resolve({ id: 8 }); },
            sendPrompt(prompt, action, context) {
                window.proposalHelpRequests ||= [];
                window.proposalHelpRequests.push({ prompt, action, context: this.replyContext(context, action) });
                return Promise.resolve();
            },
            currentPageScope() { return 'review'; }, formContext() { return null; },
            resetComposers() {}, scrollToLatest() {}, syncPageScroll() {},
            setError(title, message) { this.errorTitle = title; this.error = message; },
            retry() { window.workspaceRetryCalls = (window.workspaceRetryCalls || 0) + 1; },
            openConversation() {}, loadHistory() {}, openHistorySearch() {}, closeHistorySearch() {},
            async loadContextOptions() { return true; }, resetContextOptions() {},
            highlightHistoryText(value) { return escapeAssistantHtml(value); }, queueHistorySearch() {},
        }));` });
    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
    await expect(page.locator('[data-assistant-composer]:visible')).toBeVisible();
    return review;
}

test('the full Athena workspace remains compact, readable, and usable across themes and screen sizes', async () => {
    const fixture = readFileSync(resolve('storage/framework/testing/research-assistant.html'), 'utf8');
    assert.ok(fixture.includes('data-assistant-header') && fixture.includes('data-assistant-review-coverage'),
        'Export the current ResearchAssistantUiTest fixture with ATHENA_EXPORT_ASSISTANT_LAYOUT=1 before this browser test');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1024, 1440]) {
            for (const dark of [false, true]) {
                for (const isDraft of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: width === 1024 ? 768 : 900 }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.context().route('**/*', route => {
                        const url = new URL(route.request().url());
                        return url.hostname === 'athena.test' && url.pathname.startsWith('/evidence/')
                            ? route.fulfill({ contentType: 'text/html', body: '<p>Saved work-plan evidence</p>' }) : route.abort();
                    });
                    const review = await mountWorkspace(page, css, fixture, { dark, isDraft });
                    const header = page.locator('[data-assistant-header]');
                    const composer = page.locator('[data-assistant-composer-shell]');
                    assert.ok((await header.boundingBox()).height <= 68, `${width}px header is compact`);
                    const composerHeight = (await composer.boundingBox()).height;
                    assert.ok(composerHeight <= 160,
                        `${width}px ${dark ? 'dark' : 'light'} ${isDraft ? 'draft' : 'submission'} composer is compact during a review, got ${composerHeight}px`);
                    await expect(composer.locator('[data-assistant-review-target]')).toContainText('Revision response review');
                    const visibleTitles = await composer.getByText(projectTitle, { exact: true }).evaluateAll(elements =>
                        elements.filter(element => element.checkVisibility()).length);
                    assert.equal(visibleTitles, 1, 'The composer shows the selected proposal title once');
                    if (isDraft) await expect(composer.locator('[data-assistant-project-picker]')).toBeHidden();
                    const thread = page.locator('[data-assistant-thread]');
                    const surfaces = await page.evaluate(() => Object.fromEntries([
                        ['canvas', '[data-assistant-messages]'], ['answer', '[data-assistant-response]'],
                        ['composer', '[data-assistant-composer-shell]'], ['sidebar', '[data-assistant-history-sidebar]'],
                    ].map(([name, selector]) => {
                        const element = Array.from(document.querySelectorAll(selector))
                            .find(candidate => candidate.checkVisibility()) || document.querySelector(selector);
                        const style = getComputedStyle(element);
                        return [name, { color: style.backgroundColor.match(/[\d.]+/g).map(Number),
                            opacity: Number(style.opacity), borderWidth: parseFloat(style.borderLeftWidth),
                            borderColor: style.borderLeftColor.match(/[\d.]+/g).map(Number),
                            borders: ['Top', 'Right', 'Bottom', 'Left'].map(edge => ({
                                width: parseFloat(style[`border${edge}Width`]),
                                color: style[`border${edge}Color`].match(/[\d.]+/g).map(Number),
                            })),
                            padding: parseFloat(style.paddingLeft), radius: parseFloat(style.borderTopLeftRadius) }];
                    })));
                    assert.deepEqual(surfaces.canvas.color.slice(0, 3), dark ? [12, 10, 9] : [238, 236, 232],
                        'The conversation canvas has a visible solid warm-neutral background');
                    assert.deepEqual(surfaces.answer.color.slice(0, 3), dark ? [28, 25, 23] : [255, 255, 255],
                        'The answer has its own opaque reading surface');
                    for (const [name, surface] of Object.entries(surfaces)) {
                        assert.equal(surface.color[3] ?? 1, 1, `${name} background is opaque`);
                        assert.equal(surface.opacity, 1, `${name} surface has no transparency`);
                    }
                    const colorDistance = (first, second) => Math.hypot(...first.slice(0, 3)
                        .map((channel, index) => channel - second[index]));
                    assert.ok(colorDistance(surfaces.canvas.color, surfaces.answer.color) >= 24,
                        'The answer is visibly distinct from the surrounding canvas in both themes');
                    assert.ok(surfaces.answer.borderWidth >= 1
                        && colorDistance(surfaces.answer.borderColor, surfaces.answer.color) >= 24,
                    'The answer has a clearly visible border');
                    for (const border of surfaces.answer.borders) {
                        assert.equal(border.width, 1, 'The answer has the same quiet 1px border on all four edges');
                        assert.deepEqual(border.color.slice(0, 3), dark ? [68, 64, 60] : [214, 211, 209],
                            'All answer borders use the same neutral stone color');
                    }
                    assert.ok(surfaces.answer.padding >= 16 && surfaces.answer.radius >= 12,
                        'The answer gives its content a comfortable inset and distinct surface');
                    assert.equal(await thread.locator('[data-assistant-user-message]:visible').evaluate(element =>
                        getComputedStyle(element).backgroundColor), dark ? 'rgb(41, 37, 36)' : 'rgb(245, 245, 244)',
                    'Message surfaces use opaque warm neutrals');
                    if (width === 1440) assert.ok((await thread.boundingBox()).width > 768, 'Desktop conversation uses the available width');
                    const summary = thread.getByText(review.summary, { exact: true });
                    await expect(summary).toBeVisible();
                    const fontSize = await summary.evaluate(element => parseFloat(getComputedStyle(element).fontSize));
                    assert.ok(fontSize >= 16 && fontSize <= 17, `Response copy uses readable 16–17px text, got ${fontSize}`);
                    const contrast = await summary.evaluate(textContrast);
                    assert.ok(contrast >= 7, `Main response text has strong contrast, got ${contrast.toFixed(2)}`);
                    const finding = thread.locator('.assistant-review-finding').first();
                    const headingSize = await finding.locator('h3').evaluate(element => parseFloat(getComputedStyle(element).fontSize));
                    assert.ok(headingSize >= 17 && headingSize >= fontSize, 'Structured findings have a clear semantic heading hierarchy');
                    assert.notEqual(await finding.evaluate(element => getComputedStyle(element).display), 'grid',
                        'Findings read vertically without reserving an empty evidence column');
                    const nextEdit = finding.locator('.assistant-review-next-edit');
                    await expect(nextEdit).toContainText('Suggested next step');
                    await expect(nextEdit).toContainText(review.findings[0].next_edit);
                    const explanation = finding.getByText(review.findings[0].explanation, { exact: true });
                    assert.ok((await nextEdit.boundingBox()).y < (await explanation.boundingBox()).y,
                        'The recommended edit appears before the supporting explanation');
                    const supporting = finding.locator('details.assistant-review-evidence');
                    await expect(supporting).not.toHaveAttribute('open');
                    await expect(supporting.locator('summary')).toContainText('See supporting text');

                    const toggle = page.locator('[data-assistant-history-toggle]');
                    const sidebar = page.locator('[data-assistant-history-sidebar]');
                    assert.equal(await sidebar.getByRole('button', { name: 'Copy chat', exact: true }).count(), 0,
                        'Conversation actions belong in Chat options, leaving the sidebar for navigation');
                    assert.equal(await sidebar.getByRole('button', { name: 'Export .txt', exact: true }).count(), 0);
                    await expect(sidebar).not.toContainText('Privacy:');
                    if (width >= 1024) {
                        const selectedChat = sidebar.getByRole('button', { name: 'Earlier research discussion Yesterday', exact: true });
                        await expect(selectedChat).toHaveAttribute('aria-current', 'true');
                        const dateContrast = await selectedChat.getByText('Yesterday', { exact: true }).evaluate(textContrast);
                        assert.ok(dateContrast >= 4.5, `Selected history dates have readable contrast, got ${dateContrast.toFixed(2)}`);
                        const search = sidebar.getByRole('button', { name: 'Search history Ctrl K', exact: true });
                        assert.ok((await search.boundingBox()).height <= 44, 'History search fits on one navigation row');
                        assert.ok((await search.locator('kbd').boundingBox()).height <= 24, 'The shortcut stays on one line');
                        await expect(toggle).toBeVisible();
                        const expanded = await toggle.getAttribute('aria-expanded') === 'true';
                        await toggle.click();
                        await expect(toggle).toHaveAttribute('aria-expanded', String(!expanded));
                        if (expanded) await expect(sidebar).toBeHidden();
                        else await expect(sidebar).toBeVisible();
                        await toggle.click();
                        await expect(toggle).toHaveAttribute('aria-expanded', String(expanded));
                    } else {
                        await expect(toggle).toBeHidden();
                        await expect(sidebar).toBeHidden();
                    }
                    const options = page.getByRole('button', { name: 'Chat options', exact: true });
                    await expect(page.getByRole('button', { name: 'Copy chat', exact: true })).toBeHidden();
                    await options.click();
                    await expect(page.getByRole('button', { name: 'Search history', exact: true })).toBeVisible();
                    await expect(page.getByRole('button', { name: 'Copy chat', exact: true })).toBeVisible();
                    await expect(page.getByRole('button', { name: 'Export .txt', exact: true })).toBeVisible();
                    await expect(page.locator('[data-assistant-privacy]')).toBeVisible();
                    await options.press('Escape');
                    await expect(options).toBeFocused();
                    await expect(page.locator('[data-assistant-privacy]')).toBeHidden();

                    const coverage = thread.locator('[data-assistant-review-coverage]:visible');
                    await expect(coverage.locator('summary')).toContainText(isDraft ? 'Reviewed saved draft' : 'Reviewed submission v3');
                    await expect(coverage.locator('summary')).toContainText('Saved fields only');
                    await coverage.locator('summary').click();
                    await expect(coverage).toContainText('Attachment A: Work Plan');
                    await expect(coverage).toContainText('Detailed Research Proposal');
                    await expect(coverage).toContainText('Attachment B: Line-Item Budget');
                    await expect(coverage).toContainText('2 fields omitted');
                    await expect(coverage).toContainText('1 fields shortened');
                    await expect(thread).toContainText('Uploaded PDF content was not read.');
                    await expect(thread).toContainText('Unsaved edits are not included in this review.');
                    const quote = thread.getByText(review.findings[0].evidence[0].quote, { exact: false });
                    await expect(quote).toBeHidden();
                    await supporting.locator('summary').focus();
                    await page.keyboard.press('Enter');
                    await expect(supporting).toHaveAttribute('open', '');
                    await expect(quote).toBeVisible();
                    assert.equal(await thread.locator('img').count(), 0, 'Evidence quotes stay escaped');
                    const cleanQuote = 'Conduct quarterly coastal transects and record water-quality measurements.';
                    await page.evaluate(quote => {
                        Alpine.store('researchAssistant').messages.find(message => message.review)
                            .review.findings[0].evidence[0].quote = quote;
                    }, cleanQuote);
                    await expect(thread.getByText(cleanQuote, { exact: true })).toHaveText(cleanQuote);
                    if (await supporting.getAttribute('open') === null) await supporting.locator('summary').click();
                    await expect(thread.getByText(cleanQuote, { exact: true })).toBeVisible();
                    await expect(thread).not.toContainText('<img src=x');
                    await coverage.locator('summary').click();
                    await expect(coverage).not.toHaveAttribute('open');
                    const evidence = thread.getByRole('link', { name: 'Open evidence', exact: false }).first();
                    await expect(evidence).toHaveAttribute('href', 'https://athena.test/evidence/work-plan');
                    await evidence.focus();
                    assert.equal(await evidence.evaluate(element => document.activeElement === element), true);
                    const viewportLayout = await page.evaluate(() => ({
                        mode: document.compatMode, scroll: document.scrollingElement.scrollTop,
                        boxes: Object.fromEntries(['[data-assistant-workspace]', '[data-assistant-header]',
                            '[data-assistant-messages]', '[data-assistant-workspace] footer', '[data-assistant-composer-shell]']
                            .map(selector => { const element = document.querySelector(selector); const box = element.getBoundingClientRect();
                                return [selector, { y: box.y, height: box.height, scrollTop: element.scrollTop }]; })),
                    }));
                    assert.ok(viewportLayout.boxes['[data-assistant-header]'].y >= 0,
                        `The header remains in the viewport after focusing evidence: ${JSON.stringify(viewportLayout)}`);
                    await expect(header).toBeInViewport();
                    await expect(composer).toBeInViewport();
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true,
                        `${width}px ${dark ? 'dark' : 'light'} workspace overflows`);
                    assert.equal(await thread.evaluate(element => element.scrollWidth <= element.clientWidth), true,
                        'The conversation content does not require horizontal scrolling');
                    assert.equal(await composer.evaluate(element => element.scrollWidth <= element.clientWidth), true,
                        'Composer controls fit their available width');
                    assert.deepEqual(errors, []);
                    await page.evaluate(() => {
                        const store = Alpine.store('researchAssistant');
                        store.errorTitle = 'Athena could not complete the review';
                        store.error = 'Athena returned an invalid review format. Please retry the review.';
                    });
                    const alert = page.getByRole('alert');
                    await expect(alert).toBeVisible();
                    await expect(alert).toContainText('Athena returned an invalid review format. Please retry the review.');
                    const retry = alert.getByRole('button');
                    await expect(retry).toBeVisible();
                    await expect(retry).toHaveText('Retry');
                    assert.equal(await alert.getByRole('button').count(), 1, 'The error offers one clear retry action');
                    assert.ok((await retry.boundingBox()).height >= 44, 'Retry has a usable touch target');
                    const retryStyles = await retry.evaluate(element => ({
                        border: parseFloat(getComputedStyle(element).borderTopWidth),
                        background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color,
                    }));
                    assert.ok(retryStyles.border >= 1);
                    assert.equal(retryStyles.background, 'rgb(127, 29, 29)');
                    assert.equal(retryStyles.color, 'rgb(255, 255, 255)');
                    await page.evaluate(() => { Alpine.store('researchAssistant').retryAfter = 5; });
                    await expect(retry).toHaveText('Retry in 5s');
                    await expect(retry).toBeDisabled();
                    await page.evaluate(() => { Alpine.store('researchAssistant').retryAfter = 0; });
                    await expect(retry).toHaveText('Retry');
                    await retry.focus();
                    await page.keyboard.press('Enter');
                    assert.equal(await page.evaluate(() => window.workspaceRetryCalls), 1,
                        'The rendered Retry control invokes the actual Alpine retry binding');
                    await expect(header).toBeInViewport();
                    await expect(composer).toBeInViewport();
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true,
                        'The format-error alert does not overflow on mobile');
                    await page.evaluate(() => {
                        const store = Alpine.store('researchAssistant');
                        store.error = ''; store.errorTitle = '';
                    });
                    await expect(alert).toBeHidden();
                    const popupOpened = page.waitForEvent('popup');
                    await evidence.click();
                    const popup = await popupOpened;
                    await expect(popup.getByText('Saved work-plan evidence')).toBeVisible();
                    assert.equal(popup.url(), 'https://athena.test/evidence/work-plan');
                    await popup.close();
                    await supporting.locator('summary').click();
                    await expect(supporting).not.toHaveAttribute('open');
                    await page.locator('[data-assistant-messages]').evaluate(element => { element.scrollTop = 0; });
                    if (width === 1440 && !dark && !isDraft) {
                        await page.screenshot({ path: resolve('storage/framework/testing/assistant-workspace-desktop.png') });
                    }
                    if (width === 390 && dark && isDraft) {
                        await page.screenshot({ path: resolve('storage/framework/testing/assistant-workspace-mobile-dark.png') });
                    }
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});

test('faculty proposal help lets people choose a task before sending a proposal-bound request', async () => {
    const fixture = readFileSync(resolve('storage/framework/testing/research-assistant.html'), 'utf8');
    assert.ok(fixture.includes('data-assistant-proposal-help'), 'Export the current proposal-help workspace fixture before this browser test');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1440]) {
            for (const dark of [false, true]) {
                for (const isDraft of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.context().route('**/*', route => route.abort());
                    await mountWorkspace(page, css, fixture, { dark, isDraft });
                    const labels = isDraft
                        ? ['Suggest proposal changes', 'Strengthen my proposal', 'Review objectives and methods', 'Compare proposal papers']
                        : ['Plan my revisions', 'Explain reviewer feedback', 'Help write a response', 'Compare saved changes'];
                    for (const [index, label] of labels.entries()) {
                        const title = 'Water quality "2026" & farmers’ research <img src=x onerror=alert(1)>';
                        const result = await page.evaluate(async ({ isDraft, title }) => {
                            const store = Alpine.store('researchAssistant');
                            window.proposalHelpRequests = [];
                            return await store.openProposalHelp({
                                target_label: title, label: isDraft ? 'Improve this draft' : 'Get revision help',
                                prompt: 'Compare the saved proposal with its research papers.',
                                context: { ...(isDraft ? { proposal_draft_id: 42 } : { topic_id: 24 }), workflow_scope: 'review' },
                                action: { type: 'review_proposal', review_focus: isDraft ? 'consistency' : 'revision_response' },
                            }, document.querySelector('[data-assistant-composer]'));
                        }, { isDraft, title });
                        assert.equal(result, true);
                        await expect(page.locator('[data-assistant-composer]:visible')).toBeFocused();
                        assert.deepEqual(await page.evaluate(() => window.proposalHelpRequests), [],
                            'Opening proposal help does not send a model request');
                        const panel = page.locator('[data-assistant-proposal-help]:visible');
                        await expect(panel).toBeVisible();
                        await expect(panel).toContainText('How can Athena help?');
                        await expect(panel).toContainText(title);
                        assert.equal(await panel.locator('img').count(), 0, 'Proposal titles remain escaped in the choice panel');
                        assert.equal(await panel.getByRole('button').count(), labels.length);
                        for (const optionLabel of labels) {
                            const option = panel.getByRole('button', { name: optionLabel, exact: false });
                            await expect(option).toBeVisible();
                            assert.ok((await option.boundingBox()).height >= 44, 'Help choices have usable touch targets');
                        }
                        const choice = panel.getByRole('button', { name: label, exact: false });
                        await choice.focus();
                        await page.keyboard.press('Enter');
                        await expect(panel).toHaveCount(0);
                        const requests = await page.evaluate(() => window.proposalHelpRequests);
                        assert.equal(requests.length, 1, 'One explicit choice starts one request');
                        const request = requests[0];
                        assert.equal(request.context[isDraft ? 'proposal_draft_id' : 'topic_id'], isDraft ? 42 : 24);
                        assert.equal(request.context.review_label, title);
                        assert.equal(request.context.workflow_scope, 'review');
                        assert.equal(request.context.form, undefined);
                        if (label === 'Suggest proposal changes') {
                            assert.deepEqual(request.action, { type: 'suggest_proposal_changes' });
                            assert.equal(request.context.assistance_mode, undefined);
                        } else if (index === labels.length - 1) {
                            assert.deepEqual(request.action, { type: 'review_proposal', review_focus: isDraft ? 'consistency' : 'revision_response' });
                            assert.equal(request.context.assistance_mode, undefined);
                        } else {
                            assert.equal(request.action, null);
                            assert.equal(request.context.assistance_mode, isDraft ? 'draft_guidance'
                                : ['revision_plan', 'feedback_explanation', 'response_draft'][index]);
                        }
                        await expect(page.locator('[data-assistant-header]')).toBeInViewport();
                        await expect(page.locator('[data-assistant-composer-shell]')).toBeInViewport();
                        assert.ok((await page.locator('[data-assistant-composer-shell]').boundingBox()).height <= 160);
                        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true,
                            'The help panel and safe proposal title fit narrow layouts');
                    }
                    assert.deepEqual(errors, []);
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});

test('proposal help survives the actual workspace collapse and expand controls', async () => {
    const fixture = readFileSync(resolve('storage/framework/testing/research-assistant.html'), 'utf8');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.context().route('**/*', route => route.abort());
                await mountWorkspace(page, css, fixture, { dark, isDraft: false, withDrawer: true });
                await page.evaluate(async title => {
                    window.proposalHelpRequests = [];
                    await Alpine.store('researchAssistant').openProposalHelp({
                        target_label: title, label: 'Get revision help', prompt: 'Compare the saved changes.',
                        context: { topic_id: 24, workflow_scope: 'review' },
                        action: { type: 'review_proposal', review_focus: 'revision_response' },
                    });
                }, projectTitle);
                const fullWorkspace = page.locator('[data-assistant-full-workspace]');
                await expect(fullWorkspace.locator('[data-assistant-proposal-help]')).toBeVisible();
                await fullWorkspace.getByRole('button', { name: 'Collapse to side panel', exact: true }).click();
                await expect(fullWorkspace).toBeHidden();
                const drawer = page.locator('#research-assistant-panel');
                await expect(drawer).toBeVisible();
                await expect(drawer.locator('[data-assistant-proposal-help]')).toBeVisible();
                await expect(drawer.locator('[data-assistant-proposal-help]')).toContainText(projectTitle);
                await expect(drawer.getByRole('button', { name: 'Plan my revisions', exact: false })).toBeVisible();
                assert.deepEqual(await page.evaluate(() => window.proposalHelpRequests), []);
                await drawer.getByRole('button', { name: 'Expand to full workspace', exact: true }).click();
                await expect(drawer).toBeHidden();
                await expect(fullWorkspace.locator('[data-assistant-proposal-help]')).toBeVisible();
                await expect(fullWorkspace.locator('[data-assistant-proposal-help]')).toContainText(projectTitle);
                assert.equal(await page.evaluate(() => Alpine.store('researchAssistant').reviewContext.topic_id), 24);
                assert.deepEqual(await page.evaluate(() => window.proposalHelpRequests), []);
                await expect(fullWorkspace.locator('[data-assistant-header]')).toBeInViewport();
                await expect(fullWorkspace.locator('[data-assistant-composer-shell]')).toBeInViewport();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (width === 1440 && !dark) {
                    await page.screenshot({ path: resolve('storage/framework/testing/assistant-proposal-help-desktop.png') });
                }
                if (width === 320 && dark) {
                    await page.screenshot({ path: resolve('storage/framework/testing/assistant-proposal-help-mobile-dark.png') });
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
