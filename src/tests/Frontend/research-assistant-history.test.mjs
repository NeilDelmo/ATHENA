import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { researchAssistantHistory } from '../../resources/js/research-assistant-history.js';
import { serializeAssistantLiterature } from '../../resources/js/research-assistant-literature.js';
import { serializeAssistantProposalChanges } from '../../resources/js/research-assistant-proposal-changes.js';
import { normalizeAssistantReportChanges } from '../../resources/js/research-assistant-report-changes.js';

const app = readFileSync('resources/js/app.js', 'utf8');
const methods = ['saveConversation', 'retry', 'hasConversation', 'upsertHistory', 'setError']
    .map((name) => app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`))[0]).join('\n');
const response = (payload, status = 200) => ({ ok: status === 200, status, json: async () => payload });

function assistant(fetchHistory) {
    const document = { querySelector: () => ({ content: 'csrf' }) };
    return {
        ...researchAssistantHistory([], fetchHistory),
        ...new Function('document', 'fetch', 'serializeAssistantLiterature', 'serializeAssistantProposalChanges', 'normalizeAssistantReportChanges',
            `return ({${methods}});`)(document, fetchHistory, serializeAssistantLiterature, serializeAssistantProposalChanges, normalizeAssistantReportChanges),
        messages: [{ role: 'user', content: 'How do I prepare a research proposal?' }],
        currentConversationId: null, historySavePromise: null,
        historySearchQuery: '', historySearchResults: [], error: '', errorTitle: '',
        historyUrl: () => '/history', contextPayload: () => null,
        requestReply: () => assert.fail('Retrying a history save must not generate another AI reply'),
    };
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
            if (path === 'history') return route.fulfill({ json: { conversations: saved ? [saved] : [] } });
            if (path === 'history/7') return route.fulfill({ json: { conversation: saved } });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="http://athena-history.test">
                <style>[x-cloak]{display:none!important}</style>
                <script>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};</script>
                <script type="module" src="/${manifest['resources/js/app.js'].file}"></script>
                </head><body x-data data-app-shell data-auth-user-id="1" data-research-assistant-history-url="/history" data-research-assistant-url="/chat">
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
        await page.getByRole('button', { name: 'How do I prepare a research proposal?' }).click();
        await page.getByText('Prepare the project details and five proposal papers.', { exact: true }).waitFor();
        assert.equal(replies, 1, 'Retrying history does not request a duplicate AI response');
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
