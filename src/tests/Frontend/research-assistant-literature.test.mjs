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
        description: 'An abstract describing the participants, method, and research findings.',
        evidence_basis: 'abstract',
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
                draft_token: 'draft-1',
                paragraph: 'The study examines learning outcomes and supports the proposed research method.',
                relationship: 'Supports objective 2.',
            }],
            ...overrides,
        }),
    };
}

function assistant(overrides = {}) {
    return {
        ...researchAssistantLiterature(),
        isLoading: false,
        retryAfter: 0,
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
            study('metadata', { evidence_basis: 'metadata_only' }),
            study('blank', { description: '  ' }),
            study('unknown', { evidence_basis: 'unknown' }),
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
        results: [study('s1', { authors: [{ name: 'A. Researcher' }, 'B. Researcher'] }), study('s1'), study('s2', { evidence_basis: 'metadata_only' }), study('s3')],
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

    assert.deepEqual(Object.keys(persisted).sort(), ['kind', 'proposal_draft_id', 'proposal_title', 'notice', 'query', 'context_basis', 'results', 'year_from', 'year_to'].sort());
    assert.equal(persisted.year_from, 2021);
    assert.equal(persisted.year_to, 2026);
    assert.equal('is_working' in persisted, false);
    assert.equal('error' in persisted, false);
    assert.equal('ui_selected_source_tokens' in persisted, false);
    assert.equal(persisted.results[0].source_token, 's1');
    assert.deepEqual(Object.keys(serializeAssistantLiterature(draftMessage({ confirmed: true }).literature)).sort(), ['kind', 'proposal_draft_id', 'proposal_title', 'notice', 'drafts', 'confirmed'].sort());
    assert.deepEqual(Object.keys(serializeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, sources: [], editor_url: 'https://athena.test/paper', applied: true })).sort(), ['kind', 'proposal_draft_id', 'proposal_title', 'notice', 'sources', 'editor_url', 'applied'].sort());
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
    assert.deepEqual(calls[0][1], { type: 'draft_literature', source_tokens: ['s1', 's3'] });
    assert.deepEqual(calls[0][2], { proposal_draft_id: 42 });
    assert.equal(store.proposalDraftId, 99);
    assert.equal(message.literature.is_working, false);
    assert.equal(store.literatureOperationPending, false);
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

test('the shared component renders editable review and source content as plain text', async () => {
    const view = await readFile(new URL('../../resources/views/components/research-assistant-literature.blade.php', import.meta.url), 'utf8');

    assert.equal(view.includes('x-html='), false);
    assert.match(view, /x-model="draft\.paragraph"/);
    assert.match(view, /Add reviewed text to paper/);
    assert.match(view, /x-text="source\.description"/);
    assert.match(view, /rel="noopener noreferrer"/);
});

test('both assistant surfaces render the shared literature flow and pass starter prompt actions', async () => {
    for (const surface of ['workspace', 'drawer']) {
        const view = await readFile(new URL(`../../resources/views/components/research-assistant-${surface}.blade.php`, import.meta.url), 'utf8');
        assert.match(view, /<x-research-assistant-literature\s*\/>/);
        assert.match(view, /sendPrompt\(item\.prompt, item\.action \|\| null\)/);
    }
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
    const helpers = readFileSync(resolve('resources/js/research-assistant-literature.js'), 'utf8').replace(/^export /gm, '');
    const message = resultsMessage({
        notice: 'Review how each source supports your proposal.',
        results: [
            ...['s1', 's2', 's3', 's4'].map((token) => study(token)),
            study('metadata', { title: 'Metadata <img src=x onerror="window.untrustedMarkup=true">', url: 'javascript:alert(1)', evidence_basis: 'metadata_only', description: '' }),
        ],
    });
    const reviewedDraft = draftMessage();
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
                            ...researchAssistantLiterature(), isLoading: false, retryAfter: 0,
                            async sendPrompt(...args) {
                                window.literatureCalls.push(args);
                                return { literature: { kind: 'insertion', proposal_draft_id: 42 } };
                            },
                            openLiteraturePaper(message) { window.openedLiteratureProposal = message.literature.proposal_draft_id; },
                        });
                    });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await expect(page.getByText('Studies for your RRL', { exact: true })).toBeVisible();
                await expect(page.getByText('Metadata only', { exact: true })).toBeVisible();
                await expect(page.getByRole('checkbox', { name: 'Select study: Metadata', exact: false })).toBeDisabled();
                assert.equal(await page.locator('img').count(), 0);
                assert.equal(await page.locator('a[href^="javascript:"]').count(), 0);
                assert.equal(await page.evaluate(() => Boolean(window.untrustedMarkup)), false);
                await expect(page.getByRole('button', { name: 'Generate RRL draft', exact: true })).toBeDisabled();
                for (const token of ['s1', 's2', 's3']) await page.getByRole('checkbox', { name: `Select study: Study ${token}`, exact: true }).check();
                await expect(page.getByRole('checkbox', { name: 'Select study: Study s4', exact: true })).toBeDisabled();
                const firstSelection = page.getByRole('checkbox', { name: 'Select study: Study s1', exact: true });
                await firstSelection.focus();
                await page.keyboard.press('Space');
                await expect(firstSelection).not.toBeChecked();
                await firstSelection.check();
                await page.getByRole('button', { name: 'Generate RRL draft', exact: true }).click();
                await expect.poll(() => page.evaluate(() => window.literatureCalls.length)).toBe(1);
                const draftingCall = await page.evaluate(() => window.literatureCalls[0]);
                assert.deepEqual(draftingCall[1], { type: 'draft_literature', source_tokens: ['s2', 's3', 's1'] });
                assert.deepEqual(draftingCall[2], { proposal_draft_id: 42 });
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY, `assistant-literature-results-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }

                await page.evaluate((draft) => { Alpine.$data(document.querySelector('main')).message = draft; }, reviewedDraft);
                const paragraph = page.getByRole('textbox', { name: 'RRL paragraph based on Study s1', exact: true });
                await expect(paragraph).toBeVisible();
                await expect(page.getByText('Based on abstract', { exact: true })).toBeVisible();
                await paragraph.fill('Too short');
                await expect(page.getByRole('button', { name: 'Add reviewed text to paper', exact: true })).toBeDisabled();
                await paragraph.fill(editedParagraph);
                assert.equal(await page.evaluate(() => window.literatureCalls.length), 1);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.ASSISTANT_LITERATURE_SCREENSHOT_DIRECTORY, `assistant-literature-draft-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.getByRole('button', { name: 'Add reviewed text to paper', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Reviewed text added', exact: true })).toBeDisabled();
                const confirmingCall = await page.evaluate(() => window.literatureCalls[1]);
                assert.deepEqual(confirmingCall[1], { type: 'confirm_literature', drafts: [{ draft_token: 'draft-1', paragraph: editedParagraph }] });
                assert.deepEqual(confirmingCall[2], { proposal_draft_id: 42 });
                await expect(paragraph).toBeDisabled();

                await page.evaluate((packet) => { Alpine.$data(document.querySelector('main')).message = { id: 8, literature: packet }; }, normalizeAssistantLiterature({ kind: 'insertion', proposal_draft_id: 42, editor_url: 'https://athena.test/proposals/42/paper', notice: 'Open the paper to see the reviewed paragraphs.' }));
                await page.getByRole('button', { name: 'Open paper', exact: true }).click();
                assert.equal(await page.evaluate(() => window.openedLiteratureProposal), 42);

                await page.evaluate((empty) => { Alpine.$data(document.querySelector('main')).message = empty; }, resultsMessage({ results: [], notice: 'Try a more specific research term.' }));
                await expect(page.getByText('Try a more specific research term.', { exact: true })).toBeVisible();
                await expect(page.getByText('No studies found.', { exact: false })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Generate RRL draft', exact: true })).toBeDisabled();
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
