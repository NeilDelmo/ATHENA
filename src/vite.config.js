import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/work-plan-print.css', 'resources/css/line-item-budget-print.css', 'resources/css/curriculum-vitae-print.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
