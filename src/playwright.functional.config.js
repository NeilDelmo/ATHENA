import { defineConfig } from '@playwright/test';
import lifecycle from './playwright.lifecycle.config.js';

export default defineConfig({
    ...lifecycle,
    testMatch: ['capstone-guest.spec.js', 'functional-cases.spec.js', 'lifecycle.spec.js', 'zz-postman.spec.js'],
    metadata: { ...lifecycle.metadata, scope: 'Workbook functional correctness: local browser workflows and Postman endpoint assertions.' },
    outputDir: 'test-results/functional-browser',
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report/functional', open: 'never' }],
        ['json', { outputFile: 'test-results/functional-playwright.json' }],
        ['junit', { outputFile: 'test-results/functional-playwright.xml' }],
    ],
    webServer: { ...lifecycle.webServer, env: { ...lifecycle.webServer.env, PLAYWRIGHT_FUNCTIONAL: '1' } },
});
