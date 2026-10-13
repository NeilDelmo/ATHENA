import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
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

test('rendered dashboards stay readable and expose usable actions in both themes and narrow layouts', async context => {
    if (process.env.ATHENA_DASHBOARD_FIXTURES_READY !== '1') {
        const result = execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/DashboardProjectHealthTest.php', 'tests/Feature/DashboardPrioritiesTest.php'], {
            encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
            env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_DASHBOARD_LAYOUT: '1' },
        });
        assert.equal(JSON.parse(result).result, 'passed', 'Dashboard fixtures must come from passing feature tests');
    }
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const posterDirectory = resolve('storage/app/private/research-calls');
    const posterFiles = existsSync(posterDirectory) ? readdirSync(posterDirectory).filter(name => /\.(png|jpe?g)$/i.test(name)) : [];
    const posterFile = posterFiles.find(name => name === 'xfd6SjDKi4kkl3nSFiQaskb3uKmy56yNTnSGoNXK.png') || posterFiles[0];
    const posterResponse = posterFile ? { path: resolve(posterDirectory, posterFile) } : {
        contentType: 'image/svg+xml',
        body: '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="900"><rect width="640" height="900" fill="#7A0019"/><text x="40" y="150" fill="white" font-size="48">CALL FOR PROPOSALS</text><text x="40" y="230" fill="white" font-size="30">Research development call</text><path d="M40 320h560M40 400h500M40 480h520M40 650h560" stroke="white" stroke-width="12"/></svg>',
    };
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const fixture of ['faculty', 'researcher', 'actions', 'unreported', 'empty', 'head', 'office', 'secretary']) {
            const source = readFileSync(resolve(`storage/framework/testing/dashboard-${fixture}.html`), 'utf8');
            for (const width of [320, 390, 768, 1440]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: fixture === 'faculty' && width === 1440 ? 900 : 1000 }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.route('**/*', route => {
                        const pathname = new URL(route.request().url()).pathname;
                        if (pathname === '/images/cteb_building.png') return route.fulfill({ path: resolve('public/images/cteb_building.png') });
                        if (fixture === 'faculty' && /^\/research-calls\/[^/]+\/reference-image$/.test(pathname)) return route.fulfill(posterResponse);
                        return route.abort();
                    });
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
                        assert.equal(await page.locator('[data-dashboard-assistant-suggestions]').count(), 0, 'Faculty AI checks should live in their revision and draft rows');
                        const drafts = page.locator('#recent-drafts');
                        assert.equal(await drafts.getByRole('heading', { name: 'Drafts', exact: true }).count(), 1);
                        assert.equal(await drafts.locator('[role="progressbar"]').count(), 0, 'Draft readiness should use one short text summary');
                        for (const [selector, label, contextKey, focus] of [
                            ['[data-revision-task]', 'AI revision check', 'topic_id', 'revision_response'],
                            ['[data-dashboard-draft]', 'AI draft check', 'proposal_draft_id', 'consistency'],
                        ]) {
                            const rows = page.locator(selector);
                            assert.ok(await rows.count() > 0, `${label} fixture needs a visible row`);
                            for (const row of await rows.all()) {
                                const action = row.locator('[data-review-suggestion]');
                                assert.equal(await action.count(), 1, `${label} belongs once in each row`);
                                assert.equal((await action.textContent()).trim(), label);
                                assert.equal(await action.isVisible(), true);
                                const payload = JSON.parse(await action.getAttribute('data-review-suggestion'));
                                const targetId = Number(await row.getAttribute(contextKey === 'topic_id' ? 'data-topic-id' : 'data-draft-id'));
                                assert.deepEqual(payload.context, { [contextKey]: targetId, workflow_scope: 'review' });
                                assert.deepEqual(payload.action, { type: 'review_proposal', review_focus: focus });
                                assert.equal(payload.target_label, (await row.locator('h3, h4').first().textContent()).trim());
                                if (contextKey === 'proposal_draft_id') {
                                    assert.match(await row.textContent(), /\d+\s+of\s+\d+\s+papers ready/);
                                    assert.doesNotMatch(await row.textContent(), /\d+%\s+ready/);
                                    if (width === 1440) {
                                        const rowBox = await row.boundingBox();
                                        const sectionBox = await drafts.boundingBox();
                                        assert.ok(rowBox.height <= 200 && Math.abs(rowBox.width - sectionBox.width) <= 2, 'Draft rows should be compact and span their section');
                                    }
                                }
                            }
                        }
                        const order = await page.evaluate(() => ['[data-research-call-carousel]', '[aria-labelledby="proposal-overview-heading"]', '[data-revision-worklist]', '#recent-drafts'].map(selector => document.querySelector(selector).getBoundingClientRect().top));
                        assert.ok(order[0] < order[1] && order[1] < order[2] && order[2] < order[3], 'The announcement banner should lead the overview, required actions, and drafts');
                        const surfaces = page.locator('[aria-labelledby="proposal-overview-heading"] dl > div, [data-revision-worklist] > div:last-child, #recent-drafts > div:last-child');
                        assert.ok(await surfaces.count() >= 6, 'Palette fixture must include metrics, revisions, and drafts');
                        const invalidSurfaces = await surfaces.evaluateAll(elements => elements.map(element => {
                            const color = getComputedStyle(element).backgroundColor;
                            const [r, g, b, alpha = 1] = color.match(/[\d.]+/g).map(Number);
                            const neutral = Math.max(r, g, b) - Math.min(r, g, b) <= 12;
                            return { surface: element.querySelector('dt, h3, h4')?.textContent.trim() || element.closest('section')?.getAttribute('aria-labelledby'), color, valid: alpha === 1 && (neutral || r - g >= Math.abs(g - b)) };
                        }).filter(surface => !surface.valid));
                        assert.deepEqual(invalidSurfaces, [], `Faculty surfaces must be opaque and neutral or burgundy in ${dark ? 'dark' : 'light'} mode`);
                        const banner = page.locator('[data-research-call-carousel]');
                        const bannerBox = await banner.boundingBox();
                        const dashboardBox = await page.locator('[data-dashboard-layout]').boundingBox();
                        assert.ok(Math.abs(bannerBox.x - dashboardBox.x) <= 1 && Math.abs(bannerBox.width - dashboardBox.width) <= 1, 'The announcement banner should span the dashboard width');
                        if ([320, 390, 1440].includes(width)) {
                            assert.ok(bannerBox.height <= (width === 1440 ? 260 : 360), 'The promotional banner should stay compact');
                        }
                        if (width === 390 && !dark) context.diagnostic(`Faculty mobile banner height: ${bannerBox.height}px`);
                        const poster = banner.locator('[data-research-call-poster-trigger]').first();
                        await poster.evaluate(image => image.decode());
                        assert.ok((await poster.boundingBox()).height <= (width < 640 ? 112 : 160), 'The poster should fit the compact advertisement');
                        assert.equal(await poster.evaluate(image => getComputedStyle(image).objectFit), 'contain', 'The complete poster must remain uncropped');
                        const grayPosterWells = await poster.evaluate(image => {
                            const wells = [];
                            for (let element = image.parentElement; element && !element.hasAttribute('data-research-call-slide'); element = element.parentElement) {
                                const channels = getComputedStyle(element).backgroundColor.match(/[\d.]+/g).map(Number);
                                const rgb = channels.slice(0, 3);
                                if ((channels[3] ?? 1) > 0 && Math.max(...rgb) < 250 && Math.max(...rgb) - Math.min(...rgb) < 32) wells.push(element.tagName);
                            }
                            return wells;
                        });
                        assert.deepEqual(grayPosterWells, [], 'The poster should sit on the promotional banner without a gray thumbnail well');
                        assert.equal(await banner.getByRole('link', { name: 'Start a proposal' }).isVisible(), true);
                        assert.equal(await banner.getByRole('button', { name: 'View full poster' }).isVisible(), true);
                        for (const [control, label] of [
                            [banner.getByRole('link', { name: 'View research calls' }), 'View research calls'],
                            [drafts.getByRole('link', { name: 'View all drafts' }), 'View all drafts'],
                        ]) {
                            const style = await control.evaluate(anchor => {
                                const computed = getComputedStyle(anchor);
                                return { decoration: computed.textDecorationLine, border: parseFloat(computed.borderTopWidth), height: anchor.getBoundingClientRect().height };
                            });
                            assert.equal(style.decoration, 'none', `${label} should look like a button rather than an underlined text link`);
                            assert.ok(style.border >= 1 && style.height >= 44, `${label} needs an outlined button and usable touch target`);
                        }
                        if (width === 1440) {
                            const headerBox = await page.locator('[data-workspace-header-banner]').boundingBox();
                            assert.ok(bannerBox.y >= headerBox.y + headerBox.height && bannerBox.y + bannerBox.height <= 900, 'The first banner must fit below the header in a 900px desktop viewport');
                            const metricsBox = await page.locator('[aria-labelledby="proposal-overview-heading"] dl').boundingBox();
                            assert.ok(metricsBox.y + metricsBox.height <= 900, 'The complete metric row should fit on the first desktop screen');
                            if (!dark) context.diagnostic(`Faculty desktop banner height: ${bannerBox.height}px; metric row: ${metricsBox.y}px–${metricsBox.y + metricsBox.height}px`);
                        }
                        const lowContrast = await page.locator('[data-research-call-carousel] :is(h2, h3, p, a, button):visible:not(.sr-only), [aria-labelledby="proposal-overview-heading"] dt, [aria-labelledby="proposal-overview-heading"] dd:last-child, [data-revision-task] p, [data-revision-task] [data-review-suggestion], #recent-drafts :is(p, span, a, button)').evaluateAll(elements => {
                            const rgba = color => color.match(/[\d.]+/g).map(Number);
                            const blend = (background, [r, g, b, alpha = 1]) => [r, g, b].map((value, index) => value * alpha + background[index] * (1 - alpha));
                            const luminance = rgb => rgb.map(value => value / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4)
                                .reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
                            return elements.filter(element => element.textContent.trim()).filter(element => {
                                const layers = [];
                                for (let surface = element; surface; surface = surface.parentElement) layers.unshift(rgba(getComputedStyle(surface).backgroundColor));
                                const backgroundColor = layers.reduce(blend, [255, 255, 255]);
                                const ink = luminance(blend(backgroundColor, rgba(getComputedStyle(element).color)));
                                const background = luminance(backgroundColor);
                                return (Math.max(ink, background) + .05) / (Math.min(ink, background) + .05) < 4.5;
                            }).map(element => element.textContent.trim());
                        });
                        assert.deepEqual(lowContrast, [], `Faculty banner, metric, and detail text needs 4.5:1 contrast in ${dark ? 'dark' : 'light'} mode`);
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
                    if (fixture === 'faculty' && !dark && [390, 1440].includes(width)) {
                        for (const [name, selector] of [['banner', '[data-research-call-carousel]'], ['revisions', '[data-revision-worklist]'], ['drafts', '#recent-drafts']]) {
                            await page.locator(selector).screenshot({ path: resolve(`storage/framework/testing/dashboard-faculty-${name}-${width === 1440 ? 'desktop' : 'mobile'}.png`) });
                        }
                    }
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});
