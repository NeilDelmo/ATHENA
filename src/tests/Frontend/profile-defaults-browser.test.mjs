import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

test('profile defaults keep repeatable CV fields editable and removable on mobile and desktop', async () => {
    if (process.env.ATHENA_PROFILE_FIXTURE_READY !== '1') {
        execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ProfileTest.php', '--filter=users can save reusable'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_EXPORT_PROFILE_DEFAULTS: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
        });
    }
    const fixture = readFileSync(resolve('storage/framework/testing/profile-defaults.html'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const runtime = readFileSync(resolve('vendor/livewire/livewire/dist/livewire.esm.js'), 'utf8').replace(/export \{[\s\S]*?\};/, '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                const profile = await page.evaluate(source => new DOMParser().parseFromString(source, 'text/html')
                    .querySelector('[data-account-profile]').outerHTML, fixture);
                await page.route('**/*', route => route.request().url() === 'http://localhost/profile-defaults-test'
                    ? route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}</style></head><body class="bg-slate-50 font-sans dark:bg-slate-950"><main class="mx-auto max-w-7xl px-4 py-6">${profile}</main></body></html>` })
                    : route.abort());
                await page.goto('http://localhost/profile-defaults-test');
                await expect(page.getByRole('button', { name: 'Save profile details', exact: true })).toBeDisabled();
                await page.addScriptTag({ content: `window.livewireScriptConfig = {}; (() => { ${runtime}\nAlpine.start(); })();` });
                const form = page.locator('[data-profile-details]');
                await expect(form.getByRole('button', { name: 'Save profile details', exact: true })).toBeEnabled();
                await expect(form.getByRole('textbox', { name: 'Department (optional)', exact: true })).toHaveValue('Computer Science Department');
                await expect(form.getByRole('combobox', { name: 'Professional title (optional)', exact: true })).toHaveValue('Asst Prof.');
                await expect(form.locator('[name="cv_defaults[academic_background][0][degree]"]')).toHaveValue('Master of Science');
                await expect(form.locator('[name="cv_defaults[specializations][0][field]"]')).toHaveValue('Information Systems');

                await form.getByRole('button', { name: 'Add academic background', exact: true }).click();
                const status = form.locator('[name="cv_defaults[academic_background][1][status]"]');
                await status.selectOption('Ongoing');
                await expect(form.locator('[name="cv_defaults[academic_background][1][year_end]"]')).toBeDisabled();
                await form.locator('[name="cv_defaults[academic_background][1][degree]"]').fill('Doctor of Philosophy');
                await form.getByRole('button', { name: 'Add specialization', exact: true }).click();
                await form.locator('[name="cv_defaults[specializations][1][field]"]').fill('Data Science');
                const values = await form.evaluate(element => Object.fromEntries(new FormData(element)));
                assert.equal(values['cv_defaults[academic_background][1][degree]'], 'Doctor of Philosophy');
                assert.equal(values['cv_defaults[specializations][1][field]'], 'Data Science');
                assert.equal(values['cv_defaults[academic_background][1][year_end]'], undefined);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Profile overflow at ${width}px.`);

                if (process.env.PROFILE_DEFAULTS_SCREENSHOT_DIRECTORY) {
                    mkdirSync(process.env.PROFILE_DEFAULTS_SCREENSHOT_DIRECTORY, { recursive: true });
                    await page.locator('[data-account-profile]').screenshot({ path: resolve(process.env.PROFILE_DEFAULTS_SCREENSHOT_DIRECTORY, `profile-defaults-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                for (const section of ['academic_background', 'specializations']) {
                    const sectionElement = form.locator(`[data-profile-cv-section="${section}"]`);
                    while (await sectionElement.getByRole('button', { name: /^Remove / }).count()) {
                        await sectionElement.getByRole('button', { name: /^Remove / }).first().click();
                    }
                    assert.equal(await form.locator(`input[name="cv_defaults[${section}]"]`).inputValue(), '');
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
