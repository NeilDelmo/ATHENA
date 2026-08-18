import assert from 'node:assert/strict';
import test from 'node:test';
import {
    mirrorSemanticEditorHtml,
    orderedCitationSourceIds,
    synchronizeCitationMarkerLabels,
} from '../../resources/js/proposal-semantic-editor.js';

test('programmatic proposal values are mirrored into the visible semantic editor', () => {
    const textarea = { value: '<p>Old reference</p>' };
    const editor = { innerHTML: '<p>Old reference</p>' };

    assert.equal(mirrorSemanticEditorHtml(textarea, editor, '<p>[1] New reference</p>'), true);
    assert.equal(textarea.value, '<p>[1] New reference</p>');
    assert.equal(editor.innerHTML, '<p>[1] New reference</p>');
    assert.equal(mirrorSemanticEditorHtml(textarea, editor, '<p>[1] New reference</p>'), false);
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

test('reference numbers follow actual RRL citation markers and ignore saved-only sources', () => {
    const citations = [
        { source_link_id: 2, field: 'references' },
        { source_link_id: 3, field: 'related_literature' },
        { source_link_id: 1, field: 'related_literature' },
    ];

    assert.deepEqual(orderedCitationSourceIds([1, 3, 1], citations), [1, 3]);
    assert.deepEqual(orderedCitationSourceIds([], citations), [3, 1]);
});
