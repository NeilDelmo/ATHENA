import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const section = execFileSync('php', ['-r', String.raw`
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    preg_match('/<section data-revision-section="section-methodology".*?<\/section>/s', file_get_contents(resource_path('views/faculty/proposal-drafts/detailed-proposal/edit.blade.php')), $section);
    echo Illuminate\Support\Facades\Blade::render($section[0], [
        'sectionHeadings' => ['methodology' => 'XII. Methodology:'],
        'methodologyFields' => ['specific_methods' => 'Specific methods'],
    ]);
`], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
const editor = readFileSync(resolve('resources/js/detailed-proposal-editor.js'), 'utf8')
    .replace(/^import .+;\r?\n/gm, '').replace('export default function ', 'function ');

test('specific methods fill the writing width and preserve wording when adding or removing rows', async () => {
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const workspaceStyles = readFileSync(resolve('resources/css/proposal-paper-workspace.css'), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 768, 1280]) for (const dark of [false, true]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${styles.css}\n${workspaceStyles}[x-cloak]{display:none!important}</style></head>
                <body class="bg-gray-100 font-sans dark:bg-slate-950"><main data-proposal-paper-workspace x-data="methodsFixture" class="mx-auto p-4" style="max-width:1050px">
                    <form x-ref="form" data-detailed-proposal-autosave-form>${section}</form>
                </main></body></html>`);
            await page.addScriptTag({ content: `
                const proposalSourceWorkspace = () => ({}), proposalPreviewWorkspace = () => ({}), detailedProposalPreviewWorkspace = () => ({});
                ${editor}
                window.autosaves = 0;
                document.addEventListener('alpine:init', () => {
                    registerDetailedProposalEditor({ data(name, factory) {
                        Alpine.data('methodsFixture', () => {
                            const state = factory({});
                            state.init = function() {
                                window.methodsState = this;
                                this.specificMethodGroups = this.normalizedSpecificMethodGroups([
                                    { heading: 'Participant recruitment', methods: [{ description: 'Invite residents through the community coordinators.' }] },
                                    { heading: 'Data collection', methods: [{ description: 'Conduct interviews using the approved interview guide.' }] },
                                ]);
                            };
                            state.triggerDetailedProposalAutoSave = () => window.autosaves++;
                            return state;
                        });
                    } }, {});
                });
            ` });
            await page.addScriptTag({ content: readFileSync(resolve('node_modules/alpinejs/dist/cdn.min.js'), 'utf8') });
            const groups = page.locator('[data-specific-method-group]');
            await expect(groups).toHaveCount(2);
            await expect(page.locator('[data-revision-section="section-methodology"]')).toHaveCSS('background-color', dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
            if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                mkdirSync(process.env.ATHENA_EDITOR_VISUAL_DIR, { recursive: true });
                await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `methods-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            }
            const addGroup = page.getByRole('button', { name: 'Add heading', exact: true });
            await expect(addGroup).toHaveText('Add heading');
            const first = await groups.nth(0).boundingBox();
            const second = await groups.nth(1).boundingBox();
            const gridWidth = await groups.nth(0).locator('..').evaluate(element => element.clientWidth);
            if (gridWidth >= 768) assert.ok(Math.abs(second.y - first.y) < 1, 'Wide editing panes can fit two adequately sized cards');
            else assert.ok(second.y >= first.y + first.height, 'Narrow editing panes stack the cards');
            assert.ok(Math.abs(first.width - second.width) < 1);
            await expect(page.getByRole('heading', { name: 'Group A', exact: true })).toHaveCount(0);
            await expect(page.getByText('Group heading', { exact: true })).toHaveCount(0);
            await expect(page.getByText('Organize your procedure into groups', { exact: false })).toHaveCount(0);
            for (const button of await page.getByRole('button').all()) {
                if (await button.isVisible()) assert.ok((await button.boundingBox()).height >= 44, 'Editing actions need usable touch targets');
            }
            for (const field of await page.locator('[data-specific-method-group] textarea').all()) {
                assert.ok(await field.evaluate(element => parseFloat(getComputedStyle(element).fontSize) >= 16), 'Writing fields need normal body-sized type');
            }
            assert.ok((await page.getByRole('textbox', { name: 'Method 1 for group A', exact: true }).boundingBox()).height >= 96, 'Methods need enough initial writing space');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);

            await addGroup.click();
            await expect(groups).toHaveCount(3);
            const heading = page.getByRole('textbox', { name: 'Method heading C', exact: true });
            await expect(heading).toBeFocused();
            await expect(heading).toHaveValue('');
            await heading.fill('Interview review');
            await page.getByRole('textbox', { name: 'Method 1 for group C', exact: true }).fill('Review the interview transcripts for completeness.');
            const addMethod = groups.nth(2).getByRole('button', { name: 'Add method to group C', exact: true });
            await expect(addMethod).toHaveAttribute('title', 'Add method');
            await addMethod.click();
            const method = page.getByRole('textbox', { name: 'Method 2 for group C', exact: true });
            await expect(method).toBeFocused();
            await expect(method).toHaveValue('');
            await method.fill('Resolve unclear entries with the research team.');
            await page.getByRole('button', { name: 'Remove method 1 from group C', exact: true }).click();
            await expect(page.getByRole('textbox', { name: 'Method 1 for group C', exact: true })).toHaveValue('Resolve unclear entries with the research team.');
            await expect(page.getByRole('textbox', { name: 'Method 1 for group C', exact: true })).toBeFocused();
            await page.getByRole('button', { name: 'Remove method group B', exact: true }).click();
            await expect(groups).toHaveCount(2);
            await expect(page.getByRole('textbox', { name: 'Method heading A', exact: true })).toHaveValue('Participant recruitment');
            await expect(page.getByRole('textbox', { name: 'Method heading B', exact: true })).toHaveValue('Interview review');
            await expect(page.getByRole('textbox', { name: 'Method heading B', exact: true })).toBeFocused();
            const saved = await page.evaluate(() => Object.fromEntries(new FormData(document.querySelector('form'))));
            assert.equal(saved['specific_method_objectives[1][methods][0][description]'], 'Resolve unclear entries with the research team.');
            assert.match(saved['methodology[specific_methods]'], /A\. Participant recruitment/);
            assert.match(saved['methodology[specific_methods]'], /B\. Interview review/);
            assert.doesNotMatch(saved['methodology[specific_methods]'], /Data collection|Review the interview transcripts/);
            assert.equal(await page.evaluate(() => window.autosaves), 4);
            await page.getByRole('button', { name: 'Remove method group B', exact: true }).click();
            await expect(groups).toHaveCount(1);
            const single = await groups.boundingBox();
            assert.ok(Math.abs(single.width - gridWidth) <= 2, 'A single heading must fill the available width instead of leaving an empty column');
            if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                await page.getByRole('heading', { name: /Specific methods/ }).click();
                await page.locator('[data-specific-methods-editor]').screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `methods-single-${width}-${dark ? 'dark' : 'light'}.png`) });
            }
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally { await browser.close(); }
});
