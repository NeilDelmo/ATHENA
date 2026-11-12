import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const applicationRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const lifecycleStorage = resolve(applicationRoot, 'storage/framework/testing/playwright');
export const lifecycleManifest = resolve(applicationRoot, 'playwright/.auth/local/lifecycle.json');

export function lifecycleEnvironment(inherited = process.env) {
    const target = new URL(inherited.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8021/');
    if (target.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(target.hostname)
        || target.pathname !== '/' || target.username || target.password || target.search || target.hash) {
        throw new Error('The lifecycle runner requires a dedicated local server at http://127.0.0.1:8021/.');
    }

    const office = 'C:/Program Files/LibreOffice/program/soffice.com';

    return {
        ...inherited,
        APP_ENV: 'testing',
        APP_URL: target.origin,
        PLAYWRIGHT_BASE_URL: `${target.origin}/`,
        LARAVEL_STORAGE_PATH: lifecycleStorage,
        APP_CONFIG_CACHE: resolve(lifecycleStorage, 'bootstrap/config.php'),
        APP_ROUTES_CACHE: resolve(lifecycleStorage, 'bootstrap/routes.php'),
        APP_EVENTS_CACHE: resolve(lifecycleStorage, 'bootstrap/events.php'),
        DB_CONNECTION: 'mysql',
        DB_HOST: '127.0.0.1',
        DB_PORT: inherited.DB_PORT ?? '3306',
        DB_DATABASE: 'athena_playwright',
        DB_USERNAME: inherited.DB_USERNAME ?? 'root',
        DB_PASSWORD: inherited.DB_PASSWORD ?? '',
        DB_URL: '',
        DB_QUEUE_CONNECTION: 'mysql',
        DB_QUEUE_TABLE: 'jobs',
        CACHE_STORE: 'database',
        DB_CACHE_CONNECTION: 'mysql',
        DB_CACHE_LOCK_CONNECTION: 'mysql',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'file',
        SESSION_COOKIE: 'athena_playwright_session',
        SESSION_DOMAIN: '',
        SESSION_SECURE_COOKIE: 'false',
        SESSION_EXPIRE_ON_CLOSE: 'true',
        MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null',
        BCRYPT_ROUNDS: '4',
        RESEARCH_ASSISTANT_MANUAL_ENABLED: 'false',
        PDF_CONVERSION_USE_PHP_CLI: 'true',
        // shortcut: Windows LibreOffice profiles require a short path, use an override for longer checkouts.
        PDF_CONVERSION_TEMP_PATH: inherited.PLAYWRIGHT_PDF_TEMP_PATH ?? resolve(applicationRoot, 'storage/app/private/pw-pdf'),
        LIBREOFFICE_BINARY: inherited.LIBREOFFICE_BINARY ?? (existsSync(office) ? office : 'soffice'),
        PDF_SECTION_NODE_BINARY: process.execPath,
    };
}
