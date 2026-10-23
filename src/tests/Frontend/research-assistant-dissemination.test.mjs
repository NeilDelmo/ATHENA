import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import * as integration from '../../resources/js/research-assistant-dissemination.js';
import { normalizeAssistantProposalChanges } from '../../resources/js/research-assistant-proposal-changes.js';

const project = {
    id: 42, label: 'Coastal monitoring study',
    dissemination: {
        journal_search_url: 'https://athena.test/research/42/dissemination/journals/search',
        journal_save_url: 'https://athena.test/research/42/dissemination/journal-submissions',
        conference_search_url: 'https://athena.test/research/42/dissemination/conferences/search',
        conference_save_url: 'https://athena.test/research/42/dissemination/conferences',
        journal_project_url: 'https://athena.test/research-support?topic=42#journal-submissions',
        conference_project_url: 'https://athena.test/research/42/dissemination#conferences',
    },
};

test('venue searches use specific subject terms or the selected project title', () => {
    assert.equal(typeof integration.disseminationAction, 'function');
    assert.deepEqual(integration.disseminationAction('Find journals for my project'), { type: 'search_journals', query: '' });
    assert.deepEqual(integration.disseminationAction('Recommend conferences about coastal monitoring'), { type: 'search_conferences', query: 'coastal monitoring' });
    assert.equal(integration.disseminationAction('What is a journal?'), null);
    assert.equal(integration.disseminationAction('Compare these journal recommendations'), null);
    assert.equal(integration.disseminationAction('Compare these evidence-based journal recommendations for my paper.\nMarine Science: 87/100\nRecommend a shortlist and explain fit.'), null);
    assert.equal(integration.disseminationAction('Help me compare these scraped conference publishing opportunities.\nPlease suggest which venues look relevant.'), null);
    assert.equal(integration.disseminationAction('Find journal articles for my RRL'), null);
    assert.equal(integration.disseminationAction('Find conferences', { type: 'review_proposal' }), null);
    const action = integration.disseminationAction('Find journals for my project');
    assert.deepEqual(integration.disseminationSearchRequest(action, project).body, { query: 'Coastal monitoring study', indexing: 'prefer_scopus' });
    assert.equal(integration.disseminationSearchRequest(action, null), null);
    assert.equal(integration.disseminationSearchRequest(action, { id: 3, label: 'Pending project' }), null);
});

test('an incomplete search response cannot be presented as no matches', () => {
    const search = integration.disseminationSearchRequest({ type: 'search_journals', query: 'coastal' }, project);
    assert.throws(() => integration.disseminationSearchReply({}, search), /incomplete/);
});

test('a confirmed in-flight save cannot add a response to a replacement conversation', async () => {
    let resolveSave;
    const store = { ...integration.researchAssistantDissemination(), isLoading: false, retryAfter: 0, messages: [], nextMessageId: 2, scrollToLatest() {}, saveConversation: async () => {} };
    const search = integration.disseminationSearchRequest({ type: 'search_journals', query: 'coastal' }, project);
    const message = integration.disseminationSearchReply({ results: [{ name: 'Marine Science' }] }, search);
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.fetch = () => new Promise((resolve) => { resolveSave = resolve; });
    try {
        store.selectDisseminationResult(message, 0);
        const pending = store.confirmDisseminationResult(message);
        store.messages = [];
        resolveSave({ ok: true, json: async () => ({ id: 9 }) });
        assert.equal(await pending, true);
        assert.deepEqual(store.messages, []);
        assert.equal(message.dissemination.results[0].saved, true);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});

test('journal cards preserve evidence and strip unsafe links from display and save fields', () => {
    const search = integration.disseminationSearchRequest({ type: 'search_journals', query: 'coastal' }, project);
    const reply = integration.disseminationSearchReply({ results: [{
        id: 'S1', name: 'Marine Science', homepage_url: 'javascript:alert(1)', issn: '1234-5678',
        fit_score: 87, reasons: ['Three related articles.'], scopus: { label: 'Unverified' },
    }], warnings: ['Verify indexing.'] }, search);
    assert.equal(reply.dissemination.results[0].url, '');
    assert.equal(reply.dissemination.results[0].fields.journal_url, null);
    assert.match(reply.dissemination.results[0].detail, /87\/100.*Unverified/);
    assert.match(reply.reply, /Marine Science/);
    assert.match(reply.reply, /Verify indexing/);
    assert.equal(reply.dissemination.project_id, 42);
    assert.equal(reply.dissemination.project_url, 'https://athena.test/research-support?topic=42#journal-submissions');
});

test('conference saves retain the server candidate key and normalized event date', () => {
    const search = integration.disseminationSearchRequest({ type: 'search_conferences', query: 'coastal' }, project);
    const reply = integration.disseminationSearchReply({ results: [{
        title: 'Coastal Conference', url: 'https://www.wikicfp.com/cfp/servlet/event.showcfp?eventid=1',
        candidate_key: 'a'.repeat(64), event_date: 'Nov 10, 2026', event_starts_on: '2026-11-10',
        submission_deadline: '2026-10-30', deadline_status: 'open', location: 'Manila',
    }] }, search);
    assert.equal(reply.dissemination.results[0].fields.event_date, '2026-11-10');
    assert.equal(reply.dissemination.results[0].fields.candidate_key, 'a'.repeat(64));
    assert.equal(reply.dissemination.results[0].fields.status, 'shortlisted');
    assert.equal(reply.dissemination.project_url, 'https://athena.test/research/42/dissemination#conferences');
});

test('only explicit review and confirmation saves a selected result to its originating project', async () => {
    const store = {
        ...integration.researchAssistantDissemination(), isLoading: false, retryAfter: 0,
        messages: [], nextMessageId: 8, saveConversation: async () => {}, scrollToLatest() {},
    };
    const search = integration.disseminationSearchRequest({ type: 'search_journals', query: 'coastal' }, project);
    const message = { ...integration.disseminationSearchReply({ results: [{ id: 'S1', name: 'Marine Science', homepage_url: 'https://marine.example.org' }] }, search) };
    const requests = [];
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return { ok: true, json: async () => ({ id: 55, message: 'Journal submission added.' }) };
    };
    try {
        assert.equal(await store.confirmDisseminationResult(message), false);
        assert.equal(requests.length, 0);
        assert.equal(store.selectDisseminationResult(message, 0), true);
        assert.equal(requests.length, 0);
        message.dissemination.manuscript_title = 'Reviewed manuscript title';
        assert.equal(await store.confirmDisseminationResult(message), true);
        assert.equal(requests[0].url, project.dissemination.journal_save_url);
        assert.deepEqual(JSON.parse(requests[0].options.body), {
            journal_name: 'Marine Science', issn: null, journal_url: 'https://marine.example.org/',
            status: 'shortlisted', manuscript_title: 'Reviewed manuscript title',
        });
        assert.equal(message.dissemination.results[0].saved, true);
        assert.match(store.messages[0].content, /Coastal monitoring study/);
        assert.equal(store.selectDisseminationResult(message, 0), false);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});

test('failed saves show validation errors and leave results available for deliberate retry', async () => {
    const store = { ...integration.researchAssistantDissemination(), isLoading: false, retryAfter: 0 };
    const search = integration.disseminationSearchRequest({ type: 'search_conferences', query: 'coastal' }, project);
    const message = integration.disseminationSearchReply({ results: [{ title: 'Conference', url: 'https://cfp.example.org', candidate_key: 'a'.repeat(64) }] }, search);
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.fetch = async () => ({ ok: false, json: async () => ({ errors: { conference: ['This search result expired. Search again before saving it.'] } }) });
    try {
        store.selectDisseminationResult(message, 0);
        assert.equal(await store.confirmDisseminationResult(message), false);
        assert.match(message.dissemination.error, /expired/);
        assert.equal(message.dissemination.results[0].saved, false);
        assert.equal(store.disseminationSaving, false);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});

test('the actual chat request routes venue intent to the selected project search and guides missing context', async () => {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const method = source.match(/    async requestReply\([^)]*\) \{([\s\S]*?)\n    \},/)[0];
    const requests = [];
    const pageDocument = { body: { dataset: { researchAssistantUrl: '/chat', conferenceSearchUrl: '/conference-search' } }, querySelector: () => ({ content: 'csrf' }) };
    const methodFactory = new Function('document', 'fetch', 'normalizeAssistantLiterature', 'normalizeAssistantProposalChanges', 'normalizeAssistantReportChanges', 'disseminationAction', 'disseminationSearchRequest', 'disseminationSearchReply', `return ({${method}});`);
    const store = {
        ...methodFactory(pageDocument, async (url, options) => {
            requests.push({ url, body: JSON.parse(options.body) });
            return { ok: true, json: async () => ({ results: [{ name: 'Marine Science' }] }) };
        }, () => null, normalizeAssistantProposalChanges, () => null, integration.disseminationAction, integration.disseminationSearchRequest, integration.disseminationSearchReply),
        messages: [], nextMessageId: 2, requestSequence: 0, contextOptions: [project],
        contextMessages: () => [{ role: 'user', content: 'Find journals for my project.' }],
        replyContext: () => ({ topic_id: 42 }), scrollToLatest() {}, saveConversation: async () => {},
        setError(title, message) { this.error = message; },
    };
    const reply = await store.requestReply();
    assert.equal(requests[0].url, project.dissemination.journal_search_url);
    assert.deepEqual(requests[0].body, { query: 'Coastal monitoring study', indexing: 'prefer_scopus' });
    assert.equal(reply.dissemination.project_id, 42);
    assert.equal(store.messages[0].dissemination.results[0].title, 'Marine Science');
    store.replyContext = () => null;
    const guidance = await store.requestReply();
    assert.equal(requests.length, 1);
    assert.equal(guidance.dissemination.needs_project, true);
    assert.match(guidance.reply, /Choose.*project/);
});

test('venue cards require visible project confirmation and safely render untrusted titles on both themes', async () => {
    const view = readFileSync(new URL('../../resources/views/components/research-assistant-dissemination.blade.php', import.meta.url), 'utf8');
    const helpers = readFileSync(new URL('../../resources/js/research-assistant-dissemination.js', import.meta.url), 'utf8').replace(/^export /gm, '');
    const search = integration.disseminationSearchRequest({ type: 'search_journals', query: 'coastal' }, project);
    const message = integration.disseminationSearchReply({ results: [{ name: 'Marine <img src=x onerror="window.badMarkup=true"> Science', homepage_url: 'javascript:alert(1)', fit_score: 85, reasons: ['Related articles'], scopus: { label: 'Unverified' } }] }, search);
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1280]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="csrf-token" content="csrf"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="bg-white p-4 dark:bg-slate-900"><main class="mx-auto max-w-3xl" x-data="{ message: ${JSON.stringify(message).replace(/"/g, '&quot;')} }">${view}</main></body></html>`);
                await page.addScriptTag({ content: `${helpers}
                    window.saves = 0;
                    window.fetch = async () => { window.saves++; return { ok: true, json: async () => ({ id: 55 }) }; };
                    document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
                        ...researchAssistantDissemination(), isLoading: false, retryAfter: 0,
                        messages: [], nextMessageId: 2, scrollToLatest() {}, saveConversation: async () => {},
                    }));` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await page.getByRole('button', { name: 'Review and add', exact: true }).click();
                for (const name of ['Review and add', 'Confirm add to project', 'Cancel']) {
                    assert.ok((await page.getByRole('button', { name, exact: true }).boundingBox()).height >= 44);
                }
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                await expect(page.getByText('Project:', { exact: false })).toContainText('Coastal monitoring study');
                await page.getByRole('textbox', { name: 'Manuscript title', exact: true }).fill('Reviewed coastal manuscript');
                assert.equal(await page.evaluate(() => window.saves), 0);
                assert.equal(await page.locator('img').count(), 0);
                assert.equal(await page.locator('a[href^="javascript:"]').count(), 0);
                await page.getByRole('button', { name: 'Confirm add to project', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Saved to project', exact: true })).toBeDisabled();
                assert.equal(await page.evaluate(() => window.saves), 1);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
