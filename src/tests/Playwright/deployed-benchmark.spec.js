import { expect, test } from '@playwright/test';
import { performance } from 'node:perf_hooks';
import { savedSession, workspaces } from '../../scripts/playwright/deployed-environment.mjs';

const pages = {
    faculty: ['faculty/dashboard', 'faculty/proposal-drafts', 'faculty/submissions', 'research-calls', 'research-support'],
    faculty_researcher: ['faculty/dashboard', 'research', 'research-outcomes'],
    research_head: ['research-head/dashboard', 'research-head/proposal-submissions', 'research-head/projects',
        'research-head/report-reviews', 'research-head/budgets', 'research-head/quarterly-accomplishment-reports',
        'research-head/completed-projects', 'research-outcomes'],
    research_office: ['research-coordinator/dashboard', 'research-coordinator/proposals', 'research-coordinator/reports', 'research-outcomes'],
    research_secretary: ['research-secretary/dashboard', 'research-secretary/reports', 'research-secretary/budgets'],
    vcrdes: ['vcrdes/dashboard'],
};
const median = values => [...values].sort((a, b) => a - b)[Math.floor(values.length / 2)];
const rounded = value => Math.round(value * 10) / 10;

for (const [workspace, paths] of Object.entries(pages)) {
    test.describe(workspace, () => {
        test.describe.configure({ mode: 'serial' });
        let context;
        let page;
        test.beforeAll(async ({ browser, baseURL }) => {
            const state = savedSession(baseURL);
            expect(state, 'No saved account sign-in. Run npm run test:e2e:deployed:session.').not.toBeNull();
            context = await browser.newContext({ storageState: state, baseURL, viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
            page = await context.newPage();
            const selection = await page.goto('choose-workspace');
            expect(selection?.status()).toBe(200);
            expect(new URL(page.url()).pathname, 'Saved account sign-in expired. Refresh it with the session command.').toBe('/choose-workspace');
            const workspaceForm = page.locator('form').filter({ has: page.locator(`input[name="workspace"][value="${workspace}"]`) });
            test.skip(await workspaceForm.count() === 0, `The supplied account does not have the ${workspace} workspace.`);
            const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
            const select = () => context.request.post('choose-workspace', {
                form: { workspace }, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, maxRedirects: 0,
            });
            let selected = await select();
            if (selected.status() === 429) {
                const cooldown = Number(selected.headers()['retry-after']);
                expect(cooldown, 'The workspace setup rate limit must provide a cooldown of at most 60 seconds.').toBeGreaterThan(0);
                expect(cooldown).toBeLessThanOrEqual(60);
                console.log(`Waiting ${cooldown}s for the server workspace cooldown; excluded from navigation timings.`);
                await new Promise(resolve => setTimeout(resolve, cooldown * 1000 + 250));
                selected = await select();
            }
            expect(selected.status(), 'Select the permitted workspace before measuring navigation.').toBe(200);
        });
        test.afterAll(async () => { await context?.close(); });
        for (const path of paths) {
            test(`${workspace}: ${path}`, async ({ browser, baseURL }, testInfo) => {
                const samples = [];
                let actorId;
                for (let visit = 0; visit < 4; visit++) {
                    const start = performance.now();
                    const response = await page.goto(path, { waitUntil: 'load' });
                    expect(response?.status(), `HTTP status for ${workspace}: ${path}`).toBe(200);
                    expect(new URL(page.url()).pathname, `Navigation left ${path}. A redirect or workspace change invalidates this measurement.`).toBe(new URL(path, baseURL).pathname);
                    await expect(page.locator('body')).toHaveAttribute('data-active-workspace', workspace);
                    await expect(page.locator('body')).toHaveAttribute('data-auth-user-id', /^\d+$/);
                    await expect(page.locator(`[${workspaces[workspace][1]}]`).first()).toBeVisible();
                    await expect(page.getByRole('main').first()).toBeVisible();
                    const readyMs = performance.now() - start;
                    actorId ??= await page.locator('body').getAttribute('data-auth-user-id');
                    await expect(page.locator('body')).toHaveAttribute('data-auth-user-id', actorId);
                    if (visit > 0) {
                        const timing = await page.evaluate(() => {
                            const entry = performance.getEntriesByType('navigation')[0];
                            return { ttfbMs: entry.responseStart - entry.requestStart, domContentLoadedMs: entry.domContentLoadedEventEnd - entry.startTime };
                        });
                        samples.push({ readyMs: rounded(readyMs), ...Object.fromEntries(Object.entries(timing).map(([key, value]) => [key, rounded(value)])) });
                    }
                }
                await testInfo.attach('browser-timings', { contentType: 'application/json', body: JSON.stringify({
                    workspace, page: path, target: baseURL, browser: browser.version(), samples,
                    medianReadyMs: median(samples.map(sample => sample.readyMs)),
                    minReadyMs: Math.min(...samples.map(sample => sample.readyMs)),
                    maxReadyMs: Math.max(...samples.map(sample => sample.readyMs)),
                    medianTtfbMs: median(samples.map(sample => sample.ttfbMs)),
                    medianDomContentLoadedMs: median(samples.map(sample => sample.domContentLoadedMs)),
                }) });
            });
        }
    });
}
