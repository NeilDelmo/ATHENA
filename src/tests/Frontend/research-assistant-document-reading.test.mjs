import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { researchAssistantHistory } from '../../resources/js/research-assistant-history.js';
import { assistantDocumentAction, assistantDocumentQuery, defaultAssistantContextId } from '../../resources/js/research-assistant-context.js';
import { normalizeAssistantReportChanges, reportContext } from '../../resources/js/research-assistant-report-changes.js';

process.env.ATHENA_TEST_PRESERVE_DATABASE = '1';

const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
const methodSource = (name) => {
    const match = app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing assistant method: ${name}`);
    return match[0];
};
const reading = {
    label: 'Attachment A: Work Plan', filename: '', format: 'SAVED', basis: 'saved_draft_projection', version: 4,
    coverage: { selected_passages: 6, total_passages: 19, omitted_passages: 13, truncated: true,
        limitations: ['Only passages relevant to this question were selected.'] },
};
const response = (payload) => ({ ok: true, json: async () => payload });

function assistant(fetchReply = async () => response({ reply: 'The work plan includes quarterly sampling.', document_reading: reading }), overrides = {}, dependencies = {}) {
    const pageDocument = {
        body: { dataset: { researchAssistantUrl: '/chat', researchAssistantDocumentsUrl: '/documents' } },
        querySelector: () => ({ content: 'csrf' }), getElementById: () => null, addEventListener() {},
    };
    const deps = {
        document: pageDocument, window: { location: { origin: 'https://athena.test' }, setTimeout() {}, addEventListener() {} },
        Alpine: { effect() {} }, fetch: fetchReply, assistantDocumentAction, assistantDocumentQuery, defaultAssistantContextId,
        normalizeAssistantLiterature: () => null, normalizeAssistantProposalChanges: () => null,
        normalizeAssistantReportChanges, reportContext,
        serializeAssistantLiterature: () => null, serializeAssistantProposalChanges: () => null,
        disseminationAction: () => null, disseminationSearchRequest: () => null, disseminationSearchReply: () => null,
        currentPaperEditor: () => null, paperEditorHasUnsavedChanges: () => false, finishPaperEditorAutoSave: async () => true,
        backgroundAutoSaveHasPendingChanges: () => false, finishBackgroundAutoSaves: async () => true,
        assistantPageScope: () => 'details', redactAssistantText: (value) => value, researchAssistantMessageLimit: 8000,
        ...dependencies,
    };
    const methods = ['init', 'analyzeSelectedDocument', 'clearActiveDocument', 'documentReadingMetadata',
        'loadDocumentOptions', 'closeDocumentPicker', 'selectProjectContext', 'syncWorkspaceContext',
        'conversationTranscript', 'hydrateConversation', 'applyConversationContext', 'sendPrompt', 'send', 'requestReply', 'replyContext', 'contextMessages', 'saveConversation',
        'openConversation', 'clearConversation', 'stop', 'retry'];
    return {
        ...researchAssistantHistory([], fetchReply, { document: deps.document, ...deps.window }),
        conversationReady: true,
        ...new Function(...Object.keys(deps), `return ({${methods.map(methodSource).join('\n')}});`)(...Object.values(deps)),
        draft: '', messages: [], documentOptions: [{ token: 'saved-paper', label: 'Attachment A: Work Plan', format: 'SAVED' }],
        selectedDocumentToken: 'saved-paper', activeDocument: null, documentAnalysisPending: false,
        documentRequestSequence: 0, requestSequence: 0, nextMessageId: 1, contextOptions: [{ id: 12, label: 'Research' }],
        contextOptionsLoading: false, contextOptionsError: '', contextOptionsLoaded: true,
        contextEnabled: true, selectedContextId: 12, proposalDraftId: 42, currentConversationId: null,
        retryAction: null, retryContext: null, retryAfter: 0, isLoading: false, sendInProgress: false, error: '',
        contextPayload: () => ({ proposal_draft_id: 42 }), formContext: () => null,
        historyUrl: () => '', historyConversationUrl: (id) => `/history/${id}`,
        hasConversation() { return this.messages.length > 0; },
        scrollToLatest() {}, resetComposers() {}, syncPageScroll() {}, restoreReviewContext() {}, upsertHistory() {},
        async restoreLiteratureRequest() {},
        async loadContextOptions() { return true; }, resetContextOptions() {},
        setError(title, message) { this.error = `${title}: ${message}`; },
        ...overrides,
    };
}

test('Analyze sends the typed question and retains only the deliberately chosen document for follow-ups', async () => {
    const requests = [];
    const store = assistant(async (url, options) => {
        requests.push(JSON.parse(options.body));
        return response({ reply: 'Quarterly sampling is scheduled.', document_reading: reading });
    });
    store.draft = 'Which months include analysis activities?';
    await store.analyzeSelectedDocument();
    assert.equal(requests[0].messages[0].content, 'Which months include analysis activities?');
    assert.deepEqual(requests[0].action, { type: 'analyze_document', document_token: 'saved-paper' });
    assert.deepEqual(store.activeDocument, { token: 'saved-paper', label: 'Attachment A: Work Plan', format: 'SAVED' });
    store.draft = 'Who is responsible for that activity?';
    await store.send();
    assert.deepEqual(requests[1].action, requests[0].action);
    assert.deepEqual(store.messages.at(-1).document_reading, reading);
    for (const action of [{ type: 'search_literature' }, { type: 'review_proposal', review_focus: 'consistency' }]) {
        store.draft = 'Use this explicit help option.';
        await store.send(action);
        assert.deepEqual(requests.at(-1).action, action);
    }
});

test('ordinary chat never reads the first picker result without an explicit Analyze action', async () => {
    let request;
    const store = assistant(async (url, options) => { request = JSON.parse(options.body); return response({ reply: 'General guidance.' }); });
    store.draft = 'Explain research design.';
    await store.send();
    assert.equal(request.action, null);
    assert.equal(store.activeDocument, null);
    store.draft = '';
    await store.analyzeSelectedDocument();
    assert.match(request.messages.at(-1).content, /^Analyze the selected document/);
});

test('saved-paper analysis waits for autosave and preserves the question when saving fails', async () => {
    let finishSaving;
    const requests = [];
    const store = assistant(async (url, options) => { requests.push(JSON.parse(options.body)); return response({ reply: 'Read.' }); }, {}, {
        paperEditorHasUnsavedChanges: () => true,
        finishPaperEditorAutoSave: () => new Promise((done) => { finishSaving = done; }),
    });
    store.draft = 'Read the latest work plan.';
    const pending = store.analyzeSelectedDocument();
    assert.equal(store.documentAnalysisPending, true);
    assert.equal(requests.length, 0);
    await store.send();
    assert.equal(store.messages.length, 0);
    finishSaving(false);
    await pending;
    assert.equal(requests.length, 0);
    assert.equal(store.draft, 'Read the latest work plan.');
    assert.match(store.documentError, /Save your latest changes/);
    const retry = store.analyzeSelectedDocument();
    finishSaving(true);
    await retry;
    assert.equal(requests.length, 1);
});

test('changing proposal or clearing selection invalidates an analysis still awaiting autosave', async () => {
    for (const clear of [(store) => store.selectProjectContext(12), (store) => store.clearActiveDocument()]) {
        let finishSaving;
        let requests = 0;
        const store = assistant(async () => { requests++; return response({ reply: 'Late reply.' }); }, {}, {
            paperEditorHasUnsavedChanges: () => true,
            finishPaperEditorAutoSave: () => new Promise((done) => { finishSaving = done; }),
        });
        store.draft = 'Read this selected document.';
        const pending = store.analyzeSelectedDocument();
        clear(store);
        finishSaving(true);
        await pending;
        assert.equal(store.activeDocument, null);
        assert.equal(store.documentAnalysisPending, false);
        assert.equal(requests, 0);
    }
});

test('clearing the document stops its pending response and removes the document retry action', async () => {
    let finish;
    const store = assistant(() => new Promise((done) => { finish = done; }));
    store.activeDocument = { token: 'saved-paper', label: 'Work plan', format: 'SAVED' };
    store.messages = [{ role: 'user', content: 'Read the work plan.' }];
    store.retryAction = { type: 'analyze_document', document_token: 'saved-paper' };
    const pending = store.requestReply();
    store.clearActiveDocument();
    finish(response({ reply: 'Outdated answer.', document_reading: reading }));
    assert.equal(await pending, null);
    assert.equal(store.messages.length, 1);
    assert.equal(store.activeDocument, null);
    assert.equal(store.retryAction, null);
});

test('new chat, page navigation, workspace switching and history reopening discard ephemeral document access', async () => {
    const active = { token: 'do-not-restore', label: 'Work plan', format: 'SAVED' };
    const events = {};
    const store = assistant(async () => response({ conversation: { id: 7, messages: [{ role: 'assistant', content: 'Saved answer.', document_reading: reading }] } }), {}, {
        document: { body: { dataset: {} }, addEventListener: (name, callback) => { events[name] = callback; } },
        window: { addEventListener() {}, athenaResearchAssistantContexts: [], location: { origin: 'https://athena.test' } },
    });
    store.activeDocument = active;
    store.init();
    events['livewire:navigating']();
    assert.equal(store.activeDocument, null);
    store.activeDocument = active;
    await store.syncWorkspaceContext();
    assert.equal(store.activeDocument, null);
    store.activeDocument = active;
    store.clearConversation();
    assert.equal(store.activeDocument, null);
    store.activeDocument = active;
    await store.openConversation(7);
    assert.equal(store.activeDocument, null);
    assert.deepEqual(store.messages[0].document_reading, reading);
});

test('history keeps the current conversation when the server denies access to the saved project', async () => {
    const messages = [{ role: 'user', content: 'Keep this current conversation.' }];
    const store = assistant(async () => ({ ok: false, status: 403, json: async () => ({ message: 'This saved project is unavailable.' }) }), {
        messages, currentConversationId: 8, saveConversation: async () => ({ id: 8 }),
    });
    await store.openConversation(7);
    assert.equal(store.messages, messages);
    assert.equal(store.currentConversationId, 8);
    assert.match(store.error, /History unavailable:.*saved project is unavailable/);
});

test('authorized history restores its project even while the project picker is unavailable', async () => {
    const store = assistant(async () => response({ conversation: { id: 7, context: { topic_id: 24 },
        messages: [{ role: 'user', content: 'Saved project question.' }] } }), { loadContextOptions: async () => false });
    await store.openConversation(7);
    assert.equal(store.currentConversationId, 7);
    assert.equal(store.selectedContextId, 24);
    assert.equal(store.liveFormContextEnabled, false);
});

test('stopped or switched workspaces discard a delayed history response', async () => {
    for (const invalidate of [(store) => store.stop(), (store) => store.syncWorkspaceContext()]) {
        let release, started;
        const loading = new Promise(resolve => { started = resolve; });
        const originalMessages = [{ role: 'user', content: 'Previous workspace conversation.' }];
        const store = assistant(() => new Promise(resolve => { release = resolve; started(); }), {
            messages: originalMessages, currentConversationId: 8, workspaceOpen: false,
            saveConversation: async () => ({ id: 8 }),
        }, { window: { athenaResearchAssistantContexts: [{ id: 90, label: 'Current workspace project' }], athenaResearchAssistantActiveContextId: 90 } });
        const pending = store.openConversation(7);
        await loading;
        assert.equal(store.messages, originalMessages);
        const sequence = store.requestSequence;
        await invalidate(store);
        assert.ok(store.requestSequence > sequence);
        const currentMessages = [{ role: 'user', content: 'Current workspace conversation.' }];
        Object.assign(store, { messages: currentMessages, currentConversationId: 90, selectedContextId: 90, contextEnabled: true });
        release(response({ conversation: { id: 7, context: { topic_id: 12 }, messages: [{ role: 'user', content: 'Old workspace question.' }] } }));
        await pending;
        assert.equal(store.messages, currentMessages);
        assert.equal(store.currentConversationId, 90);
        assert.equal(store.selectedContextId, 90);
        assert.equal(store.workspaceOpen, false);
    }
});

test('a stale document-list response cannot repopulate choices after a workspace change', async () => {
    let finish;
    const store = assistant(() => new Promise((done) => { finish = done; }));
    store.documentsUrl = () => '/documents';
    const pending = store.loadDocumentOptions();
    store.clearConversation();
    finish(response({ documents: [{ token: 'other-workspace', format: 'PDF' }] }));
    await pending;
    assert.deepEqual(store.documentOptions, []);
    assert.equal(store.activeDocument, null);
});

test('history persists bounded reading coverage without extraction text or selection tokens', async () => {
    let saved;
    const store = assistant(async (url, options) => { saved = JSON.parse(options.body); return response({ conversation: { id: 7 } }); }, { historyUrl: () => '/history' });
    store.activeDocument = { token: 'secret-selection', label: 'Work plan' };
    store.messages = [{ role: 'assistant', content: 'Grounded answer.', document_reading: {
        ...reading, token: 'secret-selection', text: 'Raw extraction must not persist.',
        coverage: { ...reading.coverage, raw_text: 'Private extracted text', limitations: ['A'.repeat(900), ...Array(9).fill('Limit')] },
    } }];
    await store.saveConversation();
    assert.equal(JSON.stringify(saved).includes('secret-selection'), false);
    assert.equal(JSON.stringify(saved).includes('Raw extraction'), false);
    assert.equal(JSON.stringify(saved).includes('Private extracted text'), false);
    assert.equal(saved.messages[0].document_reading.coverage.limitations.length, 6);
    assert.equal(saved.messages[0].document_reading.coverage.limitations[0].length, 500);
    assert.equal(store.documentReadingMetadata({ ...reading, basis: 'arbitrary' }), null);
    assert.equal(store.documentReadingMetadata({ ...reading, format: 'HTML' }), null);
});

test('a reply length notice survives saving and reopening the chat', async () => {
    const store = assistant(async () => response({ reply: 'Start by defining the study population.', reply_truncated: true }));
    store.draft = 'Help me plan a study.';

    await store.send();
    const transcript = store.conversationTranscript();
    assert.equal(transcript.messages[1].reply_truncated, true);

    store.hydrateConversation({ ...transcript, id: 7 }, true);
    assert.equal(store.messages[1].reply_truncated, true);
    assert.equal(store.messages[1].content, 'Start by defining the study population.');
});

test('composer and reply length notices work on mobile and desktop in light and dark mode', async () => {
    const composer = readFileSync(resolve('resources/views/components/research-assistant-composer.blade.php'), 'utf8')
        .match(/<form\b[\s\S]*?<\/form>/)[0]
        .replaceAll('{{ $inputId }}', 'message').replaceAll('{{ $id }}', 'test');
    const notice = readFileSync(resolve('resources/views/components/research-assistant-reply-notice.blade.php'), 'utf8');
    for (const surface of ['drawer', 'workspace']) {
        const view = readFileSync(resolve(`resources/views/components/research-assistant-${surface}.blade.php`), 'utf8');
        assert.ok(view.includes('<x-research-assistant-reply-notice />'));
    }

    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const [width, dark] of [[320, true], [1280, false]]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="bg-white p-3 dark:bg-stone-950"><main class="mx-auto max-w-2xl" x-data="{ message: {} }">${notice}<button id="flag" @click="message.reply_truncated = true">Flag</button>${composer}</main></body></html>`);
            await page.addScriptTag({ content: `const researchAssistantMessageLimit = ${app.match(/const researchAssistantMessageLimit = (\d+);/)[1]};
                document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
                    draft: '', conversationReady: true, retryAfter: 0,
                    resizeComposer() {}, handleComposerKeydown() {},
                    ${methodSource('messageLengthNotice')}
                }));` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });

            const composerNotice = page.locator('[data-assistant-message-limit]');
            const replyNotice = page.locator('[data-assistant-reply-notice]');
            const input = page.getByRole('textbox');
            await expect(composerNotice).toBeHidden();
            await expect(replyNotice).toBeHidden();
            await input.fill('a'.repeat(7499));
            await expect(composerNotice).toBeHidden();
            await input.fill('a'.repeat(7500));
            await expect(composerNotice).toContainText('500 characters left');
            await input.fill('a'.repeat(7999));
            await expect(composerNotice).toContainText('1 character left');
            await input.fill('a'.repeat(8000));
            await expect(composerNotice).toContainText('8,000-character limit');
            await expect(composerNotice).toBeVisible();
            await expect(input).toHaveAccessibleDescription(/8,000-character limit/);
            await page.locator('#flag').click();
            await expect(replyNotice).toBeVisible();
            await expect(replyNotice).toContainText('Ask me to continue');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
            await input.fill('Short question');
            await expect(composerNotice).toBeHidden();
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('selected-document controls and coverage remain readable, safe, and clearable on mobile and dark mode', async () => {
    const composer = readFileSync(resolve('resources/views/components/research-assistant-composer.blade.php'), 'utf8')
        .replace(/^@props[^\n]*\n/, '').replace(/<x-research-assistant[^>]+\/>/g, '');
    const coverage = readFileSync(resolve('resources/views/components/research-assistant-document-reading.blade.php'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1280]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="bg-white p-3 dark:bg-stone-950"><main class="mx-auto max-w-2xl" x-data="{ message: null }" x-init="message = $store.researchAssistant.messages[0]">${coverage}${composer}</main></body></html>`);
                await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
                    activeDocument: { token: 'ephemeral-token', format: 'SAVED', label: 'Work plan <img src=x onerror=alert(1)> with a very long saved paper title' },
                    messages: [{ document_reading: ${JSON.stringify(reading)} }], draft: '', reviewContext: null,
                    conversationReady: true, isLoading: false, retryAfter: 0, proposalHelpOptions() { return []; },
                    messageLengthNotice() { return ''; }, literatureBusy() { return false; },
                    hasContextOptions() { return false; }, reviewTargetLabel() { return ''; }, reviewFocusLabel() { return ''; },
                    clearActiveDocument() { this.activeDocument = null; },
                }));` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                const chip = page.locator('[data-assistant-active-document]');
                await expect(chip).toContainText('Work plan <img');
                assert.equal(await chip.locator('img').count(), 0);
                const clear = page.getByRole('button', { name: 'Stop asking about this document', exact: true });
                const bounds = await clear.boundingBox();
                assert.ok(bounds.width >= 44 && bounds.height >= 44);
                await page.locator('[data-assistant-document-reading] summary').click();
                await expect(page.getByText('6 of 19 readable passages used for this answer.', { exact: true })).toBeVisible();
                await expect(page.getByText('This answer uses selected excerpts; it is not a complete review of every passage.', { exact: true })).toBeVisible();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                await clear.click();
                await expect(chip).toBeHidden();
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
