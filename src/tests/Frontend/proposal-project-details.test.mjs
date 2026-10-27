import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const app = read('resources/js/app.js');
const start = app.indexOf("Alpine.data('proposalDraftProjectDetails',");
const controller = app.slice(start, app.indexOf("Alpine.data('datePicker',", start));
const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
const calendar = read('resources/js/proposal-draft-dates.js').replaceAll('export ', '');
const autosave = read('resources/js/proposal-paper-autosave.js').replaceAll('export ', '');
const view = read('resources/views/faculty/proposal-drafts/show.blade.php');
const heading = view.slice(view.indexOf('<div class="flex flex-wrap items-center gap-2">'), view.indexOf('\n                            <p class="mt-2 text-sm leading-6 text-gray-500'));
const renderedHeading = execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    echo \\Illuminate\\Support\\Facades\\Blade::render(stream_get_contents(STDIN), ['projectDetailsComplete' => false]);
`], { input: heading, encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });

const guidance = app.slice(app.indexOf("Alpine.data('proposalDateGuidance',"), app.indexOf("Alpine.data('datePicker',"));
const picker = app.slice(app.indexOf("Alpine.data('datePicker',"), app.indexOf("Alpine.data('dateTimePicker',"));

test('proposal date advice allows boundary dates and ignores missing or invalid dates', () => {
    let factory;
    new Function('Alpine', `${dateHelpers}\n${guidance}`)({ data: (_name, value) => { factory = value; } });
    const state = factory({ start: '2026-11-01', end: '2027-03-31', label: 'planned start' });
    assert.match(state.dateWarning('2026-10-31'), /before.*November 1, 2026.*You can keep this date/);
    assert.match(state.dateWarning('2027-04-01'), /after.*March 31, 2027.*You can keep this date/);
    for (const date of ['', 'invalid', '2026-11-01', '2027-01-15', '2027-03-31']) assert.equal(state.dateWarning(date), '');
    assert.equal(factory({}).dateWarning('2026-10-31'), '');
    assert.equal(factory({ start: 'invalid', end: null }).dateWarning('2026-10-31'), '');
    assert.equal(factory({ start: '2026-11-01' }).dateWarning('2027-04-01'), '');
    assert.equal(factory({ end: '2027-03-31' }).dateWarning('2026-10-31'), '');
});

test('proposal date pickers mark implementation dates and update advice without blocking choices', async () => {
    const fields = execFileSync('php', ['-r', `
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        $app['view']->share('errors', new \\Illuminate\\Support\\ViewErrorBag);
        echo \\Illuminate\\Support\\Facades\\Blade::render(stream_get_contents(STDIN), [
            'researchCall' => new \\App\\Models\\ResearchCall([
                'status' => 'open', 'implementation_start_date' => '2026-11-01', 'implementation_end_date' => '2027-03-31',
            ]),
        ]);
    `], { input: '<x-proposal-planned-date field="start" :research-call="$researchCall" minimum-date="2026-10-26" /><x-proposal-planned-date field="end" :research-call="$researchCall" minimum-date="2026-10-26" />', encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ locale: 'en-US' });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<style>[x-cloak]{display:none!important}</style><main x-data="proposalDraftProjectDetails({ initialDuration: 6, initialStart: '2026-10-27', initialEnd: '2027-04-27' })">${fields}</main>`);
        await page.addScriptTag({ content: `${dateHelpers}\n${calendar}\ndocument.addEventListener('alpine:init', () => { ${controller}\n${picker} });` });
        await page.addScriptTag({ path: 'node_modules/alpinejs/dist/cdn.js' });
        const startWarning = page.locator('#planned_start-date-warning');
        const endWarning = page.locator('#planned_end-date-warning');
        await expect(startWarning).toContainText('before the scheduled implementation start');
        await expect(endWarning).toContainText('after the scheduled implementation end');
        await expect(page.locator('#planned_start')).toHaveAttribute('aria-describedby', 'planned_start-hint planned_start-date-warning');

        const startPicker = page.locator('[x-data^="datePicker("]').first();
        await page.locator('#planned_start').click();
        await startPicker.getByRole('button', { name: /October 28, 2026/ }).click();
        await expect(page.locator('[name="planned_start"]')).toHaveValue('2026-10-28');
        await expect(startWarning).toBeVisible();
        await page.locator('#planned_start').click();
        await startPicker.getByLabel('Calendar month').selectOption('10');
        const scheduledStart = startPicker.getByRole('button', { name: /November 1, 2026.*Implementation begins/ });
        await expect(scheduledStart).toBeEnabled();
        await scheduledStart.click();
        await expect(startWarning).toBeHidden();
        await expect(page.locator('[name="planned_end"]')).toHaveValue('2027-05-01');
        await expect(endWarning).toBeVisible();

        const endPicker = page.locator('[x-data^="datePicker("]').last();
        await page.locator('#planned_end').click();
        await endPicker.getByLabel('Calendar month').selectOption('2');
        await endPicker.getByRole('button', { name: /March 31, 2027.*Implementation ends/ }).click();
        await expect(page.locator('[name="planned_end"]')).toHaveValue('2027-03-31');
        await expect(endWarning).toBeHidden();
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});

test('project details completion updates after autosave without reopening the workspace', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent(`<main data-paper-editor x-data="proposalDraftProjectDetails({ initialDuration: '', initialStart: '', initialEnd: '', initialComplete: false, autoSave: true })">
            ${renderedHeading}
            <p data-proposal-autosave-status><span data-proposal-autosave-message></span><span data-proposal-autosave-indicator></span></p>
            <div data-proposal-stale-warning hidden>The saved version has changed</div>
            <form data-project-details-autosave-form action="/save" method="POST">
                <input name="draft_version" type="hidden" value="0">
                <input name="project_title" value="Coastal Study" required>
                <input name="duration_months" type="number" min="1" max="120" x-model.number="durationMonths" required>
                <input name="planned_start" type="date" x-model="plannedStart" required>
                <input name="planned_end" type="date" x-model="plannedEnd" required>
                <input name="project_leader" value="Project Leader" required>
            </form>
        </main>`);
        await page.addScriptTag({ content: `${dateHelpers}\n${calendar}\n${autosave}\nwindow.saveCount = 0; window.staleSave = false;
            window.fetch = async () => {
                if (window.staleSave) return { ok: false, status: 422, json: async () => ({ errors: { draft_version: ['A teammate saved newer project details.'] } }) };
                window.saveCount++;
                return { ok: true, status: 200, json: async () => ({ draft_version: window.saveCount, project_details_complete: true }) };
            };` });
        await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => { ${controller} });` });
        await page.addScriptTag({ path: 'node_modules/alpinejs/dist/cdn.js' });
        const badge = page.locator('#project-details-heading + span');
        await expect(badge).toHaveText('Incomplete');

        await page.locator('[name="duration_months"]').fill('8');
        await page.locator('[name="planned_start"]').fill('2026-10-14');
        await expect(page.locator('[name="planned_end"]')).toHaveValue('2027-06-14');
        await expect(page.locator('[data-proposal-autosave-message]')).toHaveText('Saved just now.');
        await expect(badge).toHaveText('Complete');
        assert.equal(await page.locator('[name="draft_version"]').inputValue(), '1');
        assert.equal(await badge.evaluate((element) => element.classList.contains('bg-red-50')), false);

        await page.locator('[name="project_title"]').fill('');
        await expect(badge).toHaveText('Incomplete');
        assert.equal(await page.evaluate(() => window.saveCount), 1, 'incomplete details must not be saved');
        await page.locator('[name="project_title"]').fill('Coastal Study');
        await expect(badge).toHaveText('Complete');
        await expect(page.locator('[data-proposal-autosave-message]')).toHaveText('All changes saved.');
        assert.equal(await page.evaluate(() => window.saveCount), 1, 'restoring saved details needs no new save');
        await page.locator('[name="project_title"]').fill('Updated Coastal Study');
        await expect(page.locator('[name="draft_version"]')).toHaveValue('2');
        await expect(badge).toHaveText('Complete');

        await page.evaluate(() => { window.staleSave = true; });
        await page.locator('[name="project_title"]').fill('Older unsaved work');
        await expect(page.locator('[data-proposal-stale-warning]')).toBeVisible();
        assert.equal(await page.locator('[name="project_title"]').inputValue(), 'Older unsaved work');
        assert.equal(await page.locator('[name="draft_version"]').inputValue(), '2');
        assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('main')).autoSaveBlocked), true);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
