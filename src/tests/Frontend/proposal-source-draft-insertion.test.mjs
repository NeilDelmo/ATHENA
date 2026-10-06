import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { prepareReviewedSourceDraft } from '../../resources/js/proposal-source-draft-insertion.js';

const sources = [{ id: 7, literature_source_id: 70, title: 'Coastal monitoring' }, { id: 8, literature_source_id: 80, title: 'Community participation' }];
const paragraph = 'Researchers can compare local monitoring practices with community participation when planning their coastal investigation.';
const reviewed = {
    paragraph, can_insert: true,
    evidence: [{ source_link_id: 7, passage_id: 17, quote: 'Local monitoring practices were studied.', page: 6 }],
};

test('reviewed insertion rejects unsupported writing fields, missing evidence, and sources outside the paper', () => {
    const number = () => 1;
    assert.equal(prepareReviewedSourceDraft(reviewed, sources, 'project-title', number), null);
    assert.equal(prepareReviewedSourceDraft(reviewed, sources, 'references', number), null);
    assert.equal(prepareReviewedSourceDraft({ ...reviewed, can_insert: false }, sources, 'rationale', number), null);
    assert.equal(prepareReviewedSourceDraft({ ...reviewed, evidence: [] }, sources, 'rationale', number), null);
    assert.equal(prepareReviewedSourceDraft({ ...reviewed, evidence: [{ ...reviewed.evidence[0], kind: 'note', note: 'My interpretation.' }] }, sources, 'rationale', number), null);
    assert.equal(prepareReviewedSourceDraft(reviewed, [], 'rationale', number), null);
    assert.equal(prepareReviewedSourceDraft({ ...reviewed, evidence: [{ ...reviewed.evidence[0], quote: '' }] }, sources, 'rationale', number), null);
    assert.equal(prepareReviewedSourceDraft(reviewed, sources, 'rationale', () => null), null);
});

test('a source cited from several passages receives one escaped marker with sorted unique page locators', () => {
    const insertion = prepareReviewedSourceDraft({ ...reviewed, paragraph: '<img src=x onerror=alert(1)> Supported findings.\nAnother sentence.', evidence: [
        { ...reviewed.evidence[0], page: 9 },
        { ...reviewed.evidence[0], passage_id: 18, page: 6 },
        { ...reviewed.evidence[0], passage_id: 19, page: 9 },
    ] }, sources, 'rationale', () => 2);
    assert.equal(insertion.citations.length, 1);
    assert.equal(insertion.citations[0].locator, 'pp. 6, 9');
    assert.equal(insertion.html.includes('<img'), false);
    assert.match(insertion.html, /&lt;img/);
    assert.match(insertion.html, /<br>Another sentence\./);
    assert.match(insertion.html, /data-proposal-locator="pp\. 6, 9"/);
    assert.match(insertion.html, /\[2, pp\. 6, 9\]/);
});

test('mixed researcher notes and quotations cite only the sources with academic excerpts', () => {
    const insertion = prepareReviewedSourceDraft({ ...reviewed, evidence: [
        ...reviewed.evidence,
        { source_link_id: 8, passage_id: 80, kind: 'note', note: 'My reading interpretation.', quote: '' },
    ] }, sources, 'rationale', () => 1);
    assert.equal(insertion.citations.length, 1);
    assert.equal(insertion.citations[0].source.id, 7);
    assert.equal(insertion.html.includes('data-proposal-citation="8"'), false);
});

function browserCode() {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const helpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replaceAll('export ', '');
    const escape = readFileSync(new URL('../../resources/js/research-assistant-markdown.js', import.meta.url), 'utf8');
    const escaping = escape.slice(escape.indexOf('export function escapeAssistantHtml('), escape.indexOf('\n}', escape.indexOf('export function escapeAssistantHtml(')) + 2).replace('export ', '');
    const draft = readFileSync(new URL('../../resources/js/proposal-source-draft-insertion.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
    const record = app.slice(app.indexOf('    recordLiteratureCitation('), app.indexOf('    removeCitationSource('));
    const integration = app.slice(app.indexOf('    openCitationPicker(selection) {'), app.indexOf('    async searchCitationLibrary() {'));
    const citing = app.slice(app.indexOf('    async citeSelectedText('), app.indexOf('    literatureSearchContextOptions() {'));
    const marker = app.slice(app.indexOf('window.insertProposalCitationMarker ='), app.indexOf('window.syncProposalCitationMarkers ='));
    return `${helpers}\n${escaping}\n${draft}\n${marker}\nwindow.actualSourceMethods = ({${record}${integration}${citing}});`;
}

test('actual editor integration preserves unsaved prose, guards citation targets, and rolls back only failed citation changes', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<textarea id="rationale"></textarea><div id="rationale-editor" contenteditable><p>Unsaved rationale paragraph.</p></div><textarea id="methodology-research_design"></textarea><div id="methodology-editor" contenteditable><p>Unsaved method design.</p></div><textarea id="project-title"></textarea><p id="outside">Outside this writing field.</p>');
        await page.addScriptTag({ content: browserCode() });
        const results = await page.evaluate(async ({ sources, reviewed }) => {
            const textarea = document.getElementById('rationale');
            const editor = document.getElementById('rationale-editor');
            textarea._semanticEditor = editor;
            textarea.value = '<p>Old saved rationale.</p>';
            const methodField = document.getElementById('methodology-research_design');
            methodField._semanticEditor = document.getElementById('methodology-editor');
            const state = {
                ...window.actualSourceMethods,
                literatureSources: structuredClone(sources), literatureCitations: [],
                sourceWorkspaceEvidenceCache: { 7: { passages: [{ id: 17, quote: reviewed.evidence[0].quote, page: 6, note: '' }] } },
                sourceWorkspaceInsertedDrafts: [], rationale: '<p>Old saved rationale.</p>', relatedLiterature: '<p>Existing RRL.</p>',
                methodology: { research_design: '<p>Old saved method.</p>' }, saves: 0, previewChanges: 0,
                sourceWorkspaceError: '', sourceWorkspaceNotice: '', citationPickerSaving: false,
                citationPickerLocator: '', citationPickerSelection: null, references: '',
                openSourceWorkspace(tab, mode = false) { this.opened = { tab, mode }; },
                closeSourceWorkspace() { this.closed = true; },
                markProposalPreviewStale() { this.previewChanges++; },
                triggerDetailedProposalAutoSave() { this.saves++; },
                citationReferenceNumber(source) {
                    const ids = this.markerIds();
                    const index = ids.indexOf(Number(source.id));
                    return index < 0 ? null : index + 1;
                },
                citationReferenceNumberIncluding(source) {
                    const ids = this.markerIds();
                    return ids.includes(Number(source.id)) ? ids.indexOf(Number(source.id)) + 1 : ids.length + 1;
                },
                markerIds() {
                    return orderedCitationSourceIds([...document.querySelectorAll('[data-proposal-citation]')].map((marker) => marker.dataset.proposalCitation), this.literatureCitations);
                },
                synchronizeLiteratureCitations() {
                    const ids = this.markerIds();
                    synchronizeCitationMarkerLabels(document.querySelectorAll('[data-proposal-citation]'), Object.fromEntries(ids.map((id, index) => [id, index + 1])));
                    this.references = ids.map((id, index) => `[${index + 1}] ${this.literatureSources.find((source) => Number(source.id) === id)?.title || ''}`).join('\n');
                },
            };
            for (const field of [textarea, methodField]) {
                field.addEventListener('input', () => { field._semanticEditor.innerHTML = field.value; });
                field._semanticEditor.addEventListener('input', () => { field.value = field._semanticEditor.innerHTML; });
            }
            const inserted = state.insertSourceWorkspaceDraft(reviewed, 'rationale');
            const afterFirst = { rationale: state.rationale, rrl: state.relatedLiterature, records: structuredClone(state.literatureCitations), references: state.references, saves: state.saves };
            const duplicate = state.insertSourceWorkspaceDraft(reviewed, 'rationale');
            state.sourceWorkspaceInsertedDrafts = [];
            const reloadedDuplicate = state.insertSourceWorkspaceDraft(reviewed, 'rationale');
            const rejected = state.insertSourceWorkspaceDraft({ ...reviewed, can_insert: false }, 'rationale');
            const changedEvidence = state.insertSourceWorkspaceDraft({ ...reviewed, evidence: [{ ...reviewed.evidence[0], quote: 'Changed evidence.' }] }, 'rationale');
            const unknownEvidence = state.insertSourceWorkspaceDraft({ ...reviewed, evidence: [{ ...reviewed.evidence[0], source_link_id: 999 }] }, 'rationale');
            const fieldGuard = state.insertSourceWorkspaceDraft(reviewed, 'project-title');
            const methodInserted = state.insertSourceWorkspaceDraft({ ...reviewed, paragraph: '<script>alert(1)</script> Methods are reviewed before use.' }, 'methodology-research_design');
            const methodResult = state.methodology.research_design;

            const setCaret = () => {
                const range = document.createRange(); range.selectNodeContents(editor); range.collapse(false);
                textarea._semanticCitationRange = range;
                return range;
            };
            setCaret();
            state.openCitationPicker({ fieldId: 'rationale', selectedText: 'Untrusted stale text', fieldKey: 'references' });
            const currentTarget = structuredClone(state.citationPickerSelection);
            const canCite = state.sourceWorkspaceCanCite();
            state.recordLiteratureCitation(sources[0], 'rationale', '');
            const beforeFailure = structuredClone(state.literatureCitations);
            const originalMarker = window.insertProposalCitationMarker;
            window.insertProposalCitationMarker = () => false;
            const failedDuplicate = await state.citeSelectedText(sources[0]);
            const rollbackDuplicate = JSON.stringify(beforeFailure) === JSON.stringify(state.literatureCitations);
            const failedNew = await state.citeSelectedText(sources[1]);
            const rollbackNew = JSON.stringify(beforeFailure) === JSON.stringify(state.literatureCitations);
            window.insertProposalCitationMarker = () => { throw new DOMException('The target range is no longer usable.'); };
            const failedException = await state.citeSelectedText(sources[1]);
            const rollbackException = JSON.stringify(beforeFailure) === JSON.stringify(state.literatureCitations);
            window.insertProposalCitationMarker = originalMarker;
            setCaret();
            state.openSourceWorkspaceFromToolbar({ fieldId: 'rationale' });
            state.citationPickerLocator = ' p. 12\n ';
            const cited = await state.citeSelectedText(sources[1]);
            const locatorMarker = editor.querySelector('[data-proposal-citation="8"]');
            const citationResult = { records: structuredClone(state.literatureCitations), references: state.references, marker: locatorMarker?.outerHTML, text: locatorMarker?.textContent };
            setCaret();
            state.openCitationPicker({ fieldId: 'rationale' });
            const unknownBefore = editor.innerHTML;
            const unknownCite = await state.citeSelectedText({ id: 999, literature_source_id: 9990, title: 'Unknown paper' });
            const unknownUnchanged = editor.innerHTML === unknownBefore;
            const outsideRange = document.createRange(); outsideRange.selectNodeContents(document.getElementById('outside')); outsideRange.collapse(false);
            textarea._semanticCitationRange = outsideRange;
            const outsideCanCite = state.sourceWorkspaceCanCite();
            state.openSourceWorkspaceFromToolbar({ fieldId: 'project-title' });
            const metadataTarget = state.citationPickerSelection;
            return { inserted, afterFirst, duplicate, reloadedDuplicate, rejected, changedEvidence, unknownEvidence, fieldGuard, methodInserted, methodResult, currentTarget, canCite, failedDuplicate, rollbackDuplicate, failedNew, rollbackNew, failedException, rollbackException, cited, citationResult, unknownCite, unknownUnchanged, outsideCanCite, metadataTarget };
        }, { sources, reviewed });

        assert.equal(results.inserted, true);
        assert.ok(results.afterFirst.rationale.startsWith('<p>Unsaved rationale paragraph.</p>'));
        assert.equal(results.afterFirst.rrl, '<p>Existing RRL.</p>');
        assert.equal(results.afterFirst.records[0].locator, 'p. 6');
        assert.match(results.afterFirst.references, /\[1\] Coastal monitoring/);
        assert.equal(results.afterFirst.saves, 1);
        assert.deepEqual([results.duplicate, results.reloadedDuplicate, results.rejected, results.changedEvidence, results.unknownEvidence, results.fieldGuard], [false, false, false, false, false, false]);
        assert.equal(results.methodInserted, true);
        assert.ok(results.methodResult.startsWith('<p>Unsaved method design.</p>'));
        assert.equal(results.methodResult.includes('<script>'), false);
        assert.match(results.methodResult, /&lt;script&gt;/);
        assert.equal(results.currentTarget.fieldKey, 'rationale');
        assert.equal(results.currentTarget.selectedText, '');
        assert.equal(results.currentTarget.collapsed, true);
        assert.equal(results.canCite, true);
        assert.deepEqual([results.failedDuplicate, results.failedNew], [false, false]);
        assert.deepEqual([results.rollbackDuplicate, results.rollbackNew], [true, true]);
        assert.deepEqual([results.failedException, results.rollbackException], [false, true]);
        assert.equal(results.cited, true);
        assert.equal(results.citationResult.text, ' [2, p. 12]');
        assert.match(results.citationResult.marker, /data-proposal-locator="p\. 12"/);
        assert.equal(results.citationResult.records.at(-1).locator, 'p. 12');
        assert.match(results.citationResult.references, /\[2\] Community participation/);
        assert.deepEqual([results.unknownCite, results.unknownUnchanged, results.outsideCanCite, results.metadataTarget], [false, true, false, null]);
    } finally {
        await browser.close();
    }
});
