import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = path => readFileSync(resolve(path), 'utf8');

test('user directory aligns roles and actions while preserving filters and confirmation dialogs', async () => {
    if (process.env.ATHENA_USER_DIRECTORY_FIXTURE_READY !== '1') {
        for (const [file, filter, exportFlag] of [
            ['FacultyDirectoryTest.php', 'directory separates member roles from college status and keeps assignment actions consistent', 'ATHENA_EXPORT_USER_DIRECTORY'],
            ['ProfileTest.php', 'users can save their college and contact number together from their profile', 'ATHENA_EXPORT_COLLEGE_REMINDER'],
        ]) {
            execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', `tests/Feature/${file}`, `--filter=${filter}`], {
                encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
                env: { ...process.env, [exportFlag]: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
            });
        }
    }
    const fixturePath = resolve('storage/framework/testing/user-directory.html');
    assert.ok(existsSync(fixturePath), 'Export the user directory fixture before running this browser test.');
    const fixture = readFileSync(fixturePath, 'utf8');
    const reminderPath = resolve('storage/framework/testing/college-reminder.html');
    assert.ok(existsSync(reminderPath), 'Export the missing college reminder fixture before running this browser test.');
    const reminderFixture = readFileSync(reminderPath, 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const runtime = read('vendor/livewire/livewire/dist/livewire.esm.js').replace(/export \{[\s\S]*?\};/, '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 1024, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                let mutations = 0;
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                const directoryHtml = await page.evaluate(source => {
                    const document = new DOMParser().parseFromString(source, 'text/html');
                    return document.querySelector('[data-faculty-directory]').outerHTML;
                }, fixture);
                const reminderHtml = await page.evaluate(source => {
                    const document = new DOMParser().parseFromString(source, 'text/html');
                    const reminder = document.querySelector('[data-missing-college-reminder]');
                    reminder.querySelector('a').setAttribute('href', '#college');
                    return reminder.outerHTML + document.querySelector('[data-profile-details]').outerHTML;
                }, reminderFixture);
                await page.route('**/*', route => {
                    if (route.request().method() !== 'GET') {
                        mutations++;
                        return route.abort();
                    }
                    const path = new URL(route.request().url()).pathname;
                    if (['/user-directory-test', '/college-reminder-test'].includes(path)) {
                        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}\n[x-cloak]{display:none!important}\n@media(min-width:640px){main{max-width:calc(100vw - 320px)!important}}</style></head><body x-data="{}" class="font-sans bg-slate-50 dark:bg-slate-950"><main class="mx-auto max-w-7xl px-4 py-6"><div class="athena-page-content w-full">${path === '/user-directory-test' ? directoryHtml : reminderHtml}</div></main></body></html>` });
                    }
                    return route.abort();
                });
                await page.goto('http://localhost/user-directory-test');
                await page.addScriptTag({ content: `window.livewireScriptConfig = {}; document.addEventListener('submit', event => event.preventDefault()); (() => { ${runtime}\nAlpine.plugin(import_focus.default); Alpine.start(); })();` });

                const directory = page.locator('[data-faculty-directory]');
                const rows = directory.locator('[data-member-row]');
                const visibleRows = directory.locator('[data-member-row]:visible');
                const table = directory.locator('table');
                const wrapper = table.locator('..');
                await expect(rows).toHaveCount(3);
                await expect(rows.first()).toBeVisible();
                assert.equal(await table.evaluate(element => getComputedStyle(element).tableLayout), 'fixed');

                const aligned = async () => {
                    const sizes = await rows.locator('button:visible').evaluateAll(buttons => buttons.map(button => {
                        const bounds = button.getBoundingClientRect();
                        return { width: bounds.width, height: bounds.height };
                    }));
                    assert.ok(sizes.length >= 2);
                    for (const size of sizes) {
                        assert.ok(size.height >= 44, 'Directory actions need 44px touch targets.');
                        assert.ok(Math.abs(size.width - sizes[0].width) < 1, 'Both role columns must use the same action width.');
                        assert.ok(Math.abs(size.height - sizes[0].height) < 1, 'Assign, Remove, and Unavailable must use the same action height.');
                    }
                    for (const row of await visibleRows.all()) {
                        const buttons = row.locator('button');
                        const first = await buttons.first().boundingBox();
                        const second = await buttons.last().boundingBox();
                        assert.ok(Math.abs(first.y - second.y) < 1, 'Role actions must line up within each member row.');
                    }
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Page overflow at ${width}px.`);
                    assert.equal(await wrapper.evaluate(element => getComputedStyle(element).overflowX), 'auto');
                    assert.equal(await wrapper.evaluate(element => element.scrollWidth > element.clientWidth), width < 1440);
                    if (width < 1440) {
                        await wrapper.evaluate(element => { element.scrollLeft = element.scrollWidth; });
                        assert.ok(await wrapper.evaluate(element => element.scrollLeft) > 0, 'Role columns must remain reachable by horizontal scrolling.');
                    }
                    const lastAction = await visibleRows.first().locator('button').last().boundingBox();
                    const wrapperBounds = await wrapper.boundingBox();
                    assert.ok(lastAction.x >= wrapperBounds.x && lastAction.x + lastAction.width <= wrapperBounds.x + wrapperBounds.width, 'The Secretary action must fit inside the table viewport.');
                    await wrapper.evaluate(element => { element.scrollLeft = 0; });
                };
                await aligned();

                for (const roles of await directory.locator('[data-member-roles]').all()) {
                    const name = roles.locator('..').locator(':scope > span');
                    const nameBounds = await name.boundingBox();
                    let previousBottom = nameBounds.y + nameBounds.height;
                    for (const badge of await roles.locator(':scope > span').all()) {
                        const bounds = await badge.boundingBox();
                        assert.ok(bounds.y >= previousBottom, 'Role badges must sit below the name and each other.');
                        previousBottom = bounds.y + bounds.height;
                    }
                }
                const awaitingCollege = rows.filter({ hasText: 'Member Awaiting College' });
                await expect(awaitingCollege.locator('td').first()).not.toContainText('College required');
                await expect(awaitingCollege.locator('td').nth(2)).toContainText('College required');
                await expect(awaitingCollege.locator('[data-coordinator-ineligible]')).toBeDisabled();
                await expect(awaitingCollege.locator('[data-member-roles]')).toContainText('Research Office / Secretary');

                if (process.env.USER_DIRECTORY_SCREENSHOT_DIRECTORY) {
                    mkdirSync(process.env.USER_DIRECTORY_SCREENSHOT_DIRECTORY, { recursive: true });
                    await directory.screenshot({ path: resolve(process.env.USER_DIRECTORY_SCREENSHOT_DIRECTORY, `user-directory-${width}-${dark ? 'dark' : 'light'}.png`) });
                }

                await directory.locator('[data-college-tab="CICS"]').click();
                await expect(visibleRows).toHaveCount(1);
                await expect(table.locator('thead th').nth(2)).toBeHidden();
                assert.deepEqual(errors, []);
                await expect.poll(() => new URL(page.url()).searchParams.get('college')).toBe('CICS');
                await aligned();
                await directory.locator('[data-college-tab="all"]').click();
                await expect(visibleRows).toHaveCount(3);
                const search = directory.getByRole('searchbox', { name: 'Search users' });
                for (const query of ['Member Awaiting College', 'awaiting.college@g.batstate-u.edu.ph']) {
                    await search.fill(query);
                    await expect(visibleRows).toHaveCount(1);
                    await expect(awaitingCollege).toBeVisible();
                    await expect.poll(() => new URL(page.url()).searchParams.get('search')).toBe(query);
                }
                await search.fill('no matching directory member');
                await expect(visibleRows).toHaveCount(0);
                await expect(directory.getByText('No members found', { exact: true })).toBeVisible();
                await search.fill('');
                await expect(visibleRows).toHaveCount(3);
                await expect.poll(() => new URL(page.url()).searchParams.has('search')).toBe(false);

                const dialog = directory.getByRole('dialog');
                const checkedRoles = new Set();
                for (const button of await rows.locator('button:not([disabled])').all()) {
                    const label = await button.getAttribute('aria-label');
                    const [, action, role, name] = label.match(/^(Assign|Remove) (.+) (?:to|from) (.+)$/);
                    checkedRoles.add(role);
                    await button.click();
                    await expect(dialog).toBeVisible();
                    await expect(dialog.getByRole('heading')).toHaveText(`${action} ${role}?`);
                    await expect(dialog).toContainText(name);
                    await expect(dialog.locator('form')).toHaveAttribute('action', new RegExp(`/faculty-directory/\\d+/${role === 'Research Coordinator' ? 'coordinator' : 'secretary'}$`));
                    await expect(dialog.locator('input[name="action"]')).toHaveValue(action.toLowerCase());
                    await expect(dialog.locator('input[name="_method"]')).toHaveValue('PATCH');
                    const cancel = dialog.getByRole('button', { name: 'Cancel', exact: true }).first();
                    await expect(cancel).toBeFocused();
                    const submit = dialog.getByRole('button', { name: `${action} ${role}`, exact: true });
                    await cancel.press('Shift+Tab');
                    await expect(submit).toBeFocused();
                    await submit.press('Tab');
                    await expect(cancel).toBeFocused();
                    await page.keyboard.press('Escape');
                    await expect(dialog).toBeHidden();
                    await expect(button).toBeFocused();
                    await button.click();
                    await expect(cancel).toBeFocused();
                    await cancel.press('Enter');
                    await expect(dialog).toBeHidden();
                    await expect(button).toBeFocused();
                }
                assert.deepEqual([...checkedRoles].sort(), ['Research Coordinator', 'Research Office / Secretary']);

                await page.goto('http://localhost/college-reminder-test#college');
                await page.addScriptTag({ content: `window.livewireScriptConfig = {}; document.addEventListener('submit', event => event.preventDefault()); (() => { ${runtime}\nAlpine.plugin(import_focus.default); Alpine.start(); })();` });
                const college = page.getByRole('combobox', { name: 'College', exact: true });
                const reminder = page.locator('[data-missing-college-reminder]');
                await expect(reminder).toBeVisible();
                await expect(college).toBeFocused();
                await expect(college).toHaveAttribute('required', '');
                assert.equal(await college.evaluate(element => element.validity.valueMissing), true);
                const reminderBounds = await reminder.boundingBox();
                const formBounds = await page.locator('[data-profile-details]').boundingBox();
                assert.ok(reminderBounds.y + reminderBounds.height <= formBounds.y, 'The reminder must sit above the profile form.');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'College reminder must fit the page.');
                await page.evaluate(() => { location.hash = 'profile-details'; });
                await reminder.getByRole('link', { name: 'Set your college', exact: true }).click();
                await expect(college).toBeFocused();
                assert.equal(new URL(page.url()).hash, '#college');
                if (process.env.USER_DIRECTORY_SCREENSHOT_DIRECTORY) {
                    await page.locator('.athena-page-content').screenshot({ path: resolve(process.env.USER_DIRECTORY_SCREENSHOT_DIRECTORY, `college-reminder-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                assert.deepEqual(errors, []);
                assert.equal(mutations, 0);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
