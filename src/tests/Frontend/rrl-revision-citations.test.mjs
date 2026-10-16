import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const moduleSource = (name) => readFileSync(new URL(`../../resources/js/${name}.js`, import.meta.url), 'utf8')
    .replace(/^import .+;\r?\n/gm, '').replace(/^export /gm, '');
const methodSource = (name) => {
    const match = app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing actual editor method: ${name}`);
    return match[0];
};

test('accepting a reordered RRL preserves source locators and saves synchronized citations and references', async () => {
    const before = '<p>Local reporting improved <span data-proposal-citation="42" data-proposal-locator="p. 8"> [1, p. 8]</span>.</p>'
        + '<p>Village monitoring varied <span data-proposal-citation="43" data-proposal-locator="pp. 3, 5"> [2, pp. 3, 5]</span>.</p>';
    const value = '<p>Monitoring differed between villages <span data-proposal-citation="43" data-proposal-locator="pp. 3, 5"> [2, pp. 3, 5]</span>.</p>'
        + '<p>Local reporting showed improvement <span data-proposal-citation="42" data-proposal-locator="p. 8"> [1, p. 8]</span>.</p>';
    const references = '<p>Keep this manually written reference.</p>'
        + '<p data-proposal-managed-reference="42">[1] Local reporting study.</p>'
        + '<p data-proposal-managed-reference="43">[2] Village monitoring study.</p>';
    const candidate = {
        id: 'rrl-revision', token: 'a'.repeat(64), paper_slug: 'detailed-proposal', proposal_draft_id: 12,
        field: 'related_literature', label: 'RRL revision', before, value,
        document_version: 3, draft_version: 7,
    };
    const methods = [
        'proposalChangeFieldValue', 'setProposalChangeFieldValue', 'applyAssistantProposalChange', 'proposalChangeCitationsMatch',
        'literatureCitationFieldValue', 'citationMarkerSourceIds', 'activeProposalCitations', 'citationReferenceSourceIds',
        'citationReferenceSources', 'citationReferenceNumbers', 'referenceTextForCitationSource', 'removeManagedReferenceBlocks',
        'escapeLiteratureHtml', 'plainText', 'synchronizeLiteratureCitations', 'notifyLiteratureFieldChanged',
        'detailedProposalAutoSaveForm', 'detailedProposalFormData', 'detailedProposalFingerprint', 'detailedProposalAutoSaveStatus',
        'triggerDetailedProposalAutoSave', 'scheduleDetailedProposalAutoSave', 'saveDetailedProposalNow', 'saveDetailedProposal',
        'detailedProposalShouldSaveAsDraft',
    ].map(methodSource).join('\n');
    const semanticEditor = app.slice(app.indexOf('function sanitizedSemanticHtml('), app.indexOf('window.insertProposalCitationMarker ='));
    const markerSync = app.slice(app.indexOf('window.syncProposalCitationMarkers ='), app.indexOf('window.removeProposalCitationMarkers ='));
    const finishSave = app.slice(app.indexOf('async function finishPaperEditorAutoSave('), app.indexOf('function backgroundAutoSaveContexts('));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent(`<main data-detailed-proposal-workspace data-paper-editor data-detailed-proposal-autosave="true" data-paper-project-details-complete="true" x-data="revisionEditor">
            <form x-ref="form" data-paper-form data-detailed-proposal-autosave-form method="post">
                <input type="hidden" name="document_version" value="3"><input type="hidden" name="draft_version" value="7">
                <input type="hidden" name="save_as_draft" data-paper-save-mode value="0">
                <input id="literature-citations" type="hidden" name="literature_citations" :value="JSON.stringify(literatureCitations)">
                <label for="related-literature">Review of Related Literature</label>
                <textarea id="related-literature" name="related_literature" x-model="relatedLiterature" data-semantic-editor></textarea>
                <label for="references">References</label>
                <textarea id="references" name="references" x-model="references" data-semantic-editor></textarea>
            </form>
        </main>`);
        await page.addScriptTag({ content: `
            ${moduleSource('research-assistant-markdown')}
            ${moduleSource('research-assistant-proposal-changes')}
            ${moduleSource('proposal-semantic-editor')}
            ${moduleSource('proposal-paper-autosave')}
            ${semanticEditor}
            ${markerSync}
            ${finishSave}
            const config = { proposalDraftId:12, updateUrl:'https://athena.test/save', csrfToken:'test' };
            window.savedForms = [];
            window.fetch = async (url, options) => {
                if (url !== config.updateUrl) throw new Error('Unexpected request: ' + url);
                window.savedForms.push(Object.fromEntries(options.body.entries()));
                return {ok:true,status:200,json:async()=>({document_version:4,draft_version:8,saved_as_draft:true,completion_errors:{}})};
            };
            document.addEventListener('alpine:init', () => Alpine.data('revisionEditor', () => ({
                ${methods}
                relatedLiterature:${JSON.stringify(before)}, references:${JSON.stringify(references)},
                literatureSources:[{id:42,reference:'Local reporting study.'},{id:43,reference:'Village monitoring study.'}],
                literatureCitations:[{source_link_id:42,field:'related_literature',locator:'p. 8'},
                    {source_link_id:43,field:'related_literature',locator:'pp. 3, 5'}],
                methodology:{},specificObjectives:[],proposalChangeApplying:false,proposalChangeAppliedIds:[],
                proposalChangeNotice:'',proposalChangeError:'',autoSaveBlocked:false,autoSaveInFlight:false,autoSaveRevision:0,
                init(){this.$nextTick(()=>{initializeSemanticEditors();this.lastSavedDetailedProposal=this.detailedProposalFingerprint(this.$refs.form);window.editorReady=true;});},
                markProposalPreviewStale(){},focusProposalRequirement(){},applySavedMethodologyImages(){},updateDetailedProposalCompletionStatus(){},
            })));
        ` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        await page.waitForFunction(() => window.editorReady === true);
        const unchanged = await page.evaluate(() => ({
            html: Alpine.$data(document.querySelector('main')).relatedLiterature,
            saves: window.savedForms.length,
        }));
        assert.deepEqual(unchanged, { html: before, saves: 0 });

        const result = await page.evaluate(async (suggestion) => {
            const state = Alpine.$data(document.querySelector('main'));
            const applied = await state.applyAssistantProposalChange(suggestion);
            await state.$nextTick();
            const field = document.getElementById('related-literature');
            return {
                applied,
                markers: [...field._semanticEditor.querySelectorAll('[data-proposal-citation]')].map((node) => ({
                    id: node.dataset.proposalCitation, locator: node.dataset.proposalLocator, label: node.textContent.trim(),
                })),
                references: [...document.getElementById('references')._semanticEditor.querySelectorAll('p')].map((node) => node.textContent),
                saved: window.savedForms.at(-1),
                html: state.relatedLiterature,
            };
        }, candidate);
        assert.equal(result.applied.applied, true);
        assert.equal(result.applied.saved, true);
        assert.deepEqual(result.markers, [
            { id: '43', locator: 'pp. 3, 5', label: '[1, pp. 3, 5]' },
            { id: '42', locator: 'p. 8', label: '[2, p. 8]' },
        ]);
        assert.deepEqual(result.references, [
            'Keep this manually written reference.', '[1] Village monitoring study.', '[2] Local reporting study.',
        ]);
        assert.equal(result.saved.related_literature, result.html);
        assert.match(result.saved.related_literature, /data-proposal-citation="43"[^>]*> \[1, pp\. 3, 5\]/);
        assert.match(result.saved.related_literature, /data-proposal-citation="42"[^>]*> \[2, p\. 8\]/);
        assert.ok(result.saved.references.indexOf('Village monitoring study.') < result.saved.references.indexOf('Local reporting study.'));
        assert.ok(result.saved.references.includes('Keep this manually written reference.'));
        assert.deepEqual(JSON.parse(result.saved.literature_citations).map(({ source_link_id, locator }) => [source_link_id, locator]), [[42, 'p. 8'], [43, 'pp. 3, 5']]);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
