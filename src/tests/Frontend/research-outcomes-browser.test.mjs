import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import test, { before } from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

before(() => {
    execFileSync(process.env.PHP_BINARY || 'php', ['artisan', 'test', '--compact', 'tests/Feature/ResearchOutcomeTest.php', '--filter=post implementation pages render'], {
        encoding: 'utf8', maxBuffer: 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_RESEARCH_OUTCOMES: '1' },
    });
});

test('post implementation desktop forms expose independent labels and coordinator read access', async () => {
    const css = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const dark of [false, true]) {
            for (const name of ['index', 'leader', 'create', 'coordinator']) {
                const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                const html = readFileSync(resolve(`storage/framework/testing/research-outcomes-${name}.html`), 'utf8');
                const content = await page.evaluate(source => {
                    const document = new DOMParser().parseFromString(source, 'text/html');
                    return document.querySelector('[data-page-header-container]').outerHTML + document.querySelector('main').outerHTML;
                }, html);
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css.css}</style></head><body class="bg-slate-50 font-sans text-gray-900 antialiased dark:bg-slate-950 dark:text-slate-100"><div style="margin-left:280px">${content}</div></body></html>`);
                await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
                if (['leader', 'create'].includes(name)) {
                    await expect(page.getByRole('checkbox')).toHaveCount(5);
                    for (const label of ['Publication', 'Presentation', 'Patent', 'Commercialized', 'Utilization']) {
                        await expect(page.getByRole('checkbox', { name: label, exact: true })).toBeVisible();
                    }
                    await page.getByRole('checkbox', { name: 'Commercialized', exact: true }).check();
                    await page.getByRole('checkbox', { name: 'Utilization', exact: true }).check();
                    await expect(page.getByRole('checkbox', { name: 'Commercialized', exact: true })).toBeChecked();
                    await expect(page.getByRole('checkbox', { name: 'Utilization', exact: true })).toBeChecked();
                    if (name === 'leader') {
                        await expect(page.getByRole('checkbox', { name: 'Publication', exact: true })).toBeChecked();
                        await expect(page.getByRole('button', { name: 'Save labels', exact: true })).toBeVisible();
                    } else {
                        await expect(page.getByLabel('Project title', { exact: true })).toBeVisible();
                        await expect(page.getByLabel('Funding source', { exact: true }).locator('option')).toHaveCount(3);
                    }
                } else if (name === 'coordinator') {
                    await expect(page.getByRole('checkbox')).toHaveCount(0);
                    await expect(page.getByRole('button', { name: 'Save labels', exact: true })).toHaveCount(0);
                    await expect(page.getByText('Publication', { exact: true })).toBeVisible();
                } else {
                    await expect(page.getByRole('link', { name: 'Completed school research', exact: true })).toBeVisible();
                    await expect(page.getByRole('link', { name: 'Register other research', exact: true })).toBeVisible();
                }
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.screenshot({ path: resolve(`storage/framework/testing/research-outcomes-${name}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('post implementation supports the full app shell and Livewire page navigation', async () => {
    const pages = Object.fromEntries(['index', 'leader', 'create', 'head'].map(name => [name,
        readFileSync(resolve(`storage/framework/testing/research-outcomes-${name}.html`), 'utf8'),
    ]));
    const publicPath = resolve('public');
    const types = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.jpg': 'image/jpeg', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' };
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
        const errors = [];
        let projectView = 'leader';
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.text().startsWith('Alpine Expression Error')) errors.push(message.text()); });
        await page.route('**/*', route => {
            const path = new URL(route.request().url()).pathname;
            const file = resolve(publicPath, `.${path}`);
            if (file.startsWith(publicPath + '/') || file.startsWith(publicPath + '\\')) {
                if (types[extname(file)] && existsSync(file)) {
                    return route.fulfill({ contentType: types[extname(file)], body: readFileSync(file) });
                }
            }
            if (path === '/notifications') return route.fulfill({ json: { notifications: [], unread_count: 0, pending_actions: [], workspace: 'faculty_researcher' } });
            if (path === '/research-outcomes') return route.fulfill({ contentType: 'text/html', body: pages.index });
            if (path === '/research-outcomes/create') return route.fulfill({ contentType: 'text/html', body: pages.create });
            if (/^\/research-outcomes\/projects\/\d+$/.test(path)) return route.fulfill({ contentType: 'text/html', body: pages[projectView] });
            return route.abort();
        });
        await page.goto('http://localhost/research-outcomes');
        await page.waitForFunction(() => document.body._x_dataStack?.length > 0);
        await page.getByRole('button', { name: 'Collapse navigation menu', exact: true }).click();
        await page.getByRole('button', { name: 'Expand navigation menu', exact: true }).click();
        await page.getByRole('link', { name: 'Completed school research', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Save labels', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Post-implementation', exact: true })).toHaveClass(/!bg-brand-wash/);
        await page.getByRole('link', { name: 'Back to projects', exact: true }).click();
        await page.getByRole('link', { name: 'Register other research', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Register other completed research', exact: true })).toBeVisible();
        await expect(page.getByLabel('Project title', { exact: true })).toBeVisible();
        projectView = 'head';
        const projectUrl = pages.index.match(/href="([^"]+\/research-outcomes\/projects\/\d+)"/)[1];
        await page.goto(projectUrl);
        await page.waitForFunction(() => document.body._x_dataStack?.length > 0);
        await expect(page.getByRole('link', { name: 'Post-implementation', exact: true })).toHaveClass(/!bg-brand-wash/);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
