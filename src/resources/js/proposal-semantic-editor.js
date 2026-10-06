export const proposalCitationFields = Object.freeze([
    { id: 'executive-brief', key: 'executive_brief', label: 'VII. Executive Brief' },
    { id: 'rationale', key: 'rationale', label: 'VIII. Rationale' },
    { id: 'general-objective', key: 'general_objective', label: 'IX. General Objective' },
    { id: 'introduction', key: 'introduction', label: 'XI. Review of Related Literature — opening paragraphs' },
    { id: 'related-literature', key: 'related_literature', label: 'XI. Review of Related Literature' },
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

export function proposalCitationSelection(fieldId, editor, range) {
    const field = proposalCitationField(fieldId);

    if (!field || !editor || editor.isConnected === false || !range
        || !editor.contains(range.startContainer) || !editor.contains(range.endContainer)) return null;

    const containingElement = (node) => node?.nodeType === 1 ? node : node?.parentElement;
    if ([range.startContainer, range.endContainer].some((node) => (
        containingElement(node)?.closest('[data-proposal-citation]')
    ))) return null;

    const selectedText = range.collapsed ? '' : range.toString().trim();
    if (!range.collapsed && !selectedText) return null;

    try {
        const before = range.cloneRange();
        before.selectNodeContents(editor);
        before.setEnd(range.startContainer, range.startOffset);
        const after = range.cloneRange();
        after.selectNodeContents(editor);
        after.setStart(range.endContainer, range.endOffset);
        const contextText = `${before.toString().slice(-400)}${selectedText.slice(0, 1200)}${after.toString().slice(0, 400)}`
            .replace(/\s+/g, ' ').trim();

        return {
            fieldId: field.id,
            fieldKey: field.key,
            sectionLabel: field.label,
            selectedText,
            collapsed: range.collapsed,
            contextText,
        };
    } catch {
        return null;
    }
}

export function proposalCitationLocator(value) {
    return String(value || '').replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 100);
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

        const locator = proposalCitationLocator(marker.getAttribute('data-proposal-locator'));
        const label = ` [${referenceNumber}${locator ? `, ${locator}` : ''}]`;

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
