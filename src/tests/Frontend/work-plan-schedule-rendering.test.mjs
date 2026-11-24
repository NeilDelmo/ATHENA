import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('proposalDraftWorkPlan',");
const controller = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const view = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/work-plan/edit.blade.php', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const entries = view.slice(view.indexOf('<template x-for="(entry, index) in entries"'), view.indexOf('\n            </section>\n\n            <x-proposal-signatory-summary'));

async function mountWorkPlan(page, config, { projectDetailsComplete = true, status = 'In progress' } = {}) {
    await page.setContent(`<span data-work-plan-completion-status>${status}</span>
        <form x-data='proposalDraftWorkPlan(${JSON.stringify(config)})' x-ref="form" method="POST" data-paper-project-details-complete="${projectDetailsComplete}">
            <input type="hidden" name="_method" value="PUT"><input type="hidden" name="document_version" value="0"><input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
            ${entries}</form>`);
    await page.addScriptTag({ content: `
            function proposalPaperPreviewWorkspace() {
                return { initializeProposalPaperPreview() {}, destroyProposalPaperPreview() {}, markProposalPreviewStale() {} };
            }
            window.savedBodies = [];
            window.fetch = async (url, options) => {
                window.savedBodies.push([...options.body]);
                return { ok: true, status: 200, json: async () => ({ document_version: window.savedBodies.length, saved_as_draft: options.body.get('save_as_draft') === '1' }) };
            };
            document.addEventListener('alpine:init', () => {
                const register = Alpine.data.bind(Alpine);
                Alpine.data = (name, factory) => register(name, config => {
                    const state = factory(config);
                    const initialize = state.init;
                    state.init = function () {
                        window.workPlanState = this;
                        window.scheduleStarted = performance.now();
                        initialize.call(this);
                        this.$nextTick(() => { window.scheduleInitialized = performance.now(); });
                    };
                    return state;
                });
                ${controller}
            });
        ` });
    await page.addScriptTag({ content: readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8') });
    await page.waitForFunction(() => window.scheduleInitialized);
}

test('a new Work Plan completes when the final required field is autosaved', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await mountWorkPlan(page, {
            objectivesLinked: true, updateUrl: '/save', csrfToken: 'test',
            initialEntries: [{ objective: 'Collect data' }],
        }, { status: 'Not started' });
        const status = page.locator('[data-work-plan-completion-status]');
        await expect(status).toHaveText('Not started');

        await page.getByRole('textbox', { name: /Expected outputs/ }).fill('Dataset');
        await page.getByRole('textbox', { name: /Activities/ }).fill('Run interviews');
        await expect(status).toHaveText('In progress');

        await page.locator('[data-work-plan-month="5"]').click();
        await expect(status).toHaveText('Complete');
        const requests = await page.evaluate(() => window.savedBodies);
        assert.equal(requests.length, 2);
        assert.equal(new URLSearchParams(requests[0]).get('save_as_draft'), '1');
        const completed = new URLSearchParams(requests[1]);
        assert.equal(completed.get('document_version'), '1');
        assert.equal(completed.get('save_as_draft'), '0');
        assert.deepEqual(completed.getAll('entries[0][months][]'), ['1', '2', '3', '4', '5']);
    } finally {
        await browser.close();
    }
});

test('opening a filled saved draft rechecks completion without requiring an edit', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const config = {
            objectivesLinked: true, updateUrl: '/save', csrfToken: 'test', recheckCompletion: true,
            initialEntries: [{ objective: 'Collect data', expected_output: 'Dataset', activity: 'Run interviews', months: [1, 2, 3, 4, 5] }],
        };
        const page = await browser.newPage();
        await mountWorkPlan(page, config);
        await expect(page.locator('[data-work-plan-completion-status]')).toHaveText('Complete');
        const requests = await page.evaluate(() => window.savedBodies);
        assert.equal(requests.length, 1);
        const body = new URLSearchParams(requests[0]);
        assert.equal(body.get('save_as_draft'), '0');
        assert.deepEqual(body.getAll('entries[0][months][]'), ['1', '2', '3', '4', '5']);
        assert.equal(body.get('entries[0][expected_output]'), 'Dataset');
        assert.equal(body.get('entries[0][activity]'), 'Run interviews');

        const incompleteDetails = await browser.newPage();
        await mountWorkPlan(incompleteDetails, config, { projectDetailsComplete: false });
        await expect(incompleteDetails.locator('[data-work-plan-completion-status]')).toHaveText('In progress');
        assert.equal(await incompleteDetails.evaluate(() => window.workPlanState.autoSaveTimer), null);
    } finally {
        await browser.close();
    }
});

test('large Work Plans render only the open schedule while preserving saved months and validation', async (context) => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const errors = [];
    try {
        const page = await browser.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await mountWorkPlan(page, {
            objectivesLinked: true, maxEntries: 20, durationMonths: 120, updateUrl: '/save', csrfToken: 'test', recheckCompletion: true,
            initialEntries: Array.from({ length: 20 }, (_, index) => ({
                objective: `Objective ${index + 1}`, expected_output: 'Output', activity: 'Activity',
                months: index === 0 ? [4, 1, 4, 121, 0] : [index === 1 ? 2 : index + 4],
            })),
        });
        assert.equal(await page.evaluate(() => window.workPlanState.autoSaveTimer), null, 'A saved range with gaps still needs editing');
        const initial = await page.evaluate(() => ({
            milliseconds: window.scheduleInitialized - window.scheduleStarted,
            monthButtons: document.querySelectorAll('[data-work-plan-month]').length,
            elements: document.querySelectorAll('*').length,
        }));
        context.diagnostic(`20-objective / 120-month initialization: ${JSON.stringify(initial)}`);
        assert.equal(initial.monthButtons, 120, 'Collapsed schedules must not initialize another 2,280 styled month controls');
        assert.equal(await page.locator('input[type="hidden"][name$="[months][]"]').count(), 21, 'Only scheduled months need form inputs');
        assert.equal(await page.locator('textarea').count(), 40, 'Every row keeps both editable fields available to browser validation');
        assert.equal(await page.locator('input[type="hidden"][name$="[objective]"]').count(), 20);

        const months = index => page.evaluate(row => new FormData(document.querySelector('form')).getAll(`entries[${row}][months][]`), index);
        const payload = () => page.evaluate(() => [...new FormData(document.querySelector('form'))]);
        const initialPayload = await payload();
        assert.deepEqual(await months(0), ['1', '4'], 'Selected months submit once in numeric grid order');
        assert.deepEqual(await months(19), ['23']);

        await page.evaluate(async () => {
            window.workPlanState.toggleEntry(window.workPlanState.entries[0]);
            await Alpine.nextTick();
        });
        assert.deepEqual(await payload(), initialPayload, 'Opening a schedule preserves exactly the same FormData');
        const first = page.locator('[data-work-plan-entry-id="1"]');
        await expect(first.getByRole('button', { name: 'Fill gaps' })).toBeVisible();
        assert.equal(await page.evaluate(() => window.workPlanState.isComplete()), false);
        await first.getByRole('button', { name: 'Fill gaps' }).click();
        assert.deepEqual(await months(0), ['1', '2', '3', '4']);
        await page.evaluate(async () => { window.workPlanState.toggleEntry(window.workPlanState.entries[1]); await Alpine.nextTick(); });
        const second = page.locator('[data-work-plan-entry-id="2"]');
        await expect(second.locator('[data-work-plan-month="1"]')).toBeDisabled();
        await second.locator('[data-work-plan-month="4"]').click();
        assert.deepEqual(await months(0), ['1', '2', '3', '4']);
        assert.deepEqual(await months(1), ['2', '3', '4']);
        const assignedPayload = await payload();
        await page.evaluate(async () => { window.workPlanState.toggleEntry(window.workPlanState.entries[1]); await Alpine.nextTick(); });
        assert.equal(await page.locator('[data-work-plan-month]').count(), 0);
        assert.deepEqual(await payload(), assignedPayload, 'Collapsing the last open schedule retains every selected month');
        await page.evaluate(() => window.workPlanState.saveWorkPlanNow());
        const saved = await page.evaluate(() => window.savedBodies.at(-1));
        assert.deepEqual(saved.filter(([name]) => name.includes('[months]')), assignedPayload.filter(([name]) => name.includes('[months]')));
        assert.equal(saved.some(([name, value]) => name === 'entries[19][activity]' && value === 'Activity'), true);

        await page.evaluate(async () => {
            window.workPlanState.objectivesLinked = false;
            window.workPlanState.removeEntry(0);
            await Alpine.nextTick();
        });
        assert.deepEqual(await months(0), ['2', '3', '4'], 'Removing a row reindexes collapsed month inputs');
        assert.equal(await page.locator('[name="entries[0][objective]"]').inputValue(), 'Objective 2');

        const schedule = page.locator('[data-work-plan-schedule="20"]');
        await page.evaluate(() => window.workPlanState.toggleEntry(window.workPlanState.entries.at(-1)));
        await schedule.getByRole('button', { name: 'Clear schedule' }).click();
        assert.deepEqual(await months(18), []);
        await expect(schedule.getByRole('combobox', { name: 'Start month' })).toBeFocused();
        assert.equal(await page.evaluate(() => window.workPlanState.validateForm()), false);
        assert.equal(await schedule.getAttribute('data-work-plan-invalid'), 'true');
        assert.equal(await page.locator('[data-work-plan-month]').count(), 120);
        await schedule.locator('[data-work-plan-month="5"]').click();
        assert.deepEqual(await months(18), ['1', '2', '3', '4', '5']);
        await expect(schedule.getByRole('combobox', { name: 'Start month' })).toHaveValue('1');
        await expect(schedule.getByRole('combobox', { name: 'End month' })).toHaveValue('5');
        await expect(schedule).not.toHaveAttribute('data-work-plan-invalid');
        assert.equal(await page.evaluate(() => window.workPlanState.validateForm()), true);
        await schedule.locator('[data-work-plan-month="10"]').click();
        assert.deepEqual(await months(18), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);
        await expect(schedule.getByRole('combobox', { name: 'End month' })).toHaveValue('10');
        await schedule.locator('[data-work-plan-month="3"]').press('Space');
        assert.deepEqual(await months(18), ['1', '2', '3']);
        await schedule.getByRole('combobox', { name: 'Start month' }).selectOption('5');
        await expect(schedule.getByRole('combobox', { name: 'End month' })).toHaveValue('5');
        assert.deepEqual(await months(18), ['5']);
        await schedule.getByRole('button', { name: 'Clear schedule' }).click();
        await schedule.getByRole('combobox', { name: 'End month' }).selectOption('6');
        await expect(schedule.getByRole('combobox', { name: 'Start month' })).toHaveValue('1');
        assert.deepEqual(await months(18), ['1', '2', '3', '4', '5', '6']);
        assert.equal(await page.evaluate(async () => {
            window.workPlanState.entries[0].expectedOutput = '';
            await Alpine.nextTick();
            const valid = window.workPlanState.validateForm();
            await Alpine.nextTick();
            return valid;
        }), false);
        await page.waitForFunction(() => document.activeElement?.name === 'entries[0][expected_output]');
        assert.equal(await page.locator('[name="entries[0][expected_output]"]').getAttribute('aria-invalid'), 'true');
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
