import { defineConfig } from '@playwright/test';
import guestConfig from './playwright.capstone.config.js';
import { lifecycleEnvironment } from './scripts/playwright/lifecycle-environment.mjs';

const environment = lifecycleEnvironment();

export default defineConfig({
    ...guestConfig,
    testMatch: ['capstone-guest.spec.js', 'lifecycle.spec.js'],
    timeout: 180_000,
    expect: { timeout: 20_000 },
    outputDir: 'test-results/lifecycle',
    metadata: {
        scope: 'Local proposal-to-archive lifecycle with real PDF conversion and uploads.',
        authentication: 'Synthetic test users with preauthenticated sessions; Google itself is not automated.',
        clock: 'Controlled dates for proposal approval and reporting windows.',
        applicationURL: environment.PLAYWRIGHT_BASE_URL,
    },
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report/lifecycle', open: 'never' }],
        ['json', { outputFile: 'test-results/lifecycle-results.json' }],
        ['junit', { outputFile: 'test-results/lifecycle-results.xml' }],
    ],
    use: { ...guestConfig.use, baseURL: environment.PLAYWRIGHT_BASE_URL },
    webServer: {
        command: 'node scripts/playwright/serve-lifecycle.mjs',
        url: `${environment.APP_URL}/up`,
        env: environment,
        reuseExistingServer: false,
        stderr: 'ignore',
        timeout: 180_000,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 5000 },
    },
});
