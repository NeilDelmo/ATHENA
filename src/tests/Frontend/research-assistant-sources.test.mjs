import assert from 'node:assert/strict';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { safeAssistantSourceUrl } from '../../resources/js/research-assistant-markdown.js';

const manualUrl = 'https://athena.test/research-manuals/2023-edition-hash#page=27';
const sources = [
    { reference: 'ATHENA 1', title: 'Approved Research Manual (2023) · Ethics clearance · pp. 27–28', type: 'Official Research Manual', category: 'Official Research Manual', url: manualUrl },
    { reference: 'Proposal 1', title: 'Detailed Research Proposal · Research design', type: 'proposal_paper', category: 'Saved proposal', url: 'https://athena.test/proposals/42/papers/detailed-proposal#research-design' },
    { reference: 'ATHENA 2', title: 'Work Plan field guide', category: 'Application field guide', url: null },
    { reference: 'Selected document 1', title: 'Coastal water monitoring report · p. 12', category: 'Selected document excerpts', url: null },
    { reference: 'ATHENA 1', title: '<img src=x onerror="window.sourceExecuted=true">', category: '<script>unsafe category</script>', url: 'javascript:alert(1)' },
    { reference: 'ATHENA 1', title: 'A long source title with an unusually long identifier ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', category: 'Official Research Manual', url: 'https://athena.test/research-manuals/2023-edition-hash#page=98' },
    {},
];

test('shared assistant sources are compact, keyboard accessible, safely linked, and readable in both chat sizes and themes', { timeout: 120000 }, async context => {
    const component = readFileSync(resolve('resources/views/components/research-assistant-sources.blade.php'), 'utf8');
    const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
    const method = app.match(/    sourceUrl\(([^)]*)\) \{([\s\S]*?)\n    \},/);
    assert.ok(method, 'Use the actual assistant source URL validation.');
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 900]) for (const dark of [false, true]) {
            context.diagnostic(`Sources at ${width}px ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.context().route('**/*', route => route.fulfill({ contentType: 'text/html', body: '<p>Exact source destination</p>' }));
            await page.setContent(`<!doctype html><html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}\n[x-cloak]{display:none!important}</style></head><body class="bg-stone-100 text-stone-900 dark:bg-stone-950 dark:text-stone-100"><main class="mx-auto max-w-3xl p-4" x-data="{ messages: window.fixtureMessages }"><template x-for="message in messages" :key="message.id"><article class="mb-4 rounded-xl bg-white p-4 dark:bg-stone-900" data-assistant-message :data-role="message.role"><p x-text="message.content"></p>${component}</article></template></main></body></html>`);
            await page.addScriptTag({ content: `window.fixtureMessages = ${JSON.stringify([
                { id: 1, role: 'user', content: 'What guidance applies to ethics clearance?' },
                { id: 2, role: 'assistant', content: 'Obtain the required ethics clearance before recruitment.', sources },
                { id: 3, role: 'assistant', content: 'No matching sources were supplied.', sources: [] },
            ])};
                ${safeAssistantSourceUrl.toString()}
                document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
                    openSourcePreview() { return false; },
                    sourceUrl(${method[1]}) {${method[2]}}
                }));` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            const messages = page.locator('[data-assistant-message]');
            await expect(messages).toHaveCount(3);
            await expect(messages.first().locator('[data-assistant-sources]')).toBeHidden();
            await expect(messages.last().locator('[data-assistant-sources]')).toBeHidden();
            const details = messages.nth(1).locator('[data-assistant-sources]');
            const summary = details.locator('summary');
            await expect(summary).toHaveText(/Sources\s*\(7\)/);
            assert.equal(await details.evaluate(element => element.open), false, 'Sources stay collapsed until requested.');
            await expect(details.locator('a:visible')).toHaveCount(0);
            await summary.focus();
            await page.keyboard.press('Enter');
            await expect(details).toHaveAttribute('open', '');
            const rows = details.locator('[data-assistant-source]');
            await expect(rows).toHaveCount(sources.length);
            await expect(rows.first()).toContainText(sources[0].title);
            await expect(rows.first()).toContainText('Official Research Manual');
            await expect(rows.first()).toContainText('ATHENA 1');
            const manual = rows.first().getByRole('link');
            await expect(manual).toHaveAttribute('href', manualUrl);
            await expect(manual).toHaveAttribute('target', '_blank');
            await expect(manual).toHaveAttribute('rel', /noopener/);
            await expect(manual).toHaveAttribute('aria-label', /Open manual:/);
            await expect(manual.locator('svg:visible')).toHaveCount(2);
            await expect(rows.nth(1).getByRole('link')).toHaveAttribute('href', sources[1].url);
            for (const index of [2, 3, 4, 6]) await expect(rows.nth(index).getByRole('link')).toHaveCount(0);
            await expect(rows.nth(4)).toContainText(sources[4].title);
            await expect(rows.nth(4)).toContainText(sources[4].category);
            await expect(rows.nth(6)).toContainText('Reference material');
            await expect(rows.nth(6)).toContainText('ATHENA reference');
            await expect(details.locator('img, script')).toHaveCount(0);
            await expect(details.getByRole('link')).toHaveCount(3);
            assert.equal(await page.evaluate(() => window.sourceExecuted), undefined);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Long source titles must wrap inside narrow chat columns.');
            for (const row of await rows.all()) {
                const bounds = await row.boundingBox();
                assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1);
            }
            for (const link of await details.getByRole('link').all()) {
                assert.ok((await link.boundingBox()).height >= 44, 'The source title is a usable link target.');
            }
            await page.keyboard.press('Tab');
            await expect(manual).toBeFocused();
            const opened = page.waitForEvent('popup');
            await page.keyboard.press('Enter');
            const popup = await opened;
            await popup.waitForLoadState('domcontentloaded');
            assert.equal(popup.url(), manualUrl, 'The source keeps its manual edition and exact cited page.');
            await popup.close();
            if (process.env.ASSISTANT_SOURCE_SCREENSHOTS) {
                mkdirSync(process.env.ASSISTANT_SOURCE_SCREENSHOTS, { recursive: true });
                await messages.nth(1).screenshot({ path: resolve(process.env.ASSISTANT_SOURCE_SCREENSHOTS, `assistant-sources-${width}-${dark ? 'dark' : 'light'}.png`) });
            }
            await summary.focus();
            await page.keyboard.press('Space');
            assert.equal(await details.evaluate(element => element.open), false);
            assert.deepEqual(errors, [], 'Messages without sources and duplicate reference labels must not trigger Alpine errors.');
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
