import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        // Open pages can still request lazy PDF viewer chunks from the previous build.
        emptyOutDir: false,
    },
    server: {
        watch: {
            usePolling: process.platform === 'win32',
            interval: 300,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/welcome.css', 'resources/css/work-plan-print.css', 'resources/css/monitoring-tool-print.css', 'resources/css/progress-report-print.css', 'resources/css/line-item-budget-print.css', 'resources/css/expense-breakdown-print.css', 'resources/css/curriculum-vitae-print.css', 'resources/css/detailed-proposal-print.css', 'resources/css/notice-to-proceed-print.css', 'resources/css/gad-checklist-print.css', 'resources/css/initial-screening-form-print.css', 'resources/css/comment-response-form-print.css', 'resources/js/app.js', 'resources/js/welcome.js'],
            refresh: true,
        }),
    ],
});
