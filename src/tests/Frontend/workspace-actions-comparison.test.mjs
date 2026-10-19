import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

function renderedFixtures() {
    const php = String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        $user = App\Models\User::factory()->make(['id' => 41]);
        $user->setRelation('roles', collect([new Spatie\Permission\Models\Role(['name' => 'faculty'])]));
        auth()->setUser($user);
        session()->put(App\Models\User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty');
        $source = file_get_contents('resources/views/notifications/index.blade.php');
        $source = substr($source, strpos($source, '    <div'));
        $source = str_replace('</x-app-layout>', '', $source);
        $inbox = Illuminate\Support\Facades\Blade::render($source, [
            'pendingActions' => [['key' => 'proposal:1', 'label' => 'Address proposal revisions', 'title' => '<img src=x onerror=alert(1)> ' . str_repeat('LongProjectTitle', 9), 'url' => 'https://athena.test/revise/1']],
            'categories' => collect([['key' => 'reviews', 'label' => 'Reviews', 'description' => 'Review activity', 'count' => 2]]),
            'unreadCount' => 1,
            'notificationItems' => collect([
                ['id' => 'first', 'category' => 'reviews', 'category_label' => 'Reviews', 'read_at' => null, 'search_text' => 'first review', 'created_at' => 'Today', 'created_at_iso' => '2026-10-19T00:00:00Z', 'data' => ['title' => 'First review', 'message' => 'Unread history']],
                ['id' => 'second', 'category' => 'reviews', 'category_label' => 'Reviews', 'read_at' => '2026-10-19T00:00:00Z', 'search_text' => 'second review', 'created_at' => 'Yesterday', 'created_at_iso' => '2026-10-18T00:00:00Z', 'data' => ['title' => 'Second review', 'message' => 'Read history']],
            ]),
        ]);
        $comparison = view('components.proposal-version-content-comparison', [
            'previousNumber' => 1, 'latestNumber' => 2,
            'papers' => [
                ['label' => 'Detailed Research Proposal', 'mode' => 'structured', 'sections' => [['label' => 'Rationale', 'before' => [['text' => 'Original paragraph', 'changed' => true]], 'after' => [['text' => '<img src=x onerror=alert(1)> ' . str_repeat('LongResearchFinding', 18), 'changed' => true]]]], 'figures' => [['status' => 'added', 'before' => '', 'after' => 'Figure 1: New chart']]],
                ['label' => 'Legacy uploaded Work Plan', 'mode' => 'file', 'sections' => [], 'figures' => []],
            ],
        ])->render();
        echo json_encode(compact('inbox', 'comparison'), JSON_THROW_ON_ERROR);
    `;
    const result = spawnSync('php', ['-r', php], { cwd: resolve('.'), encoding: 'utf8', timeout: 30000 });
    assert.equal(result.status, 0, (result.stderr || result.stdout).slice(-3000));
    return JSON.parse(result.stdout);
}

test('current actions and submitted comparisons are responsive, keyboard usable, safely rendered, and refresh independently from read history', { timeout: 120000 }, async context => {
    const fixtures = renderedFixtures();
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 768, 1280]) for (const dark of [false, true]) {
            context.diagnostic(`${width}px ${dark ? 'dark' : 'light'}`);
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}\n[x-cloak]{display:none!important}</style></head><body>${fixtures.inbox}<section class="mx-4">${fixtures.comparison}</section></body></html>`);
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            const actionView = page.getByRole('button', { name: 'Needs your action 1', exact: true });
            await expect(page.locator('[data-notification-item]:visible')).toHaveCount(2);
            await actionView.focus();
            await page.keyboard.press('Enter');
            await expect(page.locator('[data-workspace-action-items]')).toBeVisible();
            await expect(page.locator('[data-workspace-action="proposal:1"]')).toContainText('<img src=x onerror=alert(1)>');
            await expect(page.locator('[data-workspace-action="proposal:1"]')).toHaveAttribute('href', 'https://athena.test/revise/1');
            await expect(page.locator('[data-workspace-action-items] img')).toHaveCount(0);
            assert.ok((await actionView.boundingBox()).height >= 44);
            await page.evaluate(() => window.dispatchEvent(new CustomEvent('athena-workspace-actions', { detail: { workspace: 'research_head', actions: [] } })));
            await expect(page.locator('[data-workspace-action]')).toHaveCount(1);
            await page.evaluate(() => window.dispatchEvent(new CustomEvent('athena-workspace-actions', { detail: { workspace: 'faculty', actions: [] } })));
            await expect(page.locator('[data-pending-action-count]')).toHaveText('0');
            await expect(page.getByText('No work awaiting your action', { exact: true })).toBeVisible();
            await page.getByRole('button', { name: 'All activity', exact: true }).click();
            await expect(page.locator('[data-notification-item]:visible')).toHaveCount(2);
            await page.getByRole('button', { name: 'Read', exact: true }).click();
            await expect(page.locator('[data-notification-item]:visible')).toHaveCount(1);
            await expect(page.getByText('Second review', { exact: true })).toBeVisible();
            await page.getByRole('button', { name: 'Unread', exact: true }).click();
            await expect(page.getByText('First review', { exact: true })).toBeVisible();
            const summary = page.locator('[data-submitted-content-comparison] summary').first();
            await summary.focus();
            await page.keyboard.press('Enter');
            await expect(page.getByText('Original paragraph', { exact: false })).toBeVisible();
            await expect(page.locator('[data-submitted-content-comparison]')).toContainText('Added or revised:');
            await expect(page.locator('[data-submitted-content-comparison] img')).toHaveCount(0);
            await page.locator('[data-submitted-content-comparison] summary').nth(1).click();
            await expect(page.getByText(/This uploaded or older paper/)).toBeVisible();
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Overflow ${width} ${dark}`);
            assert.deepEqual(errors, []);
            if (process.env.WORKSPACE_ACTION_SCREENSHOTS && [320, 1280].includes(width)) {
                mkdirSync(process.env.WORKSPACE_ACTION_SCREENSHOTS, { recursive: true });
                await page.getByRole('button', { name: 'Needs your action 0', exact: true }).click();
                await page.screenshot({ path: resolve(process.env.WORKSPACE_ACTION_SCREENSHOTS, `workspace-actions-comparison-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            }
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('notification polling refreshes tasks for the workspace returned by the server without altering read markers', async () => {
    const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
    const registration = app.slice(app.indexOf("Alpine.data('notificationMenu'"), app.indexOf("Alpine.data('fileDropzone'"));
    let factory;
    const events = [];
    const response = { notifications: [{ id: 'read-note', read_at: 'saved-read-marker' }], unread_count: 0, pending_actions: [], workspace: 'research_head' };
    const scope = new Function('Alpine', 'window', 'fetch', 'CustomEvent', `${registration};`);
    scope({ data: (_name, value) => { factory = value; } }, { dispatchEvent: event => events.push(event) }, async () => ({ ok: true, json: async () => response }), class { constructor(type, values) { this.type = type; Object.assign(this, values); } });
    const state = factory({ notifications: [], unreadCount: 1, workspace: 'faculty', indexUrl: '/notifications' });
    await state.refresh();
    assert.equal(state.notifications[0].read_at, 'saved-read-marker');
    assert.equal(state.unreadCount, 0);
    assert.deepEqual(events[0].detail, { workspace: 'research_head', actions: [] });
});
