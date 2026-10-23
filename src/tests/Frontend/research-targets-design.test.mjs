import assert from 'node:assert/strict';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const read = path => readFileSync(resolve(path), 'utf8');
const screenshotDirectory = resolve(process.env.ATHENA_TARGET_SCREENSHOTS || 'tmp/research-targets-design');

function builtStyles() {
    const manifest = JSON.parse(read('public/build/manifest.json'));
    return [...new Set([manifest['resources/css/app.css'].file, ...(manifest['resources/js/app.js'].css || [])])]
        .map(file => read(`public/build/${file}`)).join('\n');
}

async function render(page, name, dark, styles, alpine = false) {
    await page.route('**/*', route => {
        const imagePath = new URL(route.request().url()).pathname.match(/\/images\/([\w.-]+)$/);
        const localImage = imagePath && resolve('public/images', imagePath[1]);
        return localImage && existsSync(localImage) ? route.fulfill({ path: localImage }) : route.abort();
    });
    const fixture = await page.evaluate(({ source, fullPage }) => {
        const document = new DOMParser().parseFromString(source, 'text/html');
        document.querySelectorAll('script, link').forEach(element => element.remove());
        return fullPage ? document.documentElement.outerHTML : document.body.innerHTML;
    }, { source: read(`storage/framework/testing/target-board-${name}.html`), fullPage: name === 'page' });
    const html = name === 'page' ? fixture : `<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body data-app-shell class="bg-[#F5F7FA] font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <div class="pl-[76px] sm:pl-[280px]"><main class="px-4 py-6 sm:px-6 lg:px-8">${fixture}</main></div>
        </body></html>`;
    await page.setContent(html);
    await page.evaluate(isDark => document.documentElement.classList.toggle('dark', isDark), dark);
    await page.addStyleTag({ content: `${styles}\n[x-cloak]{display:none!important}` });
    if (alpine) await page.addScriptTag({ content: read('node_modules/alpinejs/dist/cdn.min.js') });
    const board = page.locator('[data-research-target-board], [data-dashboard-target-summary]').first();
    await expect(board).toBeVisible();
    return board;
}

async function assertFits(page, board, width, description) {
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${description} overflows the document at ${width}px`);
    const bounds = await board.boundingBox();
    assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, `${description} must fit the available workspace`);
    const clipped = await board.locator('h2, h3, th, td, summary, button, select, input:visible').evaluateAll(elements => elements
        .filter(element => element.getClientRects().length && element.getBoundingClientRect().width > 0)
        .filter(element => {
            const bounds = element.getBoundingClientRect();
            return bounds.left < 0 || bounds.right > innerWidth + 1;
        }).map(element => element.id || element.textContent.trim()));
    assert.deepEqual(clipped, [], `${description} content must stay readable without horizontal scrolling`);
}

test('research targets remain readable across workspace sizes and themes without exposing institutional goals to colleges', { timeout: 120000 }, async context => {
    const styles = builtStyles();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    mkdirSync(screenshotDirectory, { recursive: true });

    try {
        for (const width of [390, 768, 1024, 1440]) for (const dark of [false, true]) {
            for (const name of ['office', 'coordinator', 'compact', 'empty', 'page']) {
                context.diagnostic(`${name}: ${width}px, ${dark ? 'dark' : 'light'}`);
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                const board = await render(page, name, dark, styles);
                await assertFits(page, board, width, name);
                await expect(board).toHaveCSS('background-color', dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                const heading = board.locator('h2').first();
                await expect(heading).toHaveCSS('color', dark ? 'rgb(255, 255, 255)' : 'rgb(2, 6, 23)');
                assert.ok(await board.locator('#research-target-year').evaluate(select => {
                    const style = getComputedStyle(select);
                    const canvas = document.createElement('canvas').getContext('2d');
                    canvas.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
                    return canvas.measureText(select.selectedOptions[0].text).width <= select.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
                }), `The selected academic year must remain fully readable in ${name} at ${width}px`);

                if (name === 'office' || name === 'page') {
                    if (name === 'office' && width === 1440) {
                        const height = (await board.boundingBox()).height;
                        context.diagnostic(`Desktop target board: ${height}px tall`);
                        assert.ok(height <= 850, 'The annual target board should fit eleven indicators in a compact desktop view');
                    }
                    const overview = board.locator('[data-target-plan-overview]');
                    await expect(overview).toBeVisible();
                    assert.deepEqual(await overview.locator('dd').allTextContents().then(values => values.map(value => value.trim())), ['11', '10', '2']);
                    await expect(board.locator('[data-target-indicator="publications"] [data-target-overall]')).toHaveText('6');
                    await expect(board.locator('[data-target-indicator="new_faculty"] [data-target-overall]')).toHaveText('0');
                    await expect(board.locator('[data-target-indicator="students_ylp"]')).toContainText('0');
                    await expect(board.locator('[data-target-indicator="faculty_engaged"] [data-target-overall]')).toHaveText('75.5%');
                    await expect(board.locator('[data-target-indicator="funded_collaborations"] [data-target-overall]')).toHaveText('Not set');
                    if (name === 'office' && [390, 1440].includes(width)) {
                        await board.screenshot({ path: resolve(screenshotDirectory, `targets-board-${width === 1440 ? '' : `${width}-`}${dark ? 'dark' : 'light'}.png`) });
                    }
                    const table = board.locator('table');
                    assert.ok(await table.evaluate(element => element.scrollWidth <= element.clientWidth + 1), 'All target columns must fit instead of requiring a horizontal table scroll');
                    const edit = board.getByRole('button', { name: 'Edit targets', exact: true });
                    await edit.focus();
                    await expect(edit).toBeFocused();
                    assert.ok((await edit.boundingBox()).height >= 44);
                    if (name === 'office') {
                        const publication = board.locator('[data-target-indicator="publications"]');
                        await publication.locator('[data-target-overall]').evaluate(element => { element.textContent = '1,000,000'; });
                        await publication.locator('[title] > span').evaluateAll(elements => {
                            elements[0].textContent = 'CABEIHM';
                            elements[1].textContent = '1,000,000';
                            elements[3].textContent = '0';
                        });
                        const clippedValues = await publication.locator('[data-target-overall], [title] > span').evaluateAll(elements => elements.filter(element => {
                            const bounds = element.getBoundingClientRect();
                            const parent = element.parentElement.getBoundingClientRect();
                            return element.scrollWidth > element.clientWidth + 1 && element.clientWidth > 0
                                || bounds.left < parent.left || bounds.right > parent.right + 1;
                        }).map(element => element.textContent));
                        assert.deepEqual(clippedValues, [], `Maximum allowed counts and long college codes must fit at ${width}px`);
                        const longIndicator = board.locator('[data-target-indicator="industry_collaborations"] th');
                        assert.ok(await longIndicator.evaluate(element => element.scrollWidth <= element.clientWidth + 1), 'Long indicator names must wrap without clipping');
                        await assertFits(page, board, width, 'maximum target values');
                    }
                    if (name === 'page' && [390, 1440].includes(width)) {
                        await page.screenshot({ path: resolve(screenshotDirectory, `targets-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                    }
                } else if (name === 'coordinator') {
                    await expect(board.locator('[data-target-plan-overview]')).toHaveCount(0);
                    await expect(board).not.toContainText('Overall target');
                    await expect(board).not.toContainText('College allocations');
                    await expect(board).toContainText('12');
                    await expect(board).toContainText('60.5%');
                    for (const privateValue of ['876543', '876,543', '765432', '765,432', '94.25', '83.25']) await expect(board).not.toContainText(privateValue);
                    await expect(board.getByRole('button', { name: /Edit targets|Set targets|Add year/ })).toHaveCount(0);
                } else if (name === 'compact') {
                    await expect(board.locator('[data-target-plan-overview]')).toHaveCount(0);
                    await expect(board.locator('table')).toHaveCount(0);
                    for (const [key, value] of [['publications', '6'], ['students_ylp', '0'], ['faculty_engaged', '75.5%'], ['funded_collaborations', 'Not set']]) {
                        await expect(board.locator(`[data-target-indicator="${key}"] > dd`)).toHaveText(value);
                    }
                    await expect(board.getByRole('link', { name: 'Manage research targets' })).toBeVisible();
                } else {
                    await expect(board).toContainText('No research targets');
                    await expect(board.getByRole('button', { name: 'Set targets', exact: true })).toBeVisible();
                    await expect(board.locator('table')).toHaveCount(0);
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('target editor disclosures and college selectors keep one accessible allocation field per indicator', { timeout: 120000 }, async context => {
    const styles = builtStyles();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 768, 1024, 1440]) for (const dark of [false, true]) {
            context.diagnostic(`editor: ${width}px, ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const board = await render(page, 'editor', dark, styles, true);
            await expect(board.locator('#research-target-year')).toBeDisabled();
            const details = board.locator('details');
            await expect(details).toHaveCount(11);

            for (let index = 0; index < await details.count(); index++) {
                const indicator = details.nth(index);
                const summary = indicator.locator('summary');
                await summary.focus();
                await expect(summary).toBeFocused();
                if (!await indicator.evaluate(element => element.open)) await summary.press('Enter');
                await expect(indicator).toHaveAttribute('open', '');
                const college = indicator.locator('select');
                await expect(college).toBeVisible();
                await college.selectOption('LAB_SCHOOL');
                await expect(indicator.locator('input[x-bind\\:disabled]:visible')).toHaveCount(1);
                await expect(indicator.locator('input[x-bind\\:disabled]:enabled')).toHaveCount(1);
                const lab = indicator.locator('input[id$="-LAB_SCHOOL"]');
                await expect(lab).toBeVisible();
                await expect(lab).toBeEnabled();
                await lab.fill('0');
                await expect(lab).toHaveValue('0');
                await college.selectOption('CAS');
                await expect(lab).toBeHidden();
                await expect(lab).toBeDisabled();
                await college.selectOption('LAB_SCHOOL');
                await expect(lab).toBeVisible();
                await expect(lab).toHaveValue('0');
                for (const control of [summary, college, lab, indicator.locator('input[id$="-total"]')]) {
                    assert.ok((await control.boundingBox()).height >= 44, 'Editor controls need a 44px target');
                }
                await assertFits(page, board, width, `editor indicator ${index + 1}`);
                await summary.press('Enter');
            }
            await expect(board.locator('#research-target-faculty_engaged-total')).toHaveAttribute('step', '0.01');
            await expect(board.locator('#research-target-publications-total')).toHaveAttribute('step', '1');
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
