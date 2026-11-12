import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { normalizeBaseUrl, prepareConfig, writeRun } from './athena.mjs';
import { reducedJourneys } from './mixed.mjs';

export async function prepareSharedRun(pool, configPath, threads, parent) {
    const baseUrl = normalizeBaseUrl(pool.baseUrl);
    if (pool.loadModel !== 'shared-account') throw new Error('Set loadModel:shared-account to explicitly select shared-account load.');
    if (![3, 20, 50, 100].includes(threads)) throw new Error('Use 3, 20, 50 or 100 virtual users.');
    const roles = ['research_head', 'research_secretary', 'research_office'];
    if (pool.users?.length !== 3 || roles.some(role => pool.users.filter(user => user.workspace === role).length !== 1)) {
        throw new Error('Provide exactly one Head, Secretary and Coordinator account.');
    }
    if (pool.users.some(user => !/^[1-9]\d*$/.test(String(user.actorId))) || new Set(pool.users.map(user => String(user.actorId))).size !== 3) {
        throw new Error('The three workspaces require distinct accounts.');
    }
    const smoke = threads === 3;
    const warmupSeconds = smoke ? 0 : threads === 100 ? 75 : 15;
    const timing = { threads: 1, loops: 1, warmupLoops: 0, warmupSeconds,
        rampUpSeconds: smoke ? 2 : 10, durationSeconds: smoke ? 0 : warmupSeconds + 60, pauseMs: smoke ? 500 : 1500 };
    const sessions = new Set();
    const seeds = [];
    for (const workspace of roles) {
        const source = pool.users.find(user => user.workspace === workspace);
        const prepared = await prepareConfig({ baseUrl, ...timing, workspace,
            users: [source], operations: reducedJourneys[workspace], isolatedTestEnvironment: false,
        }, configPath, { sessions });
        if (prepared.operations.some(op => op.method !== 'GET' || op.write || op.external)) throw new Error('Shared-account load permits read-only GET operations.');
        seeds.push({ ...prepared.users[0], recordSets: [prepared.users[0].records],
            scenario: { dashboard: prepared.dashboard, workspaceMarker: prepared.workspaceMarker, operations: prepared.operations } });
    }
    // Repeated cookies are intentional only in this separately labelled workload.
    const users = Array.from({ length: threads }, (_, index) => structuredClone(seeds[index % seeds.length]));
    const operations = [...new Map(seeds.flatMap(user => user.scenario.operations).map(op => [op.id, op])).values()];
    const roleCounts = Object.fromEntries(roles.map(role => [role, users.filter(user => user.workspace === role).length]));
    const limitations = ['Virtual users share exactly three accounts and server sessions; results do not represent independent-user scale.',
        'Shared session access can affect contention, caching and account-based rate limits.',
        'Sparse data: one project; no multi-project, submission, approval, document-download or browser-rendering coverage.',
        'Faculty, Faculty Researcher and VCRDES are excluded.'];
    return writeRun({ ...timing, baseUrl,
        threads, users, operations, allowWrites: false, allowExternal: false, loadModel: 'shared-account',
        measurementSeconds: smoke ? null : 60,
        runId: `shared-account-${threads}-vus-${new Date().toISOString().replace(/[:.]/g, '-')}`,
    }, { baseUrl, loadModel: 'shared-account', accountCount: 3, sharedSessionCount: 3,
        threads, virtualUsers: threads, roleCounts, journeys: Object.fromEntries(roles.map(role => [role, reducedJourneys[role]])),
        minimumTopics: { research_head: 1, research_secretary: 1, research_office: 0 },
        rampUpSeconds: timing.rampUpSeconds, warmupSeconds: timing.warmupSeconds,
        measurementSeconds: smoke ? null : 60, pauseMs: timing.pauseMs, limitations,
    }, parent);
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    try {
        const [file, level, parent = 'test-results/jmeter-mixed/deployed-shared'] = process.argv.slice(2);
        if (!file || !level) throw new Error('Usage from src: node scripts/jmeter/shared.mjs POOL.local.json 3|20|50|100 [RESULT_PARENT]');
        const path = resolve(file);
        console.log(await prepareSharedRun(JSON.parse(await readFile(path, 'utf8')), path, Number(level), parent));
    } catch (error) { console.error(error.message); process.exitCode = 1; }
}
