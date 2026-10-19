import assert from 'node:assert/strict';
import test from 'node:test';
import { uploadRequest } from '../../resources/js/file-upload.js';

class UploadRequest extends EventTarget {
    upload = new EventTarget();
    headers = {};
    status = 200;
    responseText = '{"saved":true}';
    open(method, url) { this.method = method; this.url = url; }
    setRequestHeader(name, value) { this.headers[name] = value; }
    getAllResponseHeaders() { return 'content-type: application/json\r\n'; }
    send(body) { this.body = body; UploadRequest.requests.push(this); UploadRequest.send(this); }
    abort() { this.dispatchEvent(new Event('abort')); }
    static requests = [];
    static send = request => request.dispatchEvent(new Event('load'));
}
const body = () => { const data = new FormData(); data.append('photo', new File(['picture'], 'figure.png')); return data; };
const setup = t => {
    UploadRequest.requests = [];
    UploadRequest.send = request => request.dispatchEvent(new Event('load'));
    globalThis.XMLHttpRequest = UploadRequest;
    t.after(() => { delete globalThis.XMLHttpRequest; });
};

test('ordinary saves keep their existing fetch transport', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', async () => new Response('{}'));
    await uploadRequest('/save', { method: 'POST', body: new FormData() });
    assert.equal(fetch.mock.callCount(), 1);
});

test('file transfer reports actual bytes then waits for server preparation', async t => {
    setup(t);
    const states = [];
    UploadRequest.send = request => {
        const progress = new Event('progress');
        Object.assign(progress, { lengthComputable: true, loaded: 25, total: 100 });
        request.upload.dispatchEvent(progress);
        request.upload.dispatchEvent(new Event('load'));
        request.dispatchEvent(new Event('load'));
    };
    const response = await uploadRequest('/save', { method: 'POST', body: body(), headers: { 'X-CSRF-TOKEN': 'csrf' } }, { onProgress: state => states.push(state) });
    assert.deepEqual(await response.json(), { saved: true });
    assert.ok(states.some(state => state.phase === 'uploading' && state.percent === 25));
    assert.ok(states.some(state => state.phase === 'preparing' && state.percent === 100));
    assert.equal(states.at(-1).phase, 'saved');
    assert.equal(UploadRequest.requests[0].headers['x-csrf-token'], 'csrf');
    assert.match(UploadRequest.requests[0].headers['x-athena-upload-id'], /^[a-f\d-]{36}$/);
});

test('lost responses retry the same operation and file body without duplicating saves', async t => {
    setup(t);
    UploadRequest.send = request => request.dispatchEvent(new Event(UploadRequest.requests.length === 1 ? 'error' : 'load'));
    const data = body();
    await uploadRequest('/save', { method: 'POST', body: data }, { retryDelay: 0 });
    assert.equal(UploadRequest.requests.length, 2);
    assert.equal(UploadRequest.requests[0].headers['x-athena-upload-id'], UploadRequest.requests[1].headers['x-athena-upload-id']);
    assert.equal(UploadRequest.requests[1].headers['x-athena-upload-retry'], '1');
    assert.equal(UploadRequest.requests[1].body.get('photo'), data.get('photo'));
});

test('validation and permission errors are not retried or reported as saved', async t => {
    setup(t);
    for (const status of [403, 419, 422, 413, 500]) {
        const states = [];
        UploadRequest.send = request => { request.status = status; request.dispatchEvent(new Event('load')); };
        const before = UploadRequest.requests.length;
        const response = await uploadRequest('/save', { method: 'POST', body: body() }, { onProgress: state => states.push(state) });
        assert.equal(response.status, status);
        assert.equal(UploadRequest.requests.length, before + 1);
        assert.equal(states.at(-1).phase, 'error');
    }
});

test('an explicit retry of an unchanged interrupted upload keeps its operation identity', async t => {
    setup(t);
    const data = body();
    UploadRequest.send = request => request.dispatchEvent(new Event('error'));
    await assert.rejects(uploadRequest('/save', { method: 'POST', body: data }, { retryDelay: 0 }));
    const previous = UploadRequest.requests[0].headers['x-athena-upload-id'];
    UploadRequest.send = request => request.dispatchEvent(new Event('load'));
    await uploadRequest('/save', { method: 'POST', body: data });
    assert.equal(UploadRequest.requests.at(-1).headers['x-athena-upload-id'], previous);
});

test('aborting an upload never retries', async t => {
    setup(t);
    const controller = new AbortController();
    UploadRequest.send = () => controller.abort();
    await assert.rejects(uploadRequest('/save', { method: 'POST', body: body(), signal: controller.signal }), { name: 'AbortError' });
    assert.equal(UploadRequest.requests.length, 1);
});

test('a still-processing save keeps its identity for the next retry', async t => {
    setup(t);
    const data = body();
    UploadRequest.send = request => {
        request.status = 409;
        request.responseText = '{"code":"upload_in_progress"}';
        request.dispatchEvent(new Event('load'));
    };
    await uploadRequest('/processing', { method: 'POST', body: data }, { retryDelay: 0 });
    const id = UploadRequest.requests[0].headers['x-athena-upload-id'];
    UploadRequest.send = request => request.dispatchEvent(new Event('load'));
    await uploadRequest('/processing', { method: 'POST', body: data });
    assert.equal(UploadRequest.requests.at(-1).headers['x-athena-upload-id'], id);
    assert.equal(UploadRequest.requests.at(-1).headers['x-athena-upload-retry'], '1');
});

test('retry-unavailable responses stop automatic retries', async t => {
    setup(t);
    UploadRequest.send = request => {
        if (UploadRequest.requests.length === 1) { request.dispatchEvent(new Event('error')); return; }
        request.status = 409;
        request.responseText = '{"code":"upload_retry_unavailable"}';
        request.dispatchEvent(new Event('load'));
    };
    const response = await uploadRequest('/unsafe', { method: 'POST', body: body() }, { retryDelay: 0 });
    assert.equal(response.status, 409);
    assert.equal(UploadRequest.requests.length, 2);
});

test('an already-aborted transfer never sends a file', async t => {
    setup(t);
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(uploadRequest('/cancelled', { method: 'POST', body: body(), signal: controller.signal }), { name: 'AbortError' });
    assert.equal(UploadRequest.requests.length, 0);
});

test('interrupted operation identities cannot cross accounts or workspaces', async t => {
    setup(t);
    let user = '1';
    let workspace = 'faculty';
    globalThis.document = { querySelector: selector => selector === '[data-auth-user-id]' ? { dataset: { authUserId: user } }
        : selector === '[data-active-workspace]' ? { dataset: { activeWorkspace: workspace } } : null };
    t.after(() => { delete globalThis.document; });
    const data = body();
    const ids = [];
    UploadRequest.send = request => request.dispatchEvent(new Event('error'));
    for (const change of [() => {}, () => { workspace = 'research_head'; }, () => { user = '2'; }]) {
        change();
        await assert.rejects(uploadRequest('/same', { method: 'POST', body: data }, { retryDelay: 0 }));
        ids.push(UploadRequest.requests.at(-1).headers['x-athena-upload-id']);
    }
    assert.equal(new Set(ids).size, 3);
});
