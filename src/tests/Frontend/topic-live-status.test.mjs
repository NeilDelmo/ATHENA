import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('submitting a prepared report replaces its quarter control even when its ID stays the same', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        let submitted = false;
        let requests = 0;
        await page.route('https://athena.test/**', route => {
            const path = new URL(route.request().url()).pathname;
            if (path.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js/topic-live-status.js'), 'utf8') });
            if (path === '/status') {
                requests++;
                return route.fulfill({ json: { workspace: 'faculty_researcher', proposal: { scope: 'approved', label: 'Approved', actions: [] },
                    reports: Object.fromEntries(['progress', 'monitoring'].map(kind => [`${kind}:8`, {
                        kind, quarter: 1, scope: submitted ? 'submitted' : 'prepared', current: true,
                        url: submitted ? `/${kind}/8` : `/prepare/${kind}/8`, label: submitted ? 'Awaiting review' : 'PDF prepared', status: 'pending',
                        compact_html: `<span><span>${submitted ? 'Pending' : 'Prepared'}</span></span>`, actions: [],
                    }])) } });
            }
            return route.fulfill({ contentType: 'text/html', body: `<main data-topic-live-status data-status-url="/status" data-workspace="faculty_researcher"
                data-live-snapshot='{"proposal":"approved","reports":{"progress:8":"prepared","monitoring:8":"prepared"}}'>
                <input name="notes" value="Unsaved notes">
                ${['progress', 'monitoring'].map(kind => `<section data-live-quarter-kind="${kind}" data-live-quarter="1" data-live-report-key="${kind}:8" data-live-report-scope="prepared">
                    <div data-report-controls><a href="/prepare/${kind}/8">Submit prepared ${kind}<span class="report-review-status">Prepared</span></a></div></section>`).join('')}</main>` });
        });
        await page.goto('https://athena.test/topic');
        await page.addScriptTag({ type: 'module', content: "import init from '/live.js'; window.disposeLive = init();" });
        await expect.poll(() => requests).toBe(1);
        await expect(page.getByRole('link', { name: /Submit prepared/ })).toHaveCount(2);
        submitted = true;
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        for (const [kind, label] of [['progress', 'Progress Report'], ['monitoring', 'Monitoring Tool']]) {
            const link = page.getByRole('link', { name: `View latest ${label}`, exact: true });
            await expect(link).toHaveAttribute('href', `https://athena.test/${kind}/8`);
            await expect(link).toContainText('Pending');
            await expect(link).not.toHaveAttribute('aria-disabled', 'true');
        }
        await expect(page.getByRole('link', { name: /Submit prepared/ })).toHaveCount(0);
        await expect(page.locator('[name="notes"]')).toHaveValue('Unsaved notes');
        const view = page.getByRole('link', { name: 'View latest Progress Report', exact: true });
        await view.focus();
        const count = requests;
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        await expect.poll(() => requests).toBeGreaterThan(count);
        await expect(view).toBeFocused();
    } finally {
        await browser.close();
    }
});

test('live statuses update badges and actions while retaining unsaved forms and blocking stale submits', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let scope = 'original';
    let workspace = 'research_head';
    let requests = 0;
    await page.route('https://athena.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js/topic-live-status.js'), 'utf8') });
        if (path === '/status') {
            requests++;
            return route.fulfill({ json: { workspace, proposal: { scope, label: scope === 'original' ? 'Research Head review' : 'LREC review', actions: [] }, reports: {
                'monitoring:4': { scope, current: true, label: scope === 'original' ? 'Awaiting review' : 'Reviewed', status: scope === 'original' ? 'pending' : 'reviewed',
                    html: '<span class="report-review-status"><svg></svg><span>Reviewed</span></span>', compact_html: '<span class="report-review-status"><svg></svg><span>Accepted</span></span>', actions: [] },
                ...(scope === 'original' ? {} : { 'progress:8': { scope: 'new', current: true, kind: 'progress', quarter: 1, url: '/report/8', label: 'Awaiting review', status: 'pending',
                    compact_html: '<span class="report-review-status"><span>Pending</span></span>', actions: [{ label: 'Review Progress Report', url: '/report/8' }] } }),
            } } });
        }
        return route.fulfill({ contentType: 'text/html', body: `<span data-live-proposal-status>Research Head review</span>
            <main data-topic-live-status data-status-url="/status" data-workspace="research_head"
                data-live-snapshot='{"proposal":"original","reports":{"monitoring:4":"original"}}'>
                <section data-live-status-notice hidden role="status"><p data-live-status-message></p><div data-live-status-actions></div></section>
                <span data-live-report-status="monitoring:4" data-compact="true"><span>Pending</span></span>
                <section data-live-report-key="monitoring:4" data-live-report-scope="original"><form method="POST" action="/review"><textarea name="remarks">Initial notes</textarea><button>Save review</button></form></section>
                <form method="POST"><input name="title" value="Initial title"><button>Save proposal</button></form>
                <div data-live-quarter-kind="progress" data-live-quarter="1" data-live-report-key=""><div data-report-controls><a href="/create">Start report</a></div></div>
            </main>` });
    });
    try {
        await page.goto('https://athena.test/topic');
        await page.clock.install();
        await page.addScriptTag({ type: 'module', content: "import init from '/live.js'; window.disposeLive=init(); window.liveLoaded=true;" });
        await page.waitForFunction(() => window.liveLoaded);
        await expect.poll(() => requests).toBe(1);
        await page.locator('[name="remarks"]').fill('My unsaved review');
        await page.locator('[name="title"]').fill('My unsaved title');
        scope = 'changed';
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('athena-workspace-actions')));
        await expect(page.locator('[data-live-proposal-status]')).toHaveText('LREC review');
        await expect(page.locator('[data-live-report-status="monitoring:4"]')).toContainText('Accepted');
        await expect(page.locator('[name="remarks"]')).toHaveValue('My unsaved review');
        await expect(page.locator('[name="title"]')).toHaveValue('My unsaved title');
        await expect(page.getByRole('button', { name: 'Save review' })).toBeDisabled();
        await expect(page.getByRole('button', { name: 'Save proposal' })).toBeDisabled();
        await expect(page.getByRole('link', { name: 'Review Progress Report' })).toHaveAttribute('href', 'https://athena.test/report/8');
        await expect(page.getByRole('link', { name: 'View latest Progress Report' })).toHaveAttribute('href', 'https://athena.test/report/8');
        await expect(page.getByRole('link', { name: 'Start report' })).toHaveCount(0);
        await expect(page.locator('[data-live-quarter-kind]')).toContainText('Pending');
        assert.equal(await page.locator('textarea').isEditable(), true);
        const previous = requests;
        await page.clock.fastForward(30000);
        await expect.poll(() => requests).toBeGreaterThan(previous);
        workspace = 'faculty';
        scope = 'other-workspace';
        await page.evaluate(() => window.dispatchEvent(new Event('focus')));
        await expect(page.locator('[data-live-proposal-status]')).toHaveText('LREC review');
        assert.deepEqual(errors, []);
        await page.evaluate(() => window.disposeLive());
    } finally {
        await browser.close();
    }
});

test('live refresh preserves the existing layout and status styles until the corresponding record changes', { timeout: 60000 }, async () => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const styles = [manifest['resources/css/app.css'].file, ...(manifest['resources/js/app.js'].css || [])]
        .map(path => readFileSync(resolve('public/build', path), 'utf8')).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1280]) for (const dark of [false, true]) {
            const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
            let changed = false;
            let requestCount = 0;
            let nextScope = 'new-progress';
            const reports = () => ({
                'monitoring:4': { scope: changed ? 'reviewed' : 'original', current: true, kind: 'monitoring', quarter: 1,
                    status: changed ? 'reviewed' : 'pending', label: changed ? 'Accepted by Research Head' : 'Awaiting Research Head review',
                    html: '<span class="report-review-status"><svg></svg><span>Accepted by Research Head</span></span>',
                    compact_html: '<span class="report-review-status"><svg></svg><span>Accepted</span></span>', actions: [] },
                ...(changed ? { 'progress:8': { scope: nextScope, current: true, kind: 'progress', quarter: 1, url: '/report/8',
                    status: 'pending', label: 'Awaiting review', compact_html: '<span><svg></svg><span>Pending</span></span>', actions: [] } } : {}),
            });
            await page.route('https://athena.test/**', route => {
                const path = new URL(route.request().url()).pathname;
                if (path.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: readFileSync('resources/js/topic-live-status.js', 'utf8') });
                if (path === '/status') {
                    requestCount++;
                    return route.fulfill({ json: { workspace: 'research_head', proposal: { scope: 'original', label: 'Project monitoring', class: 'text-red-700',
                        actions: [{ label: 'Open current project', url: '/project' }] }, reports: reports() } });
                }
                return route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><style>${styles}</style><body class="bg-white p-4 dark:bg-slate-950"><header><span data-live-proposal-status hidden></span></header>
                    <main class="space-y-6" data-topic-live-status data-status-url="/status" data-workspace="research_head" data-live-snapshot='{"proposal":"original","reports":{"monitoring:4":"original"}}'>
                        <div data-live-status-notice hidden role="status"><p data-live-status-message></p><div data-live-status-actions></div></div>
                        <section data-live-report-key="monitoring:4" data-live-report-scope="original"><p data-live-report-status="monitoring:4" data-live-report-style="plain" class="inline-flex items-center gap-2 text-sm text-slate-500"><span data-live-report-indicator aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-amber-400"></span><span data-live-report-label>Awaiting Research Head review</span></p></section>
                        <div class="grid grid-cols-2 gap-2"><div data-live-quarter-kind="monitoring" data-live-quarter="1" data-live-report-key="monitoring:4"><div data-report-controls><button class="monitoring-report-control quarterly-report-primary"><span>View</span><svg aria-hidden="true" class="h-5 w-5"></svg><span class="report-review-status" data-status="pending" data-overdue="true" title="PDF prepared · Overdue">Prepared</span></button></div></div>
                        <div data-live-quarter-kind="progress" data-live-quarter="1" data-live-report-key=""><div data-report-controls><a href="/create" class="monitoring-report-control quarterly-report-primary"><span>Start</span><svg aria-hidden="true" class="h-5 w-5"></svg><span class="report-review-status">Missing</span></a></div></div></div>
                        <label class="block">Review notes<textarea class="block w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white" name="remarks">Keep these notes</textarea></label>
                    </main></body></html>` });
            });
            await page.goto('https://athena.test/project');
            const baseline = await page.screenshot();
            const badgeAppearance = () => page.locator('[data-live-quarter-kind="monitoring"] .report-review-status').evaluate(element => ({
                html: element.innerHTML, className: element.className, title: element.title, overdue: element.dataset.overdue,
            }));
            const initialBadge = await badgeAppearance();
            await page.addScriptTag({ type: 'module', content: "import init from '/live.js'; window.disposeUiStatus=init();" });
            await expect.poll(() => requestCount).toBe(1);
            await page.waitForTimeout(100);
            assert.ok((await page.screenshot()).equals(baseline), `An unchanged refresh must preserve the ${width}px ${dark ? 'dark' : 'light'} screen`);
            assert.deepEqual(await badgeAppearance(), initialBadge);
            changed = true;
            await page.evaluate(() => window.dispatchEvent(new CustomEvent('athena-workspace-actions')));
            await expect(page.locator('[data-live-report-style="plain"]')).toContainText('Accepted by Research Head');
            await expect(page.locator('[data-live-report-style="plain"] svg')).toHaveCount(0);
            await expect(page.locator('[data-live-report-indicator]')).toHaveClass(/bg-emerald-500/);
            await expect(page.locator('[data-live-proposal-status]')).toBeHidden();
            const view = page.locator('[data-live-quarter-kind="progress"] a');
            await expect(view).toHaveClass(/monitoring-report-control quarterly-report-primary/);
            await expect(view.locator('.report-review-status')).toContainText('Pending');
            await expect(view.locator(':scope > svg')).toHaveCount(1);
            await expect(page.locator('[name="remarks"]')).toHaveValue('Keep these notes');
            const latest = page.getByRole('link', { name: 'Open current project' });
            await latest.focus();
            nextScope = 'another-progress-change';
            const previousRequests = requestCount;
            await page.evaluate(() => window.dispatchEvent(new CustomEvent('athena-workspace-actions')));
            await expect.poll(() => requestCount).toBeGreaterThan(previousRequests);
            await page.waitForTimeout(100);
            await expect(latest).toBeFocused();
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await page.screenshot({ path: resolve(`storage/framework/testing/live-status-ui-${width}-${dark ? 'dark' : 'light'}.png`) });
            await page.evaluate(() => window.disposeUiStatus());
            await page.close();
        }
    } finally { await browser.close(); }
});
