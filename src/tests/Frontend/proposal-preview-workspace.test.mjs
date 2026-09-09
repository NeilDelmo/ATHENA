import assert from 'node:assert/strict';
import test from 'node:test';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';

test('opening preview retains the loaded document and editor position', () => {
    let generated = 0;
    const state = { ...proposalPreviewWorkspace(), previewHtml: '<p>Saved preview</p>', generatePreview() { generated++; } };
    const frame = { contentDocument: { body: { style: {} } } };
    state.$refs = { previewFrame: frame };
    state.previewPaneOpen = false;
    state.showProposalPreview();
    assert.equal(state.previewPaneOpen, true);
    assert.equal(state.previewTab, 'preview');
    assert.equal(generated, 0);
    assert.equal(state.$refs.previewFrame, frame);
    assert.equal(state.previewHtml, '<p>Saved preview</p>');
});

test('opening an empty preview generates once while a request is pending', () => {
    let generated = 0;
    const state = { ...proposalPreviewWorkspace(), previewHtml: '', generatePreview() { generated++; this.previewLoading = true; } };
    state.showProposalPreview();
    state.showProposalPreview();
    assert.equal(generated, 1);
});

test('edits mark an existing preview stale and track changes during generation', () => {
    const state = { ...proposalPreviewWorkspace(), previewHtml: '' };
    state.markProposalPreviewStale();
    assert.equal(state.previewStale, false);
    state.previewHtml = '<p>Preview</p>';
    const revision = state.previewRevision;
    state.markProposalPreviewStale();
    assert.equal(state.previewStale, true);
    assert.notEqual(state.previewRevision, revision);
});

test('zoom is bounded and is reapplied when the preview frame loads', () => {
    const body = { style: {} };
    const state = { ...proposalPreviewWorkspace(), previewHtml: '<p>Preview</p>', $refs: { previewFrame: { contentDocument: { body } } } };
    state.setProposalPreviewZoom(75);
    assert.equal(body.style.zoom, '0.75');
    state.setProposalPreviewZoom(500);
    assert.equal(state.previewZoom, 150);
    state.proposalPreviewLoaded();
    assert.equal(body.style.zoom, '1.5');
    assert.equal(state.previewReady, true);
});

test('full-screen preview opens the pane and can return to editing layout', () => {
    const state = { ...proposalPreviewWorkspace(), previewPaneOpen: false };
    state.toggleProposalPreviewFullscreen();
    assert.equal(state.previewFullscreen, true);
    assert.equal(state.previewPaneOpen, true);
    state.toggleProposalPreviewFullscreen();
    assert.equal(state.previewFullscreen, false);
});
