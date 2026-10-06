import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { currentReviewedLiteratureSources, literatureInsertionHandoff, sameOriginPaperUrl } from '../../resources/js/research-assistant-paper-insertion.js';
import { escapeAssistantHtml } from '../../resources/js/research-assistant-markdown.js';
import { normalizeAssistantLiterature, serializeAssistantLiterature } from '../../resources/js/research-assistant-literature.js';

function storage() {
    const values = new Map();
    return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: (key) => values.delete(key) };
}

const source = {
    id: 51, title: 'Community monitoring', rrl_draft_status: 'confirmed', rrl_evidence_basis: 'abstract',
    rrl_note: 'Community participation supports sustained monitoring when local researchers receive appropriate training.',
};
const packet = { kind: 'insertion', proposal_draft_id: 12, sources: [source] };

test('reviewed paper handoff stays bound to the account and proposal', () => {
    const store = storage();
    const owner = literatureInsertionHandoff(store, 7, () => 1000);
    assert.equal(owner.stage(packet), true);
    assert.equal(owner.read(12).sources[0].id, 51);
    assert.equal(owner.read(13), null);
    assert.equal(literatureInsertionHandoff(store, 8, () => 1000).read(12), null);
    owner.clear(12);
    assert.equal(owner.read(12), null);
});

test('expired or malformed handoffs do not modify a paper', () => {
    const store = storage();
    assert.equal(literatureInsertionHandoff(store, 7, () => 1000).stage(packet), true);
    assert.equal(literatureInsertionHandoff(store, 7, () => 601001).read(12), null);
    store.setItem('athena:reviewed-rrl:7:12', '{invalid');
    assert.equal(literatureInsertionHandoff(store, 7).read(12), null);
    assert.equal(literatureInsertionHandoff(store, 7).stage({ ...packet, sources: [{ ...source, rrl_draft_status: 'draft' }] }), false);
});

test('blocked browser storage leaves the saved source available without throwing', () => {
    const handoff = literatureInsertionHandoff(null, 7);
    assert.equal(handoff.stage(packet), false);
    assert.equal(handoff.read(12), null);
    assert.doesNotThrow(() => handoff.clear(12));
});

test('a changed or removed source requires review instead of inserting an unreviewed paragraph', () => {
    const current = { ...source, rrl_note: 'A newer paragraph that was reviewed in another session.' };
    assert.deepEqual(currentReviewedLiteratureSources(packet, [current]), { sources: [], changed: true });
    assert.deepEqual(currentReviewedLiteratureSources(packet, []), { sources: [], changed: true });
    assert.deepEqual(currentReviewedLiteratureSources(packet, [source]), { sources: [source], changed: false });
});

test('paper navigation accepts only this application origin', () => {
    const origin = 'https://athena.test';
    assert.equal(sameOriginPaperUrl('/faculty/proposal-drafts/12/detailed-proposal', origin), `${origin}/faculty/proposal-drafts/12/detailed-proposal`);
    for (const value of ['https://other.test/paper', '//other.test/paper', 'javascript:alert(1)', 'data:text/html,test', '']) {
        assert.equal(sameOriginPaperUrl(value, origin), '');
    }
});

function editor() {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const insertion = app.slice(app.indexOf('    applyAssistantLiterature(packet) {'), app.indexOf('    applyInitialLiteratureSource() {'));
    const adding = app.slice(app.indexOf('    addLiteratureSourceToRrl(source, quiet = false) {'), app.indexOf('    addLiteratureSourceToReferences(source, quiet = false) {'));
    const methods = new Function('config', 'escapeAssistantHtml', `return ({${insertion}${adding}});`)({ proposalDraftId: 12 }, escapeAssistantHtml);
    return {
        ...methods,
        relatedLiterature: '<p>Existing unsaved literature paragraph.</p>',
        literatureSources: [], references: '', literatureSourceNotice: '', savesScheduled: 0,
        plainText: (value) => String(value).replace(/<[^>]*>/g, ''),
        upsertLiteratureSource(saved) { this.literatureSources = [saved]; },
        recordLiteratureCitation: () => 1,
        appendLiteratureText: (current, added) => `${current}\n\n${added}`,
        synchronizeLiteratureCitations() { this.references = '[1] Community monitoring.'; },
        notifyLiteratureFieldChanged() {},
        triggerDetailedProposalAutoSave() { this.savesScheduled++; },
    };
}

test('fresh chat insertion preserves unsaved text and schedules saving with references', () => {
    const paper = editor();
    const detail = { ...packet, handled: false };
    paper.applyAssistantLiterature(detail);
    assert.equal(detail.handled, true);
    assert.equal(detail.added, 1);
    assert.ok(paper.relatedLiterature.startsWith('<p>Existing unsaved literature paragraph.</p>'));
    assert.ok(paper.relatedLiterature.includes('data-proposal-citation="51"'));
    assert.equal(paper.references, '[1] Community monitoring.');
    assert.equal(paper.savesScheduled, 1);
    paper.applyAssistantLiterature({ ...packet });
    assert.equal(paper.relatedLiterature.split(source.rrl_note).length - 1, 1);
    assert.equal(paper.savesScheduled, 1);
});

test('an insertion for another proposal cannot change the active paper', () => {
    const paper = editor();
    const detail = { ...packet, proposal_draft_id: 13, handled: false };
    paper.applyAssistantLiterature(detail);
    assert.equal(detail.handled, false);
    assert.equal(paper.savesScheduled, 0);
    assert.equal(paper.literatureSources.length, 0);
});

test('reviewed paragraph markup is escaped before insertion', () => {
    const paper = editor();
    paper.applyAssistantLiterature({ ...packet, sources: [{ ...source, rrl_note: '<img src=x onerror=alert(1)> Reviewed research paragraph.' }] });
    assert.equal(paper.relatedLiterature.includes('<img'), false);
    assert.ok(paper.relatedLiterature.includes('&lt;img'));
});

function chat(fetchReply) {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const sending = app.slice(app.indexOf('    async send(action = null, contextOverride = null) {'), app.indexOf('    async retry() {'));
    const saving = app.slice(app.indexOf('    async saveConversation('), app.indexOf('    async openConversation('));
    const document = {
        body: { dataset: { researchAssistantUrl: '/chat' } },
        querySelector: () => ({ content: 'csrf' }),
        getElementById: () => ({ value: 'Unsaved text belonging to the currently open proposal.' }),
    };
    const methods = new Function('document', 'fetch', 'normalizeAssistantLiterature', 'serializeAssistantLiterature', 'redactAssistantText', 'researchAssistantMessageLimit',
        `return ({${sending}${saving}});`)(document, fetchReply, normalizeAssistantLiterature, serializeAssistantLiterature, (value) => value, 8000);
    return {
        ...methods,
        draft: '', messages: [], isLoading: false, sendInProgress: false, retryAfter: 0,
        requestSequence: 0, nextMessageId: 1, proposalDraftId: 99, currentConversationId: 7,
        retryContext: null, error: '', historySavePromise: null,
        historyUrl: () => '/history', hasConversation() { return this.messages.length > 0; },
        contextPayload: () => ({ proposal_draft_id: 99 }),
        formContext: () => ({ values: [{ field: 'general_objective', label: 'Objective', value: 'Other proposal objective.' }] }),
        scrollToLatest() {}, resetComposers() {}, upsertHistory() {},
        setError(title, message) { this.error = `${title}: ${message}`; },
        applyLiteratureInsertion() { throw new Error('Unexpected paper insertion.'); },
    };
}

function response(payload) {
    return { ok: true, json: async () => payload };
}

test('the actual chat request retains the source proposal and returns the draft response', async () => {
    let request;
    const store = chat(async (url, options) => {
        if (url === '/history') return response({ conversation: { id: 7 } });
        request = JSON.parse(options.body);
        return response({ reply: 'Review this draft.', literature: { kind: 'draft', proposal_draft_id: 12, drafts: [] } });
    });
    store.draft = 'Draft the selected study.';
    const payload = await store.send({ type: 'draft_literature', source_tokens: ['source-12'] }, { proposal_draft_id: 12 });
    assert.equal(payload.literature.proposal_draft_id, 12);
    assert.deepEqual(request.context, { proposal_draft_id: 12 });
    assert.deepEqual(request.action.source_tokens, ['source-12']);
    assert.equal(store.messages[1].literature.proposal_draft_id, 12);
});

test('history saves serialize real normalized message packets without UI-only keys', async () => {
    let persisted;
    const store = chat(async (url, options) => {
        persisted = JSON.parse(options.body);
        return response({ conversation: { id: 7 } });
    });
    store.messages = [{ role: 'assistant', content: 'Review these studies.', literature: normalizeAssistantLiterature({
        kind: 'results', proposal_draft_id: 12, results: [], notice: 'Indexed evidence.',
    }) }];
    store.messages[0].literature.is_working = true;
    store.messages[0].literature.error = 'Transient error';
    await store.saveConversation();
    const saved = persisted.messages[0].literature;
    assert.equal(saved.kind, 'results');
    for (const key of ['is_working', 'error', 'ui_selected_source_tokens']) assert.equal(key in saved, false);
});

test('a stopped response cannot add messages or initiate paper insertion', async () => {
    let finish;
    const store = chat(() => new Promise((done) => { finish = done; }));
    store.messages = [{ role: 'user', content: 'Add the reviewed paragraph.' }];
    const pending = store.requestReply({ type: 'confirm_literature', drafts: [] }, { proposal_draft_id: 12 });
    store.requestSequence++;
    store.abortController.abort();
    finish(response({ reply: 'Ready to insert.', literature: packet }));
    assert.equal(await pending, null);
    assert.equal(store.messages.length, 1);
});

test('saving the initial user message prevents simultaneous send requests', async () => {
    let finishHistory;
    let chatRequests = 0;
    const store = chat(async (url) => {
        if (url === '/history') return response({ conversation: { id: 7 } });
        chatRequests++;
        return response({ reply: 'Studies are ready.' });
    });
    store.saveConversation = () => new Promise((done) => { finishHistory = done; });
    store.draft = 'Find related studies.';
    const first = store.send();
    store.draft = 'Another message.';
    assert.equal(await store.send(), undefined);
    assert.equal(store.messages.length, 1);
    finishHistory({ id: 7 });
    await new Promise((done) => setTimeout(done, 0));
    finishHistory({ id: 7 });
    await first;
    assert.equal(chatRequests, 1);
});
