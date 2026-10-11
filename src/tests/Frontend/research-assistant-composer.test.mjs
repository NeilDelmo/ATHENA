import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
function storeMethod(name) {
    const match = source.match(new RegExp(`    ${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing assistant method: ${name}`);
    return new Function(match[1], match[2]);
}

test('project chips enable only available projects and discard stale document choices', () => {
    const store = {
        contextOptions: [{ id: 12, label: 'Water research' }, { id: 24, label: 'Student learning' }],
        documentRequestSequence: 5,
        documentPickerOpen: true,
        documentOptions: [{ token: 'old-project-document' }],
        selectedDocumentToken: 'old-project-document',
        documentLoading: true,
        documentError: 'Old error',
        closeDocumentPicker: storeMethod('closeDocumentPicker'),
        selectProjectContext: storeMethod('selectProjectContext'),
    };
    store.selectProjectContext('24');
    assert.equal(store.selectedContextId, 24);
    assert.equal(store.contextEnabled, true);
    assert.equal(store.documentRequestSequence, 6, 'An earlier project document request must be invalidated');
    assert.equal(store.documentPickerOpen, false);
    assert.deepEqual(store.documentOptions, []);
    assert.equal(store.selectedDocumentToken, '');
    assert.equal(store.documentLoading, false);
    assert.equal(store.documentError, '');

    store.selectProjectContext(null);
    assert.equal(store.contextEnabled, false);
    assert.equal(store.selectedContextId, 0);
    store.selectProjectContext(999);
    assert.equal(store.contextEnabled, false, 'Unavailable project IDs cannot become active');
});

test('project search matches the end of a full title and the displayed metadata', () => {
    const view = readFileSync(new URL('../../resources/views/components/research-assistant-project-picker.blade.php', import.meta.url), 'utf8');
    const data = view.match(/x-data="([^"]+)"/)[1];
    const contexts = [
        { id: 1, label: 'Mental Health Chatbot for Stress Management Among College Students in Rural Communities', meta: 'Health Sciences · 2026-2027 · v1' },
        { id: 2, label: 'Digital learning', meta: 'Education · 2025-2026 · v2' },
    ];
    const picker = new Function('$store', `return (${data})`)({ researchAssistant: { contextOptions: contexts } });
    for (const query of ['Rural Communities', 'health sciences', ' 2026-2027 ']) {
        picker.query = query;
        assert.deepEqual(picker.projects.map(project => project.id), [1]);
    }
    picker.query = 'unmatched project';
    assert.deepEqual(picker.projects, []);
    picker.query = '';
    assert.equal(picker.projects.length, 2);
});

test('removing the project chip omits that project from subsequent requests while keeping paper context', () => {
    const store = {
        contextEnabled: true, selectedContextId: 12, paperContext: { paperSlug: 'detailed-proposal' },
        proposalDraftId: 4, activePaperField: null, formContext: () => null,
        contextPayload: storeMethod('contextPayload'),
    };
    assert.equal(store.contextPayload().topic_id, 12);
    store.contextEnabled = false;
    store.selectedContextId = 0;
    assert.deepEqual(store.contextPayload(), { paper_slug: 'detailed-proposal', proposal_draft_id: 4, workflow_scope: 'proposal' });
});

test('the shared composer sends on Enter and preserves multiline and IME input', () => {
    let sends = 0;
    let prevented = 0;
    const store = { send: () => sends++, handleComposerKeydown: storeMethod('handleComposerKeydown') };
    const event = { key: 'Enter', preventDefault: () => prevented++ };
    store.handleComposerKeydown({ ...event, shiftKey: true });
    store.handleComposerKeydown({ ...event, isComposing: true });
    store.handleComposerKeydown({ ...event, defaultPrevented: true });
    assert.equal(sends, 0);
    store.handleComposerKeydown(event);
    assert.equal(sends, 1);
    assert.equal(prevented, 1);
});

test('source counts safely handle a newly sent user message without source metadata', () => {
    for (const surface of ['workspace', 'drawer']) {
        const view = readFileSync(new URL(`../../resources/views/components/research-assistant-${surface}.blade.php`, import.meta.url), 'utf8');
        const expression = view.match(/x-text="(message\.sources[^"]+)"/)[1];
        const count = new Function('message', `return ${expression}`);
        assert.equal(count({ role: 'user', content: 'My question' }), 0);
        assert.equal(count({ role: 'assistant', sources: [{ reference: '[1]' }] }), 1);
    }
});

test('Escape in history returns focus to the composer without closing its workspace', () => {
    const originalWindow = globalThis.window;
    const originalDocument = globalThis.document;
    let focused = '';
    globalThis.window = { clearTimeout() {}, setTimeout: callback => callback() };
    globalThis.document = { getElementById: id => ({ focus: () => { focused = id; } }) };
    try {
        const store = { historySearchOpen: true, historySearchQuery: 'water', history: [], workspaceOpen: true, closeHistorySearch: storeMethod('closeHistorySearch') };
        store.closeHistorySearch();
        assert.equal(store.historySearchOpen, false);
        assert.equal(store.historySearchQuery, '');
        assert.equal(store.workspaceOpen, true);
        assert.equal(focused, 'research-assistant-message');
    } finally {
        globalThis.window = originalWindow;
        globalThis.document = originalDocument;
    }
});
