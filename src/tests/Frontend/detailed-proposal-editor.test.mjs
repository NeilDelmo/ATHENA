import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { chromium } from '@playwright/test';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';

function editor(overrides = {}, { keepAutoSave = false } = {}) {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = source.indexOf("Alpine.data('proposalDraftDetailedProposal'");
    const end = source.indexOf('\n}));', start) + '\n}));'.length;
    let factory;
    const context = {
        Alpine: { data: (_, callback) => { factory = callback; } },
        proposalPreviewWorkspace: () => ({}),
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
