import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { applicationRoot, lifecycleEnvironment, lifecycleStorage } from './lifecycle-environment.mjs';

const environment = lifecycleEnvironment();
for (const directory of ['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'bootstrap']) {
    mkdirSync(resolve(lifecycleStorage, directory), { recursive: true });
}
writeFileSync(resolve(lifecycleStorage, 'clock.txt'), '2026-01-01 10:00:00');

const prepared = spawnSync('php', ['tests/Playwright/prepare-lifecycle.php'], {
    cwd: applicationRoot, env: environment, stdio: 'inherit', windowsHide: true,
});
if (prepared.status !== 0) process.exit(prepared.status ?? 1);
if (environment.PLAYWRIGHT_FUNCTIONAL === '1') {
    const functional = spawnSync('php', ['tests/Playwright/prepare-lifecycle.php', 'functional'], {
        cwd: applicationRoot, env: environment, stdio: 'inherit', windowsHide: true,
    });
    if (functional.status !== 0) process.exit(functional.status ?? 1);
    if (!JSON.parse(readFileSync(resolve(applicationRoot, 'playwright/.auth/local/lifecycle.json'), 'utf8')).functional) {
        throw new Error('Functional reference fixture preparation did not complete.');
    }
}

const target = new URL(environment.APP_URL);
const clock = resolve(applicationRoot, 'tests/Playwright/lifecycle-clock.php');
const children = [
    spawn('php', ['-d', 'variables_order=EGPCS', '-S', target.host, '-t', 'public', 'tests/Playwright/lifecycle-router.php'],
    { cwd: applicationRoot, env: environment, stdio: 'inherit', windowsHide: true }),
    spawn('php', ['-d', `auto_prepend_file=${clock}`, 'artisan', 'queue:work', 'proposal-pdfs',
        '--queue=proposal-pdfs', '--sleep=1', '--tries=1', '--timeout=240', '--no-interaction'],
    { cwd: applicationRoot, env: environment, stdio: 'inherit', windowsHide: true }),
];

let stopping = false;
function stop(code = 0) {
    if (stopping) return;
    stopping = true;
    for (const child of children) child.kill();
    process.exit(code);
}
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => stop());
for (const child of children) {
    child.on('error', error => { console.error(error.message); stop(1); });
    child.on('exit', code => { if (!stopping) stop(code || 1); });
}
