export const proposalCitationFields = Object.freeze([
    { id: 'executive-brief', key: 'executive_brief', label: 'VII. Executive Brief' },
    { id: 'rationale', key: 'rationale', label: 'VIII. Rationale' },
    { id: 'general-objective', key: 'general_objective', label: 'IX. General Objective' },
    { id: 'introduction', key: 'introduction', label: 'XI. Introduction' },
    { id: 'related-literature', key: 'related_literature', label: 'XI. Related Studies and Literature' },
    { id: 'methodology-research_design', key: 'methodology.research_design', label: 'XII. Research Design' },
    { id: 'methodology-specific_methods', key: 'methodology.specific_methods', label: 'XII. Specific Methods' },
    { id: 'methodology-data_analysis', key: 'methodology.data_analysis', label: 'XII. Data Analysis' },
]);

export function proposalCitationField(value) {
    const normalized = String(value || '').trim();

    return proposalCitationFields.find((field) => field.id === normalized || field.key === normalized) || null;
}

export function proposalCitationFieldIds() {
    return proposalCitationFields.map((field) => field.id);
}

export function notifySemanticEditorInput(textarea) {
    const event = new Event('input');

    textarea.dispatchEvent(event);

    return event;
}

export function mirrorSemanticEditorHtml(textarea, editor, value, { preserveEditorDom = false } = {}) {
    const html = String(value ?? '');
    const changed = textarea.value !== html || editor.innerHTML !== html;

    textarea.value = html;

    if (!preserveEditorDom && editor.innerHTML !== html) editor.innerHTML = html;

    return changed;
}

export function synchronizeCitationMarkerLabels(markers, referenceNumbers = {}) {
    let changed = false;

    [...markers].forEach((marker) => {
        const sourceLinkId = String(marker.getAttribute('data-proposal-citation') || '');
        const referenceNumber = referenceNumbers[sourceLinkId];

        if (!Number.isInteger(referenceNumber) || referenceNumber < 1) return;

        const label = ` [${referenceNumber}]`;

        if (marker.textContent === label) return;

        marker.textContent = label;
        changed = true;
    });

    return changed;
}

export function orderedCitationSourceIds(markerSourceIds = [], citations = []) {
    const normalizedMarkerIds = [...markerSourceIds]
        .map((sourceLinkId) => Number(sourceLinkId))
        .filter((sourceLinkId) => Number.isInteger(sourceLinkId) && sourceLinkId > 0);

    const sourceIds = normalizedMarkerIds.length > 0
        ? normalizedMarkerIds
        : citations
            .filter((citation) => proposalCitationField(citation?.field))
            .map((citation) => Number(citation.source_link_id))
            .filter((sourceLinkId) => Number.isInteger(sourceLinkId) && sourceLinkId > 0);

    return [...new Set(sourceIds)];
}
