import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('proposalDraftWorkPlan',");
const controller = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const preview = readFileSync(new URL('../../resources/js/proposal-preview-workspace.js', import.meta.url), 'utf8').replaceAll('export ', '');
const workspace = readFileSync(new URL('../../resources/js/proposal-paper-workspace.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
const previewView = readFileSync(new URL('../../resources/views/components/proposal-paper-preview.blade.php', import.meta.url), 'utf8')
    .replace(/^@props\([^\n]+\)\r?\n/, '')
    .replaceAll('{{ $panelId }}', 'work-plan-preview-panel')
    .replaceAll('{{ $previewLabel }}', 'Work Plan preview')
    .replaceAll('{{ $frameTitle }}', 'Attachment A Work Plan preview');
const toolbar = execFileSync('php', ['-r', String.raw`
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo view('components.work-plan-writing-toolbar')->render();
`], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
const editView = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/work-plan/edit.blade.php', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const completionBadge = editView.match(/<span data-work-plan-completion-status[^\n]+<\/span>/)[0]
    .replace(/\{\{.*?\}\}/, 'In progress');
const editObjectivesLink = editView.match(/<a [^\n]+>Edit objectives<\/a>/)[0]
    .replace(/\{\{.*?\}\}/, '/detailed-proposal');
const entryView = editView.slice(editView.indexOf('<template x-for="(entry, index) in entries"'), editView.indexOf('\n            </section>\n\n            <x-proposal-signatory-summary'));

test('Work Plan keeps the schedule instructions and proposal outputs compact', async () => {
    const scheduleHeader = execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        preg_match('/<section data-revision-section="section-schedule".*?(?=@if \(\$linkedObjectives)/s', file_get_contents(resource_path('views/faculty/proposal-drafts/work-plan/edit.blade.php')), $section);
        echo Illuminate\Support\Facades\Blade::render($section[0].'</section>', [
            'proposalDraft' => 42, 'proposalOutputs' => ['publication' => [['description' => 'One journal article']]],
        ]);
    `], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 960]) {
            const page = await browser.newPage({ viewport: { width, height: 600 } });
            await page.setContent(`<style>${css}</style><main class="mx-auto max-w-4xl p-4">${scheduleHeader}</main>`);
            const schedule = page.getByRole('region', { name: 'Objectives and Gantt schedule' });
            const heading = schedule.getByRole('heading', { name: 'Objectives and Gantt schedule' });
            const edit = schedule.getByRole('link', { name: 'Edit objectives in Detailed Proposal' });
            await expect(edit).toHaveText('Edit objectives');
            assert.match(await edit.getAttribute('href'), /\/proposal-drafts\/42\/detailed-proposal$/);
            await expect(schedule.locator('p')).toHaveText(['For each objective, list its outputs and activities, then select the active months.']);
            if (width === 960) assert.ok(Math.abs((await heading.boundingBox()).y - (await edit.boundingBox()).y) <= 1);
            const outputs = schedule.locator('summary');
            const icon = outputs.locator('svg');
            const label = outputs.getByText('Proposal outputs', { exact: true });
            const example = schedule.getByText('One journal article');
            await expect(outputs).toHaveText('Proposal outputs');
            for (const control of [edit, outputs, schedule.locator('p')]) {
                assert.ok(await control.evaluate(element => parseFloat(getComputedStyle(element).fontSize)) >= 16);
            }
            const iconBox = await icon.boundingBox();
            const labelBox = await label.boundingBox();
            assert.ok(iconBox.x + iconBox.width < labelBox.x);
            assert.ok(Math.abs(iconBox.y + iconBox.height / 2 - labelBox.y - labelBox.height / 2) <= 1);
            await expect(icon).toHaveCSS('transform', 'none');
            await expect(example).toBeHidden();
            await outputs.click();
            await expect(example).toBeVisible();
            await expect(icon).toHaveCSS('transform', 'matrix(0, 1, -1, 0, 0, 0)');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('Work Plan previews and autosaves continuous ranges across years and overlapping objectives', async () => {
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const workspaceStyles = readFileSync(new URL('../../resources/css/proposal-paper-workspace.css', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 1024, 768, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const config = {
                    objectivesLinked: true, durationMonths: 24, updateUrl: '/save', previewUrl: '/preview', csrfToken: 'test',
                    initialEntries: [
                        { objective: 'Document the coastal baseline', expected_output: 'Baseline dataset', activity: 'Conduct surveys', months: [1, 2] },
                        { objective: 'Evaluate the monitoring workflow', expected_output: 'Evaluation report', activity: 'Analyze results', months: [13, 15] },
                    ],
                    proposalActivities: { 'Evaluate the monitoring workflow': 'Compare the recorded results' },
                };
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${workspaceStyles}</style><body data-app-shell>
                    <div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px">
                        <div style="position:sticky;top:0;height:120px">${completionBadge}</div>
                        <main class="work-plan-writing-workspace mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8" data-proposal-paper-workspace data-work-plan-workspace data-paper-project-details-complete="true" @focusin="focusWorkPlanEntry($event)" x-data='proposalDraftWorkPlan(${JSON.stringify(config)})'>
                            <div data-proposal-autosave-status><span data-proposal-autosave-message>Changes save automatically.</span><span data-proposal-autosave-indicator></span></div>
                            ${toolbar}
                            <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
                                <div class="proposal-edit-pane" :inert="previewFullscreen">
                                    <form x-ref="form" method="POST" action="/save" data-work-plan-autosave-form>
                                        <input type="hidden" name="_method" value="PUT"><input type="hidden" name="document_version" value="0"><input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
                                        <section data-revision-section="section-schedule">${editObjectivesLink}${entryView}</section>
                                    </form>
                                </div>${previewView}
                            </div>
                        </main>
                    </div></body></html>`);
                await page.addScriptTag({ content: `${preview}\n${workspace}
                    window.savedBodies=[]; window.previewBodies=[]; window.previewFailure=false;
                    window.fetch=async (url, options)=>{
                        const body=[...options.body.entries()];
                        if(url==='/save') {
                            window.savedBodies.push(body);
                            return {ok:true,status:200,json:async()=>({document_version:window.savedBodies.length,saved_as_draft:options.body.get('save_as_draft')==='1'})};
                        }
                        window.previewBodies.push(body);
                        await new Promise(resolve=>setTimeout(resolve,30));
                        if(window.previewFailure) return {ok:false,status:422,json:async()=>({errors:{entries:['Choose the required months.']}})};
                        const activity=options.body.get('entries[1][activity]');
                        return {ok:true,status:200,text:async()=>'<html><body style="width:1122px"><h1>MAJOR ACTIVITIES/WORK PLAN</h1><p>'+activity+'</p><div style="height:2000px"></div></body></html>'};
                    };
                    document.addEventListener('alpine:init',()=>{
                        const register=Alpine.data.bind(Alpine);
                        Alpine.data=(name,factory)=>register(name,config=>{
                            const state=factory(config); const initialize=state.init;
                            state.init=function(){window.workPlanState=this;initialize.call(this)};
                            return state;
                        });
                        ${controller}
                    });` });
                await page.addScriptTag({ content: readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8') });
                await page.locator('#activity-2').waitFor();
                await page.waitForFunction((desktop) => window.workPlanState.previewPaneOpen === desktop, width >= 1024);
                assert.equal(await page.locator('[data-proposal-workspace-toolbar]').count(), 1);
                assert.equal(await page.locator('.proposal-preview-dock').isVisible(), width >= 1024);
                const completion = page.locator('[data-work-plan-completion-status]');
                const workPlanToolbar = page.getByRole('group', { name: 'Work Plan tools' });
                const completionHint = workPlanToolbar.locator('.proposal-writing-hint');
                await expect(completion).toHaveText('In progress');
                await expect(completionHint).toHaveText('Objective 2 needs a continuous schedule.');
                for (const control of await workPlanToolbar.locator('.proposal-writing-target, .proposal-writing-hint, button:visible').all()) {
                    assert.ok(await control.evaluate(element => parseFloat(getComputedStyle(element).fontSize)) >= 16);
                }
                for (const button of await workPlanToolbar.getByRole('button').all()) {
                    assert.ok((await button.boundingBox()).height >= 44);
                }
                if (width >= 1024) {
                    await page.waitForFunction(() => window.workPlanState.previewReady && !window.workPlanState.previewLoading);
                }
                const secondEntry = page.locator('[data-work-plan-entry-id="2"]');
                const objective = secondEntry.getByRole('heading', { name: 'Evaluate the monitoring workflow' });
                const outputs = secondEntry.getByRole('textbox', { name: 'Expected outputs' });
                const activities = secondEntry.getByRole('textbox', { name: 'Activities' });
                const proposalMethodsNote = secondEntry.getByText('Matches the Detailed Proposal methods. You can edit these here.');
                await expect(objective).toHaveAttribute('id', 'objective-2');
                await expect(secondEntry.getByText('From Detailed Proposal', { exact: true })).toBeVisible();
                await expect(secondEntry.getByText('Evaluate the monitoring workflow', { exact: true })).toHaveCount(1);
                await expect(secondEntry.locator('input[type="hidden"][name="entries[1][objective]"]')).toHaveValue('Evaluate the monitoring workflow');
                assert.equal(await secondEntry.getByRole('textbox').count(), 2);
                const outputBox = await outputs.boundingBox();
                const activityBox = await activities.boundingBox();
                assert.ok(activityBox.y > outputBox.y + outputBox.height);
                assert.equal(activityBox.x, outputBox.x);
                await expect(proposalMethodsNote).toBeHidden();
                await outputs.fill('Reviewed evaluation dataset');
                const editObjectives = page.getByRole('link', { name: 'Edit objectives in Detailed Proposal' });
                const replaceActivities = secondEntry.getByRole('button', { name: 'Replace activities with proposal methods' });
                assert.equal(await editObjectives.getAttribute('href'), '/detailed-proposal');
                for (const control of [editObjectives, replaceActivities]) {
                    const style = await control.evaluate(element => {
                        const computed = getComputedStyle(element);
                        return { decoration: computed.textDecorationLine, border: parseFloat(computed.borderTopWidth), height: element.getBoundingClientRect().height };
                    });
                    assert.equal(style.decoration, 'none');
                    assert.ok(style.border >= 1);
                    assert.ok(style.height >= 44);
                }
                await replaceActivities.click();
                await expect(activities).toHaveValue('Compare the recorded results');
                await expect(proposalMethodsNote).toBeVisible();
                await expect(activities).toBeEditable();
                await page.waitForFunction(() => window.savedBodies.some(body => body.some(([name, value]) => name === 'entries[1][activity]' && value === 'Compare the recorded results')));
                const startMonth = secondEntry.getByRole('combobox', { name: 'Start month' });
                await expect(secondEntry.getByRole('combobox')).toHaveCount(1);
                const selectedMonths = () => secondEntry.locator('[data-work-plan-month][aria-pressed="true"]').evaluateAll(buttons => buttons.map(button => button.dataset.workPlanMonth));
                const scheduledMonths = (body, index) => new URLSearchParams(body).getAll(`entries[${index}][months][]`);
                const waitForSavedMonths = months => page.waitForFunction(expected => {
                    const saved = new URLSearchParams(window.savedBodies.at(-1));
                    return !window.workPlanState.autoSaveInFlight
                        && saved.getAll('entries[1][months][]').join() === expected.join();
                }, months);
                await expect(startMonth).toHaveValue('13');
                assert.deepEqual(await selectedMonths(), ['13', '15']);
                await secondEntry.getByRole('button', { name: 'Fill gaps' }).click();
                assert.deepEqual(await selectedMonths(), ['13', '14', '15']);
                await startMonth.selectOption({ label: 'Y1 / M12' });
                assert.deepEqual(await selectedMonths(), ['12', '13', '14', '15']);
                await expect(secondEntry.locator('[data-work-plan-month="11"]')).toBeDisabled();
                await secondEntry.locator('[data-work-plan-month="16"]').click();
                assert.deepEqual(await selectedMonths(), ['12', '13', '14', '15', '16']);
                await secondEntry.locator('[data-work-plan-month="15"]').click();
                assert.deepEqual(await selectedMonths(), ['12', '13', '14', '15']);
                await waitForSavedMonths(['12', '13', '14', '15']);
                if (width >= 1024) {
                    const previewRequest = await page.evaluate(() => window.previewBodies.at(-1));
                    assert.deepEqual(scheduledMonths(previewRequest, 1), ['12', '13', '14', '15']);
                }
                await startMonth.selectOption('1');
                await expect(startMonth).toHaveValue('1');
                const expectedMonths = Array.from({ length: 15 }, (_, index) => String(index + 1));
                assert.deepEqual(await selectedMonths(), expectedMonths);
                await waitForSavedMonths(expectedMonths);
                await expect(completion).toHaveText('Complete');
                await expect(completionHint).toBeHidden();
                if (width < 1024) assert.equal(await page.evaluate(() => window.previewBodies.length), 0);
                const saved = await page.evaluate(() => window.savedBodies.at(-1));
                assert.ok(saved.some(([name, value]) => name === 'entries[1][objective]' && value === 'Evaluate the monitoring workflow'));
                assert.ok(saved.some(([name, value]) => name === 'entries[1][expected_output]' && value === 'Reviewed evaluation dataset'));
                assert.deepEqual(scheduledMonths(saved, 0), ['1', '2']);
                assert.deepEqual(scheduledMonths(saved, 1), expectedMonths);

                await page.getByRole('button', { name: 'Collapse objective', exact: true }).click();
                await page.locator('#activity-2').waitFor({ state: 'hidden' });
                assert.equal(await page.locator('#activity-2').isVisible(), false);
                await page.getByRole('button', { name: 'Expand objective', exact: true }).click();
                await page.locator('#activity-2').waitFor({ state: 'visible' });
                assert.equal(await page.locator('#activity-2').isVisible(), true);
                await expect(startMonth).toHaveValue('1');
                assert.deepEqual(await selectedMonths(), expectedMonths);
                const previewToggle = page.locator('[data-proposal-preview-toggle]');
                assert.equal(await previewToggle.count(), 1);
                if (width >= 1024) {
                    await previewToggle.click();
                    await page.locator('.proposal-preview-dock').waitFor({ state: 'hidden' });
                    assert.equal(await page.locator('.proposal-preview-dock').isVisible(), false);
                }
                await previewToggle.click();
                await page.waitForFunction(() => window.workPlanState.previewReady && !window.workPlanState.previewLoading && !window.workPlanState.previewStale);
                await expect(page.locator('.proposal-preview-dock').getByRole('button', { name: 'Print', exact: true, includeHidden: true })).toHaveCount(0);
                assert.equal(await page.getByRole('button', { name: 'Refresh preview', exact: true }).count(), 0);
                assert.equal(await page.getByRole('button', { name: 'Retry preview', exact: true }).isVisible(), false);
                const request = await page.evaluate(() => window.previewBodies.at(-1));
                assert.deepEqual(scheduledMonths(request, 0), ['1', '2']);
                assert.deepEqual(scheduledMonths(request, 1), expectedMonths);
                assert.equal(request.some(([name]) => name === '_method'), false);
                if (width >= 1024) {
                    assert.equal(await page.locator('.proposal-preview-dock-expanded').count(), 0);
                    const columns = await page.locator('.proposal-writing-columns').boundingBox();
                    const dock = await page.locator('.proposal-preview-dock').boundingBox();
                    const editor = await page.locator('.proposal-edit-pane').boundingBox();
                    assert.ok(editor.width > dock.width, `The editor must stay wider than the compact preview at ${width}px: ${JSON.stringify({ columns, dock, editor })}`);
                    assert.ok(dock.width <= 336, 'The side preview must stay within its compact desktop width');
                    assert.ok(editor.width >= 240, 'The editor must remain usable beside the compact preview');
                    const fittedPreview = await page.locator('iframe').evaluate((frame) => ({
                        zoom: Number(frame.contentDocument.body.style.zoom),
                        width: frame.contentDocument.documentElement.clientWidth,
                        scrollWidth: frame.contentDocument.documentElement.scrollWidth,
                    }));
                    assert.ok(fittedPreview.zoom > 0 && fittedPreview.zoom <= 1, 'The compact preview must scale the paper to fit');
                    assert.ok(fittedPreview.scrollWidth <= fittedPreview.width, 'The fitted paper must not overflow the side preview horizontally');
                    await page.locator('#output-2').fill('Updated beside the compact paper preview');
                    assert.equal(await page.locator('#output-2').inputValue(), 'Updated beside the compact paper preview');
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    await page.getByRole('button', { name: 'Enlarge the paper preview' }).click();
                }
                await page.locator('.proposal-preview-dock-expanded').waitFor({ state: 'visible' });
                assert.equal(await page.locator('.proposal-preview-dock-expanded').isVisible(), true);
                assert.equal(await page.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                await page.locator('[x-ref="previewFrame"]').focus();
                await page.keyboard.press('Tab');
                assert.equal(await page.getByRole('button', { name: 'Zoom out', exact: true }).evaluate((element) => element === document.activeElement), true);
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.workPlanState.previewFullscreen);
                const restoredFocus = width >= 1024 ? page.getByRole('button', { name: 'Enlarge the paper preview' }) : previewToggle;
                await page.waitForFunction((element) => element === document.activeElement, await restoredFocus.elementHandle());
                await activities.fill('   ');
                await expect(completionHint).toHaveText('Objective 2 needs activities.');
                await expect(completion).toHaveText('In progress');
                assert.equal(await page.evaluate(() => new URLSearchParams(window.savedBodies.at(-1)).get('save_as_draft')), '1');
                await activities.fill('Validate results with community monitors');
                await expect(proposalMethodsNote).toBeHidden();
                if (width < 1024) await previewToggle.click();
                await page.waitForFunction(() => !window.workPlanState.previewStale && window.workPlanState.previewHtml.includes('Validate results'));
                assert.equal(await page.getByRole('button', { name: 'Retry preview', exact: true }).isVisible(), false);
                const lastPreview = await page.evaluate(() => window.workPlanState.previewHtml);
                await page.evaluate(() => { window.previewFailure = true; });
                if (width < 1024) await page.evaluate(() => window.workPlanState.closeProposalPreview());
                await page.locator('#activity-2').fill('Validate results after reviewing required months');
                if (width < 1024) await previewToggle.click();
                await page.waitForFunction(() => !window.workPlanState.previewLoading && window.workPlanState.previewError);
                assert.equal(await page.evaluate(() => window.workPlanState.validationMessage), '');
                assert.equal(await page.evaluate(() => window.workPlanState.previewHtml), lastPreview);
                assert.equal(await page.getByRole('alert').textContent(), 'Choose the required months.');
                assert.equal(await page.getByRole('button', { name: 'Retry preview', exact: true }).isVisible(), true);
                await page.evaluate(() => { window.previewFailure = false; });
                await page.getByRole('button', { name: 'Retry preview', exact: true }).click();
                await page.waitForFunction(() => !window.workPlanState.previewLoading && !window.workPlanState.previewStale);
                assert.equal(await page.evaluate(() => window.workPlanState.previewHtml.includes('Validate results after reviewing required months')), true);
                assert.equal(await page.getByRole('button', { name: 'Retry preview', exact: true }).isVisible(), false);
                await page.evaluate(() => window.workPlanState.closeProposalPreview());
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                const monthColumns = await secondEntry.locator('.work-plan-writing-months').first().evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').length);
                assert.ok(monthColumns >= 3 && monthColumns <= 12);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                    await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `work-plan-workspace-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.evaluate(() => {
                    document.body.classList.add('revision-embedded');
                    document.querySelector('main').parentElement.setAttribute('data-revision-embedded', '');
                    window.workPlanState.showProposalPreview();
                });
                assert.equal(await page.evaluate(() => window.workPlanState.previewPaneOpen || window.workPlanState.previewFullscreen), false);
                assert.equal(await page.locator('[data-proposal-preview-toggle]').isVisible(), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
