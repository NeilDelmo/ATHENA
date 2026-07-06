import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['src/resources/css/app.css', 'src/resources/js/app.js'],
            refresh: [
                'src/app/**/*.php',
                'src/resources/views/**/*.blade.php',
                'src/routes/**/*.php',
            ],
        }),
    ],
});
