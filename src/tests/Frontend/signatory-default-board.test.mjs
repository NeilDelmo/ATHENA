import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { signatoryDefaultBoard } from '../../resources/js/signatory-default-board.js';

const config = {
    options: {
        'template:head': { name: 'Research Head', position: 'Head' },
        'directory:7': { name: 'Alternate Officer', position: 'Officer' },
    },
    templates: {
        verified_by: { name: 'Research Head', position: 'Head' },
        certified_by: { name: 'Budget Officer', position: 'Certifier' },
    },
    assignments: { verified_by: '', certified_by: '' },
    savedAssignments: { verified_by: '', certified_by: '' },
};

test('names can be copied to any field, previewed, and undone before saving', () => {
    const board = signatoryDefaultBoard(config);
    assert.equal(board.dirty, false);
    assert.equal(board.assign('verified_by', 'directory:7'), true);
    assert.equal(board.assign('certified_by', 'directory:7'), true);
    assert.equal(board.displayName('certified_by'), 'Alternate Officer');
    assert.equal(board.assignments.verified_by, 'directory:7');
    assert.equal(board.dirty, true);
    assert.deepEqual(config.assignments, { verified_by: '', certified_by: '' });
    board.reset();
    assert.equal(board.dirty, false);
    assert.equal(board.displayName('certified_by'), 'Budget Officer');
});

test('dragging copies only a known name and template matches restore the original field default', () => {
    const board = signatoryDefaultBoard(config);
    const payload = new Map();
    const transfer = { setData: (type, value) => payload.set(type, value), getData: type => payload.get(type) };
    board.startDrag('directory:7', { dataTransfer: transfer });
    board.drop('certified_by', { dataTransfer: transfer });
    assert.equal(board.displayName('certified_by'), 'Alternate Officer');
    assert.equal(board.draggingKey, '');
    assert.equal(board.assign('unknown_role', 'directory:7'), false);
    assert.equal(board.assign('verified_by', 'directory:999'), false);
    board.assign('verified_by', 'directory:7');
    board.assign('verified_by', 'template:head');
    assert.equal(board.assignments.verified_by, '');
    assert.equal(board.displayName('verified_by'), 'Research Head');
});

test('the name library filters by name and designation', () => {
    const board = signatoryDefaultBoard(config);
    board.query = ' alternate ';
    assert.deepEqual(board.people.map(person => person.key), ['directory:7']);
    board.query = 'Head';
    assert.deepEqual(board.people.map(person => person.key), ['template:head']);
    board.query = 'missing';
    assert.equal(board.people.length, 0);
});

test('the rendered board supports drag, keyboard placement, dropdowns and undo on mobile and desktop in both themes', async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/ManagedSignatoryDefaultsTest.php', '--filter=signatory board'], {
        encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_SIGNATORY_BOARD: '1' },
    });
    const html = readFileSync(resolve('storage/framework/testing/signatory-default-board.html'), 'utf8');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 1280]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.route('**/*', route => route.abort());
                const boardHtml = await page.evaluate(source => new DOMParser().parseFromString(source, 'text/html')
                    .querySelector('[data-signatory-default-board]').outerHTML, html);
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n[x-cloak]{display:none!important}</style><body><div class="pl-[76px] sm:pl-[280px]"><main class="px-4 py-6 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">${boardHtml}</div></main></div></body></html>`);
                await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => Alpine.data('signatoryDefaultBoard', ${signatoryDefaultBoard.toString()})); document.addEventListener('submit', event => event.preventDefault());` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });

                const board = page.locator('[data-signatory-default-board]');
                const alternate = page.getByRole('button', { name: /Alternate Research Head/ });
                const budget = page.locator('[data-signatory-field="certified_by"]');
                const save = board.getByRole('button', { name: 'Save defaults', exact: true }).first();
                await expect(alternate).toBeVisible();
                await expect(save).toBeDisabled();
                assert.equal(await board.locator('select').count(), 11);
                assert.equal(await board.getByText('Unavailable Signatory').count(), 0);

                await alternate.press('Enter');
                await budget.getByRole('button').press('Enter');
                await expect(budget.getByRole('button')).toHaveText('Alternate Research Head');
                await expect(save).toBeEnabled();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                await board.getByRole('button', { name: 'Undo changes' }).click();
                await expect(save).toBeDisabled();
                await expect(budget.getByRole('button')).toHaveText('Dr. ENRICO M. DALANGIN');

                await budget.getByRole('combobox').selectOption({ label: 'Alternate Research Head' });
                await expect(budget.getByRole('button')).toHaveText('Alternate Research Head');
                await expect(save).toBeEnabled();
                await board.getByRole('button', { name: 'Undo changes' }).click();

                if (width === 1280) {
                    await alternate.dragTo(budget);
                    await expect(budget.getByRole('button')).toHaveText('Alternate Research Head');
                    await expect(save).toBeEnabled();
                    await board.getByRole('button', { name: 'Undo changes' }).click();
                }
                await page.getByRole('searchbox', { name: 'Find an available name' }).fill('unfindable');
                await expect(page.getByText('No matching names.')).toBeVisible();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                await expect(board).toHaveCSS('background-color', dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
