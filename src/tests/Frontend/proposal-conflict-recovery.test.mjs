import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('conflict recovery waits for lazy editor hydration before capturing saved values', { timeout: 60000 }, async () => {
    const source = readFileSync(resolve('resources/js/proposal-conflict-recovery.js'), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.route('https://athena.test/**', route => {
            if (route.request().url().endsWith('/recovery.js')) return route.fulfill({ contentType: 'text/javascript', body: source });
            return route.fulfill({ contentType: 'text/html', body: `<body data-auth-user-id="1">
                <main data-paper-editor data-paper-dirty="false" x-ignore x-data="{ rationale: 'Saved rationale', entries: [{ activity: 'Saved activity' }] }">
                    <form data-paper-form>
                        <label>Rationale<textarea name="rationale" x-model="rationale"></textarea></label>
                        <template x-for="(entry, index) in entries"><label>Row activity<input :name="'entries[' + index + '][activity]'" x-model="entry.activity"></label></template>
                        <input type="hidden" name="document_version" value="1">
                    </form>
                    <section data-proposal-version-monitor data-loaded-version="1" data-state-url="/drafts/4/state/detailed-proposal/0">
                        <div data-proposal-stale-warning hidden></div><div data-proposal-conflict-recovery hidden></div>
                    </section>
                </main>
            </body>` });
        });
        await page.goto('https://athena.test/editor');
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        await page.addScriptTag({ type: 'module', content: `import init from '/recovery.js'; window.initializeRecovery = init; init(); window.recoveryLoaded = true;` });
        await page.waitForFunction(() => window.recoveryLoaded);
        await expect(page.getByLabel('Rationale')).toHaveValue('');
        await expect(page.getByLabel('Row activity')).toHaveCount(0);
        assert.equal(await page.evaluate(() => document.querySelector('[data-paper-editor]')._proposalRecovery), undefined);

        await page.evaluate(async () => {
            const root = document.querySelector('[data-paper-editor]');
            Alpine.mutateDom(() => {
                Alpine.destroyTree(root);
                root.removeAttribute('x-ignore');
                Alpine.initTree(root);
            });
            await Alpine.nextTick();
            window.initializeRecovery();
            window.dispatchEvent(new Event('pagehide'));
        });
        await expect(page.getByLabel('Rationale')).toHaveValue('Saved rationale');
        await expect(page.getByLabel('Row activity')).toHaveValue('Saved activity');
        const recoveryCopies = () => page.evaluate(() => Object.entries(localStorage).filter(([key]) => key.startsWith('athena-proposal-recovery:')).map(([, value]) => JSON.parse(value)));
        assert.deepEqual(await recoveryCopies(), [], 'Hydration must not create an unsaved recovery copy');
        await page.getByLabel('Rationale').fill('My revised rationale');
        await expect.poll(async () => (await recoveryCopies()).flatMap(copy => copy.fields.map(field => ({ name: field.name, value: field.value })))).toEqual([
            { name: 'rationale', value: 'My revised rationale' },
        ]);
    } finally { await browser.close(); }
});

test('conflict recovery survives reload, compares latest text, reapplies selected fields safely, and isolates accounts', { timeout: 60000 }, async () => {
    const source = readFileSync(resolve('resources/js/proposal-conflict-recovery.js'), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    let latest = false;
    let user = '1';
    await page.route('https://athena.test/**', route => {
        if (route.request().url().endsWith('/recovery.js')) return route.fulfill({ contentType: 'text/javascript', body: source });
        if (route.request().url().includes('/drafts/4/state/')) return route.fulfill({ json: { version: latest ? 2 : 1 } });
        return route.fulfill({ contentType: 'text/html', body: `<body data-auth-user-id="${user}"><main data-paper-editor data-paper-dirty="false" x-data="{title:'${latest ? 'Teammate title' : 'Original title'}', rationale:'Original rationale'}"><form data-paper-form><label>Project title<input name="title" x-model="title"></label><label>Rationale<textarea name="rationale" x-model="rationale"></textarea></label><input type="hidden" name="document_version" value="${latest ? 2 : 1}"><input type="hidden" name="_token" value="SECRET"><label>Linked objective<input name="objective" value="Linked" readonly></label><div data-repeatable-entry="row-1"><input name="entries[0][objective]" value="${latest ? 'Teammate moved another objective here' : 'First objective'}" readonly><label>Row activity<input name="entries[0][activity]" value="Original activity"></label></div><input name="password" type="password"><input type="file" name="figure"></form><section data-proposal-version-monitor data-loaded-version="${latest ? 2 : 1}" data-state-url="https://athena.test/drafts/4/state/work-plan/0"><div data-proposal-stale-warning hidden></div><div data-proposal-conflict-recovery hidden></div></section></main><form action="/logout" method="post"><button>Log out</button></form></body>` });
    });
    async function initialize() {
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        await page.addScriptTag({ type: 'module', content: `import init from '/recovery.js'; window.disposeRecovery=init(); window.recoveryLoaded=true;` });
        await page.waitForFunction(() => window.recoveryLoaded);
    }
    try {
        await page.goto('https://athena.test/editor');
        await initialize();
        await page.getByLabel('Project title').fill('My unsaved title <img src=x onerror=alert(1)>');
        await page.getByLabel('Rationale').fill('My unsaved rationale');
        await page.getByLabel('Row activity').fill('My objective activity');
        await page.evaluate(() => { const input = document.createElement('input'); input.name = 'new_row_text'; input.value = 'Text from a newly added row'; document.querySelector('form').append(input); });
        await page.locator('[name="figure"]').setInputFiles({ name: 'diagram.png', mimeType: 'image/png', buffer: Buffer.from('file') });
        await page.evaluate(() => { document.querySelector('[data-paper-editor]').dataset.paperDirty = 'true'; document.querySelector('[data-proposal-stale-warning]').hidden = false; });
        await expect(page.locator('[data-proposal-conflict-recovery]')).toContainText('Recovery copy saved');
        const saved = await page.evaluate(() => Object.entries(localStorage).find(([key]) => key.startsWith('athena-proposal-recovery:'))?.[1]);
        assert.ok(saved.includes('My unsaved title'));
        assert.ok(!saved.includes('SECRET'));
        assert.ok(!saved.includes('password'));
        latest = true;
        await page.reload();
        await initialize();
        const recovery = page.locator('[data-proposal-conflict-recovery]');
        await expect(recovery).toContainText('Teammate title');
        await expect(recovery).toContainText('My unsaved title');
        await expect(recovery).toContainText('Select image and attachment files again');
        await expect(recovery).toContainText('This field or row no longer exists');
        await expect(recovery.getByRole('checkbox', { name: 'new_row_text' })).toBeDisabled();
        await expect(recovery.getByRole('checkbox', { name: 'Row activity' })).toBeDisabled();
        await expect(recovery).toContainText('This row changed or moved');
        await expect(recovery.locator('img, script')).toHaveCount(0);
        await recovery.getByRole('checkbox', { name: /Project title/ }).check();
        await recovery.getByRole('checkbox', { name: 'Rationale', exact: true }).check();
        await page.getByRole('textbox', { name: 'Rationale', exact: true }).fill('New edit after reload');
        await recovery.getByRole('button', { name: 'Apply selected changes' }).click();
        await expect(page.getByRole('textbox', { name: 'Project title', exact: true })).toHaveValue('My unsaved title <img src=x onerror=alert(1)>');
        await expect(page.getByRole('textbox', { name: 'Rationale', exact: true })).toHaveValue('New edit after reload');
        await expect(recovery.getByRole('status')).toContainText('1 field was left alone');
        assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('[data-paper-editor]')).title), 'My unsaved title <img src=x onerror=alert(1)>');
        await page.evaluate(() => { document.querySelector('[data-paper-editor]').dataset.paperDirty = 'false'; });
        await expect(recovery).toContainText('My unsaved rationale');
        assert.ok(!await page.evaluate(() => JSON.stringify(Object.entries(localStorage)).includes('My unsaved title')), 'confirmed server-save equality clears the reapplied title');
        while (await recovery.isVisible()) await recovery.getByRole('button', { name: 'Discard recovery copy' }).click();
        await expect(recovery).toBeHidden();
        await page.evaluate(() => {
            localStorage.setItem('athena-proposal-recovery:1:expired', JSON.stringify({ savedAt: Date.now() - 90000000, fields: [{name:'private',value:'expired',label:'Private'}] }));
        });
        await page.reload();
        await initialize();
        assert.equal(await page.evaluate(() => localStorage.getItem('athena-proposal-recovery:1:expired')), null);
        await page.evaluate(() => localStorage.setItem('athena-proposal-recovery:1:logout', JSON.stringify({savedAt:Date.now(),fields:[]})));
        await page.evaluate(() => { document.addEventListener('submit', event => event.preventDefault()); document.querySelector('form[action="/logout"]').requestSubmit(); });
        assert.equal(await page.evaluate(() => Object.keys(localStorage).some(key => key.startsWith('athena-proposal-recovery:'))), false);
        await page.evaluate(() => localStorage.setItem('athena-proposal-recovery:1:private', JSON.stringify({ fields: ['private'] })));
        user = '2';
        await page.reload();
        await initialize();
        assert.equal(await page.evaluate(() => Object.keys(localStorage).some(key => key.startsWith('athena-proposal-recovery:1:'))), false);
        await page.getByLabel('Project title').fill('Cannot store this');
        await page.evaluate(() => {
            Storage.prototype.setItem = () => { throw new Error('Quota exceeded'); };
            document.querySelector('[data-paper-editor]').dataset.paperDirty='true';
            document.querySelector('[data-proposal-stale-warning]').hidden=false;
        });
        await expect(page.locator('[data-proposal-conflict-recovery]')).toContainText('Recovery copy could not be saved');
    } finally { await browser.close(); }
});
