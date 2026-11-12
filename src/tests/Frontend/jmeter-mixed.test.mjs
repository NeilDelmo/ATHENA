import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtemp, writeFile, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { roleCounts, prepareMixed, prepareMixedRun, journeys } from '../../scripts/jmeter/mixed.mjs';
import { writeRun, exampleConfig, prepareConfig } from '../../scripts/jmeter/athena.mjs';
import { summarize } from '../../scripts/jmeter/summarize.mjs';

test('mixed role allocation reaches every role at each requested load', () => {
    for (const users of [20, 50, 100]) {
        const counts = roleCounts(users);
        assert.equal(Object.values(counts).reduce((a, b) => a + b), users);
        assert.equal(Object.keys(counts).length, 6);
        assert.ok(Object.values(counts).every(count => count > 0));
    }
});

test('mixed preparation validates role journeys, projects and independent sessions', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'athena-mixed-'));
    try {
        const users = [];
        for (const [workspace, count] of Object.entries(roleCounts(20))) {
            for (let i = 0; i < count; i++) {
                const sessionFile = join(dir, `${users.length}.json`);
                await writeFile(sessionFile, JSON.stringify({ cookies: [{ name: 'athena_session', value: `session-${users.length}`, domain: 'localhost', expires: -1 }] }));
                users.push({ workspace, actorId: users.length + 1, sessionFile,
                    records: { call_id: 1, draft_id: 1, topic_id: 1, document_id: 1, report_id: 1 },
                    recordSets: [{ topic_id: 1, draft_id: 1, document_id: 1 }, { topic_id: 2, draft_id: 2, document_id: 2 }] });
            }
        }
        const input = { baseUrl: 'http://localhost:8092', users, threads: 20, rampUpSeconds: 5,
            loops: 2, warmupLoops: 0, durationSeconds: 0, pauseMs: 0, isolatedTestEnvironment: true };
        const result = await prepareMixed(input, join(dir, 'run.json'));
        assert.equal(result.users.length, 20);
        assert.equal(result.users.find(u => u.workspace === 'faculty').scenario.dashboard, '/faculty/dashboard');
        assert.ok(result.users.find(u => u.workspace === 'research_secretary').scenario.operations.some(op => op.id === 'secretary-project'));
        assert.ok(Object.values(journeys).every(ids => ids.length > 0));
        const duplicate = structuredClone(input);
        duplicate.users.at(-1).sessionFile = users[0].sessionFile;
        await assert.rejects(prepareMixed(duplicate, join(dir, 'run.json')), /Duplicate session/);
        const missingProject = structuredClone(input);
        missingProject.users[0].recordSets = [{ topic_id: 1 }];
        await assert.rejects(prepareMixed(missingProject, join(dir, 'run.json')), /two distinct/);
        const invalidProject = structuredClone(input);
        invalidProject.users[0].recordSets[1].topic_id = 'invalid';
        await assert.rejects(prepareMixed(invalidProject, join(dir, 'run.json')), /real test topic_id/);
        const timed = await prepareMixed({ ...input, durationSeconds: 90, warmupSeconds: 15 }, join(dir, 'run.json'));
        const path = await writeRun(timed, {}, dir);
        const plan = await readFile(join(path, 'athena.jmx'), 'utf8');
        assert.match(plan, /ThreadGroup.scheduler">true/);
        assert.match(plan, /ThreadGroup.duration">\$\{__P/);
        assert.ok(plan.indexOf('head-project"') < plan.indexOf('project-document"'));
        const quickRun = await prepareMixedRun(input, join(dir, 'run.json'), 20, dir, 'major');
        const quick = JSON.parse(await readFile(join(quickRun, 'runtime.local.json'), 'utf8'));
        const quickCoverage = JSON.parse(await readFile(join(quickRun, 'coverage.json'), 'utf8'));
        assert.equal(quick.durationSeconds - quick.warmupSeconds, 60);
        assert.equal(quickCoverage.profile, 'major');
        assert.ok(quick.operations.length < result.operations.length);
        assert.ok(quick.operations.every(op => !op.write && !op.external));
        assert.deepEqual(Object.keys(quickCoverage.journeys), Object.keys(journeys));
        assert.ok(quick.users.every(user => user.workspace === 'vcrdes' || user.recordSets.length >= 2));
    } finally { await rm(dir, { recursive: true, force: true }); }
});

test('legacy Research Head preparation still uses its original workload', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'athena-head-'));
    try {
        await writeFile(join(dir, 'head.json'), JSON.stringify({ cookies: [{ name: 'athena_session', value: 'head', domain: 'athenaresearch.app', expires: -1 }] }));
        const input = exampleConfig();
        input.users[0].sessionFile = 'head.json';
        const result = await prepareConfig(input, join(dir, 'run.json'));
        assert.equal(result.workspace, 'research_head');
        assert.equal(result.operations.length, 15);
        assert.equal(result.durationSeconds, 0);
    } finally { await rm(dir, { recursive: true, force: true }); }
});

test('reduced deployed preparation keeps four roles and rejects copied accounts or sessions', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'athena-reduced-'));
    try {
        const users = [];
        for (const workspace of ['faculty', 'research_head', 'research_secretary', 'research_office']) {
            const sessionFile = join(dir, `${workspace}.json`);
            await writeFile(sessionFile, JSON.stringify({ cookies: [{ name: 'athena_session', value: workspace, domain: 'localhost', expires: -1 }] }));
            users.push({ workspace, actorId: users.length + 1, sessionFile, recordSets: [{ draft_id: 4, topic_id: 1 }] });
        }
        assert.deepEqual(roleCounts(4, 'reduced'), { faculty: 1, research_head: 1, research_secretary: 1, research_office: 1 });
        for (const count of [20, 50, 100]) assert.equal(Object.values(roleCounts(count, 'reduced')).reduce((a, b) => a + b), count);
        const pool = { baseUrl: 'http://localhost', users };
        const path = await prepareMixedRun(pool, join(dir, 'pool.json'), 4, dir, 'reduced');
        const config = JSON.parse(await readFile(join(path, 'runtime.local.json'), 'utf8'));
        const coverage = JSON.parse(await readFile(join(path, 'coverage.json'), 'utf8'));
        assert.equal(config.users.length, 4);
        assert.equal(config.durationSeconds, 0);
        assert.equal(coverage.profile, 'reduced');
        assert.equal(coverage.accountCount, 4);
        assert.deepEqual(coverage.minimumTopics, { faculty: 0, research_head: 1, research_secretary: 1, research_office: 0 });
        const csv = 'timeStamp,elapsed,label,success,workspace,actor_index,project_key\n1000,20,PERF | coordinator-proposals | List,true,research_office,1,\n';
        const reducedSummary = summarize(csv, { roleCounts: { research_office: 1 }, journeys: { research_office: ['coordinator-proposals'] }, minimumTopics: coverage.minimumTopics });
        assert.equal(reducedSummary.actualJourneys.research_office.minimumTopics, 0);
        assert.deepEqual(reducedSummary.actualJourneys.research_office.missingOperations, []);
        assert.equal(summarize(csv, { roleCounts: { research_office: 1 }, journeys: { research_office: ['coordinator-proposals'] } }).actualJourneys.research_office.minimumTopics, 2);
        assert.ok(config.operations.every(op => !op.write && !op.external));
        assert.ok(!config.users.some(user => ['vcrdes', 'faculty_researcher'].includes(user.workspace)));
        const duplicate = structuredClone(pool);
        duplicate.users[1].actorId = duplicate.users[0].actorId;
        await assert.rejects(prepareMixedRun(duplicate, join(dir, 'pool.json'), 4, dir, 'reduced'), /distinct ATHENA account/);
        const copied = structuredClone(pool);
        copied.users[1].sessionFile = copied.users[0].sessionFile;
        await assert.rejects(prepareMixedRun(copied, join(dir, 'pool.json'), 4, dir, 'reduced'), /Duplicate session/);
        await assert.rejects(prepareMixedRun(pool, join(dir, 'pool.json'), 20, dir, 'reduced'), /one session for each/);
    } finally { await rm(dir, { recursive: true, force: true }); }
});
