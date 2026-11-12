import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { asActor, confirmAction, fixtures } from './lifecycle-helpers.mjs';

test('MAN-01 MAN-03 [active edition]: permitted roles retrieve the known manual through its reader', async ({ browser }, testInfo) => {
    for (const actor of ['faculty', 'head', 'secretary', 'coordinator']) {
        await asActor(browser, actor, testInfo, async (page, context) => {
            const f = fixtures().functional;
            await page.goto(`research-manuals/${f.activeManualVersion}`);
            await expect(page.getByText('Functional research manual active', { exact: false }).first()).toBeVisible();
            const response = await context.request.get(`research-manuals/${f.activeManualVersion}/file?download=1`);
            expect(response.status()).toBe(200);
            expect(createHash('sha256').update(await response.body()).digest('hex')).toBe(f.documentChecksum);
        });
    }
});

for (const actor of ['faculty', 'head', 'secretary', 'coordinator', 'vcrdes', 'outsider']) {
    test(`AUTH-05 [${actor}]: logout removes access after navigation, back and refresh`, async ({ browser }, testInfo) => {
        await asActor(browser, actor, testInfo, async page => {
            await page.goto('dashboard');
            const protectedURL = page.url();
            await page.getByRole('button', { name: 'Open account menu', exact: true }).click();
            await page.getByRole('button', { name: 'Log Out', exact: true }).click();
            await confirmAction(page, 'Log out');
            await page.goto(protectedURL);
            await expect(page).toHaveURL(/\/login$/);
            await page.goBack();
            await page.reload();
            await page.goto(protectedURL);
            await expect(page).toHaveURL(/\/login$/);
            await expect(page.getByRole('heading', { name: 'Sign in to ATHENA' })).toBeVisible();
        });
    });
}

test('DOC-01 [reference retrieval]: downloaded PDF bytes match the known fixture', async ({ browser }, testInfo) => {
    await asActor(browser, 'functional_owner', testInfo, async (page, context) => {
        const f = fixtures().functional;
        const response = await context.request.get(`topics/${f.projectId}/documents/${f.documentId}/download`);
        expect(response.status()).toBe(200);
        const contents = await response.body();
        expect(createHash('sha256').update(contents).digest('hex')).toBe(f.documentChecksum);
        expect(contents.length).toBe(f.documentSize);
        expect(contents.toString()).toContain('Expected content: coastal baseline 2026');
        await page.goto(`topics/${f.projectId}`);
    });
});

test('DOC-05 [missing file]: missing document is a controlled 404', async ({ browser }, testInfo) => {
    await asActor(browser, 'functional_owner', testInfo, async (page, context) => {
        const f = fixtures().functional;
        const response = await context.request.get(`topics/${f.projectId}/documents/${f.missingDocumentId}/download`);
        expect(response.status()).toBe(404);
        expect((await response.body()).toString()).not.toContain('Expected content: coastal baseline 2026');
        await page.goto(`topics/${f.projectId}`);
    });
});

test('BUD-01 BUD-02 [project owner]: PHP 100000 allocation reconciles before and after a saved edit', async ({ browser }, testInfo) => {
    await asActor(browser, 'functional_owner', testInfo, async page => {
        const f = fixtures().functional;
        const url = `projects/${f.projectId}/reports/${f.reportId}/budget`;
        await page.goto(url);
        await expect(page.locator('[x-text="currency(spentTotal())"]')).toHaveText(/20,000\.00/);
        await expect(page.locator('[x-text="currency(remainingBudget())"]')).toHaveText(/80,000\.00/);
        await page.locator('[name="budget_utilization[1][actual_amount]"]').fill('8500');
        await expect(page.locator('[x-text="currency(spentTotal())"]')).toHaveText(/21,000\.00/);
        await expect(page.locator('[x-text="currency(remainingBudget())"]')).toHaveText(/79,000\.00/);
        await page.getByRole('button', { name: 'Confirm budget utilization', exact: true }).click();
        await page.goto(url);
        await expect(page.locator('[name="budget_utilization[1][actual_amount]"]')).toHaveValue('8500');
        await expect(page.locator('[x-text="currency(spentTotal())"]')).toHaveText(/21,000\.00/);
        await expect(page.locator('[x-text="currency(remainingBudget())"]')).toHaveText(/79,000\.00/);
    });
});

test('TURN-01 TURN-02 [faculty]: intended external link opens and ATHENA session survives', async ({ browser }, testInfo) => {
    await asActor(browser, 'faculty', testInfo, async (page, context) => {
        await page.goto('research-support#turnitin');
        const link = page.getByRole('link', { name: 'Open Turnitin', exact: false });
        await expect(link).toHaveAttribute('href', 'https://home.turnitin.com/');
        await expect(link).toHaveAttribute('target', '_blank');
        await context.route('https://home.turnitin.com/**', route => route.fulfill({ contentType: 'text/html', body: '<title>External navigation test</title>' }));
        const opened = page.waitForEvent('popup');
        await link.click();
        const popup = await opened;
        await expect(popup).toHaveURL('https://home.turnitin.com/');
        await popup.close();
        await page.goto('faculty/dashboard');
        await expect(page.locator('body')).toHaveAttribute('data-auth-user-id', String(fixtures().users.faculty.id));
        await expect(page.locator('body')).toHaveAttribute('data-active-workspace', 'faculty');
    });
});

test('MAN-04 [unknown edition]: unavailable manual does not return another edition', async ({ browser }, testInfo) => {
    await asActor(browser, 'faculty', testInfo, async (page, context) => {
        const response = await context.request.get(`research-manuals/${'0'.repeat(64)}/file`);
        expect(response.status()).toBe(404);
        expect((await response.body()).subarray(0, 5).toString()).not.toBe('%PDF-');
        await page.goto('faculty/dashboard');
    });
});
