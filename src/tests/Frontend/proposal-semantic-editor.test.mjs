import assert from 'node:assert/strict';
import test from 'node:test';
import {
    mirrorSemanticEditorHtml,
    orderedCitationSourceIds,
    proposalCitationField,
    proposalCitationFieldIds,
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
