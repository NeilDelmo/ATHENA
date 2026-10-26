import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('repeated submissions cannot bypass a pending upload while its controls are disabled', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    let requests = 0;
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://athena-upload.test/**', async route => {
            if (route.request().method() === 'POST') {
                requests++;
                await pending;
                return route.fulfill({ status: 422, json: { errors: { title: ['Use a unique title.'] } } });
            }
            return route.fulfill({ contentType: 'text/html', body: '<form id="upload" action="/save" method="POST" enctype="multipart/form-data"><label>Title<input name="title" value="Report"></label><input aria-label="Evidence" type="file" name="evidence"></form><button form="upload" type="submit">Save report</button>' });
        });
        await page.goto('https://athena-upload.test/editor');
        await page.addScriptTag({ content: `${readFileSync(resolve('resources/js/file-upload.js'), 'utf8').replace(/^export /gm, '')}\nwindow.submitUpload=submitFileUploadForm;initializeFileUploads();window.nativeSubmissions=0;HTMLFormElement.prototype.submit=function(){window.nativeSubmissions++;};` });
        await page.getByLabel('Evidence').setInputFiles({ name: 'proof.png', mimeType: 'image/png', buffer: Buffer.from('proof') });
        await page.getByRole('button', { name: 'Save report', exact: true }).click();
        await expect.poll(() => requests).toBe(1);
        const repeated = await page.evaluate(async () => {
            const form = document.querySelector('form');
            const event = new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: document.querySelector('[form="upload"]') });
            form.dispatchEvent(event);
            return { prevented: event.defaultPrevented, saved: await window.submitUpload(form), native: window.nativeSubmissions };
        });
        assert.deepEqual(repeated, { prevented: true, saved: false, native: 0 });
        await expect(page.getByRole('button', { name: 'Save report', exact: true })).toBeDisabled();
        release();
        await expect(page.getByText('Use a unique title.', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Title')).toHaveValue('Report');
        await expect(page.getByRole('button', { name: 'Save report', exact: true })).toBeEnabled();
        await page.getByRole('button', { name: 'Save report', exact: true }).click();
        await expect.poll(() => requests).toBe(2);
        await expect(page.getByRole('button', { name: 'Save report', exact: true })).toBeEnabled();
        assert.deepEqual(errors, []);
    } finally {
        release();
        await browser.close();
    }
});

test('Edge native uploads keep failed edits dirty and leave cleanly after recovering a saved response', { timeout: 60000 }, async () => {
    const app = readFileSync('resources/js/app.js', 'utf8').replaceAll('\r\n', '\n');
    const appSection = (start, end) => {
        const first = app.indexOf(start);
        const last = app.indexOf(end, first);
        assert.ok(first >= 0 && last > first, `Missing application guard: ${start}`);
        return app.slice(first, last + end.length);
    };
    const editorGuards = [
        appSection('function currentPaperEditor() {', '\n}'),
        appSection('function paperEditorHasUnsavedChanges(editor) {', '\n}'),
        appSection("document.addEventListener('input', (event) => {\n    const editor", '\n});'),
        appSection("document.addEventListener('change', (event) => {\n    const editor", '\n});'),
        appSection("document.addEventListener('submit', async (event) => {\n    const editor", '\n});'),
        appSection("window.addEventListener('beforeunload', (event) => {\n    const editor", '\n});'),
    ].join('\n');
    const operations = new Map();
    const requests = [];
    let saves = 0;
    const server = createServer(async (request, response) => {
        if (request.url === '/file-upload.js') { response.setHeader('Content-Type', 'text/javascript'); response.end(readFileSync('resources/js/file-upload.js')); return; }
        if (request.url === '/proposal-paper-autosave.js') { response.setHeader('Content-Type', 'text/javascript'); response.end(readFileSync('resources/js/proposal-paper-autosave.js')); return; }
        if (request.url === '/style.css') { response.setHeader('Content-Type', 'text/css'); response.end(readFileSync('resources/css/file-upload.css')); return; }
        if (request.url === '/done') { response.end('<h1>Edition saved</h1>'); return; }
        if (request.method === 'POST') {
            let size = 0;
            for await (const chunk of request) size += chunk.length;
            const id = request.headers['x-athena-upload-id'];
            requests.push({ id, size, path: request.url, retry: request.headers['x-athena-upload-retry'] });
            await new Promise(resolve => setTimeout(resolve, 300));
            response.setHeader('Content-Type', 'application/json');
            if (request.url === '/background') { response.end('{"saved":true}'); return; }
            if (request.url === '/invalid') { response.writeHead(422); response.end(JSON.stringify({ errors: { title: ['Use a unique edition.'] } })); return; }
            if (!operations.has(id)) {
                saves++;
                operations.set(id, { redirect: '/done' });
                response.writeHead(200);
                response.flushHeaders();
                response.write('{"redirect":');
                setTimeout(() => response.destroy(), 20);
                return;
            }
            response.end(JSON.stringify(operations.get(id)));
            return;
        }
        response.end(`<html data-auth-user-id="1"><head><link rel="stylesheet" href="/style.css"></head><body style="margin:20px"><main data-proposal-paper-workspace data-paper-editor data-paper-dirty="false"><span data-proposal-autosave-message></span><form data-paper-form enctype="multipart/form-data" method="POST" action="/invalid"><label>Edition<input name="title" value="2026" required></label><input aria-label="Manual PDF" name="manual" type="file" required><button type="submit" name="action" value="review">Upload for review</button></form></main><script type="module">import { initializeFileUploads } from '/file-upload.js'; import { activeProposalPaperAutoSave, autoSaveHasPendingChanges } from '/proposal-paper-autosave.js'; const backgroundAutoSaveHasPendingChanges = () => false; ${editorGuards} initializeFileUploads(); window.ready=true; new MutationObserver(()=>{ const p=document.querySelector('[data-upload-phase]'); if(p) console.log('phase:'+p.dataset.uploadPhase+':'+p.querySelector('progress').value); }).observe(document.body,{subtree:true,attributes:true});</script></body></html>`);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 850 } });
        const errors = [];
        const phases = [];
        const dialogs = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('dialog', async dialog => { dialogs.push(dialog.type()); await dialog.accept(); });
        page.on('console', message => { if (message.text().startsWith('phase:')) phases.push(message.text()); });
        await page.goto(`http://127.0.0.1:${server.address().port}`);
        await page.waitForFunction(() => window.ready);
        const file = { name: 'manual.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(2 * 1024 * 1024, 'a') };
        await page.getByLabel('Manual PDF').setInputFiles(file);
        await expect(page.locator('[data-paper-editor]')).toHaveAttribute('data-paper-dirty', 'true');
        const initialFormHeight = await page.locator('form').evaluate(form => form.getBoundingClientRect().height);
        await page.evaluate(async () => {
            const { uploadRequest } = await import('/file-upload.js');
            const form = document.querySelector('form');
            await uploadRequest('/background', { method: 'POST', body: new FormData(form) }, { root: form });
        });
        await expect(page.locator('[data-file-upload-progress]')).toBeHidden();
        assert.equal(await page.locator('form').evaluate(form => form.getBoundingClientRect().height), initialFormHeight);
        await page.getByRole('button', { name: 'Upload for review' }).click();
        await expect(page.locator('[data-upload-phase]')).toHaveAttribute('data-upload-phase', 'preparing');
        assert.equal(await page.evaluate(() => !document.dispatchEvent(new CustomEvent('livewire:navigate', { cancelable: true }))), true);
        await expect(page.locator('[name=title]')).toBeDisabled();
        await expect(page.locator('[data-upload-message]')).toContainText('Saving and preparing');
        await expect(page.locator('[data-proposal-autosave-message]')).toContainText('Saving and preparing');
        await expect(page.locator('[data-upload-message]')).toHaveText('Use a unique edition.');
        assert.equal(await page.getByLabel('Manual PDF').evaluate(input => input.files[0].name), 'manual.pdf');
        await expect(page.locator('[name=title]')).toHaveValue('2026');
        await expect(page.locator('[name=title]')).toBeEnabled();
        await expect(page.getByRole('button', { name: 'Upload for review' })).toBeEnabled();
        await expect(page.locator('[data-paper-editor]')).toHaveAttribute('data-paper-dirty', 'true');
        await expect(page.locator('[data-paper-editor]')).not.toHaveAttribute('data-paper-submitting', 'true');
        await page.getByRole('button', { name: 'Retry upload', exact: true }).focus();
        await expect(page.getByRole('button', { name: 'Retry upload', exact: true })).toBeFocused();
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await page.screenshot({ path: resolve('storage/framework/testing/file-upload-error-mobile.png'), fullPage: true });
        await page.evaluate(() => { document.querySelector('form').action = '/save'; document.documentElement.classList.add('dark'); });
        await page.getByRole('button', { name: 'Retry upload', exact: true }).click();
        await page.waitForURL('**/done');
        assert.equal(saves, 1);
        const savedRequests = requests.filter(request => request.path === '/save');
        assert.equal(savedRequests.length, 2);
        assert.equal(savedRequests[0].id, savedRequests[1].id);
        assert.equal(savedRequests[1].retry, '1');
        assert.ok(savedRequests.every(request => request.size > file.buffer.length));
        assert.ok(phases.some(value => value.startsWith('phase:uploading:')));
        assert.ok(phases.some(value => value.startsWith('phase:preparing:100')));
        assert.deepEqual(dialogs, [], 'A confirmed saved upload must not warn about unsaved paper edits.');
        assert.deepEqual(errors, []);
    } finally { await browser.close(); await new Promise(resolve => server.close(resolve)); }
});
