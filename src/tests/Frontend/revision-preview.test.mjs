import assert from 'node:assert/strict';
import { File } from 'node:buffer';
import test from 'node:test';
import { fitRevisionPaperPreview, initializeRevisionPreviews, previewRevisionEditor, revisionPaperPreview } from '../../resources/js/revision-workspace.js';

function cardFixture(editor = {}) {
    const nodes = {};
    const card = {
        dataset: { revisionDocument: 'work_plan', revisionPreviewUploadUrl: '/revision/preview' },
        querySelector: selector => nodes[selector] || null,
        closest: () => ({ querySelector: () => ({ value: 'csrf-token' }) }),
        addEventListener() {},
    };
    nodes['[data-revision-no-change]'] = { checked: false };
    nodes['[data-revision-comment]'] = { selectedOptions: [{ dataset: { pdfUrl: '/submitted.pdf' } }] };
    nodes['[data-revision-editor-frame]'] = {
        contentWindow: { athenaRevisionEditor: { topicId: 3, documentType: 'work_plan', ...editor } },
        closest: () => card,
        addEventListener() {},
    };
    return { card, nodes };
}

test('generated paper fits the revision panel after loading and resizing without changing PDF viewers', () => {
    const body = { style: {}, scrollWidth: 864 };
    const documentElement = { style: {}, clientWidth: 648 };
    const frame = { hasAttribute: () => true, contentDocument: { body, documentElement } };
    fitRevisionPaperPreview(frame);
    assert.equal(body.style.zoom, '0.75');
    documentElement.clientWidth = 432;
    fitRevisionPaperPreview(frame);
    assert.equal(body.style.zoom, '0.5');
    fitRevisionPaperPreview({ hasAttribute: () => false, get contentDocument() { assert.fail('PDF viewer must not be changed'); } });
});

test('preview generates the latest unsaved revision without saving or preparing its attachment', async () => {
    const state = {
        value: 'Revised activity',
        previewHtml: '<p>Old activity</p>',
        async generatePreview() { this.previewHtml = `<p>${this.value}</p>`; },
        save() { assert.fail('Preview must not save'); },
        downloadDocument() { assert.fail('Preview must not prepare an attachment'); },
    };
    assert.equal(await previewRevisionEditor(state), '<p>Revised activity</p>');
    state.value = 'Another edit';
    assert.equal(await previewRevisionEditor(state), '<p>Another edit</p>');
});

test('preview errors expose validation messages and never show an old paper', async () => {
    await assert.rejects(previewRevisionEditor({
        async generatePreview() { this.previewHtml = ''; this.validationMessage = 'Complete the activity.'; },
    }), /Complete the activity/);
    await assert.rejects(previewRevisionEditor(null), /still loading/);
    await assert.rejects(previewRevisionEditor({ generatePreview() {}, previewLoading: true }), /already being prepared/);
});

test('no-change preview uses the submitted paper even if the editor contains changes', async () => {
    const { card, nodes } = cardFixture({ preview() { assert.fail('No-change must use the submitted paper'); } });
    nodes['[data-revision-no-change]'].checked = true;
    const result = await revisionPaperPreview(card, 3);
    assert.equal(result.url, '/submitted.pdf');
    assert.match(result.label, /No file change needed/);
});

test('preview reads the matching mounted editor and rejects a different proposal frame', async () => {
    const { card, nodes } = cardFixture({ preview: async () => '<p>Current revision</p>' });
    assert.equal((await revisionPaperPreview(card, 3)).html, '<p>Current revision</p>');
    nodes['[data-revision-editor-frame]'].contentWindow.athenaRevisionEditor.topicId = 8;
    await assert.rejects(revisionPaperPreview(card, 3), /still loading/);
});

test('replacement PDF previews support selecting each uploaded file and force PDF content type', async (t) => {
    const original = URL.createObjectURL;
    const blobs = [];
    URL.createObjectURL = blob => { blobs.push(blob); return 'blob:replacement'; };
    t.after(() => { URL.createObjectURL = original; });
    const { card, nodes } = cardFixture({ preview() { assert.fail('Replacement must take priority over the editor'); } });
    nodes['input[type="file"]'] = { files: [new File(['%PDF-1'], 'first.pdf'), new File(['%PDF-2'], 'second.pdf')] };
    const result = await revisionPaperPreview(card, 3, 1);
    assert.equal(result.url, 'blob:replacement');
    assert.match(result.label, /second.pdf/);
    assert.equal(blobs[0].type, 'application/pdf');
    assert.equal(await blobs[0].text(), '%PDF-2');
});

test('Word replacement preview sends only the chosen file to the authorized preview endpoint', async (t) => {
    const original = globalThis.fetch;
    const originalUrl = URL.createObjectURL;
    t.after(() => { globalThis.fetch = original; URL.createObjectURL = originalUrl; });
    URL.createObjectURL = () => 'blob:converted-word';
    const file = new File(['word bytes'], 'revision.docx');
    globalThis.fetch = async (url, options) => {
        assert.equal(url, '/revision/preview');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.equal(options.body.get('paper'), file);
        return { ok: true, blob: async () => new Blob(['%PDF-1.7'], { type: 'application/pdf' }) };
    };
    const { card, nodes } = cardFixture();
    nodes['input[type="file"]'] = { files: [file] };
    assert.equal((await revisionPaperPreview(card, 3)).url, 'blob:converted-word');
    globalThis.fetch = async () => ({ ok: false, json: async () => ({ message: 'PDF conversion unavailable.' }) });
    await assert.rejects(revisionPaperPreview(card, 3), /PDF conversion unavailable/);
});

function previewFixture(editor) {
    const fixture = cardFixture(editor);
    const { card, nodes } = fixture;
    const handlers = {};
    const control = name => ({
        hidden: true, value: '', disabled: false,
        addEventListener(event, callback) { handlers[`${name}:${event}`] = callback; },
        removeAttribute(name) { delete this[name]; },
        setAttribute(name, value) { this[name] = value; },
        replaceChildren() {}, focus() {},
    });
    for (const name of ['panel', 'frame', 'open', 'close', 'refresh', 'status', 'error', 'stale', 'file']) {
        nodes[`[data-revision-preview-${name}]`] = control(name);
    }
    nodes['[data-revision-editor-content]'] = { hidden: false };
    nodes['[data-revision-dialog]'] = control('dialog');
    nodes['[data-revision-preview-panel]'].hidden = true;
    nodes['[data-revision-preview-open]'].hidden = false;
    initializeRevisionPreviews({ querySelectorAll: () => [card] }, 3);
    return { ...fixture, handlers };
}

test('switching back to editing preserves the mounted editor and clears replacement object URLs', async (t) => {
    const originalUrl = URL.createObjectURL;
    const originalRevoke = URL.revokeObjectURL;
    const revoked = [];
    URL.createObjectURL = () => 'blob:replacement';
    URL.revokeObjectURL = url => revoked.push(url);
    t.after(() => { URL.createObjectURL = originalUrl; URL.revokeObjectURL = originalRevoke; });
    const { nodes, handlers } = previewFixture({ preview: async () => '<p>Revision</p>' });
    const mounted = nodes['[data-revision-editor-frame]'];
    await handlers['refresh:click']();
    assert.equal(nodes['[data-revision-preview-frame]'].srcdoc, '<p>Revision</p>');
    assert.equal(nodes['[data-revision-editor-content]'].hidden, true);
    handlers['close:click']();
    assert.equal(nodes['[data-revision-editor-frame]'], mounted);
    assert.equal(nodes['[data-revision-editor-content]'].hidden, false);
    nodes['input[type="file"]'] = { files: [new File(['%PDF'], 'new.pdf')] };
    await handlers['refresh:click']();
    handlers['dialog:close']();
    assert.deepEqual(revoked, ['blob:replacement']);
});

test('closing a preview while generation is pending cannot reopen it when the response arrives', async () => {
    let resolve;
    const { nodes, handlers } = previewFixture({ preview: () => new Promise(callback => { resolve = callback; }) });
    const request = handlers['refresh:click']();
    handlers['close:click']();
    resolve('<p>Late preview</p>');
    await request;
    assert.equal(nodes['[data-revision-preview-panel]'].hidden, true);
    assert.equal(nodes['[data-revision-preview-frame]'].hidden, true);
    assert.equal(nodes['[data-revision-preview-frame]'].srcdoc, undefined);
});
