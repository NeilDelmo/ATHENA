export function isEmbeddedRevisionEditor() {
    return Boolean(document.querySelector('[data-revision-embedded] [data-revision-editor-context]'));
}

export function embeddedRevisionFileSaved(payload) {
    const root = document.querySelector('[data-paper-editor]');
    const version = root?.querySelector('[name="document_version"]');
    if (version && Number.isInteger(payload.document_version)) version.value = String(payload.document_version);
}
