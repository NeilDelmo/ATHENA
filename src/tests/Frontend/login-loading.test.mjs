import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('sign in paints while the external font stylesheet is still loading', { timeout: 30000 }, async () => {
    const rendered = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        session()->put('_token', str_repeat('a', 40));
        echo view('auth.login')->render();
    `], { cwd: resolve('.'), encoding: 'utf8' });
    assert.equal(rendered.status, 0, (rendered.stderr || rendered.stdout).slice(-3000));

    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    let releaseFont;
    const fontReady = new Promise(resolve => { releaseFont = resolve; });
    try {
        const page = await browser.newPage();
        let fontRequests = 0;
        await page.route('https://fonts.bunny.net/**', async route => {
            fontRequests++;
            await fontReady;
            await route.fulfill({ contentType: 'text/css', body: 'body { --font-styles-loaded: yes; }' });
        });
        await page.route('http://localhost/**', route => {
            const assetPath = new URL(route.request().url()).pathname.match(/\/(build|images)\/.+$/)?.[0];
            if (!assetPath) return route.fulfill({ status: 204, body: '' });
            const path = resolve('public', assetPath.slice(1));
            if (!path.startsWith(resolve('public') + '/') && !path.startsWith(resolve('public') + '\\')) return route.abort();
            if (!existsSync(path)) return route.fulfill({ status: 204, body: '' });
            const contentType = path.endsWith('.css') ? 'text/css' : path.endsWith('.js') ? 'text/javascript' : 'image/png';
            return route.fulfill({ contentType, body: readFileSync(path) });
        });
        await page.route('http://athena-login-loading.test/', route => route.fulfill({ contentType: 'text/html', body: rendered.stdout }));

        await page.goto('http://athena-login-loading.test/', { waitUntil: 'commit' });
        await page.getByRole('heading', { name: 'Sign in to ATHENA' }).waitFor({ state: 'attached' });
        await expect.poll(() => page.evaluate(() => performance.getEntriesByName('first-contentful-paint').length), {
            timeout: 2500,
            message: 'The sign-in screen must paint before the font server responds',
        }).toBe(1);
        assert.equal(fontRequests, 1);
        await expect(page.getByRole('link', { name: 'Continue with BatStateU Google' })).toBeVisible();

        releaseFont();
        await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).getPropertyValue('--font-styles-loaded').trim())).toBe('yes');
    } finally {
        releaseFont();
        await browser.close();
    }
});
