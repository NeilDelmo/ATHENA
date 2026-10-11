import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('submission date filter icons stay centered when collapsed and expanded', async () => {
    const source = readFileSync(resolve('resources/views/livewire/research-head-overview.blade.php'), 'utf8');
    const summary = source.match(/<summary class="rh-button-secondary[\s\S]*?<\/summary>/)[0]
        .replace(/{{[\s\S]*?}}/g, 'Filter by submission date');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 600 } });
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${css}</style><body class="p-4"><details class="group">${summary}<p>Date range fields</p></details></body></html>`);
                const control = page.locator('summary');
                assert.equal(await control.locator('svg').count(), 2);
                for (const open of [false, true]) {
                    if (open) await control.click();
                    const icons = await control.locator('svg').evaluateAll(elements => elements.map(element => {
                        const bounds = element.getBoundingClientRect();
                        return { center: bounds.y + bounds.height / 2, width: bounds.width, height: bounds.height };
                    }));
                    assert.ok(Math.abs(icons[0].center - icons[1].center) <= 1);
                    assert.deepEqual(icons[0], icons[1]);
                    assert.equal(await control.locator('svg:last-child path:last-child').evaluate(element => getComputedStyle(element).display !== 'none'), !open);
                }
                await control.click();
                assert.equal(await page.locator('details').getAttribute('open'), null);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('rendered dashboards stay readable and expose usable actions in both themes and narrow layouts', async () => {
    if (process.env.ATHENA_DASHBOARD_FIXTURES_READY !== '1') {
        const result = execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/DashboardProjectHealthTest.php', 'tests/Feature/DashboardPrioritiesTest.php'], {
            encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
            env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_DASHBOARD_LAYOUT: '1' },
        });
        assert.equal(JSON.parse(result).result, 'passed', 'Dashboard fixtures must come from passing feature tests');
    }
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const fixture of ['faculty', 'researcher', 'actions', 'unreported', 'empty', 'head', 'office', 'secretary']) {
            const source = readFileSync(resolve(`storage/framework/testing/dashboard-${fixture}.html`), 'utf8');
            for (const width of [320, 390, 768, 1440]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.route('**/*', route => route.abort());
                    const content = await page.evaluate(html => {
                        const document = new DOMParser().parseFromString(html, 'text/html');
                        const dashboard = document.querySelector('[data-dashboard-layout]') || document.querySelector('[data-research-head-overview]');
                        assertDashboard(dashboard);
                        const header = document.querySelector('[data-workspace-header-banner]');
                        return (header?.outerHTML || '') + dashboard.outerHTML;
                        function assertDashboard(element) {
                            if (!element) throw new Error('Rendered dashboard is missing');
                        }
                    }, source);
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css}\n[x-cloak]{display:none!important}</style></head><body class="bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"><main class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6">${content}</main></body></html>`);
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${fixture}: ${width}px ${dark ? 'dark' : 'light'} overflows`);
                    const clipped = await page.locator('[data-project-attention] h3, [data-dashboard-layout] dt').evaluateAll(elements => elements.filter(element => {
                        const style = getComputedStyle(element);
                        return style.display !== 'none' && element.scrollWidth > element.clientWidth + 1;
                    }).map(element => element.textContent.trim()));
                    assert.deepEqual(clipped, [], `${fixture}: titles and metric labels must wrap`);
                    if (fixture === 'actions') {
                        const rows = page.locator('[data-project-attention]:visible');
                        assert.equal(await rows.count(), 4);
                        const toggle = page.locator('button[aria-controls="faculty-project-actions"]');
                        const box = await toggle.boundingBox();
                        assert.ok(box.height >= 44, 'Disclosure control needs a usable touch target');
                        await toggle.focus();
                        await page.keyboard.press('Enter');
                        await page.locator('[data-project-attention]').last().waitFor({ state: 'visible' });
                        assert.equal(await rows.count(), 5);
                        assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
                        await page.getByRole('button', { name: 'Show fewer projects' }).click();
                        await page.locator('[data-project-attention]').last().waitFor({ state: 'hidden' });
                        assert.equal(await rows.count(), 4);
                    }
                    if (fixture === 'researcher') {
                        const link = page.getByRole('link', { name: /^Needs attention\s*:/ });
                        const card = await link.locator('..').locator('..').boundingBox();
                        const target = await page.evaluate(({ x, y }) => document.elementFromPoint(x, y)?.closest('a')?.getAttribute('href'), { x: card.x + card.width / 2, y: card.y + card.height - 12 });
                        assert.equal(target, '#needs-attention', 'The metric should be clickable across its card');
                        await link.click();
                        assert.equal(await page.evaluate(() => location.hash), '#needs-attention');
                    }
                    if (fixture === 'faculty') {
                        assert.ok(await page.getByRole('link', { name: /^Submitted\s*:/ }).getAttribute('href').then(href => href.includes('category=all')));
                        const order = await page.evaluate(() => ['[data-revision-worklist]', '#recent-drafts', '[data-research-call-carousel]'].map(selector => document.querySelector(selector).getBoundingClientRect().top));
                        assert.ok(order[0] < order[1] && order[1] < order[2], 'Required actions and drafts should precede announcements');
                        const lowContrast = await page.locator('[data-revision-task] p, #recent-drafts p, #recent-drafts span').evaluateAll(elements => {
                            const rgb = color => color.match(/[\d.]+/g).slice(0, 3).map(Number);
                            const luminance = color => rgb(color).map(value => value / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4)
                                .reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
                            return elements.filter(element => {
                                let surface = element;
                                while (surface.parentElement && getComputedStyle(surface).backgroundColor === 'rgba(0, 0, 0, 0)') surface = surface.parentElement;
                                const ink = luminance(getComputedStyle(element).color);
                                const background = luminance(getComputedStyle(surface).backgroundColor);
                                return (Math.max(ink, background) + .05) / (Math.min(ink, background) + .05) < 4.5;
                            }).map(element => element.textContent.trim());
                        });
                        assert.deepEqual(lowContrast, [], `Faculty detail text needs 4.5:1 contrast in ${dark ? 'dark' : 'light'} mode`);
                    }
                    if (fixture === 'secretary') {
                        const portfolio = page.locator('#assigned-projects');
                        assert.equal(await portfolio.getAttribute('open'), null);
                        const disclosure = await portfolio.locator('summary svg').boundingBox();
                        assert.ok(disclosure?.width >= 16 && disclosure?.height >= 16, 'Collapsed portfolio needs a visible disclosure cue');
                        await portfolio.locator('summary').focus();
                        await page.keyboard.press('Enter');
                        assert.notEqual(await portfolio.getAttribute('open'), null, 'Keyboard users can open the portfolio');
                        assert.ok(await page.getByRole('link', { name: 'Review budget' }).count() > 0);
                        await portfolio.locator('summary').click();
                        assert.equal(await portfolio.getAttribute('open'), null);
                    }
                    if (['office', 'secretary'].includes(fixture)) {
                        for (const action of await page.locator('.dashboard-action:visible').all()) {
                            assert.ok((await action.boundingBox()).height >= 44, `${fixture}: actions need usable touch targets`);
                        }
                    }
                    assert.deepEqual(errors, [], `${fixture}: browser errors`);
                    if (width === 390 && dark) {
                        await page.screenshot({ path: resolve(`storage/framework/testing/dashboard-${fixture}-mobile-dark.png`), fullPage: true });
                    }
                    if (width === 1440 && !dark) {
                        await page.screenshot({ path: resolve(`storage/framework/testing/dashboard-${fixture}-desktop.png`), fullPage: true });
                    }
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});
