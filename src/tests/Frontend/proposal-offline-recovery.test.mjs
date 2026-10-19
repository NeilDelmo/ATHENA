import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import { freshProposalRecoveryVersion, validProposalRecoveryRecord } from '../../resources/js/proposal-conflict-recovery.js';

const source = readFileSync(resolve('resources/js/proposal-conflict-recovery.js'), 'utf8');
const recoveryKeys = () => Object.keys(localStorage).filter(key => key.startsWith('athena-proposal-recovery:'));

test('persistent recovery validates expiry, typed values, field count and size', () => {
    const now = 100000000;
    const record = { savedAt: now, loadedVersion: 2, fields: [{ name: 'title', label: 'Title', type: 'text', value: 'Unfinished' }] };
    assert.equal(validProposalRecoveryRecord(record, now), true);
    for (const candidate of [null, {}, { ...record, savedAt: now - 86400001 }, { ...record, savedAt: now + 1 },
        { ...record, loadedVersion: -1 }, { ...record, fields: Array(2001).fill(record.fields[0]) },
        { ...record, fields: [{ ...record.fields[0], value: 'x'.repeat(1000000) }] },
        { ...record, fields: [{ ...record.fields[0], value: ['wrong type'] }] },
        { ...record, fields: [{ ...record.fields[0], type: 'multiple', value: ['A', 2] }] }]) {
        assert.equal(validProposalRecoveryRecord(candidate, now), false);
    }
    assert.equal(validProposalRecoveryRecord({ ...record, fields: [{ ...record.fields[0], type: 'multiple', value: ['A'] }] }, now), true);
});

test('recovery checks a fresh authorized state and refuses mismatches, removals and failed authorization', async () => {
    let options;
    const request = (state, response = {}) => async (url, config) => {
        assert.equal(url, '/authorized-state');
        options = config;
        return { ok: true, redirected: false, json: async () => state, ...response };
    };
    assert.equal((await freshProposalRecoveryVersion('/authorized-state', 2, request({ version: 2 }))).current, true);
    assert.equal(options.cache, 'no-store');
    assert.equal(options.credentials, 'same-origin');
    for (const state of [{ version: 3 }, { version: 1 }, { version: 2, is_removed: true }]) {
        assert.equal((await freshProposalRecoveryVersion('/authorized-state', 2, request(state))).current, false);
    }
    for (const response of [{ ok: false }, { redirected: true }]) {
        await assert.rejects(freshProposalRecoveryVersion('/authorized-state', 2, request({ version: 2 }, response)));
    }
    await assert.rejects(freshProposalRecoveryVersion('/authorized-state', 2, request({ version: '2' })));
});

async function fixture(browser) {
    const context = await browser.newContext();
    const state = { version: 1, user: '1', requests: 0, authorized: true };
    await context.route('https://athena.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/recovery.js') return route.fulfill({ contentType: 'text/javascript', body: source });
        if (path.endsWith('/state/details/0')) {
            state.requests++;
            return route.fulfill({ status: state.authorized ? 200 : 403, json: { version: state.version } });
        }
        return route.fulfill({ contentType: 'text/html', body: `<body data-auth-user-id="${state.user}"><main data-paper-editor data-paper-dirty="false">
            <p data-proposal-autosave-status><span data-proposal-autosave-indicator></span><span data-proposal-autosave-message>Saved to ATHENA</span></p>
            <form data-paper-form><label>Project title<input name="project_title" value="Server title ${state.version}"></label>
                <label for="rationale">Narrative</label><textarea id="rationale" name="rationale">&lt;p&gt;Server narrative.&lt;/p&gt;</textarea>
                <label><input type="checkbox" name="choice[]" value="A">Choice A</label>
                <label><input type="checkbox" name="choice[]" value="B">Choice B</label>
                <input type="hidden" name="draft_version" value="${state.version}"><input type="hidden" name="_token" value="CSRF_SECRET">
                <input name="password" type="password" value="PASSWORD_SECRET"><input name="linked" value="LINKED_SECRET" readonly>
                <input type="file" name="figure"><button type="submit">Submit proposal</button>
            </form><section data-proposal-version-monitor data-loaded-version="${state.version}" data-state-url="/drafts/4/state/details/0">
                <div data-proposal-stale-warning hidden><p data-proposal-stale-message></p></div><div data-proposal-conflict-recovery hidden></div>
            </section></main><form action="/logout" method="post"><button>Log out</button></form></body>` });
    });
    async function initialize(page) {
        await page.addScriptTag({ type: 'module', content: `import init from '/recovery.js';
            const root = document.querySelector('[data-paper-editor]');
            window.editorState = {autoSaveBlocked:false}; window.Alpine = {$data:()=>window.editorState};
            window.saves=0; window.submissions=0;
            document.addEventListener('submit', event => { if(!event.target.action.endsWith('/logout')) {window.submissions++; event.preventDefault();} });
            root.addEventListener('input', () => root.dataset.paperDirty='true');
            root.addEventListener('change', () => root.dataset.paperDirty='true');
            document.querySelector('[name="rationale"]')._semanticEditor = document.createElement('div');
            window.guard=()=>root._proposalRecovery?.beforeSave() ?? Promise.resolve(true); window.failedSave=()=>root._proposalRecovery?.connectionFailed();
            window.dispose=init({resumeSave:async()=>{ if(await window.guard()) window.saves++; }});
            window.recoveryLoaded=true;` });
        await page.waitForFunction(() => window.recoveryLoaded);
    }
    async function open() {
        const page = await context.newPage();
        await page.goto('https://athena.test/editor');
        await initialize(page);
        return page;
    }
    return { context, state, open, initialize };
}

test('unfinished text and choices survive closing an editor and require a fresh selected restore', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { context, state, open } = await fixture(browser);
        let page = await open();
        await page.getByLabel('Project title', { exact: true }).fill('My unfinished title');
        await page.getByLabel('Narrative', { exact: true }).fill('<p>My <strong>formatted</strong> narrative.</p>');
        await page.getByLabel('Choice B', { exact: true }).check();
        await page.locator('[name="figure"]').setInputFiles({ name: 'diagram.png', mimeType: 'image/png', buffer: Buffer.from('FILE_SECRET') });
        const panel = page.locator('[data-proposal-conflict-recovery]');
        await expect(panel).toContainText('saved locally in this browser');
        const saved = await page.evaluate(() => JSON.stringify(Object.entries(localStorage)));
        for (const secret of ['CSRF_SECRET', 'PASSWORD_SECRET', 'LINKED_SECRET', 'FILE_SECRET', 'diagram.png']) assert.ok(!saved.includes(secret), secret);
        assert.ok(saved.includes('My unfinished title'));
        assert.equal(state.requests, 0, 'ordinary edits need no extra server check');
        await page.close();
        page = await open();
        const restored = page.locator('[data-proposal-conflict-recovery]');
        await expect(restored.getByRole('button', { name: 'Apply selected changes' })).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Project title', exact: true })).toHaveValue('Server title 1');
        await expect(restored).toContainText('Select image and attachment files again');
        await expect(restored).toContainText('My formatted narrative.');
        await expect(restored.locator('strong, img, script')).toHaveCount(0);
        await restored.getByRole('checkbox', { name: 'Project title', exact: true }).check();
        await restored.getByRole('checkbox', { name: 'Choice A', exact: true }).check();
        const checks = state.requests;
        await restored.getByRole('button', { name: 'Apply selected changes' }).click();
        await expect(page.getByRole('textbox', { name: 'Project title', exact: true })).toHaveValue('My unfinished title');
        await expect(page.locator('form [value="B"]')).toBeChecked();
        assert.ok(state.requests > checks, 'applying recovered text checks again before changing fields');
        assert.equal(await page.evaluate(() => window.submissions), 0);
        await context.close();
    } finally { await browser.close(); }
});

test('reconnect checks current state before resuming drafts and protects newer teammate work', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { context, state, open } = await fixture(browser);
        const page = await open();
        await page.evaluate(() => { Object.defineProperty(navigator, 'onLine', { configurable:true, get:()=>false }); window.dispatchEvent(new Event('offline')); });
        await page.getByLabel('Project title', { exact: true }).fill('Unsynced offline title');
        await page.evaluate(() => window.failedSave());
        assert.equal(await page.evaluate(() => window.guard()), false);
        state.authorized = false;
        await page.evaluate(() => { Object.defineProperty(navigator, 'onLine', { configurable:true, get:()=>true }); window.dispatchEvent(new Event('online')); });
        await expect(page.locator('[data-proposal-autosave-message]')).toContainText('Saved locally only');
        assert.equal(await page.evaluate(() => window.saves), 0);
        state.authorized = true;
        await page.getByRole('button', { name: 'Retry draft saving' }).click();
        await expect.poll(() => page.evaluate(() => window.saves)).toBe(1);
        await page.evaluate(() => window.dispatchEvent(new Event('offline')));
        state.version = 2;
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        await expect(page.locator('[data-proposal-stale-warning]')).toBeVisible();
        assert.equal(await page.evaluate(() => window.saves), 1);
        assert.equal(await page.evaluate(() => window.editorState.autoSaveBlocked), true);
        assert.equal(await page.evaluate(() => window.guard()), false);
        assert.match(await page.evaluate(() => JSON.stringify(Object.entries(localStorage))), /Unsynced offline title/);
        assert.equal(await page.evaluate(() => window.submissions), 0);
        await context.close();
    } finally { await browser.close(); }
});

test('duplicate tabs keep separate copies and logout prevents an older tab repopulating private recovery', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { context, state, open, initialize } = await fixture(browser);
        const first = await open();
        await first.evaluate(() => sessionStorage.setItem('cloned-tab-token', 'shared'));
        await first.getByLabel('Project title', { exact: true }).fill('First tab text');
        const popup = first.waitForEvent('popup');
        await first.evaluate(() => window.open('/editor'));
        const second = await popup;
        await second.waitForLoadState();
        await initialize(second);
        assert.equal(await second.evaluate(() => sessionStorage.getItem('cloned-tab-token')), 'shared');
        await second.getByRole('textbox', { name: 'Project title', exact: true }).fill('Second tab text');
        await expect.poll(() => second.evaluate(recoveryKeys)).toHaveLength(2);
        const values = await second.evaluate(() => JSON.stringify(Object.entries(localStorage)));
        assert.match(values, /First tab text/);
        assert.match(values, /Second tab text/);
        await first.close();
        await second.close();
        const reopened = await open();
        await expect(reopened.getByLabel('Recovery copy', { exact:true })).toHaveValue(/athena-proposal-recovery:/);
        assert.equal(await reopened.locator('[data-proposal-conflict-recovery] select option').count(), 2);
        state.user = '2';
        const otherAccount = await open();
        await expect.poll(() => reopened.evaluate(recoveryKeys)).toHaveLength(0);
        await reopened.getByLabel('Project title', { exact:true }).fill('Old account must not be stored');
        await reopened.evaluate(() => window.dispatchEvent(new Event('pagehide')));
        assert.equal((await reopened.evaluate(recoveryKeys)).length, 0);
        await otherAccount.getByLabel('Project title', { exact:true }).fill('Current account text');
        await expect.poll(() => otherAccount.evaluate(recoveryKeys)).toHaveLength(1);
        await otherAccount.evaluate(() => document.querySelector('form[action="/logout"]').dispatchEvent(new Event('submit', { bubbles:true, cancelable:true })));
        assert.equal((await otherAccount.evaluate(recoveryKeys)).length, 0);
        await context.close();
    } finally { await browser.close(); }
});

test('disabled recovery storage gives an honest warning while keeping text editable', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { context, open } = await fixture(browser);
        await context.addInitScript(() => Object.defineProperty(window, 'localStorage', { configurable:true, get() { throw new Error('Storage denied'); } }));
        const page = await open();
        await expect(page.locator('[data-proposal-conflict-recovery]')).toContainText('Recovery storage is unavailable');
        await page.getByLabel('Project title', { exact:true }).fill('Still editable');
        await expect(page.getByLabel('Project title', { exact:true })).toHaveValue('Still editable');
        await context.close();
    } finally { await browser.close(); }
});

test('discarding a current recovery copy does not recreate it when the page closes', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { context, open } = await fixture(browser);
        const page = await open();
        await page.getByLabel('Project title', { exact:true }).fill('Explicitly discarded local copy');
        await expect(page.locator('[data-proposal-conflict-recovery]')).toBeVisible();
        await page.getByRole('button', { name:'Discard recovery copy' }).click();
        await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
        assert.equal((await page.evaluate(recoveryKeys)).length, 0);
        await page.close();
        const reopened = await open();
        await expect(reopened.locator('[data-proposal-conflict-recovery]')).toBeHidden();
        assert.equal((await reopened.evaluate(recoveryKeys)).length, 0);
        await context.close();
    } finally { await browser.close(); }
});
