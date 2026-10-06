import assert from 'node:assert/strict';
import { File } from 'node:buffer';
import test from 'node:test';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';
import { applyRevisionModificationStates, fitRevisionPaperPreview, initializeRevisionDialogs, initializeRevisionPanelResize, initializeRevisionPreviews, loadRevisionEditorFrame, previewRevisionEditor, revisionEditorForFrame, revisionNoChangeResolution, revisionPaperPreview, synchronizeRevisionNoChangeResponses, synchronizeSingleRevisionReply } from '../../resources/js/revision-workspace.js';

function cardFixture(editor = {}) {
    const nodes = {};
    const card = {
        dataset: { revisionDocument: 'work_plan', revisionPreviewUploadUrl: '/revision/preview' },
        querySelector: selector => nodes[selector] || null,
        closest: () => ({ querySelector: () => ({ value: 'csrf-token' }) }),
        addEventListener() {},
    };
    nodes['[data-revision-no-change]'] = { checked: false };
    nodes['[data-revision-comment]'] = { selectedOptions: [{ dataset: { pdfUrl: '/submitted.pdf' } }] };
    nodes['[data-revision-editor-frame]'] = {
        contentWindow: { athenaRevisionEditor: { topicId: 3, documentType: 'work_plan', ...editor } },
        closest: () => card,
        addEventListener() {},
    };
    return { card, nodes };
}

test('a single reply supplies the keep-paper explanation without losing it when switching actions', () => {
    const response = { value: '', placeholder: 'Explain your change.', dataset: {}, maxLength: 5000 };
    const explanation = { value: 'The submitted schedule already addresses this comment.' };
    const keep = { checked: true };
    const nodes = { '[data-revision-response-document]': response, '[data-revision-no-change-explanation]': explanation, '[data-revision-no-change]': keep };
    const card = { hasAttribute: () => true, querySelector: selector => nodes[selector] };
    synchronizeSingleRevisionReply(card, true);
    assert.equal(response.value, explanation.value);
    assert.equal(response.maxLength, 1000);
    response.value = 'This passage already covers the requested change.';
    synchronizeSingleRevisionReply(card);
    assert.equal(explanation.value, response.value);
    response.value = '';
    synchronizeSingleRevisionReply(card);
    assert.equal(explanation.value, '', 'Clearing the reply must leave the paper unresolved');
    response.value = 'Keep this response while editing.';
    keep.checked = false;
    synchronizeSingleRevisionReply(card);
    assert.equal(response.value, 'Keep this response while editing.');
    assert.equal(response.maxLength, 5000);
    assert.equal(response.placeholder, 'Explain your change.');
});

test('generated paper fits the revision panel after loading and resizing without changing PDF viewers', () => {
    const body = { style: {}, scrollWidth: 864 };
    const documentElement = { style: {}, clientWidth: 648 };
    const frame = { hasAttribute: () => true, contentDocument: { body, documentElement } };
    fitRevisionPaperPreview(frame);
    assert.equal(body.style.zoom, '0.75');
    documentElement.clientWidth = 432;
    fitRevisionPaperPreview(frame);
    assert.equal(body.style.zoom, '0.5');
    fitRevisionPaperPreview({ hasAttribute: () => false, get contentDocument() { assert.fail('PDF viewer must not be changed'); } });
});

test('preview generates the latest unsaved revision without saving or preparing its attachment', async () => {
    const state = {
        value: 'Revised activity',
        previewHtml: '<p>Old activity</p>',
        async generatePreview() { this.previewHtml = `<p>${this.value}</p>`; },
        save() { assert.fail('Preview must not save'); },
        downloadDocument() { assert.fail('Preview must not prepare an attachment'); },
    };
    assert.equal(await previewRevisionEditor(state), '<p>Revised activity</p>');
    state.value = 'Another edit';
    assert.equal(await previewRevisionEditor(state), '<p>Another edit</p>');
});

test('preview errors expose validation messages and never show an old paper', async () => {
    await assert.rejects(previewRevisionEditor({
        async generatePreview() { this.previewHtml = ''; this.validationMessage = 'Complete the activity.'; },
    }), /Complete the activity/);
    await assert.rejects(previewRevisionEditor(null), /still loading/);
    await assert.rejects(previewRevisionEditor({ generatePreview() {}, previewLoading: true }), /already being prepared/);
});

test('no-change preview uses the submitted paper even if the editor contains changes', async () => {
    const { card, nodes } = cardFixture({ preview() { assert.fail('No-change must use the submitted paper'); } });
    nodes['[data-revision-no-change]'].checked = true;
    const result = await revisionPaperPreview(card, 3);
    assert.equal(result.url, '/submitted.pdf');
    assert.match(result.label, /No file change needed/);
});

test('preview reads the matching mounted editor and rejects a different proposal frame', async () => {
    const { card, nodes } = cardFixture({ preview: async () => '<p>Current revision</p>' });
    assert.equal((await revisionPaperPreview(card, 3)).html, '<p>Current revision</p>');
    nodes['[data-revision-editor-frame]'].contentWindow.athenaRevisionEditor.topicId = 8;
    await assert.rejects(revisionPaperPreview(card, 3), /still loading/);
});

test('replacement PDF previews support selecting each uploaded file and force PDF content type', async (t) => {
    const original = URL.createObjectURL;
    const blobs = [];
    URL.createObjectURL = blob => { blobs.push(blob); return 'blob:replacement'; };
    t.after(() => { URL.createObjectURL = original; });
    const { card, nodes } = cardFixture({ preview() { assert.fail('Replacement must take priority over the editor'); } });
    nodes['input[type="file"]'] = { files: [new File(['%PDF-1'], 'first.pdf'), new File(['%PDF-2'], 'second.pdf')] };
    const result = await revisionPaperPreview(card, 3, 1);
    assert.equal(result.url, 'blob:replacement');
    assert.match(result.label, /second.pdf/);
    assert.equal(blobs[0].type, 'application/pdf');
    assert.equal(await blobs[0].text(), '%PDF-2');
});

test('Word replacement preview sends only the chosen file to the authorized preview endpoint', async (t) => {
    const original = globalThis.fetch;
    const originalUrl = URL.createObjectURL;
    t.after(() => { globalThis.fetch = original; URL.createObjectURL = originalUrl; });
    URL.createObjectURL = () => 'blob:converted-word';
    const file = new File(['word bytes'], 'revision.docx');
    globalThis.fetch = async (url, options) => {
        assert.equal(url, '/revision/preview');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.equal(options.body.get('paper'), file);
        return { ok: true, blob: async () => new Blob(['%PDF-1.7'], { type: 'application/pdf' }) };
    };
    const { card, nodes } = cardFixture();
    nodes['input[type="file"]'] = { files: [file] };
    assert.equal((await revisionPaperPreview(card, 3)).url, 'blob:converted-word');
    globalThis.fetch = async () => ({ ok: false, json: async () => ({ message: 'PDF conversion unavailable.' }) });
    await assert.rejects(revisionPaperPreview(card, 3), /PDF conversion unavailable/);
});

function previewFixture(editor) {
    const fixture = cardFixture(editor);
    const { card, nodes } = fixture;
    card.querySelectorAll = () => [];
    const handlers = {};
    const control = name => ({
        hidden: true, value: '', disabled: false,
        addEventListener(event, callback) { handlers[`${name}:${event}`] = callback; },
        removeAttribute(name) { delete this[name]; },
        hasAttribute(name) { return name in this; },
        setAttribute(name, value) { this[name] = value; },
        replaceChildren() {}, focus() {},
    });
    for (const name of ['panel', 'frame', 'open', 'close', 'refresh', 'status', 'error', 'stale', 'file']) {
        nodes[`[data-revision-preview-${name}]`] = control(name);
    }
    nodes['[data-revision-editor-content]'] = { hidden: false };
    nodes['[data-revision-submitted-panel]'] = { hidden: false };
    nodes['[data-revision-dialog]'] = control('dialog');
    nodes['[data-revision-preview-panel]'].hidden = true;
    nodes['[data-revision-preview-open]'].hidden = false;
    for (const name of ['zoom-value', 'zoom-out', 'zoom-in', 'fit-page', 'fit-width']) {
        nodes[`[data-revision-${name}]`] = control(name);
    }
    initializeRevisionPreviews({ querySelectorAll: () => [card] }, 3);
    return { ...fixture, handlers };
}

test('switching paper previews keeps the editor visible and mounted and clears replacement object URLs', async (t) => {
    const originalUrl = URL.createObjectURL;
    const originalRevoke = URL.revokeObjectURL;
    const revoked = [];
    URL.createObjectURL = () => 'blob:replacement';
    URL.revokeObjectURL = url => revoked.push(url);
    t.after(() => { URL.createObjectURL = originalUrl; URL.revokeObjectURL = originalRevoke; });
    const { nodes, handlers } = previewFixture({ preview: async () => '<p>Revision</p>' });
    const mounted = nodes['[data-revision-editor-frame]'];
    await handlers['refresh:click']();
    assert.equal(nodes['[data-revision-preview-frame]'].srcdoc, '<p>Revision</p>');
    assert.equal(nodes['[data-revision-editor-content]'].hidden, false);
    assert.equal(nodes['[data-revision-submitted-panel]'].hidden, true);
    assert.equal(nodes['[data-revision-preview-open]']['aria-pressed'], 'true');
    handlers['close:click']();
    assert.equal(nodes['[data-revision-submitted-panel]'].hidden, false);
    assert.equal(nodes['[data-revision-preview-close]']['aria-pressed'], 'true');
    assert.equal(nodes['[data-revision-editor-frame]'], mounted);
    assert.equal(nodes['[data-revision-editor-content]'].hidden, false);
    nodes['input[type="file"]'] = { files: [new File(['%PDF'], 'new.pdf')] };
    await handlers['refresh:click']();
    handlers['dialog:close']();
    assert.deepEqual(revoked, ['blob:replacement']);
});

test('closing a preview while generation is pending cannot reopen it when the response arrives', async () => {
    let resolve;
    const { nodes, handlers } = previewFixture({ preview: () => new Promise(callback => { resolve = callback; }) });
    const request = handlers['refresh:click']();
    handlers['close:click']();
    resolve('<p>Late preview</p>');
    await request;
    assert.equal(nodes['[data-revision-preview-panel]'].hidden, true);
    assert.equal(nodes['[data-revision-preview-frame]'].hidden, true);
    assert.equal(nodes['[data-revision-preview-frame]'].srcdoc, undefined);
});

test('the rendered revision workspace keeps replies with comments, compares compact papers and preserves edits in both themes', async () => {
    const rendered = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        $topic = (new App\Models\TopicProposal)->forceFill(['id' => 3, 'status' => 'revision_requested']);
        $version = (new App\Models\ProposalVersion)->forceFill(['id' => 4, 'topic_id' => 3]);
        $topic->setRelation('versions', collect([$version]));
        $file = (new App\Models\ProposalVersionFile)->forceFill(['id' => 5, 'proposal_version_id' => 4, 'document_type' => 'work_plan', 'position' => 0]);
        $revision = (new App\Models\TopicReviewFileRevision)->forceFill(['id' => 6, 'document_type' => 'work_plan', 'revision_note' => 'Update the schedule.', 'original_filename' => 'work-plan.pdf']);
        $revision->setRelation('file', $file);
        $revision->setRelation('annotations', collect([
            (new App\Models\ProposalFileAnnotation)->forceFill(['id' => 11, 'page_number' => 1, 'editor_target' => null, 'comment' => 'Move fieldwork to June.']),
            (new App\Models\ProposalFileAnnotation)->forceFill(['id' => 12, 'page_number' => 2, 'editor_target' => null, 'comment' => 'Explain the final evaluation.']),
        ]));
        $parameters = [
            'topic' => $topic, 'documentType' => 'work_plan', 'fileRevisions' => collect([$revision]), 'required' => true,
            'documentTypes' => collect(['work_plan']), 'commentResponseRows' => collect([
                ['key' => 'annotation_11', 'response' => '', 'remarks' => ''],
                ['key' => 'annotation_12', 'response' => '', 'remarks' => ''],
            ]),
        ];
        $multiple = view('components.proposal-revision-document', $parameters)->render();
        $revision->setRelation('annotations', $revision->annotations->take(1));
        $parameters['commentResponseRows'] = $parameters['commentResponseRows']->take(1);
        echo json_encode(['multiple' => $multiple, 'single' => view('components.proposal-revision-document', $parameters)->render()], JSON_THROW_ON_ERROR);
    `], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(rendered.status, 0, (rendered.stderr || rendered.stdout).slice(-2500));
    const markupTemplates = JSON.parse(rendered.stdout);
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const source = readFileSync(resolve('resources/js/revision-workspace.js'), 'utf8');
    const waitHelper = source.slice(source.indexOf('async function waitForRevisionOperation('), source.indexOf('function revisionEditorError('));
    const paper = '<html><style>body{margin:16px;background:#e2e8f0;width:816px}.paper{box-sizing:border-box;background:white;width:816px;min-height:1056px;padding:64px;font:16px Georgia;color:#111827}h1{font-size:20px;text-align:center}table{border-collapse:collapse;width:100%}td{padding:16px;border:1px solid #111827}</style><body><article class="paper"><h1>ATTACHMENT A: WORK PLAN</h1><p>Fruit Drop Detection</p><table><tr><td>Objective</td><td>Activity</td><td>Schedule</td></tr><tr><td>Observe fruit drop</td><td>June fieldwork</td><td>June 2027</td></tr></table></article></body></html>';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 768, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.route('**/annotations*', (route) => route.fulfill({ contentType: 'text/html', body: paper }));
                const markup = markupTemplates.multiple.replace(/(<iframe data-revision-editor-frame) (?:src|data-revision-editor-src)="[^"]*"/, '$1 src="about:blank"');
                await page.route('**/revision-layout-fixture', (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><style>${styles.css}</style><body><form data-revision-workspace="3">${markup}</form></body></html>` }));
                await page.goto('http://localhost/revision-layout-fixture');
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await page.waitForFunction(() => document.querySelector('[data-revision-editor-frame]').contentDocument?.readyState === 'complete');
                await page.evaluate(({ paperHtml, css }) => {
                    const frame = document.querySelector('[data-revision-editor-frame]');
                    frame.contentDocument.documentElement.className = document.documentElement.className + ' revision-embedded';
                    frame.contentDocument.head.innerHTML = `<style>${css}</style>`;
                    frame.contentDocument.body.innerHTML = '<style>body{margin:0;padding:24px;background:white;color:#1e293b}h2{font-size:18px}textarea{box-sizing:border-box;width:100%;height:140px;border:1px solid #cbd5e1;padding:16px;border-radius:8px}</style><div data-paper-editor><h2>Work Plan</h2><form data-paper-form><label for="activity">Activity</label><textarea id="activity">June fieldwork</textarea></form></div>';
                    frame.contentWindow.athenaRevisionEditor = {
                        topicId: 3, documentType: 'work_plan', focus() {},
                        modificationStates: () => ({ __document__: true }),
                        preview: async () => paperHtml.replace('June fieldwork', frame.contentDocument.querySelector('textarea').value),
                    };
                }, { paperHtml: paper, css: styles.css });
                await page.addScriptTag({ content: `const REVISION_OPERATION_TIMEOUT_MS = 150000;
                    ${waitHelper}
                    ${[proposalPreviewWorkspace, fitRevisionPaperPreview, revisionNoChangeResolution, revisionEditorForFrame, revisionPaperPreview, applyRevisionModificationStates, synchronizeRevisionNoChangeResponses, synchronizeSingleRevisionReply, initializeRevisionPanelResize, initializeRevisionPreviews, loadRevisionEditorFrame, initializeRevisionDialogs].map((fn) => fn.toString()).join('\n')}
                    window.dialogs = initializeRevisionDialogs(document.querySelector('form'), 3);
                    window.dialogs.open(document.querySelector('[data-revision-document]'));` });
                const dialog = page.locator('[data-revision-dialog]');
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await dialog.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `revision-editor-initial-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                assert.equal(await page.getByRole('button', { name: 'View full paper', exact: true }).count(), 0);
                const editorFrameBounds = await page.locator('[data-revision-editor-frame]').boundingBox();
                assert.equal(await page.locator('[data-revision-reference-toggle]').isVisible(), width < 768);
                assert.ok(editorFrameBounds.height > 700, `Editor should use the available height at ${width}px: ${editorFrameBounds.height}`);
                assert.ok(editorFrameBounds.y < 250, `Fields should begin near the top at ${width}px: ${editorFrameBounds.y}`);
                assert.equal(await page.frameLocator('[data-revision-editor-frame]').locator('textarea').evaluate((element) => getComputedStyle(element).fontSize), '16px');
                assert.equal(await page.frameLocator('[data-revision-editor-frame]').locator('label').evaluate((element) => getComputedStyle(element).fontSize), '16px');
                if (width < 1280) await page.locator('[data-revision-feedback-toggle]').click();
                const firstReply = page.locator('[data-revision-comment-body="11"] textarea').first();
                assert.equal(await firstReply.evaluate((element) => getComputedStyle(element).fontSize), '16px');
                await firstReply.fill('Moved fieldwork to June.');
                await page.locator('[data-revision-comment]').selectOption('12');
                await page.locator('[data-revision-comment-body="12"] textarea').first().fill('Added the final evaluation.');
                await page.locator('[data-revision-comment]').selectOption('11');
                assert.equal(await firstReply.inputValue(), 'Moved fieldwork to June.');
                await page.locator('[data-revision-feedback-dismiss]').click();
                if (width < 768) await page.locator('[data-revision-reference-toggle]').click();
                await page.locator('[data-revision-preview-open]').click();
                await page.waitForFunction(() => document.querySelector('[data-revision-preview-frame]').contentDocument?.body?.style.zoom);
                assert.equal(await page.locator('[data-revision-editor-content]').isVisible(), true);
                assert.equal(await page.locator('[data-revision-preview-open]').getAttribute('aria-pressed'), 'true');
                assert.equal(await page.evaluate(() => Number(document.querySelector('[data-revision-preview-frame]').contentDocument.body.style.zoom) < 1), true);
                await page.frameLocator('[data-revision-editor-frame]').locator('textarea').fill('July fieldwork');
                assert.equal(await page.locator('[data-revision-preview-stale]').isVisible(), true);
                await page.locator('[data-revision-preview-refresh]').click();
                await page.waitForFunction(() => document.querySelector('[data-revision-preview-frame]').contentDocument?.body?.textContent.includes('July fieldwork'));
                assert.equal(await page.locator('[data-revision-preview-stale]').isVisible(), false);
                if (width >= 768) {
                    const reference = await page.locator('[data-revision-reference]').boundingBox();
                    const editor = await page.locator('.revision-editor-panel').boundingBox();
                    assert.ok(reference.width < editor.width);
                }
                const editorValue = () => page.frameLocator('[data-revision-editor-frame]').locator('textarea').inputValue();
                await page.frameLocator('[data-revision-preview-frame]').locator('.paper').click();
                assert.equal(await page.locator('[data-revision-reference]').evaluate((element) => element.classList.contains('revision-reference-expanded')), true);
                await page.waitForFunction(() => {
                    const frame = document.querySelector('[data-revision-preview-frame]');
                    return frame.contentDocument.querySelector('.paper').getBoundingClientRect().height <= frame.clientHeight;
                });
                assert.equal(await page.locator('[data-revision-fit-page]').getAttribute('aria-pressed'), 'true');
                await page.locator('[data-revision-preview-frame]').hover();
                await page.keyboard.down('Control');
                await page.mouse.wheel(0, -120);
                await page.waitForFunction(() => document.querySelector('[data-revision-zoom-value]').textContent === '110%');
                await page.mouse.wheel(0, 120);
                await page.waitForFunction(() => document.querySelector('[data-revision-zoom-value]').textContent === '100%');
                await page.keyboard.up('Control');
                const fittedZoom = await page.frameLocator('[data-revision-preview-frame]').locator('body').evaluate((element) => Number(element.style.zoom));
                await page.getByRole('button', { name: 'Zoom out', exact: true }).click();
                assert.equal(await page.locator('[data-revision-zoom-value]').textContent(), '90%');
                assert.ok(await page.frameLocator('[data-revision-preview-frame]').locator('body').evaluate((element) => Number(element.style.zoom)) < fittedZoom);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                await page.getByRole('button', { name: 'Fit width', exact: true }).click();
                assert.equal(await page.locator('[data-revision-fit-width]').getAttribute('aria-pressed'), 'true');
                await page.getByRole('button', { name: 'Fit page', exact: true }).click();
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await dialog.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `revision-preview-fit-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                await page.getByRole('button', { name: 'Minimize preview', exact: true }).click();
                assert.equal(await editorValue(), 'July fieldwork');
                if (width < 768) await page.locator('[data-revision-reference-toggle]').click();
                await page.frameLocator('[data-revision-preview-frame]').locator('.paper').click();
                await page.keyboard.press('Escape');
                assert.equal(await dialog.evaluate((element) => element.open), true);
                assert.equal(await page.locator('[data-revision-reference]').evaluate((element) => element.classList.contains('revision-reference-expanded')), false);
                await page.locator('[data-revision-preview-close]').click();
                await page.frameLocator('[data-revision-pdf-frame]').locator('.paper').click();
                assert.equal(await page.locator('[data-revision-reference]').evaluate((element) => element.classList.contains('revision-reference-expanded')), true);
                await page.locator('[data-revision-pdf-frame]').hover();
                await page.keyboard.down('Control');
                await page.mouse.wheel(0, -120);
                await page.waitForFunction(() => document.querySelector('[data-revision-zoom-value]').textContent === '110%');
                await page.keyboard.up('Control');
                await page.locator('[data-revision-reference-dismiss]').click();
                assert.equal(await editorValue(), 'July fieldwork');
                await page.locator('[data-revision-feedback-toggle]').click();
                await page.getByLabel('Keep submitted paper', { exact: true }).check();
                await page.locator('[data-revision-no-change-explanation]').fill('The submitted plan already addresses this feedback.');
                assert.equal(await page.locator('[data-revision-editor-content]').evaluate((element) => element.inert), true);
                assert.equal(await page.locator('[data-comment-response-no-change]:checked').count(), 2);
                assert.equal(await firstReply.inputValue(), 'Moved fieldwork to June.');
                await page.getByLabel('Revise this paper', { exact: true }).check();
                assert.equal(await page.locator('[data-revision-editor-content]').evaluate((element) => element.inert), false);
                assert.equal(await editorValue(), 'July fieldwork');
                await page.locator('[data-revision-feedback-dismiss]').click();
                // A missing reply opens the correct comment and its feedback panel.
                await page.evaluate(() => {
                    const reply = document.querySelector('[name="feedback_responses[annotation_12][response]"]');
                    reply.value = '';
                    reply.reportValidity();
                });
                assert.equal(await page.locator('[data-revision-comment]').inputValue(), '12');
                assert.equal(await page.locator('[data-revision-feedback-panel]').isVisible(), true);
                assert.equal(await page.locator('[data-revision-comment-body="12"] details').count(), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, JSON.stringify(await page.evaluate(() => ({ width: window.innerWidth, scroll: document.documentElement.scrollWidth, overflow: [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > window.innerWidth + 1).map(el => [el.tagName, el.className, el.getBoundingClientRect().right]).slice(0, 8) }))));
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await page.locator('[data-revision-comment]').selectOption('11');
                    if (width >= 768) {
                        await page.locator('[data-revision-preview-open]').click();
                        await page.waitForFunction(() => document.querySelector('[data-revision-preview-frame]').contentDocument?.body?.style.zoom);
                    }
                    await dialog.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `revision-workspace-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.route('**/annotations*', (route) => route.fulfill({ contentType: 'text/html', body: paper }));
                const markup = markupTemplates.single.replace(/(<iframe data-revision-editor-frame) (?:src|data-revision-editor-src)="[^"]*"/, '$1 src="about:blank"');
                await page.route('**/single-feedback-fixture', (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><style>${styles.css}</style><body><form data-revision-workspace="3">${markup}</form></body></html>` }));
                await page.goto('http://localhost/single-feedback-fixture');
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await page.addScriptTag({ content: `const REVISION_OPERATION_TIMEOUT_MS = 150000;
                    ${waitHelper}
                    ${[proposalPreviewWorkspace, fitRevisionPaperPreview, revisionNoChangeResolution, revisionEditorForFrame, revisionPaperPreview, applyRevisionModificationStates, synchronizeRevisionNoChangeResponses, synchronizeSingleRevisionReply, initializeRevisionPanelResize, initializeRevisionPreviews, loadRevisionEditorFrame, initializeRevisionDialogs].map((fn) => fn.toString()).join('\n')}
                    window.dialogs = initializeRevisionDialogs(document.querySelector('form'), 3);
                    window.dialogs.open(document.querySelector('[data-revision-document]'));` });
                if (width < 1280) await page.locator('[data-revision-feedback-toggle]').click();
                const panel = page.locator('[data-revision-feedback-panel]');
                const reply = panel.locator('[data-revision-response-document]');
                assert.equal(await panel.locator('textarea:visible').count(), 1);
                assert.equal(await panel.locator('[data-revision-comment]').isVisible(), false);
                assert.equal(await panel.locator('details, select:visible, input[type="number"]:visible').count(), 0);
                const comment = panel.locator('.revision-reviewer-comment');
                assert.equal(await comment.evaluate((element) => getComputedStyle(element).fontSize), '18px');
                assert.ok((await reply.boundingBox()).y > (await comment.boundingBox()).y);
                assert.ok((await panel.locator('[data-revision-resolution-panel]').boundingBox()).y > (await reply.boundingBox()).y);
                await page.getByLabel('Keep submitted paper', { exact: true }).check();
                await reply.fill('The submitted plan already addresses this feedback.');
                assert.equal(await panel.locator('[data-revision-no-change-explanation]').inputValue(), 'The submitted plan already addresses this feedback.');
                assert.equal(await page.locator('[data-revision-document-state]').getAttribute('data-addressed'), 'true');
                assert.equal(await reply.getAttribute('maxlength'), '1000');
                assert.equal(await panel.locator('textarea:visible').count(), 1);
                await panel.getByRole('button', { name: 'Close feedback panel', exact: true }).click();
                await page.locator('[data-revision-feedback-toggle]').click();
                assert.equal(await reply.inputValue(), 'The submitted plan already addresses this feedback.');
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await panel.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `revision-feedback-single-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                await page.getByLabel('Revise this paper', { exact: true }).check();
                assert.equal(await reply.getAttribute('maxlength'), '5000');
                assert.equal(await reply.inputValue(), 'The submitted plan already addresses this feedback.');
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
