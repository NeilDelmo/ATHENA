import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import { deployedURL, savedSession, sessionPath } from './deployed-environment.mjs';

const target = deployedURL();
const state = savedSession(target, process.argv[2] ?? sessionPath());
const browser = await chromium.launch({ headless: false, channel: process.env.PLAYWRIGHT_CHANNEL ?? (process.platform === 'win32' ? 'msedge' : undefined) });
try {
    const context = await browser.newContext({ storageState: state ?? undefined });
    const page = await context.newPage();
    await page.goto(new URL('choose-workspace', target).href);
    if (!await page.locator('body[data-auth-user-id]').count()) {
        console.log('Complete Google sign-in with your existing ATHENA account. Finish any role selection. Its allowed workspaces will be reused automatically.');
        const deadline = Date.now() + 10 * 60_000;
        while (Date.now() < deadline && !await page.locator('body[data-auth-user-id]').count()) {
            await page.waitForTimeout(500);
        }
    }
    const response = await page.goto(new URL('choose-workspace', target).href);
    if (!response?.ok() || !await page.locator('body[data-auth-user-id]').count()) {
        throw new Error('A signed-in ATHENA account is required. Run the session command again to refresh the sign-in.');
    }
    const available = await page.locator('input[name="workspace"]').evaluateAll(fields => fields.map(field => field.value));
    if (!available.length) throw new Error('The account has no available ATHENA workspaces.');
    const captured = await context.storageState();
    const host = new URL(target).hostname;
    captured.cookies = captured.cookies.filter(cookie => {
        const domain = cookie.domain.replace(/^\./, '');
        return host === domain || host.endsWith(`.${domain}`);
    });
    captured.origins = [];
    await mkdir(dirname(sessionPath()), { recursive: true });
    await writeFile(sessionPath(), `${JSON.stringify(captured, null, 2)}\n`, { mode: 0o600 });
    console.log(`Saved existing account sign-in. Available workspaces: ${available.join(', ')}. Google cookies and passwords are not stored.`);
} finally {
    await browser.close();
}
