import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Playwright',
    fullyParallel: false,
    workers: 1,
    timeout: 180_000,
    expect: { timeout: 10_000 },
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost/athena-app',
        colorScheme: 'light',
        headless: false,
        storageState: process.env.PLAYWRIGHT_STORAGE_STATE ?? 'playwright/.auth/faculty.json',
        trace: 'retain-on-failure',
        viewport: { width: 1440, height: 900 },
    },
});