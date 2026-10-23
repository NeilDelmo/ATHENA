import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import test, { after } from 'node:test';
import { chromium, expect } from '@playwright/test';

const origin = 'http://athena-guest.test';
const compiledViews = mkdtempSync(join(tmpdir(), 'athena-guest-assets-'));
after(() => {
    assert.ok(compiledViews.startsWith(join(tmpdir(), 'athena-guest-assets-')));
    rmSync(compiledViews, { recursive: true, force: true });
});

function guestPage(authenticated = false) {
    const hotFile = resolve(compiledViews, 'hot');
    writeFileSync(hotFile, origin);
    const result = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config()->set('session.driver', 'array');
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        Illuminate\Support\Facades\Vite::useHotFile($argv[2]);
        if ($argv[1] === 'authenticated') {
            Illuminate\Support\Facades\Auth::setUser(App\Models\User::factory()->make(['id' => 7]));
            echo Illuminate\Support\Facades\Blade::render('<x-guest-layout>Choose your workspace</x-guest-layout>');
        } else {
            echo view('auth.login')->render();
        }
    `, authenticated ? 'authenticated' : 'guest', hotFile], {
        encoding: 'utf8', cwd: resolve('.'),
        env: { ...process.env, VIEW_COMPILED_PATH: compiledViews, SESSION_DRIVER: 'array', CACHE_STORE: 'array' },
    });
    assert.equal(result.status, 0, (result.stderr || result.stdout).slice(-4000));
    return result.stdout;
}

test('login loads a dedicated guest entry while authenticated workspace pages retain their application runtime', () => {
    const guest = guestPage();
    assert.deepEqual([...guest.matchAll(/<script[^>]+type="module"[^>]+src="([^"]+)"/g)].map(match => match[1]), [
        `${origin}/@vite/client`, `${origin}/resources/js/guest.js`,
    ]);
    const authenticated = guestPage(true);
    assert.ok(authenticated.includes(`${origin}/resources/js/app.js`));
    assert.ok(!authenticated.includes(`${origin}/resources/js/guest.js`));
    assert.ok(authenticated.includes('window.livewireScriptConfig'));
});

test('the minimal login entry preserves theme controls, system changes and the institutional sign-in link', async () => {
    const html = guestPage();
    assert.ok(html.includes(`${origin}/resources/js/guest.js`));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ colorScheme: 'light' });
        const errors = [];
        const scripts = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => {
            const path = new URL(route.request().url()).pathname;
            if (route.request().resourceType() === 'script') scripts.push(path);
            if (path === '/resources/js/guest.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync(resolve('resources/js/guest.js')) });
            if (path === '/@vite/client') return route.fulfill({ contentType: 'text/javascript', body: '' });
            if (route.request().isNavigationRequest()) return route.fulfill({ contentType: 'text/html', body: html });
            return route.fulfill({ body: '', contentType: 'text/plain' });
        });
        await page.goto(`${origin}/login`);
        const toggle = page.getByRole('button', { name: 'Toggle light and dark theme' });
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await page.emulateMedia({ colorScheme: 'dark' });
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(toggle).toHaveAttribute('title', 'Use light theme');
        await expect(page.locator('html')).toHaveClass('dark');
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await expect(page.locator('html')).not.toHaveClass('dark');
        assert.equal(await page.evaluate(() => localStorage.getItem('athena-theme')), 'light');
        await page.reload();
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await page.emulateMedia({ colorScheme: 'light' });
        await page.emulateMedia({ colorScheme: 'dark' });
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        const signIn = page.getByRole('link', { name: 'Continue with BatStateU Google' });
        assert.equal(new URL(await signIn.getAttribute('href')).pathname.endsWith('/auth/google'), true);
        assert.deepEqual(new Set(scripts), new Set(['/@vite/client', '/resources/js/guest.js']));
        assert.deepEqual(errors, []);

        await page.addInitScript(() => {
            Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Storage blocked', 'SecurityError'); } });
        });
        await page.reload();
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        assert.equal(await page.evaluate(() => document.documentElement.style.colorScheme), 'light');
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
