import assert from 'node:assert/strict';
import test from 'node:test';
import {
    assistantDocumentAction,
    assistantDocumentQuery,
    assistantWorkflowScope,
    defaultAssistantContextId,
    redactAssistantText,
} from '../../resources/js/research-assistant-context.js';

test('the active record remains selected even when it is absent from recent contexts', () => {
    const contexts = [
        { id: 12, label: 'Most recent proposal' },
        { id: 11, label: 'Another recent proposal' },
    ];

    assert.equal(defaultAssistantContextId(contexts, 3), 3);
});

test('the first recent context is used only when no record is currently active', () => {
    const contexts = [{ id: 12, label: 'Most recent proposal' }];

    assert.equal(defaultAssistantContextId(contexts, null), 12);
    assert.equal(defaultAssistantContextId([], null), 0);
});

test('workflow scope follows the visible topic section', () => {
    assert.equal(assistantWorkflowScope('#proposal-review'), 'review');
    assert.equal(assistantWorkflowScope('#file-review-card-12'), 'review');
    assert.equal(assistantWorkflowScope('#notice-to-proceed'), 'notice');
    assert.equal(assistantWorkflowScope('#project-monitoring'), 'monitoring');
    assert.equal(assistantWorkflowScope('#proposal-details'), 'details');
});

test('unsaved NTP dates and resolution values remain readable while contact details are redacted', () => {
    assert.equal(redactAssistantText('LREC-2026-015 on 2026-09-01'), 'LREC-2026-015 on 2026-09-01');
    assert.equal(redactAssistantText('Call 0917 123 4567'), 'Call [redacted phone]');
    assert.equal(redactAssistantText('Email person@example.edu'), 'Email [redacted email]');
});

test('document analysis sends an action only for an explicitly selected document', () => {
    assert.deepEqual(assistantDocumentQuery({ topic_id: 12, proposal_draft_id: 9 }), {
        topic_id: 12,
        proposal_draft_id: 9,
    });
    assert.deepEqual(assistantDocumentQuery({ topic_id: 0 }), {});
    assert.equal(assistantDocumentAction(''), null);
    assert.deepEqual(assistantDocumentAction('encrypted-reference'), {
        type: 'analyze_document',
        document_token: 'encrypted-reference',
    });
});
