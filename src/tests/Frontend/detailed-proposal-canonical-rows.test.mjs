import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const app = readFileSync(new URL('../../resources/js/detailed-proposal-editor.js', import.meta.url), 'utf8').replace(/^ {4}/gm, '');
const start = app.indexOf("Alpine.data('proposalDraftDetailedProposal'");
const controller = app.slice(start, app.indexOf('\n}));', start) + '\n}));'.length);
const citationHelpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replace(/^export /gm, '');

test('responsibility form shares percentages automatically, keeps fixed inputs, and submits their allocation mode', async () => {
    const section = execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        preg_match('/<section data-revision-section="section-responsibilities".*?<\/section>/s', file_get_contents(resource_path('views/faculty/proposal-drafts/detailed-proposal/edit.blade.php')), $section);
        echo Illuminate\Support\Facades\Blade::render($section[0], ['sectionHeadings' => ['responsibilities' => 'XIII. Duties and Responsibilities']]);
    `], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1280]) for (const dark of [false, true]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${css}[x-cloak]{display:none!important}</style>
                <body class="bg-gray-50 dark:bg-slate-950"><main data-detailed-proposal-workspace class="mx-auto max-w-4xl p-4"><form x-data="responsibilityLayout">${section}</form></main></body></html>`);
            await page.addScriptTag({ content: `
                const proposalSourceWorkspace = () => ({}), proposalPreviewWorkspace = () => ({}), detailedProposalPreviewWorkspace = () => ({});
                const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
                ${controller}
                document.addEventListener('alpine:init', () => window.Alpine.data('responsibilityLayout', () => {
                    const state = window.proposalFactory({});
                    state.init = function () { this.responsibilities = [this.newResponsibility({ name: 'Project leader', percentage: 100, percentage_fixed: '0' })]; };
                    state.triggerDetailedProposalAutoSave = () => {};
                    return state;
                }));
            ` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            const fields = page.locator('input[name$="[percentage]"]');
            const shares = () => fields.evaluateAll(inputs => inputs.map(input => input.value));
            await expect(fields).toHaveCount(1);
            await expect(fields.first()).toHaveValue('100');
            await page.getByRole('button', { name: 'Add member', exact: true }).click();
            await page.getByRole('button', { name: 'Add member', exact: true }).click();
            await expect.poll(shares).toEqual(['34', '33', '33']);
            await fields.first().fill('50');
            await expect.poll(shares).toEqual(['50', '25', '25']);
            await fields.nth(2).fill('30');
            await expect.poll(shares).toEqual(['50', '20', '30']);
            const formData = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
            assert.deepEqual([0, 1, 2].map(index => formData[`responsibilities[${index}][percentage_fixed]`]), ['1', '0', '1']);
            assert.deepEqual([0, 1, 2].map(index => formData[`responsibilities[${index}][percentage]`]), ['50', '20', '30']);
            await fields.nth(2).fill('60');
            await expect(fields.first()).toHaveValue('50');
            await expect(page.getByRole('status')).toContainText('Fixed percentages exceed 100%');
            await expect(page.getByText('Fixed share · Up to 49% available', { exact: true })).toBeVisible();
            assert.equal(await fields.nth(2).evaluate(input => input.checkValidity()), false);
            await fields.nth(2).fill('30');
            await fields.nth(1).fill('10');
            await expect(page.getByRole('status')).toContainText('Total 90%');
            await page.getByRole('button', { name: 'Use automatic percentage for member 2', exact: true }).click();
            await expect.poll(shares).toEqual(['50', '20', '30']);
            await expect(page.getByRole('status')).toHaveText('Total: 100%');
            await page.getByRole('button', { name: 'Use automatic percentage for Project leader', exact: true }).click();
            await expect.poll(shares).toEqual(['35', '35', '30']);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            assert.deepEqual(errors, []);
            if (process.env.ATHENA_EDITOR_VISUAL_DIR) await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `responsibility-shares-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            await page.close();
        }
    } finally { await browser.close(); }
});

test('expected outputs keep all eight categories visible with compact add and remove controls', async () => {
    execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ProposalRequiredFieldsTest.php', '--filter=Detailed Proposal'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_EXPORT_REQUIRED_FIELDS: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
    });
    const fixture = readFileSync(new URL('../../storage/framework/testing/proposal-required-detailed-proposal.html', import.meta.url), 'utf8');
    const section = fixture.match(/<section data-revision-section="section-expected-outputs"[\s\S]*?(?=\s*<section data-revision-section="section-literature")/)[0];
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1280]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1100 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css}</style></head><body class="bg-gray-50 dark:bg-slate-950"><main class="mx-auto max-w-4xl p-4 sm:p-6"><form x-data="outputLayout">${section}</form></main></body></html>`);
                await page.addScriptTag({ content: `
                    const proposalSourceWorkspace = () => ({}), proposalPreviewWorkspace = () => ({}), detailedProposalPreviewWorkspace = () => ({});
                    const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
                    ${controller}
                    document.addEventListener('alpine:init', () => window.Alpine.data('outputLayout', () => {
                        const state = window.proposalFactory({});
                        state.init = () => {};
                        state.expectedOutputs = Object.fromEntries(['publication','patent','product','people_service','place_partnership','policy','social_impact','economic_impact'].map(key => [key, []]));
                        state.expectedOutputs.policy = [{ id: 'policy-saved', description: 'A policy brief for municipal reporting.' }];
                        state.expectedOutputs.social_impact = [{ id: 'impact-saved', description: 'More consistent community reporting.' }];
                        state.saves = 0;
                        state.triggerDetailedProposalAutoSave = function () { this.saves++; };
                        return state;
                    }));
                ` });
                await page.addScriptTag({ path: new URL('../../node_modules/alpinejs/dist/cdn.js', import.meta.url).pathname.replace(/^\/(.:)/, '$1') });
                await page.locator('[data-output-category="policy"] textarea').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-output-category]:visible').count(), 8);
                for (const label of ['Publication', 'Patent', 'Product', 'People Service', 'Place & Partnership', 'Policy', 'Social Impact', 'Economic Impact']) {
                    assert.ok(await page.locator('[data-output-category]').filter({ hasText: label }).isVisible(), `${label} must remain visible`);
                }
                assert.equal((await page.locator('[data-output-category] button:visible').allTextContents()).join('').trim(), '');
                assert.equal(await page.locator('[data-output-category-picker]').count(), 0);
                assert.equal(await page.locator('[data-output-category-icon]').count(), 8);
                const panels = await page.locator('[data-output-category]').evaluateAll(elements => elements.map(element => ({
                    border: getComputedStyle(element).borderTopWidth,
                    headerHeight: element.querySelector('header').getBoundingClientRect().height,
                })));
                assert.ok(panels.every(panel => panel.border === '1px' && panel.headerHeight >= 40), 'Each category needs a contained header');
                const fields = await page.locator('[data-output-category] textarea:visible').evaluateAll(elements => elements.map(element => {
                    const rect = element.getBoundingClientRect();
                    return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
                }));
                assert.equal(fields[0].width, fields[1].width);
                assert.ok(fields.every(field => field.height <= 76), 'Output fields should start compact');
                if (width >= 640) {
                    assert.notEqual(fields[1].x, fields[0].x, 'Desktop categories should use two columns');
                    assert.ok(await page.locator('#expected-outputs').evaluate(element => element.getBoundingClientRect().height) < 400, 'Empty categories should stay compact');
                }
                await page.getByRole('button', { name: 'Add Policy output', exact: true }).click();
                await page.locator('[data-output-category="policy"] textarea').nth(1).waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-output-category="policy"] textarea').count(), 2);
                await page.getByRole('button', { name: 'Add Product output', exact: true }).click();
                await page.locator('[data-output-category="product"] textarea').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-output-category="product"] textarea:visible').count(), 1);
                await page.getByRole('button', { name: 'Remove Product output 1', exact: true }).click();
                await page.locator('[data-output-category="product"] textarea').waitFor({ state: 'detached' });
                await page.getByRole('button', { name: 'Add Product output', exact: true }).waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-output-category="product"]:visible').count(), 1);
                assert.ok(await page.getByRole('button', { name: 'Add Product output', exact: true }).isVisible());
                await page.getByRole('button', { name: 'Remove Policy output 2', exact: true }).click();
                await page.locator('[data-output-category="policy"] textarea').nth(1).waitFor({ state: 'detached' });
                assert.equal(await page.locator('[data-output-category="policy"] textarea').inputValue(), 'A policy brief for municipal reporting.');
                assert.equal(await page.evaluate(() => window.Alpine.$data(document.querySelector('form')).saves), 4);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally { await browser.close(); }
});

test('specific method groups use compact panels and preserve headings and numbered methods', async () => {
    execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ProposalRequiredFieldsTest.php', '--filter=Detailed Proposal'], {
        encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
        env: { ...process.env, ATHENA_EXPORT_REQUIRED_FIELDS: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
    });
    const fixture = readFileSync(new URL('../../storage/framework/testing/proposal-required-detailed-proposal.html', import.meta.url), 'utf8');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1280]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                const markup = await page.evaluate(source => new DOMParser().parseFromString(source, 'text/html').querySelector('[data-specific-methods-editor]')?.outerHTML || '', fixture);
                assert.ok(markup, 'The specific methods editor must have its own panel');
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css}</style></head><body class="bg-gray-50 dark:bg-slate-950"><main class="mx-auto max-w-4xl p-4 sm:p-6"><form x-data="methodLayout">${markup}</form></main></body></html>`);
                await page.addScriptTag({ content: `
                    const proposalSourceWorkspace = () => ({}), proposalPreviewWorkspace = () => ({}), detailedProposalPreviewWorkspace = () => ({});
                    const Alpine = { data: (_, factory) => { window.proposalFactory = factory; } };
                    ${controller}
                    document.addEventListener('alpine:init', () => window.Alpine.data('methodLayout', () => {
                        const state = window.proposalFactory({});
                        state.init = () => {};
                        state.specificMethodGroups = [{id:'saved-group',heading:'Compare monitoring methods',methods:[{id:'saved-method',description:'Review the reporting records.'}]}];
                        state.saves = 0;
                        state.triggerDetailedProposalAutoSave = function () { this.saves++; };
                        return state;
                    }));
                ` });
                await page.addScriptTag({ path: new URL('../../node_modules/alpinejs/dist/cdn.js', import.meta.url).pathname.replace(/^\/(.:)/, '$1') });
                const panel = page.locator('[data-specific-method-group]');
                await panel.waitFor({ state: 'visible' });
                assert.equal((await page.locator('button:visible').allTextContents()).join('').trim(), '');
                assert.equal(await panel.evaluate(element => getComputedStyle(element).borderTopWidth), '1px');
                assert.ok(await page.locator('[data-specific-methods-editor]').evaluate(element => element.getBoundingClientRect().height) < 300);
                assert.ok((await panel.locator('textarea').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().height))).every(height => height <= 76));
                await page.getByRole('button', { name: 'Add method to group A', exact: true }).click();
                await page.getByRole('textbox', { name: 'Method 2 for group A', exact: true }).waitFor({ state: 'visible' });
                await page.getByRole('textbox', { name: 'Method 2 for group A', exact: true }).fill('Compare observations.');
                const data = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
                assert.equal(data['specific_method_objectives[0][heading]'], 'Compare monitoring methods');
                assert.equal(data['specific_method_objectives[0][methods][1][description]'], 'Compare observations.');
                await page.getByRole('button', { name: 'Remove method 2 from group A', exact: true }).click();
                await page.getByRole('textbox', { name: 'Method 2 for group A', exact: true }).waitFor({ state: 'detached' });
                await page.getByRole('button', { name: 'Add method group', exact: true }).click();
                await page.getByRole('textbox', { name: 'Method heading B', exact: true }).waitFor({ state: 'visible' });
                assert.equal(await panel.count(), 2);
                if (width >= 640) {
                    const boxes = await panel.evaluateAll(elements => elements.map(element => ({x:element.getBoundingClientRect().x,y:element.getBoundingClientRect().y})));
                    assert.equal(boxes[0].y, boxes[1].y);
                    assert.ok(boxes[1].x > boxes[0].x);
                }
                await page.getByRole('button', { name: 'Remove method group B', exact: true }).click();
                await page.getByRole('textbox', { name: 'Method heading B', exact: true }).waitFor({ state: 'detached' });
                assert.equal(await page.getByRole('textbox', { name: 'Method heading A', exact: true }).inputValue(), 'Compare monitoring methods');
                assert.equal(await page.getByRole('textbox', { name: 'Method 1 for group A', exact: true }).inputValue(), 'Review the reporting records.');
                assert.equal(await page.evaluate(() => window.Alpine.$data(document.querySelector('form')).saves), 4);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally { await browser.close(); }
});

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
            ${citationHelpers}
            ${controller}
            document.addEventListener('alpine:init', () => window.Alpine.data('canonicalRows', () => {
                const state = window.proposalFactory({ initialData: window.fixture, expectedOutputKeys: ['publications', 'products'], methodologyKeys: ['research_design', 'specific_methods', 'data_analysis'], figureSections: { introduction: 'Opening', related_literature: 'RRL' } });
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
            introduction: '<p><strong>Opening evidence</strong><span data-proposal-citation="12"> [1]</span></p>',
            related_literature: '<p>Review the existing studies.</p>',
            literature_citations: [{ id: 'opening-citation', source_link_id: 12, literature_source_id: 3, field: 'introduction', selected_text: 'Opening evidence' }],
            methodology_images: [{ id: 'opening-figure', section: 'introduction', caption: 'Community reporting framework' }],
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
                    <input type="hidden" name="introduction" x-model="introduction"><textarea name="related_literature" x-model="relatedLiterature"></textarea>
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
                    objectives: state.specificObjectives, groups: state.specificMethodGroups, outputs: state.expectedOutputs,
                    citations: state.literatureCitations, images: state.methodologyImages };
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
        assert.equal(legacy.data.introduction, '');
        assert.equal(legacy.data.related_literature, `${saved.introduction}\n\n${saved.related_literature}`);
        assert.equal(legacy.citations[0].field, 'related_literature');
        assert.equal(legacy.images[0].section, 'related_literature');
        assert.equal(legacy.data['methodology[research_design]'], saved.methodology.research_design);
        assert.equal(legacy.data['methodology[data_analysis]'], saved.methodology.data_analysis);
        const canonical = await run({ ...saved, introduction: '', related_literature: legacy.data.related_literature,
            literature_citations: legacy.citations, methodology_images: legacy.images,
            specific_objectives: legacy.objectives, specific_method_objectives: legacy.groups, expected_outputs: legacy.outputs });
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
