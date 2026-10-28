import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { researchAssistantReview } from '../../resources/js/research-assistant-review.js';
import { journalFinder } from '../../resources/js/journal-finder.js';
import { normalizeAssistantLiterature, serializeAssistantLiterature } from '../../resources/js/research-assistant-literature.js';
import { normalizeAssistantProposalChanges, serializeAssistantProposalChanges } from '../../resources/js/research-assistant-proposal-changes.js';
import { normalizeAssistantReportChanges, reportContext } from '../../resources/js/research-assistant-report-changes.js';

const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
const methodSource = (name) => {
    const match = app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing assistant method: ${name}`);
    return `${app.slice(match.index, match.index + match[0].length)}`;
};
const methodsSource = ['sendPrompt', 'contextPayload', 'replyContext', 'send', 'requestReply', 'contextMessages',
    'saveConversation', 'openConversation', 'openWithContext', 'retry', 'stop', 'clearConversation', 'selectProjectContext', 'selectedContext', 'closeDocumentPicker', 'hasPaperContext', 'hasContextOptions']
    .map(methodSource).join('\n');
const response = (payload, status = 200) => ({ ok: status === 200, status, json: async () => payload });

function assistant(fetchReply = async (url) => response(url === '/chat'
    ? { reply: 'Objective 2 has no matching work-plan activity. Add a measured task and its budget basis.' }
    : { conversation: { id: 8 } }), overrides = {}) {
    const document = {
        body: { dataset: { researchAssistantUrl: '/chat' } },
        querySelector: () => ({ content: 'csrf' }),
        getElementById: () => null,
    };
    const methods = new Function('document', 'fetch', 'normalizeAssistantLiterature', 'serializeAssistantLiterature',
        'normalizeAssistantProposalChanges', 'serializeAssistantProposalChanges', 'normalizeAssistantReportChanges', 'reportContext', 'currentPageReportContext', 'redactAssistantText', 'researchAssistantMessageLimit', `return ({${methodsSource}});`)(document, fetchReply,
        normalizeAssistantLiterature, serializeAssistantLiterature, normalizeAssistantProposalChanges, serializeAssistantProposalChanges,
        normalizeAssistantReportChanges, reportContext, () => null, (value) => value, 8000);
    return {
        ...methods, ...researchAssistantReview(),
        draft: '', messages: [], isLoading: false, sendInProgress: false, retryAfter: 0,
        requestSequence: 0, nextMessageId: 1, proposalDraftId: 99, currentConversationId: null,
        contextOptions: [{ id: 12, label: 'Water study' }], contextEnabled: true, selectedContextId: 12,
        contextOptionsLoading: false, contextOptionsError: '', contextOptionsLoaded: true,
        paperContext: { paperSlug: 'detailed-proposal' }, activePaperField: { field: 'general_objective' },
        retryContext: null, error: '', historySavePromise: null, documentRequestSequence: 0,
        historyUrl: () => '/history', historyConversationUrl: (id) => `/history/${id}`,
        hasConversation() { return this.messages.some((message) => message.role === 'user'); },
        currentPageScope: () => 'proposal', formContext: () => ({ values: [{ field: 'general_objective', value: 'Unrelated page draft' }] }),
        scrollToLatest() {}, resetComposers() {}, upsertHistory() {}, syncPageScroll() {},
        async loadContextOptions() { return true; }, resetContextOptions() {},
        openWorkspace() { this.workspaceOpen = true; this.drawerOpen = false; },
        setError(title, message) { this.errorTitle = title; this.error = message; },
        ...overrides,
    };
}

function reviewItem(context = { topic_id: 12, workflow_scope: 'review' }, focus = 'consistency') {
    return {
        prompt: 'Check the proposal against its work plan and budget.', context,
        action: { type: 'review_proposal', review_focus: focus },
    };
}

globalThis.HTMLElement = class {};
globalThis.document = { activeElement: null, body: { dataset: { activeWorkspace: 'faculty' } } };

test('suggested prompts preserve an unfinished composer message', async () => {
    const store = assistant(async () => assert.fail('An unfinished message must not be sent by another action'));
    store.draft = 'My unfinished question about the project';
    await store.sendPrompt('Compare these journal recommendations.');
    assert.equal(store.draft, 'My unfinished question about the project');
    assert.deepEqual(store.messages, []);
    assert.equal(store.errorTitle, 'Finish your current message');
});

test('suggested prompts cannot replace the composer while another operation blocks sending', async () => {
    for (const state of [{ isLoading: true }, { sendInProgress: true }, { retryAfter: 20 },
        { documentAnalysisPending: true }, { disseminationSaving: true },
        { proposalChangesOperationPending: true }, { reportChangesOperationPending: true }]) {
        const store = assistant(async () => assert.fail('No request should start'), state);
        await store.sendPrompt('Compare these journal recommendations.');
        assert.equal(store.draft, '');
        assert.deepEqual(store.messages, []);
    }
});

test('journal comparison opens the assistant and preserves an unfinished question', async context => {
    const previousWindow = globalThis.window;
    const store = assistant(async () => assert.fail('The unfinished question must remain unsent'), {
        openDrawer() { this.drawerOpen = true; },
        proposalChangesBusy() { return false; },
    });
    store.draft = 'My unfinished question';
    globalThis.window = { Alpine: { store: () => store } };
    context.after(() => { globalThis.window = previousWindow; });
    const finder = journalFinder({ query: 'Coastal monitoring' });
    finder.results = [{ name: 'Coastal Research', fit_score: 80 }];
    await finder.askAthena();
    assert.equal(store.draft, 'My unfinished question');
    assert.equal(store.drawerOpen, true);
    assert.equal(store.messages.length, 0);
});

test('journal comparison shows the generated answer when the composer is clear', async context => {
    const previousWindow = globalThis.window;
    const store = assistant(undefined, {
        openDrawer() { this.drawerOpen = true; },
        proposalChangesBusy() { return false; },
    });
    globalThis.window = { Alpine: { store: () => store } };
    context.after(() => { globalThis.window = previousWindow; });
    const finder = journalFinder({ query: 'Coastal monitoring' });
    finder.results = [{ name: 'Coastal Research', fit_score: 80 }];
    await finder.askAthena();
    assert.equal(store.drawerOpen, true);
    assert.match(store.messages[0].content, /Coastal Research/);
    assert.equal(store.messages[1].role, 'assistant');
    assert.equal(store.draft, '');
});

test('a review saves the previous conversation before starting a separate focused chat', async () => {
    const requests = [];
    const store = assistant(async (url, options) => {
        const payload = JSON.parse(options.body);
        requests.push({ url, payload });
        return response(url === '/chat'
            ? { reply: 'Objective 2 has no work-plan activity.', sources: [{ reference: '[ATHENA 1]', url: 'https://athena.test/topics/12#work-plan' }] }
            : { conversation: { id: payload.id || 9 } });
    });
    store.messages = [{ role: 'user', content: 'Earlier chat about another proposal.' }];
    store.currentConversationId = 7;
    const trigger = new HTMLElement();

    assert.equal(await store.startSuggestedReview(reviewItem(), trigger), true);
    assert.equal(requests[0].payload.id, 7);
    assert.equal(requests[0].payload.messages[0].content, 'Earlier chat about another proposal.');
    assert.equal(requests[1].payload.id, null, 'The review gets a new conversation');
    const chat = requests.find((request) => request.url === '/chat').payload;
    assert.deepEqual(chat.context, { topic_id: 12, workflow_scope: 'review' });
    assert.deepEqual(chat.action, { type: 'review_proposal', review_focus: 'consistency' });
    assert.equal(chat.messages.length, 1);
    assert.equal(chat.messages[0].content, reviewItem().prompt);
    assert.equal(store.workspaceOpen, true);
    assert.equal(store.returnFocusElement, trigger);
    assert.equal(store.messages[1].sources[0].url, 'https://athena.test/topics/12#work-plan');
    assert.deepEqual(requests.at(-1).payload.context, { topic_id: 12, workflow_scope: 'review', review_focus: 'consistency', review_label: 'Water study' });
});

test('failed chat saving and unsent composer text remain intact', async () => {
    for (const overrides of [
        { saveConversation: async () => null },
        { draft: 'Unsent text I am still writing.' },
    ]) {
        let sends = 0;
        const store = assistant(undefined, { ...overrides, sendPrompt: async () => sends++ });
        store.messages = [{ role: 'user', content: 'Earlier unsaved conversation.' }];
        store.currentConversationId = 7;
        assert.equal(await store.startSuggestedReview(reviewItem()), false);
        assert.equal(store.messages[0].content, 'Earlier unsaved conversation.');
        assert.equal(store.currentConversationId, 7);
        assert.equal(store.draft, overrides.draft || '');
        assert.equal(sends, 0);
        assert.equal(store.reviewContext, null);
    }
});

test('invalid review targets and busy or retry states cannot start another task', async () => {
    let sends = 0;
    for (const item of [
        null, reviewItem({ workflow_scope: 'review' }), reviewItem({ topic_id: -1, workflow_scope: 'review' }),
        reviewItem({ topic_id: 1.5, workflow_scope: 'review' }), reviewItem({ topic_id: 12, proposal_draft_id: 9, workflow_scope: 'review' }),
        reviewItem({ topic_id: 12, workflow_scope: 'proposal' }), reviewItem(undefined, 'unrecognized'),
        { ...reviewItem(), prompt: ' ' }, { ...reviewItem(), action: { type: 'search_literature' } },
    ]) {
        const store = assistant(undefined, { sendPrompt: async () => sends++ });
        assert.equal(await store.startSuggestedReview(item), false);
        assert.equal(store.reviewContext, null);
        assert.equal(store.workspaceOpen, undefined);
    }
    for (const state of [{ isLoading: true }, { sendInProgress: true }, { retryAfter: 2 },
        { reviewLaunchPending: true }, { literatureOperationPending: true }]) {
        const store = assistant(undefined, { ...state, sendPrompt: async () => sends++ });
        assert.equal(await store.startSuggestedReview(reviewItem()), false);
    }
    assert.equal(sends, 0);
});

test('the launch lock covers saving before the chat request begins', async () => {
    let finishSave;
    let sends = 0;
    const store = assistant(undefined, {
        saveConversation: () => new Promise((resolve) => { finishSave = resolve; }),
        sendPrompt: async () => sends++,
    });
    const first = store.startSuggestedReview(reviewItem());
    assert.equal(await store.startSuggestedReview(reviewItem()), false);
    finishSave({ id: 7 });
    assert.equal(await first, true);
    assert.equal(sends, 1);
    assert.equal(store.reviewLaunchPending, false);
});

test('typing or clearing a chat while a review is saving cancels the launch without losing text', async () => {
    for (const change of ['typing', 'clearing']) {
        let finishSave;
        let sends = 0;
        const store = assistant(undefined, {
            saveConversation: () => new Promise((resolve) => { finishSave = resolve; }),
            sendPrompt: async () => sends++,
        });
        const pending = store.startSuggestedReview(reviewItem());
        if (change === 'typing') {
            store.draft = 'Text typed while the chat was saving.';
            assert.equal(await store.send(), undefined);
            assert.equal(store.messages.length, 0);
        } else {
            store.clearConversation();
        }
        finishSave({ id: 7 });
        assert.equal(await pending, false);
        assert.equal(sends, 0);
        assert.equal(store.draft, change === 'typing' ? 'Text typed while the chat was saving.' : '');
        assert.equal(store.reviewLaunchPending, false);
    }
});

test('draft reviews use the originating draft without leaking a different page’s live form', async () => {
    const calls = [];
    const store = assistant(undefined, { sendPrompt: async (...args) => calls.push(args) });
    const context = { proposal_draft_id: 42, workflow_scope: 'review' };
    assert.equal(await store.startSuggestedReview(reviewItem(context, 'revision_response')), true);
    assert.deepEqual(calls[0][2], context);
    assert.deepEqual(store.replyContext(context), { ...context, assistance_mode: 'discussion' });
    assert.equal(store.contextEnabled, false);
    assert.equal(store.selectedContextId, 0);
    assert.equal(store.proposalDraftId, 99, 'The current page draft keeps its identity');
    assert.equal(store.hasPaperContext(), false, 'The current-page paper chip cannot imply the wrong review target');
    assert.deepEqual(store.contextPayload(), { ...context, review_focus: 'revision_response', review_label: 'Proposal draft #42' });
});

test('follow-ups and retries keep the proposal target without repeating a full review', async () => {
    const calls = [];
    let status = 200;
    const store = assistant(async (url, options) => {
        const payload = JSON.parse(options.body);
        if (url === '/chat') calls.push(payload);
        return response(url === '/chat' ? { reply: 'A concrete correction.', message: 'Try again.' } : { conversation: { id: 8 } }, url === '/chat' ? status : 200);
    });
    await store.startSuggestedReview(reviewItem({ topic_id: 24, workflow_scope: 'review' }, 'revision_response'));
    store.draft = 'Which reviewer response needs the most work?';
    status = 503;
    await store.send();
    assert.deepEqual(calls[1].context, { topic_id: 24, workflow_scope: 'review', assistance_mode: 'discussion', review_label: 'Project #24' });
    assert.equal(calls[1].action, null);
    status = 200;
    await store.retry();
    assert.deepEqual(calls[2].context, calls[1].context);
    assert.deepEqual(calls[2].action, calls[1].action);
    assert.equal(store.reviewContext.topic_id, 24, 'Targets outside the recent context list still retain their authorized selection');
    assert.equal(store.selectedContextId, 24);
});

test('faculty proposal help opens safe choices without calling the model', async () => {
    for (const context of [{ topic_id: 24, workflow_scope: 'review' }, { proposal_draft_id: 42, workflow_scope: 'review' }]) {
        const requests = [];
        const store = assistant(async (url, options) => {
            requests.push({ url, payload: JSON.parse(options.body) });
            return response({ conversation: { id: 7 } });
        });
        store.messages = [{ role: 'user', content: 'Earlier chat about another proposal.' }];
        store.currentConversationId = 7;
        const trigger = new HTMLElement();
        const item = { ...reviewItem(context, context.topic_id ? 'revision_response' : 'consistency'),
            target_label: 'Water quality "2026" & farmers’ research <img src=x onerror=alert(1)>' };
        assert.equal(await store.openProposalHelp(item, trigger), true);
        assert.equal(requests.filter(request => request.url === '/chat').length, 0,
            'Opening the proposal choices must not consume an AI request');
        assert.equal(requests[0].payload.messages[0].content, 'Earlier chat about another proposal.');
        assert.equal(store.proposalHelpOpen, true);
        assert.equal(store.workspaceOpen, true);
        assert.equal(store.returnFocusElement, trigger);
        assert.deepEqual(store.messages, []);
        assert.equal(store.reviewContext.topic_id || store.reviewContext.proposal_draft_id,
            context.topic_id || context.proposal_draft_id);
        assert.equal(store.reviewTargetLabel(), item.target_label);
        assert.equal(store.hasPaperContext(), false, 'Unrelated live form fields are excluded');
        assert.deepEqual(store.proposalHelpOptions().map(option => option.label), context.topic_id
            ? ['Plan my revisions', 'Explain reviewer feedback', 'Help write a response', 'Compare saved changes']
            : ['Suggest proposal changes', 'Strengthen my proposal', 'Review objectives and methods', 'Compare proposal papers']);
    }
});

test('proposal help choices send only the selected saved target and the chosen assistance mode', async () => {
    for (const [label, mode, context, focus] of [
        ['Plan my revisions', 'revision_plan', { topic_id: 24, workflow_scope: 'review' }, 'revision_response'],
        ['Explain reviewer feedback', 'feedback_explanation', { topic_id: 24, workflow_scope: 'review' }, 'revision_response'],
        ['Help write a response', 'response_draft', { topic_id: 24, workflow_scope: 'review' }, 'revision_response'],
        ['Compare saved changes', null, { topic_id: 24, workflow_scope: 'review' }, 'revision_response'],
        ['Strengthen my proposal', 'draft_guidance', { proposal_draft_id: 42, workflow_scope: 'review' }, 'consistency'],
        ['Review objectives and methods', 'draft_guidance', { proposal_draft_id: 42, workflow_scope: 'review' }, 'consistency'],
        ['Compare proposal papers', null, { proposal_draft_id: 42, workflow_scope: 'review' }, 'consistency'],
    ]) {
        const requests = [];
        const store = assistant(async (url, options) => {
            requests.push({ url, payload: JSON.parse(options.body) });
            return response(url === '/chat' ? { reply: 'Here is a concrete next step using the saved proposal.' }
                : { conversation: { id: 8 } });
        });
        await store.openProposalHelp({ ...reviewItem(context, focus), target_label: 'Water study' });
        const option = store.proposalHelpOptions().find(option => option.label === label);
        assert.ok(option, `Missing proposal help option: ${label}`);
        await store.startProposalHelp(option);
        const chat = requests.find(request => request.url === '/chat').payload;
        assert.deepEqual(chat.context, mode ? { ...context, assistance_mode: mode, review_label: 'Water study' }
            : { ...context, review_focus: focus, review_label: 'Water study' });
        assert.deepEqual(chat.action, mode ? null : { type: 'review_proposal', review_focus: focus });
        assert.equal(store.proposalHelpOpen, false);
        assert.equal(chat.messages.length, 1, 'The selected help begins a clean proposal-bound conversation');
        assert.ok(chat.messages[0].content.trim());
        assert.ok(!JSON.stringify(chat).includes('Unrelated page draft'));
    }
});

test('proposal help follow-ups, retry, and restored history remain ordinary conversations on the exact saved target', async () => {
    const requests = [];
    let status = 200;
    let savedContext;
    let savedMessages;
    const store = assistant(async (url, options) => {
        if (!options?.body) return response({ conversation: { id: 8, context: savedContext, messages: savedMessages } });
        const payload = JSON.parse(options.body);
        requests.push({ url, payload });
        if (url === '/chat') return response({ reply: 'Draft a response with the saved reviewer comment as its basis.', message: 'Try again.' }, status);
        savedContext = payload.context;
        savedMessages = payload.messages;
        return response({ conversation: { id: 8 } });
    });
    await store.openProposalHelp({ ...reviewItem({ proposal_draft_id: 42, workflow_scope: 'review' }, 'consistency'),
        target_label: 'Community water study' });
    await store.startProposalHelp(store.proposalHelpOptions().find(option => option.label === 'Strengthen my proposal'));
    store.draft = 'Can you explain the second point in simpler terms?';
    status = 503;
    await store.send();
    const failedRequest = requests.filter(request => request.url === '/chat').at(-1).payload;
    assert.equal(failedRequest.action, null);
    assert.deepEqual(failedRequest.context, { proposal_draft_id: 42, workflow_scope: 'review',
        review_label: 'Community water study', assistance_mode: 'discussion' });
    status = 200;
    await store.retry();
    assert.deepEqual(requests.filter(request => request.url === '/chat').at(-1).payload, failedRequest,
        'Retry repeats the same target and conversational mode');
    const historyContext = structuredClone(savedContext);
    store.messages = [];
    await store.openConversation(8);
    assert.equal(store.reviewTargetLabel(), 'Community water study');
    assert.equal(store.reviewContext.proposal_draft_id, 42);
    assert.equal(store.reviewContext.assistance_mode, historyContext.assistance_mode);
    assert.equal(store.proposalHelpOpen, false, 'Opening saved history does not restart a choice or model call');
    store.draft = 'Help me write one concise sentence.';
    await store.send();
    const followUp = requests.filter(request => request.url === '/chat').at(-1).payload;
    assert.equal(followUp.action, null);
    assert.equal(followUp.context.proposal_draft_id, 42);
    assert.equal(followUp.context.assistance_mode, 'discussion');
});

test('opening history restores review focus and clearing or changing project releases it', async () => {
    const context = { proposal_draft_id: 42, workflow_scope: 'review', review_focus: 'revision_response', review_label: 'Community water study' };
    const store = assistant(async () => response({ conversation: { id: 15, context,
        messages: [{ role: 'user', content: 'Review my responses.' }, { role: 'assistant', content: 'Response 1 needs a page reference.' }] } }));
    await store.openConversation(15);
    assert.deepEqual(store.contextPayload(), context);
    assert.equal(store.reviewTargetLabel(), 'Community water study');
    assert.equal(store.reviewFocusLabel(), 'Revision response review');
    assert.equal(store.contextEnabled, false);
    store.clearConversation();
    assert.equal(store.reviewContext, null);
    store.restoreReviewContext({ topic_id: 12, workflow_scope: 'review', review_focus: 'consistency' });
    store.selectProjectContext(12);
    assert.equal(store.reviewContext, null);
    store.restoreReviewContext({ topic_id: 24, workflow_scope: 'review', review_focus: 'consistency' });
    store.openDrawer = () => {};
    await store.openWithContext(12);
    assert.equal(store.reviewContext, null);
    assert.equal(store.selectedContextId, 12);
});

test('an incomplete structured review stays retryable without saving a failed assistant answer', async () => {
    const requests = [];
    let failed = true;
    const store = assistant(async (url, options) => {
        const payload = JSON.parse(options.body);
        requests.push({ url, payload });
        if (url !== '/chat') return response({ conversation: { id: 8 } });
        return failed
            ? response({ message: 'Athena could not complete this review. Please retry.', sources: Array(11).fill({ reference: 'Unused guide' }) }, 502)
            : response({ reply: 'The sampling schedule still needs alignment.', sources: [],
                review: { summary: 'Align the sampling schedule.', version_number: 2, findings: [] } });
    });
    await store.startSuggestedReview(reviewItem({ topic_id: 24, workflow_scope: 'review' }, 'revision_response'));
    assert.match(store.error, /Please retry/);
    assert.equal(store.messages.filter(message => message.role === 'assistant').length, 0);
    const saved = requests.filter(request => request.url === '/history').at(-1).payload.messages;
    assert.equal(saved.filter(message => message.role === 'assistant').length, 0);
    failed = false;
    await store.retry();
    const chats = requests.filter(request => request.url === '/chat');
    assert.deepEqual(chats[1].payload.context, chats[0].payload.context);
    assert.deepEqual(chats[1].payload.action, chats[0].payload.action);
    assert.equal(store.messages.filter(message => message.role === 'assistant').length, 1);
    assert.equal(store.messages.at(-1).review.version_number, 2);
    assert.equal(store.error, '');
});

test('review starters require a proposal and use the selected project ahead of an unrelated page draft', () => {
    const store = assistant();
    const starters = store.reviewStarterPrompts();
    assert.deepEqual(starters.map((item) => item.label), ['Check proposal consistency', 'Check revision responses']);
    assert.deepEqual(starters.map((item) => item.context.topic_id), [12, 12]);
    store.contextEnabled = false;
    assert.equal(store.reviewStarterPrompts()[0].context.proposal_draft_id, 99);
    store.proposalDraftId = 0;
    assert.deepEqual(store.reviewStarterPrompts(), []);
});

test('research head assistant offers no proposal evaluation shortcuts after workspace changes or restoring a review', () => {
    const store = assistant();
    try {
        document.body.dataset.activeWorkspace = 'research_head';
        assert.deepEqual(store.reviewStarterPrompts(), []);
        store.restoreReviewContext({ topic_id: 12, workflow_scope: 'review', review_focus: 'revision_response' });
        assert.deepEqual(store.reviewStarterPrompts(), []);
        assert.deepEqual(store.proposalHelpOptions(), []);
        document.body.dataset.activeWorkspace = 'faculty';
        assert.equal(store.reviewStarterPrompts().length, 2);
        assert.ok(store.proposalHelpOptions().length > 0);
    } finally {
        document.body.dataset.activeWorkspace = 'faculty';
    }
});

test('review targets retain safe display titles and have visible fallbacks outside project options', async () => {
    const store = assistant(undefined, { sendPrompt: async () => null });
    await store.startSuggestedReview({ ...reviewItem({ topic_id: 24, workflow_scope: 'review' }), target_label: 'Research <img src=x onerror=alert(1)> title' });
    assert.equal(store.reviewTargetLabel(), 'Research <img src=x onerror=alert(1)> title');
    assert.equal(store.selectedContext().label, store.reviewTargetLabel());
    assert.equal(store.contextPayload().review_label, store.reviewTargetLabel());
    store.restoreReviewContext({ proposal_draft_id: 42, workflow_scope: 'review', review_focus: 'revision_response' });
    assert.equal(store.reviewTargetLabel(), 'Proposal draft #42');
    store.restoreReviewContext({ topic_id: 24, workflow_scope: 'review' });
    assert.equal(store.reviewTargetLabel(), 'Project #24');
    store.restoreReviewContext({ topic_id: 24, workflow_scope: 'review', review_label: 'A'.repeat(400) });
    assert.equal(store.reviewTargetLabel().length, 300);
});

test('structured reviews survive replies, saved history, restoration, copying, and text export', async () => {
    const review = {
        summary: 'The work plan does not yet evidence the second objective.',
        record_basis: 'latest_submitted_version', version_number: 3, reviewed_at: '2026-10-13T04:00:00Z',
        papers: [{ reference: 'Attachment A: Work Plan', title: 'Attachment A: Work Plan (submission v3)',
            url: 'https://athena.test/proposals/24/work-plan', coverage: 'saved_structured_fields',
            omitted_fields: 2, truncated_fields: ['Research design'] }],
        findings: [{ title: 'Objective 2 has no corresponding activity', status: 'not_evidenced',
            explanation: 'The saved work plan contains only coastal survey activities.',
            next_edit: 'Add the survey analysis activity and its schedule.',
            evidence: [{ reference: 'Attachment A: Work Plan', location: 'Activity 1',
                quote: 'Quarterly coastal transects', url: 'https://athena.test/proposals/24/work-plan' }] }],
        limitations: ['Uploaded PDF content was not read.'],
    };
    const reply = '## Review summary\nThe work plan does not yet evidence the second objective.\n\n'
        + '## Next edit\nAdd the survey analysis activity and its schedule.';
    const saves = [];
    const store = assistant(async (url, options) => {
        if (url === '/chat') return response({ reply, review, sources: [] });
        if (options?.method === 'POST') {
            saves.push(JSON.parse(options.body));
            return response({ conversation: { id: 15 } });
        }
        return response({ conversation: { id: 15,
            context: { topic_id: 24, workflow_scope: 'review', review_focus: 'consistency', review_label: 'Water study' },
            messages: [{ role: 'user', content: 'Check my proposal.' }, { role: 'assistant', content: reply, review }] } });
    });
    store.messages = [{ id: 1, role: 'user', content: 'Check my proposal.' }];
    store.restoreReviewContext({ topic_id: 24, workflow_scope: 'review', review_focus: 'consistency' });
    await store.requestReply({ type: 'review_proposal', review_focus: 'consistency' },
        { topic_id: 24, workflow_scope: 'review' });
    assert.deepEqual(store.messages.at(-1).review, review);
    assert.deepEqual(saves.at(-1).messages.at(-1).review, review);

    store.messages = [];
    await store.openConversation(15);
    assert.deepEqual(store.messages.at(-1).review, review);
    await store.saveConversation();
    assert.deepEqual(saves.at(-1).messages.at(-1).review, review);

    let copied = '';
    let exported = '';
    let downloaded = '';
    const transcriptMethods = new Function('navigator', 'window', 'document', 'Blob', 'URL',
        `return ({${['transcriptText', 'copyConversation', 'exportConversation'].map(methodSource).join('\n')}});`)(
        { clipboard: { writeText: async text => { copied = text; } } }, { setTimeout() {} },
        { body: { appendChild() {} }, createElement: () => ({ click() { downloaded = this.download; }, remove() {} }) },
        class { constructor(parts) { exported = parts.join(''); } },
        { createObjectURL: () => 'blob:review-transcript', revokeObjectURL() {} },
    );
    Object.assign(store, transcriptMethods);
    await store.copyConversation();
    store.exportConversation();
    assert.match(copied, /The work plan does not yet evidence the second objective/);
    assert.match(copied, /Add the survey analysis activity and its schedule/);
    assert.equal(exported, copied);
    assert.match(downloaded, /^athena-research-assistant-.*\.txt$/);
    assert.ok(!copied.includes('[object Object]'));
    assert.equal(store.error, '');
});

test('review starters launch through the actual welcome and menu controls in light, dark, mobile, and desktop layouts', async () => {
    const fixture = readFileSync(resolve('storage/framework/testing/research-assistant.html'), 'utf8');
    const { welcome, menu } = JSON.parse(execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo json_encode([
            'welcome' => Illuminate\Support\Facades\Blade::render('<x-research-assistant-welcome />'),
            'menu' => Illuminate\Support\Facades\Blade::render('<x-research-assistant-menu id="test" />'),
        ]);
    `], { encoding: 'utf8' }));
    const template = welcome.match(/<template x-for="item[\s\S]*?<\/template>/)[0];
    const menuTemplate = menu.match(/<template x-for="review[\s\S]*?<\/template>/)[0];
    const composer = readFileSync(resolve('resources/views/components/research-assistant-composer.blade.php'), 'utf8');
    const targetChip = composer.match(/<div data-assistant-review-target[\s\S]*?<\/div>/)[0];
    const helpers = readFileSync(resolve('resources/js/research-assistant-review.js'), 'utf8').replace(/^export /gm, '');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const content = await page.evaluate(({ fixture, template }) => {
                    const parsed = new DOMParser().parseFromString(fixture, 'text/html');
                    const existing = parsed.querySelector('template[x-for="item in $store.researchAssistant.starterPrompts()"]');
                    const welcome = existing.parentElement.parentElement;
                    existing.outerHTML = template;
                    return welcome.outerHTML;
                }, { fixture, template });
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}[x-cloak]{display:none!important}</style><body class="bg-white dark:bg-slate-900" x-data><main>${content}</main><div x-data="{open:true}" class="mx-auto max-w-3xl p-4"><button x-ref="trigger" type="button">Assistant menu</button>${menuTemplate}${targetChip}</div></body></html>`);
                await page.addScriptTag({ content: `${helpers}
                    window.reviewCalls = [];
                    document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
                        ...researchAssistantReview(), draft: '', messages: [], contextEnabled: true, selectedContextId: 12,
                        proposalDraftId: 0, isLoading: false, sendInProgress: false, retryAfter: 0,
                        hasConversation() { return this.messages.length > 0; },
                        hasContextOptions() { return false; },
                        starterPrompts() { return this.reviewStarterPrompts(); }, hasStarterPrompts() { return true; },
                        starterPromptHeading() { return 'Let’s move your research forward.'; },
                        starterPromptDescription() { return 'Pick a starting point below, or ask a question of your own.'; },
                        openWorkspace() { this.workspaceOpen = true; }, async saveConversation() { return { id: 8 }; },
                        clearConversation() { this.messages = []; }, selectProjectContext() {}, setError() {},
                        async sendPrompt(...args) { window.reviewCalls.push(args); await new Promise(resolve => window.finishReview = resolve); },
                    }));` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                const starter = page.getByRole('button', { name: 'Check proposal consistency', exact: false }).first();
                await expect(starter).toBeVisible();
                await expect(starter.locator('[data-assistant-icon]')).toBeVisible();
                await starter.focus();
                await page.keyboard.press('Enter');
                await expect.poll(() => page.evaluate(() => window.reviewCalls.length)).toBe(1);
                await expect(starter).toBeDisabled();
                const first = await page.evaluate(() => window.reviewCalls[0]);
                assert.deepEqual(first[1], { type: 'review_proposal', review_focus: 'consistency' });
                assert.equal(first[2].topic_id, 12);
                assert.equal(await page.evaluate(() => Alpine.store('researchAssistant').workspaceOpen), true);
                await expect(page.locator('[data-assistant-review-target]')).toContainText('Project #12');
                await expect(page.locator('[data-assistant-review-target]')).toContainText('Proposal consistency review');
                await page.evaluate(() => window.finishReview());
                await expect(starter).toBeEnabled();
                const menuAction = page.getByRole('button', { name: 'Check revision responses', exact: true });
                await expect(menuAction.locator('[data-assistant-icon]')).toBeVisible();
                await menuAction.click();
                await expect.poll(() => page.evaluate(() => window.reviewCalls.length)).toBe(2);
                assert.equal(await page.evaluate(() => window.reviewCalls[1][1].review_focus), 'revision_response');
                await expect(page.locator('[data-assistant-review-target]')).toContainText('Revision response review');
                await page.evaluate(() => Alpine.store('researchAssistant').restoreReviewContext({
                    proposal_draft_id: 42, workflow_scope: 'review', review_focus: 'revision_response',
                    review_label: 'Community water quality <img src=x onerror=alert(1)> title restored from history',
                }));
                await expect(page.locator('[data-assistant-review-target]')).toContainText('Community water quality <img src=x onerror=alert(1)> title restored from history');
                assert.equal(await page.locator('[data-assistant-review-target] img').count(), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.evaluate(() => window.finishReview());
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('dashboard suggestion buttons preserve quoted titles and launch the exact proposal review in the workspace', async () => {
    const component = readFileSync(resolve('resources/views/components/dashboard-assistant-action.blade.php'), 'utf8');
    const buttonTemplate = component.match(/<button[\s\S]*?<\/button>/)[0];
    const composer = readFileSync(resolve('resources/views/components/research-assistant-composer.blade.php'), 'utf8');
    const targetChip = composer.match(/<div data-assistant-review-target[\s\S]*?<\/div>/)[0];
    const helpers = readFileSync(resolve('resources/js/research-assistant-review.js'), 'utf8').replace(/^export /gm, '');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const escape = (value) => String(value).replace(/[&<>"']/g,
        (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const [context, focus] of [
            [{ topic_id: 24, workflow_scope: 'review' }, 'consistency'],
            [{ proposal_draft_id: 42, workflow_scope: 'review' }, 'revision_response'],
        ]) {
            const item = {
                ...reviewItem(context, focus), target_label: 'Water quality "2026" & farmers’ research',
                label: focus === 'consistency' ? 'Check proposal consistency' : 'Check revision responses',
            };
            const button = buttonTemplate
                .replace("{{ Illuminate\\Support\\Js::encode($item) }}", escape(JSON.stringify(item)))
                .replace("{{ $help ? 'openProposalHelp' : 'startSuggestedReview' }}", 'startSuggestedReview')
                .replaceAll('{{ $actionLabel }}', escape(item.label))
                .replaceAll("{{ $item['target_label'] }}", escape(item.target_label));
            const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.setContent(`<html><style>${styles.css}[x-cloak]{display:none!important}</style><body x-data class="p-4"><section data-dashboard-assistant-suggestions>${button}</section><section id="athena-assistant-workspace" x-show="$store.researchAssistant.workspaceOpen" x-cloak>${targetChip}</section></body></html>`);
            await page.addScriptTag({ content: `${helpers}
                const researchAssistantMessageLimit = 8000;
                const currentPageReportContext = () => null;
                const reportContext = () => null;
                window.dashboardCalls = [];
                window.dashboardSaves = [];
                document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
                    ...({${methodsSource}}), ...researchAssistantReview(),
                    draft: '', messages: [{role:'user',content:'Previous chat to save'}], currentConversationId: 7,
                    contextOptions: [], contextEnabled: false, selectedContextId: 0, proposalDraftId: 99,
                    paperContext: null, activePaperField: null, isLoading: false, sendInProgress: false, retryAfter: 0,
                    requestSequence: 0, nextMessageId: 2, documentRequestSequence: 0,
                    hasConversation() { return this.messages.some(message => message.role === 'user'); },
                    openWorkspace() { this.workspaceOpen = true; },
                    async saveConversation() {
                        window.dashboardSaves.push({id:this.currentConversationId,messages:JSON.parse(JSON.stringify(this.messages)),context:this.contextPayload(false)});
                        this.currentConversationId ||= 11;
                        return {id:this.currentConversationId};
                    },
                    async requestReply(action, context) {
                        window.dashboardCalls.push({action,context:this.replyContext(context,action),messages:this.contextMessages()});
                        await new Promise(resolve => window.finishDashboardReview = resolve);
                    },
                    currentPageScope() { return 'proposal'; }, formContext() { return null; },
                    resetComposers() {}, scrollToLatest() {}, setError() {},
                }));` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            const control = page.getByRole('button', { name: `${item.label} for ${item.target_label} with Athena`, exact: true });
            await control.focus();
            await page.keyboard.press('Enter');
            await expect.poll(() => page.evaluate(() => window.dashboardCalls.length)).toBe(1);
            assert.equal(await control.getAttribute('aria-controls'), 'athena-assistant-workspace');
            await expect(page.locator('#athena-assistant-workspace')).toBeVisible();
            await expect(control).toBeDisabled();
            const call = await page.evaluate(() => window.dashboardCalls[0]);
            assert.deepEqual(call.action, item.action);
            assert.deepEqual(call.context, item.context);
            assert.deepEqual(call.messages, [{ role: 'user', content: item.prompt }]);
            const saves = await page.evaluate(() => window.dashboardSaves);
            assert.equal(saves[0].id, 7);
            assert.equal(saves[0].messages[0].content, 'Previous chat to save');
            assert.equal(saves[1].id, null);
            assert.equal(saves[1].context.review_label, item.target_label);
            await expect(page.locator('[data-assistant-review-target]')).toContainText(item.target_label);
            await expect(page.locator('[data-assistant-review-target]')).toContainText(focus === 'consistency'
                ? 'Proposal consistency review' : 'Revision response review');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            assert.deepEqual(errors, []);
            await page.evaluate(() => window.finishDashboardReview());
            await expect(control).toBeEnabled();
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
