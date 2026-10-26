import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { chromium, expect } from '@playwright/test';

function workspace(kind = 'finder') {
    const result = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        if (($argv[1] ?? 'finder') === 'proposal') {
            echo view('faculty.proposal-drafts.detailed-proposal.partials.literature-workspace')->render();
        } else {
            Illuminate\Support\Facades\Auth::setUser(App\Models\User::factory()->make(['id' => 7]));
            $drafts = collect([(new App\Models\ProposalDraft)->forceFill(['id' => 42, 'user_id' => 7, 'project_title' => 'Community coastal monitoring'])]);
            if (($argv[1] ?? '') === 'pickers') {
                $drafts->push((new App\Models\ProposalDraft)->forceFill(['id' => 43, 'user_id' => 8, 'project_title' => 'Mobile Nutrition Tracking App for Pregnant and Lactating Mothers in Rural Coastal Communities'])->setRelation('owner', (new App\Models\User)->forceFill(['name' => 'Djanisse Villaflor'])));
                foreach (range(44, 70) as $id) {
                    $drafts->push((new App\Models\ProposalDraft)->forceFill(['id' => $id, 'user_id' => 7, 'project_title' => 'Research proposal '.$id]));
                }
            }
            echo Illuminate\Support\Facades\Blade::render('<x-rrl-finder :proposal-drafts="$drafts" />', ['drafts' => ($argv[1] ?? '') === 'empty' ? collect() : $drafts]);
        }
    `, kind], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(result.status, 0, (result.stderr || result.stdout).slice(-3000));
    return result.stdout;
}

const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css]
    .map((file) => readFileSync(resolve('public/build', file), 'utf8')).join('\n');
const app = (readFileSync(resolve('resources/js/app.js'), 'utf8') + '\n' + readFileSync(resolve('resources/js/detailed-proposal-editor.js'), 'utf8').replace(/^ {4}/gm, ''));
const storeStart = app.indexOf("Alpine.store('literatureSearch'");
const store = app.slice(storeStart, app.indexOf('\n});', storeStart) + '\n});'.length);
const helpers = readFileSync(resolve('resources/js/proposal-literature-search.js'), 'utf8').replace(/^export /gm, '');
const results = [
    { title: 'Community monitoring of mangrove restoration', authors: 'Maria Santos', year: 2025, source: 'OpenAlex', venue: 'Coastal Research', doi: '10.1234/mangrove', citation_count: 14, description: 'Community participation improves monitoring of restored mangroves.', access_status: 'metadata_only' },
    { title: 'Participation in coastal conservation', authors: 'Ana Reyes', year: 2024, source: 'Crossref', venue: 'Marine Studies', doi: '10.1234/coastal', citation_count: 8, description: 'A study of community participation in coastal conservation.', access_status: 'metadata_only' },
];

function finderStore(request, kind = 'literature') {
    let state;
    const selectedStoreStart = app.indexOf(`Alpine.store('${kind}Search'`);
    const selectedStore = app.slice(selectedStoreStart, app.indexOf('\n});', selectedStoreStart) + '\n});'.length);
    const navigations = [];
    const dataset = {
        literatureLibrarySaveUrl: '/library',
        literatureAttachUrlTemplate: '/proposals/__proposal__/sources/__source__',
        literatureSynthesisUrl: '/synthesis',
        literatureFullTextPreviewUrl: '/full-text',
        literatureDraftUpdateUrlTemplate: '/proposals/__proposal__/links/__link__/draft',
        detailedProposalUrlTemplate: '/proposals/__proposal__/paper',
    };
    runInNewContext(`${helpers}\n${selectedStore}`, {
        Alpine: { store(name, value) { state = value; } },
        document: { querySelector: (selector) => selector === '[data-rrl-workspace]' ? { dataset } : { content: 'token' }, body: { dataset: { literatureSearchUrl: '/search', conferenceSearchUrl: '/conference-search' }, classList: { add() {}, remove() {} } } },
        window: { location: { origin: 'https://athena.test', assign: (url) => navigations.push(url) }, setTimeout() {}, setInterval() { return 1; }, clearInterval() {} },
        fetch: request,
        URL,
    });
    state.selectedProposalId = '42';
    return { state, navigations };
}

const searchCases = [['literature', false], ['literature', true], ['conference', false], ['conference', true]];

for (const [kind, failed] of searchCases) {
    test(`clearing ${kind} search discards a pending ${failed ? 'failure' : 'result'}`, async () => {
        let finishSearch;
        let failSearch;
        const { state } = finderStore(() => new Promise((resolve, reject) => { finishSearch = resolve; failSearch = reject; }), kind);
        state.query = 'coastal monitoring';
        const pending = state.search();
        state.clear();
        if (failed) failSearch(new Error('Old search failed'));
        else finishSearch(new Response(JSON.stringify({ results, provider_notice: 'Old provider notice' })));
        await pending;

        assert.equal(state.query, '');
        assert.equal(state.hasSearched, false);
        assert.equal(state.results.length, 0);
        assert.equal(state.selectedIndex ?? null, null);
        assert.equal(state.error, '');
        assert.equal(state.providerNotice || '', '');
        assert.equal(state.isLoading, false);
        assert.equal(state.loadingTimer, null);
    });
}

for (const [kind, failed] of searchCases) {
    test(`a cleared ${kind} search cannot interrupt its replacement with an old ${failed ? 'failure' : 'result'}`, async () => {
        const requests = [];
        const { state } = finderStore(() => new Promise((resolve, reject) => requests.push({ resolve, reject })), kind);
        state.query = 'coastal monitoring';
        const first = state.search();
        state.clear();
        assert.equal(state.isLoading, false, 'Clear must let the user search again immediately');
        state.query = 'learning outcomes';
        const second = state.search();
        assert.equal(requests.length, 2);

        if (failed) {
            requests[1].resolve(new Response(JSON.stringify({ results: [{ title: 'Learning outcomes study' }] })));
            await second;
            requests[0].resolve(new Response(JSON.stringify({ message: 'Old search failed', failed_sources: ['Old provider'] }), { status: 503 }));
            await first;
        } else {
            requests[0].resolve(new Response(JSON.stringify({ results, provider_notice: 'Old provider notice' })));
            await first;
            assert.equal(state.isLoading, true);
            assert.notEqual(state.loadingTimer, null);
            assert.equal(state.results.length, 0);
            requests[1].resolve(new Response(JSON.stringify({ results: [{ title: 'Learning outcomes study' }] })));
            await second;
        }

        assert.equal(state.query, 'learning outcomes');
        assert.equal(state.results[0].title, 'Learning outcomes study');
        assert.equal(state.error, '');
        assert.equal(state.providerNotice || '', '');
        assert.equal(state.failedSources.length, 0);
        assert.equal(state.isLoading, false);
    });
}

test('switching literature results keeps the latest paper preview when an older extraction arrives last', async () => {
    const requests = [];
    const { state } = finderStore(() => new Promise((resolve) => requests.push({ resolve })));
    state.results = results.map((source, index) => ({ ...source, full_text_token: `paper-${index}` }));
    state.selectedIndex = 0;
    const first = state.loadFullText();
    state.selectResult(1);
    const second = state.loadFullText();
    requests[1].resolve(new Response(JSON.stringify({ preview_text: 'Second paper text', source_url: 'https://example.org/second', content_type: 'text/html', notice: 'Second paper coverage' })));
    await second;
    requests[0].resolve(new Response(JSON.stringify({ preview_text: 'First paper text', source_url: 'https://example.org/first', content_type: 'application/pdf', notice: 'First paper coverage' })));
    await first;

    assert.equal(state.fullTextPreview, 'Second paper text');
    assert.equal(state.fullTextSourceUrl, 'https://example.org/second');
    assert.equal(state.fullTextContentType, 'text/html');
    assert.equal(state.fullTextNotice, 'Second paper coverage');
    assert.equal(state.fullTextResultKey, state.resultKey(state.results[1]));
});

test('an old full-text failure cannot interrupt the next paper extraction', async () => {
    const requests = [];
    const { state } = finderStore(() => new Promise((resolve, reject) => requests.push({ resolve, reject })));
    state.results = results.map((source, index) => ({ ...source, full_text_token: `paper-${index}` }));
    state.selectedIndex = 0;
    const first = state.loadFullText();
    state.selectResult(1);
    const second = state.loadFullText();
    requests[0].reject(new Error('First extraction failed'));
    await first;

    assert.equal(state.fullTextError, '');
    assert.equal(state.isLoadingFullText, true);
    requests[1].resolve(new Response(JSON.stringify({ preview_text: 'Second paper text' })));
    await second;
    assert.equal(state.fullTextPreview, 'Second paper text');
    assert.equal(state.isLoadingFullText, false);
});

test('resetting literature search discards an extraction already in progress', async () => {
    let finishExtraction;
    const { state } = finderStore(() => new Promise((resolve) => { finishExtraction = resolve; }));
    state.results = [{ ...results[0], full_text_token: 'paper-0' }];
    state.selectedIndex = 0;
    const pending = state.loadFullText();
    state.resetFullTextPreview();
    finishExtraction(new Response(JSON.stringify({ preview_text: 'Discarded paper text' })));
    await pending;

    assert.equal(state.fullTextPreview, '');
    assert.equal(state.fullTextResultKey, '');
    assert.equal(state.isLoadingFullText, false);
});

test('RRL preparation saves and links once, then requires reviewed confirmation before inserting prose and its reference', async () => {
    const calls = [];
    const source = { ...results[0], description: 'Community participation supported monitoring across the coastal communities described in this study. Methods and limitations still require checking the full paper.' };
    const linked = { id: 12, literature_source_id: 8, ...source };
    const paragraph = 'The abstract describes community participation in coastal monitoring, providing a starting point for this study. The full paper must be reviewed to assess its methods and limitations.';
    const { state, navigations } = finderStore(async (url, options) => {
        const body = JSON.parse(options.body);
        calls.push({ url, body });
        const payload = url === '/library' ? { source: { id: 8, ...source } }
            : url === '/synthesis' ? { synthesis: paragraph, basis: 'abstract' }
                : { source: { ...linked, ...(body.rrl_note ? { rrl_note: body.rrl_note, rrl_draft_status: body.rrl_draft_status, rrl_evidence_basis: body.rrl_evidence_basis } : {}) } };
        return new Response(JSON.stringify(payload), { status: 200 });
    });
    await state.prepareSynthesis(source);
    assert.deepEqual(calls.map((call) => call.url), ['/library', '/proposals/42/sources/8', '/synthesis', '/proposals/42/links/12/draft']);
    assert.equal(state.sharedSources.length, 1);
    assert.equal(state.synthesisReviewOpen, true);
    assert.equal(state.synthesisSource.rrl_draft_status, 'draft');
    assert.deepEqual(calls[2].body, { proposal_draft_id: 42, source_link_id: 12, evidence_basis: 'full_text' });
    assert.deepEqual(navigations, []);
    state.synthesisDraft = `${paragraph} Reviewed wording.`;
    await state.confirmSynthesis();
    assert.equal(calls.at(-1).body.rrl_draft_status, 'confirmed');
    assert.equal(calls.at(-1).body.rrl_note, state.synthesisDraft);
    const destination = new URL(navigations[0]);
    assert.equal(destination.searchParams.get('literature_source'), '12');
    assert.equal(destination.searchParams.get('apply_to'), 'both');
    assert.equal(destination.hash, '#related-literature');
});

test('paper retrieval failure leaves an explicit abstract fallback and never confirms a draft', async () => {
    const calls = [];
    const source = { id: 8, ...results[0], description: 'Indexed abstract about community monitoring and participants, with enough detail for explicitly preliminary research notes.' };
    const linked = { ...source, id: 12, literature_source_id: 8 };
    const { state, navigations } = finderStore(async (url, options) => {
        const body = JSON.parse(options.body);
        calls.push({ url, body });
        if (url === '/synthesis' && body.evidence_basis === 'full_text') return new Response(JSON.stringify({ message: 'Attach a readable PDF or choose the abstract instead.' }), { status: 422 });
        return new Response(JSON.stringify(url === '/synthesis'
            ? { synthesis: 'An explicitly abstract-based preliminary paragraph for review before it is inserted in the paper.', basis: 'abstract' }
            : { source: { ...linked, ...body } }), { status: 200 });
    });
    await state.prepareSynthesis(source, true);
    assert.match(state.synthesisError, /Attach a readable PDF/);
    assert.equal(state.synthesisDraft, '');
    assert.deepEqual(navigations, []);
    assert.equal(calls.some((call) => call.body.rrl_draft_status), false);
    await state.generateSynthesis('abstract');
    assert.equal(state.synthesisBasis, 'abstract');
    assert.equal(calls.filter((call) => call.url === '/synthesis').at(-1).body.evidence_basis, 'abstract');
    assert.equal(calls.at(-1).body.rrl_draft_status, 'draft');
    assert.deepEqual(navigations, []);
});

test('reviewed paper insertion confirms the scoped source and always attaches a safe managed citation', async () => {
    const match = app.match(/    async insertSourceWorkspacePaperDraft\(draft\) \{([\s\S]*?)\n    \},/);
    assert.ok(match);
    const methods = new Function('config', `return ({${match[0]}});`)({ literatureDraftUpdateUrlTemplate: '/links/__proposal_literature_source__/draft' });
    const calls = [];
    const state = { ...methods, literatureSources: [{ id: 12 }], relatedLiterature: '<p>Existing review.</p>',
        plainText: (value) => String(value).replace(/<[^>]*>/g, ''),
        escapeLiteratureHtml: (value) => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'),
        sourceWorkspaceRequest: async (url, options) => { const body = JSON.parse(options.body); calls.push({ url, body }); return { source: { id: 12, ...body } }; },
        upsertLiteratureSource() {}, recordLiteratureCitation: () => 1,
        synchronizeLiteratureCitations() { this.synchronized = true; }, notifyLiteratureFieldChanged() {},
        markProposalPreviewStale() {}, triggerDetailedProposalAutoSave() { this.saved = true; },
    };
    const draft = { source_link_id: 12, basis: 'full_text', paragraph: 'Reviewed paper finding [1] with <img src=x onerror=alert(1)> shown literally.' };
    assert.equal(await state.insertSourceWorkspacePaperDraft(draft), true);
    assert.equal(calls[0].url, '/links/12/draft');
    assert.equal(calls[0].body.rrl_draft_status, 'confirmed');
    assert.match(state.relatedLiterature, /&lt;img/);
    assert.match(state.relatedLiterature, /data-proposal-citation="12"/);
    assert.equal(state.synchronized, true);
    assert.equal(state.saved, true);
});

test('adding a shared source to a proposal links it without generating or confirming RRL prose', async () => {
    const calls = [];
    const { state, navigations } = finderStore(async (url, options) => {
        calls.push({ url, body: JSON.parse(options.body) });
        return new Response(JSON.stringify({ source: { id: 12, literature_source_id: 8, ...results[0] } }), { status: 201 });
    });
    await state.useLibrarySource({ id: 8, ...results[0] }, 'reference');
    assert.deepEqual(calls, [{ url: '/proposals/42/sources/8', body: {} }]);
    assert.equal(state.synthesisReviewOpen, false);
    assert.equal(state.synthesisDraft, '');
    assert.equal(new URL(navigations[0]).searchParams.get('apply_to'), 'reference');
});

test('a failed proposal link keeps its shared source and does not generate a draft or navigate', async () => {
    const { state, navigations } = finderStore(async (url) => new Response(JSON.stringify(url === '/library'
        ? { source: { id: 8, ...results[0] } } : { message: 'Forbidden' }), { status: url === '/library' ? 201 : 403 }));
    await state.prepareSynthesis(results[0]);
    assert.equal(state.sharedSources[0].id, 8);
    assert.equal(state.saveError, 'You no longer have permission to edit that proposal.');
    assert.equal(state.synthesisReviewOpen, false);
    assert.deepEqual(navigations, []);
    assert.equal(state.savingResultKeys.length, 0);
});

test('external source entry saves to the shared library without silently linking the previously selected proposal', async () => {
    const calls = [];
    const { state, navigations } = finderStore(async (url, options) => {
        calls.push({ url, body: JSON.parse(options.body) });
        return new Response(JSON.stringify({ source: { id: 8, ...results[0], reference: 'Unnumbered IEEE reference' } }), { status: 201 });
    });
    state.externalSource.title = results[0].title;
    state.externalSource.doi = results[0].doi;
    await state.saveExternalSource();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/library');
    assert.equal(state.externalReference, 'Unnumbered IEEE reference');
    assert.equal(state.saveNotice.includes('Choose Add to proposal'), true);
    assert.deepEqual(navigations, []);
});

test('literature search keeps optional controls quiet and works in both themes on mobile and desktop', async () => {
    const html = workspace();
    assert.equal(/<details|<summary/.test(html), false);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="csrf-token" content="test"><style>${styles}</style></head><body class="bg-slate-50 p-4 dark:bg-slate-950" data-literature-search-url="/literature-search"><main class="mx-auto max-w-6xl">${html}</main></body></html>`);
                await page.addScriptTag({ content: `${helpers}
                    window.fetch = async (url, options) => {
                        window.searchRequest = JSON.parse(options.body);
                        return new Promise((done) => { window.finishSearch = (payload) => done(new Response(JSON.stringify(payload), {status: 200, headers: {'Content-Type': 'application/json'}})); });
                    };
                    document.addEventListener('alpine:init', () => {
                        ${store}
                        Alpine.store('researchAssistant', { isLoading: false });
                    });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                const filters = page.locator('button[aria-controls="literature-search-filters"]');
                await filters.waitFor();
                await expect(filters).toHaveAttribute('aria-expanded', 'false');
                await expect(page.locator('#literature-search-filters')).toBeHidden();
                await expect(page.locator('#literature-external-source')).toBeHidden();
                assert.equal(await page.getByRole('button', { name: 'Search', exact: true }).isDisabled(), true);
                assert.equal(await page.getByText('Your first action is to search').count(), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-search-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await filters.focus();
                await page.keyboard.press('Enter');
                await expect(filters).toHaveAttribute('aria-expanded', 'true');
                await page.getByLabel('From year', { exact: true }).fill('2020');
                await page.getByLabel('Open access only', { exact: true }).check();
                await filters.click();
                await expect(page.locator('#literature-search-filters')).toBeHidden();
                await page.getByRole('button', { name: 'Add a source', exact: true }).click();
                await page.locator('#literature-external-source').waitFor({ state: 'visible' });
                assert.equal(await page.locator('#literature-external-source').isVisible(), true);
                await page.getByRole('button', { name: 'Add a source', exact: true }).click();
                await page.getByLabel('Search literature', { exact: true }).fill('Community mangrove restoration');
                await page.getByRole('button', { name: 'Search', exact: true }).click();
                await page.waitForFunction(() => Boolean(window.finishSearch));
                await expect(page.locator('[data-literature-loading]')).toBeVisible();
                assert.equal(await page.locator('[data-literature-loading]').innerText(), 'Searching academic papers…');
                const payload = await page.evaluate(() => window.searchRequest);
                assert.equal(payload.year_from, 2020);
                assert.equal(payload.open_access, true);
                await page.evaluate((records) => window.finishSearch({ results: records }), results);
                await page.locator('[data-rrl-results-workspace]').waitFor({ state: 'visible' });
                const second = page.locator('[data-rrl-results-table] button').nth(1);
                await second.focus();
                await page.keyboard.press('Enter');
                await expect(second).toHaveAttribute('aria-pressed', 'true');
                await expect(page.locator('[data-rrl-paper-details] h4')).toHaveText(results[1].title);
                await expect(page.locator('[data-rrl-paper-details]').getByRole('button', { name: 'Add to proposal', exact: true })).toHaveCount(1);
                await expect(page.locator('[data-rrl-paper-details]').getByRole('button', { name: 'Draft RRL', exact: true })).toHaveCount(1);
                assert.equal(await page.getByRole('button', { name: /Prepare for RRL|Create research notes|Add reference|Use both/ }).count(), 0);
                const collectionsToggle = page.getByRole('button', { name: 'Collections', exact: true });
                await expect(collectionsToggle).toHaveAttribute('aria-expanded', 'false');
                await collectionsToggle.click();
                await expect(page.locator('#literature-save-options')).toBeVisible();
                await expect(collectionsToggle).toHaveAttribute('aria-expanded', 'true');
                await expect(page.locator('#literature-proposal-draft')).toHaveCount(0);
                await collectionsToggle.click();
                await expect(page.locator('#literature-save-options')).toBeHidden();
                await expect(page.locator('#literature-publication-details')).toBeHidden();
                const publication = page.getByRole('button', { name: 'Publication details', exact: true });
                await publication.click();
                await expect(page.locator('#literature-publication-details')).toBeVisible();
                await publication.click();
                await expect(page.locator('#literature-publication-details')).toBeHidden();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.locator('[data-rrl-paper-details]').evaluate((element) => { element.scrollTop = 0; });
                    await page.locator('button[aria-controls="literature-publication-details"]').evaluate((element) => element.blur());
                    await page.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-results-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.getByLabel('Search literature', { exact: true }).fill('babylon');
                await page.getByRole('button', { name: 'Search', exact: true }).click();
                await expect(page.locator('[data-literature-loading]')).toBeVisible();
                await page.evaluate(() => window.finishSearch({
                    results: [],
                    query_guidance: { is_broad: true, message: '“babylon” is too broad.', suggestion: 'Add a subject, place, or time period.' },
                }));
                const hint = page.getByRole('status').filter({ hasText: '“babylon” is too broad.' });
                await expect(hint).toBeVisible();
                await expect(page.getByText('A more specific research query is needed', { exact: true })).toBeHidden();
                assert.equal(await hint.locator('svg').count(), 0);
                assert.equal(await hint.evaluate((element) => getComputedStyle(element).backgroundColor), 'rgba(0, 0, 0, 0)');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await hint.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-query-hint-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                await page.getByLabel('Search literature', { exact: true }).fill('Community mangrove restoration');
                await page.getByRole('button', { name: 'Search', exact: true }).click();
                await page.evaluate(() => window.finishSearch({ results: [] }));
                await expect(hint).toBeHidden();
                await expect(page.getByText('No literature records found', { exact: true })).toBeVisible();
                await page.getByRole('tab', { name: /Shared library/ }).click();
                await expect(page.locator('#shared-literature-library')).toBeVisible();
                await expect(page.locator('#literature-find-workspace')).toBeHidden();
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('saved papers remain readable and show proposal action errors in both themes and narrow layouts', async () => {
    const html = workspace();
    const sources = [
        { id: 8, ...results[0], url: 'https://example.test/papers/mangrove', added_by_name: 'Maria Santos', collections: [{ id: 3, name: 'Environment' }] },
        { id: 9, ...results[1], url: 'https://example.test/papers/coastal', added_by_name: 'Ana Reyes', collections: [] },
    ];
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1024, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="csrf-token" content="test"><style>${styles}</style></head><body class="bg-slate-50 p-4 dark:bg-slate-950"><main class="mx-auto max-w-6xl">${html}</main></body></html>`);
                await page.locator('[data-rrl-workspace]').evaluate((element, records) => {
                    element.dataset.librarySources = JSON.stringify(records);
                    element.dataset.libraryCollections = JSON.stringify([{ id: 3, name: 'Environment' }]);
                }, sources);
                await page.addScriptTag({ content: `${helpers}
                    window.fetch = async () => new Response(JSON.stringify({ message: 'Forbidden' }), { status: 403, headers: { 'Content-Type': 'application/json' } });
                    document.addEventListener('alpine:init', () => { ${store} Alpine.store('researchAssistant', { isLoading: false }); });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await page.getByRole('tab', { name: /Shared library/ }).click();
                const library = page.locator('#shared-literature-library');
                await expect(library.locator('article')).toHaveCount(2);
                await expect(library.getByRole('button', { name: 'Draft RRL', exact: true }).first()).toBeDisabled();
                const primary = library.getByRole('button', { name: 'Add to proposal', exact: true }).first();
                const publication = library.getByRole('link', { name: 'Read publication', exact: true }).first();
                await expect(publication).toHaveText('Read publication');
                await expect(publication).toHaveAttribute('href', sources[0].url);
                await expect(publication).toHaveAttribute('target', '_blank');
                await expect(publication.locator('svg')).toHaveCount(1);
                assert.notEqual(await publication.evaluate((element) => getComputedStyle(element).backgroundColor), 'rgba(0, 0, 0, 0)', 'The publication action needs a visible button background');
                const publicationBounds = await publication.boundingBox();
                assert.ok(publicationBounds.width >= 44 && publicationBounds.height >= 44, 'The publication action needs a usable touch target');
                const disabledColor = await primary.evaluate((element) => getComputedStyle(element).backgroundColor);
                await page.getByRole('button', { name: 'Proposal to use shared papers with', exact: true }).click();
                await page.getByRole('option', { name: /Community coastal monitoring/ }).click();
                await expect(primary).toBeEnabled();
                await expect.poll(() => primary.evaluate((element) => getComputedStyle(element).backgroundColor)).not.toBe(disabledColor);
                await primary.click();
                await expect(page.getByRole('alert').filter({ hasText: 'You no longer have permission to edit that proposal.' })).toBeVisible();
                await page.getByRole('button', { name: 'Filter shared papers by collection', exact: true }).click();
                await page.getByRole('option', { name: 'Environment', exact: true }).click();
                await expect(library.locator('article')).toHaveCount(1);
                await page.getByLabel('Search the shared literature library').fill('no matching paper');
                await expect(library.locator('article')).toHaveCount(0);
                await expect(library.getByText('No shared papers match these filters')).toBeVisible();
                await page.getByLabel('Search the shared literature library').fill('');
                await page.getByRole('button', { name: 'Filter shared papers by collection', exact: true }).click();
                await page.getByRole('option', { name: 'All collections', exact: true }).click();
                await expect(library.locator('article')).toHaveCount(2);
                await expect(library.locator('article').first()).toBeVisible();
                const actions = await library.locator('article button').evaluateAll((buttons) => buttons.map((button) => button.getBoundingClientRect().height));
                assert.ok(actions.every((height) => height >= 44), 'Paper actions need usable touch targets');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.evaluate(() => { Alpine.store('literatureSearch').saveError = ''; });
                    await page.getByLabel('Search the shared literature library').evaluate((element) => element.blur());
                    await page.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-library-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('literature pickers search full titles and owners, support keyboard selection, and stay synchronized', async () => {
    const html = workspace('pickers');
    const title = 'Mobile Nutrition Tracking App for Pregnant and Lactating Mothers in Rural Coastal Communities';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${styles}</style></head><body class="bg-slate-50 p-4 dark:bg-slate-950"><main class="mx-auto max-w-6xl">${html}</main></body></html>`);
                await page.locator('[data-rrl-workspace]').evaluate(element => {
                    element.dataset.libraryCollections = JSON.stringify([{ id: 3, name: 'Environment' }]);
                });
                await page.addScriptTag({ content: `${helpers} document.addEventListener('alpine:init', () => { ${store} Alpine.store('researchAssistant', { isLoading: false }); });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await page.getByRole('tab', { name: /Shared library/ }).click();
                const trigger = page.locator('#library-literature-proposal');
                await expect(trigger).toHaveAttribute('aria-haspopup', 'dialog', { timeout: 1000 });
                await trigger.click();
                const menu = page.getByRole('dialog', { name: 'Proposal to use shared papers with', exact: true });
                const search = menu.getByRole('combobox');
                await expect(search).toBeFocused();
                const option = menu.getByRole('option', { name: new RegExp(title) });
                await expect(option).toContainText(title);
                await expect(option).toContainText('Shared by Djanisse Villaflor');
                assert.equal(await menu.getByRole('listbox').evaluate(element => element.scrollHeight > element.clientHeight), true);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-proposal-menu-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await search.fill('villaflor');
                await expect(menu.getByRole('option')).toHaveCount(1);
                await search.press('ArrowDown');
                await search.press('Enter');
                await expect(menu).toBeHidden();
                await expect(trigger).toContainText(title);
                await expect(trigger).toBeFocused();
                assert.equal(await page.evaluate(() => Alpine.store('literatureSearch').selectedProposalId), '43');
                await trigger.click();
                await search.fill('no such proposal');
                await expect(menu.getByRole('option')).toHaveCount(0);
                await expect(menu.getByRole('status')).toContainText('No matches');
                await search.press('Enter');
                await expect(menu).toBeVisible();
                await search.press('Escape');
                await expect(menu).toBeHidden();
                await expect(trigger).toBeFocused();
                await page.evaluate(() => { Alpine.store('literatureSearch').selectedProposalId = '42'; });
                await expect(trigger).toContainText('Community coastal monitoring');
                await trigger.click();
                await search.press('Tab');
                await expect(menu).toBeHidden();
                await trigger.click();
                await menu.getByRole('option', { name: 'Choose a proposal', exact: true }).click();
                assert.equal(await page.evaluate(() => Alpine.store('literatureSearch').selectedProposalId), '');

                const collection = page.getByRole('button', { name: 'Filter shared papers by collection', exact: true });
                await collection.click();
                const collections = page.getByRole('dialog', { name: 'Filter shared papers by collection', exact: true });
                await collections.getByRole('combobox').fill('environ');
                await collections.getByRole('option', { name: 'Environment', exact: true }).click();
                assert.equal(await page.evaluate(() => Alpine.store('literatureSearch').libraryCollectionFilter), '3');
                await collection.click();
                await page.getByRole('heading', { name: 'Browse the shared library' }).click();
                await expect(collections).toBeHidden();
                await page.evaluate(() => {
                    Alpine.store('literatureSearch').collections.push({ id: 9, name: 'Public health' });
                    Alpine.store('literatureSearch').selectedCollectionId = '9';
                });
                await page.getByRole('tab', { name: 'Find papers', exact: true }).click();
                await page.evaluate(() => { Alpine.$data(document.querySelector('[data-rrl-workspace]')).saveOptionsOpen = true; });
                await expect(page.locator('#literature-collection')).toContainText('Public health');
                await expect(page.locator('#literature-proposal-draft')).toHaveCount(0);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
        const page = await browser.newPage();
        await page.setContent(workspace('empty'));
        await page.addScriptTag({ content: `${helpers} document.addEventListener('alpine:init', () => { ${store} });` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        await page.getByRole('tab', { name: /Shared library/ }).click();
        await expect(page.locator('#library-literature-proposal')).toBeDisabled();
        await expect(page.locator('#library-literature-proposal')).toContainText('No editable proposal');
    } finally {
        await browser.close();
    }
});

test('the proposal literature search uses an accessible button without triangle disclosures', async () => {
    const html = workspace('proposal');
    assert.equal(/<details|<summary/.test(html), false);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent('<main></main>');
        const search = await page.evaluate((markup) => new DOMParser().parseFromString(markup, 'text/html').querySelector('section[aria-label="Search literature"]').outerHTML, html);
        await page.setContent(`<style>${styles}</style><main class="p-4" x-data="{ literatureWorkspaceTab: 'search', literatureSearchQuery: '', literatureSearchLoading: false, literatureSearchUseContext: true, literatureSearchFilters: {}, literatureSearchError: '', literatureSearchNotice: '', literatureSearchResults: [], literatureSearchKeywords: [], literatureSearchHistory: [], literatureSearchContextOptions() { return []; } }">${search}</main>`);
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        const settings = page.getByRole('button', { name: 'Search settings', exact: true });
        await settings.waitFor();
        assert.equal(await settings.getAttribute('aria-expanded'), 'false');
        await settings.focus();
        await page.keyboard.press('Space');
        assert.equal(await settings.getAttribute('aria-expanded'), 'true');
        await page.locator('#proposal-literature-settings').waitFor({ state: 'visible' });
        assert.equal(await page.getByText('Include proposal context', { exact: true }).isVisible(), true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
