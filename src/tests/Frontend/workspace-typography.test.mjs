import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const view = path => readFileSync(`resources/views/${path}.blade.php`, 'utf8');
const header = view('components/page-header').match(/<h[12]\b[^>]*>[\s\S]*?<\/h[12]>/g).join('');
const calls = view('research_calls/faculty-index').match(/<h3 id="faculty-research-call-heading"[^>]*>[\s\S]*?<\/h3>/)[0];
const comments = view('topics/file-annotations').match(/<h3 id="annotation-comments-heading"[^>]*>[\s\S]*?<\/h3>/)[0];
const tables = view('components/terminal-report-tables').match(/<h3 id="terminal-tables-heading"[^>]*>[\s\S]*?<\/h3>/)[0];
const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
    .process(readFileSync('resources/css/app.css', 'utf8'), { from: undefined });

test('faculty headings, review tools and report tables use consistent workspace typography', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 800 } });
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${css}</style>
                    <body data-faculty-shell class="font-sans">${header}${calls}${comments}${tables}
                        <button class="pdf-annotation-pin">1</button><span class="pdf-annotation-number">2</span>
                    </body></html>`);
                const styles = await page.locator('h1, h2, h3, .pdf-annotation-pin, .pdf-annotation-number')
                    .evaluateAll(elements => elements.map(element => {
                        const style = getComputedStyle(element);
                        return { family: style.fontFamily, weight: style.fontWeight, size: style.fontSize };
                    }));
                const bodyFont = await page.locator('body').evaluate(element => getComputedStyle(element).fontFamily);
                assert.match(bodyFont, /^"?Plus Jakarta Sans"?,/);
                for (const style of styles) {
                    assert.equal(style.family, bodyFont, 'UI text must inherit the shared workspace typeface');
                    assert.equal(style.weight, '700', 'Bold UI text must not jump to a heavier font on other pages');
                }
                assert.equal(styles[0].size, styles[1].size, 'Dashboard and regular page titles must have the same scale');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
