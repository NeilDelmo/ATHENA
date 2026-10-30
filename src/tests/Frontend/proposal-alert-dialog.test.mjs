import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('proposal notifications show compact branded feedback and preserve Undo and dismissal', async t => {
    const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
    const theme = app.slice(app.indexOf('function proposalDialogTheme('), app.indexOf('async function showProposalConfirmation('));
    const start = app.indexOf('async function initializeProposalAlerts(');
    const initialize = app.slice(start, app.indexOf('void initializeProposalAlerts();', start));
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [360, 1280]) {
            for (const dark of [false, true]) {
                await t.test(`${width}px ${dark ? 'dark' : 'light'}`, async () => {
                    const page = await browser.newPage({ viewport: { width, height: 800 }, reducedMotion: 'reduce' });
                    await page.route('http://athena.test/drafts/42/restore', route => route.fulfill({ body: 'Draft restored.' }));
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><body class="font-sans">
                        <div data-proposal-alert data-alert-icon="success" data-alert-title="Draft moved to Trash" data-alert-button="Back to drafts">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span>You can restore this proposal draft from Trash within 30 days.</span>
                                <form data-proposal-alert-undo method="POST" action="http://athena.test/drafts/42/restore">
                                    <input type="hidden" name="_token" value="test-csrf-token">
                                    <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-stone-300 px-4 font-semibold text-brand"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7 4-4 4 4 4M3 8h10a7 7 0 1 1 0 14" /></svg>Undo</button>
                                </form>
                            </div>
                        </div></body></html>`);
                    await page.addScriptTag({ path: resolve('node_modules/sweetalert2/dist/sweetalert2.all.min.js') });
                    await page.addStyleTag({ content: css });
                    await page.addScriptTag({ content: `${theme}\n${initialize}\nvoid initializeProposalAlerts();` });
                    const dialog = page.getByRole('dialog', { name: 'Draft moved to Trash' });
                    await dialog.waitFor();
                    await dialog.locator('.swal2-actions form').waitFor({ state: 'visible' });
                    await dialog.evaluate(element => Promise.all(element.getAnimations().map(animation => animation.finished.catch(() => {}))));
                    assert.equal(await dialog.evaluate(element => element.classList.contains('athena-alert-dialog')), true);
                    const dismiss = dialog.getByRole('button', { name: 'Back to drafts' });
                    const undo = dialog.getByRole('button', { name: 'Undo', exact: true });
                    assert.equal(await dialog.locator('.swal2-actions form').count(), 1);
                    assert.equal(await dialog.locator('.swal2-success-ring').count(), 0);
                    assert.equal(await dialog.locator('.swal2-icon svg').count(), 1);
                    const bounds = await dialog.boundingBox();
                    assert.ok(bounds.height <= 340 && bounds.width <= width - 24, 'Feedback should remain compact and fit the screen');
                    assert.ok(Math.abs((await undo.boundingBox()).y - (await dismiss.boundingBox()).y) <= 2, 'Undo and dismissal should share the footer');
                    for (const button of [undo, dismiss]) assert.ok((await button.boundingBox()).height >= 44);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    if (width === 1280) {
                        await dialog.screenshot({ path: resolve(`storage/framework/testing/proposal-trash-dialog-${dark ? 'dark' : 'light'}.png`) });
                    }
                    if (dark) {
                        const request = page.waitForRequest('http://athena.test/drafts/42/restore');
                        await undo.click();
                        const restore = await request;
                        assert.equal(restore.method(), 'POST');
                        assert.equal(new URLSearchParams(restore.postData()).get('_token'), 'test-csrf-token');
                    } else {
                        await dismiss.click();
                        await dialog.waitFor({ state: 'hidden' });
                        await page.evaluate(() => void initializeProposalAlerts());
                        assert.equal(await page.getByRole('dialog').count(), 0, 'Handled notifications must not reopen');
                    }
                    await page.close();
                });
            }
        }
    } finally {
        await browser.close();
    }
});
