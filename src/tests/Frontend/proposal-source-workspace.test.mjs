import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { normalizeSourceEvidence, parseSourceImport, proposalSourceWorkspace, sourceWorkspaceSafeUrl } from '../../resources/js/proposal-source-workspace.js';

const firstId = '7c9ae400-6fde-4f0e-9af0-934c22005a41';
const secondId = 'ec8ce97a-8b83-4b6c-8d2d-82c9a37e8ac1';
const linkedSource = { id: 12, literature_source_id: 3, title: 'Community learning outcomes', authors: 'A. Researcher', year: 2025, url: 'https://example.org/article' };
const quote = 'Community participation was associated with improved learning outcomes.';
const evidence = { source: linkedSource, document: { name: 'study.pdf', url: 'https://athena.test/source/document', pages: [{ number: 2, text: quote }], coverage: { characters: quote.length, extracted_pages: 1, total_pages: 44, page_limit: 40, character_limit: 30000, limited: true }, notice: 'Only part of the PDF was extracted.' }, passages: [{ id: firstId, quote, note: 'Supports objective 2.', page: 2, kind: 'quote', origin: 'pdf' }, { id: secondId, quote: '', note: 'Check the sample size.', page: null, kind: 'note', origin: 'manual' }] };

function response(payload, ok = true, status = 200) { return { ok, status, json: async () => payload }; }
function workspace(request = async () => response({})) {
    return { ...proposalSourceWorkspace({ csrfToken: 'token', libraryUrl: '/library', storeUrl: '/library', searchUrl: '/search', linkUrlTemplate: '/proposal/sources/__literature_source__', evidenceBase: '/proposal/links', assistanceUrl: '/proposal/assistance', metadataUrl: '/metadata', request }), literatureSources: [{ ...linkedSource }], sourceWorkspaceCanCite: () => true, upsertLiteratureSource(source) { const index = this.literatureSources.findIndex((item) => item.id === source.id); if (index === -1) this.literatureSources.push(source); else this.literatureSources[index] = source; } };
}
function collectEvidence(state) { state.sourceWorkspaceAcceptEvidence(12, evidence); state.sourceWorkspaceActiveSourceId = 12; state.toggleSourceWorkspacePassage(12, state.sourceWorkspaceEvidence().passages[0]); }

test('reader normalization preserves UUID passage IDs, explicit origin, pages and safe private PDF URLs', () => {
    const normalized = normalizeSourceEvidence({ ...evidence, passages: [...evidence.passages, { id: 'invalid', quote }] });
    assert.equal(normalized.passages.length, 2);
    assert.equal(normalized.passages[0].id, firstId);
    assert.equal(normalized.passages[0].origin, 'pdf');
    assert.equal(normalized.document.pages[0].number, 2);
    assert.equal(normalized.document.coverage.limited, true);
    assert.equal(normalizeSourceEvidence({ document: { url: 'javascript:alert(1)', pages: [] } }).document.url, '');
    for (const url of ['javascript:alert(1)', '//evil.test', 'https://user:password@example.org', 'data:text/html,attack']) assert.equal(sourceWorkspaceSafeUrl(url), '');
});

test('BibTeX and RIS imports produce reviewable metadata across multiple publications', () => {
    const bib = parseSourceImport('@article{one,title={Community {learning} study},author={Santos, Maria and Reyes, Ana},year={2025},journal={Learning Research},doi={10.1234/one},url={javascript:alert(1)}}\n@book{two,title="Coastal monitoring",year=2024,publisher={Research Press}}', 'bibtex');
    assert.equal(bib.length, 2);
    assert.equal(bib[0].title, 'Community learning study');
    assert.equal(bib[0].authors, 'Maria Santos, Ana Reyes');
    assert.equal(bib[0].url, '');
    assert.equal(bib[1].publisher, 'Research Press');
    const ris = parseSourceImport('TY  - JOUR\nTI  - Community evidence\nAU  - Maria Santos\nAU  - Ana Reyes\nPY  - 2025/01/10\nDO  - 10.1234/ris\nER  -\nTY  - BOOK\nTI  - Another study\nER  -', 'ris');
    assert.equal(ris.length, 2);
    assert.equal(ris[0].authors, 'Maria Santos, Ana Reyes');
    assert.equal(ris[0].year, '2025');
    assert.deepEqual(parseSourceImport('not bibliography', 'bibtex'), []);
});

test('DOI metadata lookup uses server metadata while publication URLs require manual review', async () => {
    const calls = [];
    const state = workspace(async (url, options) => { calls.push({ url, body: JSON.parse(options.body) }); return response({ source: { title: 'Publisher title', authors: ['A. Author'], year: 2025 }, notice: 'Review metadata.' }); });
    state.sourceWorkspaceIdentifier = 'https://doi.org/10.1234/example';
    assert.equal(await state.lookupSourceWorkspaceIdentifier(), true);
    assert.deepEqual(calls[0], { url: '/metadata', body: { identifier: '10.1234/example' } });
    assert.equal(state.sourceWorkspaceSourceForm.authors, 'A. Author');
    state.sourceWorkspaceIdentifier = 'https://example.org/publication';
    assert.equal(await state.lookupSourceWorkspaceIdentifier(), true);
    assert.equal(calls.length, 1);
    assert.equal(state.sourceWorkspaceSourceForm.title, '');
    assert.equal(state.sourceWorkspaceSourceForm.url, 'https://example.org/publication');
});

test('reviewed source creation saves metadata then links it to the current paper', async () => {
    const calls = [];
    const state = workspace(async (url, options) => { calls.push({ url, body: JSON.parse(options.body) }); return response({ source: url === '/library' ? { id: 21, title: 'Reviewed manual source' } : { id: 52, literature_source_id: 21, title: 'Reviewed manual source' } }); });
    state.sourceWorkspaceSourceForm = { title: 'Reviewed manual source', authors: 'A. Author', year: '2025', url: 'https://example.org/source', source: 'Manual entry' };
    assert.equal((await state.saveSourceWorkspaceMetadata()).id, 52);
    assert.equal(calls[0].url, '/library');
    assert.equal(calls[0].body.year, 2025);
    assert.equal(calls[1].url, '/proposal/sources/21');
    assert.equal(state.literatureSources.at(-1).id, 52);
});

test('academic search retains proposal context and year/open-access filters', async () => {
    let body;
    const state = workspace(async (url, options) => { body = JSON.parse(options.body); return response({ results: [] }); });
    state.suggestedLiteratureContext = () => [{ value: 'Objective 2: improve learning outcomes.' }];
    state.sourceWorkspaceQuery = 'community learning';
    state.sourceWorkspaceSearchFilters = { year_from: '2020', year_to: '2026', open_access: true };
    await state.searchSourceWorkspaceAcademic();
    assert.deepEqual(body, { query: 'community learning', context: 'Objective 2: improve learning outcomes.', year_from: 2020, year_to: 2026, open_access: true });
});

test('PDF excerpts require their selected page while unreadable PDF fallback accepts labelled manual quotes', async () => {
    const calls = [];
    const state = workspace(async (url, options) => { calls.push({ url, body: JSON.parse(options.body) }); return response(evidence); });
    state.sourceWorkspaceActiveSourceId = 12;
    state.sourceWorkspaceAcceptEvidence(12, evidence);
    state.sourceWorkspaceQuote = quote;
    assert.equal(state.sourceWorkspaceCanSavePassage(), false);
    state.sourceWorkspacePassagePage = 2;
    assert.equal(state.sourceWorkspaceCanSavePassage(), true);
    await state.saveSourceWorkspacePassage();
    assert.deepEqual(calls[0].body, { quote, note: '', page: 2, kind: 'quote' });
    state.sourceWorkspaceAcceptEvidence(12, { ...evidence, document: { ...evidence.document, pages: [], coverage: { characters: 0 } } });
    state.sourceWorkspaceQuote = quote;
    state.sourceWorkspacePassagePage = null;
    assert.equal(state.sourceWorkspaceCanSavePassage(), true);
    state.sourceWorkspaceQuote = '';
    state.sourceWorkspaceNote = 'ab';
    assert.equal(state.sourceWorkspaceCanSavePassage(), false);
    state.sourceWorkspaceNote = 'Reading note';
    assert.equal(state.sourceWorkspaceCanSavePassage(), true);
});

test('AI evidence payload preserves immutable UUIDs and exact source association', async () => {
    let body;
    const state = workspace(async (url, options) => { body = JSON.parse(options.body); return response({ mode: 'synthesize', supported: true, draft: { paragraph: 'A reviewed synthesis connects the supplied excerpt to the research objectives.', can_insert: true, evidence: [{ source_link_id: 12, passage_id: firstId, page: 2, quote, title: linkedSource.title, kind: 'quote', origin: 'pdf' }] } }); });
    collectEvidence(state);
    state.sourceWorkspaceAiMode = 'synthesize';
    assert.equal(await state.prepareSourceWorkspaceAssistance(), true);
    assert.deepEqual(body, { mode: 'synthesize', evidence: [{ source_link_id: 12, passage_ids: [firstId] }] });
    assert.equal(state.sourceWorkspaceCanInsertDraft(), true);
    assert.equal(state.sourceWorkspaceDraft.evidence[0].passage_id, firstId);
});

test('notes alone can be explained, but cannot support claims or produce manuscript synthesis', () => {
    const state = workspace();
    state.sourceWorkspaceAcceptEvidence(12, evidence);
    state.toggleSourceWorkspacePassage(12, state.sourceWorkspaceEvidenceCache[12].passages[1]);
    assert.equal(state.sourceWorkspaceCanAssist(), true);
    state.sourceWorkspaceAiMode = 'support';
    state.sourceWorkspaceClaim = 'The intervention improves outcomes.';
    assert.equal(state.sourceWorkspaceCanAssist(), false);
    state.sourceWorkspaceAiMode = 'synthesize';
    assert.equal(state.sourceWorkspaceCanAssist(), false);
});

test('evidence changes and changed AI instructions invalidate old insertable drafts', async () => {
    const state = workspace(async () => response({ mode: 'synthesize', supported: true, draft: { paragraph: 'A synthesis grounded in selected excerpts supports this proposal and its objectives.', evidence: [], can_insert: true } }));
    collectEvidence(state);
    state.sourceWorkspaceAiMode = 'synthesize';
    await state.prepareSourceWorkspaceAssistance();
    assert.equal(state.sourceWorkspaceCanInsertDraft(), true);
    state.sourceWorkspaceInstruction = 'Different focus';
    assert.equal(state.sourceWorkspaceCanInsertDraft(), false);
    state.sourceWorkspaceInstruction = '';
    state.sourceWorkspaceAcceptEvidence(12, { ...evidence, passages: [] });
    assert.equal(state.sourceWorkspaceSelectedPassages.length, 0);
    assert.equal(state.sourceWorkspaceCanInsertDraft(), false);
});

test('unsupported and review-only responses cannot insert, while synthesis requires explicit insertion', async () => {
    let insertions = 0;
    const state = workspace(async () => response({ mode: 'synthesize', supported: false, draft: { paragraph: 'The supplied evidence does not support the requested claim and needs stronger sources.', evidence: [], can_insert: false } }));
    collectEvidence(state);
    state.sourceWorkspaceAiMode = 'synthesize';
    state.insertSourceWorkspaceDraft = async () => { insertions++; return true; };
    await state.prepareSourceWorkspaceAssistance();
    assert.equal(await state.insertReviewedSourceWorkspaceDraft(), false);
    assert.equal(insertions, 0);
    state.sourceWorkspaceDraft.can_insert = true;
    state.sourceWorkspaceDraftSignature = state.sourceWorkspaceAssistanceSignature();
    assert.equal(await state.insertReviewedSourceWorkspaceDraft(), true);
    assert.equal(insertions, 1);
    assert.equal(await state.insertReviewedSourceWorkspaceDraft(), false);
});

test('UUID passage deletion clears evidence selections and uses the exact server identifier', async () => {
    let requested;
    const state = workspace(async (url) => { requested = url; return response({ ...evidence, passages: [] }); });
    collectEvidence(state);
    await state.deleteSourceWorkspacePassage(evidence.passages[0]);
    assert.equal(requested, `/proposal/links/12/passages/${firstId}`);
    assert.deepEqual(state.sourceWorkspaceSelectedPassages, []);
});

test('passage selection enforces up to five sources and twelve excerpts with exact UUID identities', () => {
    const state = workspace();
    const passage = (source, index) => ({ id: `${String(source).padStart(8, '0')}-6fde-4f0e-9af0-${String(index).padStart(12, '0')}`, quote, page: 1, kind: 'quote', origin: 'manual' });
    state.literatureSources = Array.from({ length: 6 }, (_, index) => ({ id: index + 1, title: `Source ${index + 1}` }));
    for (const source of state.literatureSources) state.sourceWorkspaceAcceptEvidence(source.id, { passages: Array.from({ length: 4 }, (_, index) => passage(source.id, index + 1)) });
    for (const source of state.literatureSources.slice(0, 5)) assert.equal(state.toggleSourceWorkspacePassage(source.id, state.sourceWorkspaceEvidenceCache[source.id].passages[0]), true);
    assert.equal(state.toggleSourceWorkspacePassage(6, state.sourceWorkspaceEvidenceCache[6].passages[0]), false);
    for (const source of state.literatureSources.slice(0, 3)) for (const item of state.sourceWorkspaceEvidenceCache[source.id].passages.slice(1)) { if (state.sourceWorkspaceSelectedPassages.length < 12) state.toggleSourceWorkspacePassage(source.id, item); }
    assert.equal(state.sourceWorkspaceSelectedPassages.length, 12);
    assert.equal(state.toggleSourceWorkspacePassage(4, state.sourceWorkspaceEvidenceCache[4].passages[1]), false);
});

test('uploading a PDF uses multipart form without a JSON content type and clears obsolete selected evidence', async () => {
    let options;
    const state = workspace(async (url, requestOptions) => { assert.equal(url, '/proposal/links/12/document'); options = requestOptions; return response({ ...evidence, passages: [] }); });
    collectEvidence(state);
    const file = new File(['PDF content'], 'source.pdf', { type: 'application/pdf' });
    await state.sourceWorkspaceUploadDocument(12, file);
    assert.ok(options.body instanceof FormData);
    assert.equal(options.body.get('file').name, 'source.pdf');
    assert.equal('Content-Type' in options.headers, false);
    assert.deepEqual(state.sourceWorkspaceSelectedPassages, []);
});

test('citation insertion uses the linked source and stays available without an AI response', async () => {
    const state = workspace();
    let cited;
    state.citeSelectedText = async (source) => { cited = source; return true; };
    state.sourceWorkspaceOpen = true;
    assert.equal(await state.citeSourceWorkspaceSource(linkedSource), true);
    assert.equal(cited.id, 12);
    assert.equal(state.sourceWorkspaceOpen, false);
    assert.equal(state.sourceWorkspaceDraft.paragraph, '');
});

test('PDF selection enforces the server upload size of 8 MB', () => {
    const state = workspace();
    assert.equal(state.chooseSourceWorkspacePdf({ target: { files: [{ name: 'big.pdf', size: 8 * 1024 * 1024 + 1 }] } }), false);
    assert.match(state.sourceWorkspaceError, /8 MB/);
    assert.equal(state.chooseSourceWorkspacePdf({ target: { files: [{ name: 'paper.pdf', size: 8 * 1024 * 1024 }] } }), true);
});

test('changing tabs during a pending library request prevents stale results and resets loading', async () => {
    let finish;
    const state = workspace(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.searchSourceWorkspaceLibrary();
    await state.setSourceWorkspaceTab('paper');
    finish(response({ sources: [{ id: 45, title: 'Late result' }] }));
    assert.equal(await pending, false);
    assert.deepEqual(state.sourceWorkspaceLibraryResults, []);
    assert.equal(state.sourceWorkspaceLoading, false);
});

test('session redirects, validation errors, and malformed service responses leave actions retryable', async () => {
    for (const request of [async () => ({ ...response({}), redirected: true }), async () => response({ errors: { title: ['Enter publication title.'] } }, false, 422), async () => ({ ok: true, json: async () => { throw new Error('HTML login'); } })]) {
        const state = workspace(request);
        state.sourceWorkspaceIdentifier = '10.1234/example';
        assert.equal(await state.lookupSourceWorkspaceIdentifier(), false);
        assert.ok(state.sourceWorkspaceError);
        assert.equal(state.sourceWorkspaceLoading, false);
    }
});

test('compiled Sources panel supports reading evidence, synthesis review, focus, and both themes on mobile and desktop', async () => {
    const compiled = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Blade::render('<x-proposal-source-workspace />');
    `], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(compiled.status, 0, (compiled.stderr || compiled.stdout).slice(-3000));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const helpers = readFileSync(resolve('resources/js/proposal-source-workspace.js'), 'utf8').replace(/^export /gm, '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="bg-white dark:bg-slate-950"><main x-data="sourcesFixture"><button id="sources-opener" @click="openSourceWorkspace()">Sources</button>${compiled.stdout}</main></body></html>`);
                await page.addScriptTag({ content: `${helpers}
                    window.sourceRequests = []; window.sourceInsertions = 0;
                    document.addEventListener('alpine:init', () => Alpine.data('sourcesFixture', () => ({
                        ...proposalSourceWorkspace({ csrfToken: 'test', evidenceBase: '/proposal/links', assistanceUrl: '/assistance', libraryUrl: '/library', request: async (url, options) => {
                            window.sourceRequests.push({url, body: options.body ? JSON.parse(options.body) : null});
                            return {ok:true, json:async () => url === '/assistance' ? {mode:'synthesize',supported:true,draft:{paragraph:'This reviewed synthesis relates community participation to the research objectives and learning outcomes.',can_insert:true,evidence:[{source_link_id:12,passage_id:'${firstId}',page:2,quote:${JSON.stringify(quote)},title:'Community learning outcomes',kind:'quote',origin:'pdf'}]}} : ${JSON.stringify(evidence)} };
                        } }),
                        literatureSources: [${JSON.stringify(linkedSource)}], sourceWorkspaceCanCite: () => true,
                        citationPickerLocator: '', citationPickerSelection: { selectedText: 'Community participation improves outcomes.' },
                        upsertLiteratureSource(source) { this.literatureSources = [source]; },
                        citeSelectedText: async () => true,
                        insertSourceWorkspaceDraft: async (draft,destination) => {window.sourceInsertions++;window.sourceInsertedDraft={draft,destination};return true;},
                    })));` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await page.getByRole('button', { name: 'Sources', exact: true }).click();
                await expect(page.getByRole('dialog', { name: 'Sources', exact: true })).toBeVisible();
                assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
                await expect(page.getByRole('button', { name: 'Close Sources', exact: true })).toBeFocused();
                await page.getByLabel('Page or section (optional)', { exact: true }).fill('p. 2');
                assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).citationPickerLocator), 'p. 2');
                await page.getByRole('button', { name: 'Close Sources', exact: true }).focus();
                await page.keyboard.press('Shift+Tab');
                await expect(page.getByLabel('What should Athena focus on?', { exact: true })).toBeFocused();
                await page.getByRole('button', { name: 'Add source', exact: true }).click();
                await page.locator('summary').filter({ hasText: 'Reference details' }).click();
                await page.getByLabel('Publication type', { exact: true }).selectOption('report');
                await page.getByLabel('Volume', { exact: true }).fill('12');
                await page.getByLabel('Issue', { exact: true }).fill('3');
                await page.getByLabel('Pages', { exact: true }).fill('14–28');
                await page.getByLabel('Publisher', { exact: true }).fill('Research Press');
                assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).sourceWorkspaceSourceForm.publisher), 'Research Press');
                await page.getByRole('button', { name: 'This paper', exact: true }).click();
                await page.getByRole('button', { name: 'Read & take notes', exact: true }).click();
                await expect(page.locator('[data-source-page-text]')).toHaveText(quote);
                await expect(page.getByText('Only part of the PDF was extracted.', { exact: true })).toBeVisible();
                await page.locator('[data-source-page-text]').evaluate((element) => { const range = document.createRange(); range.selectNodeContents(element); const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range); element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true })); });
                await expect(page.getByLabel('Selected PDF excerpt', { exact: true })).toHaveValue(quote);
                await page.getByRole('button', { name: 'Save excerpt or note', exact: true }).click();
                const passageRequest = await page.evaluate(() => window.sourceRequests.find((request) => request.url.endsWith('/passages')));
                assert.deepEqual(passageRequest.body, { quote, note: '', page: 2, kind: 'quote' });
                const checkbox = page.getByRole('checkbox', { name: `Use excerpt ${firstId} for AI help`, exact: true });
                await checkbox.check();
                await page.getByLabel('AI help', { exact: true }).selectOption('synthesize');
                await page.getByRole('button', { name: 'Draft synthesis', exact: true }).click();
                const review = page.getByLabel('AI response', { exact: true });
                await expect(review).toBeVisible();
                assert.equal(await page.evaluate(() => window.sourceInsertions), 0);
                await review.fill('My edited synthesis explains the relationship between community participation and improved learning outcomes.');
                await page.getByLabel('Add reviewed text to', { exact: true }).selectOption('rationale');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await page.locator('section[x-ref="sourceWorkspacePanel"] > div.overflow-y-auto').evaluate((element) => { element.scrollTop = 0; });
                    await page.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, `sources-review-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.getByRole('button', { name: 'Insert reviewed draft', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Reviewed draft inserted', exact: true })).toBeDisabled();
                assert.equal(await page.evaluate(() => window.sourceInsertedDraft.destination), 'rationale');
                assert.equal(await page.evaluate(() => window.sourceInsertedDraft.draft.evidence[0].passage_id), firstId);
                await page.getByRole('button', { name: 'Close Sources', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Sources', exact: true })).toBeFocused();
                assert.equal(await page.evaluate(() => document.body.style.overflow), '');
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally { await browser.close(); }
});
