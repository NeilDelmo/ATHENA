import { defineConfig } from '@playwright/test';
import { deployedURL } from './scripts/playwright/deployed-environment.mjs';

export default defineConfig({
    testDir: './tests/Playwright',
    testMatch: 'deployed-benchmark.spec.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: true,
    timeout: 120_000,
    expect: { timeout: 15_000 },
    outputDir: 'test-results/deployed',
    metadata: {
        applicationURL: deployedURL(),
        scope: 'Authenticated page navigation; one browser, three measured visits after one warm-up. Sign-in excluded. No submissions or approvals.',
    },
    use: {
        baseURL: deployedURL(),
        channel: process.env.PLAYWRIGHT_CHANNEL ?? (process.platform === 'win32' ? 'msedge' : undefined),
        headless: true,
        viewport: { width: 1440, height: 1000 },
        colorScheme: 'light',
        screenshot: 'only-on-failure',
        trace: 'off',
        navigationTimeout: 30_000,
    },
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report/deployed', open: 'never' }],
        ['json', { outputFile: 'test-results/deployed-results.json' }],
        ['junit', { outputFile: 'test-results/deployed-results.xml' }],
        ['./scripts/playwright/benchmark-reporter.mjs'],
    ],
});
