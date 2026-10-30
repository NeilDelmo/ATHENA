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
const linkedSource = { id: 12, literature_source_id: 3, title: 'Community learning outcomes', authors: 'A. Researcher', year: 2025, url: 'https://example.org/article', description: 'This study examines how community participation affects learning outcomes.' };
const librarySource = { id: 8, title: 'Community participation in schools', authors: 'B. Researcher', year: 2024, url: 'https://example.org/schools', description: 'A study of community involvement in school programmes.' };
const quote = 'Community participation was associated with improved learning outcomes.';
const evidence = { source: linkedSource, document: { name: 'study.pdf', url: 'https://athena.test/source/document', pages: [{ number: 2, text: quote }], coverage: { characters: quote.length, extracted_pages: 1, total_pages: 44, page_limit: 40, character_limit: 30000, limited: true }, notice: 'Only part of the PDF was extracted.' }, passages: [{ id: firstId, quote, note: 'Supports objective 2.', page: 2, kind: 'quote', origin: 'pdf' }, { id: secondId, quote: '', note: 'Check the sample size.', page: null, kind: 'note', origin: 'manual' }] };

function response(payload, ok = true, status = 200) { return { ok, status, json: async () => payload }; }
function workspace(request = async () => response({})) {
    return { ...proposalSourceWorkspace({ csrfToken: 'token', proposalDraftId: 42, synthesisUrl: '/synthesis', libraryUrl: '/library', storeUrl: '/library', searchUrl: '/search', linkUrlTemplate: '/proposal/sources/__literature_source__', evidenceBase: '/proposal/links', assistanceUrl: '/proposal/assistance', metadataUrl: '/metadata', request }), literatureSources: [{ ...linkedSource }], sourceWorkspaceCanCite: () => true, upsertLiteratureSource(source) { const index = this.literatureSources.findIndex((item) => item.id === source.id); if (index === -1) this.literatureSources.push(source); else this.literatureSources[index] = source; } };
}
function collectEvidence(state) { state.sourceWorkspaceAcceptEvidence(12, evidence); state.sourceWorkspaceActiveSourceId = 12; state.toggleSourceWorkspacePassage(12, state.sourceWorkspaceEvidence().passages[0]); }

for (const tab of ['library', 'search']) {
    test(`${tab} source previews do not save or link literature`, async () => {
        const calls = [];
        const state = workspace(async (url) => { calls.push(url); return response(evidence); });
        collectEvidence(state);
        state.sourceWorkspaceTab = tab;
        state.sourceWorkspaceCitationMode = tab === 'library';
        const source = { ...librarySource, id: 12 };

        assert.equal(await state.readSourceWorkspaceSource(source), true);
        assert.deepEqual(calls, []);
        assert.deepEqual(state.literatureSources, [linkedSource]);
        assert.equal(state.sourceWorkspaceView, 'reader');
        assert.equal(state.sourceWorkspaceActiveSource().title, librarySource.title);
        assert.equal(state.sourceWorkspaceActiveSourceId, 0);
        assert.deepEqual(state.sourceWorkspaceEvidence(), { document: null, passages: [] });
        assert.equal(state.sourceWorkspaceCanDraftPaper(), false);
        state.sourceWorkspaceNote = 'A note for this source';
        assert.equal(state.sourceWorkspaceCanSavePassage(), false);
        assert.equal(await state.uploadSourceWorkspaceReaderPdf({ target: { files: [new File(['PDF'], 'paper.pdf')] } }), false);
        assert.deepEqual(calls, []);

        state.sourceWorkspaceTab = 'paper';
        assert.equal(await state.readSourceWorkspaceSource(linkedSource), true);
        assert.deepEqual(calls, ['/proposal/links/12/evidence']);
        assert.equal(state.sourceWorkspaceActiveSource().title, linkedSource.title);
        assert.equal(state.sourceWorkspaceCanDraftPaper(), true);
    });
}

test('saving academic literature stores and links it once without opening its preview', async () => {
    const calls = [];
    const saved = { ...librarySource, id: 52, literature_source_id: 8 };
    const state = workspace(async (url, options) => {
        calls.push({ url, body: JSON.parse(options.body) });
        return response({ source: url === '/library' ? librarySource : saved });
    });
    state.sourceWorkspaceTab = 'search';

    assert.equal((await state.saveSourceWorkspaceSource(librarySource)).id, 52);
    assert.deepEqual(calls.map((call) => call.url), ['/library', '/proposal/sources/8']);
    assert.equal(calls[0].body.title, librarySource.title);
    assert.equal(state.sourceWorkspaceLinkedSource(librarySource).id, 52);
    assert.equal(state.sourceWorkspaceView, 'list');
    assert.equal((await state.saveSourceWorkspaceSource(librarySource)).id, 52);
    assert.equal(calls.length, 2);
    assert.equal(state.literatureSources.length, 2);
});

test('saved literature lists only this proposal bookmarks and searches all of them beyond the shared catalog limit', async () => {
    const calls = [];
    const state = workspace(async (url) => { calls.push(url); return response({ sources: [librarySource] }); });
    const bookmarks = Array.from({ length: 25 }, (_, index) => ({ ...linkedSource, id: index + 1, literature_source_id: index + 100, title: `Saved paper ${index + 1}`, is_saved: true }));
    state.literatureSources = [...bookmarks, { ...librarySource, id: 52, literature_source_id: 8, is_saved: false }];
    state.sourceWorkspaceLibraryResults = [librarySource];

    await state.setSourceWorkspaceTab('library');
    assert.deepEqual(state.sourceWorkspaceSources(), bookmarks);
    assert.deepEqual(calls, []);
    state.sourceWorkspaceQuery = 'Saved paper 25';
    assert.deepEqual(state.sourceWorkspaceSources(), [bookmarks[24]]);
    assert.equal(state.sourceWorkspaceLinkedSource(bookmarks[24]).id, 25);

    state.sourceWorkspaceCitationMode = true;
    state.sourceWorkspaceQuery = '';
    await state.setSourceWorkspaceTab('library');
    assert.deepEqual(state.sourceWorkspaceSources(), [librarySource]);
    assert.deepEqual(calls, ['/library?query=']);
});

test('bookmark toggles persist without discarding citations, PDF evidence, or selected passages', async () => {
    const calls = [];
    const state = workspace(async (url, options) => {
        const body = JSON.parse(options.body);
        calls.push({ url, method: options.method, body });
        return response({ source: { ...linkedSource, is_saved: body.is_saved } });
    });
    collectEvidence(state);
    state.literatureCitations = [{ source_link_id: 12, field: 'related-literature' }];
    state.sourceWorkspaceTab = 'search';
    const result = { ...linkedSource, _linkedSource: { ...linkedSource, is_saved: true } };
    state.linkedLiteratureSourceForResult = (source) => source._linkedSource;

    assert.equal((await state.toggleSourceWorkspaceSource(result)).is_saved, false);
    assert.equal(state.sourceWorkspaceSourceSaved(result), false);
    state.sourceWorkspaceTab = 'library';
    assert.deepEqual(state.sourceWorkspaceSources(), []);
    state.sourceWorkspaceTab = 'search';
    assert.equal((await state.toggleSourceWorkspaceSource(result)).is_saved, true);
    assert.equal(state.sourceWorkspaceSourceSaved(result), true);
    assert.deepEqual(calls, [
        { url: '/proposal/links/12/bookmark', method: 'PATCH', body: { is_saved: false } },
        { url: '/proposal/links/12/bookmark', method: 'PATCH', body: { is_saved: true } },
    ]);
    assert.equal(state.sourceWorkspaceEvidence().document.name, 'study.pdf');
    assert.equal(state.sourceWorkspaceSelectedPassages[0].passage_id, firstId);
    assert.deepEqual(state.literatureCitations, [{ source_link_id: 12, field: 'related-literature' }]);
    assert.equal(state.literatureSources.length, 1);
});

test('failed unsaving leaves the saved state and evidence intact and can be retried', async () => {
    const state = workspace(async () => response({ message: 'Could not save bookmark.' }, false, 503));
    collectEvidence(state);
    assert.equal(await state.toggleSourceWorkspaceSource(linkedSource), false);
    assert.equal(state.sourceWorkspaceSourceSaved(linkedSource), true);
    assert.equal(state.sourceWorkspaceEvidence().document.name, 'study.pdf');
    assert.equal(state.sourceWorkspaceSelectedPassages.length, 1);
    assert.equal(state.sourceWorkspaceSaving, false);
    assert.equal(state.sourceWorkspaceError, 'Could not save bookmark.');
});

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

for (const identifier of ['DOI: https://doi.org/10.1234/example', 'doi: https://dx.doi.org/10.1234/example']) {
    test(`labelled DOI links reach the metadata lookup: ${identifier}`, async () => {
        const calls = [];
        const state = workspace(async (url, options) => {
            calls.push({ url, body: JSON.parse(options.body) });
            return response({ source: { title: 'Publisher title' } });
        });
        state.sourceWorkspaceIdentifier = identifier;

        assert.equal(await state.lookupSourceWorkspaceIdentifier(), true);
        assert.deepEqual(calls, [{ url: '/metadata', body: { identifier: '10.1234/example' } }]);
        assert.equal(state.sourceWorkspaceSourceForm.title, 'Publisher title');
        assert.equal(state.sourceWorkspaceError, '');
    });
}

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

test('paper drafts request server-owned full text and require explicit reviewed insertion into RRL', async () => {
    const calls = [];
    const state = workspace(async (url, options) => {
        calls.push({ url, body: JSON.parse(options.body) });
        return response({ synthesis: 'The extracted paper describes community participation and its relationship to learning outcomes.', basis: 'full_text', notice: 'Some pages were unavailable.', coverage: { limited: true } });
    });
    collectEvidence(state);
    let inserted;
    state.insertSourceWorkspacePaperDraft = async (draft) => { inserted = draft; return true; };
    state.insertSourceWorkspaceDraft = async () => { throw new Error('Selected-evidence insertion must not be used.'); };
    assert.equal(await state.prepareSourceWorkspacePaperDraft(), true);
    assert.deepEqual(calls, [{ url: '/synthesis', body: { proposal_draft_id: 42, source_link_id: 12, evidence_basis: 'full_text' } }]);
    assert.equal(inserted, undefined);
    assert.equal(state.sourceWorkspaceDraftMode, 'paper');
    assert.equal(state.sourceWorkspaceDraft.coverage.limited, true);
    assert.equal(state.sourceWorkspaceDraft.notice, 'Some pages were unavailable.');
    assert.equal(state.sourceWorkspaceDestination, 'related-literature');
    state.sourceWorkspaceDestination = 'rationale';
    state.sourceWorkspaceDraft.paragraph = 'My reviewed paragraph retains the paper’s findings without overstating what it established.';
    assert.equal(await state.insertReviewedSourceWorkspaceDraft(), true);
    assert.equal(inserted.paragraph, state.sourceWorkspaceDraft.paragraph);
    assert.equal(inserted.source_link_id, 12);
    assert.equal(inserted.basis, 'full_text');
    assert.equal(await state.insertReviewedSourceWorkspaceDraft(), false);
});

test('unavailable paper text stops drafting without allowing an abstract fallback', async () => {
    const calls = [];
    const state = workspace(async (url, options) => {
        const body = JSON.parse(options.body);
        calls.push(body);
        return body.evidence_basis === 'full_text' ? response({ message: 'The PDF contains no readable body text. Attach a text PDF.' }, false, 422)
            : response({ synthesis: 'The abstract describes community participation, but the full methods and results require reading the paper.', basis: 'abstract', notice: 'Abstract only; full paper not read.', coverage: {} });
    });
    collectEvidence(state);
    assert.equal(await state.prepareSourceWorkspacePaperDraft('abstract'), false);
    assert.equal(await state.prepareSourceWorkspacePaperDraft(), false);
    assert.match(state.sourceWorkspaceError, /no readable body text/);
    assert.equal(state.sourceWorkspaceDraft.can_insert, false);
    assert.equal(calls.length, 1, 'A failed extraction must not silently use the abstract');
    assert.equal(await state.prepareSourceWorkspacePaperDraft('abstract'), false);
    assert.deepEqual(calls.map((body) => body.evidence_basis), ['full_text']);
    assert.equal(state.sourceWorkspaceDraft.can_insert, false);
});

test('abstract metadata never qualifies a source for abstract-based generation', async () => {
    const calls = [];
    const state = workspace(async (url, options) => {
        const body = JSON.parse(options.body);
        calls.push(body);
        return body.evidence_basis === 'full_text' ? response({ message: 'No accessible full text is available. Attach a source PDF.' }, false, 422)
            : response({ synthesis: 'This abstract-based draft describes the reported topic; full methods and findings still require reading the paper.', basis: 'abstract', notice: 'Based on the abstract only.' });
    });
    state.sourceWorkspaceActiveSourceId = 12;
    assert.equal(state.sourceWorkspaceEvidence().document, null);
    assert.equal(state.sourceWorkspaceActiveSource().full_text_url, undefined);
    assert.equal(state.sourceWorkspaceCanDraftPaper(), true);
    assert.equal(await state.prepareSourceWorkspacePaperDraft(), false);
    assert.equal(calls.length, 1);
    assert.equal(await state.prepareSourceWorkspacePaperDraft('abstract'), false);
    assert.deepEqual(calls.map((body) => body.evidence_basis), ['full_text']);
    assert.equal(state.sourceWorkspaceDraftInserted, false);
});

test('paper generation rejects unexpected evidence basis and stale source or evidence changes', async () => {
    let finish;
    const state = workspace(() => new Promise((resolve) => { finish = resolve; }));
    collectEvidence(state);
    const pending = state.prepareSourceWorkspacePaperDraft();
    state.sourceWorkspaceActiveSourceId = 99;
    finish(response({ synthesis: 'A late draft that must not become available for another paper source.', basis: 'full_text' }));
    assert.equal(await pending, false);
    assert.equal(state.sourceWorkspaceDraft.can_insert, false);
    state.sourceWorkspaceActiveSourceId = 12;
    const changedEvidence = state.prepareSourceWorkspacePaperDraft();
    state.sourceWorkspaceAcceptEvidence(12, { ...evidence, passages: [] });
    finish(response({ synthesis: 'A late draft that must not replace a draft whose document evidence changed.', basis: 'full_text' }));
    assert.equal(await changedEvidence, false);
    assert.equal(state.sourceWorkspaceCanInsertDraft(), false);
    const unexpectedBasis = state.prepareSourceWorkspacePaperDraft();
    finish(response({ synthesis: 'A draft supplied from an abstract without the researcher choosing that fallback.', basis: 'abstract' }));
    assert.equal(await unexpectedBasis, false);
    assert.match(state.sourceWorkspaceError, /incomplete paper draft/);
});

test('closing Sources aborts paper generation and ignores its late response', async () => {
    let finish, signal;
    const state = workspace((url, options) => { signal = options.signal; return new Promise((resolve) => { finish = resolve; }); });
    collectEvidence(state);
    state.sourceWorkspaceOpen = true;
    const pending = state.prepareSourceWorkspacePaperDraft();
    assert.equal(state.sourceWorkspaceAiLoading, true);
    assert.equal(state.closeSourceWorkspace(), true);
    assert.equal(signal.aborted, true);
    finish(response({ synthesis: 'A delayed response must never insert or reopen the closed source workspace.', basis: 'full_text' }));
    assert.equal(await pending, false);
    assert.equal(state.sourceWorkspaceOpen, false);
    assert.equal(state.sourceWorkspaceAiLoading, false);
    assert.equal(state.sourceWorkspaceDraft.can_insert, false);
});

test('a prepared paper draft becomes unusable after changing its source document or source identity', async () => {
    const state = workspace(async () => response({ synthesis: 'The extracted paper describes community participation and related learning outcomes.', basis: 'full_text' }));
    collectEvidence(state);
    assert.equal(await state.prepareSourceWorkspacePaperDraft(), true);
    state.sourceWorkspaceActiveSourceId = 99;
    assert.equal(state.sourceWorkspaceCanInsertDraft(), false);
    state.sourceWorkspaceActiveSourceId = 12;
    state.sourceWorkspaceAcceptEvidence(12, { ...evidence, document: { ...evidence.document, name: 'replacement.pdf' } });
    assert.equal(state.sourceWorkspaceCanInsertDraft(), false);
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

test('citation picker filters proposal sources and clears its selection when switching collections', async () => {
    const state = workspace();
    await state.openSourceWorkspace('paper', true);
    state.sourceWorkspaceQuery = 'researcher';
    assert.deepEqual(state.sourceWorkspaceSources(), [linkedSource]);
    state.sourceWorkspaceQuery = 'missing';
    assert.deepEqual(state.sourceWorkspaceSources(), []);
    state.sourceWorkspaceCitationSource = linkedSource;
    await state.setSourceWorkspaceTab('search');
    assert.equal(state.sourceWorkspaceCitationSource, null);
});

test('reviewed manual metadata can save and cite in one action without an AI or reader step', async () => {
    const state = workspace(async (url) => response({ source: url === '/library'
        ? { id: 21, title: 'Reviewed manual source' }
        : { id: 52, literature_source_id: 21, title: 'Reviewed manual source' } }));
    await state.openSourceWorkspace('add', true);
    state.sourceWorkspaceSourceForm.title = 'Reviewed manual source';
    state.citeSelectedText = async (source) => { assert.equal(source.id, 52); return true; };
    assert.equal((await state.saveSourceWorkspaceMetadata()).id, 52);
    assert.equal(state.sourceWorkspaceOpen, false);
    assert.equal(state.sourceWorkspaceDraft.paragraph, '');
});

test('closing citation picker clears the writing target and does not reuse it in research mode', async () => {
    const state = workspace();
    state.citationPickerSelection = { fieldId: 'related-literature', selectedText: 'Claim' };
    await state.openSourceWorkspace('paper', true);
    state.sourceWorkspaceCitationSource = linkedSource;
    assert.equal(state.closeSourceWorkspace(), true);
    assert.equal(state.citationPickerSelection, null);
    assert.equal(state.sourceWorkspaceCitationSource, null);
    await state.openSourceWorkspace('paper');
    assert.equal(state.sourceWorkspaceCitationMode, false);
});

test('changing the citation source clears an unrelated page and library tabs refresh the current query', async () => {
    const calls = [];
    const state = workspace(async (url) => { calls.push(url); return response({ sources: [] }); });
    await state.openSourceWorkspace('paper', true);
    state.sourceWorkspaceCitationSource = linkedSource;
    state.citationPickerLocator = 'p. 6';
    state.selectSourceWorkspaceCitation(linkedSource);
    assert.equal(state.citationPickerLocator, 'p. 6');
    state.selectSourceWorkspaceCitation({ id: 15, title: 'Different source' });
    assert.equal(state.citationPickerLocator, '');
    state.sourceWorkspaceQuery = 'learning';
    await state.setSourceWorkspaceTab('library');
    state.sourceWorkspaceQuery = 'monitoring';
    await state.setSourceWorkspaceTab('paper');
    await state.setSourceWorkspaceTab('library');
    assert.deepEqual(calls, ['/library?query=learning', '/library?query=monitoring']);
});

test('a failed Save and cite retains the saved source for retry without a second metadata save', async () => {
    const calls = [];
    const state = workspace(async (url) => { calls.push(url); return response({ source: url === '/library'
        ? { id: 21, title: 'Reviewed manual source' }
        : { id: 52, literature_source_id: 21, title: 'Reviewed manual source' } }); });
    await state.openSourceWorkspace('add', true);
    state.sourceWorkspaceSourceForm.title = 'Reviewed manual source';
    state.citeSelectedText = async () => false;
    assert.equal(await state.saveSourceWorkspaceMetadata(), false);
    assert.equal(state.sourceWorkspaceOpen, true);
    assert.equal(state.sourceWorkspaceCitationSource.id, 52);
    state.citeSelectedText = async () => true;
    assert.equal(await state.citeSourceWorkspaceSource(state.sourceWorkspaceCitationSource), true);
    assert.deepEqual(calls, ['/library', '/proposal/sources/21']);
});

test('PDF selection enforces the server upload size of 8 MB', () => {
    const state = workspace();
    assert.equal(state.chooseSourceWorkspacePdf({ target: { files: [{ name: 'big.pdf', size: 8 * 1024 * 1024 + 1 }] } }), false);
    assert.match(state.sourceWorkspaceError, /8 MB/);
    assert.equal(state.chooseSourceWorkspacePdf({ target: { files: [{ name: 'paper.pdf', size: 8 * 1024 * 1024 }] } }), true);
});

for (const change of ['replace PDF', 'edit authors']) {
    test(`a failed PDF upload retries the current source details after ${change}`, async () => {
        const metadata = [];
        const uploads = [];
        const shared = new Map();
        const linked = new Map();
        const state = workspace(async (url, options) => {
            if (url === '/library') {
                const fields = JSON.parse(options.body);
                metadata.push(fields);
                const source = { ...fields, id: fields.title === 'Replacement study' ? 22 : 21 };
                shared.set(source.id, source);
                return response({ source });
            }
            if (url.startsWith('/proposal/sources/')) {
                const sourceId = Number(url.split('/').at(-1));
                const source = { ...shared.get(sourceId), id: sourceId + 31, literature_source_id: sourceId };
                linked.set(source.id, source);
                return response({ source });
            }
            const sourceId = Number(url.split('/').at(-2));
            uploads.push({ sourceId, file: options.body.get('file').name });
            return uploads.length === 1 ? response({ message: 'Temporary upload failure' }, false, 503)
                : response({ source: linked.get(sourceId), passages: [] });
        });
        state.sourceWorkspaceTab = 'add';
        const original = new File(['PDF one'], 'Original study.pdf', { type: 'application/pdf' });
        state.chooseSourceWorkspacePdf({ target: { files: [original] } });
        state.sourceWorkspaceSourceForm.authors = 'A. Author';
        assert.equal(await state.saveSourceWorkspaceMetadata(), false);

        if (change === 'replace PDF') {
            state.chooseSourceWorkspacePdf({ target: { files: [new File(['PDF two'], 'Replacement study.pdf', { type: 'application/pdf' })] } });
        } else {
            state.sourceWorkspaceSourceForm.authors = 'B. Corrected Author';
        }
        const saved = await state.saveSourceWorkspaceMetadata();

        assert.equal(metadata.length, 2);
        assert.equal(saved.id, change === 'replace PDF' ? 53 : 52);
        assert.deepEqual(uploads[1], { sourceId: saved.id, file: change === 'replace PDF' ? 'Replacement study.pdf' : original.name });
        if (change === 'edit authors') assert.equal(saved.authors, 'B. Corrected Author');
        assert.equal(state.sourceWorkspacePendingFile, null);
        assert.equal(state.sourceWorkspaceError, '');
    });
}

test('a PDF already being saved cannot be replaced by another file selection', async () => {
    let finishMetadata;
    let uploadedFile;
    const state = workspace((url, options) => {
        if (url === '/library') return new Promise((resolve) => { finishMetadata = resolve; });
        if (url.endsWith('/document')) uploadedFile = options.body.get('file').name;
        return response({ source: { ...linkedSource, id: 52 }, passages: [] });
    });
    const original = new File(['PDF one'], 'Original study.pdf', { type: 'application/pdf' });
    state.chooseSourceWorkspacePdf({ target: { files: [original] } });
    const saving = state.saveSourceWorkspaceMetadata();
    const replacement = new File(['PDF two'], 'Replacement study.pdf', { type: 'application/pdf' });
    assert.equal(state.chooseSourceWorkspacePdf({ target: { files: [replacement] } }), false);
    assert.equal(state.sourceWorkspacePendingFile, original);
    assert.equal(state.sourceWorkspaceSourceForm.title, 'Original study');
    finishMetadata(response({ source: { id: 21, title: 'Original study' } }));
    await saving;
    assert.equal(uploadedFile, original.name);
});

test('changing tabs during a pending library request prevents stale results and resets loading', async () => {
    let finish;
    const state = workspace(() => new Promise((resolve) => { finish = resolve; }));
    state.sourceWorkspaceCitationMode = true;
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

test('compiled centered Sources dialog supports reading evidence, synthesis review, focus, and both themes on mobile and desktop', async () => {
    const compiled = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Blade::render('<x-proposal-source-workspace />');
    `], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(compiled.status, 0, (compiled.stderr || compiled.stdout).slice(-3000));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const helpers = readFileSync(resolve('resources/js/proposal-source-workspace.js'), 'utf8').replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="bg-white dark:bg-slate-950"><main x-data="sourcesFixture"><button id="sources-opener" @click="openSourceWorkspace()">Sources</button>${compiled.stdout}</main></body></html>`);
                await page.addScriptTag({ content: `const uploadRequest = (...args) => fetch(...args);\n${helpers}
                    window.sourceRequests = []; window.sourceInsertions = 0; window.paperDraftInsertions = 0;
                    document.addEventListener('alpine:init', () => Alpine.data('sourcesFixture', () => ({
                        ...proposalSourceWorkspace({ csrfToken: 'test', proposalDraftId: 42, synthesisUrl: '/synthesis', evidenceBase: '/proposal/links', assistanceUrl: '/assistance', libraryUrl: '/library', storeUrl: '/library', searchUrl: '/search', linkUrlTemplate: '/proposal/sources/__literature_source__', request: async (url, options) => {
                            window.sourceRequests.push({url, body: options.body ? JSON.parse(options.body) : null});
                            if (url === '/search') return {ok:true,json:async()=>({results:[${JSON.stringify(librarySource)}]})};
                            if (url === '/library' && options.method === 'POST') return {ok:true,json:async()=>({source:${JSON.stringify(librarySource)}})};
                            if (url.endsWith('/bookmark')) return {ok:true,json:async()=>({source:{...${JSON.stringify({ ...librarySource, id: 52, literature_source_id: 8 })},is_saved:JSON.parse(options.body).is_saved}})};
                            if (url === '/proposal/sources/8') return {ok:true,json:async()=>({source:${JSON.stringify({ ...librarySource, id: 52, literature_source_id: 8 })}})};
                            if (url === '/synthesis' && window.paperExtractionFails) return {ok:false,status:422,json:async()=>({message:'The whole source paper could not be read. Attach a complete readable PDF in Sources, then try again.'})};
                            return {ok:true, json:async () => url.startsWith('/library') ? { sources: ${JSON.stringify([{ ...linkedSource, id: 3 }, librarySource])} } : url === '/synthesis' ? {synthesis:'The paper describes community participation and its relationship to the measured learning outcomes.',basis:'full_text',notice:'Only part of the body text was extracted.',coverage:{limited:true}} : url === '/assistance' ? {mode:'synthesize',supported:true,draft:{paragraph:'This reviewed synthesis relates community participation to the research objectives and learning outcomes.',can_insert:true,evidence:[{source_link_id:12,passage_id:'${firstId}',page:2,quote:${JSON.stringify(quote)},title:'Community learning outcomes',kind:'quote',origin:'pdf'}]}} : ${JSON.stringify(evidence)} };
                        } }),
                        literatureSources: [${JSON.stringify(linkedSource)}], sourceWorkspaceCanCite: () => true,
                        citationPickerLocator: '', citationPickerSelection: { selectedText: 'Community participation improves outcomes.' },
                        upsertLiteratureSource(source) { this.literatureSources = [...this.literatureSources.filter(item => item.id !== source.id), source]; },
                        citeSelectedText: async () => true,
                        insertSourceWorkspaceDraft: async (draft,destination) => {window.sourceInsertions++;window.sourceInsertedDraft={draft,destination};return true;},
                        insertSourceWorkspacePaperDraft: async (draft) => {window.paperDraftInsertions++;window.paperInsertedDraft=draft;return true;},
                    })));` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await page.getByRole('button', { name: 'Sources', exact: true }).click();
                await expect(page.getByRole('dialog', { name: 'Sources', exact: true })).toBeVisible();
                const panel = page.locator('section[x-ref="sourceWorkspacePanel"]');
                const assertCenteredPanel = async () => {
                    const box = await panel.boundingBox();
                    assert.ok(Math.abs(box.x + box.width / 2 - width / 2) < 2, 'Sources should be horizontally centered');
                    assert.ok(Math.abs(box.y + box.height / 2 - 450) < 2, 'Sources should be vertically centered');
                    assert.ok(box.height <= 766, 'Sources should leave space around the dialog');
                    return box;
                };
                const listBox = await assertCenteredPanel();
                assert.ok(listBox.width <= 672, 'The source list should be as compact as the citation picker');
                const sourceRow = page.locator('article').filter({ has: page.getByRole('heading', { name: linkedSource.title, exact: true }) });
                await expect(sourceRow.getByRole('button', { name: 'View details', exact: true })).toBeVisible();
                await expect(sourceRow.getByText(linkedSource.description, { exact: true })).toBeHidden();
                await expect(sourceRow.getByRole('button', { name: 'Unsave literature', exact: true })).toHaveAttribute('aria-pressed', 'true');
                await expect(sourceRow.getByRole('link')).toBeHidden();
                await expect(page.getByText(/To cite your own text/)).toBeHidden();
                if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY) await panel.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, `sources-list-${width}-${dark ? 'dark' : 'light'}.png`) });
                assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
                await expect(page.getByRole('button', { name: 'Close Sources', exact: true })).toBeFocused();
                await expect(page.getByLabel('Page or section (optional)', { exact: true })).toBeHidden();
                await expect(page.getByRole('complementary', { name: 'Selected evidence and AI help' })).toBeHidden();
                await page.getByRole('button', { name: 'Close Sources', exact: true }).focus();
                await page.keyboard.press('Shift+Tab');
                await expect(page.getByRole('button', { name: 'Add source', exact: true })).toBeFocused();
                await page.keyboard.press('Shift+Tab');
                await expect(sourceRow.getByRole('button', { name: 'Unsave literature', exact: true })).toBeFocused();
                await expect(page.getByRole('tab')).toHaveCount(0);
                await page.getByRole('button', { name: 'Find sources', exact: true }).click();
                await expect(page.getByRole('group', { name: 'Paper access', exact: true })).toBeHidden();
                await page.locator('summary').filter({ hasText: 'Search filters' }).click();
                await expect(page.getByRole('group', { name: 'Paper access', exact: true })).toBeVisible();
                await expect(page.getByRole('checkbox', { name: /open access/i })).toHaveCount(0);
                await page.getByRole('button', { name: 'Open access', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Open access', exact: true })).toHaveAttribute('aria-pressed', 'true');
                await page.getByRole('button', { name: 'All papers', exact: true }).click();
                await expect(page.getByRole('button', { name: 'All papers', exact: true })).toHaveAttribute('aria-pressed', 'true');
                await page.getByLabel('Search in', { exact: true }).selectOption('library');
                const savedLibraryRow = page.locator('article').filter({ has: page.getByRole('heading', { name: linkedSource.title, exact: true }) });
                await expect(savedLibraryRow.getByRole('button', { name: 'Unsave literature', exact: true })).toBeEnabled();
                const newLibraryRow = page.locator('article').filter({ has: page.getByRole('heading', { name: librarySource.title, exact: true }) });
                await expect(newLibraryRow).toHaveCount(0);
                await page.getByLabel('Search in', { exact: true }).selectOption('search');
                await page.getByRole('searchbox', { name: 'Search academic publications', exact: true }).fill('community');
                await page.getByRole('button', { name: 'Search', exact: true }).click();
                const previewButton = newLibraryRow.getByRole('button', { name: 'View details', exact: true });
                const saveButton = newLibraryRow.getByRole('button', { name: 'Save literature', exact: true });
                await expect(previewButton).toBeVisible();
                await expect(saveButton).toHaveAttribute('aria-pressed', 'false');
                await expect(saveButton.locator('svg')).toHaveAttribute('fill', 'none');
                const [previewButtonBox, saveButtonBox] = await newLibraryRow.locator('button[aria-label="View details"], button[aria-label="Save literature"]')
                    .evaluateAll(buttons => buttons.map(button => button.getBoundingClientRect().toJSON()));
                assert.ok(saveButtonBox.x >= previewButtonBox.x + previewButtonBox.width, 'The bookmark should sit to the right of the preview arrow');
                assert.equal(saveButtonBox.y, previewButtonBox.y);
                await expect(newLibraryRow.getByText(librarySource.description, { exact: true })).toBeVisible();
                await expect(newLibraryRow.getByRole('link', { name: 'Visit website', exact: true })).toHaveAttribute('href', librarySource.url);
                assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).literatureSources.length), 1, 'Browsing shared sources should not add them to the proposal');
                await previewButton.click();
                await expect(page.getByRole('region', { name: 'Abstract or description', exact: true }).getByText(librarySource.description, { exact: true })).toBeVisible();
                await expect(page.getByLabel('Attach source PDF', { exact: false })).toBeDisabled();
                await expect(page.getByLabel('Researcher supplied excerpt', { exact: true })).toBeDisabled();
                await expect(page.getByLabel('Reading note', { exact: true })).toBeDisabled();
                await expect(page.getByRole('button', { name: 'Draft from paper', exact: true })).toBeHidden();
                assert.equal(await page.evaluate(() => sourceRequests.some(request => request.body && request.url !== '/search')), false, 'Preview must not write to the library or proposal');
                assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).literatureSources.length), 1);
                await page.getByRole('button', { name: 'Back to sources', exact: true }).click();
                await saveButton.click();
                const savedButton = newLibraryRow.getByRole('button', { name: 'Unsave literature', exact: true });
                await expect(savedButton).toHaveAttribute('aria-pressed', 'true');
                await expect(savedButton).toBeEnabled();
                await expect(savedButton.locator('svg')).toHaveAttribute('fill', 'currentColor');
                await expect(savedButton).toHaveCSS('color', dark ? 'rgb(252, 165, 165)' : 'rgb(185, 28, 28)');
                assert.equal(await page.evaluate(() => sourceRequests.filter(request => request.url === '/proposal/sources/8').length), 1);
                assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).literatureSources.length), 2);
                await page.getByLabel('Search in', { exact: true }).selectOption('library');
                await expect(newLibraryRow).toBeVisible();
                await savedButton.click();
                await expect(newLibraryRow).toHaveCount(0);
                await page.getByLabel('Search in', { exact: true }).selectOption('search');
                await expect(saveButton).toHaveAttribute('aria-pressed', 'false');
                await expect(saveButton.locator('svg')).toHaveAttribute('fill', 'none');
                await saveButton.click();
                await expect(savedButton).toHaveAttribute('aria-pressed', 'true');
                assert.deepEqual(await page.evaluate(() => sourceRequests.filter(request => request.url.endsWith('/bookmark')).map(request => request.body.is_saved)), [false, true]);
                assert.equal(await page.evaluate(() => sourceRequests.filter(request => request.url === '/proposal/sources/8').length), 1, 'Resaving should reuse the linked source');
                await expect(page.getByRole('group', { name: 'Source navigation', exact: true })).toBeVisible();
                if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY) await panel.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, `source-bookmarks-${width}-${dark ? 'dark' : 'light'}.png`) });
                await page.getByRole('button', { name: 'Add source', exact: true }).click();
                await page.locator('summary').filter({ hasText: 'Reference details' }).click();
                await page.getByLabel('Publication type', { exact: true }).selectOption('report');
                await page.getByLabel('Volume', { exact: true }).fill('12');
                await page.getByLabel('Issue', { exact: true }).fill('3');
                await page.getByLabel('Pages', { exact: true }).fill('14–28');
                await page.getByLabel('Publisher', { exact: true }).fill('Research Press');
                assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).sourceWorkspaceSourceForm.publisher), 'Research Press');
                await page.getByRole('button', { name: 'Back to proposal sources', exact: true }).click();
                await sourceRow.getByRole('button', { name: 'View details', exact: true }).click();
                await expect(page.getByRole('button', { name: /draft from abstract|use abstracts? instead/i })).toHaveCount(0);
                const summary = page.getByRole('region', { name: 'Abstract or description', exact: true });
                await expect(summary.getByText(linkedSource.description, { exact: true })).toBeVisible();
                const summaryBox = await summary.boundingBox();
                const abstractBox = await summary.getByText(linkedSource.description, { exact: true }).boundingBox();
                assert.ok(Math.abs(abstractBox.width - (summaryBox.width - 32)) < 2, 'The abstract should fill the container inside its padding');
                const publication = page.getByRole('link', { name: 'Open publication website', exact: true });
                await expect(publication).toHaveAttribute('href', linkedSource.url);
                await expect(publication).toHaveAttribute('rel', 'noopener noreferrer');
                await page.evaluate(() => { window.paperExtractionFails = true; });
                await page.getByRole('button', { name: 'Draft from paper', exact: true }).click();
                await expect(page.getByText('The whole source paper could not be read. Attach a complete readable PDF in Sources, then try again.', { exact: true })).toBeVisible();
                await expect(page.getByRole('button', { name: /draft from abstract|use abstracts? instead/i })).toHaveCount(0);
                assert.equal(await page.evaluate(() => window.paperDraftInsertions), 0);
                if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY && width === 1440 && !dark) await panel.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, 'source-paper-required.png') });
                await page.evaluate(() => { window.paperExtractionFails = false; Alpine.$data(document.querySelector('main')).sourceWorkspaceError = ''; });
                if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY) await page.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, `source-details-${width}-${dark ? 'dark' : 'light'}.png`) });
                await expect(page.locator('[data-source-page-text]')).toHaveText(quote);
                const readerBox = await assertCenteredPanel();
                if (width >= 1024) assert.ok(readerBox.width > listBox.width, 'Reading should have more room than browsing');
                await expect(page.getByText('Only part of the PDF was extracted.', { exact: true })).toBeVisible();
                await page.locator('[data-source-page-text]').evaluate((element) => { const range = document.createRange(); range.selectNodeContents(element); const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range); element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true })); });
                await expect(page.getByLabel('Selected PDF excerpt', { exact: true })).toHaveValue(quote);
                await page.getByRole('button', { name: 'Save excerpt or note', exact: true }).click();
                const passageRequest = await page.evaluate(() => window.sourceRequests.find((request) => request.url.endsWith('/passages')));
                assert.deepEqual(passageRequest.body, { quote, note: '', page: 2, kind: 'quote' });
                const checkbox = page.getByRole('checkbox', { name: `Use excerpt ${firstId} for AI help`, exact: true });
                await checkbox.check();
                await page.getByText('Other AI help', { exact: true }).click();
                const assistance = page.locator('button').filter({ has: page.locator('span[x-text*="Check support"]') });
                for (const [mode, label] of [['explain', 'Explain excerpts'], ['support', 'Check support'], ['synthesize', 'Draft synthesis']]) {
                    await page.getByLabel('AI help', { exact: true }).selectOption(mode);
                    await expect(assistance).toHaveText(label);
                    await expect(assistance.locator('[data-assistant-icon]')).toBeVisible();
                }
                await page.getByText('Other AI help', { exact: true }).click();
                await page.getByRole('button', { name: 'Draft from selected evidence', exact: true }).click();
                const review = page.getByLabel('AI response', { exact: true });
                await expect(review).toBeVisible();
                assert.equal(await page.evaluate(() => window.sourceInsertions), 0);
                await review.fill('My edited synthesis explains the relationship between community participation and improved learning outcomes.');
                await page.getByLabel('Add reviewed text to', { exact: true }).selectOption('rationale');
                await assertCenteredPanel();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await page.locator('section[x-ref="sourceWorkspacePanel"] > div.overflow-y-auto').evaluate((element) => { element.scrollTop = 0; });
                    await page.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, `sources-review-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.getByRole('button', { name: 'Insert reviewed draft', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Reviewed draft inserted', exact: true })).toBeDisabled();
                assert.equal(await page.evaluate(() => window.sourceInsertedDraft.destination), 'rationale');
                assert.equal(await page.evaluate(() => window.sourceInsertedDraft.draft.evidence[0].passage_id), firstId);
                await page.getByRole('button', { name: 'Back to sources', exact: true }).click();
                assert.equal((await assertCenteredPanel()).width, readerBox.width, 'Selected evidence should keep its reading space beside the source list');
                await sourceRow.getByRole('button', { name: 'View details', exact: true }).click();
                await page.getByRole('button', { name: 'Draft from paper', exact: true }).click();
                await expect(page.getByRole('heading', { name: 'Review the paper-based RRL draft', exact: true })).toBeVisible();
                await expect(page.getByText('Only part of the body text was extracted.', { exact: true })).toBeVisible();
                await expect(page.getByText('Destination: Review of Related Literature', { exact: true })).toBeVisible();
                await expect(page.getByLabel('Add reviewed text to', { exact: true })).toBeHidden();
                await expect(page.getByRole('region', { name: 'Evidence used in this response', exact: true })).toBeHidden();
                assert.equal(await page.evaluate(() => window.paperDraftInsertions), 0);
                const paperRequest = await page.evaluate(() => window.sourceRequests.find((request) => request.url === '/synthesis'));
                assert.deepEqual(paperRequest.body, { proposal_draft_id: 42, source_link_id: 12, evidence_basis: 'full_text' });
                await page.getByLabel('AI response', { exact: true }).fill('My reviewed RRL paragraph describes the findings of this paper while preserving their limitations.');
                await page.getByRole('button', { name: 'Insert reviewed draft', exact: true }).click();
                await expect(page.getByRole('button', { name: 'Reviewed draft inserted', exact: true })).toBeDisabled();
                assert.equal(await page.evaluate(() => window.paperDraftInsertions), 1);
                assert.equal(await page.evaluate(() => window.paperInsertedDraft.source_link_id), 12);
                await page.getByRole('button', { name: 'Close Sources', exact: true }).click();
                await expect(page.getByRole('dialog')).toBeHidden();
                await expect(page.getByRole('button', { name: 'Sources', exact: true })).toBeFocused();
                assert.equal(await page.evaluate(() => document.body.style.overflow), '');
                await page.evaluate(() => Alpine.$data(document.querySelector('main')).openSourceWorkspace('paper', true));
                await expect(page.getByRole('dialog', { name: 'Insert citation', exact: true })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Close Sources', exact: true })).toBeFocused();
                assert.equal((await assertCenteredPanel()).width, listBox.width, 'Citation mode should stay compact after collecting evidence');
                await page.keyboard.press('Escape');
                await expect(page.getByRole('dialog')).toBeHidden();
                await expect(page.getByRole('button', { name: 'Sources', exact: true })).toBeFocused();
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally { await browser.close(); }
});
