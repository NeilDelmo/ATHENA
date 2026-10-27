import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('proposalDraftWorkPlan',");
const controller = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const view = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/work-plan/edit.blade.php', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const entries = view.slice(view.indexOf('<template x-for="(entry, index) in entries"'), view.indexOf('\n            </section>\n\n            <x-proposal-signatory-summary'));

test('large Work Plans render only the open schedule while preserving saved months and validation', async (context) => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const errors = [];
    try {
        const page = await browser.newPage();
        page.on('pageerror', error => errors.push(error.message));
        const config = {
            objectivesLinked: true, maxEntries: 20, durationMonths: 120, updateUrl: '/save', csrfToken: 'test',
            initialEntries: Array.from({ length: 20 }, (_, index) => ({
                objective: `Objective ${index + 1}`, expected_output: 'Output', activity: 'Activity',
                months: index === 0 ? [4, 1, 4, 121, 0] : [index === 1 ? 2 : index + 4],
            })),
        };
        await page.setContent(`<form x-data='proposalDraftWorkPlan(${JSON.stringify(config)})' x-ref="form" method="POST" data-paper-project-details-complete="true">
            <input type="hidden" name="_method" value="PUT"><input type="hidden" name="document_version" value="0"><input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
            ${entries}</form>`);
        await page.addScriptTag({ content: `
            function proposalPaperPreviewWorkspace() {
                return { initializeProposalPaperPreview() {}, destroyProposalPaperPreview() {}, markProposalPreviewStale() {} };
            }
            window.savedBodies = [];
            window.fetch = async (url, options) => {
                window.savedBodies.push([...options.body]);
                return { ok: true, status: 200, json: async () => ({ document_version: window.savedBodies.length, saved_as_draft: false }) };
            };
            document.addEventListener('alpine:init', () => {
                const register = Alpine.data.bind(Alpine);
                Alpine.data = (name, factory) => register(name, config => {
                    const state = factory(config);
                    const owner = state.monthOwnerIndex;
                    state.monthOwnerIndex = function (...args) { window.ownerCalls++; return owner.apply(this, args); };
                    const initialize = state.init;
                    state.init = function () {
                        window.workPlanState = this;
                        window.ownerCalls = 0;
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
        const initial = await page.evaluate(() => ({
            milliseconds: window.scheduleInitialized - window.scheduleStarted,
            ownerCalls: window.ownerCalls,
            styledCheckboxes: document.querySelectorAll('.work-plan-writing-months input[type="checkbox"]').length,
            elements: document.querySelectorAll('*').length,
        }));
        context.diagnostic(`20-objective / 120-month initialization: ${JSON.stringify(initial)}`);
        assert.equal(initial.styledCheckboxes, 120, 'Collapsed schedules must not initialize another 2,280 styled month controls');
        assert.ok(initial.ownerCalls < 3200, 'Collapsed schedules must avoid repeating ownership work across styled bindings');
        assert.equal(await page.locator('textarea').count(), 60, 'Collapsed objective fields stay available to browser validation');

        const months = index => page.evaluate(row => new FormData(document.querySelector('form')).getAll(`entries[${row}][months][]`), index);
        const payload = () => page.evaluate(() => [...new FormData(document.querySelector('form'))]);
        const initialPayload = await payload();
        assert.deepEqual(await months(0), ['1', '4'], 'Selected months submit once in numeric grid order');
        assert.deepEqual(await months(19), ['23']);

        await page.evaluate(async () => {
            window.workPlanState.entries[0].months.push(121, -1);
            await Alpine.nextTick();
            window.workPlanState.toggleEntry(window.workPlanState.entries[0]);
            await Alpine.nextTick();
        });
        assert.deepEqual(await payload(), initialPayload, 'Opening a schedule preserves exactly the same FormData, including filtered invalid months');
        const first = page.locator('[data-work-plan-entry-id="1"]');
        assert.equal(await first.locator('input[type="checkbox"][value="2"]').isDisabled(), true);
        await first.locator('input[type="checkbox"][value="4"]').uncheck({ force: true });
        await page.evaluate(async () => { window.workPlanState.toggleEntry(window.workPlanState.entries[1]); await Alpine.nextTick(); });
        const second = page.locator('[data-work-plan-entry-id="2"]');
        assert.equal(await second.locator('input[type="checkbox"][value="4"]').isEnabled(), true);
        await second.locator('input[type="checkbox"][value="4"]').check({ force: true });
        assert.deepEqual(await months(0), ['1']);
        assert.deepEqual(await months(1), ['2', '4']);
        const assignedPayload = await payload();
        await page.evaluate(async () => { window.workPlanState.toggleEntry(window.workPlanState.entries[1]); await Alpine.nextTick(); });
        assert.equal(await page.locator('.work-plan-writing-months input[type="checkbox"]').count(), 0);
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
        assert.deepEqual(await months(0), ['2', '4'], 'Removing a row reindexes collapsed month inputs');
        assert.equal(await page.locator('[name="entries[0][objective]"]').inputValue(), 'Objective 2');

        assert.equal(await page.evaluate(async () => {
            window.workPlanState.entries.at(-1).months = [];
            const valid = window.workPlanState.validateForm();
            await Alpine.nextTick();
            return valid;
        }), false);
        await page.waitForFunction(() => document.activeElement?.name === 'entries[18][months][]');
        assert.equal(await page.locator('[data-work-plan-schedule="20"]').getAttribute('data-work-plan-invalid'), 'true');
        assert.equal(await page.locator('.work-plan-writing-months input[type="checkbox"]').count(), 120);
        assert.equal(await page.evaluate(async () => {
            window.workPlanState.entries.at(-1).months = [2];
            const valid = window.workPlanState.validateForm();
            await Alpine.nextTick();
            return valid;
        }), false);
        await page.waitForFunction(() => document.activeElement?.name === 'entries[18][months][]' && document.activeElement.value === '2');
        assert.equal(await page.evaluate(() => window.workPlanState.monthConflictIndexes.includes(18)), true);
        assert.equal(await page.evaluate(async () => {
            window.workPlanState.entries.at(-1).months = [120];
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
