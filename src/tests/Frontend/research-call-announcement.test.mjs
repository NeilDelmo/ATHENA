import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';

const read = path => readFileSync(path, 'utf8');

test('announcement details have clear sections and fit desktop and mobile in both themes', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchCallWorkflowTest.php', '--filter=research-call announcement groups'], {
        encoding: 'utf8', env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_CALL_ANNOUNCEMENT: '1' },
    });
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const styles = read(`public/build/${manifest['resources/css/app.css'].file}`);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const poster = process.env.ATHENA_CALL_POSTER_IMAGE
        ? readFileSync(process.env.ATHENA_CALL_POSTER_IMAGE)
        : Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="565"><rect width="400" height="565" fill="#800020"/><text x="200" y="150" fill="white" font-size="28" text-anchor="middle">CALL FOR PROPOSALS</text></svg>');
    try {
        for (const [workspace, status] of [['faculty', 'open'], ['faculty', 'scheduled'], ['research_head', 'closed']]) {
            const fixture = read(`storage/framework/testing/research-call-announcement-${workspace}-${status}.html`);
            for (const width of [360, 1440]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 1000 } });
                    await page.route('**/*', route => route.fulfill({ contentType: process.env.ATHENA_CALL_POSTER_IMAGE ? 'image/png' : 'image/svg+xml', body: poster }));
                    const content = await page.evaluate(source => {
                        const doc = new DOMParser().parseFromString(source, 'text/html');
                        return doc.querySelector('[data-research-call-announcement]').parentElement.outerHTML;
                    }, fixture);
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles}</style><body class="bg-slate-100 p-4 font-sans dark:bg-slate-950">${content}</body></html>`);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}px ${status}: no horizontal overflow`);
                    const announcement = page.locator('[data-research-call-announcement]');
                    const header = announcement.locator('header');
                    assert.equal(await header.evaluate(element => getComputedStyle(element).backgroundColor), 'rgb(128, 0, 32)');
                    assert.equal(await announcement.locator('[data-call-guidelines] p').evaluate(element => getComputedStyle(element).whiteSpace), 'normal');
                    for (const selector of ['[data-call-submission-window]', '[data-call-budget]']) {
                        assert.equal(await announcement.locator(selector).evaluate(element => getComputedStyle(element).borderTopWidth), '1px');
                        assert.equal(await announcement.locator(`${selector} svg`).count(), 1);
                    }
                    assert.equal(await page.locator('#milestone-heading').textContent(), 'Submission and review milestones');
                    assert.equal(await page.locator('[aria-labelledby="milestone-heading"] li').count(), 3);
                    const create = announcement.getByRole('link', { name: 'Create proposal', exact: true });
                    assert.equal(await create.count(), workspace === 'faculty' && status === 'open' ? 1 : 0);
                    if (status === 'scheduled') {
                        assert.ok(await announcement.getByText('No poster attached.', { exact: true }).isVisible());
                    } else {
                        assert.equal(await announcement.locator('img').evaluate(element => getComputedStyle(element).objectFit), 'contain');
                    }
                    if (width === 1440) {
                        assert.ok((await announcement.locator('[data-call-guidelines]').boundingBox()).height < 200, 'Guidelines should use the available width');
                    }
                    if (workspace === 'faculty' && status === 'open') {
                        await page.screenshot({ path: `storage/framework/testing/research-call-announcement-${width}-${dark ? 'dark' : 'light'}.png`, fullPage: true });
                    }
                    await page.close();
                }
            }
        }
    } finally { await browser.close(); }
});
