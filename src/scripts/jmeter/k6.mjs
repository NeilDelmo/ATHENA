import { spawn } from 'node:child_process';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { resolve, join, dirname } from 'node:path';
import { prepareMixedRun } from './mixed.mjs';
import { prepareConfig } from './athena.mjs';

// From src: node scripts/jmeter/k6.mjs --pool POOL.json
// Or: node scripts/jmeter/k6.mjs --session-config CONFIG.json
const args = process.argv.slice(2);
const option = key => args[args.indexOf(key) + 1];
const binary = process.env.ATHENA_K6 ?? resolve('test-results/tools/k6/k6-v2.3.0-windows-amd64/k6.exe');
const parent = resolve('test-results/k6', new Date().toISOString().replace(/[:.]/g, '-'));
const summaries = [];

async function run(path) {
    const config = JSON.parse(await readFile(path, 'utf8'));
    const host = new URL(config.baseUrl).hostname;
    for (const user of config.users) {
        user.workspace ??= config.workspace;
        const operations = (user.scenario ?? config).operations;
        if (operations.some(op => op.method !== 'GET' || op.write || op.external)) throw new Error('k6 only supports read operations.');
        const state = JSON.parse(await readFile(user.sessionFile, 'utf8'));
        user.cookies = state.cookies.filter(cookie => host === cookie.domain.replace(/^\./, '') || host.endsWith('.' + cookie.domain.replace(/^\./, '')));
    }
    const runtime = join(dirname(path), 'k6-runtime.local.json');
    const summary = join(dirname(path), 'k6-summary.json');
    await writeFile(runtime, JSON.stringify(config, null, 2), { mode: 0o600 });
    const child = spawn(binary, ['run', '--quiet', '--no-usage-report', 'scripts/jmeter/athena-k6.js'], {
        stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true,
        env: { ...process.env, ATHENA_CONFIG: runtime, ATHENA_SUMMARY: summary },
    });
    let log = '';
    for (const stream of [child.stdout, child.stderr]) stream.on('data', data => { process.stdout.write(data); log += data; });
    const code = await new Promise((done, reject) => { child.on('error', reject); child.on('exit', done); });
    await writeFile(join(dirname(path), 'k6.log'), log);
    const result = JSON.parse(await readFile(summary, 'utf8'));
    summaries.push(result);
    const columns = ['workspace', 'users', 'measurementSeconds', 'requests', 'failures', 'averageMs', 'p95Ms', 'coveredUserOperations', 'requiredUserOperations', 'passed'];
    await writeFile(join(parent, 'summary.csv'), [columns.join(','), ...summaries.map(row => columns.map(key => row[key]).join(','))].join('\n') + '\n');
    if (code !== 0 || !result.passed) throw new Error(`k6 check failed (${code}). Results: ${summary}`);
}

try {
    await mkdir(parent, { recursive: true });
    if (args.includes('--pool')) {
        const path = resolve(option('--pool'));
        const pool = JSON.parse(await readFile(path, 'utf8'));
        for (const users of [6, 20, 50, 100]) {
            const directory = await prepareMixedRun(pool, path, users, parent, 'major');
            await run(join(directory, 'runtime.local.json'));
        }
    } else if (args.includes('--session-config')) {
        const path = resolve(option('--session-config'));
        const input = JSON.parse(await readFile(path, 'utf8'));
        const config = await prepareConfig({ ...input, threads: 1, loops: 1, warmupLoops: 0,
            rampUpSeconds: 1, warmupSeconds: 15, durationSeconds: 75, pauseMs: 1500,
            operations: input.operations.map(op => typeof op === 'string' ? op : op.id),
        }, path);
        const output = join(parent, 'runtime.local.json');
        await writeFile(output, JSON.stringify(config, null, 2), { mode: 0o600 });
        await run(output);
    } else throw new Error('Choose --pool FILE or --session-config FILE.');
    console.log(`k6 results: ${parent}`);
} catch (error) { console.error(error.message); process.exitCode = 1; }
