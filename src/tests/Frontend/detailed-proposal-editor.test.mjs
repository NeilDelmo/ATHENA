import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { chromium } from '@playwright/test';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';
import { proposalCitationField } from '../../resources/js/proposal-semantic-editor.js';

function editor(overrides = {}, { keepAutoSave = false } = {}) {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = source.indexOf("Alpine.data('proposalDraftDetailedProposal'");
    const end = source.indexOf('\n}));', start) + '\n}));'.length;
    let factory;
    const context = {
        Alpine: { data: (_, callback) => { factory = callback; } },
        proposalPreviewWorkspace: () => ({}),
        detailedProposalPreviewWorkspace: () => ({}),
        window: { crypto: { randomUUID: () => 'client-id' } },
        document: { body: { classList: { add() {} } }, getElementById: () => null },
        HTMLElement: class {},
        URL: { createObjectURL: () => 'blob:preview' },
        ...overrides,
    };
    vm.runInNewContext(source.slice(start, end), context);
    const instance = factory({ figureSections: { rationale: 'Rationale', research_design: 'Research Design', data_analysis: 'Data Analysis' } });
    if (!keepAutoSave) instance.triggerDetailedProposalAutoSave = () => {};
    instance.$nextTick = (callback) => callback();
    instance.assignMethodologyImageFile = () => {};
    return instance;
}

test('new and reopened figures keep the selected narrative section', () => {
    const state = editor();
    assert.equal(state.newMethodologyImage({ section: 'rationale' }).section, 'rationale');
    state.handleMethodologyDrop({ dataTransfer: { files: [{ type: 'image/png', name: 'chart.png', size: 100 }] } }, 'data_analysis');
    assert.equal(state.methodologyImages[0].section, 'data_analysis');
    assert.equal(state.methodologyImages[0].currentFile.name, 'chart.png');
});

test('preview validation and server failures retain the last rendered paper and allow retry', async () => {
    let result = { status: 422, ok: false, json: async () => ({ errors: { rationale: ['Check the rationale.'] } }) };
    const state = editor({ fetch: async () => result });
    state.formData = () => new FormData();
    state.previewRevision = 0;
    state.previewHtml = '<p>Last rendered paper</p>';
    await state.generatePreview();
    assert.equal(state.previewHtml, '<p>Last rendered paper</p>');
    assert.equal(state.previewReady, true);
    assert.equal(state.previewLoading, false);
    assert.equal(state.previewStale, true);
    assert.equal(state.validationMessage, 'Check the rationale.');

    result = { status: 500, ok: false };
    await state.generatePreview();
    assert.equal(state.previewHtml, '<p>Last rendered paper</p>');
    assert.match(state.previewError, /could not be generated/);

    result = { status: 200, ok: true, text: async () => '<p>Current paper</p>' };
    await state.generatePreview();
    assert.equal(state.previewHtml, '<p>Current paper</p>');
    assert.equal(state.previewStale, false);
    assert.equal(state.previewError, '');
});

test('edits during a preview request schedule another refresh without concurrent requests', async () => {
    let complete;
    let requests = 0;
    const state = editor({ fetch: () => {
        requests++;
        return new Promise((resolve) => { complete = resolve; });
    } });
    state.formData = () => new FormData();
    state.previewRevision = 0;
    let scheduled = 0;
    state.scheduleDetailedProposalPreview = () => scheduled++;
    const pending = state.generatePreview();
    await state.generatePreview();
    assert.equal(requests, 1);
    state.previewRevision++;
    complete({ ok: true, status: 200, text: async () => '<p>Earlier revision</p>' });
    await pending;
    assert.equal(state.previewStale, true);
    assert.equal(scheduled, 1);
});

test('source usage recognizes the official literature heading and its saved opening paragraphs', () => {
    const state = editor({ proposalCitationField });
    state.activeProposalCitations = () => [
        { source_link_id: 42, field: 'related_literature' },
        { source_link_id: 43, field: 'introduction' },
    ];
    state.citationReferenceNumber = () => null;
    assert.equal(state.literatureSourceUsage({ id: 42 }).usedInRrl, true);
    assert.equal(state.literatureSourceUsage({ id: 43 }).usedInRrl, true);
    assert.equal(state.literatureSourceUsage({ id: 44 }).usedInRrl, false);
});

test('removing the last saved figure refreshes the cached preview before autosave finishes', () => {
    const state = editor({ proposalPreviewWorkspace }, { keepAutoSave: true });
    state.$el = { dataset: {} };
    state.$refs = {};
    state.scheduleDetailedProposalAutoSave = () => {};
    state.previewHtml = '<img src="saved-figure.png">';
    const image = state.newMethodologyImage({ id: 'saved-image', previewUrl: '/saved-figure.png' });
    state.methodologyImages = [image];
    let generated = 0;
    state.generatePreview = function () {
        generated++;
        assert.equal(this.methodologyImages.length, 0);
        this.previewHtml = '<p>Proposal without figures</p>';
        this.previewStale = false;
    };

    state.removeMethodologyImage(image);
    assert.equal(state.previewStale, true);
    assert.equal(state.$el.dataset.paperDirty, 'true');
    state.showProposalPreview();
    assert.equal(generated, 1);
    assert.doesNotMatch(state.previewHtml, /saved-figure/);
    state.showProposalPreview();
    assert.equal(generated, 1);
});

test('specific objective requests use the current rows even before Alpine updates field names', () => {
    class FormDataSnapshot extends FormData {
        constructor(form) {
            super();
            for (const [key, value] of form.entries) this.append(key, value);
        }
    }
    const state = editor({ FormData: FormDataSnapshot });
    const form = { entries: [['_method', 'PUT'], ['specific_objectives[0][description]', 'Old DOM value']] };
    state.specificObjectives = [{ id: 1, description: 'Assess recovery.' }, { id: 2, description: 'Validate monitoring.' }];
    const data = state.detailedProposalFormData(form);
    assert.equal(data.get('specific_objectives[0][description]'), 'Assess recovery.');
    assert.equal(data.get('specific_objectives[1][description]'), 'Validate monitoring.');
    assert.equal(data.get('specific_objectives_present'), '1');

    state.specificObjectives = [];
    const cleared = state.detailedProposalFormData(form);
    assert.equal(cleared.has('specific_objectives[0][description]'), false);
    assert.equal(cleared.get('specific_objectives_present'), '1');
});

test('adding reordering and removing objectives trigger autosave without needing a text edit', () => {
    const state = editor();
    let changes = 0;
    state.triggerDetailedProposalAutoSave = () => { changes += 1; };
    state.addSpecificObjective();
    state.addSpecificObjective();
    const firstId = state.specificObjectives[0].id;
    state.moveSpecificObjective(0, 1);
    assert.equal(state.specificObjectives[1].id, firstId);
    state.removeSpecificObjective(0);
    assert.equal(changes, 4);
});

test('dragging an existing figure moves it between sections and updates numbering', () => {
    const state = editor();
    const image = state.newMethodologyImage({ section: 'research_design' });
    const earlier = state.newMethodologyImage({ section: 'rationale' });
    image.clientId = 'moving';
    state.methodologyImages = [image, earlier];
    assert.equal(state.methodologyImageFigureNumber(image), 2);
    state.startMethodologyImageDrag(image.clientId);
    state.handleMethodologyDrop({ dataTransfer: { files: [] } }, 'rationale');
    assert.equal(image.section, 'rationale');
    assert.equal(state.methodologyImageFigureNumber(image), 2);
    assert.equal(state.draggedMethodologyImage, '');
});

test('oversized and unsupported dropped images are rejected before autosave', () => {
    const state = editor();
    state.addMethodologyImages([{ type: 'image/png', size: 11 * 1024 * 1024 }], 'rationale');
    assert.equal(state.methodologyImages.length, 0);
    assert.match(state.validationMessage, /10 MB/);
    state.addMethodologyImages([{ type: 'image/svg+xml', size: 100 }], 'rationale');
    assert.equal(state.methodologyImages.length, 0);
    assert.match(state.validationMessage, /PNG/);
});

test('reopening a full text draft uses available abstract evidence until full text is loaded again', () => {
    const state = editor();
    state.literatureReviewContextFromRrl = () => '';
    state.openLiteratureReview({ rrl_evidence_basis: 'full_text', rrl_note: 'Saved paragraph.', description: 'Indexed abstract evidence. '.repeat(5) });
    assert.equal(state.literatureReviewBasis, 'abstract');
    assert.equal(state.literatureReviewFullText, '');
    assert.equal(state.hasLiteratureReviewEvidence(), true);
    assert.equal(state.literatureReviewDraft, 'Saved paragraph.');
    assert.match(state.literatureReviewNotice, /Load the public full text again/);
});

test('sources without usable evidence explain why generation is disabled', () => {
    const state = editor();
    state.literatureReviewSource = { description: '' };
    assert.equal(state.hasLiteratureReviewEvidence(), false);
    assert.match(state.literatureReviewEvidenceMessage(), /no usable abstract/);
    state.literatureReviewBasis = 'full_text';
    assert.match(state.literatureReviewEvidenceMessage(), /Load the public full text/);
});

test('RRL generation displays actionable validation errors and preserves existing notes', async () => {
    const state = editor({ fetch: async () => ({ ok: false, status: 422, json: async () => ({ message: 'Invalid input.', errors: { abstract: ['The abstract is too short.'] } }) }) });
    state.literatureReviewSource = { title: 'Paper', description: 'Evidence '.repeat(15) };
    state.literatureReviewDraft = 'Saved paragraph.';
    await state.generateLiteratureReviewDraft();
    assert.equal(state.literatureReviewError, 'The abstract is too short.');
    assert.equal(state.literatureReviewGenerating, false);
    assert.equal(state.literatureReviewDraft, 'Saved paragraph.');
});

test('missing nested requirements focus the exact named field', () => {
    class Element { setAttribute() {} }
    const target = new Element();
    target.name = 'specific_method_objectives[0][heading]';
    target.type = 'textarea';
    const state = editor({ HTMLElement: Element });
    state.$refs = { form: { querySelectorAll: () => [target] } };
    state.closeProposalPreview = () => {};
    state.highlightDetailedProposalField = () => {};
    let focused;
    state.focusDetailedProposalField = (field) => { focused = field; };
    state.focusProposalRequirement('specific_method_objectives.0.heading');
    assert.equal(focused, target);
});

test('an empty objective list leaves a visible required row while preserving the general objective', () => {
    const state = editor();
    state.plainText = (value) => String(value ?? '').replace(/<[^>]*>/g, '').trim();
    const result = state.normalizedObjectives('<p>samplee</p>', []);
    assert.equal(result.generalObjective, '<p>samplee</p>');
    assert.equal(result.specificObjectives.length, 1);
    assert.equal(result.specificObjectives[0].description, '');
    state.specificObjectives = result.specificObjectives;
    state.removeSpecificObjective(0);
    assert.equal(state.specificObjectives.length, 1);
    assert.equal(state.specificObjectives[0].description, '');
});

test('missing objective links focus an empty specific field instead of the filled general field', () => {
    class Element { setAttribute() {} }
    const general = Object.assign(new Element(), { name: 'general_objective', type: 'textarea', value: 'samplee' });
    const filled = Object.assign(new Element(), { name: 'specific_objectives[0][description]', type: 'textarea', value: 'Measure habitat recovery.' });
    const empty = Object.assign(new Element(), { name: 'specific_objectives[1][description]', type: 'textarea', value: '' });
    const state = editor({ HTMLElement: Element });
    state.specificObjectives = [{ description: filled.value }, { description: '' }];
    state.$refs = { form: { querySelectorAll: () => [general, filled, empty] } };
    state.closeProposalPreview = () => {};
    state.highlightDetailedProposalField = () => {};
    let focused;
    state.focusDetailedProposalField = (field) => { focused = field; };
    state.focusProposalRequirement('specific_objectives');
    assert.equal(focused, empty);
});

test('the objective editor shows and focuses the required field when only a general objective is saved', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = app.indexOf("Alpine.data('proposalDraftDetailedProposal'");
    const end = app.indexOf('\n}));', start) + '\n}));'.length;
    const view = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php', import.meta.url), 'utf8');
    const sectionStart = view.indexOf('<section data-revision-section="section-objectives"');
    const section = view.slice(sectionStart, view.indexOf('</section>', sectionStart) + '</section>'.length)
        .replace(/{{ config\('detailed_proposal.maximum_narrative_length'\) }}/g, '12000');
    const alpine = readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.setContent(`<div x-data="objectiveTest"><button id="missing-objective" @click="focusProposalRequirement('specific_objectives')">Missing Specific Objective</button><form x-ref="form">${section}</form></div>`);
            await page.addScriptTag({ content: `
                const proposalPreviewWorkspace = () => ({});
                const detailedProposalPreviewWorkspace = () => ({});
                const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
                ${app.slice(start, end)}
                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('objectiveTest', () => {
                        const state = window.proposalFactory({ completionErrors: { specific_objectives: ['Add at least one Specific Objective below the optional General Objective.'] } });
                        state.init = function () {
                            const objectives = this.normalizedObjectives('samplee', []);
                            this.generalObjective = objectives.generalObjective;
                            this.specificObjectives = objectives.specificObjectives;
                            window.objectiveState = this;
                        };
                        state.closeProposalPreview = () => {};
                        state.triggerDetailedProposalAutoSave = () => {};
                        return state;
                    });
                });` });
            await page.addScriptTag({ content: alpine });
            const required = page.locator('textarea[name="specific_objectives[0][description]"]');
            await required.waitFor({ state: 'visible' });
            assert.equal(await page.locator('#general-objective').inputValue(), 'samplee');
            assert.equal(await required.evaluate((field) => field.required), true);
            await page.locator('#missing-objective').click();
            assert.equal(await required.evaluate((field) => field === document.activeElement), true);
            assert.equal(await required.getAttribute('aria-invalid'), 'true');
            assert.equal(await page.locator('#general-objective').getAttribute('aria-invalid'), null);
            assert.equal(await page.locator('#specific-objectives').getAttribute('data-detailed-proposal-invalid'), null);
            await required.fill('Measure habitat recovery.');
            const saved = await page.evaluate(() => Object.fromEntries(window.objectiveState.detailedProposalFormData()));
            assert.equal(saved.general_objective, 'samplee');
            assert.equal(saved['specific_objectives[0][description]'], 'Measure habitat recovery.');
            await page.getByRole('button', { name: 'Remove', exact: true }).click();
            assert.equal(await required.count(), 1);
            assert.equal(await required.inputValue(), '');
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('an abstract-based generated draft enables insertion and adds its matching IEEE reference', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = app.indexOf("Alpine.data('proposalDraftDetailedProposal'");
    const end = app.indexOf('\n}));', start) + '\n}));'.length;
    const helpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replaceAll('export ', '');
    const view = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/detailed-proposal/partials/literature-workspace.blade.php', import.meta.url), 'utf8');
    const generateButton = view.match(/<button[^>]+x-on:click="generateLiteratureReviewDraft\('auto'\)"[^>]*><\/button>/)[0];
    const insertButton = view.match(/<button[^>]+x-on:click="saveLiteratureReview\(true\)"[^>]*><\/button>/)[0];
    const alpine = readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent(`<div x-data="rrlTest">${generateButton}${insertButton}</div>`);
        await page.addScriptTag({ content: `${helpers}
            const proposalPreviewWorkspace = () => ({});
            const detailedProposalPreviewWorkspace = () => ({});
            const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
            ${app.slice(start, end)}
            window.state = window.proposalFactory({ literatureSynthesisUrl: '/synthesize', literatureDraftUpdateUrlTemplate: '/draft/__proposal_literature_source__' });
            delete window.state.init;
            window.state.triggerDetailedProposalAutoSave = () => {};
            window.state.focusLiteratureDestination = () => {};
            window.state.openLiteratureReview({ id: 7, literature_source_id: 70, title: 'Community Monitoring', description: 'Community participation supports continuous mangrove monitoring and regular environmental observation.', reference: 'M. Santos, "Community Monitoring," Journal of Coastal Research, 2024.' });
            window.requests = [];
            window.fetch = async (url, options) => {
                const body = JSON.parse(options.body);
                window.requests.push({ url, body });
                return { ok: true, json: async () => url === '/synthesize'
                    ? { synthesis: 'The abstract reports that sustained community participation supports continuous mangrove monitoring and more regular environmental observation. These findings suggest a role for residents in local monitoring programs.', basis: 'abstract' }
                    : { source: { ...window.state.literatureReviewSource, ...body } } };
            };
            document.addEventListener('alpine:init', () => {
                window.Alpine.data('rrlTest', () => ({ ...window.state, init() { window.state = this; } }));
            });
        ` });
        await page.addScriptTag({ content: alpine });
        const insert = page.getByRole('button', { name: 'Add to RRL + reference', exact: true });
        assert.equal(await insert.isDisabled(), true);
        await page.getByRole('button', { name: 'Generate RRL draft', exact: true }).click();
        await page.waitForFunction(() => window.state.literatureReviewDraft.length >= 40);
        assert.equal(await insert.isEnabled(), true);
        await insert.click();
        await page.waitForFunction(() => window.state.references.includes('Community Monitoring'));
        const result = await page.evaluate(() => ({ rrl: window.state.relatedLiterature, references: window.state.references, citations: window.state.literatureCitations, requests: window.requests, notice: window.state.literatureSourceNotice }));
        assert.match(result.rrl, /The abstract reports/);
        assert.match(result.rrl, /data-proposal-citation="7"> \[1\]/);
        assert.match(result.references, /data-proposal-managed-reference="7"/);
        assert.match(result.references, /\[1\].*Community Monitoring/);
        assert.equal(result.citations.length, 1);
        assert.equal(result.requests[0].body.evidence_basis, 'abstract');
        assert.equal(result.requests[1].body.rrl_evidence_basis, 'abstract');
        assert.equal(result.requests[1].body.rrl_draft_status, 'confirmed');
        assert.match(result.notice, /RRL paragraph and its reference were added/);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

test('shared-library reviewed abstract drafts can be confirmed for proposal insertion', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = app.indexOf("Alpine.store('literatureSearch'");
    const end = app.indexOf('\n});', start) + '\n});'.length;
    let state;
    vm.runInNewContext(app.slice(start, end), { Alpine: { store: (_, value) => { state = value; } } });
    state.synthesisSource = { id: 7 };
    state.synthesisDraft = 'A reviewed paragraph based on the indexed abstract describes community monitoring.';
    state.synthesisBasis = 'abstract';
    state.synthesisApplyTo = 'both';
    const saved = [];
    let redirected;
    state.persistSynthesisDraft = async (status) => { saved.push(status); return true; };
    state.redirectToDetailedProposal = (source, action) => { redirected = { source, action }; };
    await state.confirmSynthesis();
    assert.deepEqual(saved, ['confirmed']);
    assert.equal(redirected.source.id, 7);
    assert.equal(redirected.action, 'both');
    assert.equal(state.synthesisError, '');
});

test('detailed proposal signature titles retain their case on screen and in print', async () => {
    const css = readFileSync(new URL('../../resources/css/detailed-proposal-print.css', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        const page = await browser.newPage();
        await page.setContent(`<style>${css}</style><p class="detailed-proposal-signature-name">Assoc. Prof. ALBERTSON D. AMANTE</p>`);
        for (const media of ['screen', 'print']) {
            await page.emulateMedia({ media });
            const name = await page.locator('.detailed-proposal-signature-name').evaluate((element) => ({
                text: element.innerText,
                transform: getComputedStyle(element).textTransform,
                weight: getComputedStyle(element).fontWeight,
                decoration: getComputedStyle(element).textDecorationLine,
            }));
            assert.equal(name.text, 'Assoc. Prof. ALBERTSON D. AMANTE');
            assert.equal(name.transform, 'none');
            assert.equal(name.weight, '700');
            assert.equal(name.decoration, 'underline');
        }
    } finally {
        await browser.close();
    }
});

test('incomplete drafts stay quiet until requirements are checked, with contextual field feedback', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = app.indexOf("Alpine.data('proposalDraftDetailedProposal'");
    const end = app.indexOf('\n}));', start) + '\n}));'.length;
    const view = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php', import.meta.url), 'utf8');
    const panelStart = view.indexOf('<section x-show="showCompletionChecklist"');
    const panel = view.slice(panelStart, view.indexOf('</section>', panelStart) + '</section>'.length);
    const status = readFileSync(new URL('../../resources/views/components/proposal-autosave-status.blade.php', import.meta.url), 'utf8');
    const statusWrapper = view.match(/<div x-show="showDetailedProposalSaveStatus"[^>]*>[\s\S]*?<\/div>/)[0].replace('<x-proposal-autosave-status />', status);
    const validation = view.match(/<div x-show="validationMessage"[^>]*><\/div>/)[0];
    const checkButton = view.match(/<button type="button" @click="checkDetailedProposalRequirements\(\)"[^>]*>[\s\S]*?<\/button>/)[0];
    const helpers = readFileSync(new URL('../../resources/js/proposal-paper-autosave.js', import.meta.url), 'utf8').replaceAll('export ', '');
    const alpine = readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8');
    const manifest = JSON.parse(readFileSync(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'));
    const cssFiles = [manifest['resources/css/app.css'].file, ...(manifest['resources/js/app.js'].css ?? [])];
    const css = [...new Set(cssFiles)].map(file => readFileSync(new URL(`../../public/build/${file}`, import.meta.url), 'utf8')).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${css}[x-cloak]{display:none!important}</style><body>
                    <div x-data="completionPanelTest" data-paper-editor data-detailed-proposal-autosave="true" data-paper-project-details-complete="true" class="mx-auto max-w-4xl space-y-6 p-4">
                        ${validation}${statusWrapper}${checkButton}${panel}
                        <form x-ref="form"><input type="hidden" name="save_as_draft" data-paper-save-mode value="0">
                            <label for="responsibility-percentage">Responsibility percentage</label>
                            <input id="responsibility-percentage" name="responsibilities[0][percentage]" type="number" required min="1" max="100" aria-label="Responsibility percentage" class="block w-full rounded-lg border-slate-300 bg-white dark:bg-slate-900 dark:text-white">
                            <label for="leader-contact">Leader contact</label><input id="leader-contact" name="leader_contact" required class="block w-full rounded-lg border-slate-300 bg-white dark:bg-slate-900 dark:text-white">
                        </form>
                    </div></body></html>`);
                await page.addScriptTag({ content: `
                    ${helpers}
                    const proposalPreviewWorkspace = () => ({});
                const detailedProposalPreviewWorkspace = () => ({});
                    const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
                    ${app.slice(start, end)}
                    const missing = { 'responsibilities.0.percentage': ['The member responsibility percentage field is required.'] };
                    window.savedRequests = [];
                    window.fetch = async (_, options) => {
                        const draft = options.body.get('save_as_draft') === '1';
                        window.savedRequests.push(draft);
                        return { status: draft ? 200 : 422, ok: draft, json: async () => draft
                            ? { saved_as_draft: true, document_version: 1, draft_version: 1, completion_errors: missing }
                            : { errors: missing } };
                    };
                    document.addEventListener('alpine:init', () => window.Alpine.data('completionPanelTest', () => {
                        const state = window.proposalFactory({ updateUrl: '/save', completionErrors: missing });
                        state.init = function () {
                            window.completionPanelState = this;
                            this.sdgs = [1];
                            this.expectedOutputs = { products: [{ description: 'A report.' }] };
                            this.startDetailedProposalAutoSave();
                            this.lastSavedDetailedProposal = '';
                        };
                        state.detailedProposalFingerprint = () => 'changed';
                        state.detailedProposalFormData = form => new FormData(form);
                        state.closeProposalPreview = () => {};
                        state.triggerDetailedProposalAutoSave = () => {};
                        return state;
                    }));` });
                await page.addScriptTag({ content: alpine });
                await page.waitForFunction(() => window.completionPanelState);
                const checklist = page.locator('[data-proposal-completion-checklist]');
                assert.equal(await checklist.isVisible(), false);
                assert.equal(await page.locator('[aria-invalid="true"]').count(), 0);
                if (process.env.ATHENA_DESIGN_SNAPSHOTS === '1' && width === 1440 && !dark) {
                    await page.screenshot({ path: '../tmp/detailed-proposal-writing.png' });
                }
                await page.evaluate(() => window.completionPanelState.saveDetailedProposal());
                assert.equal(await checklist.isVisible(), false);
                assert.equal(await page.locator('[data-proposal-autosave-status]').isVisible(), true);
                assert.match(await page.locator('[data-proposal-autosave-status]').innerText(), /Draft saved just now/);
                await page.getByRole('spinbutton', { name: 'Responsibility percentage' }).focus();
                await page.keyboard.press('Tab');
                await page.locator('[data-proposal-field-feedback]').waitFor({ state: 'visible' });
                assert.equal(await checklist.isVisible(), false);
                assert.equal(await page.locator('#leader-contact').getAttribute('aria-invalid'), null);
                assert.equal(await page.locator('#responsibility-percentage').getAttribute('aria-invalid'), 'true');
                const feedbackId = await page.locator('#responsibility-percentage').getAttribute('aria-describedby');
                assert.equal(await page.locator('#'+feedbackId).isVisible(), true);
                await page.getByRole('button', { name: 'Check requirements' }).click();
                await checklist.waitFor({ state: 'visible' });
                if (process.env.ATHENA_DESIGN_SNAPSHOTS === '1' && width === 1440 && !dark) {
                    await page.screenshot({ path: '../tmp/detailed-proposal-requirements.png' });
                }
                assert.equal(await page.getByRole('button', { name: 'Check requirements' }).evaluate(button => button === document.activeElement), true);
                assert.deepEqual(await page.evaluate(() => window.savedRequests), [false, true]);
                const color = await checklist.evaluate(element => {
                    const context = document.createElement('canvas').getContext('2d');
                    context.fillStyle = getComputedStyle(element).backgroundColor;
                    context.fillRect(0, 0, 1, 1);
                    return Array.from(context.getImageData(0, 0, 1, 1).data);
                });
                assert.ok(Math.abs(color[0] - color[1]) < 10, `Expected a neutral panel: ${color}`);
                await checklist.getByRole('button', { name: 'Complete Responsibility percentage.' }).click();
                assert.equal(await page.getByRole('spinbutton', { name: 'Responsibility percentage' }).evaluate(field => field === document.activeElement), true);
                await page.getByRole('spinbutton', { name: 'Responsibility percentage' }).fill('100');
                assert.equal(await page.locator('#responsibility-percentage').getAttribute('aria-invalid'), null);
                assert.equal(await page.locator('#'+feedbackId).isVisible(), false);
                await page.locator('#leader-contact').fill('09123456789');
                await page.getByRole('button', { name: 'Check requirements' }).click();
                await checklist.waitFor({ state: 'hidden' });
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                await page.evaluate(() => {
                    const wrapper = document.createElement('div');
                    const textarea = document.createElement('textarea');
                    textarea.name = 'rationale';
                    textarea.required = true;
                    textarea.dataset.semanticEditor = '';
                    textarea.className = 'sr-only';
                    textarea.value = '<p><br></p>';
                    const semantic = document.createElement('div');
                    semantic.contentEditable = 'true';
                    semantic.setAttribute('role', 'textbox');
                    semantic.setAttribute('aria-label', 'Rationale narrative');
                    semantic.innerHTML = '<p><br></p>';
                    textarea._semanticEditor = semantic;
                    semantic.addEventListener('input', () => {
                        textarea.value = semantic.innerHTML;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    wrapper.append(textarea, semantic);
                    window.completionPanelState.$refs.form.append(wrapper);
                });
                const narrative = page.getByRole('textbox', { name: 'Rationale narrative' });
                assert.equal(await narrative.getAttribute('aria-invalid'), null);
                await narrative.focus();
                await page.keyboard.press('Tab');
                assert.equal(await narrative.getAttribute('aria-invalid'), 'true');
                assert.equal(await page.evaluate(() => window.completionPanelState.validateForm()), false);
                assert.equal(await narrative.evaluate(field => field === document.activeElement), true);
                await narrative.fill('A revised rationale with meaningful content.');
                assert.equal(await narrative.getAttribute('aria-invalid'), null);
                assert.equal(await page.evaluate(() => window.completionPanelState.validateForm()), true);
                await page.evaluate(() => window.completionPanelState.detailedProposalAutoSaveStatus('Could not save.', 'error'));
                await page.locator('[data-proposal-autosave-status]').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-proposal-autosave-status]').isVisible(), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('draft and saving indicators use neutral colors and empty output rows do not pass requirements', () => {
    const state = editor();
    state.plainText = (value) => String(value ?? '').trim();
    state.completionErrors = { research_agenda: ['Required.'] };
    assert.equal(state.showCompletionChecklist, false);
    assert.equal(state.showDetailedProposalSaveStatus, true);
    assert.doesNotMatch(state.detailedProposalCompletionClasses(), /amber|yellow/);
    state.requirementsReviewed = true;
    assert.equal(state.showCompletionChecklist, true);
    state.expectedOutputs = { products: [{ description: '' }] };
    assert.equal(state.hasDetailedProposalExpectedOutput(), false);
    state.expectedOutputs.products[0].description = 'A community report.';
    assert.equal(state.hasDetailedProposalExpectedOutput(), true);
    state.detailedProposalSaveState = 'saved';
    assert.equal(state.showDetailedProposalSaveStatus, true);
});
