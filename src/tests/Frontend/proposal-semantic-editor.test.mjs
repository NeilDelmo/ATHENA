import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { resolve } from 'node:path';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import {
    mirrorSemanticEditorHtml,
    notifySemanticEditorInput,
    orderedCitationSourceIds,
    proposalCitationField,
    proposalCitationFieldIds,
    proposalCitationLocator,
    proposalCitationSelection,
    synchronizeCitationMarkerLabels,
} from '../../resources/js/proposal-semantic-editor.js';

test('detailed proposal writing areas stay open and stable while typing, pasting, and reopening saved text', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const helpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replaceAll('export ', '');
    const initialization = app.slice(app.indexOf('function sanitizedSemanticHtml('), app.indexOf('\nwindow.insertProposalCitationMarker'));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}</style><body data-app-shell>
                    <form data-detailed-proposal-autosave-form class="mx-auto max-w-3xl p-5">
                        <textarea id="rationale" rows="14" data-semantic-editor></textarea>
                        <div id="next-section">Next section</div>
                        <textarea id="general-objective" rows="4" data-semantic-editor></textarea>
                        <textarea id="methodology-research_design" rows="7" data-semantic-editor>&lt;p&gt;Saved draft.&lt;/p&gt;</textarea>
                    </form>
                    <textarea id="terminal" rows="9" data-semantic-editor data-semantic-editor-size="large"></textarea>
                </body></html>`);
                await page.addScriptTag({ content: `${helpers}\n${initialization}\ninitializeSemanticEditors();` });
                const editor = page.locator('[data-detailed-proposal-autosave-form] .semantic-rich-text-editor').first();
                const initialHeight = (await editor.boundingBox()).height;
                const nextSectionTop = (await page.locator('#next-section').boundingBox()).y;
                assert.equal(initialHeight, 360);
                assert.equal(await page.locator('#general-objective').evaluate((field) => field._semanticEditor.offsetHeight), 128);
                assert.equal(await page.locator('#methodology-research_design').evaluate((field) => field._semanticEditor.textContent), 'Saved draft.');
                assert.equal(await page.locator('#terminal').evaluate((field) => field._semanticEditor.style.height), '');

                await editor.fill('A new rationale.');
                await editor.press('End');
                await editor.press('Enter');
                await page.keyboard.insertText('Another paragraph.');
                await editor.evaluate((element) => {
                    const clipboard = new DataTransfer();
                    clipboard.setData('text/html', '<p>Long pasted paragraph.</p>'.repeat(40));
                    element.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true }));
                });
                assert.equal((await editor.boundingBox()).height, initialHeight);
                assert.equal((await page.locator('#next-section').boundingBox()).y, nextSectionTop);
                assert.ok(await editor.evaluate((element) => element.scrollHeight > element.clientHeight));
                assert.match(await page.locator('#rationale').inputValue(), /Long pasted paragraph/);

                await editor.press('ControlOrMeta+End');
                await editor.press('Enter');
                await page.keyboard.insertText('Final typed sentence.');
                assert.ok(await editor.evaluate((element) => element.scrollTop > 0));
                await page.locator('#next-section').click();
                assert.equal((await editor.boundingBox()).height, initialHeight);
                assert.match(await page.locator('#rationale').inputValue(), /Final typed sentence/);

                await page.locator('#rationale').evaluate((field) => {
                    field.value = '<p>Reopened saved paragraph.</p>'.repeat(40);
                    field._syncSemanticEditor();
                });
                assert.equal((await editor.boundingBox()).height, initialHeight);
                assert.equal((await page.locator('#next-section').boundingBox()).y, nextSectionTop);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('numbered and bulleted lists remain visible in the live semantic editor', () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const styles = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

    assert.match(app, /semantic-rich-text-editor min-h-/);
    assert.match(styles, /\.semantic-rich-text-editor ol \{\s*list-style: decimal outside;/);
    assert.match(styles, /\.semantic-rich-text-editor ul \{\s*list-style: disc outside;/);
    assert.match(styles, /\.semantic-rich-text-editor li \{\s*display: list-item;/);
});

test('semantic editor input updates Alpine without bubbling a duplicate form input', () => {
    let receivedEvent = null;
    const textarea = {
        dispatchEvent(event) {
            receivedEvent = event;
        },
    };

    const event = notifySemanticEditorInput(textarea);

    assert.equal(receivedEvent, event);
    assert.equal(event.type, 'input');
    assert.equal(event.bubbles, false);
});

test('programmatic proposal values are mirrored into the visible semantic editor', () => {
    const textarea = { value: '<p>Old reference</p>' };
    const editor = { innerHTML: '<p>Old reference</p>' };

    assert.equal(mirrorSemanticEditorHtml(textarea, editor, '<p>[1] New reference</p>'), true);
    assert.equal(textarea.value, '<p>[1] New reference</p>');
    assert.equal(editor.innerHTML, '<p>[1] New reference</p>');
    assert.equal(mirrorSemanticEditorHtml(textarea, editor, '<p>[1] New reference</p>'), false);
});

test('manual typing updates the saved value without replacing the active editor DOM', () => {
    const textarea = { value: '<p>Hello</p>' };
    let editorWrites = 0;
    const editor = {
        get innerHTML() {
            return '<p>Hello&nbsp;</p>';
        },
        set innerHTML(value) {
            editorWrites++;
        },
    };

    assert.equal(mirrorSemanticEditorHtml(textarea, editor, '<p>Hello\u00a0</p>', { preserveEditorDom: true }), true);
    assert.equal(textarea.value, '<p>Hello\u00a0</p>');
    assert.equal(editorWrites, 0);
});

test('citation marker synchronization reports and applies only real label changes', () => {
    const marker = {
        textContent: ' [1]',
        getAttribute: (name) => name === 'data-proposal-citation' ? '42' : null,
    };

    assert.equal(synchronizeCitationMarkerLabels([marker], { 42: 2 }), true);
    assert.equal(marker.textContent, ' [2]');
    assert.equal(synchronizeCitationMarkerLabels([marker], { 42: 2 }), false);
});

test('citation renumbering retains a readable locator and treats it as plain text', () => {
    const marker = {
        textContent: ' [1, p. 6]',
        getAttribute: (name) => name === 'data-proposal-citation' ? '42' : '  p. 6\n  ',
    };
    assert.equal(synchronizeCitationMarkerLabels([marker], { 42: 3 }), true);
    assert.equal(marker.textContent, ' [3, p. 6]');
    assert.equal(synchronizeCitationMarkerLabels([marker], { 42: 3 }), false);
    assert.equal(proposalCitationLocator('p.\u0000 6\n'), 'p. 6');
    assert.equal(proposalCitationLocator('x'.repeat(140)).length, 100);
    assert.equal(proposalCitationLocator('<img src=x>'), '<img src=x>');
});

test('citation targets accept a caret or selection only inside eligible narrative editors', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<div id="editor" contenteditable><p>Before selected text. After.</p><span data-proposal-citation="7"> [1]</span></div><p id="outside">Outside.</p>');
        const helpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replaceAll('export ', '');
        await page.addScriptTag({ content: helpers });
        const result = await page.evaluate(() => {
            const editor = document.getElementById('editor');
            const text = editor.querySelector('p').firstChild;
            const range = document.createRange();
            range.setStart(text, 7); range.setEnd(text, 20);
            const selected = proposalCitationSelection('rationale', editor, range);
            range.collapse(false);
            const caret = proposalCitationSelection('rationale', editor, range);
            const metadata = proposalCitationSelection('project-title', editor, range);
            const references = proposalCitationSelection('references', editor, range);
            range.setEnd(document.getElementById('outside').firstChild, 3);
            const crossing = proposalCitationSelection('rationale', editor, range);
            range.selectNodeContents(editor.querySelector('[data-proposal-citation]'));
            range.collapse(false);
            const marker = proposalCitationSelection('rationale', editor, range);
            range.selectNodeContents(text); range.collapse(false);
            editor.remove();
            const detached = proposalCitationSelection('rationale', editor, range);
            return { selected, caret, metadata, references, crossing, marker, detached };
        });
        assert.equal(result.selected.selectedText, 'selected text');
        assert.equal(result.selected.collapsed, false);
        assert.equal(result.selected.fieldKey, 'rationale');
        assert.equal(result.selected.sectionLabel, 'VIII. Rationale');
        assert.match(result.selected.contextText, /Before selected text\. After\./);
        assert.equal(result.caret.selectedText, '');
        assert.equal(result.caret.collapsed, true);
        assert.deepEqual([result.metadata, result.references, result.crossing, result.marker, result.detached], [null, null, null, null, null]);
    } finally {
        await browser.close();
    }
});

test('proposal citation fields resolve editor ids and persisted keys', () => {
    assert.equal(proposalCitationField('introduction')?.key, 'introduction');
    assert.equal(proposalCitationField('introduction')?.label, 'XI. Review of Related Literature — opening paragraphs');
    assert.equal(proposalCitationField('related-literature')?.label, 'XI. Review of Related Literature');
    assert.equal(proposalCitationField('methodology.research_design')?.id, 'methodology-research_design');
    assert.equal(proposalCitationField('references'), null);
    assert.ok(proposalCitationFieldIds().includes('related-literature'));
});

test('reference numbers follow citation markers across proposal sections and ignore reference-only records', () => {
    const citations = [
        { source_link_id: 2, field: 'references' },
        { source_link_id: 3, field: 'introduction' },
        { source_link_id: 1, field: 'related_literature' },
        { source_link_id: 4, field: 'methodology.research_design' },
    ];

    assert.deepEqual(orderedCitationSourceIds([1, 3, 1, 4], citations), [1, 3, 4]);
    assert.deepEqual(orderedCitationSourceIds([], citations), [3, 1, 4]);
});
