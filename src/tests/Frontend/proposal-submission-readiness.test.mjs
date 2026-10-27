import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import { safeProposalReadinessUrl } from '../../resources/js/proposal-submission-readiness.js';

const state = (blockers = [], extra = {}) => ({
    status: blockers.length ? 'incomplete' : 'ready', can_guide: true, blockers,
    ready_to_prepare: !blockers.length, ready_to_submit: !blockers.length, ...extra,
});
const blocker = (field, extra = {}) => ({
    key: field, paper: 'work-plan', field, owner: 'faculty', message: 'Enter the activity for objective 2.',
    url: `https://athena.test/editor?proposal_requirement=${encodeURIComponent(field)}`, ...extra,
});
const panel = '<section data-proposal-readiness data-proposal-readiness-url="/ready"><p data-proposal-readiness-label></p><ul data-proposal-readiness-issues></ul></section>';

test('readiness navigation only accepts same-origin HTTP URLs', () => {
    const base = 'https://athena.test/drafts/1';
    assert.equal(safeProposalReadinessUrl('/editor?proposal_requirement=title', base), 'https://athena.test/editor?proposal_requirement=title');
    for (const url of ['javascript:alert(1)', 'https://other.test/editor', '//other.test/editor', 'data:text/html,test', null]) {
        assert.equal(safeProposalReadinessUrl(url, base), null);
    }
});

test('readiness automatically reveals exact fields, saves before guidance, and never guides office or read-only users', { timeout: 60000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const source = readFileSync(resolve('resources/js/proposal-submission-readiness.js'), 'utf8');
    async function fixture(html, response, query = '') {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://athena.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/module.js') return route.fulfill({ contentType: 'text/javascript', body: source });
            if (url.pathname === '/ready') {
                assert.match(route.request().headers().accept, /application\/json/);
                await page.evaluate(() => window.order.push('fetch'));
                const saved = response.afterConfirmation && await page.evaluate(() => window.confirmations > 0);
                return route.fulfill({ status: response.fail ? 503 : 200, json: saved ? response.afterConfirmation : response.value });
            }
            return route.fulfill({ contentType: 'text/html', body: `<style>[x-cloak]{display:none!important}</style>${html}` });
        });
        await page.goto(`https://athena.test/editor${query}`);
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        await page.addScriptTag({ type: 'module', content: `import init from '/module.js'; window.order = []; window.navigations = []; window.modals = []; window.confirmations = 0; window.allowSave = true; window.addEventListener('open-modal', event => window.modals.push(event.detail)); window.initializeReadiness = () => init({beforeContinue: async options => { window.order.push(options?.recheckCompletion ? 'confirm' : 'save'); if (options?.recheckCompletion) window.confirmations++; return window.allowSave; }, navigate: url => window.navigations.push(url)}); window.initializeReadiness(); window.readinessLoaded = true;` });
        await page.waitForFunction(() => window.readinessLoaded);
        return { page, errors };
    }
    try {
        const work = await fixture(`<main x-data="{entries:[{id:1},{id:2}], expandedEntryId:1}" data-proposal-paper="work-plan" data-paper-dirty="false" data-proposal-readiness-url="/ready">${panel}<input id="unrelated" value="Keep this text"><template x-for="(entry,index) in entries"><details :open="expandedEntryId === entry.id"><summary>Objective</summary><div x-show="expandedEntryId === entry.id"><textarea :name="'entries['+index+'][activity]'" :id="'activity-'+index" aria-describedby="existing-help"></textarea></div></details></template><p id="existing-help">Existing help</p></main>`, { value: state([blocker('entries.1.activity')]) }, '?proposal_requirement=entries.1.activity');
        const activity = work.page.locator('#activity-1');
        await expect(activity).toBeFocused();
        await expect(activity).toHaveAttribute('aria-invalid', 'true');
        const description = await activity.getAttribute('aria-describedby');
        assert.match(description, /existing-help/);
        await expect(work.page.locator('[data-proposal-readiness-cue]')).toHaveText('Enter the activity for objective 2.');
        await expect(work.page.locator('[data-proposal-readiness-issues] button')).toHaveCount(0);
        await expect(work.page.locator('#unrelated')).toHaveValue('Keep this text');
        await work.page.locator('#unrelated').focus();
        const workResponse = { value: state() };
        await work.page.unroute('https://athena.test/**');
        await work.page.route('**/ready', route => route.fulfill({ json: workResponse.value }));
        await work.page.evaluate(() => { const root = document.querySelector('[data-proposal-paper]'); root.dataset.paperDirty = 'true'; });
        await work.page.evaluate(() => { document.querySelector('[data-proposal-paper]').dataset.paperDirty = 'false'; });
        await expect(work.page.locator('[data-proposal-readiness-cue]')).toHaveCount(0);
        await expect(activity).not.toHaveAttribute('aria-invalid');
        await expect(activity).toHaveAttribute('aria-describedby', 'existing-help');
        await expect(work.page.locator('#unrelated')).toBeFocused();
        assert.deepEqual(work.errors, []);
        await work.page.close();

        const detailed = await fixture(`<main x-data="{ focusProposalRequirement(field) { document.querySelector('[contenteditable]').focus(); } }" data-proposal-paper="detailed-proposal" data-proposal-readiness-url="/ready">${panel}<div contenteditable="true" role="textbox" tabindex="0" aria-label="Specific methods"></div></main>`, { value: state([blocker('methodology.specific_methods', { paper: 'detailed-proposal', message: 'Describe the specific methods.' })]) }, '?proposal_requirement=methodology.specific_methods');
        await expect(detailed.page.getByRole('textbox')).toBeFocused();
        await expect(detailed.page.getByRole('textbox')).toHaveAttribute('aria-invalid', 'true');
        assert.deepEqual(detailed.errors, []);
        await detailed.page.close();

        const dates = await fixture(`<main x-data="{}" data-proposal-paper="details" data-proposal-readiness-url="/ready">${panel}<input name="project_title" value="Existing title"><input type="hidden" name="planned_start"><input id="planned_start" role="combobox" aria-label="Planned start"></main>`, { value: state([blocker('planned_start', { paper: 'details' })]) }, '?proposal_requirement=planned_start');
        await expect(dates.page.getByRole('combobox')).toBeFocused();
        await expect(dates.page.getByRole('combobox')).toHaveAttribute('aria-invalid', 'true');
        await dates.page.close();

        const unknown = await fixture(`<main x-data="{}" data-proposal-paper="work-plan" data-proposal-readiness-url="/ready">${panel}<input autofocus aria-label="Unrelated field" value="Keep this text"></main>`, { value: state([blocker('missing_unknown_field')]) }, '?proposal_requirement=missing_unknown_field');
        await expect(unknown.page.locator('[data-proposal-readiness-issues]')).toHaveText('Enter the activity for objective 2.');
        await expect(unknown.page.getByRole('textbox')).not.toHaveAttribute('aria-invalid');
        await expect(unknown.page.locator('[data-proposal-readiness-cue]')).toHaveCount(0);
        await unknown.page.close();

        const budget = await fixture(`<main x-data="{overrideMooe:false}" data-proposal-paper="line-item-budget" data-proposal-readiness-url="/ready">${panel}<input autofocus aria-label="Unrelated field"><section aria-label="MOOE total"><label><input type="checkbox" x-model="overrideMooe">Edit this total manually</label><input name="mooe_total_override" value="2500" :disabled="!overrideMooe" x-show="overrideMooe"></section></main>`, { value: state([blocker('mooe_total_override', { paper: 'line-item-budget' })]) }, '?proposal_requirement=mooe_total_override');
        await expect(budget.page.getByRole('region', { name: 'MOOE total' })).toBeFocused();
        await expect(budget.page.getByRole('checkbox')).not.toBeChecked();
        await expect(budget.page.locator('[name="mooe_total_override"]')).toBeDisabled();
        await expect(budget.page.locator('[name="mooe_total_override"]')).toHaveValue('2500');
        await budget.page.close();

        const confirmation = await fixture(`<main x-data="{}" data-proposal-paper="work-plan" data-proposal-readiness-url="/ready">${panel}<input name="entries[0][activity]" value="Verified activity"></main>`, {
            value: state([blocker(null, { reason: 'save_confirmation', message: 'Confirm the saved Work Plan.' })]),
            afterConfirmation: state([], { status: 'prepare', ready_to_submit: false }),
        }, '?proposal_requirement=__paper__');
        await expect(confirmation.page.locator('[data-proposal-readiness-label]')).toHaveText('Content complete · PDFs need preparation');
        assert.deepEqual(await confirmation.page.evaluate(() => window.order), ['fetch', 'confirm', 'fetch']);
        assert.equal(await confirmation.page.evaluate(() => window.confirmations), 1);
        assert.deepEqual(await confirmation.page.evaluate(() => window.navigations), []);
        await expect(confirmation.page.locator('input')).toHaveValue('Verified activity');
        await confirmation.page.close();

        for (const [paper, expression, field, target] of [
            ['expense-breakdown', '{items:[{id:4},{id:8}], expandedItemId:4}', 'items.1.description', '<div x-show="expandedItemId === 8"><input name="items[1][description]"></div>'],
            ['curriculum-vitae', '{people:[{id:4},{id:8}], activeCvPersonId:4, focusPerson(index) { this.activeCvPersonId = this.people[index].id; }}', 'people.1.last_name', '<div x-show="activeCvPersonId === 8"><details><summary>Personal details</summary><input name="people[1][last_name]"></details></div>'],
        ]) {
            const row = await fixture(`<main x-data="${expression}" data-proposal-paper="${paper}" data-proposal-readiness-url="/ready">${panel}${target}</main>`, { value: state([blocker(field, { paper })]) }, `?proposal_requirement=${field}`);
            await expect(row.page.locator('input')).toBeFocused();
            assert.deepEqual(row.errors, []);
            await row.page.close();
        }

        const response = { value: state([blocker('entries.1.activity')]) };
        const hub = await fixture(`${panel}<button data-proposal-readiness-continue data-url="/ready">Review submission</button>`, response);
        const proceed = hub.page.getByRole('button', { name: 'Review submission' });
        await proceed.click();
        await expect(hub.page.locator('[data-proposal-readiness-label]')).toHaveText('Complete these fields');
        await expect.poll(() => hub.page.evaluate(() => window.navigations.length)).toBe(1);
        assert.deepEqual(await hub.page.evaluate(() => window.order), ['save', 'fetch']);
        assert.match((await hub.page.evaluate(() => window.navigations))[0], /proposal_requirement=entries/);
        await hub.page.evaluate(() => { window.order = []; window.allowSave = false; });
        await proceed.click();
        assert.deepEqual(await hub.page.evaluate(() => window.order), ['save']);
        for (const value of [
            state([blocker(null, { owner: 'research_office', url: null, message: 'Research Office: assign the research call.' })], { status: 'office' }),
            state([blocker('title')], { can_guide: false }),
            state([blocker('title', { url: 'https://other.test/editor' })]),
            state(),
        ]) {
            response.value = value;
            const modalCount = await hub.page.evaluate(() => window.modals.length);
            await hub.page.evaluate(() => { window.allowSave = true; });
            await proceed.click();
            await expect.poll(() => hub.page.evaluate(() => window.modals.length)).toBe(modalCount + 1);
            assert.equal(await hub.page.evaluate(() => window.navigations.length), 1);
            if (value.status === 'office') await expect(hub.page.locator('[data-proposal-readiness-issues]')).toContainText('Research Office action required');
        }
        response.fail = true;
        const modalCount = await hub.page.evaluate(() => window.modals.length);
        await proceed.click();
        await expect.poll(() => hub.page.evaluate(() => window.modals.length)).toBe(modalCount + 1);
        await expect(hub.page.locator('[data-proposal-readiness-label]')).toContainText('Could not check');
        await expect(proceed).toBeEnabled();
        response.fail = false;
        response.value = state([blocker('title', { message: '<img src=x onerror=alert(1)>' })], { can_guide: false });
        await hub.page.evaluate(() => { window.initializeReadiness(); window.initializeReadiness(); window.order = []; });
        await proceed.click();
        await expect.poll(() => hub.page.evaluate(() => window.order.length)).toBe(2);
        await expect(hub.page.locator('[data-proposal-readiness-issues]')).toHaveText('<img src=x onerror=alert(1)>');
        await expect(hub.page.locator('[data-proposal-readiness-issues] img, [data-proposal-readiness-issues] button')).toHaveCount(0);
        assert.deepEqual(hub.errors, []);
        await hub.page.close();

        const lazyResponse = { value: state([], { status: 'prepare', ready_to_submit: false }) };
        const lazy = await fixture('<button data-proposal-readiness-continue data-url="/ready">Review submission</button>', lazyResponse);
        await lazy.page.evaluate(html => {
            const review = document.createElement('div');
            review.dataset.proposalReviewPackage = '';
            review.setAttribute('wire:id', 'review-package');
            review.innerHTML = `${html}<h2 data-review-title>Original title</h2><input name="document_version" value="1">`;
            review.querySelector('[data-proposal-readiness]').dataset.proposalReadinessState = JSON.stringify({ status: 'ready', blockers: [] });
            document.body.append(review);
            window.latestReview = { status: 'prepare', title: 'Changed title', documentVersion: 2 };
            window.Livewire = { find(id) { if (id !== 'review-package') throw new Error('Wrong review component'); return { async $refresh() {
                window.order.push('refresh');
                await new Promise(resolve => requestAnimationFrame(resolve));
                review.querySelector('[data-proposal-readiness]').dataset.proposalReadinessState = JSON.stringify({ status: window.latestReview.status, blockers: [] });
                review.querySelector('[data-review-title]').textContent = window.latestReview.title;
                review.querySelector('[name="document_version"]').value = String(window.latestReview.documentVersion);
            } }; } };
            window.addEventListener('open-modal', () => window.order.push('modal'));
        }, panel);
        const lazyProceed = lazy.page.getByRole('button', { name: 'Review submission' });
        await lazyProceed.click();
        await expect.poll(() => lazy.page.evaluate(() => window.modals.length)).toBe(1);
        assert.deepEqual(await lazy.page.evaluate(() => window.order), ['save', 'fetch', 'refresh', 'modal']);
        await expect(lazy.page.locator('[data-proposal-readiness-label]')).toHaveText('Content complete · PDFs need preparation');
        lazyResponse.value = state();
        await lazy.page.evaluate(() => {
            window.order = [];
            document.querySelector('[data-proposal-readiness]').dataset.proposalReadinessState = JSON.stringify({ status: 'ready', blockers: [] });
            window.latestReview = { status: 'ready', title: 'Latest ready title', documentVersion: 3 };
        });
        await lazyProceed.click();
        await expect.poll(() => lazy.page.evaluate(() => window.modals.length)).toBe(2);
        assert.deepEqual(await lazy.page.evaluate(() => window.order), ['save', 'fetch', 'refresh', 'modal']);
        await expect(lazy.page.locator('[data-review-title]')).toHaveText('Latest ready title');
        await expect(lazy.page.locator('[name="document_version"]')).toHaveValue('3');
        for (const [index, id] of [null, ''].entries()) {
            await lazy.page.evaluate(id => {
                const review = document.querySelector('[data-proposal-review-package]');
                if (id === null) review.removeAttribute('wire:id');
                else review.setAttribute('wire:id', id);
                window.order = [];
            }, id);
            await lazyProceed.click();
            await expect.poll(() => lazy.page.evaluate(() => window.modals.length)).toBe(3 + index);
            assert.deepEqual(await lazy.page.evaluate(() => window.order), ['save', 'fetch', 'modal']);
            await expect(lazy.page.locator('[data-proposal-readiness-label]')).toHaveText('Ready to turn in');
        }
        await lazy.page.evaluate(() => { document.querySelector('[data-proposal-review-package]').remove(); window.order = []; });
        await lazyProceed.click();
        await expect.poll(() => lazy.page.evaluate(() => window.modals.length)).toBe(5);
        assert.deepEqual(await lazy.page.evaluate(() => window.order), ['save', 'fetch', 'modal']);
        assert.deepEqual(lazy.errors, []);
        await lazy.page.close();
    } finally {
        await browser.close();
    }
});
