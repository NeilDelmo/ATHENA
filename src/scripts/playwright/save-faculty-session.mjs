import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost/athena-app';
const sessionPath = path.resolve(process.env.PLAYWRIGHT_STORAGE_STATE ?? 'playwright/.auth/faculty.json');

await mkdir(path.dirname(sessionPath), { recursive: true });

const browser = await chromium.launch({ headless: false });
const context = await browser.newContext({ colorScheme: 'light', viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

console.log('Complete Google sign-in in the opened browser. ATHENA will select the Faculty role and workspace automatically.');
await page.goto(`${baseUrl}/login`);

const deadline = Date.now() + 10 * 60 * 1000;

while (Date.now() < deadline) {
    const pathname = new URL(page.url()).pathname;

    if (pathname.endsWith('/select-role')) {
        const facultyRole = page.getByRole('button', { name: /Continue as Faculty/i });

        if (await facultyRole.isVisible().catch(() => false)) {
            await facultyRole.click();
        }
    }

    if (pathname.endsWith('/choose-workspace')) {
        const facultyWorkspace = page.locator('form').filter({ has: page.locator('input[name="workspace"][value="faculty"]') }).getByRole('button');

        if (await facultyWorkspace.isVisible().catch(() => false)) {
            await facultyWorkspace.click();
        }
    }

    if (new URL(page.url()).pathname.includes('/faculty/')) {
        await context.storageState({ path: sessionPath });
        console.log(`Faculty session saved to ${sessionPath}`);
        await browser.close();
        process.exit(0);
    }

    await page.waitForTimeout(500);
}

await browser.close();
throw new Error('Timed out waiting for Faculty workspace sign-in. Run the command again and complete Google sign-in within 10 minutes.');