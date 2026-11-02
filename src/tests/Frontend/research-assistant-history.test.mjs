import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import { researchAssistantHistory } from '../../resources/js/research-assistant-history.js';
import { serializeAssistantLiterature } from '../../resources/js/research-assistant-literature.js';
import { serializeAssistantProposalChanges } from '../../resources/js/research-assistant-proposal-changes.js';
import { normalizeAssistantReportChanges } from '../../resources/js/research-assistant-report-changes.js';

const app = readFileSync('resources/js/app.js', 'utf8');
const methods = ['conversationTranscript', 'saveConversation', 'retry', 'hasConversation', 'upsertHistory', 'setError', 'queueHistorySearch', 'openHistorySearch', 'closeHistorySearch']
    .map((name) => app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`))[0]).join('\n');
const response = (payload, status = 200) => ({ ok: status === 200, status, json: async () => payload });

function assistant(fetchHistory, browserWindow = {}) {
    const document = { querySelector: () => ({ content: 'csrf' }) };
    return {
        ...researchAssistantHistory([], fetchHistory),
        ...new Function('document', 'fetch', 'serializeAssistantLiterature', 'serializeAssistantProposalChanges', 'normalizeAssistantReportChanges', 'window',
            `return ({${methods}});`)(document, fetchHistory, serializeAssistantLiterature, serializeAssistantProposalChanges, normalizeAssistantReportChanges, browserWindow),
        messages: [{ role: 'user', content: 'How do I prepare a research proposal?' }],
        currentConversationId: null, historySavePromise: null,
        historySearchQuery: '', historySearchResults: [], error: '', errorTitle: '',
        historySearchSequence: 0,
        historyUrl: () => '/history', contextPayload: () => null,
        requestReply: () => assert.fail('Retrying a history save must not generate another AI reply'),
    };
}

for (const action of ['clear', 'close', 'reopen']) {
    test(`a late chatbot history search cannot replace the list after ${action}`, async () => {
        const timers = [];
        let finishSearch;
        const store = assistant(() => new Promise((resolve) => { finishSearch = resolve; }), {
            location: { origin: 'https://athena.test' },
            setTimeout(callback) { timers.push(callback); return timers.length; },
            clearTimeout() {},
        });
        store.history = [{ id: 7, title: 'Coastal research' }, { id: 8, title: 'Learning outcomes' }];
        store.historyLoaded = true;
        store.historySearchResults = store.history;
        store.historySearchOpen = true;
        store.historySearchQuery = 'coastal';
        store.queueHistorySearch();
        const pendingSearch = timers.shift()();

        if (action === 'close') {
            store.closeHistorySearch();
        } else if (action === 'reopen') {
            await store.openHistorySearch();
        } else {
            store.historySearchQuery = '';
            store.queueHistorySearch();
        }
        finishSearch(response({ conversations: [store.history[0]] }));
        await pendingSearch;

        assert.deepEqual(store.historySearchResults, store.history);
        assert.equal(store.historySearchLoading, false);
    });
}

test('a reply arriving during a pending save is persisted before a fresh page loads history', async () => {
    const requests = [];
    let finishFirstSave;
    let savedConversation;
    const fetchHistory = async (_url, options = {}) => {
        if (!options.body) return response({ conversations: [savedConversation] });
        const payload = JSON.parse(options.body);
        requests.push(payload);
        if (requests.length === 1) await new Promise((resolve) => { finishFirstSave = resolve; });
        savedConversation = { ...payload, id: 7, title: payload.messages[0].content };
        return response({ conversation: savedConversation });
    };
    const store = assistant(fetchHistory);
    const firstSave = store.saveConversation();
    store.messages.push({ role: 'assistant', content: 'Start with the project details and five proposal papers.' });
    const latestSave = store.saveConversation();
    finishFirstSave();
    await Promise.all([firstSave, latestSave]);

    const refreshed = assistant(fetchHistory);
    await refreshed.loadHistory();
    assert.equal(refreshed.history[0].messages.length, 2, 'The saved transcript includes the latest reply after refresh');
    assert.equal(requests.length, 2);
    assert.equal(requests[1].id, 7, 'The newer transcript updates the same chat');
});

test('simultaneous saves of the same transcript share one request', async () => {
    let finishSave;
    let requests = 0;
    const store = assistant(async () => {
        requests++;
        await new Promise((resolve) => { finishSave = resolve; });
        return response({ conversation: { id: 7 } });
    });
    const first = store.saveConversation();
    const second = store.saveConversation();
    finishSave();
    await Promise.all([first, second]);
    assert.equal(requests, 1);
});

test('a late save of an older chat does not attach a new chat to its conversation id', async () => {
    const requests = [];
    let finishSave;
    const store = assistant(async (_url, options) => {
        const payload = JSON.parse(options.body);
        requests.push(payload);
        if (requests.length === 1) await new Promise((resolve) => { finishSave = resolve; });
        return response({ conversation: { id: requests.length === 1 ? 7 : 8 } });
    });
    const oldSave = store.saveConversation();
    store.messages = [{ role: 'user', content: 'A different research question.' }];
    store.currentConversationId = null;
    const newSave = store.saveConversation();
    finishSave();
    await Promise.all([oldSave, newSave]);
    assert.equal(requests[1].id, null, 'A new chat must create its own history entry');
    assert.equal(store.currentConversationId, 8);
});

test('Retry saves a failed chat without generating another reply and clears the save error', async () => {
    let requests = 0;
    const store = assistant(async () => ++requests === 1
        ? response({}, 500) : response({ conversation: { id: 7 } }));
    const messages = structuredClone(store.messages);
    await store.saveConversation();
    assert.equal(store.errorTitle, 'Chat not saved');
    assert.match(store.error, /before refreshing/i);
    await store.retry();
    assert.equal(requests, 2);
    assert.equal(store.currentConversationId, 7);
    assert.equal(store.error, '');
    assert.deepEqual(store.messages, messages);
});

test('session expiry and a redirected login page explain why a chat could not be saved', async () => {
    for (const failedResponse of [response({ message: 'CSRF token mismatch.' }, 419), response({}, 401),
        { ok: true, redirected: true, json: async () => { throw new SyntaxError('HTML login page'); } }]) {
        const store = assistant(async () => failedResponse);
        await store.saveConversation();
        assert.equal(store.errorTitle, 'Chat not saved');
        assert.match(store.error, /session.*expired/i);
        assert.match(store.error, /copy|export/i);
        assert.equal(store.messages.length, 1);
    }
});

test('the built chatbot retries saving and reopens the complete chat after a page refresh', async () => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const assets = new Set(Object.values(manifest).map((entry) => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    let saved;
    let failedSave = false;
    let replies = 0;
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    try {
        await page.route('http://athena-history.test/**', async (route) => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            if (path === 'chat') {
                replies++;
                return route.fulfill({ json: { reply: 'Prepare the project details and five proposal papers.', sources: [] } });
            }
            if (path === 'history' && route.request().method() === 'POST') {
                const payload = route.request().postDataJSON();
                if (payload.messages.length === 2 && !failedSave) {
                    failedSave = true;
                    return route.fulfill({ status: 500, json: { message: 'The save was interrupted.' } });
                }
                saved = { ...payload, id: 7, title: payload.messages[0].content };
                return route.fulfill({ json: { conversation: saved } });
            }
            if (path === 'history' && new URL(route.request().url()).searchParams.has('resume')) return route.fulfill({ json: { account_id: 1, workspace: 'faculty', conversation: saved || null } });
            if (path === 'history') return route.fulfill({ json: { conversations: saved ? [saved] : [] } });
            if (path === 'history/7') return route.fulfill({ json: { conversation: saved } });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="http://athena-history.test">
                <style>[x-cloak]{display:none!important}</style>
                <script>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};</script>
                <script type="module" src="/${manifest['resources/js/app.js'].file}"></script>
                </head><body x-data data-app-shell data-auth-user-id="1" data-active-workspace="faculty" data-research-assistant-history-url="/history" data-research-assistant-url="/chat">
                <button @click="$store.researchAssistant.openWorkspace()">Open full chat</button>
                <div x-show="$store.researchAssistant.workspaceOpen" x-cloak>
                    <template x-for="conversation in $store.researchAssistant.history" :key="conversation.id">
                        <button @click="$store.researchAssistant.openConversation(conversation.id)" x-text="conversation.title"></button>
                    </template>
                    <template x-for="message in $store.researchAssistant.messages" :key="message.id"><p x-text="message.content"></p></template>
                    <textarea id="research-assistant-message" x-model="$store.researchAssistant.draft" aria-label="Message"></textarea>
                    <button @click="$store.researchAssistant.send()">Send</button>
                    <div x-show="$store.researchAssistant.error" role="alert" x-cloak>
                        <p x-text="$store.researchAssistant.error"></p><button @click="$store.researchAssistant.retry()">Retry saving</button>
                    </div>
                </div></body></html>` });
        });
        await page.goto('http://athena-history.test/workspace');
        await page.getByRole('button', { name: 'Open full chat' }).click();
        await page.getByRole('textbox', { name: 'Message' }).fill('How do I prepare a research proposal?');
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await page.getByRole('alert').waitFor({ state: 'visible' });
        await page.getByRole('button', { name: 'Retry saving' }).click();
        await page.getByRole('alert').waitFor({ state: 'hidden' });
        assert.equal(saved.messages.length, 2);
        await page.reload();
        await page.getByRole('button', { name: 'Open full chat' }).click();
        await page.getByText('Prepare the project details and five proposal papers.', { exact: true }).waitFor({ timeout: 5000 });
        assert.equal(replies, 1, 'Retrying history does not request a duplicate AI response');
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});


function retentionBrowser() {
    const entries = new Map();
    return {
        document: { body: { dataset: { authUserId: '11', activeWorkspace: 'faculty' } } },
        location: { origin: 'https://athena.test' },
        sessionStorage: { getItem: key => entries.get(key) ?? null, setItem: (key, value) => entries.set(key, value) },
    };
}

function resumableAssistant(browser, fetcher, context = { proposal_draft_id: 67 }) {
    return {
        ...researchAssistantHistory([], fetcher, browser),
        context, messages: [], currentConversationId: null, conversationVersion: null,
        draft: '', drawerOpen: false, workspaceOpen: false, error: '', errorTitle: '',
        retryAction: null, retryContext: null,
        historyUrl: () => '/history', contextPayload() { return this.context; },
        hasConversation() { return this.messages.length > 0; },
        setError(title, message) { this.errorTitle = title; this.error = message; },
        hydrateConversation(conversation, preservePageContext) {
            this.messages = conversation.messages;
            this.currentConversationId = conversation.id;
            this.conversationVersion = conversation.version;
            if (!preservePageContext) this.context = conversation.context;
        },
    };
}

function resumedChat(id, context = { proposal_draft_id: 67 }, lastRole = 'assistant') {
    return { id, context, version: 'a'.repeat(64), messages: [
        { role: 'user', content: 'Help with this proposal.' },
        ...(lastRole === 'assistant' ? [{ role: 'assistant', content: `Saved answer ${id}.` }] : []),
    ] };
}

function resumeResponse(conversation, browser) {
    return response({ conversation, account_id: Number(browser.document.body.dataset.authUserId), workspace: browser.document.body.dataset.activeWorkspace });
}

test('saved chats restore automatically for the current project without making an AI request', async () => {
    const browser = retentionBrowser();
    const requests = [];
    const first = resumableAssistant(browser, async (url, options) => { requests.push({ url, options }); return resumeResponse(resumedChat(7), browser); });
    assert.equal(await first.restoreConversation(), true);
    assert.equal(first.currentConversationId, 7);
    assert.equal(first.messages[1].content, 'Saved answer 7.');
    first.draft = 'My unsent follow-up';
    first.drawerOpen = true;
    first.rememberConversation();
    const refreshed = resumableAssistant(browser, async url => { requests.push({ url }); return resumeResponse(resumedChat(7), browser); });
    refreshed.restoreConversationView();
    await refreshed.restoreConversation();
    assert.equal(refreshed.drawerOpen, true);
    assert.equal(refreshed.currentConversationId, 7);
    assert.equal(refreshed.draft, 'My unsent follow-up');
    const query = new URL(requests.at(-1).url).searchParams;
    assert.equal(query.get('context[proposal_draft_id]'), '67');
    assert.equal(query.get('conversation_id'), '7');
    assert.ok(requests.every(request => !request.options?.method || request.options.method === 'GET'));
});

test('each project and saved chat retains its own unsent input', async () => {
    const browser = retentionBrowser();
    const fetcher = async url => {
        const project = Number(new URL(url).searchParams.get('context[proposal_draft_id]'));
        return resumeResponse(resumedChat(project, { proposal_draft_id: project }), browser);
    };
    const first = resumableAssistant(browser, fetcher);
    await first.restoreConversation();
    first.draft = 'Mangrove follow-up'; first.rememberConversation();
    const second = resumableAssistant(browser, fetcher, { proposal_draft_id: 68 });
    await second.restoreConversation();
    assert.equal(second.draft, '');
    second.draft = 'Duck farming follow-up'; second.rememberConversation();
    const back = resumableAssistant(browser, fetcher);
    await back.restoreConversation();
    assert.equal(back.currentConversationId, 67);
    assert.equal(back.draft, 'Mangrove follow-up');
    back.currentConversationId = 70; back.draft = 'Another chat'; back.rememberConversation();
    back.currentConversationId = 67; back.restoreConversationDraft();
    assert.equal(back.draft, 'Mangrove follow-up');
});

test('a deliberate new chat stays empty after refresh and keeps the saved chat in history', async () => {
    const browser = retentionBrowser();
    const chat = resumedChat(7);
    const first = resumableAssistant(browser, async () => resumeResponse(chat, browser));
    await first.restoreConversation();
    first.currentConversationId = null; first.messages = []; first.conversationFresh = true;
    first.draft = 'A new question I have not sent'; first.rememberConversation();
    const refreshed = resumableAssistant(browser, () => assert.fail('A deliberate new chat must not reopen the previous conversation'));
    await refreshed.restoreConversation();
    assert.equal(refreshed.currentConversationId, null);
    assert.deepEqual(refreshed.messages, []);
    assert.equal(refreshed.draft, 'A new question I have not sent');
    assert.equal(chat.messages.length, 2);
});

test('a delayed restoration cannot replace a newly started chat', async () => {
    const browser = retentionBrowser();
    let finish;
    const store = resumableAssistant(browser, () => new Promise(resolve => { finish = resolve; }));
    const loading = store.restoreConversation();
    store.conversationRestoreSequence++;
    store.conversationReady = true; store.conversationFresh = true;
    store.draft = 'Keep this new draft'; store.rememberConversation();
    finish(resumeResponse(resumedChat(7), browser));
    await loading;
    assert.deepEqual(store.messages, []);
    assert.equal(store.draft, 'Keep this new draft');
});

test('resuming after an interrupted response keeps the question and waits for an explicit retry', async () => {
    const browser = retentionBrowser();
    const first = resumableAssistant(browser, async () => resumeResponse(resumedChat(7, { proposal_draft_id: 67 }, 'user'), browser));
    await first.restoreConversation();
    first.retryAction = { type: 'draft_rrl' };
    first.retryContext = { proposal_draft_id: 67, paper_slug: 'detailed-proposal' };
    first.rememberConversation();
    const refreshed = resumableAssistant(browser, async () => resumeResponse(resumedChat(7, { proposal_draft_id: 67 }, 'user'), browser));
    await refreshed.restoreConversation();
    assert.equal(refreshed.messages[0].content, 'Help with this proposal.');
    assert.equal(refreshed.errorTitle, 'Response interrupted');
    assert.deepEqual(refreshed.retryAction, { type: 'draft_rrl' });
    assert.equal(refreshed.retryContext.proposal_draft_id, 67);
});

test('browser storage failure still permits server restoration and accounts never share a retained selection', async () => {
    const browser = retentionBrowser();
    const first = resumableAssistant(browser, async () => resumeResponse(resumedChat(7), browser));
    await first.restoreConversation();
    first.draft = 'Private draft for account 11'; first.rememberConversation();
    browser.document.body.dataset.authUserId = '22';
    const second = resumableAssistant(browser, async url => {
        assert.equal(new URL(url).searchParams.get('conversation_id'), null);
        return resumeResponse(resumedChat(8), browser);
    });
    await second.restoreConversation();
    assert.equal(second.draft, '');
    Object.defineProperty(browser, 'sessionStorage', { get() { throw new Error('Storage disabled'); } });
    const blockedStorage = resumableAssistant(browser, async () => resumeResponse(resumedChat(8), browser));
    await blockedStorage.restoreConversation();
    assert.equal(blockedStorage.currentConversationId, 8);
    assert.equal(blockedStorage.error, '');
});


test('follow-up messages include the edited RRL paragraphs without treating them as paper evidence', () => {
    const method = app.match(/    contextMessages\(\) \{([\s\S]*?)\n    \},/)[0];
    const store = { ...new Function('researchAssistantMessageLimit', `return ({${method}});`)(8000), messages: [
        { role: 'user', content: 'Draft the RRL.' },
        { role: 'assistant', content: 'Review this draft.', literature: { kind: 'draft', drafts: [
            { paragraph: 'My edited paragraph about coastal flood forecasting (Rivera, 2025).', source_token: 'private-token-not-for-the-prompt' },
        ] } },
        { role: 'user', content: 'Make the paragraph easier to read.' },
    ] };
    const context = store.contextMessages();
    assert.match(context[1].content, /My edited paragraph/);
    assert.match(context[1].content, /conversation text|not source evidence/i);
    assert.doesNotMatch(context[1].content, /private-token-not-for-the-prompt/);
    store.messages[1].literature.drafts[0].paragraph = 'x'.repeat(20000);
    assert.ok(store.contextMessages()[1].content.length <= 8000);
});


async function retainedChatPage(run) {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    const state = { workspace: 'faculty', replies: [], saves: [], failReply: false, nextId: 30,
        records: new Map([67, 68].map((project, index) => [index + 7, {
            id: index + 7, title: `Question for project ${project}`, version: 'a'.repeat(64),
            context: { proposal_draft_id: project, workspace: 'faculty' }, context_label: `Project ${project}`,
            messages: [{ role: 'user', content: `Question for project ${project}` },
                { role: 'assistant', content: `Saved reply for project ${project}` }],
        }])) };
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.route('http://athena-retention.test/**', async route => {
            const url = new URL(route.request().url());
            const path = url.pathname.slice(1);
            if (assets.has(path)) return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            if (path === 'chat') {
                state.replies.push(route.request().postDataJSON());
                return route.fulfill(state.failReply ? { status: 503, json: { message: 'The reply was interrupted.' } }
                    : { json: { reply: 'Continued the saved conversation.', sources: [] } });
            }
            if (path === 'history' && route.request().method() === 'POST') {
                const payload = route.request().postDataJSON();
                state.saves.push({ workspace: state.workspace, payload });
                const record = { ...payload, id: payload.id || state.nextId++, version: 'b'.repeat(64),
                    context: { ...payload.context, workspace: state.workspace },
                    context_label: `Project ${payload.context?.proposal_draft_id || ''}`, title: payload.messages[0].content };
                state.records.set(record.id, record);
                return route.fulfill({ json: { conversation: record } });
            }
            if (path === 'history') {
                const project = Number(url.searchParams.get('context[proposal_draft_id]'));
                const records = [...state.records.values()].filter(record => record.context.workspace === state.workspace
                    && (!project || Number(record.context.proposal_draft_id) === project)).reverse();
                const preferred = Number(url.searchParams.get('conversation_id'));
                return route.fulfill({ json: url.searchParams.has('resume')
                    ? { account_id: 1, workspace: state.workspace, conversation: records.find(record => record.id === preferred) || records[0] || null }
                    : { conversations: records } });
            }
            if (path.startsWith('history/')) return route.fulfill({ json: { conversation: state.records.get(Number(path.slice(8))) } });
            const project = Number(url.pathname.split('/').at(-1));
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="http://athena-retention.test">
                <style>[x-cloak]{display:none!important}</style>
                <script>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};</script>
                <script type="module" src="/${manifest['resources/js/app.js'].file}"></script>
                </head><body x-data data-app-shell data-auth-user-id="1" data-active-workspace="${state.workspace}"
                    data-research-assistant-history-url="/history" data-research-assistant-url="/chat"
                    ${project ? `data-research-assistant-proposal-draft-id="${project}" data-research-assistant-paper-slug="detailed-proposal"` : ''}>
                <button @click="$store.researchAssistant.openDrawer()">Open floating chat</button>
                <button @click="$store.researchAssistant.openWorkspace()">Expand chat</button>
                <div x-show="$store.researchAssistant.drawerOpen || $store.researchAssistant.workspaceOpen" x-cloak>
                    <button @click="$store.researchAssistant.closeDrawer()">Close chat</button>
                    <button @click="$store.researchAssistant.newConversation()">New chat</button>
                    <template x-for="conversation in $store.researchAssistant.history" :key="conversation.id">
                        <button @click="$store.researchAssistant.openConversation(conversation.id)" x-text="conversation.title"></button>
                    </template>
                    <template x-for="message in $store.researchAssistant.messages" :key="message.id"><p data-chat-message x-text="message.content"></p></template>
                    <textarea x-model="$store.researchAssistant.draft" aria-label="Message" :disabled="$store.researchAssistant.historyRestoring"></textarea>
                    <button @click="$store.researchAssistant.send()" :disabled="!$store.researchAssistant.conversationReady">Send</button>
                    <div x-show="$store.researchAssistant.error" role="alert" x-cloak>
                        <p x-text="$store.researchAssistant.errorTitle"></p><p x-text="$store.researchAssistant.error"></p>
                        <button @click="$store.researchAssistant.retry()">Retry</button>
                    </div>
                </div></body></html>` });
        });
        await run(page, state);
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
}

test('the built floating chat resumes by project, keeps unsent text, and starts a separate saved chat on request', async () => {
    await retainedChatPage(async (page, state) => {
        await page.goto('http://athena-retention.test/project/67');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]').last()).toHaveText('Saved reply for project 67');
        await page.getByRole('textbox', { name: 'Message' }).fill('Unsent question for project 67');
        await page.getByRole('button', { name: 'Expand chat' }).click();
        await page.getByRole('button', { name: 'Close chat' }).click();
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.getByRole('textbox')).toHaveValue('Unsent question for project 67');
        await page.reload();
        await expect(page.getByRole('textbox')).toHaveValue('Unsent question for project 67');
        assert.equal(await page.evaluate(() => Alpine.store('researchAssistant').selectedContext().label), 'Project 67');
        assert.equal(state.replies.length, 0, 'Opening, expanding and refreshing never sends a prompt');

        await page.goto('http://athena-retention.test/project/68');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]').last()).toHaveText('Saved reply for project 68');
        await expect(page.getByRole('textbox')).toHaveValue('');
        await page.getByRole('textbox').fill('Unsent question for project 68');
        await page.goto('http://athena-retention.test/project/67');
        await expect(page.getByRole('textbox')).toHaveValue('Unsent question for project 67');
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await expect(page.locator('[data-chat-message]').last()).toHaveText('Continued the saved conversation.');
        assert.equal(state.replies[0].conversation_id, 7);
        assert.equal(state.replies[0].context.proposal_draft_id, 67);
        assert.ok(state.replies[0].messages.some(message => message.content === 'Saved reply for project 67'));
        await page.getByRole('textbox').fill('Keep this unsent text with the old chat');
        await page.getByRole('button', { name: 'New chat', exact: true }).click();
        await expect(page.locator('[data-chat-message]')).toHaveCount(0);
        await expect(page.getByRole('textbox')).toHaveValue('');
        await page.reload();
        await expect(page.locator('[data-chat-message]')).toHaveCount(0);
        await page.getByRole('button', { name: 'Question for project 67', exact: true }).click();
        await expect(page.getByRole('textbox')).toHaveValue('Keep this unsent text with the old chat');
        await expect(page.locator('[data-chat-message]')).toHaveCount(4);
        assert.equal(state.replies.length, 1);
    });
});

test('the built chat requires explicit retry after a refresh interrupts an RRL action', async () => {
    await retainedChatPage(async (page, state) => {
        await page.goto('http://athena-retention.test/project/67');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]')).toHaveCount(2);
        state.failReply = true;
        await page.evaluate(() => Alpine.store('researchAssistant').sendPrompt('Draft an RRL from my project.', { type: 'draft_rrl' }, { proposal_draft_id: 67 }));
        await page.reload();
        await expect(page.getByRole('alert')).toContainText('Response interrupted');
        assert.equal(state.replies.length, 1);
        state.failReply = false;
        await page.getByRole('button', { name: 'Retry', exact: true }).click();
        await expect(page.locator('[data-chat-message]').last()).toHaveText('Continued the saved conversation.');
        assert.equal(state.replies[1].action.type, 'draft_rrl');
        assert.equal(state.replies[1].conversation_id, 7);
        assert.equal(state.replies[1].context.proposal_draft_id, 67);
        assert.equal(state.replies[1].messages.filter(message => message.role === 'user' && message.content === 'Draft an RRL from my project.').length, 1);
    });
});

test('workspace changes clear the visible private chat before restoring the new workspace', async () => {
    await retainedChatPage(async (page, state) => {
        await page.goto('http://athena-retention.test/project/67');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]')).toHaveCount(2);
        await page.getByRole('textbox').fill('Faculty-only unsent question');
        state.workspace = 'research_head';
        await page.evaluate(() => {
            Alpine.store('researchAssistant').sourcePreview = { title: 'Private faculty source' };
            Alpine.store('researchAssistant').sourcePreviewOpen = true;
            document.body.dataset.activeWorkspace = 'research_head';
            document.dispatchEvent(new Event('athena:workspace-switched'));
        });
        await expect(page.locator('[data-chat-message]')).toHaveCount(0);
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.getByRole('textbox')).toHaveValue('');
        assert.equal(state.saves.filter(save => save.workspace === 'research_head').length, 0, 'The old chat must never be saved under the new workspace');
        assert.deepEqual(await page.evaluate(() => [Alpine.store('researchAssistant').sourcePreview, Alpine.store('researchAssistant').sourcePreviewOpen]), [null, false]);
        state.workspace = 'faculty';
        await page.evaluate(() => {
            document.body.dataset.activeWorkspace = 'faculty';
            document.dispatchEvent(new Event('athena:workspace-switched'));
        });
        await expect(page.locator('[data-chat-message]')).toHaveCount(2);
        await expect(page.getByRole('textbox')).toHaveValue('Faculty-only unsent question');
        assert.equal(state.replies.length, 0);
    });
});


test('soft navigation restores each projects conversation and its unfinished message', async () => {
    await retainedChatPage(async (page, state) => {
        await page.goto('http://athena-retention.test/project/67');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]')).toHaveCount(2);
        await page.getByRole('textbox').fill('Project 67 pending question');
        for (const project of [68, 67]) {
            await page.evaluate(project => {
                document.dispatchEvent(new Event('livewire:navigating'));
                document.body.dataset.researchAssistantProposalDraftId = String(project);
                document.dispatchEvent(new Event('livewire:navigated'));
            }, project);
            await expect(page.locator('[data-chat-message]').last()).toHaveText(`Saved reply for project ${project}`);
            await expect(page.getByRole('textbox')).toHaveValue(project === 67 ? 'Project 67 pending question' : '');
        }
        assert.equal(state.replies.length, 0);
        assert.ok(state.saves.every(save => save.payload.context.proposal_draft_id === 67 || save.payload.context.proposal_draft_id === 68));
    });
});


test('a fresh project chat opened from the general page stays fresh after refresh', async () => {
    await retainedChatPage(async (page, state) => {
        await page.goto('http://athena-retention.test/workspace');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]').last()).toHaveText('Saved reply for project 68');
        await page.getByRole('button', { name: 'New chat', exact: true }).click();
        await expect(page.locator('[data-chat-message]')).toHaveCount(0);
        await page.reload();
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.locator('[data-chat-message]')).toHaveCount(0);
        await page.getByRole('textbox').fill('A fresh question for this project');
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await expect(page.locator('[data-chat-message]').last()).toHaveText('Continued the saved conversation.');
        assert.equal(state.replies[0].context.proposal_draft_id, 68);
        assert.notEqual(state.replies[0].conversation_id, 8);
        assert.equal(state.records.get(8).messages.length, 2);
    });
});


test('Retry on a history loading error reopens that chosen chat without generating an AI reply', async () => {
    const store = assistant(async () => assert.fail('No chat generation or save is needed'));
    store.errorTitle = 'History unavailable';
    store.historyRetryConversationId = 15;
    let opened;
    store.openConversation = async id => { opened = id; return true; };
    assert.equal(await store.retry(), true);
    assert.equal(opened, 15);
});


test('the history limit preserves every saved message and carries the unfinished question to a fresh chat', async () => {
    for (const count of [99, 100]) {
        const method = app.match(/    async send\(action = null, contextOverride = null\) \{[\s\S]*?\n    \},/)[0];
        const store = { ...assistant(async () => assert.fail('A full chat must not send or save an oversized transcript')),
            ...new Function('researchAssistantMessageLimit', `return ({${method}});`)(8000),
            conversationReady: true, conversationAccountScope: '', draft: 'My unfinished question',
            messages: Array.from({ length: count }, (_, index) => ({ role: index % 2 ? 'assistant' : 'user', content: `Message ${index}` })),
        };
        await store.send();
        assert.equal(store.errorTitle, 'Chat is full');
        assert.equal(store.messages.length, count);
        assert.equal(store.draft, 'My unfinished question');
        store.newConversation = async function () { this.messages = []; this.draft = ''; return true; };
        await store.retry();
        assert.equal(store.messages.length, 0);
        assert.equal(store.draft, 'My unfinished question');
    }
});
