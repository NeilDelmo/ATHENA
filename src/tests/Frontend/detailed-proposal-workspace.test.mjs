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
const semantic = app.slice(app.indexOf('function sanitizedSemanticHtml('), app.indexOf('\nwindow.insertProposalCitationMarker'));
const helpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replaceAll('export ', '');
const preview = readFileSync(new URL('../../resources/js/proposal-preview-workspace.js', import.meta.url), 'utf8').replaceAll('export ', '');
const paperWorkspace = readFileSync(new URL('../../resources/js/proposal-paper-workspace.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
const workspace = readFileSync(new URL('../../resources/js/detailed-proposal-workspace.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
const previewView = readFileSync(new URL('../../resources/views/components/proposal-paper-preview.blade.php', import.meta.url), 'utf8')
    .replace(/^@props\([^\n]+\)\r?\n/, '')
    .replaceAll('{{ $panelId }}', 'proposal-preview-panel')
    .replaceAll('{{ $previewLabel }}', 'Detailed proposal preview')
    .replaceAll('{{ $frameTitle }}', 'Detailed Research Proposal content preview');
const toolbar = execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    echo view('components.detailed-proposal-writing-toolbar')->render();
`], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });

test('following a writing section scrolls only the preview frame before and after refresh', async () => {
    const cvWorkspace = readFileSync(new URL('../../resources/js/curriculum-vitae-workspace.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const { nested, cv } of [{ nested: false, cv: false }, { nested: true, cv: false }, { nested: true, cv: true }]) {
            const section = cv ? 'cv-person-1' : 'rationale';
            const page = await browser.newPage({ viewport: { width: 1440, height: 700 } });
            await page.setContent(`<body style="margin:0"><div style="height:800px"></div>
                <div id="ancestor" style="${nested ? 'height:360px;overflow-y:auto' : ''}">
                    ${nested ? '<div style="height:250px"></div>' : ''}
                    <iframe id="preview" style="display:block;width:400px;height:300px;border:0"></iframe>
                    ${nested ? '<div style="height:500px"></div>' : ''}
                </div><div style="height:2000px"></div></body>`);
            await page.addScriptTag({ content: `${preview}\n${paperWorkspace}\n${cvWorkspace}
                window.previewState = {
                    ...${cv ? 'curriculumVitaePaperWorkspace' : 'proposalPaperPreviewWorkspace'}(), previewPaneOpen: true, previewHtml: 'Loaded preview', activeCvPersonIndex: 0,
                    $refs: { previewFrame: document.getElementById('preview') }, $nextTick: callback => callback(),
                };
                window.previewLoads = 0;
                previewState.$refs.previewFrame.addEventListener('load', () => {
                    previewState.proposalPreviewLoaded(); window.previewLoads++;
                });` });
            const results = [];
            for (const [index, height] of [1600, 2000].entries()) {
                await page.evaluate(({ height, index, nested, section }) => {
                    window.scrollTo(0, 350);
                    document.getElementById('ancestor').scrollTop = nested ? 125 : 0;
                    if (index === 0) previewState.previewFocusSection = '';
                    previewState.$refs.previewFrame.srcdoc = `<body style="margin:0;width:800px"><div style="height:${height}px"></div><section data-proposal-preview-section="${section}">Writing section</section><div style="height:2000px"></div></body>`;
                }, { height, index, nested, section });
                await page.waitForFunction((count) => window.previewLoads === count, index + 1);
                if (index === 0) await page.evaluate(({ cv, section }) => cv ? previewState.focusCurriculumVitaePreview() : previewState.focusProposalPaperPreview(section), { cv, section });
                results.push(await page.evaluate(() => ({
                    mainScroll: window.scrollY,
                    ancestorScroll: document.getElementById('ancestor').scrollTop,
                    frameScroll: previewState.$refs.previewFrame.contentWindow.scrollY,
                    sectionTop: previewState.$refs.previewFrame.contentDocument.querySelector('section').getBoundingClientRect().top,
                    zoom: Number(previewState.$refs.previewFrame.contentDocument.body.style.zoom),
                })));
            }
            for (const result of results) {
                assert.equal(result.zoom, 0.5);
                assert.equal(result.mainScroll, 350, `Preview must preserve the editor viewport: ${JSON.stringify({ nested, cv, results })}`);
                assert.equal(result.ancestorScroll, nested ? 125 : 0);
                assert.ok(result.frameScroll > 0);
                assert.ok(Math.abs(result.sectionTop) <= 1, `Zoomed section should align with the frame top: ${JSON.stringify(result)}`);
            }
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('attachment references remain neutral and compact in both themes', async () => {
    const view = readFileSync(resolve('resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php'), 'utf8');
    const headings = {
        'work-plan': 'XIV. Major Activities/Workplan (Gantt Chart):',
        budget: 'XV. Line-Item Budget:',
        'curriculum-vitae': 'XVII. Curriculum Vitae:',
    };
    const rows = [...view.matchAll(/<section\b[^>]*data-proposal-attachment-reference[\s\S]*?<\/section>/g)]
        .map((match) => match[0]).join('\n')
        .replace(/\{\{ \$sectionHeadings\['([^']+)'\] \}\}/g, (_, key) => headings[key]);
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const styles = readFileSync(resolve('public/build', manifest['resources/css/app.css'].file), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1024]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 600 } });
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles}</style><body class="bg-[#F5F7FA] p-4 font-sans dark:bg-slate-950"><div class="space-y-6">${rows}</div></body></html>`);
                assert.equal(await page.locator('[data-proposal-attachment-reference]').count(), 3);
                for (const row of await page.locator('[data-proposal-attachment-reference]').all()) {
                    assert.equal(await row.evaluate((element) => getComputedStyle(element).backgroundColor), 'rgba(0, 0, 0, 0)');
                    assert.equal(await row.getByRole('heading').isVisible(), true);
                    assert.ok((await row.boundingBox()).height <= 80);
                }
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('shared writing tools preserve selection, serialize tables, target figures, and fit both preview layouts', async () => {
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const workspaceStyles = readFileSync(new URL('../../resources/css/proposal-paper-workspace.css', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 1024, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${workspaceStyles}</style><body data-app-shell>
                    <div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px">
                    <div style="position:sticky;top:0;height:120px">Application header</div>
                    <main style="padding:24px" data-detailed-proposal-workspace data-proposal-paper-workspace x-data="writingFixture">
                        ${toolbar}
                        <div class="proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window="resizeProposalPaperPreview()">
                            <form x-ref="form" data-detailed-proposal-autosave-form class="proposal-edit-pane" :inert="previewFullscreen">
                                <label for="rationale">Rationale</label><textarea id="rationale" name="rationale" rows="14" data-semantic-editor x-model="rationale"></textarea>
                                <label for="executive-brief">Executive Brief</label><textarea id="executive-brief" name="executive_brief" data-semantic-editor x-model="executiveBrief"></textarea>
                                <label for="date">Start date</label><input id="date" type="date">
                                <div id="methodology-specific-methods"><label for="method-details">Specific Method details</label><textarea id="method-details"></textarea></div>
                            </form>${previewView}
                        </div>
                        <button type="button" x-show="!previewPaneOpen" @click="showProposalPreview()" class="proposal-writing-preview-launcher">Preview paper</button>
                    </main></div></body></html>`);
                await page.addScriptTag({ content: `${helpers}\n${preview}\n${paperWorkspace}\n${workspace}\n${semantic}
                    window.imageTargets = []; window.citations = []; window.sourceTargets = []; window.previewRequests = 0;
                    window.addEventListener('proposal-cite-selection', e => window.citations.push(e.detail));
                    window.addEventListener('proposal-open-sources', e => window.sourceTargets.push(e.detail));
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('writingFixture', () => ({
                            ...detailedProposalPreviewWorkspace(), rationale:'<p>Study paragraph.</p>', executiveBrief:'<p>Summary.</p>',
                            previewHtml:'<html><body style="width:816px"><h1>Detailed Research Proposal</h1><p>Draft preview.</p></body></html>',
                            previewReady:true, previewLoading:false, previewError:'', validationMessage:'',
                            init() {
                                window.writingState = this;
                                this.$nextTick(() => {
                                    initializeSemanticEditors();
                                    this.cleanup = initializeDetailedProposalWritingTools(this.$el, {
                                        openImagePicker:section=>window.imageTargets.push(section),
                                        onActiveSection:field=>this.focusDetailedProposalPreview(field),
                                    });
                                    this.initializeDetailedProposalPreview();
                                    this.$refs.form.addEventListener('input', () => this.markProposalPreviewStale());
                                });
                            },
                            async generatePreview() {
                                window.previewRequests++; this.previewLoading=true;
                                await new Promise(resolve=>setTimeout(resolve,40));
                                this.previewHtml='<html><body style="width:816px"><h1>Detailed Research Proposal</h1><div style="height:2000px"></div><section data-proposal-preview-section="rationale">'+this.rationale+'</section><div style="height:2000px"></div></body></html>';
                                this.previewLoading=false; this.previewStale=false;
                            },
                            printPreview() {},
                            destroy() { this.cleanup?.(); this.destroyDetailedProposalPreview(); }
                        }));
                    });` });
                await page.addScriptTag({ content: readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8') });
                const rationale = page.getByRole('textbox', { name: 'Rationale', exact: true });
                await rationale.waitFor();
                assert.equal(await page.locator('[data-proposal-writing-toolbar]').count(), 1);
                assert.equal(await page.locator('[aria-label="Text formatting"]').count(), 0);
                const boldButton = page.getByRole('button', { name: 'Bold', exact: true });
                assert.equal(await boldButton.isDisabled(), true);
                assert.equal(await page.getByRole('button', { name: 'Sources', exact: true }).count(), 0);
                assert.equal(await page.locator('[data-writing-menu-toggle]').count(), 3);
                assert.equal(await page.locator('[data-writing-editor-expand]').count(), 2);
                assert.equal(await page.locator('[data-writing-expand]').count(), 0);
                await page.evaluate(() => {
                    const field = document.createElement('textarea');
                    field.id = 'new-method-details'; field.name = 'method_details'; field.dataset.semanticEditor = '';
                    const label = document.createElement('label'); label.htmlFor = field.id; label.textContent = 'New method details';
                    document.querySelector('form').append(label, field);
                    initializeSemanticEditors();
                });
                const dynamicExpand = page.locator('[data-writing-editor-expand="new-method-details"]');
                await dynamicExpand.waitFor();
                await dynamicExpand.click();
                await expect(dynamicExpand).toHaveAttribute('aria-expanded', 'true');
                await dynamicExpand.click();
                await expect(dynamicExpand).toHaveAttribute('aria-expanded', 'false');
                await page.locator('#date').focus();
                const citationBounds = await page.locator('[data-writing-cite]').boundingBox();
                const toolbarBounds = await page.locator('[data-proposal-writing-toolbar]').boundingBox();
                assert.ok(citationBounds.x >= toolbarBounds.x && citationBounds.x + citationBounds.width <= toolbarBounds.x + toolbarBounds.width);
                assert.equal(await boldButton.locator('svg[aria-hidden="true"]').count(), 1);
                assert.ok(await boldButton.evaluate((button) => parseFloat(getComputedStyle(button).fontSize) >= 13));
                assert.ok(await boldButton.evaluate((button) => parseFloat(getComputedStyle(button).opacity) >= .7));
                const boldHeight = (await boldButton.boundingBox()).height;
                assert.ok(width < 640 ? boldHeight >= 44 : boldHeight <= 36);
                for (const group of ['Text style', 'History', 'Insert']) {
                    assert.equal(await page.getByRole('group', { name: group, exact: true }).count(), 1);
                }
                await rationale.evaluate((element) => {
                    const range=document.createRange(); range.selectNodeContents(element.querySelector('p'));
                    const selection=window.getSelection(); selection.removeAllRanges(); selection.addRange(range); element.focus();
                });
                await boldButton.locator('svg').click();
                assert.match(await page.locator('#rationale').inputValue(), /<strong>Study paragraph\.<\/strong>/);
                const moreButton = page.getByRole('button', { name: 'More', exact: true });
                await moreButton.click();
                const moreMenu = page.getByRole('menu', { name: 'More formatting', exact: true });
                await expect(moreMenu.getByRole('menuitem', { name: 'Underline', exact: true })).toBeFocused();
                await page.keyboard.press('ArrowDown');
                await expect(moreMenu.getByRole('menuitem', { name: 'Clear formatting', exact: true })).toBeFocused();
                await page.keyboard.press('Escape');
                await expect(moreButton).toBeFocused();
                assert.equal(await moreMenu.isVisible(), false);
                await moreButton.click();
                await page.getByRole('button', { name: 'Hide tools', exact: true }).click();
                await expect(page.locator('[data-writing-toolbar-controls]')).toBeHidden();
                await page.getByRole('button', { name: 'Show tools', exact: true }).click();
                await expect(page.locator('[data-writing-toolbar-controls]')).toBeVisible();
                assert.equal(await moreMenu.isVisible(), false);
                await moreButton.click();
                await moreMenu.getByRole('menuitem', { name: 'Underline', exact: true }).click();
                assert.match(await page.locator('#rationale').inputValue(), /<u>/);
                await page.getByRole('button', { name: 'Lists', exact: true }).click();
                const listsMenu = page.getByRole('menu', { name: 'Lists', exact: true });
                const menuBounds = await listsMenu.boundingBox();
                assert.ok(menuBounds.x >= 0 && menuBounds.x + menuBounds.width <= width);
                await listsMenu.getByRole('menuitem', { name: 'Bullets', exact: true }).click();
                assert.equal(await rationale.locator('ul').count(), 1);
                await page.locator('[data-writing-command="undo"]').click();
                assert.equal(await rationale.locator('ul').count(), 0);
                await page.locator('[data-writing-cite]').click();
                assert.equal((await page.evaluate(() => window.citations))[0].fieldId, 'rationale');

                await rationale.press('ControlOrMeta+End');
                assert.equal(await page.getByRole('button', { name: 'Insert citation', exact: true }).isDisabled(), false);
                await page.getByRole('button', { name: 'Insert citation', exact: true }).click();
                const caretCitation = (await page.evaluate(() => window.citations)).at(-1);
                assert.equal(caretCitation.fieldId, 'rationale');
                assert.equal(caretCitation.selectedText, '');
                assert.equal(caretCitation.collapsed, true);
                await rationale.evaluate((element) => {
                    document.execCommand('insertHTML', false, '<span data-proposal-citation="42" contenteditable="false"> [1]</span>&nbsp;');
                    const range = document.createRange(); range.selectNodeContents(element);
                    const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
                    element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                });
                await moreButton.click();
                await moreMenu.getByRole('menuitem', { name: 'Clear formatting', exact: true }).click();
                assert.equal(await rationale.locator('strong, u').count(), 0);
                assert.equal(await rationale.locator('[data-proposal-citation="42"]').count(), 1);
                await rationale.press('ControlOrMeta+End');
                await page.getByRole('button', { name: 'Insert', exact: true }).click();
                await page.locator('[data-writing-table]').click();
                await page.locator('[data-table-rows]').fill('3');
                await page.locator('[data-writing-insert-table]').click();
                assert.equal(await rationale.locator('tr').count(), 3);
                assert.equal(await rationale.locator('th').count(), 2);
                assert.match(await rationale.locator('table').evaluate((table) => {
                    const range = document.createRange(); range.selectNodeContents(table.parentElement); range.setEndBefore(table);
                    return range.toString();
                }), /Study paragraph/);
                await rationale.locator('th').first().click();
                await rationale.press('Tab');
                assert.equal(await page.evaluate(() => {
                    const node = window.getSelection().anchorNode;
                    return (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement).closest('th')?.cellIndex;
                }), 1);
                await rationale.press('Shift+Tab');
                assert.equal(await page.evaluate(() => {
                    const node = window.getSelection().anchorNode;
                    return (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement).closest('th')?.cellIndex;
                }), 0);
                const cell = rationale.locator('td').first();
                await cell.fill('Local monitors');
                assert.match(await page.locator('#rationale').inputValue(), /<td>(?:<p>)?Local monitors(?:<\/p>)?<\/td>/);
                await cell.click();
                await page.locator('[data-writing-table-action="add-row"]').click();
                assert.equal(await rationale.locator('tr').count(), 4);
                await page.locator('[data-writing-command="undo"]').click();
                assert.equal(await rationale.locator('tr').count(), 3);
                await rationale.locator('td').first().click();
                await page.locator('[data-writing-table-action="remove-column"]').click();
                assert.equal(await rationale.locator('tr').first().locator('th').count(), 1);
                await rationale.locator('td').first().click();
                await page.locator('[data-writing-table-action="add-column"]').click();
                assert.equal(await rationale.locator('tr').first().locator('th').count(), 2);
                await rationale.locator('td').first().click();
                await page.locator('[data-writing-table-action="remove-row"]').click();
                assert.equal(await rationale.locator('tr').count(), 2);
                await rationale.locator('td').first().click();
                await page.locator('[data-writing-table-action="remove-table"]').click();
                assert.equal(await rationale.locator('table').count(), 0);
                await page.locator('[data-writing-command="undo"]').click();
                assert.equal(await rationale.locator('tr').count(), 2);
                await page.locator('[data-writing-command="redo"]').click();
                assert.equal(await rationale.locator('table').count(), 0);
                await page.getByRole('button', { name: 'Insert', exact: true }).click();
                await page.locator('[data-writing-image]').click();
                assert.deepEqual(await page.evaluate(() => window.imageTargets), ['rationale']);
                const expandButton = page.locator('[data-writing-editor-expand="rationale"]');
                await expandButton.click();
                assert.ok((await rationale.boundingBox()).height >= 320);
                await expect(expandButton).toHaveText('Shrink editor');
                await expect(expandButton).toHaveAttribute('aria-expanded', 'true');
                await expandButton.click();
                await expect(expandButton).toHaveText('Expand editor');
                assert.ok((await rationale.boundingBox()).height < 320);
                await page.locator('#date').focus();
                assert.equal(await page.locator('[data-writing-cite]').isDisabled(), true);
                assert.equal(await page.locator('[data-writing-image]').isDisabled(), true);
                assert.equal(await page.locator('[data-writing-command="bold"]').isDisabled(), true);
                await page.locator('#method-details').focus();
                assert.equal(await page.locator('[data-writing-command="bold"]').isDisabled(), true);
                await page.getByRole('button', { name: 'Insert', exact: true }).click();
                await page.locator('[data-writing-image]').click();
                assert.deepEqual(await page.evaluate(() => window.imageTargets), ['rationale', 'specific_methods']);

                if (width >= 1024) {
                    await rationale.focus();
                    await page.waitForFunction(() => window.previewRequests > 0 && !window.writingState.previewStale);
                    await page.waitForFunction(() => document.querySelector('[x-ref="previewFrame"]').contentWindow.scrollY > 0);
                    assert.equal(await page.locator('.proposal-preview-dock').isVisible(), true);
                    await page.getByRole('button', { name: 'Collapse preview', exact: true }).click();
                }
                await page.getByRole('button', { name: 'Preview paper', exact: true }).click();
                if (width >= 1024) await page.getByRole('button', { name: 'Enlarge the paper preview' }).click();
                await page.locator('.proposal-preview-dock-expanded').waitFor({ state: 'visible' });
                assert.equal(await page.locator('.proposal-preview-dock-expanded').isVisible(), true);
                assert.equal(await page.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                await page.waitForFunction(() => !window.writingState.previewLoading
                    && document.querySelector('[x-ref="previewFrame"]').contentDocument?.readyState === 'complete');
                await page.locator('[x-ref="previewFrame"]').focus();
                await page.keyboard.press('Tab');
                await expect(page.getByRole('button', { name: 'Zoom out', exact: true })).toBeFocused();
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.writingState.previewFullscreen);
                const focusTarget = width >= 1024 ? page.getByRole('button', { name: 'Enlarge the paper preview' }) : page.getByRole('button', { name: 'Preview paper', exact: true });
                await expect(focusTarget).toBeFocused();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                    await rationale.focus();
                    await page.locator('[data-proposal-writing-toolbar]').screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `proposal-toolbar-${width}-${dark ? 'dark' : 'light'}.png`) });
                    await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `proposal-workspace-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
