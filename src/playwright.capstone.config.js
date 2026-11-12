import { defineConfig } from '@playwright/test';
import demoConfig from './playwright.config.js';

const baseURL = `${demoConfig.use.baseURL.replace(/\/+$/, '')}/`;
const channel = process.env.PLAYWRIGHT_CHANNEL ?? (process.platform === 'win32' ? 'msedge' : undefined);

export default defineConfig({
    ...demoConfig,
    testMatch: 'capstone-guest.spec.js',
    timeout: 30_000,
    retries: 0,
    forbidOnly: true,
    metadata: {
        scope: 'Sign-in UI and signed-out access only. Authenticated workflows and load testing are excluded.',
        applicationURL: baseURL,
        browserChannel: channel ?? 'bundled Chromium',
    },
    outputDir: 'test-results/capstone',
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report/capstone', open: 'never' }],
        ['json', { outputFile: 'test-results/capstone-results.json' }],
    ],
    use: {
        ...demoConfig.use,
        baseURL,
        channel,
        headless: true,
        storageState: { cookies: [], origins: [] },
        screenshot: 'on',
    },
});
