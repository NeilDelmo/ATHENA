const interrupted = new Map();
const fileIds = new WeakMap();
let nextFileId = 0;
let activeUploads = 0;
let initialized = false;

function operationKey(url, options) {
    const fields = [...options.body].map(([name, value]) => {
        if (!(value instanceof File)) return [name, value];
        if (!fileIds.has(value)) fileIds.set(value, ++nextFileId);
        return [name, fileIds.get(value), value.name, value.size, value.lastModified];
    });
    return JSON.stringify([globalThis.document?.querySelector('[data-auth-user-id]')?.dataset.authUserId,
        globalThis.document?.querySelector('[data-active-workspace]')?.dataset.activeWorkspace, url, options.method, fields]);
}

function progressPanel(root) {
    if (!root?.isConnected) return () => {};
    const saveMessage = root.closest('[data-proposal-paper-workspace], [data-paper-editor]')?.querySelector('[data-proposal-autosave-message]');
    let panel = root.querySelector(':scope > [data-file-upload-progress]');
    if (!panel) {
        panel = document.createElement('div');
        panel.dataset.fileUploadProgress = '';
        panel.className = 'file-upload-progress';
        panel.innerHTML = '<p role="status" aria-live="polite" data-upload-message></p><progress max="100" aria-label="File upload progress"></progress><p data-upload-size></p><button type="button" data-upload-retry hidden>Retry upload</button>';
        root.append(panel);
    }
    return state => {
        if (!panel.isConnected) return;
        panel.hidden = state.phase === 'saved';
        panel.dataset.uploadPhase = state.phase;
        panel.querySelector('[data-upload-message]').textContent = state.message;
        if (saveMessage) saveMessage.textContent = state.message;
        const progress = panel.querySelector('progress');
        progress.hidden = ['saved', 'error'].includes(state.phase);
        if (state.percent === null) progress.removeAttribute('value');
        else progress.value = state.percent;
        panel.querySelector('[data-upload-size]').textContent = state.total
            ? `${(state.loaded / 1048576).toFixed(1)} MB of ${(state.total / 1048576).toFixed(1)} MB` : '';
        panel.querySelector('[data-upload-retry]').hidden = true;
    };
}

function transfer(url, options, headers, update) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const signal = options.signal;
        const abort = () => request.abort();
        const finish = callback => { signal?.removeEventListener('abort', abort); callback(); };
        if (signal?.aborted) { reject(new DOMException('Upload cancelled', 'AbortError')); return; }
        request.open(options.method || 'POST', url);
        request.withCredentials = true;
        headers.forEach((value, name) => request.setRequestHeader(name, value));
        request.upload.addEventListener('progress', event => {
            const percent = event.lengthComputable ? Math.min(100, Math.floor(event.loaded / event.total * 100)) : null;
            update({ phase: 'uploading', percent, loaded: event.loaded, total: event.lengthComputable ? event.total : 0,
                message: percent === null ? 'Uploading files…' : `Uploading files… ${percent}%` });
        });
        request.upload.addEventListener('load', () => update({ phase: 'preparing', percent: 100, message: 'Upload complete. Saving and preparing files…' }));
        request.addEventListener('load', () => finish(() => {
            if (!request.status) { reject(new TypeError('Upload connection interrupted')); return; }
            const responseHeaders = new Headers();
            for (const line of request.getAllResponseHeaders().trim().split(/[\r\n]+/)) {
                const split = line.indexOf(':');
                if (split > 0) responseHeaders.append(line.slice(0, split), line.slice(split + 1).trim());
            }
            const response = new Response([204, 205, 304].includes(request.status) ? null : request.responseText, { status: request.status, headers: responseHeaders });
            Object.defineProperty(response, 'url', { value: request.responseURL || String(url) });
            resolve(response);
        }));
        request.addEventListener('error', () => finish(() => reject(new TypeError('Upload connection interrupted'))));
        request.addEventListener('abort', () => finish(() => reject(new DOMException('Upload cancelled', 'AbortError'))));
        signal?.addEventListener('abort', abort, { once: true });
        request.send(options.body);
    });
}

export async function uploadRequest(url, options = {}, { root, onProgress, retryDelay = 1000 } = {}) {
    const hasFiles = options.body instanceof FormData && [...options.body.values()].some(value => value instanceof File && value.size > 0);
    if (!hasFiles || typeof XMLHttpRequest === 'undefined'
        || (globalThis.location && new URL(url, location.href).origin !== location.origin)) return fetch(url, options);

    root ??= globalThis.document?.activeElement?.closest('form') || globalThis.document?.querySelector('main');
    const render = progressPanel(root);
    const update = state => { render(state); onProgress?.(state); };
    const key = operationKey(String(url), options);
    for (const [storedKey, operation] of interrupted) if (operation.expires < Date.now()) interrupted.delete(storedKey);
    const operation = interrupted.get(key) || { id: crypto.randomUUID(), attempted: false, expires: Date.now() + 10 * 60 * 1000 };
    interrupted.set(key, operation);
    while (interrupted.size > 12) interrupted.delete(interrupted.keys().next().value);
    const headers = new Headers(options.headers);
    headers.set('X-Athena-Upload-ID', operation.id);
    update({ phase: 'uploading', percent: 0, message: 'Uploading files… 0%' });
    activeUploads++;
    try {
        for (let attempt = 0; ; attempt++) {
            if (operation.attempted) headers.set('X-Athena-Upload-Retry', '1');
            operation.attempted = true;
            let response;
            try {
                response = await transfer(url, options, headers, update);
            } catch (error) {
                if (error.name === 'AbortError' || attempt >= 2) throw error;
            }
            const failure = response && !response.ok ? await response.clone().json().catch(() => ({})) : {};
            const inProgress = response?.status === 409 && failure.code === 'upload_in_progress';
            if (response && (!inProgress || attempt >= 2)) {
                const retryUnavailable = response.status === 409 && failure.code === 'upload_retry_unavailable';
                if (!inProgress && !retryUnavailable) interrupted.delete(key);
                update({ phase: response.ok ? 'saved' : 'error', percent: response.ok ? 100 : null,
                    message: response.ok ? 'Files saved.' : (inProgress || retryUnavailable
                        ? failure.message || 'Upload status could not be confirmed. Check the saved record before uploading again.'
                        : 'Files were not saved. Review the message below; your selection is kept.') });
                return response;
            }
            update({ phase: 'retrying', percent: null, message: 'Connection interrupted or upload still processing. Retrying safely…' });
            await new Promise(resolve => setTimeout(resolve, retryDelay * (attempt + 1)));
            if (options.signal?.aborted) throw new DOMException('Upload cancelled', 'AbortError');
        }
    } catch (error) {
        update({ phase: 'error', percent: null, message: error.name === 'AbortError'
            ? 'Upload cancelled. Your selected files are kept.' : 'Connection interrupted. Your selected files are kept. Try saving again.' });
        throw error;
    } finally { activeUploads--; }
}

export async function submitFileUploadForm(form, submitter) {
    const body = new FormData(form);
    if (![...body.values()].some(value => value instanceof File && value.size > 0)) {
        HTMLFormElement.prototype.submit.call(form);
        return true;
    }
    if (form.dataset.fileUploadBusy === 'true') return false;
    if (submitter?.name) body.append(submitter.name, submitter.value);
    form.dataset.fileUploadBusy = 'true';
    form.setAttribute('aria-busy', 'true');
    const controls = [...form.querySelectorAll('input, button, select, textarea')].filter(control => !control.disabled);
    controls.forEach(control => { control.disabled = true; });
    let succeeded = false;
    try {
        const response = await uploadRequest(form.getAttribute('action') || location.href, { method: form.getAttribute('method') || 'POST', body,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Athena-Upload-Form': '1' } }, { root: form });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const messages = Object.values(payload.errors || {}).flat();
            const error = new Error(messages.join(' ') || payload.message || (response.status === 419
                ? 'Your session expired. Open ATHENA in another tab to sign in, then retry here.' : 'The upload could not be saved. Your selected files are kept.'));
            error.retryUnavailable = payload.code === 'upload_retry_unavailable';
            throw error;
        }
        const destination = new URL(payload.redirect || location.href, location.href);
        if (destination.origin !== location.origin) throw new Error('The upload finished, but its destination could not be opened. Check your saved files.');
        const editor = form.closest('[data-paper-editor]');
        if (editor) {
            editor.dataset.paperDirty = 'false';
            editor.dataset.paperSubmitting = 'true';
        }
        succeeded = true;
        location.assign(destination.href);
        return true;
    } catch (error) {
        const panel = form.querySelector(':scope > [data-file-upload-progress]');
        if (panel) {
            panel.dataset.uploadPhase = 'error';
            panel.querySelector('progress').hidden = true;
            panel.querySelector('[data-upload-message]').textContent = error.message;
            const retry = panel.querySelector('[data-upload-retry]');
            retry.hidden = Boolean(error.retryUnavailable);
            retry.onclick = () => { if (form.reportValidity()) void submitFileUploadForm(form, submitter); };
        }
        return false;
    } finally {
        if (!succeeded) {
            delete form.dataset.fileUploadBusy;
            form.removeAttribute('aria-busy');
            controls.forEach(control => { control.disabled = false; });
        }
    }
}

export function initializeFileUploads() {
    if (initialized) return;
    initialized = true;
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.getAttribute('enctype') !== 'multipart/form-data'
            || ![...new FormData(form).values()].some(value => value instanceof File && value.size > 0)) return;
        event.preventDefault();
        void submitFileUploadForm(form, event.submitter);
    });
    window.addEventListener('beforeunload', event => {
        if (!activeUploads) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('livewire:navigate', event => { if (activeUploads) event.preventDefault(); });
}
