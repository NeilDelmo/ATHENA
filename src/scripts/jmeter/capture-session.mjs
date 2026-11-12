import { chromium } from '@playwright/test';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { createInterface } from 'node:readline/promises';
import { stdin, stdout } from 'node:process';
import { pathToFileURL } from 'node:url';
import { baseUrl, workspaces } from './scenarios.mjs';
import { normalizeBaseUrl } from './athena.mjs';

export async function captureSession(browser, { workspace, target, destination, prompt }) {
    if (!workspaces[workspace]) throw new Error('Choose a supported workspace.');
    target = normalizeBaseUrl(target);
    destination = resolve(destination);
    const host = new URL(target).hostname;
    const belongsToTarget = cookie => host === cookie.domain.replace(/^\./, '') || host.endsWith('.' + cookie.domain.replace(/^\./, ''));
    let saved;
    try {
        saved = JSON.parse(await readFile(destination, 'utf8'));
        saved = { cookies: saved.cookies.filter(belongsToTarget), origins: [] };
    } catch (error) {
        if (error.code !== 'ENOENT') throw new Error('Could not read the saved session. Check its JSON file or choose a new output file.');
    }
    const context = await browser.newContext(saved ? { storageState: saved } : {});
    try {
        const page = await context.newPage();
        const dashboard = target + workspaces[workspace][0];
        const initial = await page.goto(saved ? dashboard : `${target}/login`);
        const needsLogin = !saved || new URL(page.url()).origin !== new URL(target).origin ||
            /\/(login|select-role)\/?$/.test(new URL(page.url()).pathname) || initial?.status() === 401 ||
            await page.locator('a[href$="/auth/google"], form[action$="/login"]').count() > 0;
        if (needsLogin) await prompt(page);
        else if (!initial?.ok()) throw new Error(`The saved session check returned HTTP ${initial?.status()}. Check the website before signing in again.`);
        if (!page.url().startsWith(target + '/')) throw new Error('Finish sign-in and return to ATHENA first.');
        const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        if (!token) throw new Error('No current ATHENA CSRF token.');
        const response = await context.request.post(`${target}/choose-workspace`, {
            data: { workspace }, headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, maxRedirects: 0,
        });
        if (response.status() !== 200) throw new Error('This account cannot select that workspace, or the session expired.');
        const verified = await page.goto(dashboard);
        if (!verified?.ok() || !page.url().startsWith(dashboard)) throw new Error('The expected dashboard did not open.');
        await page.locator(`[${workspaces[workspace][1]}]`).first().waitFor({ state: 'attached' });
        if (await page.locator('body').getAttribute('data-active-workspace') !== workspace) throw new Error('ATHENA is using a different workspace.');
        const actorId = await page.locator('body').getAttribute('data-auth-user-id');
        const state = await context.storageState();
        state.cookies = state.cookies.filter(belongsToTarget);
        state.origins = [];
        await mkdir(dirname(destination), { recursive: true });
        await writeFile(destination, JSON.stringify(state, null, 2) + '\n', { mode: 0o600 });
        return { workspace, actorId, sessionFile: destination, reused: !needsLogin };
    } finally {
        await context.close();
    }
}

async function main() {
    const args = process.argv.slice(2);
    const option = (key, fallback) => args.includes(key) ? args[args.indexOf(key) + 1] : fallback;
    const workspace = option('--workspace', 'research_head');
    if (!workspaces[workspace]) throw new Error('Choose a supported workspace.');
    const target = normalizeBaseUrl(option('--base-url', baseUrl));
    const destination = resolve(option('--out', `scripts/jmeter/private/${workspace}-01.json`));
    const browser = await chromium.launch({ headless: false, channel: option('--channel', undefined) });
    let terminal;
    try {
        const result = await captureSession(browser, { workspace, target, destination, prompt: async () => {
            terminal = createInterface({ input: stdin, output: stdout });
            await terminal.question('No active saved sign-in. Sign in with the dedicated test account, finish any role selection, and open ATHENA. Then press Enter here. ');
        } });
        console.log(`${result.reused ? 'Reused and refreshed' : 'Saved'} ATHENA-only session at ${destination}. Keep it private and use a separate session for each virtual user.`);
        if (result.actorId) console.log(`Mixed-role pool entry: ${JSON.stringify({ workspace, actorId: result.actorId, sessionFile: destination, recordSets: [] })}`);
    } finally {
        terminal?.close();
        await browser.close();
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().catch(error => { console.error(error.message); process.exitCode = 1; });
}
