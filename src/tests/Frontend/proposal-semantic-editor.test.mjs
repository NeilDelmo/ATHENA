import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    mirrorSemanticEditorHtml,
    notifySemanticEditorInput,
    orderedCitationSourceIds,
    proposalCitationField,
    proposalCitationFieldIds,
    synchronizeCitationMarkerLabels,
} from '../../resources/js/proposal-semantic-editor.js';

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
        getAttribute: () => '42',
    };

    assert.equal(synchronizeCitationMarkerLabels([marker], { 42: 2 }), true);
    assert.equal(marker.textContent, ' [2]');
    assert.equal(synchronizeCitationMarkerLabels([marker], { 42: 2 }), false);
});

test('proposal citation fields resolve editor ids and persisted keys', () => {
    assert.equal(proposalCitationField('introduction')?.key, 'introduction');
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
