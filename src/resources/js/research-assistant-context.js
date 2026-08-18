export function defaultAssistantContextId(contexts, activeContextId) {
    const normalizedActiveContextId = Number(activeContextId || 0);

    if (normalizedActiveContextId > 0) return normalizedActiveContextId;

    return Number(contexts?.[0]?.id || 0);
}

export function assistantWorkflowScope(hash) {
    if (['#proposal-review', '#submit-revision'].includes(hash)
        || String(hash || '').startsWith('#file-review-card-')) {
        return 'review';
    }

    if (hash === '#notice-to-proceed') return 'notice';
    if (hash === '#project-monitoring') return 'monitoring';

    return 'details';
}

export function redactAssistantText(value) {
    return String(value || '').replace(
        /[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/gi,
        '[redacted email]',
    ).replace(
        /(^|\D)(?:\+63|0)9(?:[\s().-]*\d){9}(?=\D|$)/g,
        '$1[redacted phone]',
    );
}

export function assistantDocumentQuery(context) {
    const query = {};
    const topicId = Number(context?.topic_id || 0);
    const proposalDraftId = Number(context?.proposal_draft_id || 0);

    if (topicId > 0) query.topic_id = topicId;
    if (proposalDraftId > 0) query.proposal_draft_id = proposalDraftId;

    return query;
}

export function assistantDocumentAction(documentToken) {
    const token = String(documentToken || '').trim();

    return token ? {
        type: 'analyze_document',
        document_token: token,
    } : null;
}
