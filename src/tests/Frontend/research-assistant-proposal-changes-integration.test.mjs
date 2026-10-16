import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { resolve as resolvePath } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { allowedProposalChangeField, normalizeAssistantProposalChanges, proposalChangeRichTextValue } from '../../resources/js/research-assistant-proposal-changes.js';
import { sameOriginPaperUrl } from '../../resources/js/research-assistant-paper-insertion.js';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const methodSource = (name) => {
    const match = app.match(new RegExp(`    (?:async )?${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing method: ${name}`);
    return match[0];
};
const token = 'a'.repeat(64);
const candidate = {
    id: 'suggestion-1', token, paper_slug: 'detailed-proposal', proposal_draft_id: 12,
    field: 'rationale', label: 'Rationale', before: '<p>Existing rationale.</p>',
    value: 'Clearer rationale with <img src=x onerror=alert(1)> as plain text.', reason: 'Improve clarity.',
    document_version: 3, draft_version: 7, editor_url: 'https://athena.test/proposal/12',
};
const selection = { proposal_draft_id: 12, token, suggestion_id: candidate.id };
const resolverSource = app.slice(app.indexOf('async function resolveAssistantProposalChange('), app.indexOf("Alpine.store('researchAssistant'"));

function resolver(fetchReply) {
    return new Function('fetch', 'window', 'sameOriginPaperUrl', 'normalizeAssistantProposalChanges',
        `${resolverSource}; return resolveAssistantProposalChange;`)(fetchReply, { location: { origin: 'https://athena.test' } }, sameOriginPaperUrl, normalizeAssistantProposalChanges);
}

test('Apply resolves immutable server text using only proposal and suggestion identifiers', async () => {
    let request;
    const resolve = resolver(async (_url, options) => {
        request = JSON.parse(options.body);
        return { ok: true, json: async () => ({ proposal_change: { ...candidate, value: 'Authorized server value.' } }) };
    });
    const result = await resolve({ ...selection, value: 'Forged client replacement.', field: 'references' }, '/resolve', 'csrf');
    assert.deepEqual(request, selection);
    assert.equal(result.value, 'Authorized server value.');
    assert.equal(result.document_version, 3);
    assert.equal(result.token, token);
});

test('the resolver rejects unrelated draft IDs, invalid fields and missing version guards', async () => {
    for (const override of [{ proposal_draft_id: 13 }, { id: 'different-suggestion' }, { field: '__proto__.polluted' },
        { field: 'references' }, { document_version: null }, { draft_version: -1 }]) {
        const resolve = resolver(async () => ({ ok: true, json: async () => ({ proposal_change: { ...candidate, ...override } }) }));
        await assert.rejects(resolve(selection, '/resolve', 'csrf'), /invalid proposal change/);
    }
    await assert.rejects(resolver(async () => { throw new Error('Must not fetch'); })(selection, 'https://other.test/resolve', 'csrf'), /Refresh this page/);
});

test('expired and stale server selections retain a useful error for the user', async () => {
    const resolve = resolver(async () => ({ ok: false, status: 409, json: async () => ({ message: 'The paper or manual changed.' }) }));
    await assert.rejects(resolve(selection, '/resolve', 'csrf'), /paper or manual changed/);
});

function proposalStore({ active = false, saveCurrent = true, navigate = true, saveConversation = true, applyResult = { applied: true, saved: true, notice: 'Saved.' } } = {}) {
    const packet = normalizeAssistantProposalChanges({ proposal_draft_id: 12, editor_url: candidate.editor_url,
        suggestions: [candidate, { ...candidate, id: 'second', token: 'b'.repeat(64), field: 'executive_brief' }] });
    const message = { id: 1, proposal_changes: packet };
    const staged = [];
    const requests = [];
    const editor = active ? { dataset: { proposalDraftId: '12' } } : null;
    const document = { body: { dataset: { researchAssistantProposalChangesUrl: '/resolve' } },
        querySelector: (selector) => selector.startsWith('meta') ? { content: 'csrf' } : editor };
    const window = { location: { origin: 'https://athena.test', assign(value) { this.destination = value; } },
        dispatchEvent(event) { event.detail.handled = true; event.detail.promise = Promise.resolve(applyResult); } };
    const source = ['proposalChangesBusy', 'applyProposalChange', 'keepCurrentProposalText', 'findProposalStudies', 'refreshProposalChanges', 'proposalRrlAssessmentText', 'proposalRrlRequirementSource'].map(methodSource).join('\n');
    const methods = new Function('document', 'window', 'normalizeAssistantProposalChanges', 'sameOriginPaperUrl',
        'resolveAssistantProposalChange', 'finishPaperEditorAutoSave', 'finishPendingNavigationChanges', 'assistantProposalChangeHandoff', 'CustomEvent', `return ({${source}});`)(
        document, window, normalizeAssistantProposalChanges, sameOriginPaperUrl,
        async (selected) => { requests.push(selected); return { ...candidate }; },
        async () => saveCurrent, async () => navigate, () => ({ stage(value) { staged.push(value); return true; } }),
        class { constructor(type, { detail }) { this.type = type; this.detail = detail; } },
    );
    return { ...methods, messages: [message], message, requestSequence: 0, draft: '',
        isLoading: false, sendInProgress: false, retryAfter: 0, proposalChangesOperationPending: false,
        hasConversation: () => true, saveConversation: async () => saveConversation ? { id: 1 } : null,
        sendPrompt: async (...args) => { requests.push(args); return true; }, staged, requests, window };
}

test('Apply waits for current saves and never stages navigation after an interrupted save', async () => {
    const blocked = proposalStore({ active: true, saveCurrent: false });
    assert.equal(await blocked.applyProposalChange(blocked.message, blocked.message.proposal_changes.suggestions[0]), false);
    assert.deepEqual(blocked.requests, []);
    assert.match(blocked.message.proposal_changes.suggestions[0].error, /Finish saving/);
    for (const options of [{ navigate: false }, { saveConversation: false }]) {
        const state = proposalStore(options);
        assert.equal(await state.applyProposalChange(state.message, state.message.proposal_changes.suggestions[0]), false);
        assert.deepEqual(state.staged, []);
        assert.equal(state.window.location.destination, undefined);
    }
});

test('cross-page Apply stages only the bound token and opens the authorized same-origin editor', async () => {
    const state = proposalStore();
    assert.equal(await state.applyProposalChange(state.message, state.message.proposal_changes.suggestions[0]), true);
    assert.deepEqual(state.staged, [selection]);
    assert.equal(state.window.location.destination, `${candidate.editor_url}#rationale`);
    assert.equal(JSON.stringify(state.staged).includes('Existing rationale'), false);
    assert.equal(state.proposalChangesOperationPending, false);
});

test('successful Apply invalidates sibling suggestions and refresh keeps the selected proposal', async () => {
    const state = proposalStore({ active: true });
    const suggestions = state.message.proposal_changes.suggestions;
    assert.equal(await state.applyProposalChange(state.message, suggestions[0]), true);
    assert.equal(suggestions[0].saved, true);
    assert.equal(suggestions[1].stale, true);
    assert.match(suggestions[1].error, /Request updated suggestions/);
    assert.equal(await state.applyProposalChange(state.message, suggestions[1]), false);
    await state.refreshProposalChanges(state.message);
    assert.deepEqual(state.requests.at(-1).slice(1), [{ type: 'suggest_proposal_changes' }, { proposal_draft_id: 12 }]);
    state.draft = 'A message still being written.';
    const requests = state.requests.length;
    assert.equal(await state.refreshProposalChanges(state.message), false);
    assert.equal(state.requests.length, requests);
    assert.equal(state.draft, 'A message still being written.');
});

test('partial literature counts retain the manual minimum without inventing a numeric shortfall', () => {
    const state = proposalStore();
    state.message.proposal_changes.assessment.rrl = { count: null, known_count: 7, minimum: 10, shortfall: null,
        requirement_reference: 'Manual basis', sources: [{ reference: 'Other source', title: 'Other' }, { reference: 'Manual basis', title: 'Manual p92' }] };
    assert.match(state.proposalRrlAssessmentText(state.message), /Form minimum: 10 reviewed studies/);
    assert.match(state.proposalRrlAssessmentText(state.message), /total.*cannot be verified/);
    assert.doesNotMatch(state.proposalRrlAssessmentText(state.message), /Add at least 3/);
    assert.equal(state.proposalRrlRequirementSource(state.message).title, 'Manual p92');
});

test('Keep current removes a suggestion without resolving or applying it', async () => {
    const state = proposalStore();
    const suggestion = state.message.proposal_changes.suggestions[0];
    assert.equal(await state.keepCurrentProposalText(state.message, suggestion), true);
    assert.equal(state.message.proposal_changes.suggestions.some((item) => item.id === suggestion.id), false);
    assert.deepEqual(state.requests, []);
    assert.deepEqual(state.staged, []);
});

test('document analysis and dissemination saves block proposal change operations', async () => {
    for (const flag of ['documentAnalysisPending', 'disseminationSaving']) {
        const state = proposalStore();
        state[flag] = true;
        assert.equal(state.proposalChangesBusy(), true);
        assert.equal(await state.applyProposalChange(state.message, state.message.proposal_changes.suggestions[0]), false);
        assert.deepEqual(state.requests, []);
    }
});

class Input {
    constructor(name, value) { this.name = name; this.value = value; this.disabled = false; this.readOnly = false; this.events = []; }
    closest() { return null; }
    dispatchEvent(event) { this.events.push(event.type); }
}
class Textarea extends Input {
    hasAttribute(name) { return name === 'data-semantic-editor'; }
}
class Form {
    constructor(control) { this.control = control; this.versions = { document_version: new Input('document_version', '3'), draft_version: new Input('draft_version', '7') }; }
    querySelector(selector) { return this.versions[selector.match(/name="([^"]+)"/)?.[1]] || null; }
    querySelectorAll() { return [this.control]; }
}

function editor({ field = 'rationale', before = candidate.before, save = true, rich = true } = {}) {
    const control = new (rich ? Textarea : Input)(field.split('.').map((part, index) => index ? `[${part}]` : part).join(''), before);
    const form = new Form(control);
    const source = ['proposalChangeFieldValue', 'setProposalChangeFieldValue', 'applyAssistantProposalChange'].map(methodSource).join('\n');
    const methods = new Function('config', 'allowedProposalChangeField', 'normalizeAssistantProposalChanges', 'proposalChangeRichTextValue',
        'sanitizedSemanticHtml', 'HTMLFormElement', 'HTMLTextAreaElement', 'finishPaperEditorAutoSave', `return ({${source}});`)(
        { proposalDraftId: 12 }, allowedProposalChangeField, normalizeAssistantProposalChanges, proposalChangeRichTextValue,
        (value) => value, Form, Textarea, async () => save,
    );
    return {
        ...methods, $el: { dataset: { paperDirty: 'false' }, inert: false },
        $nextTick: async () => {}, proposalChangeApplying: false, proposalChangeAppliedIds: [],
        proposalChangeNotice: '', proposalChangeError: '', autoSaveBlocked: false, autoSaveInFlight: false,
        rationale: before, relatedLiterature: before, executiveBrief: '<p>Keep executive brief.</p>', generalObjective: 'Keep objective.', introduction: 'Keep introduction.',
        methodology: { research_design: before, data_analysis: 'Keep analysis.' }, specificObjectives: [{ id: 19, description: before }],
        references: 'Keep references.', literatureCitations: [{ source_link_id: 42 }],
        lastSavedDetailedProposal: 'saved', detailedProposalFingerprint: () => 'saved',
        detailedProposalAutoSaveForm: () => form, markProposalPreviewStale() { this.previewMarked = true; },
        triggerDetailedProposalAutoSave() { this.savesScheduled = (this.savesScheduled || 0) + 1; this.$el.dataset.paperDirty = 'true'; },
        proposalChangeCitationsMatch: () => true,
        synchronizeLiteratureCitations() { this.referencesSynchronized = true; },
        focusProposalRequirement(key) { this.focusedField = key; }, form, control,
    };
}

test('authorized rich-text changes escape markup and save without touching other proposal data', async () => {
    const state = editor();
    const packet = { ...candidate };
    const result = await state.applyAssistantProposalChange(packet);
    assert.equal(packet.handled, true);
    assert.equal(result.applied, true);
    assert.equal(result.saved, true);
    assert.match(state.rationale, /&lt;img/);
    assert.doesNotMatch(state.rationale, /<img/);
    assert.deepEqual(state.control.events, ['input']);
    assert.equal(state.references, 'Keep references.');
    assert.deepEqual(state.literatureCitations, [{ source_link_id: 42 }]);
    assert.equal(state.generalObjective, 'Keep objective.');
    assert.equal(state.savesScheduled, 1);
    assert.match(result.notice, /updated and saved/);
});

test('accepted RRL revisions retain authoritative citation HTML and synchronize references', async () => {
    const before = '<p>First claim <span data-proposal-citation="42" data-proposal-locator="p. 8">[1, p. 8]</span>.</p><p>Second claim.</p>';
    const value = '<p>Second claim.</p><p>Clearer first claim <span data-proposal-citation="42" data-proposal-locator="p. 8">[1, p. 8]</span>.</p>';
    const state = editor({ field: 'related_literature', before });
    assert.equal(state.relatedLiterature, before);
    const result = await state.applyAssistantProposalChange({ ...candidate, field: 'related_literature', before, value });
    assert.equal(result.saved, true);
    assert.equal(state.relatedLiterature, value);
    assert.equal(state.referencesSynchronized, true);
    assert.equal(state.rationale, before);

    const invalid = editor({ field: 'related_literature', before });
    invalid.proposalChangeCitationsMatch = () => false;
    await assert.rejects(invalid.applyAssistantProposalChange({ ...candidate, field: 'related_literature', before, value: '<p>Lost source.</p>' }), /preserve the source citations/);
    assert.equal(invalid.relatedLiterature, before);
    assert.equal(invalid.savesScheduled, undefined);
});

test('RRL revision launch saves first and preserves an unfinished assistant message', async () => {
    const requests = [];
    const assistant = { draft: '', proposalChangesBusy: () => false, openDrawer() { requests.push('open'); },
        async sendPrompt(...args) { requests.push(args); } };
    let saves = 0;
    let canSave = true;
    const methods = new Function('Alpine', 'config', 'finishPaperEditorAutoSave', `return ({${methodSource('requestRrlRevision')}});`)(
        { store: () => assistant }, { proposalDraftId: 12 }, async () => { saves++; return canSave; });
    const state = { ...methods, $el: {}, rrlRevisionPending: false, proposalChangeError: '' };
    assistant.draft = 'Unfinished message';
    assert.equal(await state.requestRrlRevision(), false);
    assert.equal(assistant.draft, 'Unfinished message');
    assert.equal(saves, 0);
    assistant.draft = '';
    canSave = false;
    assert.equal(await state.requestRrlRevision(), false);
    assert.deepEqual(requests, []);
    canSave = true;
    assert.equal(await state.requestRrlRevision(), true);
    assert.equal(requests[0], 'open');
    assert.match(requests[1][0], /saved RRL/);
    assert.deepEqual(requests[1].slice(1), [{ type: 'suggest_proposal_changes' }, { proposal_draft_id: 12 }]);
    assert.equal(state.rrlRevisionPending, false);
});

test('the real resolve wrapper without a token still reaches the guarded editor', async () => {
    const { token: _omitted, ...serverCandidate } = candidate;
    const resolve = resolver(async () => ({ ok: true, json: async () => ({ proposal_change: serverCandidate }) }));
    const packet = await resolve(selection, '/resolve', 'csrf');
    const state = editor();
    const result = await state.applyAssistantProposalChange(packet);
    assert.equal(result.saved, true);
    assert.equal(packet.handled, true);
});

test('save failure reports applied editor text without claiming a successful save', async () => {
    const state = editor({ save: false });
    const result = await state.applyAssistantProposalChange({ ...candidate });
    assert.equal(result.applied, true);
    assert.equal(result.saved, false);
    assert.match(result.notice, /still needs to be saved/);
    assert.match(result.error, /Saving did not finish/);
    assert.ok(state.rationale.includes('Clearer rationale'));
});

test('stale, dirty, busy and unavailable fields cannot be overwritten', async () => {
    const variants = [
        (state) => { state.autoSaveBlocked = true; },
        (state) => { state.autoSaveInFlight = true; },
        (state) => { state.proposalChangeApplying = true; },
        (state) => { state.$el.dataset.paperSubmitting = 'true'; },
        (state) => { state.$el.inert = true; },
        (state) => { state.$el.isConnected = false; },
        (state) => { state.form.versions.document_version.value = '4'; },
        (state) => { state.form.versions.draft_version.value = '8'; },
        (state) => { state.rationale = 'Unsaved new rationale.'; },
        (state) => { state.control.value = 'Unsaved control value.'; },
        (state) => { state.detailedProposalFingerprint = () => 'dirty'; },
        (state) => { state.control.disabled = true; },
        (state) => { state.control.readOnly = true; },
    ];
    for (const change of variants) {
        const state = editor();
        change(state);
        const before = state.rationale;
        await assert.rejects(state.applyAssistantProposalChange({ ...candidate }));
        assert.equal(state.rationale, before);
        assert.equal(state.savesScheduled, undefined);
    }
});

test('wrong proposal, forbidden paths and cited text preserve the current editor', async () => {
    const state = editor();
    const other = { ...candidate, proposal_draft_id: 13 };
    assert.deepEqual(await state.applyAssistantProposalChange(other), { applied: false, saved: false });
    assert.equal(other.handled, undefined);
    await assert.rejects(state.applyAssistantProposalChange({ ...candidate, field: 'references' }), /invalid/);
    const before = '<p>Claim <span data-proposal-citation="42">[1]</span></p>';
    const cited = editor({ before });
    await assert.rejects(cited.applyAssistantProposalChange({ ...candidate, before }), /linked citations/);
    assert.equal(cited.rationale, before);
    assert.equal(cited.savesScheduled, undefined);
});

test('specific-objective changes target an existing row without changing its identity or siblings', async () => {
    const before = 'Identify community needs.';
    const state = editor({ field: 'specific_objectives.0.description', before, rich: false });
    state.specificObjectives.push({ id: 20, description: 'Keep the second objective.' });
    await state.applyAssistantProposalChange({ ...candidate, field: 'specific_objectives.0.description', before, value: 'Identify the stated community needs clearly.' });
    assert.deepEqual(state.specificObjectives, [{ id: 19, description: 'Identify the stated community needs clearly.' }, { id: 20, description: 'Keep the second objective.' }]);
    assert.equal(state.control.value.startsWith('<p>'), false);
    await assert.rejects(state.applyAssistantProposalChange({ ...candidate, id: 'missing-row', field: 'specific_objectives.2.description', before }), /field changed/);
});

test('repeated application does not change text or schedule a second save', async () => {
    const state = editor();
    await state.applyAssistantProposalChange({ ...candidate });
    const inserted = state.rationale;
    const result = await state.applyAssistantProposalChange({ ...candidate });
    assert.equal(result.applied, true);
    assert.equal(state.rationale, inserted);
    assert.equal(state.savesScheduled, 1);
});

test('both assistant surfaces render the shared read-only candidate and explicit Apply action', () => {
    const component = readFileSync(new URL('../../resources/views/components/research-assistant-proposal-changes.blade.php', import.meta.url), 'utf8');
    assert.match(component, /proposalChangeReplacementText\(suggestion\)/);
    assert.doesNotMatch(component, /x-html|textarea/);
    assert.match(component, /Apply to proposal/);
    assert.match(component, /Keep current/);
    assert.match(component, /Find additional studies/);
    for (const surface of ['workspace', 'drawer']) {
        assert.match(readFileSync(new URL(`../../resources/views/components/research-assistant-${surface}.blade.php`, import.meta.url), 'utf8'), /<x-research-assistant-proposal-changes\s*\/>/);
    }
});

test('the compiled preview applies, saves, reports save failure and refreshes stale suggestions in both themes', async () => {
    const compiled = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Blade::render('<x-research-assistant-proposal-changes />');
    `], { encoding: 'utf8', cwd: resolvePath('.'), env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1' } });
    assert.equal(compiled.status, 0, (compiled.stderr || compiled.stdout).slice(-3000));
    const styles = await postcss([tailwindcss(loadConfig(resolvePath('tailwind.config.js')))])
        .process(readFileSync(resolvePath('resources/css/app.css'), 'utf8'), { from: undefined });
    const proposalHelpers = readFileSync(resolvePath('resources/js/research-assistant-proposal-changes.js'), 'utf8').replace(/^import .+;\r?\n/gm, '').replace(/^export /gm, '');
    const markdownHelpers = readFileSync(resolvePath('resources/js/research-assistant-markdown.js'), 'utf8').replace(/^export /gm, '');
    const paperHelpers = readFileSync(resolvePath('resources/js/proposal-paper-autosave.js'), 'utf8').replace(/^export /gm, '');
    const storeMethods = ['proposalChangesBusy', 'proposalChangeBeforeText', 'proposalChangeReplacementText', 'keepCurrentProposalText', 'proposalRrlAssessmentText', 'proposalRrlRequirementSource', 'findProposalStudies', 'refreshProposalChanges', 'applyProposalChange'].map(methodSource).join('\n');
    const editorMethods = ['proposalChangeFieldValue', 'setProposalChangeFieldValue', 'applyAssistantProposalChange',
        'saveDetailedProposalNow', 'saveDetailedProposal', 'detailedProposalAutoSaveStatus'].map(methodSource).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const [width, dark, failSave] of [[320, false, false], [1280, true, true]]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            const message = { id: 1, proposal_changes: normalizeAssistantProposalChanges({
                proposal_draft_id: 12, proposal_title: 'Proposal <img src=x onerror=alert(1)>', editor_url: candidate.editor_url,
                suggestions: [candidate,
                    { ...candidate, id: 'suggestion-2', token: 'b'.repeat(64), field: 'executive_brief', label: 'Executive brief', before: '<p>Keep executive brief.</p>', value: 'Clear executive brief.' },
                    { ...candidate, id: 'rrl-change', token: 'c'.repeat(64), field: 'related_literature', label: 'RRL revision', before: '<p>Existing review.</p>', value: '<p>Clearer review <span data-proposal-citation="42" data-proposal-locator="p. 8">[1, p. 8]</span>.</p>' },
                ],
                assessment: { rrl: { count: 7, known_count: 7, count_status: 'tracked', minimum: 10, shortfall: 3, minimum_satisfied: false,
                    requirement_reference: 'Manual RRL', sources: [{ reference: 'Manual RRL', title: 'Research Manual — Detailed Research Proposal (p. 92)', page_start: 92, page_end: 92 }] } },
            }) };
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="csrf"><style>${styles.css}[x-cloak]{display:none!important}</style></head><body class="p-3 dark:bg-stone-950" data-research-assistant-proposal-changes-url="https://athena.test/resolve"><div data-detailed-proposal-workspace data-paper-editor data-proposal-draft-id="12" data-detailed-proposal-autosave="true" style="position:fixed;left:-10000px;width:1px;height:1px;overflow:hidden" x-data="proposalEditor" @athena-apply-proposal-change.window="$event.detail.promise = applyAssistantProposalChange($event.detail)"><form x-ref="form"><input type="hidden" name="document_version" value="3"><input type="hidden" name="draft_version" value="7"><textarea name="rationale" data-semantic-editor>&lt;p&gt;Existing rationale.&lt;/p&gt;</textarea></form></div><main x-data="proposalMessage" class="mx-auto max-w-3xl">${compiled.stdout}</main></body></html>`);
            await page.addScriptTag({ content: `${markdownHelpers}\n${proposalHelpers}\n${paperHelpers}\n${sameOriginPaperUrl.toString()}\n${resolverSource}
                const config = { proposalDraftId: 12, updateUrl: 'https://athena.test/save', csrfToken: 'csrf' };
                const sanitizedSemanticHtml = value => value;
                window.applyRequests = [];
                window.savedProposalForms = [];
                window.refreshRequests = [];
                window.failSave = ${failSave};
                window.locationOrigin = 'https://athena.test';
                const actualOrigin = window.location.origin;
                const sameOriginPaperUrlOriginal = sameOriginPaperUrl;
                sameOriginPaperUrl = (value) => sameOriginPaperUrlOriginal(value, 'https://athena.test');
                window.fetch = async (url, options) => {
                    if (url.endsWith('/resolve')) {
                        window.applyRequests.push(JSON.parse(options.body));
                        return { ok:true, json:async()=>({proposal_change:${JSON.stringify(candidate)}}) };
                    }
                    window.savedProposalForms.push([...options.body.entries()]);
                    return { ok:!window.failSave, status:window.failSave?500:200, json:async()=>window.failSave?{message:'Connection interrupted.'}:{document_version:4,draft_version:8,saved_as_draft:true,completion_errors:{}} };
                };
                async function finishPaperEditorAutoSave(root) {
                    const state = Alpine.$data(root);
                    return finishProposalPaperAutoSave({state,form:state.$refs.form,configuration:activeProposalPaperAutoSave(root.dataset)});
                }
                document.addEventListener('alpine:init', () => {
                    Alpine.data('proposalEditor', () => ({
                        ${editorMethods}
                        rationale:${JSON.stringify(candidate.before)},executiveBrief:'<p>Keep executive brief.</p>',methodology:{},specificObjectives:[],
                        proposalChangeApplying:false,proposalChangeAppliedIds:[],autoSaveBlocked:false,autoSaveInFlight:false,autoSaveRevision:0,
                        proposalChangeNotice:'',proposalChangeError:'',completionErrors:{},methodologyImages:[],
                        init(){this.lastSavedDetailedProposal=this.detailedProposalFingerprint(this.$refs.form);},
                        detailedProposalAutoSaveForm(){return this.$refs.form;},
                        detailedProposalFormData(form){return new FormData(form);},
                        detailedProposalFingerprint(form){return JSON.stringify([...new FormData(form)].filter(([name])=>!['document_version','draft_version'].includes(name)));},
                        detailedProposalShouldSaveAsDraft(){return true;},applySavedMethodologyImages(){},updateDetailedProposalCompletionStatus(){},
                        markProposalPreviewStale(){},triggerDetailedProposalAutoSave(){this.autoSaveRevision++;this.$el.dataset.paperDirty='true';},
                        scheduleDetailedProposalAutoSave(){},focusProposalRequirement(){},
                    }));
                    Alpine.data('proposalMessage', () => ({message:${JSON.stringify(message)}}));
                    Alpine.store('researchAssistant', {
                        ${storeMethods}
                        messages:[],requestSequence:0,proposalChangesOperationPending:false,isLoading:false,sendInProgress:false,retryAfter:0,draft:'',
                        hasConversation(){return false;},saveConversation(){return Promise.resolve({id:1});},
                        sendPrompt(...args){window.refreshRequests.push(args);return Promise.resolve();},
                    });
                });
            ` });
            await page.addScriptTag({ path: resolvePath('node_modules/alpinejs/dist/cdn.js') });
            await page.evaluate(() => { Alpine.store('researchAssistant').messages = [Alpine.$data(document.querySelector('main')).message]; });
            const panel = page.locator('[data-assistant-proposal-changes]');
            await expect(panel).toContainText('7 tracked reviewed studies; the proposal form requires at least 10');
            await expect(panel).toContainText('Add at least 3 more');
            await expect(panel).toContainText('Manual basis: Research Manual — Detailed Research Proposal (p. 92)');
            assert.equal(await panel.locator('img').count(), 0);
            const rrl = panel.locator('article').filter({ has: page.getByRole('heading', { name: 'RRL revision', exact: true }) });
            await expect(rrl).toContainText('Clearer review [1, p. 8].');
            await expect(rrl).toContainText('does not read or verify the cited papers');
            assert.equal(await rrl.locator('[data-proposal-citation]').count(), 0);
            await rrl.getByRole('button', { name: 'Keep current', exact: true }).click();
            await expect(rrl).toHaveCount(0);
            assert.equal(await page.evaluate(() => window.applyRequests.length), 0);
            const markersMatch = await page.evaluate((source) => {
                const check = new Function(`return ({${source}}).proposalChangeCitationsMatch;`)();
                const before = '<p>One <span data-proposal-citation="42" data-proposal-locator="p. 8">[1]</span>.</p><p>Two <span data-proposal-citation="43">[2]</span>.</p>';
                const reordered = '<p>Two <span data-proposal-citation="43">[2]</span>.</p><p>One <span data-proposal-citation="42" data-proposal-locator="p. 8">[1]</span>.</p>';
                return [check(before, reordered), check(before, reordered.replace('p. 8', 'p. 9')),
                    check(before, reordered.replace('43', '44')), check(before, `${reordered}<span data-proposal-citation="42" data-proposal-locator="p. 8">[1]</span>`)];
            }, methodSource('proposalChangeCitationsMatch'));
            assert.deepEqual(markersMatch, [true, false, false, false]);
            await page.screenshot({ path: resolvePath(`storage/framework/testing/proposal-changes-${width}-${dark ? 'dark' : 'light'}-preview.png`), fullPage: true });
            const apply = panel.getByRole('button', { name: 'Apply to proposal', exact: true }).first();
            await apply.click();
            await expect.poll(() => page.evaluate(() => window.savedProposalForms.length)).toBe(1);
            await expect(panel.getByRole('button', { name: failSave ? 'Added to editor' : 'Applied and saved', exact: true })).toBeDisabled();
            await expect(panel).toContainText(failSave ? 'Saving did not finish' : 'updated and saved');
            await expect(panel).toContainText('Request updated suggestions before applying another change');
            await page.screenshot({ path: resolvePath(`storage/framework/testing/proposal-changes-${width}-${dark ? 'dark' : 'light'}-applied.png`), fullPage: true });
            const state = await page.evaluate(() => ({value:Alpine.$data(document.querySelector('[data-paper-editor]')).rationale,requests:window.applyRequests}));
            assert.match(state.value, /&lt;img/);
            assert.deepEqual(state.requests, [selection]);
            await panel.getByRole('button', { name: 'Request updated suggestions', exact: true }).click();
            assert.equal(await page.evaluate(() => window.refreshRequests[0][1].type), 'suggest_proposal_changes');
            await page.evaluate(() => {
                const message = Alpine.$data(document.querySelector('main')).message;
                message.proposal_changes.suggestions = [];
            });
            await expect(panel).toContainText('Saved proposal check');
            await expect(panel.getByRole('button', { name: 'Apply to proposal', exact: true })).toHaveCount(0);
            await expect(panel.getByText('Review each suggestion before applying it to your detailed proposal.', { exact: true })).toBeHidden();
            await panel.getByRole('button', { name: 'Find additional studies', exact: true }).click();
            assert.equal(await page.evaluate(() => window.refreshRequests.at(-1)[1].type), 'search_literature');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
