import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
function storeMethod(name) {
    const match = source.match(new RegExp(`    ${name}\\(([^)]*)\\) \\{([\\s\\S]*?)\\n    \\},`));
    assert.ok(match, `Missing assistant method: ${name}`);
    return new Function('currentPageReportContext', `return function(${match[1]}) {${match[2]}}`)(() => null);
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

test('closed project menus defer their options and retain search selection and keyboard focus', async () => {
    const view = readFileSync(new URL('../../resources/views/components/research-assistant-project-picker.blade.php', import.meta.url), 'utf8');
    const picker = (id) => view.replace(/^@props\([^\n]+\)\s*/m, '')
        .replaceAll("{{ $id === 'drawer' ? 'drawerOpen' : 'workspaceOpen' }}", id === 'drawer' ? 'drawerOpen' : 'workspaceOpen')
        .replaceAll('{{ $id }}', id);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent(`<style>[x-cloak]{display:none!important}</style><main x-data>${picker('drawer')}${picker('workspace')}</main>`);
        await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
            drawerOpen: true, workspaceOpen: true, contextEnabled: false, selectedContextId: 0,
            contextOptions: Array.from({ length: 32 }, (_, id) => ({ id: id + 1, label: 'Project ' + (id + 1), meta: 'Research', status: 'pending' })),
            hasContextOptions() { return true; },
            selectedContext() { return this.contextOptions.find(project => project.id === this.selectedContextId); },
            closeDocumentPicker() {},
            selectProjectContext(id) { this.selectedContextId = id; this.contextEnabled = true; },
        }));` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        const menus = page.locator('[data-assistant-project-picker]');
        await expect(menus).toHaveCount(2);
        await expect(menus.locator('[data-assistant-project-option]')).toHaveCount(0);
        await menus.first().getByRole('button', { name: 'Add project context', exact: true }).click();
        await expect(menus.first().locator('[data-assistant-project-option]')).toHaveCount(32);
        await expect(menus.last().locator('[data-assistant-project-option]')).toHaveCount(0);
        await menus.first().getByRole('searchbox').fill('Project 32');
        await expect(menus.first().locator('[data-assistant-project-option]')).toHaveCount(1);
        await menus.first().locator('[data-assistant-project-option]').click();
        await expect(menus.locator('[data-assistant-project-option]')).toHaveCount(0);
        await expect(menus.first().getByRole('button', { name: 'Change project: Project 32', exact: true })).toBeFocused();
        await menus.last().getByRole('button', { name: 'Change project: Project 32', exact: true }).click();
        await expect(menus.last().locator('[data-assistant-project-option]')).toHaveCount(32);
        await menus.last().getByRole('searchbox').press('Escape');
        await expect(menus.locator('[data-assistant-project-option]')).toHaveCount(0);
        await expect(menus.last().getByRole('button', { name: 'Change project: Project 32', exact: true })).toBeFocused();
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
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
        const expression = view.match(/x-text="([^"]*message\.sources[^\"]+)"/)[1];
        const count = new Function('message', `return ${expression}`);
        assert.equal(count({ role: 'user', content: 'My question' }), '(0)');
        assert.equal(count({ role: 'assistant', sources: [{ reference: '[1]' }] }), '(1)');
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
