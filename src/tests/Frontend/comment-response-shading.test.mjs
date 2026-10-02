import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium } from '@playwright/test';

test('selected evaluation boxes stay solid black on screen and when printed', async () => {
    const css = readFileSync(new URL('../../resources/css/comment-response-form-print.css', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        const page = await browser.newPage();
        await page.setContent(`<style>${css}</style><ul class="evaluation-levels">
            <li><span class="evaluation-box is-checked" aria-hidden="true"></span>Initial Screening</li>
            <li><span class="evaluation-box" aria-hidden="true"></span>Local Research Evaluation</li>
        </ul>`);

        for (const media of ['screen', 'print']) {
            await page.emulateMedia({ media });
            const boxes = await page.locator('.evaluation-box').evaluateAll((elements) => elements.map((element) => ({
                background: getComputedStyle(element).backgroundColor,
                printColorAdjust: getComputedStyle(element).printColorAdjust,
                text: element.textContent,
            })));
            assert.equal(boxes[0].background, 'rgb(0, 0, 0)');
            assert.equal(boxes[1].background, 'rgb(255, 255, 255)');
            assert.deepEqual(boxes.map((box) => box.text), ['', '']);
            if (media === 'print') assert.equal(boxes[0].printColorAdjust, 'exact');
        }
    } finally {
        await browser.close();
    }
});
