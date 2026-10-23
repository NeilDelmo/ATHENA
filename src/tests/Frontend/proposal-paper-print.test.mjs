import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium } from '@playwright/test';

test('proposal papers print at full size without changing the preview zoom', async (context) => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const [paper, pageClass, sheetClass] of [
            ['work-plan', 'work-plan-preview-page', 'work-plan-sheet'],
            ['line-item-budget', 'line-budget-preview-page', 'line-budget-sheet'],
            ['curriculum-vitae', 'cv-preview-page', 'cv-sheet'],
        ]) {
            await context.test(paper, async () => {
                const page = await browser.newPage();

                try {
                    const styles = readFileSync(new URL(`../../resources/css/${paper}-print.css`, import.meta.url), 'utf8');
                    await page.setContent(`<style>${styles}</style><body class="${pageClass}"><main class="${sheetClass}">Proposal paper</main></body>`);

                    for (const zoom of ['0.35', '1.5']) {
                        await page.evaluate(value => { document.body.style.zoom = value; }, zoom);
                        await page.emulateMedia({ media: 'print' });
                        assert.equal(await page.evaluate(() => getComputedStyle(document.body).zoom), '1');
                        await page.emulateMedia({ media: 'screen' });
                        assert.equal(await page.evaluate(() => getComputedStyle(document.body).zoom), zoom);
                    }
                } finally {
                    await page.close();
                }
            });
        }
    } finally {
        await browser.close();
    }
});
