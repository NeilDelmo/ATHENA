import assert from 'node:assert/strict';
import test from 'node:test';
import { proposalSignedUploads } from '../../resources/js/proposal-signed-uploads.js';

const pdf = name => new File(['%PDF-1.4 signed'], name, { type: 'application/pdf' });
const create = () => proposalSignedUploads({
    url: '/signed-files', csrf: 'csrf-token',
    documents: [1, 2, 3].map(id => ({ id, label: `Paper ${id}`, saved: false })),
});
const success = (id, complete = false) => ({
    ok: true,
    json: async () => ({ source_file_id: id, filename: `signed-${id}.pdf`, view_url: `/view/${id}`, download_url: `/download/${id}`, complete }),
});

test('completed uploads expose readiness without an extra navigation step', () => {
    const state = proposalSignedUploads({ complete: true, documents: [{ id: 1, saved: true }] });
    assert.equal(state.complete, true);
    assert.equal(state.count, 1);
    assert.equal(state.continueToNotice, undefined);
});

test('dropping onto a paper uploads directly to that source without a matching step', async t => {
    const calls = [];
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        calls.push(options.body);
        return success(Number(options.body.get('source_file_id')));
    });
    const state = create();
    const files = [pdf('scan-a.pdf'), pdf('scan-b.pdf'), pdf('scan-c.pdf')];
    await Promise.all(state.documents.map((document, index) => state.drop(document, [files[index]])));
    assert.equal(state.count, 3);
    assert.deepEqual(calls.map(body => body.get('source_file_id')), ['1', '2', '3']);
    assert.deepEqual(calls.map(body => body.get('review_file').name), files.map(file => file.name));
    assert.ok(calls.every(body => body.get('_token') === 'csrf-token'));
});

test('dropping several files onto one paper rejects the selection without replacing its saved copy', async t => {
    const mock = t.mock.method(globalThis, 'fetch', async () => success(1));
    const state = create();
    Object.assign(state.documents[0], { saved: true, filename: 'old.pdf', viewUrl: '/old' });
    await state.drop(state.documents[0], [pdf('a.pdf'), pdf('b.pdf')]);
    assert.equal(mock.mock.callCount(), 0);
    assert.match(state.documents[0].error, /one PDF/);
    assert.equal(state.documents[0].viewUrl, '/old');
});

test('concurrent row uploads do not navigate or lose selections and stale responses cannot relock completion', async t => {
    const resolve = {};
    t.mock.method(globalThis, 'fetch', (url, options) => new Promise(done => {
        resolve[options.body.get('source_file_id')] = done;
    }));
    const state = create();
    const first = state.upload(state.documents[0], pdf('proposal.pdf'));
    const second = state.upload(state.documents[1], pdf('budget.pdf'));
    assert.equal(state.busy, true);
    assert.equal(state.documents[0].file.name, 'proposal.pdf');
    assert.equal(state.documents[1].file.name, 'budget.pdf');
    resolve[2](success(2, true));
    await second;
    resolve[1](success(1, false));
    await first;
    assert.equal(state.complete, true);
    assert.equal(state.count, 2);
    assert.equal(state.busy, false);
});

test('failed replacements retain the saved PDF and selected file for retry', async t => {
    const state = create();
    Object.assign(state.documents[0], { saved: true, filename: 'old.pdf', viewUrl: '/old' });
    const file = pdf('corrected.pdf');
    const mock = t.mock.method(globalThis, 'fetch', async () => ({ ok: false, json: async () => ({ errors: { review_file: ['Storage unavailable.'] } }) }));
    await state.upload(state.documents[0], file);
    assert.equal(state.documents[0].filename, 'old.pdf');
    assert.equal(state.documents[0].viewUrl, '/old');
    assert.equal(state.documents[0].file, file);
    assert.equal(state.documents[0].error, 'Storage unavailable.');
    assert.equal(state.busy, false);
    mock.mock.mockImplementation(async () => success(1));
    await state.upload(state.documents[0], state.documents[0].file);
    assert.equal(state.documents[0].error, '');
    assert.equal(state.documents[0].filename, 'signed-1.pdf');
    assert.equal(state.documents[0].file, null);
});

test('invalid files and oversize PDFs never start an upload', async t => {
    const mock = t.mock.method(globalThis, 'fetch', async () => success(1));
    const state = create();
    await state.upload(state.documents[0], new File(['text'], 'notes.txt', { type: 'text/plain' }));
    assert.equal(state.documents[0].error, 'Choose a PDF file.');
    await state.upload(state.documents[0], { name: 'large.pdf', type: 'application/pdf', size: 26 * 1024 * 1024 });
    assert.match(state.documents[0].error, /25 MB/);
    assert.equal(mock.mock.callCount(), 0);
});
