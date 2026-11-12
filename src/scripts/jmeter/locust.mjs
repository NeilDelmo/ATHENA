import { spawn, spawnSync } from 'node:child_process';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { prepareMixedRun } from './mixed.mjs';
import { prepareConfig } from './athena.mjs';

// Run from src: node scripts/jmeter/locust.mjs --local
// Reuse one captured account: node scripts/jmeter/locust.mjs --session-config CONFIG.json
// Prepared independent account pool: node scripts/jmeter/locust.mjs --pool POOL.json
const args = process.argv.slice(2);
const value = key => args[args.indexOf(key) + 1];
const parent = resolve('test-results/locust', new Date().toISOString().replace(/[:.]/g, '-'));
let server;
const summaries = [];
async function execute(configPath) {
    const child = spawn('python', ['scripts/jmeter/athena-locust.py', configPath], { stdio: 'inherit', windowsHide: true });
    await new Promise((done, reject) => {
        child.on('error', reject);
        child.on('exit', code => code === 0 ? done() : reject(new Error(`Locust check failed (${code}). Results: ${configPath}`)));
    });
    summaries.push(JSON.parse(await readFile(join(resolve(configPath, '..'), 'locust-summary.json'), 'utf8')));
    const columns = ['users', 'measurementSeconds', 'requests', 'failures', 'averageMs', 'p95Ms', 'coveredUserOperations', 'requiredUserOperations', 'passed'];
    await writeFile(join(parent, 'summary.csv'), [columns.join(','), ...summaries.map(row => columns.map(key => row[key]).join(','))].join('\n') + '\n');
}
try {
    let poolPath;
    if (args.includes('--local')) {
        server = spawn('node', ['scripts/jmeter/serve-local.mjs'], { stdio: ['pipe', 'pipe', 'inherit'], windowsHide: true });
        poolPath = await new Promise((done, reject) => {
            let output = '';
            const timeout = setTimeout(() => reject(new Error('Local setup timed out')), 300_000);
            server.on('error', reject);
            server.on('exit', code => { clearTimeout(timeout); reject(new Error(`Local setup exited (${code})`)); });
            server.stdout.on('data', chunk => {
                process.stdout.write(chunk);
                output += chunk;
                const match = output.match(/Local pool: (.+)\r?\n/);
                if (match) { clearTimeout(timeout); done(match[1].trim()); }
            });
        });
        await new Promise(done => setTimeout(done, 1500));
    } else if (args.includes('--pool')) poolPath = resolve(value('--pool'));
    if (poolPath) {
        const pool = JSON.parse(await readFile(poolPath, 'utf8'));
        for (const users of [6, 20, 50, 100]) {
            const directory = await prepareMixedRun(pool, poolPath, users, parent, 'major');
            await execute(join(directory, 'runtime.local.json'));
        }
    } else if (args.includes('--session-config')) {
        const path = resolve(value('--session-config'));
        const input = JSON.parse(await readFile(path, 'utf8'));
        const config = await prepareConfig({ ...input, threads: 1, loops: 1, warmupLoops: 0,
            rampUpSeconds: 1, warmupSeconds: 15, durationSeconds: 75, pauseMs: 1500,
            operations: input.operations.map(op => typeof op === 'string' ? op : op.id),
        }, path);
        await mkdir(parent, { recursive: true });
        const target = join(parent, 'runtime.local.json');
        await writeFile(target, JSON.stringify(config, null, 2));
        await execute(target);
    } else throw new Error('Choose --local, --pool FILE or --session-config FILE.');
    console.log(`Locust results: ${parent}`);
} catch (error) {
    console.error(error.message);
    process.exitCode = 1;
} finally {
    if (server && server.exitCode === null) {
        server.stdin.write('stop\n');
        await new Promise(done => server.once('exit', done));
    }
}
