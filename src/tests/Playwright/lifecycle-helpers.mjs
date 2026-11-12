import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect } from '@playwright/test';
import { applicationRoot, lifecycleEnvironment, lifecycleManifest } from '../../scripts/playwright/lifecycle-environment.mjs';

export function fixtures() {
    return JSON.parse(readFileSync(lifecycleManifest, 'utf8'));
}

export function fixtureAction(action, ...args) {
    const result = execFileSync('php', ['tests/Playwright/prepare-lifecycle.php', action, ...args.map(String)], {
        cwd: applicationRoot, env: lifecycleEnvironment(), encoding: 'utf8', windowsHide: true,
    });
    return result.trim() ? JSON.parse(result) : undefined;
}

export function fixtureFile(name) {
    return resolve(applicationRoot, 'test-results/lifecycle-fixtures', name);
}

export async function asActor(browser, actor, testInfo, action, workspace) {
    const context = await browser.newContext({
        baseURL: testInfo.project.use.baseURL,
        colorScheme: 'light', viewport: { width: 1440, height: 1000 },
    });
    await context.route('https://fonts.bunny.net/**', route => route.abort());
    const page = await context.newPage();
    try {
        const { key, users } = fixtures();
        const desired = workspace ?? users[actor].workspace;
        const session = await context.request.get(`__playwright/session/${actor}`, {
            headers: { 'X-Playwright-Key': key }, params: { workspace: desired },
        });
        expect(session.status(), `Synthetic ${actor} session`).toBe(204);
        await action(page, context);
        await testInfo.attach(`${actor}-result`, { body: await page.screenshot(), contentType: 'image/png' });
    } catch (error) {
        if (!page.isClosed()) {
            await testInfo.attach(`${actor}-failure`, { body: await page.screenshot(), contentType: 'image/png' });
        }
        throw error;
    } finally {
        await context.close();
    }
}

export async function confirmAction(page, name) {
    const dialog = page.locator('.swal2-popup');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name, exact: true }).click();
    await expect(dialog).not.toBeVisible();
}

export async function submitDraft(page, draft) {
    await page.goto(`faculty/proposal-drafts/${draft.id}/review`);
    const turnIn = page.locator('[data-proposal-package-submit]').getByRole('button', { name: 'Turn in proposal', exact: true });
    await expect(turnIn).toBeVisible({ timeout: 150_000 });
    await expect(turnIn).toBeEnabled();
    await turnIn.click();
    await confirmAction(page, 'Turn in proposal');
    await expect(page).toHaveURL(/\/faculty\/dashboard$/, { timeout: 120_000 });
    await page.goto('faculty/submissions');
    const submission = page.getByRole('article').filter({ hasText: draft.title });
    await submission.getByRole('link', { name: 'View proposal', exact: true }).click();
    await expect(page).toHaveURL(/\/topics\/\d+(?:[?#].*)?$/, { timeout: 120_000 });
    return Number(new URL(page.url()).pathname.match(/\/topics\/(\d+)/)[1]);
}

export async function headDecision(page, topic, status, confirmation, button) {
    await page.goto(`topics/${topic}`);
    await page.locator('#proposal-review-tab-button').click();
    const form = page.locator('#research-head-decision-form');
    if (!await form.isVisible()) await page.locator('[data-review-decision-disclosure] > button').click();
    await form.locator(`input[name="status"][value="${status}"]`).check();
    if (confirmation) await form.locator(`input[name="${confirmation}"]`).check();
    await form.getByRole('button', { name: button, exact: true }).click();
}

export async function forwardReport(page, url) {
    await page.goto(url);
    const review = page.locator('[data-secretary-report-review]').last();
    await review.locator('[name="decision"][value="forward"]').check();
    await review.getByRole('button', { name: 'Forward to Research Head', exact: true }).click();
}

export async function acceptReport(page, url) {
    await page.goto(url);
    const review = page.locator('[data-head-report-review]').last();
    await review.locator('[name="review_status"][value="reviewed"]').check();
    await review.getByRole('button', { name: 'Accept report', exact: true }).click();
}

export async function fillWritingField(page, name, value) {
    const field = page.locator(`[name="${name}"]`);
    await expect(field).toHaveCount(1);
    if (await field.isVisible()) {
        await field.fill(value);
    } else {
        const editor = field.locator('xpath=following-sibling::div[contains(@class,"proposal-semantic-field")][1]')
            .getByRole('textbox');
        await expect(editor).toBeVisible();
        await editor.fill(value);
    }
}

export async function selectCalendarDate(page, id, date) {
    const [year, month, day] = date.split('-').map(Number);
    const control = page.locator(`#${id}`);
    await control.click();
    const calendar = page.getByRole('dialog', { name: 'Choose a date' });
    const yearField = calendar.getByRole('spinbutton', { name: 'Calendar year' });
    await yearField.fill(String(year));
    await yearField.blur();
    await calendar.getByRole('combobox', { name: 'Calendar month' }).selectOption(String(month - 1));
    const label = new Intl.DateTimeFormat('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' }).format(new Date(date));
    await calendar.getByRole('button', { name: label, exact: true }).click();
    await expect(control.locator('xpath=../preceding-sibling::input[@type="hidden"]')).toHaveValue(date);
}

export function archiveContents(path) {
    return JSON.parse(execFileSync('php', ['-r',
        '$zip = new ZipArchive; if ($zip->open($argv[1]) !== true) { exit(1); } echo json_encode(["names" => array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1)), "manifest" => json_decode($zip->getFromName("manifest.json"), true)]);',
        path], { cwd: applicationRoot, encoding: 'utf8', windowsHide: true }));
}
