import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';

function selector(kind = 'three') {
    const compiledViews = resolve('../tmp/workspace-switch-views');
    mkdirSync(compiledViews, { recursive: true });
    const result = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        session()->put('_token', str_repeat('a', 40));
        $user = App\Models\User::factory()->make(['name' => 'Neil Carlo Delmo', 'email' => 'neil.delmo@g.batstate-u.edu.ph']);
        $definitions = App\Models\User::workspaceDefinitions();
        $kind = $argv[1] ?? 'three';
        $workspaces = match ($kind) {
            'role' => ['faculty' => $definitions['faculty'], 'research_coordinator' => $definitions['research_office']],
            'two' => array_intersect_key($definitions, array_flip(['research_head', 'faculty'])),
            'five' => $definitions,
            default => array_intersect_key($definitions, array_flip(['research_head', 'faculty_researcher', 'faculty'])),
        };
        echo Illuminate\Support\Facades\Blade::render('<x-workspace-selector :user="$user" :workspaces="$workspaces" :action="$action" :field="$field" :current-workspace="$currentWorkspace" />', [
            'user' => $user, 'workspaces' => $workspaces,
            'action' => route($kind === 'role' ? 'role-selection.store' : 'workspace.store'),
            'field' => $kind === 'role' ? 'role' : 'workspace',
            'currentWorkspace' => 'faculty',
        ]);
    `, kind], { encoding: 'utf8', cwd: resolve('.'), env: { ...process.env, VIEW_COMPILED_PATH: compiledViews } });
    assert.equal(result.status, 0, (result.stderr || result.stdout).slice(-4000));
    return result.stdout;
}

const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css]
    .map((file) => readFileSync(resolve('public/build', file), 'utf8')).join('\n');

async function render(page, html, dark) {
    await page.route('**/images/athenalogo-transparent.png', (route) => route.fulfill({
        contentType: 'image/png', body: readFileSync(resolve('public/images/athenalogo-transparent.png')),
    }));
    await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles}</style></head><body class="font-sans">${html}</body></html>`);
}

test('the workspace selector shows clear choices without overflow on mobile and desktop in both themes', async () => {
    const html = selector();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 768, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await render(page, html, dark);
                const header = page.locator('[data-workspace-selector] header');
                assert.equal(await header.evaluate((element) => getComputedStyle(element).backgroundColor), 'rgb(122, 0, 25)');
                assert.equal(await header.getByText('ATHENA', { exact: true }).evaluate((element) => getComputedStyle(element).color), 'rgb(255, 255, 255)');
                assert.equal(await header.getByRole('button').evaluate((element) => getComputedStyle(element).color), 'rgb(255, 255, 255)');
                assert.equal(await page.getByRole('heading', { name: 'Choose your workspace' }).isVisible(), true);
                for (const name of ['Research Head', 'Research Projects', 'Faculty']) {
                    assert.equal(await page.getByRole('heading', { name, exact: true }).isVisible(), true);
                }
                assert.equal(await page.locator('[data-current-workspace-badge]').count(), 1);
                assert.equal(await page.locator('[data-workspace-option="faculty"] [data-current-workspace-badge]').isVisible(), true);
                assert.equal(await page.getByRole('button', { name: 'Continue in workspace Faculty', exact: true }).isVisible(), true);
                const enter = page.getByRole('button', { name: 'Enter workspace Research Head', exact: true });
                assert.equal(await enter.isVisible(), true);
                assert.ok((await enter.boundingBox()).height >= 44);
                const positions = await page.locator('[data-workspace-option]').evaluateAll((cards) => cards.map((card) => card.getBoundingClientRect().y));
                assert.equal(width < 768 ? positions[0] < positions[1] : positions[0] === positions[2], true);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, `overflow at ${width}, dark=${dark}`);
                await enter.focus();
                assert.equal(await enter.evaluate((button) => button.matches(':focus-visible')), true);
                if (process.env.WORKSPACE_SCREENSHOT_DIRECTORY && [390, 1440].includes(width)) {
                    await page.screenshot({ path: resolve(process.env.WORKSPACE_SCREENSHOT_DIRECTORY, `workspace-selector-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                const action = await page.locator('[data-workspace-option="research_head"] form').getAttribute('action');
                await page.route(action, (route) => route.fulfill({ contentType: 'text/html', body: '<p>Workspace selected</p>' }));
                const submitted = page.waitForRequest((request) => request.url() === action && request.method() === 'POST');
                await page.keyboard.press('Enter');
                const request = await submitted;
                const data = new URLSearchParams(request.postData());
                assert.equal(data.get('workspace'), 'research_head');
                assert.equal(data.get('_token'), 'a'.repeat(40));
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('two and five workspaces and the legacy role selector retain their form contracts', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const kind of ['two', 'five', 'role']) {
            const html = selector(kind);
            for (const width of [390, 1440]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                await render(page, html, false);
                assert.equal(await page.locator('[data-workspace-option]').count(), kind === 'five' ? 5 : 2);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                const faculty = page.locator('[data-workspace-option="faculty"]');
                const action = await faculty.locator('form').getAttribute('action');
                await page.route(action, (route) => route.fulfill({ contentType: 'text/html', body: '<p>Workspace selected</p>' }));
                const submitted = page.waitForRequest((request) => request.url() === action && request.method() === 'POST');
                await faculty.getByRole('button').click();
                const data = new URLSearchParams((await submitted).postData());
                assert.equal(data.get(kind === 'role' ? 'role' : 'workspace'), 'faculty');
                assert.equal(data.has(kind === 'role' ? 'workspace' : 'role'), false);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

async function switchingPage(browser, kind = 'three') {
    const rendered = selector(kind);
    const originalAction = rendered.match(/<form method="POST" action="([^"]+)" data-workspace-switch/)[1];
    const origin = 'http://athena-switching.test';
    const originalOrigin = new URL(originalAction).origin;
    const action = originalAction.replace(originalOrigin, origin);
    const prefix = new URL(action).pathname.replace(/\/(choose-workspace|select-role)$/, '');
    const dashboard = `${origin}${prefix}/faculty/dashboard`;
    const chooser = rendered.replaceAll(originalOrigin, origin);
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const appEntry = manifest['resources/js/app.js'];
    const allowedAssets = new Set(Object.values(manifest).map((entry) => entry.file));
    const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
    const state = { posts: [], dashboards: 0, documents: 0, bundles: 0, errors: [], responseStatus: 200, responseBody: null, releasePost: null, releaseDocuments: null, pausePost: false };
    let activeWorkspace = 'Faculty';
    page.on('pageerror', (error) => state.errors.push(error.message));
    function html(body) {
        return `<!DOCTYPE html><html><head><meta name="csrf-token" content="${'a'.repeat(40)}"><meta name="app-url" content="${origin}${prefix}">
            <style>${styles}</style>
            <script data-navigate-once>window.livewireScriptConfig = { csrf: '${'a'.repeat(40)}', uri: '/livewire/update', progressBar: '' };</script>
            <script type="module" src="/${appEntry.file}"></script>
            </head><body>${body}</body></html>`;
    }
    await page.route(`${origin}/**`, async (route) => {
        const request = route.request();
        const path = new URL(request.url()).pathname.slice(1);
        if (request.isNavigationRequest()) state.documents++;
        if (allowedAssets.has(path)) {
            if (path === appEntry.file) state.bundles++;
            return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
        }
        if (path === 'documents') {
            await new Promise((resolve) => { state.releaseDocuments = resolve; });
            return route.fulfill({ json: { documents: [{ token: 'old-workspace-paper', label: 'Previous workspace paper' }] } });
        }
        if (request.method() === 'POST') {
            state.posts.push(request);
            if (state.pausePost) await new Promise((resolve) => { state.releasePost = resolve; });
            if (state.responseStatus === 200) {
                activeWorkspace = kind === 'role' ? 'Research Office' : 'Research Projects';
            }
            return route.fulfill({ status: state.responseStatus, json: state.responseBody || { redirect: dashboard } });
        }
        if (request.url() === dashboard) {
            state.dashboards++;
            const contextId = activeWorkspace === 'Faculty' ? 101 : activeWorkspace === 'Research Projects' ? 202 : 303;
            return route.fulfill({ contentType: 'text/html', body: html(`<script>window.athenaResearchAssistantContexts = [{ id: ${contextId}, label: '${activeWorkspace} project' }]; window.athenaResearchAssistantActiveContextId = null; window.athenaResearchAssistantPageActions = [];</script><div x-data data-app-shell data-auth-user-id="1"><h1>${activeWorkspace} workspace</h1><aside x-persist="app-sidebar-${activeWorkspace}">${activeWorkspace} navigation</aside><a wire:navigate href="${action}">Switch Workspace</a></div>`) });
        }
        if (request.url() === action) {
            return route.fulfill({ contentType: 'text/html', body: html(chooser) });
        }
        return route.fulfill({ status: 204, body: '' });
    });
    await page.goto(dashboard);
    await page.waitForFunction(() => window.Livewire?.navigate);
    await page.evaluate(() => { window.workspaceRuntimeSentinel = 'preserved'; });
    await page.getByRole('link', { name: 'Switch Workspace' }).click();
    await page.getByRole('heading', { name: 'Choose your workspace' }).waitFor();
    return { page, state, dashboard, action };
}

test('workspace and legacy role switching reuse the app and fetch a fresh dashboard once', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const kind of ['three', 'role']) {
            const { page, state, dashboard } = await switchingPage(browser, kind);
            state.pausePost = true;
            const key = kind === 'role' ? 'research_coordinator' : 'faculty_researcher';
            await page.evaluate(() => {
                const assistant = window.Alpine.store('researchAssistant');
                assistant.paperContext = { paperSlug: 'detailed-proposal' };
                assistant.proposalDraftId = 51;
                assistant.selectedDocumentToken = 'old-paper';
                document.body.dataset.researchAssistantDocumentsUrl = `${window.location.origin}/documents`;
                window.oldWorkspaceDocuments = assistant.loadDocumentOptions();
            });
            const form = page.locator(`[data-workspace-option="${key}"] form`);
            await form.getByRole('button').click();
            await page.waitForFunction(() => document.querySelector('[data-workspace-selector]').getAttribute('aria-busy') === 'true');
            assert.equal(await form.getByRole('button').textContent(), 'Opening workspace…');
            assert.equal(await page.locator('[data-workspace-switch] button:disabled').count(), kind === 'role' ? 2 : 3);
            await form.evaluate((element) => element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
            await page.waitForTimeout(100);
            assert.equal(state.posts.length, 1);
            const request = state.posts[0];
            assert.equal(request.headers().accept, 'application/json');
            assert.match(request.postData(), new RegExp(`name="${kind === 'role' ? 'role' : 'workspace'}"\\s+${key}`));
            assert.ok(request.postData().includes('a'.repeat(40)));
            state.releasePost();
            await page.getByRole('heading', { name: `${kind === 'role' ? 'Research Office' : 'Research Projects'} workspace` }).waitFor();
            assert.equal(page.url(), dashboard);
            assert.equal(state.dashboards, 2);
            assert.equal(state.documents, 1, 'switch must avoid a document reload');
            assert.equal(state.bundles, 1, 'switch must reuse the loaded app bundle');
            assert.equal(await page.evaluate(() => window.workspaceRuntimeSentinel), 'preserved');
            assert.deepEqual(await page.evaluate(() => {
                const assistant = window.Alpine.store('researchAssistant');
                return { contextId: assistant.selectedContextId, paper: assistant.paperContext, draftId: assistant.proposalDraftId, document: assistant.selectedDocumentToken };
            }), { contextId: kind === 'role' ? 303 : 202, paper: null, draftId: 0, document: '' });
            state.releaseDocuments();
            await page.evaluate(() => window.oldWorkspaceDocuments);
            assert.deepEqual(await page.evaluate(() => window.Alpine.store('researchAssistant').documentOptions), []);
            assert.equal(await page.evaluate(() => window.Alpine.store('researchAssistant').selectedDocumentToken), '');
            assert.deepEqual(state.errors, []);
            await page.goBack();
            await page.getByRole('heading', { name: 'Choose your workspace' }).waitFor();
            assert.equal(state.documents, 2, 'history across a workspace change must get fresh server HTML');
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('failed switches show the server error and permit a single retry without leaving the chooser', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { page, state, action } = await switchingPage(browser);
        const button = page.locator('[data-workspace-option="faculty_researcher"] button[type="submit"]');
        for (const status of [422, 419, 403, 429]) {
            state.responseStatus = status;
            state.responseBody = status === 422 ? { errors: { workspace: ['This workspace is unavailable.'] } } : { message: 'Please try again later.' };
            await button.click();
            const expected = status === 419 ? 'Your session has expired. Refresh this page and sign in again.' : status === 422 ? 'This workspace is unavailable.' : 'Please try again later.';
            await page.getByRole('status').getByText(expected, { exact: true }).waitFor();
            assert.equal(await button.isEnabled(), true);
            assert.equal(await button.textContent(), 'Enter workspace');
            assert.equal(page.url(), action);
            assert.equal(state.dashboards, 1);
            assert.equal(await page.locator('[data-workspace-selector]').getAttribute('aria-busy'), null);
        }
        state.responseStatus = 200;
        state.responseBody = null;
        await button.click();
        await page.getByRole('heading', { name: 'Research Projects workspace' }).waitFor();
        assert.equal(state.posts.length, 5);
        assert.deepEqual(state.errors, []);
    } finally {
        await browser.close();
    }
});
