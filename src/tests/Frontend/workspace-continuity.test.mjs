import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('workspace switching includes the matching account workspace page with query and tab', async () => {
    const source = readFileSync(resolve('resources/js/workspace-switching.js'), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        let post;
        await page.route('https://athena.test/**', route => {
            if (route.request().url().endsWith('/switch.js')) return route.fulfill({ contentType: 'text/javascript', body: source });
            if (route.request().method() === 'POST') { post = route.request().postData(); return route.fulfill({ json: { redirect: 'https://athena.test/projects?status=active#files' } }); }
            return route.fulfill({ contentType: 'text/html', body: '<body data-auth-user-id="4" data-active-workspace="faculty"><div data-workspace-selector><div data-workspace-switch-status hidden><span data-workspace-switch-message></span><a data-workspace-switch-continue hidden></a></div><form action="/choose-workspace" data-workspace-switch><input name="workspace" value="faculty"><button type="submit">Continue</button></form></div></body>' });
        });
        await page.goto('https://athena.test/projects?status=active#files');
        await page.addScriptTag({ type: 'module', content: `import {initializeWorkspaceSwitching} from '/switch.js'; window.destination=''; initializeWorkspaceSwitching(url=>window.destination=url);` });
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect.poll(() => page.evaluate(() => window.destination)).toBe('https://athena.test/projects?status=active#files');
        assert.match(post, /name="resume_url"\s+https:\/\/athena.test\/projects\?status=active#files/);
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigated')));
        await page.evaluate(() => { document.body.dataset.authUserId = '5'; document.body.removeAttribute('data-active-workspace'); document.dispatchEvent(new CustomEvent('livewire:navigated')); window.destination=''; });
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect.poll(() => page.evaluate(() => window.destination)).not.toBe('');
        assert.ok(!post.includes('resume_url'));
        await page.close();
    } finally { await browser.close(); }
});

test('project folder tabs, search, status and location resume per account and reject removed locations', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    let user = 4;
    let includeFolder = true;
    const project = { id: 2, title: 'Coastal study', status: 'ongoing', year: 2026, date: '2026-01-01' };
    try {
        const page = await browser.newPage();
        await page.route('https://athena.test/**', route => {
            const path = new URL(route.request().url()).pathname;
            if (path.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js', path.slice(1)), 'utf8') });
            return route.fulfill({ contentType: 'text/html', body: `<body data-auth-user-id="${user}" data-active-workspace="research_head"><main id="records" x-data="projectRecordsPage({initialView:'monitoring'})"><p x-text="recordsView"></p><section id="projects" x-data='projectWorkspace(${JSON.stringify({projects:[project],workspace:{folders:includeFolder?[{id:'folder-1',name:'Archive',parent_id:null}]:[],assignments:[]}})})'><input aria-label="Folder search" x-model="query"><p x-text="location"></p><p x-text="statusFilter"></p></section></main></body>` });
        });
        async function load() {
            await page.goto('https://athena.test/projects');
            await page.addScriptTag({ type: 'module', content: `import {projectWorkspace,projectRecordsPage} from '/project-workspace.js'; document.addEventListener('alpine:init',()=>{Alpine.data('projectWorkspace',projectWorkspace);Alpine.data('projectRecordsPage',projectRecordsPage);}); window.ready=true;` });
            await page.waitForFunction(() => window.ready);
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            await expect(page.locator('#records>p')).toHaveText(/monitoring|files/);
        }
        await load();
        await page.evaluate(() => {
            Alpine.$data(document.querySelector('#records')).recordsView = 'files';
            const state = Alpine.$data(document.querySelector('#projects'));
            state.query = 'Coastal'; state.statusFilter = 'ongoing'; state.location = 'folder-1';
        });
        await expect.poll(() => page.evaluate(() => sessionStorage.getItem('athena-workspace-locations:4'))).toContain('Coastal');
        await load();
        await expect(page.locator('#records>p')).toHaveText('files');
        await expect(page.getByRole('textbox')).toHaveValue('Coastal');
        assert.deepEqual(await page.evaluate(() => { const state=Alpine.$data(document.querySelector('#projects')); return [state.location,state.statusFilter]; }), ['folder-1','ongoing']);
        includeFolder = false;
        await load();
        assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('#projects')).location), 'all');
        user = 5;
        await load();
        await expect(page.locator('#records>p')).toHaveText('monitoring');
        await expect(page.getByRole('textbox')).toHaveValue('');
        assert.equal(await page.evaluate(() => Alpine.$data(document.querySelector('#projects')).statusFilter), 'all');
        await page.close();
    } finally { await browser.close(); }
});
