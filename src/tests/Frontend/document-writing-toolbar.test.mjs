import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { documentWritingToolbar } from '../../resources/js/document-writing-toolbar.js';

test('hiding and reopening document tools moves keyboard focus to the visible control', () => {
    const state = documentWritingToolbar();
    const focused = [];
    state.$nextTick = callback => callback();
    state.$el = {
        querySelector: selector => ({ focus: () => focused.push(selector) }),
    };

    state.closeWritingToolbar();
    assert.equal(state.writingToolbarOpen, false);
    state.showWritingToolbar();
    assert.equal(state.writingToolbarOpen, true);
    assert.deepEqual(focused, ['[data-writing-toolbar-open]', '[data-writing-toolbar-close]']);
});

test('formatting follows the focused semantic field and ignores toolbar focus', () => {
    const state = documentWritingToolbar();
    const commands = [];
    const field = {
        isConnected: true,
        matches: selector => selector === 'textarea[data-semantic-editor]',
        _semanticApi: { execute: command => commands.push(command) },
    };
    state.focusWritingField({ target: {
        closest: selector => selector === '.proposal-semantic-field' ? { previousElementSibling: field } : null,
        matches: () => false,
    } });
    assert.equal(state.activeWritingField, field);

    state.focusWritingField({ target: { closest: () => ({}), matches: () => false } });
    state.formatWriting('bold');
    state.formatWriting('italic');
    state.formatWriting('underline');
    state.formatWriting('insertOrderedList');
    state.formatWriting('insertUnorderedList');
    state.formatWriting('undo');
    state.formatWriting('redo');
    state.formatWriting('removeFormat');
    state.formatWriting('insertHTML');
    assert.deepEqual(commands, ['bold', 'italic', 'underline', 'insertOrderedList', 'insertUnorderedList', 'undo', 'redo', 'removeFormat']);

    field.isConnected = false;
    state.formatWriting('bold');
    assert.equal(commands.length, 8);

    state.focusWritingField({ target: { closest: () => null, matches: () => true } });
    assert.equal(state.activeWritingField, null);
    state.formatWriting('bold');
    assert.equal(commands.length, 8);
});

test('shared document controls format the selected text without adding a second editor toolbar', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const editors = app.slice(app.indexOf('function sanitizedSemanticHtml('), app.indexOf('window.insertProposalCitationMarker ='));
    const semanticHelpers = readFileSync(new URL('../../resources/js/proposal-semantic-editor.js', import.meta.url), 'utf8').replaceAll('export ', '');
    const toolbar = readFileSync(new URL('../../resources/js/document-writing-toolbar.js', import.meta.url), 'utf8').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<style>.hidden { display: none; }</style>
            <form data-document-writing-workspace x-data="documentWritingToolbar" @focusin="focusWritingField($event)">
                <div data-proposal-workspace-toolbar>
                    <button type="button" data-writing-toolbar-close x-show="writingToolbarOpen" @click="closeWritingToolbar()">Hide tools</button>
                    <button type="button" data-writing-toolbar-open x-show="!writingToolbarOpen" @click="showWritingToolbar()">Show tools</button>
                    <div x-show="writingToolbarOpen"><button type="button" id="bold" @mousedown.prevent @click="formatWriting('bold')">Bold</button></div>
                </div>
                <label for="accomplishments">Other accomplishments</label>
                <textarea id="accomplishments" data-semantic-editor>Quarterly results completed</textarea>
                <input id="reviewer" aria-label="Reviewer name">
            </form>
            <label for="standalone">Other form</label><textarea id="standalone" data-semantic-editor>Unchanged editor</textarea>`);
        await page.addScriptTag({ content: `${semanticHelpers}\n${toolbar}\n${editors}
            initializeSemanticEditors();
            document.addEventListener('alpine:init', () => Alpine.data('documentWritingToolbar', documentWritingToolbar));
        ` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
        await page.getByRole('button', { name: 'Hide tools', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('[data-writing-toolbar-open]') === document.activeElement);
        await page.getByRole('button', { name: 'Show tools', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('[data-writing-toolbar-close]') === document.activeElement);
        assert.equal(await page.locator('[data-document-writing-workspace] [aria-label="Text formatting"]').count(), 0);
        assert.equal(await page.locator('[aria-label="Text formatting"]').count(), 1);
        await page.getByRole('textbox', { name: 'Other accomplishments' }).focus();
        await page.evaluate(() => {
            const editor = document.querySelector('#accomplishments')._semanticEditor;
            const range = document.createRange();
            range.setStart(editor.firstChild, 10);
            range.setEnd(editor.firstChild, 17);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            editor.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
        });
        await page.getByRole('button', { name: 'Bold', exact: true }).first().focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('#accomplishments').inputValue(), 'Quarterly <strong>results</strong> completed');
        await page.getByRole('textbox', { name: 'Reviewer name' }).focus();
        await page.getByRole('button', { name: 'Bold', exact: true }).first().click();
        assert.equal(await page.locator('#accomplishments').inputValue(), 'Quarterly <strong>results</strong> completed');
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
