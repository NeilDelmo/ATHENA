import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('proposalDraftDetailedProposal'");
const controller = app.slice(start, app.indexOf('\n}));', start) + '\n}));'.length);

test('legacy nested rows request one canonical full-form save and canonical or empty rows do not', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent('<div id="fixtures"></div>');
        await page.addScriptTag({ content: `
            const proposalSourceWorkspace = () => ({}), proposalPreviewWorkspace = () => ({}), detailedProposalPreviewWorkspace = () => ({});
            const formatPersonName = (value) => String(value || '');
            const assistantLiteratureHandoff = () => ({ read: () => null });
            const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
            ${controller}
            document.addEventListener('alpine:init', () => window.Alpine.data('canonicalRows', () => {
                const state = window.proposalFactory({ initialData: window.fixture, expectedOutputKeys: ['publications', 'products'], methodologyKeys: ['research_design', 'specific_methods', 'data_analysis'] });
                state.saves = 0;
                state.triggerDetailedProposalAutoSave = function () { this.saves++; };
                state.detailedProposalFingerprint = function (form) { return JSON.stringify([...this.detailedProposalFormData(form)]); };
                state.synchronizeLiteratureCitations = () => {};
                state.refreshSuggestedLiteratureQuery = () => {};
                state.applyInitialLiteratureSource = () => {};
                state.destroySourceWorkspace = () => {};
                state.destroyDetailedProposalPreview = () => {};
                return state;
            }));
        ` });
        await page.addScriptTag({ path: new URL('../../node_modules/alpinejs/dist/cdn.js', import.meta.url).pathname.replace(/^\/(.:)/, '$1') });
        const saved = { general_objective: 'Understand local monitoring.', rationale: '<p>Keep the rationale.</p>', references: '<p>Keep the references.</p>',
            specific_objectives: [{ description: '' }, { description: '<p>1. Compare reporting methods.</p>' }],
            specific_method_objectives: [{ heading: '', methods: [{ description: '' }] }, { heading: '<p>Comparison</p>', methods: [{ description: '' }, { description: '<p>Review the reports.</p>' }] }],
            expected_outputs: { publications: [{ quantity: 2, unit: 'reports', description: '<p>Monitoring report</p>' }], products: '<p>Monitoring toolkit</p>' },
            methodology: { research_design: '<p>Keep the design.</p>', data_analysis: '<p>Keep the analysis.</p>' } };
        const run = async (fixture) => {
            await page.evaluate((data) => {
                document.getElementById('fixtures').innerHTML = '';
                window.fixture = data;
                document.getElementById('fixtures').innerHTML = `<main x-data="canonicalRows"><form x-ref="form">
                    <textarea name="general_objective" x-model="generalObjective"></textarea><textarea name="rationale" x-model="rationale"></textarea><textarea name="references" x-model="references"></textarea>
                    <textarea name="methodology[research_design]" x-model="methodology.research_design"></textarea><textarea name="methodology[data_analysis]" x-model="methodology.data_analysis"></textarea>
                    <template x-for="(row,index) in specificObjectives" :key="row.id"><textarea :name="'specific_objectives['+index+'][description]'" x-model="row.description"></textarea></template>
                    <template x-for="(group,index) in specificMethodGroups" :key="group.id"><div><textarea :name="'specific_method_objectives['+index+'][heading]'" x-model="group.heading"></textarea>
                        <template x-for="(row,methodIndex) in group.methods" :key="row.id"><textarea :name="'specific_method_objectives['+index+'][methods]['+methodIndex+'][description]'" x-model="row.description"></textarea></template></div></template>
                    <template x-for="key in ['publications','products']"><div><template x-for="(row,index) in expectedOutputs[key]" :key="row.id"><textarea :name="'expected_outputs['+key+']['+index+'][description]'" x-model="row.description"></textarea></template></div></template>
                </form></main>`;
            }, fixture);
            await page.waitForFunction(() => !!window.Alpine.$data(document.querySelector('main')).lastSavedDetailedProposal
                || window.Alpine.$data(document.querySelector('main')).saves > 0);
            return page.evaluate(() => {
                const state = window.Alpine.$data(document.querySelector('main'));
                return { saves: state.saves, needed: state.autoSaveInitialContent, data: Object.fromEntries(state.detailedProposalFormData()),
                    objectives: state.specificObjectives, groups: state.specificMethodGroups, outputs: state.expectedOutputs };
            });
        };
        const legacy = await run(saved);
        assert.equal(legacy.saves, 1);
        assert.equal(legacy.needed, true);
        assert.equal(legacy.data['specific_objectives[0][description]'], 'Compare reporting methods.');
        assert.equal(legacy.data['specific_method_objectives[0][heading]'], 'Comparison');
        assert.equal(legacy.data['specific_method_objectives[0][methods][0][description]'], 'Review the reports.');
        assert.equal(legacy.data['expected_outputs[publications][0][description]'], 'Two (2) reports Monitoring report');
        assert.equal(legacy.data['expected_outputs[products][0][description]'], 'Monitoring toolkit');
        assert.equal(legacy.data.rationale, saved.rationale);
        assert.equal(legacy.data.references, saved.references);
        assert.equal(legacy.data['methodology[research_design]'], saved.methodology.research_design);
        assert.equal(legacy.data['methodology[data_analysis]'], saved.methodology.data_analysis);
        const canonical = await run({ ...saved, specific_objectives: legacy.objectives, specific_method_objectives: legacy.groups, expected_outputs: legacy.outputs });
        assert.equal(canonical.saves, 0, 'Client row IDs must not cause another canonical save');
        assert.equal(canonical.needed, false);
        const empty = await run({});
        assert.equal(empty.saves, 0, 'Injected blank rows must not save a brand-new document');
        assert.equal(empty.needed, false);
        const incomplete = await run({ general_objective: 'Only a general objective.', specific_objectives: [],
            specific_method_objectives: [{ heading: 'Methods to complete later.', methods: [] }] });
        assert.equal(incomplete.saves, 0, 'Placeholder rows for an incomplete saved section must not request repeated saves');
        const legacyMethods = await run({ methodology: { specific_methods: '<p>A. Read reports</p><p>1. Compare the findings.</p>' } });
        assert.equal(legacyMethods.saves, 1);
        assert.equal(legacyMethods.data['specific_method_objectives[0][heading]'], 'Read reports');
        assert.equal(legacyMethods.data['specific_method_objectives[0][methods][0][description]'], 'Compare the findings.');
        assert.deepEqual(errors, []);
        await page.close();
    } finally { await browser.close(); }
});
