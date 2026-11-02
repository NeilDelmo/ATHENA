import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import {
    normalizeAssistantLiterature,
    researchAssistantLiterature,
    serializeAssistantLiterature,
} from '../../resources/js/research-assistant-literature.js';

function study(sourceToken, overrides = {}) {
    return {
        source_token: sourceToken,
        title: `Study ${sourceToken}`,
        authors: ['A. Researcher'],
        year: 2025,
        url: 'https://example.org/study',
        doi: '10.1234/example',
        description: 'An indexed abstract describing the participants, study methods, research findings, and the educational setting.',
        evidence_basis: 'abstract',
        can_read_paper: true,
        can_synthesize: true,
        relevance: { score: 85, label: 'Strong match', reason: 'Addresses the proposal’s second objective.', matched_terms: ['learning'] },
        ...overrides,
    };
}

function resultsMessage(overrides = {}) {
    return {
        id: 3,
        literature: normalizeAssistantLiterature({
            kind: 'results',
            proposal_draft_id: 42,
            proposal_title: 'Research proposal',
            query: 'learning outcomes',
            context_basis: 'Title and objectives',
            results: ['s1', 's2', 's3', 's4'].map((token) => study(token)),
            ...overrides,
        }),
    };
}

function draftMessage(overrides = {}) {
    return {
        id: 5,
        literature: normalizeAssistantLiterature({
            kind: 'draft',
            proposal_draft_id: 42,
            proposal_title: 'Research proposal',
            drafts: [{
                ...study('s1'),
                evidence_basis: 'full_text',
                draft_token: 'draft-1',
                paragraph: 'The study examines learning outcomes and supports the proposed research method.',
                relationship: 'Supports objective 2.',
            }],
            ...overrides,
        }),
    };
}

function assistant(overrides = {}, savePaper = async () => true) {
    return {
        ...researchAssistantLiterature({ savePaper }),
        isLoading: false,
        retryAfter: 0,
        draft: '',
        requestSequence: 1,
        proposalDraftId: 99,
        sendPrompt: async () => null,
        ...overrides,
    };
}

test('study selection accepts at most three sources and permits deselection at the limit', () => {
    const store = assistant();
    const message = resultsMessage();

    for (const token of ['s1', 's2', 's3']) assert.equal(store.toggleLiteratureSource(message, token), true);
    assert.equal(store.toggleLiteratureSource(message, 's4'), false);
    assert.equal(store.literatureSelectedCount(message), 3);
    assert.equal(store.literatureSourceDisabled(message, message.literature.results[3]), true);
    assert.equal(store.literatureSourceDisabled(message, message.literature.results[0]), false);
    assert.equal(store.toggleLiteratureSource(message, 's1'), true);
    assert.equal(store.toggleLiteratureSource(message, 's4'), true);
    assert.deepEqual(store.literatureSelectedTokens(message), ['s2', 's3', 's4']);
});

test('metadata-only and empty-evidence studies cannot be drafted even with a synthesis flag', () => {
    const message = resultsMessage({
        results: [
            study('metadata', { evidence_basis: 'metadata_only', can_read_paper: false }),
            study('blank', { description: '  ', can_read_paper: false }),
            study('unknown', { evidence_basis: 'unknown', can_read_paper: false }),
        ],
    });
    const store = assistant();

    assert.equal(store.literatureEvidenceLabel(message.literature.results[0]), 'Metadata only');
    for (const source of message.literature.results) {
        assert.equal(source.can_synthesize, false);
        assert.equal(store.toggleLiteratureSource(message, source.source_token), false);
    }
    assert.equal(store.canDraftLiterature(message), false);
});

test('history normalization retains readable source metadata and only valid unique selections', () => {
    const message = resultsMessage({
        is_working: true,
        error: 'Old error',
        results: [study('s1', { authors: [{ name: 'A. Researcher' }, 'B. Researcher'] }), study('s1'), study('s2', { evidence_basis: 'metadata_only', can_read_paper: false }), study('s3')],
        ui_selected_source_tokens: ['s1', 's1', 's2', 'forged', 's3'],
    });

    assert.equal(message.literature.is_working, false);
    assert.equal(message.literature.error, '');
    assert.equal(message.literature.results.length, 3);
    assert.equal(message.literature.proposal_title, 'Research proposal');
    assert.equal(message.literature.context_basis, 'Title and objectives');
    assert.deepEqual(message.literature.ui_selected_source_tokens, ['s1', 's3']);
    assert.deepEqual(message.literature.results[0].authors, ['A. Researcher', 'B. Researcher']);
    assert.equal(message.literature.results[0].relevance.reason, 'Addresses the proposal’s second objective.');
    assert.equal(assistant().literatureSourceMetadata(message.literature.results[0]), 'A. Researcher, B. Researcher · 2025');
});

test('history normalization keeps reviewed paragraphs and confirmed status without reviving operations', () => {
    const message = draftMessage({ confirmed: true, is_working: true });
    const reloaded = normalizeAssistantLiterature(JSON.parse(JSON.stringify(message.literature)));

    assert.equal(reloaded.confirmed, true);
    assert.equal(reloaded.is_working, false);
    assert.equal(reloaded.drafts[0].paragraph, message.literature.drafts[0].paragraph);
    assert.equal(reloaded.drafts[0].relationship, 'Supports objective 2.');
    assert.equal(assistant().canConfirmLiterature({ literature: reloaded }), false);
});

test('history serialization persists only the literature contract and removes temporary interface state', () => {
    const message = resultsMessage({ ui_selected_source_tokens: ['s1'], year_from: 2021, year_to: 2026 });
    message.literature.is_working = true;
    message.literature.error = 'Temporary problem';
    message.literature.unknown_field = 'not persisted';
    const persisted = serializeAssistantLiterature(message.literature);

    assert.deepEqual(Object.keys(persisted).sort(), ['kind', 'proposal_draft_id', 'proposal_title', 'notice', 'topic', 'intent', 'editor_url', 'read_failures', 'query', 'context_basis', 'results', 'year_from', 'year_to'].sort());
    assert.equal(persisted.year_from, 2021);
    assert.equal(persisted.year_to, 2026);
    assert.equal('is_working' in persisted, false);
    assert.equal('error' in persisted, false);
    assert.equal('ui_selected_source_tokens' in persisted, false);
    assert.equal(persisted.results[0].source_token, 's1');
    assert.deepEqual(Object.keys(serializeAssistantLiterature(draftMessage({ confirmed: true }).literature)).sort(), ['kind', 'proposal_draft_id', 'proposal_title', 'notice', 'topic', 'intent', 'editor_url', 'read_failures', 'drafts', 'confirmed'].sort());
    assert.deepEqual(Object.keys(serializeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, sources: [], editor_url: 'https://athena.test/paper', applied: true })).sort(), ['kind', 'proposal_draft_id', 'proposal_title', 'notice', 'topic', 'intent', 'read_failures', 'sources', 'editor_url', 'applied'].sort());
});

test('unsupported packets and proposals without a valid draft ID are ignored', () => {
    for (const packet of [null, {}, { kind: 'results' }, { kind: 'results', proposal_draft_id: -1 }, { kind: 'draft', proposal_draft_id: 1.5 }, { kind: 'surprise', proposal_draft_id: 42 }]) {
        assert.equal(normalizeAssistantLiterature(packet), null);
    }
    assert.equal(assistant().literatureSelectedCount({}), 0);
    assert.equal(assistant().canConfirmLiterature({}), false);
});

test('external links allow absolute HTTP and HTTPS URLs and reject executable or credential URLs', () => {
    for (const url of ['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '//evil.example/study', '/paper', 'https://user:secret@example.org/study', 'https://', 'file:///C:/paper.html']) {
        const message = resultsMessage({ results: [study('s1', { url })] });
        const draft = draftMessage({ drafts: [{ ...study('s1', { url }), draft_token: 'd1', paragraph: 'A valid RRL paragraph with a sufficient amount of reviewed text.' }] });
        const insertion = normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, editor_url: url, sources: [{ id: 8, url }] });

        assert.equal(message.literature.results[0].url, '');
        assert.equal(draft.literature.drafts[0].url, '');
        assert.equal(insertion.editor_url, '');
        assert.equal(insertion.sources[0].url, '');
    }
    assert.equal(resultsMessage({ results: [study('s1', { url: 'http://example.org/study' })] }).literature.results[0].url, 'http://example.org/study');
    assert.equal(resultsMessage().literature.results[0].url, 'https://example.org/study');
});

test('insertion packets preserve source library fields needed by the paper editor', () => {
    const source = { id: 8, source_key: 'source-8', rrl_text: 'Reviewed paragraph', citation_text: '[8]', reference_text: 'Reference', url: 'https://example.org/study' };
    const packet = normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, sources: [source], editor_url: 'https://athena.test/proposals/42/paper', applied: true });

    assert.deepEqual(packet.sources[0], source);
    assert.equal(packet.applied, true);
});

test('draft actions use selected source tokens and retain their originating proposal', async () => {
    const calls = [];
    const store = assistant({ sendPrompt: async (...args) => calls.push(args) });
    const message = resultsMessage();
    store.toggleLiteratureSource(message, 's1');
    store.toggleLiteratureSource(message, 's3');

    assert.equal(await store.draftSelectedLiterature(message), true);
    assert.equal(calls.length, 1);
    assert.deepEqual(calls[0][1], { type: 'draft_literature', source_tokens: ['s1', 's3'], evidence_basis: 'full_text' });
    assert.deepEqual(calls[0][2], { proposal_draft_id: 42 });
    assert.equal(store.proposalDraftId, 99);
    assert.equal(message.literature.is_working, false);
    assert.equal(store.literatureOperationPending, false);
});

test('literature draft and confirmation actions preserve an unfinished chat message', async () => {
    const pendingMessage = 'Can you explain the sampling method in the second study?';
    const store = assistant({ draft: pendingMessage, sendPrompt: async () => assert.fail('An unfinished message must not be replaced by an RRL action') });
    const search = resultsMessage();
    store.toggleLiteratureSource(search, 's1');
    const draft = draftMessage();

    assert.equal(store.canDraftLiterature(search), false);
    assert.equal(await store.draftSelectedLiterature(search), false);
    assert.equal(store.canConfirmLiterature(draft), false);
    assert.equal(await store.confirmLiteratureDraft(draft), false);
    assert.equal(store.draft, pendingMessage);

    store.draft = '';
    assert.equal(store.canDraftLiterature(search), true);
    assert.equal(store.canConfirmLiterature(draft), true);
});

test('abstract fallback is rejected while readable papers without abstracts remain eligible', async () => {
    const calls = [];
    const store = assistant({ sendPrompt: async (...args) => calls.push(args) });
    const message = resultsMessage({ results: [study('paper', { description: '', evidence_basis: 'metadata_only', can_read_paper: true })] });
    assert.equal(store.toggleLiteratureSource(message, 'paper'), true);
    assert.equal(store.canDraftLiterature(message), true);
    assert.equal(store.canDraftLiterature(message, 'abstract'), false);
    assert.equal(await store.draftSelectedLiterature(message), true);
    assert.equal(calls[0][1].evidence_basis, 'full_text');
    const fallback = resultsMessage();
    store.toggleLiteratureSource(fallback, 's1');
    assert.equal(await store.draftSelectedLiterature(fallback, 'abstract'), false);
    assert.equal(calls.length, 1);
});

test('restored abstract drafts cannot be confirmed and abstract-only source metadata cannot be selected', async () => {
    const store = assistant({ sendPrompt: async () => assert.fail('Abstract-only evidence cannot be submitted') });
    const draft = draftMessage();
    draft.literature.drafts[0].evidence_basis = 'abstract';
    assert.equal(store.canConfirmLiterature(draft), false);
    assert.equal(await store.confirmLiteratureDraft(draft), false);
    const result = resultsMessage({ results: [study('abstract', { can_read_paper: false })] });
    assert.equal(store.toggleLiteratureSource(result, 'abstract'), false);
});

test('draft actions reject empty selections, unavailable sources, and tampered oversized selections', async () => {
    let calls = 0;
    const store = assistant({ sendPrompt: async () => calls++ });
    const message = resultsMessage();

    assert.equal(await store.draftSelectedLiterature(message), false);
    message.literature.ui_selected_source_tokens = ['s1', 's2', 's3', 's4'];
    assert.equal(await store.draftSelectedLiterature(message), false);
    message.literature.ui_selected_source_tokens = ['unknown'];
    assert.equal(await store.draftSelectedLiterature(message), false);
    assert.equal(calls, 0);
});

test('confirmation sends edited paragraphs paired with their original draft tokens', async () => {
    const calls = [];
    const message = draftMessage();
    message.literature.drafts[0].paragraph = '  My reviewed paragraph relates this study’s findings to objective 2 in our proposal.  ';
    const store = assistant({
        sendPrompt: async (...args) => {
            calls.push(args);
            return { literature: { kind: 'insertion', proposal_draft_id: 42 } };
        },
    });

    assert.equal(await store.confirmLiteratureDraft(message), true);
    assert.deepEqual(calls[0][1], {
        type: 'confirm_literature',
        drafts: [{ draft_token: 'draft-1', paragraph: 'My reviewed paragraph relates this study’s findings to objective 2 in our proposal.' }],
    });
    assert.deepEqual(calls[0][2], { proposal_draft_id: 42 });
    assert.equal(message.literature.confirmed, true);
    assert.equal(await store.confirmLiteratureDraft(message), false);
    assert.equal(calls.length, 1);
});

test('confirmation requires every paragraph to have at least 40 reviewed characters and actual evidence', async () => {
    let calls = 0;
    const store = assistant({ sendPrompt: async () => calls++ });
    const message = draftMessage();

    for (const paragraph of ['', ' '.repeat(40), 'a'.repeat(39)]) {
        message.literature.drafts[0].paragraph = paragraph;
        assert.equal(await store.confirmLiteratureDraft(message), false);
    }
    message.literature.drafts[0].paragraph = 'a'.repeat(40);
    assert.equal(store.canConfirmLiterature(message), true);
    message.literature.drafts[0].paragraph = 'a'.repeat(5001);
    assert.equal(store.canConfirmLiterature(message), false);
    message.literature.drafts[0].paragraph = 'a'.repeat(40);
    message.literature.drafts[0].evidence_basis = 'metadata_only';
    assert.equal(await store.confirmLiteratureDraft(message), false);
    assert.equal(calls, 0);
});

test('working, loading, and retry guards prevent selections and actions', async () => {
    for (const state of [{ isLoading: true }, { retryAfter: 2 }, { literatureOperationPending: true }]) {
        const store = assistant(state);
        const message = resultsMessage({ ui_selected_source_tokens: ['s1'] });
        assert.equal(store.toggleLiteratureSource(message, 's2'), false);
        assert.equal(await store.draftSelectedLiterature(message), false);
        assert.equal(await store.confirmLiteratureDraft(draftMessage()), false);
    }
    const message = draftMessage();
    message.literature.is_working = true;
    assert.equal(await assistant().confirmLiteratureDraft(message), false);
});

test('pending confirmations block simultaneous operations before global chat loading begins', async () => {
    let resolveResponse;
    let calls = 0;
    const store = assistant({
        sendPrompt: () => {
            calls++;
            return new Promise((resolve) => { resolveResponse = resolve; });
        },
    });
    const draft = draftMessage();
    const results = resultsMessage({ ui_selected_source_tokens: ['s1'] });
    const pending = store.confirmLiteratureDraft(draft);

    assert.equal(draft.literature.is_working, true);
    assert.equal(await store.confirmLiteratureDraft(draft), false);
    assert.equal(await store.draftSelectedLiterature(results), false);
    assert.equal(calls, 1);
    resolveResponse({ literature: { kind: 'insertion', proposal_draft_id: 42 } });
    assert.equal(await pending, true);
    assert.equal(store.literatureOperationPending, false);
});

test('failed or unrelated responses leave drafts available for a deliberate retry', async () => {
    for (const sendPrompt of [
        async () => null,
        async () => ({ literature: { kind: 'insertion', proposal_draft_id: 99 } }),
        async () => { throw new Error('Network failure'); },
    ]) {
        const message = draftMessage();
        const store = assistant({ sendPrompt });
        assert.equal(await store.confirmLiteratureDraft(message), false);
        assert.equal(message.literature.confirmed, false);
        assert.equal(message.literature.is_working, false);
        assert.equal(store.canConfirmLiterature(message), true);
    }
});

test('RRL flow review is optional after insertion and stays bound to its original proposal', async () => {
    const events = [];
    const message = { literature: normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, applied: false }) };
    const store = assistant({ sendPrompt: async (...args) => events.push(args) }, async (id) => {
        events.push(['saved', id]);
        return true;
    });

    assert.equal(store.canReviewLiteratureFlow(message), false);
    assert.equal(await store.reviewLiteratureFlow(message), false);
    assert.deepEqual(events, []);
    message.literature.applied = true;
    assert.equal(store.canReviewLiteratureFlow(message), true);
    assert.deepEqual(events, []);
    assert.equal(await store.reviewLiteratureFlow(message), true);
    assert.deepEqual(events[0], ['saved', 42]);
    assert.match(events[1][0], /paragraph flow/);
    assert.deepEqual(events[1].slice(1), [{ type: 'suggest_proposal_changes' }, { proposal_draft_id: 42 }]);
    assert.equal(store.proposalDraftId, 99);
    assert.equal(message.literature.is_working, false);
    assert.equal(store.literatureOperationPending, false);
});

test('RRL flow review preserves unfinished messages and waits for other assistant operations', async () => {
    const message = { literature: normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, applied: true }) };
    for (const state of [{ draft: 'My unfinished question' }, { isLoading: true }, { sendInProgress: true },
        { retryAfter: 2 }, { literatureOperationPending: true }, { proposalChangesBusy: () => true }]) {
        let saved = false;
        let requested = false;
        const store = assistant({ ...state, sendPrompt: async () => { requested = true; } }, async () => { saved = true; return true; });
        assert.equal(store.canReviewLiteratureFlow(message), false);
        assert.equal(await store.reviewLiteratureFlow(message), false);
        assert.equal(store.draft, state.draft || '');
        assert.equal(saved, false);
        assert.equal(requested, false);
    }
    assert.equal(assistant().canReviewLiteratureFlow(draftMessage()), false);
    assert.equal(assistant().canReviewLiteratureFlow({ literature: { kind: 'insertion', applied: true, proposal_draft_id: 0 } }), false);
});

test('RRL flow review stops after a failed save, cancellation, or a newly typed message', async () => {
    for (const scenario of ['failed save', 'cancelled', 'new message', 'network failure']) {
        const message = { literature: normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, applied: true }) };
        let requests = 0;
        const store = assistant({ sendPrompt: async () => {
            requests++;
            if (scenario === 'network failure') throw new Error('Network interrupted');
        } }, async () => {
            if (scenario === 'cancelled') store.requestSequence++;
            if (scenario === 'new message') store.draft = 'Keep my new question';
            assert.equal(message.literature.is_working, true);
            assert.equal(store.literatureOperationPending, true);
            assert.equal(await store.reviewLiteratureFlow(message), false);
            return scenario !== 'failed save';
        });
        assert.equal(await store.reviewLiteratureFlow(message), false);
        assert.equal(requests, scenario === 'network failure' ? 1 : 0);
        assert.equal(message.literature.is_working, false);
        assert.equal(store.literatureOperationPending, false);
        if (['failed save', 'network failure'].includes(scenario)) assert.ok(message.literature.error);
        if (scenario === 'new message') assert.equal(store.draft, 'Keep my new question');
    }
});

test('RRL flow review saves only the paper matching the insertion proposal', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const factory = app.slice(app.indexOf('...researchAssistantLiterature(') + 3, app.indexOf('...researchAssistantDissemination(')).trim().replace(/,$/, '');
    for (const activeId of [42, 99, null]) {
        const activeEditor = activeId ? { dataset: { proposalDraftId: String(activeId) } } : null;
        const saves = [];
        const methods = new Function('researchAssistantLiterature', 'document', 'finishPaperEditorAutoSave', 'finishPendingNavigationChanges', `return ${factory};`)(
            researchAssistantLiterature, { querySelector: () => activeEditor }, async (editor) => { saves.push(editor); return true; }, async () => true,
        );
        const store = { ...assistant(), ...methods };
        const message = { literature: normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, applied: true }) };
        assert.equal(await store.reviewLiteratureFlow(message), true);
        assert.deepEqual(saves, activeId === 42 ? [activeEditor] : []);
    }
});

test('the shared component renders editable review and source content as plain text', async () => {
    const view = await readFile(new URL('../../resources/views/components/research-assistant-literature.blade.php', import.meta.url), 'utf8');

    assert.equal(view.includes('x-html='), false);
    assert.match(view, /x-model="draft\.paragraph"/);
    assert.match(view, /Add to proposal/);
    assert.match(view, /x-text="source\.description"/);
    assert.match(view, /rel="noopener noreferrer"/);
});

test('both assistant surfaces render the shared literature flow and pass starter prompt actions', async () => {
    for (const surface of ['workspace', 'drawer']) {
        const view = await readFile(new URL(`../../resources/views/components/research-assistant-${surface}.blade.php`, import.meta.url), 'utf8');
        assert.match(view, /<x-research-assistant-literature\s*\/>/);
        assert.match(view, /<x-research-assistant-welcome(?: compact)?\s*\/>/);
    }
    const welcome = await readFile(new URL('../../resources/views/components/research-assistant-welcome.blade.php', import.meta.url), 'utf8');
    assert.match(welcome, /sendPrompt\(item\.prompt, item\.action \|\| null\)/);
});

test('compiled literature cards support selection, edited confirmation, and both themes at mobile and desktop widths', async () => {
    const compiled = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Blade::render('<x-research-assistant-literature />');
    `], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(compiled.status, 0, (compiled.stderr || compiled.stdout).slice(-3000));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const helpers = readFileSync(resolve('resources/js/research-assistant-literature.js'), 'utf8').replace(/^import .+;\r?\n/gm, '').replace(/^export /gm, '');
    const message = resultsMessage({
        notice: 'Review how each source supports your proposal.',
        results: [
            ...['s1', 's2', 's3', 's4'].map((token) => study(token)),
            study('metadata', { title: 'Metadata <img src=x onerror="window.untrustedMarkup=true">', url: 'javascript:alert(1)', evidence_basis: 'metadata_only', description: '', can_read_paper: false }),
        ],
    });
    const reviewedDraft = draftMessage();
    reviewedDraft.literature.drafts[0].paragraph = 'The study examined how students used learning activities and compared their engagement across two classes. Its findings suggest that context matters when choosing an approach, and its limitations should guide how these results are applied. '.repeat(4).trim();
    reviewedDraft.literature.drafts[0].relationship = 'standalone';
    const coverageNote = 'All 27 PDF pages were processed without text truncation. Check the original paper for tables, figures, scanned content, and extraction errors.';
    reviewedDraft.literature.drafts[0].notice = coverageNote;
    const editedParagraph = 'My reviewed explanation connects the study’s findings with objective 2 and its proposed method.';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="bg-white p-4 dark:bg-slate-900"><main class="mx-auto max-w-3xl" x-data="literatureFixture">${compiled.stdout}</main></body></html>`);
                await page.addScriptTag({ content: `${helpers}
                    window.literatureCalls = [];
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('literatureFixture', () => ({ message: ${JSON.stringify(message)} }));
                        Alpine.store('researchAssistant', {
                            ...researchAssistantLiterature({ savePaper: async (id) => { window.literatureSaveId = id; return true; } }), isLoading: false, retryAfter: 0, draft: '', requestSequence: 1, workspaceOpen: false, copiedMessageId: null,
                            saveConversation() {},
                            openWorkspace() { this.workspaceOpen = true; },
                            async copyMessage(message) { window.copiedDraft = message.content; this.copiedMessageId = message.id; },
                            async sendPrompt(...args) {
                                window.literatureCalls.push(args);
                                return { literature: { kind: 'insertion', proposal_draft_id: 42 } };
                            },
                            openLiteraturePaper(message) { window.openedLiteratureProposal = message.literature.proposal_draft_id; },
                            openLiteratureSources(message, mode = 'pdf') { window.sourceRecovery = { id: message.literature.proposal_draft_id, mode }; },
                        });
                    });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await expect(page.getByText('Sources for your RRL', { exact: true })).toBeVisible();
                await expect(page.getByText('Metadata only', { exact: true })).toBeVisible();
                await expect(page.getByRole('checkbox', { name: 'Select study: Metadata', exact: false })).toBeDisabled();
                assert.equal(await page.locator('img').count(), 0);
                assert.equal(await page.locator('a[href^="javascript:"]').count(), 0);
                assert.equal(await page.evaluate(() => Boolean(window.untrustedMarkup)), false);
                await expect(page.getByRole('button', { name: 'Generate from papers', exact: true })).toBeDisabled();
                for (const token of ['s1', 's2', 's3']) await page.getByRole('checkbox', { name: `Select study: Study ${token}`, exact: true }).check();
                await expect(page.getByRole('checkbox', { name: 'Select study: Study s4', exact: true })).toBeDisabled();
                const firstSelection = page.getByRole('checkbox', { name: 'Select study: Study s1', exact: true });
                await firstSelection.focus();
                await page.keyboard.press('Space');
                await expect(firstSelection).not.toBeChecked();
                await firstSelection.check();
                await page.getByRole('button', { name: 'Generate from papers', exact: true }).click();
                await expect.poll(() => page.evaluate(() => window.literatureCalls.length)).toBe(1);
                const draftingCall = await page.evaluate(() => window.literatureCalls[0]);
                assert.deepEqual(draftingCall[1], { type: 'draft_literature', source_tokens: ['s2', 's3', 's1'], evidence_basis: 'full_text' });
                assert.deepEqual(draftingCall[2], { proposal_draft_id: 42 });
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY, `assistant-literature-results-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }

                await page.evaluate((draft) => { Alpine.$data(document.querySelector('main')).message = draft; }, reviewedDraft);
                const paragraph = page.getByRole('textbox', { name: 'RRL paragraph based on Study s1', exact: true });
                await expect(paragraph).toBeVisible();
                await expect(page.getByRole('article', { name: 'Editable RRL draft' })).toBeVisible();
                await expect(page.getByText('Paragraph 1', { exact: true })).toHaveCount(0);
                await page.getByRole('button', { name: 'Copy RRL draft', exact: true }).click();
                assert.match(await page.evaluate(() => window.copiedDraft), /\[1\][\s\S]*References/);
                await page.getByRole('button', { name: 'Expand RRL draft', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Expand RRL draft', exact: true })).toBeHidden();
                await expect(page.getByText('standalone', { exact: true })).toHaveCount(0);
                await expect(page.getByText(coverageNote, { exact: true })).toBeHidden();
                const sourceDetails = page.getByText('Sources (1)', { exact: true });
                await sourceDetails.click();
                await expect(page.getByText('Drafted from source-paper text', { exact: true })).toBeVisible();
                await page.getByText('Reading details', { exact: true }).click();
                await expect(page.getByText(coverageNote, { exact: true })).toBeVisible();
                await sourceDetails.press('Enter');
                await expect(page.getByText(coverageNote, { exact: true })).toBeHidden();
                assert.ok(await paragraph.evaluate(element => parseFloat(getComputedStyle(element).fontSize) >= 16 && parseFloat(getComputedStyle(element).lineHeight) >= 26), 'Draft paragraphs must use readable text and spacing');
                const fits = () => paragraph.evaluate(element => element.scrollHeight <= element.clientHeight + 1);
                await expect.poll(fits).toBe(true);
                const height = () => paragraph.evaluate(element => Math.round(element.getBoundingClientRect().height));
                const originalHeight = await height();
                await page.setViewportSize({ width: width === 320 ? 390 : 320, height: 900 });
                await expect.poll(height).not.toBe(originalHeight);
                await expect.poll(fits).toBe(true);
                await page.setViewportSize({ width, height: 900 });
                await expect.poll(height).toBe(originalHeight);
                await expect.poll(fits).toBe(true);
                if (process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY, `assistant-literature-draft-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await paragraph.fill(reviewedDraft.literature.drafts[0].paragraph.repeat(2));
                await expect.poll(fits).toBe(true);
                await paragraph.fill('Too short');
                await expect(page.getByRole('button', { name: 'Add to proposal', exact: true })).toBeDisabled();
                await paragraph.fill(editedParagraph);
                assert.equal(await page.evaluate(() => window.literatureCalls.length), 1);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                await page.getByRole('button', { name: 'Add to proposal', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Review confirmed', exact: true })).toBeDisabled();
                const confirmingCall = await page.evaluate(() => window.literatureCalls[1]);
                assert.deepEqual(confirmingCall[1], { type: 'confirm_literature', drafts: [{ draft_token: 'draft-1', paragraph: editedParagraph }] });
                assert.deepEqual(confirmingCall[2], { proposal_draft_id: 42 });
                await expect(paragraph).toBeDisabled();

                await page.evaluate((packet) => { Alpine.$data(document.querySelector('main')).message = { id: 8, literature: packet }; }, normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, editor_url: 'https://athena.test/proposals/42/paper', notice: 'Open the paper to see the reviewed paragraphs.' }));
                await expect(page.getByRole('button', { name: 'Review RRL flow', exact: true })).toBeHidden();
                await expect(page.getByText('To improve paragraph flow, open Use AI in the RRL section and choose Revise RRL.', { exact: true })).toBeVisible();
                await page.getByRole('button', { name: 'Open paper', exact: true }).click();
                assert.equal(await page.evaluate(() => window.openedLiteratureProposal), 42);
                await page.evaluate(() => { Alpine.$data(document.querySelector('main')).message.literature.applied = true; });
                const reviewFlow = page.getByRole('button', { name: 'Review RRL flow', exact: true });
                await expect(reviewFlow).toBeVisible();
                assert.equal(await page.evaluate(() => window.literatureCalls.length), 2);
                await page.evaluate(() => { Alpine.store('researchAssistant').isLoading = true; });
                await expect(reviewFlow).toBeDisabled();
                await page.evaluate(() => { Alpine.store('researchAssistant').isLoading = false; Alpine.store('researchAssistant').draft = 'My unfinished question'; });
                await expect(reviewFlow).toBeDisabled();
                await page.evaluate(() => { Alpine.store('researchAssistant').draft = ''; });
                await reviewFlow.click();
                await expect.poll(() => page.evaluate(() => window.literatureCalls.length)).toBe(3);
                assert.equal(await page.evaluate(() => window.literatureSaveId), 42);
                const reviewingCall = await page.evaluate(() => window.literatureCalls[2]);
                assert.deepEqual(reviewingCall.slice(1), [{ type: 'suggest_proposal_changes' }, { proposal_draft_id: 42 }]);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);

                await page.evaluate((legacy) => { Alpine.$data(document.querySelector('main')).message = legacy; }, draftMessage({ drafts: [{ ...study('old'), draft_token: 'old-draft', evidence_basis: 'abstract', paragraph: 'A saved draft based on an abstract, which has not been read as a complete paper.' }] }));
                await expect(page.getByRole('button', { name: 'Add to proposal', exact: true })).toBeDisabled();
                await expect(page.getByText('This saved draft was based on abstracts.', { exact: false })).toBeVisible();
                await expect(page.getByText('Complete source paper read', { exact: true })).toHaveCount(0);

                await page.evaluate((failure) => { Alpine.$data(document.querySelector('main')).message = failure; }, resultsMessage({ intent: 'draft', topic: 'Gamified studying',
                    results: [study('blocked', { can_read_paper: false, can_synthesize: false, reading_error: 'The publisher rejected the PDF request.' })],
                    read_failures: [{ title: 'Study blocked', reason: 'The publisher rejected the PDF request.' }],
                }));
                await expect(page.getByRole('heading', { name: 'RRL draft not ready' })).toBeVisible();
                await expect(page.getByText('Gamified studying', { exact: true })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Generate from papers', exact: true })).toBeHidden();
                await expect(page.getByRole('button', { name: 'Attach source PDF', exact: true })).toBeVisible();
                await expect(page.getByRole('checkbox')).toBeHidden();
                await page.getByText('Why the papers could not be read', { exact: true }).click();
                await expect(page.getByText('The publisher rejected the PDF request.', { exact: true })).toBeVisible();
                await page.getByRole('button', { name: 'Change search', exact: true }).click();
                assert.deepEqual(await page.evaluate(() => window.sourceRecovery), { id: 42, mode: 'search' });
                assert.equal(await page.evaluate(() => window.literatureCalls.length), 3);
                if (process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY, `assistant-literature-unreadable-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }

                await page.evaluate((empty) => { Alpine.$data(document.querySelector('main')).message = empty; }, resultsMessage({ results: [], notice: 'Try a more specific research term.' }));
                await expect(page.getByText('Try a more specific research term.', { exact: true })).toBeVisible();
                await expect(page.getByText('No studies found.', { exact: false })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Generate from papers', exact: true })).toBeDisabled();
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});


test('draft reading outcomes and the clarified topic survive history without claiming a link is a read paper', () => {
    const store = assistant();
    const packet = normalizeAssistantLiterature({ kind: 'results', intent: 'draft', proposal_draft_id: 42,
        proposal_title: 'Unclear working title', topic: 'Gamified studying', query: 'gamification learning',
        results: [study('blocked', { can_read_paper: false, can_synthesize: false, reading_error: 'Publisher blocked the PDF.' })],
        read_failures: [{ title: 'Blocked study', reason: 'Publisher blocked the PDF.' }],
    });
    assert.equal(packet.topic, 'Gamified studying');
    assert.equal(packet.intent, 'draft');
    assert.equal(serializeAssistantLiterature(packet).read_failures[0].reason, 'Publisher blocked the PDF.');
    assert.match(store.literatureEvidenceLabel(study('pending')), /link|not.*checked/i);
    assert.doesNotMatch(store.literatureEvidenceLabel(study('pending')), /text available/i);
});

test('proposal choices retain the original RRL request and exclude malformed options', () => {
    const request = 'Draft an RRL for my proposal using complete source papers.';
    const packet = normalizeAssistantLiterature({ kind: 'choose_proposal', intent: 'draft', request,
        proposals: [{ id: 12, title: 'Gamified studying' }, { id: -1, title: 'Invalid' }], workspace_required: false });
    assert.ok(packet);
    assert.equal(packet.request, request);
    assert.deepEqual(packet.proposals, [{ id: 12, title: 'Gamified studying' }]);
    assert.equal(serializeAssistantLiterature(packet).request, request);
});


test('the report-to-proposal handoff preserves the request and uses the authorized workspace switch', async () => {
    const saved = new Map(); const events = [];
    const browser = { document: { body: { dataset: { authUserId: '7', activeWorkspace: 'faculty_researcher', researchAssistantWorkspaceUrl: '/choose-workspace' } }, querySelector: () => ({ content: 'csrf' }) },
        location: { origin: 'https://athena.test', assign: url => events.push(['navigate', url]) },
        sessionStorage: { getItem: key => saved.get(key), setItem: (key, value) => saved.set(key, value), removeItem: key => saved.delete(key) },
        fetch: async (url, options) => { events.push(['switch', url, JSON.parse(options.body)]); return { ok: true, json: async () => ({ redirect: 'https://athena.test/proposals' }) }; },
    };
    const store = { ...assistant(), ...researchAssistantLiterature({ browser }), hasConversation: () => true,
        async saveConversation() { events.push(['save']); return { id: 4 }; }, openDrawer() {},
        async ensureConversationContext(context) { events.push(['context', context]); return true; },
        async sendPrompt(...args) { events.push(['request', ...args]); return { literature: { kind: 'choose_proposal' } }; },
    };
    const message = { literature: normalizeAssistantLiterature({ kind: 'choose_proposal', intent: 'draft', request: 'Draft an RRL for my proposal.', workspace_required: true, proposals_url: 'https://athena.test/proposals' }) };
    assert.equal(await store.switchLiteratureWorkspace(message), true);
    assert.equal(events[0][0], 'save');
    assert.equal(events[1][0], 'switch');
    assert.deepEqual(events[1][2], { workspace: 'faculty', resume_url: 'https://athena.test/proposals' });
    assert.equal(events[2][0], 'navigate');
    browser.document.body.dataset.activeWorkspace = 'faculty';
    assert.equal(await store.restoreLiteratureRequest(), true);
    assert.deepEqual(events.find(item => item[0] === 'request').slice(1), ['Draft an RRL for my proposal.', { type: 'draft_rrl' }, null]);
});

test('choosing a proposal sends the retained request on that proposal and protects an unfinished message', async () => {
    const calls = [];
    const store = assistant({ openDrawer() {}, async ensureConversationContext(context) { calls.push(context); return true; },
        restoreReviewContext() {}, async sendPrompt(...args) { calls.push(args); return { reply: 'Draft ready.' }; },
    });
    const message = { literature: normalizeAssistantLiterature({ kind: 'choose_proposal', intent: 'draft', request: 'Draft the RRL.', proposals: [{ id: 12, title: 'Gamified studying' }] }) };
    store.draft = 'Keep my unfinished question';
    assert.equal(await store.chooseLiteratureProposal(message, 12), false);
    assert.equal(store.draft, 'Keep my unfinished question');
    store.draft = '';
    assert.equal(await store.chooseLiteratureProposal(message, 12), true);
    assert.deepEqual(calls.at(-1), ['Draft the RRL.', { type: 'draft_rrl' }, { proposal_draft_id: 12 }]);
});

test('copying the draft document includes the review citations and reference list', async () => {
    let copied;
    const store = assistant({ copyMessage: async message => { copied = message.content; } });
    const message = draftMessage({ drafts: [{ ...study('s1'), draft_token: 'draft-1', paragraph: 'A source-backed paragraph.', reference: '[1] A. Researcher, Study s1, 2025.', evidence_basis: 'full_text' }] });
    await store.copyLiteratureDraft(message);
    assert.match(copied, /A source-backed paragraph\. \[1\]/);
    assert.match(copied, /References\n\[1\] A\. Researcher/);
});


test('the built chat carries a report RRL request into a chosen proposal and retains the edited draft after refresh', async () => {
    const compiled = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Blade::render('<x-research-assistant-literature />');
    `], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(compiled.status, 0, (compiled.stderr || compiled.stdout).slice(-2000));
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const request = 'Draft an RRL for my proposal using complete source papers. Use the project title and objectives. Show the draft for my review.';
    const state = { workspace: 'faculty_researcher', calls: [], records: new Map(), nextId: 1, switches: 0 };
    const artifact = draftMessage({ proposal_title: 'bibe girl', topic: 'Gamified studying',
        drafts: [{ ...study('s1'), evidence_basis: 'full_text', draft_token: 'draft-1',
            paragraph: 'The study examined how gamified learning activities relate to students’ engagement and learning outcomes. Its findings suggest that the learning context and study design matter when applying these results to a new project.',
            reference: '[1] A. Researcher, “Study s1,” 2025.' }] }).literature;
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.route('http://athena-rrl.test/**', async route => {
            const url = new URL(route.request().url()); const path = url.pathname.slice(1);
            if (assets.has(path)) return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            if (path === 'workspace') {
                assert.deepEqual(route.request().postDataJSON(), { workspace: 'faculty', resume_url: 'http://athena-rrl.test/proposals' });
                state.workspace = 'faculty'; state.switches++;
                return route.fulfill({ json: { redirect: 'http://athena-rrl.test/proposals' } });
            }
            if (path === 'chat') {
                const payload = route.request().postDataJSON(); state.calls.push(payload);
                if (payload.action?.type === 'confirm_literature') return route.fulfill({ json: { reply: 'The reviewed text is ready for your proposal.',
                    literature: { kind: 'insertion', proposal_draft_id: 42, sources: [{ id: 9, rrl_note: payload.action.drafts[0].paragraph,
                        rrl_draft_status: 'confirmed', rrl_evidence_basis: 'full_text' }] } } });
                if (payload.context?.report_type) return route.fulfill({ json: { reply: 'Choose a proposal in your proposal workspace to continue.',
                    literature: { kind: 'choose_proposal', intent: 'draft', request, workspace_required: true, proposals_url: 'http://athena-rrl.test/proposals' } } });
                if (!payload.context?.proposal_draft_id) return route.fulfill({ json: { reply: 'Which proposal should I use for the RRL?',
                    literature: { kind: 'choose_proposal', intent: 'draft', request, proposals: [{ id: 42, title: 'bibe girl' }] } } });
                return route.fulfill({ json: payload.messages.at(-1).content.includes('gamified studying')
                    ? { reply: 'Here is a draft to review and edit.', literature: artifact }
                    : { reply: 'What would you like to study regarding “bibe girl”?' } });
            }
            if (path === 'history' && route.request().method() === 'POST') {
                const payload = route.request().postDataJSON();
                const record = { ...payload, id: payload.id || state.nextId++, version: 'a'.repeat(64),
                    context: { ...payload.context, workspace: state.workspace }, title: payload.messages[0].content };
                state.records.set(record.id, record); return route.fulfill({ json: { conversation: record } });
            }
            if (path.startsWith('history')) {
                const project = Number(url.searchParams.get('context[proposal_draft_id]'));
                const records = [...state.records.values()].filter(record => record.context.workspace === state.workspace
                    && (!project || record.context.proposal_draft_id === project)).reverse();
                const id = Number(path.split('/')[1] || url.searchParams.get('conversation_id'));
                return route.fulfill({ json: path !== 'history' ? { conversation: state.records.get(id) }
                    : url.searchParams.has('resume') ? { account_id: 7, workspace: state.workspace, conversation: records.find(item => item.id === id) || records[0] || null }
                    : { conversations: records } });
            }
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="http://athena-rrl.test">
                <style>[x-cloak]{display:none!important}</style><script>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};</script>
                <script type="module" src="/${manifest['resources/js/app.js'].file}"></script></head>
                <body x-data data-app-shell data-auth-user-id="7" data-active-workspace="${state.workspace}"
                    data-research-assistant-history-url="/history" data-research-assistant-url="/chat" data-research-assistant-workspace-url="/workspace">
                ${state.workspace === 'faculty_researcher' ? `<div data-assistant-report-context='{"report_type":"progress-report","topic_id":11}'></div>` : ''}
                <button @click="$store.researchAssistant.openDrawer()">Open floating chat</button>
                <main data-assistant-workspace x-show="$store.researchAssistant.drawerOpen || $store.researchAssistant.workspaceOpen" x-cloak>
                <template x-for="message in $store.researchAssistant.messages" :key="message.id"><div><p x-text="message.content"></p>${compiled.stdout}</div></template>
                <textarea x-model="$store.researchAssistant.draft" aria-label="Message"></textarea>
                <button @click="$store.researchAssistant.send()" :disabled="$store.researchAssistant.isLoading">Send</button>
                <p role="alert" x-text="$store.researchAssistant.error"></p></main></body></html>` });
        });
        await page.goto('http://athena-rrl.test/reports/progress');
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await page.getByRole('textbox', { name: 'Message', exact: true }).fill(request);
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        const switchButton = page.getByRole('button', { name: 'Choose proposal in faculty workspace' });
        await expect(switchButton).toBeVisible();
        await page.getByRole('textbox', { name: 'Message', exact: true }).fill('Keep my unsent report question');
        await switchButton.click();
        assert.equal(state.switches, 0);
        await expect(page.getByRole('textbox', { name: 'Message', exact: true })).toHaveValue('Keep my unsent report question');
        await page.getByRole('textbox', { name: 'Message', exact: true }).fill('');
        await switchButton.click();
        await expect(page).toHaveURL('http://athena-rrl.test/proposals');
        await page.getByRole('button', { name: 'bibe girl', exact: true }).click();
        await expect(page.getByText('What would you like to study regarding “bibe girl”?', { exact: true })).toBeVisible();
        await page.getByRole('textbox', { name: 'Message', exact: true }).fill('just focus more on gamified studying');
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await expect(page.getByRole('article', { name: 'Editable RRL draft' })).toBeVisible();
        await expect(page.getByText('Gamified studying', { exact: true })).toBeVisible();
        assert.equal(state.calls.filter(call => call.action?.type === 'confirm_literature').length, 0);
        const edited = 'I reviewed the study’s design and findings on gamified learning. These findings offer a starting point for examining engagement in this proposed study, while the original design limits causal claims.';
        await page.getByRole('textbox', { name: 'RRL paragraph based on Study s1', exact: true }).fill(edited);
        await expect.poll(() => [...state.records.values()].some(record => record.messages.some(message => message.literature?.drafts?.[0]?.paragraph === edited))).toBe(true);
        const requestsBeforeRefresh = state.calls.length;
        await page.reload();
        await page.getByRole('button', { name: 'Open floating chat' }).click();
        await expect(page.getByRole('textbox', { name: 'RRL paragraph based on Study s1', exact: true })).toHaveValue(edited);
        assert.equal(state.calls.length, requestsBeforeRefresh);
        await page.evaluate(() => { window.addEventListener('athena-insert-literature', event => {
            window.reviewedInsertion = event.detail.sources; event.detail.handled = true; event.detail.added = true;
        }); });
        await page.getByRole('button', { name: 'Add to proposal', exact: true }).click();
        await expect.poll(() => state.calls.filter(call => call.action?.type === 'confirm_literature').length).toBe(1);
        const confirmation = state.calls.at(-1);
        assert.deepEqual(confirmation.action.drafts, [{ draft_token: 'draft-1', paragraph: edited }]);
        assert.equal(confirmation.context.proposal_draft_id, 42);
        assert.equal(confirmation.context.report_type, undefined);
        await expect.poll(() => page.evaluate(() => window.reviewedInsertion?.[0]?.rrl_note)).toBe(edited);
        assert.equal([...state.records.values()].filter(record => record.context.workspace === 'faculty_researcher').length, 1);
        assert.equal(state.switches, 1);
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});


test('draft recovery opens PDF upload or an editable search without repeating failed automatic results', async () => {
    for (const onPaper of [false, true]) {
        const calls = [];
        const editor = { dataset: { proposalDraftId: '42' } };
        const paper = { async openSourceWorkspace(tab) { calls.push(['tab', tab]); this.sourceWorkspaceQuery = 'Old title'; },
            setSourceWorkspaceAddMode(mode) { calls.push(['mode', mode]); } };
        const browser = { document: { querySelector: () => onPaper ? editor : null },
            location: { origin: 'https://athena.test', assign: url => calls.push(['url', url]) }, Alpine: { $data: () => paper } };
        const store = { ...assistant(), ...researchAssistantLiterature({ browser }), closeDrawer() {}, closeWorkspace() {},
            hasConversation: () => true, saveConversation: async () => true };
        const message = resultsMessage({ topic: 'Gamified studying', editor_url: 'https://athena.test/proposals/42/paper' });
        assert.equal(await store.openLiteratureSources(message, 'pdf'), true);
        assert.equal(await store.openLiteratureSources(message, 'search'), true);
        if (onPaper) {
            assert.deepEqual(calls, [['tab', 'add'], ['mode', 'pdf'], ['tab', 'search']]);
            assert.equal(paper.sourceWorkspaceQuery, 'Gamified studying');
        } else {
            const upload = new URL(calls[0][1]); const search = new URL(calls[1][1]);
            assert.equal(upload.searchParams.get('sources'), 'pdf');
            assert.equal(search.searchParams.get('sources'), 'search');
            assert.equal(search.searchParams.get('source-query'), 'Gamified studying');
        }
    }
});

test('RRL handoff requests expire, stay account-bound, and wait for unfinished input', async () => {
    const entries = new Map(); let requests = 0;
    const browser = { document: { body: { dataset: { authUserId: '7', activeWorkspace: 'faculty' } } },
        sessionStorage: { getItem: key => entries.get(key), setItem: (key, value) => entries.set(key, value), removeItem: key => entries.delete(key) } };
    const store = { ...assistant(), ...researchAssistantLiterature({ browser }), openDrawer() {},
        async ensureConversationContext() { return true; }, async sendPrompt() { requests++; return true; } };
    const key = store.literatureRequestKey();
    for (const created_at of [Date.now() - 11 * 60 * 1000, Date.now() + 60 * 1000]) {
        entries.set(key, JSON.stringify({ request: 'Draft my RRL.', intent: 'draft', created_at }));
        assert.equal(await store.restoreLiteratureRequest(), false);
        assert.equal(entries.has(key), false);
    }
    store.rememberLiteratureRequest({ request: 'Draft my RRL.', intent: 'draft' });
    browser.document.body.dataset.authUserId = '8';
    assert.equal(await store.restoreLiteratureRequest(), false);
    browser.document.body.dataset.authUserId = '7'; store.draft = 'Keep this message';
    assert.equal(await store.restoreLiteratureRequest(), false);
    assert.equal(requests, 0);
    assert.equal(store.draft, 'Keep this message');
    store.draft = '';
    assert.equal(await store.resumeLiteratureRequest(), true);
    assert.equal(requests, 1);
});


test('a failed handoff write cannot replay an older RRL request in another workspace', async () => {
    const entries = new Map([['athena:rrl-request:7', JSON.stringify({ request: 'An older topic', intent: 'draft', created_at: Date.now() })]]);
    let switches = 0;
    const browser = { document: { body: { dataset: { authUserId: '7', activeWorkspace: 'faculty_researcher', researchAssistantWorkspaceUrl: '/workspace' } }, querySelector: () => ({ content: 'csrf' }) },
        location: { origin: 'https://athena.test', assign() { assert.fail('The new request was not retained'); } },
        sessionStorage: { getItem: key => entries.get(key), setItem() { throw new Error('Quota exceeded'); }, removeItem: key => entries.delete(key) },
        fetch: async () => { switches++; return { ok: true, json: async () => ({ redirect: 'https://athena.test/proposals' }) }; } };
    const store = { ...assistant(), ...researchAssistantLiterature({ browser }), hasConversation: () => true, saveConversation: async () => true };
    const message = { literature: normalizeAssistantLiterature({ kind: 'choose_proposal', intent: 'draft', request: 'Draft the new topic.', workspace_required: true, proposals_url: 'https://athena.test/proposals' }) };
    assert.equal(await store.switchLiteratureWorkspace(message), false);
    assert.equal(switches, 0);
    assert.equal(entries.has('athena:rrl-request:7'), false);
});
