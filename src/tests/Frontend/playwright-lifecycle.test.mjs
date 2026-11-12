import assert from 'node:assert/strict';
import test from 'node:test';
import { lifecycleEnvironment } from '../../scripts/playwright/lifecycle-environment.mjs';

test('lifecycle tests cannot target a deployed server through the local runner', () => {
    for (const url of ['https://athenaresearch.app', 'http://example.com', 'http://localhost/athena-app']) {
        assert.throws(() => lifecycleEnvironment({ PLAYWRIGHT_BASE_URL: url }), /local server/);
    }
});

test('lifecycle tests isolate database, storage, mail, queues and sessions from development', () => {
    const environment = lifecycleEnvironment({
        APP_ENV: 'production', DB_DATABASE: 'athena', DB_URL: 'mysql://production',
        SESSION_DRIVER: 'array', LARAVEL_STORAGE_PATH: 'private-development-data', MAIL_MAILER: 'smtp',
    });

    assert.equal(environment.APP_ENV, 'testing');
    assert.equal(environment.DB_DATABASE, 'athena_playwright');
    assert.equal(environment.DB_URL, '');
    assert.equal(environment.DB_HOST, '127.0.0.1');
    assert.equal(environment.DB_QUEUE_CONNECTION, 'mysql');
    assert.equal(environment.MAIL_MAILER, 'array');
    assert.equal(environment.SESSION_DRIVER, 'file');
    assert.equal(environment.SESSION_COOKIE, 'athena_playwright_session');
    assert.match(environment.LARAVEL_STORAGE_PATH, /storage[\\/]framework[\\/]testing[\\/]playwright$/);
});

test('the local browser target and application URL agree', () => {
    const environment = lifecycleEnvironment({ PLAYWRIGHT_BASE_URL: 'http://127.0.0.1:8021/' });
    assert.equal(environment.APP_URL, 'http://127.0.0.1:8021');
    assert.equal(environment.PLAYWRIGHT_BASE_URL, 'http://127.0.0.1:8021/');
});
