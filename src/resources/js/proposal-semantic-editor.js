export function mirrorSemanticEditorHtml(textarea, editor, value) {
    const html = String(value ?? '');
    const changed = textarea.value !== html || editor.innerHTML !== html;

    textarea.value = html;

    if (editor.innerHTML !== html) editor.innerHTML = html;

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
