import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

function workspace(kind = 'finder') {
    const result = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        echo ($argv[1] ?? 'finder') === 'proposal'
            ? view('faculty.proposal-drafts.detailed-proposal.partials.literature-workspace')->render()
            : Illuminate\Support\Facades\Blade::render('<x-rrl-finder />');
    `, kind], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(result.status, 0, (result.stderr || result.stdout).slice(-3000));
    return result.stdout;
}

const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css]
    .map((file) => readFileSync(resolve('public/build', file), 'utf8')).join('\n');
const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
const storeStart = app.indexOf("Alpine.store('literatureSearch'");
const store = app.slice(storeStart, app.indexOf('\n});', storeStart) + '\n});'.length);
const helpers = readFileSync(resolve('resources/js/proposal-literature-search.js'), 'utf8').replace(/^export /gm, '');
const results = [
    { title: 'Community monitoring of mangrove restoration', authors: 'Maria Santos', year: 2025, source: 'OpenAlex', venue: 'Coastal Research', doi: '10.1234/mangrove', citation_count: 14, description: 'Community participation improves monitoring of restored mangroves.', access_status: 'metadata_only' },
    { title: 'Participation in coastal conservation', authors: 'Ana Reyes', year: 2024, source: 'Crossref', venue: 'Marine Studies', doi: '10.1234/coastal', citation_count: 8, description: 'A study of community participation in coastal conservation.', access_status: 'metadata_only' },
];

test('literature search keeps optional controls quiet and works in both themes on mobile and desktop', async () => {
    const html = workspace();
    assert.equal(/<details|<summary/.test(html), false);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="csrf-token" content="test"><style>${styles}</style></head><body class="bg-slate-50 p-4 dark:bg-slate-950" data-literature-search-url="/literature-search"><main class="mx-auto max-w-6xl">${html}</main></body></html>`);
                await page.addScriptTag({ content: `${helpers}
                    window.fetch = async (url, options) => {
                        window.searchRequest = JSON.parse(options.body);
                        return new Promise((done) => { window.finishSearch = (results) => done(new Response(JSON.stringify({results}), {status: 200, headers: {'Content-Type': 'application/json'}})); });
                    };
                    document.addEventListener('alpine:init', () => {
                        ${store}
                        Alpine.store('researchAssistant', { isLoading: false });
                    });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                const filters = page.locator('button[aria-controls="literature-search-filters"]');
                await filters.waitFor();
                await expect(filters).toHaveAttribute('aria-expanded', 'false');
                await expect(page.locator('#literature-search-filters')).toBeHidden();
                await expect(page.locator('#literature-external-source')).toBeHidden();
                assert.equal(await page.getByRole('button', { name: 'Search', exact: true }).isDisabled(), true);
                assert.equal(await page.getByText('Your first action is to search').count(), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-search-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await filters.focus();
                await page.keyboard.press('Enter');
                await expect(filters).toHaveAttribute('aria-expanded', 'true');
                await page.getByLabel('From year', { exact: true }).fill('2020');
                await page.getByLabel('Open access only', { exact: true }).check();
                await filters.click();
                await expect(page.locator('#literature-search-filters')).toBeHidden();
                await page.getByRole('button', { name: 'Add a source', exact: true }).click();
                await page.locator('#literature-external-source').waitFor({ state: 'visible' });
                assert.equal(await page.locator('#literature-external-source').isVisible(), true);
                await page.getByRole('button', { name: 'Add a source', exact: true }).click();
                await page.getByLabel('Search literature', { exact: true }).fill('Community mangrove restoration');
                await page.getByRole('button', { name: 'Search', exact: true }).click();
                await page.waitForFunction(() => Boolean(window.finishSearch));
                await expect(page.locator('[data-literature-loading]')).toBeVisible();
                assert.equal(await page.locator('[data-literature-loading]').innerText(), 'Searching academic papers…');
                const payload = await page.evaluate(() => window.searchRequest);
                assert.equal(payload.year_from, 2020);
                assert.equal(payload.open_access, true);
                await page.evaluate((records) => window.finishSearch(records), results);
                await page.locator('[data-rrl-results-workspace]').waitFor({ state: 'visible' });
                const second = page.locator('[data-rrl-results-table] tr[tabindex]').nth(1);
                await second.focus();
                await page.keyboard.press('Enter');
                await expect(second).toHaveAttribute('aria-selected', 'true');
                await expect(page.locator('[data-rrl-paper-details] h4')).toHaveText(results[1].title);
                await expect(page.locator('#literature-publication-details')).toBeHidden();
                const publication = page.getByRole('button', { name: 'Publication details', exact: true });
                await publication.click();
                await expect(page.locator('#literature-publication-details')).toBeVisible();
                await publication.click();
                await expect(page.locator('#literature-publication-details')).toBeHidden();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.LITERATURE_SCREENSHOT_DIRECTORY) {
                    await page.screenshot({ path: resolve(process.env.LITERATURE_SCREENSHOT_DIRECTORY, `literature-results-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.getByRole('tab', { name: /Saved library/ }).click();
                await expect(page.locator('#shared-literature-library')).toBeVisible();
                await expect(page.locator('#literature-find-workspace')).toBeHidden();
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('the proposal literature search uses an accessible button without triangle disclosures', async () => {
    const html = workspace('proposal');
    assert.equal(/<details|<summary/.test(html), false);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent('<main></main>');
        const search = await page.evaluate((markup) => new DOMParser().parseFromString(markup, 'text/html').querySelector('section[aria-label="Search literature"]').outerHTML, html);
        await page.setContent(`<style>${styles}</style><main class="p-4" x-data="{ literatureWorkspaceTab: 'search', literatureSearchQuery: '', literatureSearchLoading: false, literatureSearchUseContext: true, literatureSearchFilters: {}, literatureSearchError: '', literatureSearchNotice: '', literatureSearchResults: [], literatureSearchKeywords: [], literatureSearchHistory: [], literatureSearchContextOptions() { return []; } }">${search}</main>`);
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        const settings = page.getByRole('button', { name: 'Search settings', exact: true });
        await settings.waitFor();
        assert.equal(await settings.getAttribute('aria-expanded'), 'false');
        await settings.focus();
        await page.keyboard.press('Space');
        assert.equal(await settings.getAttribute('aria-expanded'), 'true');
        await page.locator('#proposal-literature-settings').waitFor({ state: 'visible' });
        assert.equal(await page.getByText('Include proposal context', { exact: true }).isVisible(), true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
