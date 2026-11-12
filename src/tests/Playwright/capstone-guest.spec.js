import { expect, test } from '@playwright/test';

for (const [device, viewport] of [
    ['desktop', { width: 1440, height: 900 }],
    ['mobile', { width: 390, height: 844 }],
]) {
    test(`AUTH-UI-${device}: institutional sign-in is usable on ${device}`, async ({ page }) => {
        await page.setViewportSize(viewport);
        const javascriptErrors = [];
        page.on('pageerror', error => javascriptErrors.push(error.message));

        const response = await page.goto('login');

        expect(response.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Sign in to ATHENA' })).toBeVisible();
        const signIn = page.getByRole('link', { name: 'Continue with BatStateU Google' });
        await signIn.scrollIntoViewIfNeeded();
        await expect(signIn).toBeInViewport();
        await expect(signIn).toHaveAttribute('href', /\/auth\/google$/);
        await expect(page.getByText('@g.batstate-u.edu.ph', { exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(javascriptErrors).toEqual([]);
    });
}

test('UI-THEME: the sign-in theme choice survives a reload', async ({ page }) => {
    await page.goto('login');
    await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
    await page.getByRole('button', { name: 'Toggle light and dark theme' }).click();
    await expect(page.locator('html')).toHaveClass(/\bdark\b/);
    await page.reload();
    await expect(page.locator('html')).toHaveClass(/\bdark\b/);
    await page.getByRole('button', { name: 'Toggle light and dark theme' }).click();
    await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
});

for (const [id, area, path] of [
    ['ACCESS-01', 'Faculty dashboard', 'faculty/dashboard'],
    ['ACCESS-02', 'Research Coordinator dashboard', 'research-coordinator/dashboard'],
    ['ACCESS-03', 'Research Head dashboard', 'research-head/dashboard'],
    ['ACCESS-04', 'Research Secretary dashboard', 'research-secretary/dashboard'],
    ['ACCESS-05', 'VCRDES dashboard', 'vcrdes/dashboard'],
    ['ACCESS-06', 'Proposal drafts', 'faculty/proposal-drafts'],
    ['ACCESS-07', 'Research manual management', 'research-head/research-manuals'],
    ['ACCESS-08', 'Research support', 'research-support'],
]) {
    test(`${id}: signed-out visitors cannot open ${area}`, async ({ page, baseURL }) => {
        const response = await page.goto(path);

        expect(response.status()).toBe(200);
        await expect(page).toHaveURL(new URL('login', baseURL).href);
        await expect(page.getByRole('heading', { name: 'Sign in to ATHENA' })).toBeVisible();
        expect(response.request().redirectedFrom()).not.toBeNull();
    });
}
