import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const app = read('resources/js/app.js');
const factory = (name) => {
    const start = app.indexOf(`Alpine.data('${name}',`);
    return app.slice(start, app.indexOf('Alpine.data(', start + 1));
};
const controller = factory('proposalDraftCurriculumVitae');
const dates = factory('datePicker');
const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
const focusHelper = app.slice(app.indexOf('function focusNewFormEntry('), app.indexOf('\nthemeMediaQuery.addEventListener', app.indexOf('function focusNewFormEntry(')));
const preview = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '');
const workspace = read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const cvWorkspace = read('resources/js/curriculum-vitae-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const peopleHelpers = read('resources/js/proposal-people.js').replaceAll('export ', '');
const previewView = read('resources/views/components/proposal-paper-preview.blade.php')
    .replace(/^@props\([^\n]+\)\n/, '')
    .replaceAll('{{ $panelId }}', 'curriculum-vitae-preview-panel')
    .replaceAll('{{ $previewLabel }}', 'Team CV preview')
    .replaceAll('{{ $frameTitle }}', 'Attachment C team CV preview');
const edit = read('resources/views/faculty/proposal-drafts/curriculum-vitae/edit.blade.php');
const members = edit.slice(edit.indexOf('<template x-for="(person, personIndex) in people"'), edit.indexOf('\n            <noscript>'));
const manager = edit.slice(edit.indexOf('<section id="cv-member-manager"'), edit.indexOf('\n        <form id="cv-members-form"'));
const paperTemplate = read('resources/views/faculty/curriculum-vitae/preview.blade.php')
    .replace("@vite('resources/css/curriculum-vitae-print.css')", `<style>${read('resources/css/curriculum-vitae-print.css')}</style>`);

test('CV editing follows one member while preserving the whole package, autosave, section entries, and preview controls', async () => {
    const rendered = JSON.parse(execFileSync('php', ['-r', `
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $sections = config('curriculum_vitae.sections');
        echo json_encode([
            'toolbar' => view('components.curriculum-vitae-writing-toolbar')->render(),
            'members' => \\Illuminate\\Support\\Facades\\Blade::render($input['members'], ['sections' => $sections]),
            'manager' => \\Illuminate\\Support\\Facades\\Blade::render($input['manager'], ['sampleAvailable' => false]),
            'preview' => \\Illuminate\\Support\\Facades\\Blade::render($input['preview'], [
                'curriculumVitae' => \\App\\Support\\CurriculumVitaeData::fromValidated(['people' => [
                    ['first_name' => 'Alice', 'last_name' => 'Researcher'],
                    ['first_name' => 'Sam', 'last_name' => 'Member'],
                ]]),
            ]),
            'sections' => $sections,
        ], JSON_THROW_ON_ERROR);
    `], { input: JSON.stringify({ members, manager, preview: paperTemplate }), encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' }, maxBuffer: 6 * 1024 * 1024 }));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const workspaceStyles = read('resources/css/proposal-paper-workspace.css');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 1024, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const revisionTarget = width === 1024 && dark ? 'section-cv-2-academic_background' : null;
                const config = {
                    sections: rendered.sections, revisionTarget, updateUrl: '/save', previewUrl: '/preview', csrfToken: 'test',
                    initialPeople: [
                        { first_name: 'Alice', last_name: 'Researcher', email: 'alice@example.test' },
                        { first_name: 'Sam', last_name: '', academic_background: [{ degree: 'Doctor of Information Technology', status: 'Ongoing', year_start: '2024' }] },
                    ],
                    workspacePeople: [{ key: 'bina', name: 'Bina Scientist', email: 'bina@example.test', cv: { first_name: 'Bina', last_name: 'Scientist', email: 'bina@example.test' } }],
                };
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${workspaceStyles}</style><body data-app-shell>
                    <div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px">
                        <div style="position:sticky;top:0;height:120px">Application header</div>
                        <main class="mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8" data-proposal-paper-workspace data-curriculum-vitae-workspace @focusin="focusCurriculumVitaeField($event)" x-data='proposalDraftCurriculumVitae(${JSON.stringify(config)})'>
                            <h1 class="text-xl font-semibold">Attachment C: Curriculum Vitae</h1>
                            <div data-proposal-autosave-status><span data-proposal-autosave-message>Changes save automatically.</span><span data-proposal-autosave-indicator></span></div>
                            ${rendered.toolbar}
                            <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
                                <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen">
                                    ${rendered.manager}
                                    <form id="cv-members-form" x-ref="form" method="POST" action="/save" class="space-y-6" data-curriculum-vitae-autosave-form>
                                        <input type="hidden" name="_method" value="PUT"><input type="hidden" name="document_version" value="0"><input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
                                        ${rendered.members}
                                    </form>
                                </div>${previewView}
                            </div>
                            <button type="button" x-show="!previewPaneOpen" @click="showProposalPreview()" class="proposal-writing-preview-launcher">Preview paper</button>
                        </main>
                    </div></body></html>`);
                await page.addScriptTag({ content: `${preview}\n${workspace}\n${cvWorkspace}\n${peopleHelpers}\n${focusHelper}\n${dateHelpers}
                    window.savedBodies=[];window.previewBodies=[];window.previewFailure=false;
                    window.fetch=async(url,options)=>{
                        const body=[...options.body.entries()];
                        if(url==='/save') {
                            window.savedBodies.push(body);
                            return {ok:true,status:200,json:async()=>({document_version:window.savedBodies.length,saved_as_draft:options.body.get('save_as_draft')==='1'})};
                        }
                        window.previewBodies.push(body);
                        await new Promise(resolve=>setTimeout(resolve,30));
                        if(window.previewFailure) return {ok:false,status:422,json:async()=>({errors:{people:['Please review these team CVs.']}})};
                        const paper=new DOMParser().parseFromString(${JSON.stringify(rendered.preview)},'text/html');
                        paper.querySelectorAll('.cv-sheet').forEach((sheet,index)=>{
                            const cells=sheet.querySelectorAll('.cv-name-table .cv-value-row td');
                            ['last_name','first_name','middle_name'].forEach((key,cellIndex)=>{
                                cells[cellIndex].textContent=options.body.get('people['+index+']['+key+']')||'';
                            });
                        });
                        return {ok:true,status:200,text:async()=>paper.documentElement.outerHTML};
                    };
                    document.addEventListener('alpine:init',()=>{
                        const register=Alpine.data.bind(Alpine);
                        Alpine.data=(name,factory)=>register(name,config=>{
                            const state=factory(config);
                            if(name==='proposalDraftCurriculumVitae') {
                                const initialize=state.init;
                                state.init=function(){window.cvState=this;window.cvConfig=config;initialize.call(this)};
                            }
                            return state;
                        });
                        ${dates}\n${controller}
                    });` });
                await page.addScriptTag({ content: read('node_modules/alpinejs/dist/cdn.min.js') });
                await page.locator('.cv-writing-member:visible').waitFor();
                assert.equal(await page.locator('.cv-writing-member:visible').count(), 1);
                assert.equal(await page.evaluate(() => window.cvState.activeCvPersonIndex), revisionTarget ? 1 : 0);
                assert.equal(await page.locator('[data-proposal-workspace-toolbar]').count(), 1);
                assert.equal(await page.locator('.proposal-preview-dock').isVisible(), false);
                if (revisionTarget) assert.equal(await page.evaluate(() => window.cvState.activeCvSection), 'academic_background');
                const memberIds = await page.evaluate(() => window.cvState.people.map(person=>person.id));
                await page.locator('#cv-editing-member').selectOption(String(memberIds[0]));
                assert.equal(await page.evaluate(() => window.cvState.validateForm()), false);
                await page.locator('[name="people[1][last_name]"]').waitFor({ state: 'visible' });
                await page.waitForFunction(() => document.activeElement?.name === 'people[1][last_name]', null, { timeout: 5000 }).catch(async (error) => {
                    const details = await page.evaluate(() => ({
                        activeName: document.activeElement?.name,
                        member: window.cvState.activeCvPersonIndex,
                        invalid: [...document.querySelectorAll('[aria-invalid="true"]')].map((field) => ({ name: field.name, visible: Boolean(field.getClientRects().length) })),
                    }));
                    throw new Error(`CV validation focus failed: ${JSON.stringify(details)}`, { cause: error });
                });
                assert.equal(await page.locator('[name="people[1][last_name]"]').getAttribute('aria-invalid'), 'true');
                await page.locator('[name="people[1][last_name]"]').fill('Member');
                await page.locator('#cv-editing-section').selectOption('academic_background');
                const selectedMember = page.locator('.cv-writing-member:visible');
                const academic = selectedMember.locator('details[data-cv-section="academic_background"]');
                assert.equal(await academic.getByLabel('Year Ended', { exact: true }).first().isDisabled(), true);
                assert.equal(await academic.getByLabel('Year Ended', { exact: true }).first().inputValue(), 'Present');
                await academic.getByLabel('Thesis', { exact: true }).first().fill('Coastal monitoring systems\nCommunity reporting workflows');
                await page.locator('#cv-editing-section').selectOption('projects');
                await page.getByRole('button', { name: 'Add entry', exact: true }).click();
                const newProject = selectedMember.locator('details[data-cv-section="projects"] [data-cv-row-id]').last();
                const title = newProject.locator('textarea[name$="[title]"]');
                await title.waitFor();
                assert.equal(await title.evaluate((element) => element === document.activeElement), true);
                await title.fill('Community monitoring and reporting tools\nA research collaboration');
                assert.equal(await title.locator('..').evaluate((element) => getComputedStyle(element).gridColumnEnd), '-1');
                await page.waitForFunction(() => window.savedBodies.at(-1)?.some(([name,value])=>name==='people[1][projects][5][title]'&&value.includes('research collaboration'))&&!window.cvState.autoSaveInFlight);
                const saved = await page.evaluate(() => window.savedBodies.at(-1));
                assert.ok(saved.some(([name, value]) => name === 'people[0][first_name]' && value === 'Alice'));
                assert.ok(saved.some(([name, value]) => name === 'people[1][last_name]' && value === 'Member'));
                assert.ok(saved.some(([name, value]) => name === 'people[1][academic_background][0][thesis]' && value.includes('\nCommunity')));
                assert.equal(await page.evaluate(() => window.previewBodies.length), 0);

                await page.getByRole('button', { name: 'Add member', exact: true }).click();
                await page.locator('#workspace-cv-person-search').fill('Bina');
                await page.locator('#workspace-cv-person-options').getByRole('option', { name: /Bina Scientist/ }).click();
                await page.waitForFunction(() => window.cvState.people.length===3&&window.cvState.activeCvPersonIndex===2);
                assert.equal(await page.locator('[name="people[2][email]"]').inputValue(), 'bina@example.test');
                assert.equal(await page.evaluate(() => window.cvState.availableWorkspacePeople().length), 0);
                await page.getByRole('button', { name: 'Add member', exact: true }).click();
                await page.getByRole('button', { name: 'Add blank CV', exact: true }).click();
                await page.waitForFunction(() => window.cvState.people.length===4&&window.cvState.activeCvPersonIndex===3);
                await page.locator('.cv-writing-member:visible').getByRole('button', { name: 'Remove member', exact: true }).click();
                await page.waitForFunction(() => window.cvState.people.length===3&&window.cvState.activeCvPersonIndex===2);
                await page.locator('.cv-writing-member:visible').getByRole('button', { name: 'Remove member', exact: true }).click();
                await page.waitForFunction(() => window.cvState.people.length===2&&window.cvState.activeCvPersonIndex===1);
                assert.equal(await page.locator('.cv-writing-member:visible').count(), 1);
                assert.equal(await page.locator('[name="people[1][projects][5][title]"]').inputValue(), 'Community monitoring and reporting tools\nA research collaboration');

                await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => window.cvState.previewReady&&!window.cvState.previewLoading);
                const frame = page.locator('[x-ref="previewFrame"]');
                const previewAtMember = async (index) => {
                    await page.waitForFunction((memberIndex) => {
                        const section=document.querySelector('[x-ref="previewFrame"]').contentDocument?.querySelector('[data-proposal-preview-section="cv-person-'+memberIndex+'"]');
                        if(!section) return false;
                        const bounds=section.getBoundingClientRect();
                        const frame=document.querySelector('[x-ref="previewFrame"]');
                        return bounds.top>=-30&&bounds.top<=Math.max(0,frame.clientHeight-bounds.height)+30;
                    }, index, { timeout: 5000 }).catch(async (error) => {
                        assert.fail(`${width}/${dark ? 'dark' : 'light'}/member ${index}: ${error.message}; ${JSON.stringify(await page.evaluate((memberIndex) => {
                            const frame = document.querySelector('[x-ref="previewFrame"]');
                            const section = frame.contentDocument?.querySelector('[data-proposal-preview-section="cv-person-'+memberIndex+'"]');
                            return { package: window.cvState.previewViewingCvPackage, target: window.cvState.previewFocusSection, active: window.cvState.activeCvPersonIndex, zoom: frame.contentDocument?.body?.style.zoom, section: section?.getBoundingClientRect().toJSON(), frame: frame.getBoundingClientRect().toJSON(), scroll: frame.contentWindow?.scrollY };
                        }, index))}`);
                    });
                };
                await previewAtMember(2);
                assert.equal(await page.evaluate(() => window.previewBodies.at(-1).some(([name])=>name==='_method')), false);
                if (width < 1024) {
                    await page.keyboard.press('Escape');
                    await page.waitForFunction(() => !window.cvState.previewFullscreen);
                } else {
                    await page.locator('#cv-editing-member').selectOption(String(memberIds[0]));
                    await previewAtMember(1);
                    await page.locator('#cv-editing-member').selectOption(String(memberIds[1]));
                    await previewAtMember(2);
                    assert.ok(await page.locator('.proposal-edit-pane').evaluate((element) => element.getBoundingClientRect().width) > (width === 1440 ? 700 : 400));
                }
                await page.getByRole('button', { name: 'Review team CVs', exact: true }).click();
                await page.locator('.proposal-preview-dock-expanded').waitFor();
                await previewAtMember(1);
                assert.equal(await page.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                await frame.focus();
                await page.keyboard.press('Tab');
                assert.equal(await page.getByRole('button', { name: 'Zoom out', exact: true }).evaluate((element) => element === document.activeElement), true);
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.cvState.previewFullscreen);
                assert.equal(await page.getByRole('button', { name: 'Review team CVs', exact: true }).evaluate((element) => element === document.activeElement), true);
                await page.locator('[name="people[1][middle_name]"]').fill('Updated Coastal');
                if (width < 1024) await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => !window.cvState.previewStale&&window.cvState.previewHtml.includes('Updated Coastal'));
                await previewAtMember(2);
                const lastPreview = await page.evaluate(() => window.cvState.previewHtml);
                await page.evaluate(() => { window.previewFailure=true; });
                await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                await page.waitForFunction(() => !window.cvState.previewLoading&&window.cvState.validationMessage);
                assert.equal(await page.evaluate(() => window.cvState.previewHtml), lastPreview);
                await page.evaluate(() => { window.previewFailure=false; });
                await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                await page.waitForFunction(() => !window.cvState.previewLoading&&!window.cvState.previewStale);
                if (width < 1024) await page.evaluate(() => window.cvState.closeProposalPreview());
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                    await page.locator('#cv-editing-section').selectOption('personal');
                    await page.evaluate(() => {
                        const member=document.querySelector('.cv-writing-member:not([style*="display: none"])');
                        window.scrollTo({top:scrollY+member.getBoundingClientRect().top-parseFloat(getComputedStyle(member).scrollMarginTop),behavior:'instant'});
                    });
                    await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `curriculum-vitae-workspace-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                await page.evaluate(() => {
                    window.cvState.closeProposalPreview();
                    document.body.classList.add('revision-embedded');
                    document.querySelector('main').parentElement.setAttribute('data-revision-embedded','');
                    window.cvState.showCurriculumVitaePackagePreview();
                    window.cvConfig.revisionTarget='section-cv-1-academic_background';
                    window.cvState.initializeCurriculumVitaeMemberTarget();
                });
                await page.waitForFunction(() => window.cvState.activeCvPersonIndex===0);
                assert.equal(await page.evaluate(() => window.cvState.previewPaneOpen||window.cvState.previewFullscreen), false);
                assert.equal(await page.locator('[data-proposal-preview-toggle]').first().isVisible(), false);
                await page.getByRole('button', { name: 'Add member', exact: true }).click();
                await page.locator('#workspace-cv-person-search').waitFor({ state: 'visible' });
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
