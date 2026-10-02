import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const config = loadConfig(fileURLToPath(new URL('../../tailwind.config.js', import.meta.url)));
const source = await readFile(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const { css } = await postcss([tailwindcss(config)]).process(source, { from: undefined });
const topic = await readFile(new URL('../../resources/views/topics/show.blade.php', import.meta.url), 'utf8');
const journalAction = topic.match(/<a data-find-journals[\s\S]*?<\/a>/)[0];
const report = await readFile(new URL('../../resources/views/components/narrative-report-summary.blade.php', import.meta.url), 'utf8');
const reportClasses = report.match(/<a[^\n]*?class="([^"]+)"/)[1];

test('filled actions match Revise tool while table actions retain their original colors in both themes', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 700 } });
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css}</style></head><body>
                    <div style="padding:16px">${journalAction}</div>
                    <a id="report" class="${reportClasses}">Open report</a>
                    <button id="revision" class="bg-red-700 text-white hover:bg-red-800">Revise tool</button>
                    <button id="dashboard" class="dashboard-action">Dashboard action</button>
                    <button id="rh" class="rh-button">Research Head action</button>
                    <button id="literal" class="bg-[#7A0019] text-white">Create proposal</button>
                    <section class="bg-brand" id="panel">Brand panel</section>
                    <button id="secondary" class="bg-white text-gray-700 dark:bg-slate-900">Secondary</button>
                    <table><tbody><tr><td><button id="table" class="bg-brand text-white hover:bg-brand-soft">Table action</button>
                    <button id="table-rh" class="rh-button">Table component</button></td></tr></tbody></table>
                    <div role="table"><div role="row"><button id="aria-table" class="bg-brand text-white">ARIA table</button></div></div>
                </body></html>`);

                const background = (selector) => page.locator(selector).evaluate((element) => getComputedStyle(element).backgroundColor);
                const red = await background('#revision');
                for (const selector of ['#report', '#dashboard', '#rh', '#literal', '[data-find-journals]']) {
                    await expect(page.locator(selector)).toHaveCSS('background-color', red);
                    assert.equal(await page.locator(selector).evaluate((element) => getComputedStyle(element).color), 'rgb(255, 255, 255)');
                    await page.locator(selector).hover();
                    await expect(page.locator(selector)).toHaveCSS('background-color', 'rgb(153, 27, 27)');
                    await page.mouse.move(width - 1, 699);
                }
                for (const selector of ['#table', '#table-rh', '#aria-table', '#panel']) {
                    assert.equal(await background(selector), 'rgb(122, 0, 25)', `${selector} must retain maroon`);
                }
                await page.locator('#table').hover();
                assert.equal(await background('#table'), 'rgb(159, 18, 57)');
                assert.equal(await background('#secondary'), dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                const bounds = await page.locator('[data-find-journals]').boundingBox();
                assert.ok(bounds.height >= 48);
                assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
                await page.locator('[data-find-journals]').focus();
                assert.equal(await page.locator('[data-find-journals]').evaluate((element) => getComputedStyle(element).getPropertyValue('--tw-ring-color').trim()), 'rgb(220 38 38 / 1)');
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
