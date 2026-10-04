import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const viewRoot = resolve('resources/views');
const readView = (path) => readFileSync(resolve(viewRoot, path), 'utf8');

function component(source, tag, slot, fixed = true) {
    const declarations = source.match(/->class\(\[([\s\S]*?)\]\)/)[1];
    const classes = [...declarations.matchAll(/'([^']+)'(?: => ([^,\r\n]+))?,/g)]
        .filter(([, , condition]) => !condition || condition.trim() === 'true' || (fixed ? condition.trim() === '$fixed' : condition.trim() === '! $fixed'))
        .map(([, value]) => value);
    classes.push(source.match(/'class' => '([^']+)'/)?.[1] || '');
    return source.slice(source.indexOf(`<${tag}`), source.lastIndexOf(`</${tag}>`) + tag.length + 3)
        .replace(/@if[\s\S]*?@endif/g, '')
        .replace('{{ $slot }}', slot)
        .replace(/\{\{[\s\S]*?\}\}/, `class="${classes.join(' ')}" ${tag === 'a' ? `href="/proposal#attachments" ${fixed ? 'data-fixed-back-link' : ''} data-paper-cancel-exit` : (fixed ? 'data-monitoring-action-dock-fixed' : '')}`);
}

function sourceFixtures() {
    const back = readView('components/back-link.blade.php');
    const pages = [];
    const collect = (directory) => {
        for (const file of readdirSync(directory, { withFileTypes: true })) {
            const path = resolve(directory, file.name);
            if (file.isDirectory()) { collect(path); continue; }
            for (const match of readFileSync(path, 'utf8').matchAll(/<x-back-link\s([^>]*?)>(.*?)<\/x-back-link>/gs)) {
                if (!/(?:^|\s)fixed(?:\s|$)/.test(match[1])) continue;
                const label = match[2].replace(/\{\{.*?\}\}/gs, 'submitted proposal').trim();
                pages.push({ view: path.slice(viewRoot.length + 1), html: component(back, 'a', label) });
            }
        }
    };
    collect(viewRoot);
    const controls = `${component(back, 'a', 'Exit monitoring', false)}<button type="button" class="min-h-12 rounded-xl bg-white px-5 py-3">Save draft</button><button type="button" class="min-h-12 rounded-xl bg-white px-5 py-3">Preview report</button><form action="/prepare" method="POST"><button name="prepare" value="1" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 text-white">Prepare official PDF</button></form><button type="button" disabled class="min-h-12 rounded-xl bg-white px-5 py-3">Submit to Research Head</button>`;
    const drawer = readView('components/research-assistant-drawer.blade.php');
    return {
        pages, dock: component(readView('components/monitoring-action-dock.blade.php'), 'div', controls),
        inline: component(readView('components/monitoring-action-dock.blade.php'), 'div', '<button>Preview report</button>', false),
        launcher: drawer.slice(0, drawer.indexOf('</button>') + '</button>'.length),
    };
}

function statusFixture() {
    const source = readView('topics/partials/project-monitoring.blade.php');
    const start = source.indexOf('        <div\n            x-data="projectStatusManager');
    const end = source.indexOf('\n    @endif', start);
    return source.slice(start, end)
        .replace(/x-data="[^"]*"/, 'x-data="projectStatusManager(\'ongoing\')"')
        .replace(/@error\([\s\S]*?@enderror/g, '')
        .replace(/@foreach[\s\S]*?@endforeach/g, '<option value="ongoing">Ongoing</option><option value="delayed">Delayed</option><option value="completed">Completed</option>')
        .replace(/@csrf|@method\([^)]*\)/g, '')
        .replace('{{ $projectStatusLabel }}', 'Completion pending')
        .replace('{{ $floatingStatusClasses }}', 'bg-blue-50 text-blue-700')
        .replace(/\{\{[\s\S]*?\}\}/g, 'test-project');
}

function topicActionsFixture() {
    const source = readView('topics/show.blade.php');
    const observerInit = source.match(/this\.\$nextTick\(\(\) => \{\s*this\.floatingBackLinkObserver[\s\S]*?(?=\s*this\.scrollToTopicHash\(\);)/)[0];
    const destroy = source.match(/destroy\(\) \{[\s\S]*?\n            \},/)[0];
    const back = component(readView('components/back-link.blade.php'), 'a', 'Back to submitted proposals')
        .replace('<a', '<a x-ref="workspaceBackLink" data-topic-workspace-back-link x-show="!$store.researchAssistant.drawerOpen && !$store.researchAssistant.workspaceOpen"');
    return `<div data-topic-workspace x-data="{ activeTopicTab: 'monitoring', floatingBackLinkObserver: null, init() { ${observerInit} }, ${destroy} }">
        <nav style="padding-left:var(--athena-page-action-left)"><button data-switch-review @click="activeTopicTab = 'review'">Review</button><button data-switch-monitoring @click="activeTopicTab = 'monitoring'">Monitoring</button></nav>
        ${back}<div x-show="activeTopicTab === 'monitoring'">${statusFixture()}</div>
    </div>`;
}

function filesTriggerFixture() {
    const source = readView('components/project-document-drawer.blade.php');
    return source.match(/<button[\s\S]*?<\/button>/)[0]
        .replace(/\{\{ \$floating \? '([^']*)' : '[^']*' \}\}/g, '$1')
        .replace(/@if \(\$floating\) data-project-documents-floating-trigger @else data-project-documents-inline-trigger @endif/, 'data-project-documents-floating-trigger')
        .replace(/\{\{[\s\S]*?\}\}/g, '3');
}

test('every fixed page action clears the chatbot across mobile tablet desktop and larger text', async () => {
    const fixtures = process.env.FLOATING_ACTIONS_QA_PATH
        ? JSON.parse(readFileSync(process.env.FLOATING_ACTIONS_QA_PATH, 'utf8')) : sourceFixtures();
    assert.ok(fixtures.pages.length >= 20);
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const viewports = [320, 390, 639, 640, 768, 1024, 1440].map((width) => ({ width, height: 700 }));
        viewports.push({ width: 640, height: 360 }, { width: 390, height: 280 });
        for (const { width, height } of viewports) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html data-sidebar-collapsed="true" class="${dark ? 'dark' : ''}" style="font-size:${width === 390 ? 20 : 16}px"><style>${styles.css}</style>
                    <body data-app-shell x-data class="bg-white dark:bg-slate-950"><aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 w-[76px] bg-white sm:w-[280px]"><button>Profile</button></aside><div data-app-content-shell class="pl-[76px] sm:pl-[280px]"><main class="px-4 py-6">
                    <div style="height:2200px">Page contents</div><div class="flex justify-end"><button id="last-control">Save changes</button></div>
                    <div id="inline">${fixtures.inline}</div></main></div>
                    ${fixtures.pages.map((fixture, index) => `<div data-page-fixture="${index}" style="display:none">${fixture.html}</div>`).join('')}
                    <div id="status-fixture" style="display:none">${statusFixture()}</div><div id="topic-actions-fixture" style="display:none">${topicActionsFixture()}${filesTriggerFixture()}</div><div id="dock-fixture" style="display:none">${fixtures.dock}</div>${fixtures.launcher}</body></html>`);
                await page.addScriptTag({ content: `${readFileSync(resolve('resources/js/project-status-manager.js'), 'utf8').replace('export function', 'function')}
                    document.addEventListener('alpine:init', () => { Alpine.data('projectStatusManager', projectStatusManager); Alpine.store('researchAssistant', {workspaceOpen:false, drawerOpen:false, toggleDrawer(){this.drawerOpen = !this.drawerOpen}}) });
                    window.exitClicks = 0; document.addEventListener('click', (event) => {if (event.target.closest('[data-back-link]')) {event.preventDefault(); window.exitClicks++}});
                    document.addEventListener('submit', (event) => {event.preventDefault(); window.prepared = event.submitter.name});` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await page.waitForFunction(() => document.getElementById('research-assistant-launcher').offsetWidth > 0);
                const checked = await page.evaluate(() => {
                    const launcher = document.getElementById('research-assistant-launcher').getBoundingClientRect();
                    return [true, false].flatMap((collapsed) => {
                        document.documentElement.dataset.sidebarCollapsed = String(collapsed);
                        return [...document.querySelectorAll('[data-page-fixture]')].map((fixture) => {
                            fixture.style.display = 'block';
                            const link = fixture.querySelector('[data-fixed-back-link]');
                            const rect = link.getBoundingClientRect();
                            const range = document.createRange();
                            range.selectNodeContents(link.lastElementChild);
                            const text = range.getBoundingClientRect();
                            const hit = document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
                            const sidebar = document.getElementById('app-sidebar').getBoundingClientRect();
                            const result = { gap: launcher.left - rect.right, left: rect.left, bottom: rect.bottom, hit: link.contains(hit), textFits: text.right <= rect.right && text.left >= rect.left, sidebarGap: rect.left - sidebar.right };
                            fixture.style.display = 'none';
                            return result;
                        });
                    });
                });
                checked.forEach((result, index) => {
                    const view = fixtures.pages[index % fixtures.pages.length].view;
                    assert.ok(result.gap >= 11, `${width}px ${view}: chatbot clearance`);
                    assert.ok(result.left >= 15, `${width}px ${view}: inside viewport`);
                    assert.ok(result.sidebarGap >= 15, `${width}px ${view}: sidebar clearance`);
                    assert.ok(result.bottom <= height);
                    assert.equal(result.textFits, true, `${width}px ${view}: readable label`);
                    assert.equal(result.hit, true, `${width}px ${view}: reachable`);
                });
                await page.locator('[data-page-fixture="0"]').evaluate((element) => { element.style.display = 'block'; });
                await page.locator('[data-page-fixture="0"] a').click();
                assert.equal(await page.evaluate(() => window.exitClicks), 1);
                await page.locator('#research-assistant-launcher').click();
                assert.equal(await page.locator('#research-assistant-launcher').getAttribute('aria-expanded'), 'true');
                await page.locator('#research-assistant-launcher').click();
                await page.locator('[data-page-fixture="0"]').evaluate((element) => { element.style.display = 'none'; });
                await page.locator('#status-fixture').evaluate((element) => { element.style.display = 'block'; });
                const status = page.locator('#status-fixture [data-project-status-manager]');
                await status.waitFor({ state: 'visible' });
                for (const collapsed of [true, false]) {
                    await page.evaluate((value) => { document.documentElement.dataset.sidebarCollapsed = String(value); }, collapsed);
                    const statusBounds = await status.evaluate((element) => {
                        const rect = element.getBoundingClientRect();
                        const trigger = element.querySelector('[x-ref="statusTrigger"]');
                        const button = trigger.getBoundingClientRect();
                        const range = document.createRange(); range.selectNodeContents(trigger.lastElementChild);
                        const label = range.getBoundingClientRect();
                        return { gap: document.getElementById('research-assistant-launcher').getBoundingClientRect().left - rect.right,
                            sidebarGap: rect.left - document.getElementById('app-sidebar').getBoundingClientRect().right,
                            fits: button.left >= rect.left && button.right <= rect.right && label.left >= button.left && label.right <= button.right };
                    });
                    assert.ok(statusBounds.gap >= 11, `${width}px monitoring status: chatbot clearance`);
                    assert.ok(statusBounds.sidebarGap >= 15, `${width}px monitoring status: sidebar clearance ${JSON.stringify(statusBounds)}`);
                    assert.equal(statusBounds.fits, true, `${width}px monitoring status: readable trigger`);
                    await status.locator('[x-ref="statusTrigger"]').click();
                    const popup = status.locator('[role="dialog"]');
                    await popup.waitFor({ state: 'visible' });
                    assert.equal(await popup.evaluate((element) => {
                        const rect = element.getBoundingClientRect();
                        return rect.top >= 0 && rect.left >= document.getElementById('app-sidebar').getBoundingClientRect().right && rect.right <= innerWidth;
                    }), true, `${width}px monitoring status: popup inside available viewport`);
                    for (const control of await popup.locator('select:visible, button:visible').all()) {
                        await control.scrollIntoViewIfNeeded();
                        assert.equal(await control.evaluate((element) => {
                            const rect = element.getBoundingClientRect();
                            return element.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
                        }), true, `${width}px monitoring status: reachable popup controls`);
                    }
                    await popup.locator('[aria-label="Close status manager"]').click();
                    await popup.waitFor({ state: 'hidden' });
                }
                await page.locator('#research-assistant-launcher').click();
                await status.waitFor({ state: 'hidden' });
                assert.equal(await status.isVisible(), false);
                await page.locator('#research-assistant-launcher').click();
                await status.waitFor({ state: 'visible' });
                assert.equal(await status.isVisible(), true);
                await page.locator('#status-fixture').evaluate((element) => { element.style.display = 'none'; });
                const topicActions = page.locator('#topic-actions-fixture');
                await topicActions.evaluate((element) => { element.style.display = 'block'; });
                const topicBack = topicActions.locator('[data-topic-workspace-back-link]');
                const topicStatus = topicActions.locator('[data-project-status-manager]');
                for (const collapsed of [true, false]) {
                    await page.evaluate((value) => { document.documentElement.dataset.sidebarCollapsed = String(value); }, collapsed);
                    await page.waitForFunction(() => {
                        const root = document.querySelector('#topic-actions-fixture [data-topic-workspace]');
                        return Math.abs(parseFloat(root.style.getPropertyValue('--athena-topic-back-link-height')) - root.querySelector('[data-topic-workspace-back-link]').getBoundingClientRect().height) < 1;
                    });
                    const bounds = await topicActions.evaluate((element) => {
                        const back = element.querySelector('[data-topic-workspace-back-link]');
                        const status = element.querySelector('[data-project-status-manager]');
                        const backRect = back.getBoundingClientRect();
                        const statusRect = status.getBoundingClientRect();
                        const filesRect = element.querySelector('[data-project-documents-floating-trigger]').getBoundingClientRect();
                        const launcher = document.getElementById('research-assistant-launcher').getBoundingClientRect();
                        const overlapsFiles = (rect) => rect.left < filesRect.right && rect.right > filesRect.left && rect.top < filesRect.bottom && rect.bottom > filesRect.top;
                        return { verticalGap: backRect.top - statusRect.bottom, chatbotGap: launcher.left - backRect.right,
                            statusTop: statusRect.top, overlapsFiles: overlapsFiles(backRect) || overlapsFiles(statusRect),
                            backReachable: back.contains(document.elementFromPoint(backRect.x + backRect.width / 2, backRect.y + backRect.height / 2)) };
                    });
                    assert.ok(bounds.verticalGap >= 11, `${width}x${height} topic: back and status clearance`);
                    assert.ok(bounds.chatbotGap >= 11, `${width}x${height} topic: back and chatbot clearance`);
                    assert.ok(bounds.statusTop >= 0, `${width}x${height} topic: status inside viewport`);
                    assert.equal(bounds.overlapsFiles, false, `${width}x${height} topic: project files clearance`);
                    assert.equal(bounds.backReachable, true);
                    await topicStatus.locator('[x-ref="statusTrigger"]').click();
                    const popup = topicStatus.locator('[role="dialog"]');
                    await popup.waitFor({ state: 'visible' });
                    const popupBounds = await popup.evaluate((element) => {
                        const rect = element.getBoundingClientRect();
                        return { top: rect.top, left: rect.left, right: rect.right, sidebarRight: document.getElementById('app-sidebar').getBoundingClientRect().right,
                            maxHeight: getComputedStyle(element).maxHeight, height: rect.height };
                    });
                    assert.ok(popupBounds.top >= 0 && popupBounds.left >= popupBounds.sidebarRight && popupBounds.right <= width,
                        `${width}x${height} topic: stacked popup inside viewport ${JSON.stringify(popupBounds)}`);
                    for (const control of await popup.locator('select:visible, button:visible').all()) {
                        await control.scrollIntoViewIfNeeded();
                        assert.equal(await control.evaluate((element) => {
                            const rect = element.getBoundingClientRect();
                            return element.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
                        }), true, `${width}x${height} topic: reachable status controls`);
                    }
                    await popup.locator('[aria-label="Close status manager"]').click();
                    await popup.waitFor({ state: 'hidden' });
                    await topicActions.locator('[data-switch-review]').click();
                    await topicStatus.waitFor({ state: 'hidden' });
                    assert.equal(await topicBack.isVisible(), true);
                    await topicActions.locator('[data-switch-monitoring]').click();
                    await topicStatus.waitFor({ state: 'visible' });
                }
                await topicBack.click();
                assert.equal(await page.evaluate(() => window.exitClicks), 2);
                await page.locator('#research-assistant-launcher').click();
                await topicBack.waitFor({ state: 'hidden' });
                await topicStatus.waitFor({ state: 'hidden' });
                await page.locator('#research-assistant-launcher').click();
                await topicBack.waitFor({ state: 'visible' });
                await topicStatus.waitFor({ state: 'visible' });
                await page.locator('#research-assistant-launcher').hover();
                const hintClear = await page.locator('#research-assistant-launcher > span[aria-hidden="true"]').evaluate((element) => {
                    const hint = element.getBoundingClientRect();
                    return [...document.querySelectorAll('#topic-actions-fixture [data-topic-workspace-back-link], #topic-actions-fixture [data-project-status-manager], #topic-actions-fixture [data-project-documents-floating-trigger]')]
                        .every((control) => {
                            const rect = control.getBoundingClientRect();
                            return hint.right <= rect.left || hint.left >= rect.right || hint.bottom <= rect.top || hint.top >= rect.bottom;
                        });
                });
                assert.equal(hintClear, true, `${width}x${height} topic: assistant tooltip clearance`);
                await page.emulateMedia({ media: 'print' });
                assert.equal(await topicBack.isVisible(), false);
                assert.equal(await topicStatus.isVisible(), false);
                await page.emulateMedia({ media: 'screen' });
                if (process.env.FLOATING_ACTIONS_SCREENSHOT_DIR && height === 700 && [390, 1440].includes(width)) {
                    await page.screenshot({ path: resolve(process.env.FLOATING_ACTIONS_SCREENSHOT_DIR, `monitoring-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                await topicActions.evaluate((element) => { element.style.display = 'none'; });
                await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
                const inlineClear = await page.locator('#last-control').evaluate((button) => button.getBoundingClientRect().bottom <= document.getElementById('research-assistant-launcher').getBoundingClientRect().top);
                assert.equal(inlineClear, true);
                assert.equal(await page.locator('#inline [data-monitoring-action-dock]').evaluate((element) => getComputedStyle(element).position), 'static');
                await page.locator('#dock-fixture').evaluate((element) => { element.style.display = 'block'; });
                const dock = page.locator('[data-monitoring-action-dock-fixed]');
                assert.ok(await dock.evaluate((element) => document.getElementById('research-assistant-launcher').getBoundingClientRect().left - element.getBoundingClientRect().right >= 11));
                for (const button of await dock.locator('a, button').all()) {
                    await button.scrollIntoViewIfNeeded();
                    assert.equal(await button.evaluate((element) => { const rect = element.getBoundingClientRect(); return element.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2)); }), true);
                }
                await dock.locator('[name="prepare"]').click();
                assert.equal(await page.evaluate(() => window.prepared), 'prepare');
                assert.equal(await dock.locator('button[disabled]').isEnabled(), false);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                await page.emulateMedia({ media: 'print' });
                assert.equal(await dock.isVisible(), false);
                assert.equal(await page.locator('#research-assistant-launcher').isVisible(), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
        const guest = await browser.newPage({ viewport: { width: 390, height: 700 } });
        await guest.setContent(`<style>${styles.css}</style><body><div data-app-content-shell><main class="p-6"><button>Continue</button></main></div>${fixtures.pages[0].html}</body>`);
        assert.equal(await guest.locator('main').evaluate((element) => getComputedStyle(element).paddingTop), '24px');
        assert.equal(await guest.locator('[data-fixed-back-link]').evaluate((element) => getComputedStyle(element).right), '16px');
        await guest.close();
    } finally {
        await browser.close();
    }
});
