import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { prepareConfig, writeRun } from './athena.mjs';
import { payloadExamples } from './scenarios.mjs';

const stages = {
    create: ['faculty', ['proposal-drafts', 'create-draft'], payloadExamples['create-draft']],
    submit: ['faculty_researcher', ['researcher-project', 'submit-monitoring'], {}],
    forward: ['research_secretary', ['secretary-project', 'forward-monitoring'], { decision: 'forward' }],
    accept: ['research_head', ['head-project', 'accept-monitoring'], { review_status: 'reviewed' }],
};

try {
    const [file, stage, parent = 'test-results/jmeter-mixed/local-workflow'] = process.argv.slice(2);
    if (!file || !Object.hasOwn(stages, stage)) throw new Error('Usage from src: node scripts/jmeter/write-journey.mjs POOL.local.json create|submit|forward|accept');
    const path = resolve(file);
    const pool = JSON.parse(await readFile(path, 'utf8'));
    if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(pool.baseUrl) || pool.isolatedTestEnvironment !== true || pool.writeRecords?.length !== 3) {
        throw new Error('The write journey requires the disposable local ATHENA fixture pool.');
    }
    const [workspace, operations, payload] = stages[stage];
    const users = pool.users.filter(user => user.workspace === workspace).slice(0, pool.writeRecords.length).map((user, i) => ({
        actorId: user.actorId, sessionFile: user.sessionFile, records: stage === 'submit' ? user.writeRecord : pool.writeRecords[i],
        payloads: { [operations.at(-1)]: payload },
    }));
    const config = await prepareConfig({ ...pool, users, threads: users.length, workspace, operations,
        rampUpSeconds: 1, loops: 1, warmupLoops: 0, durationSeconds: 0, pauseMs: 500,
    }, path, { allowWrites: true });
    config.runId = `${stage}-${config.runId}`;
    console.log(await writeRun(config, { baseUrl: pool.baseUrl, stage, workspace, threads: users.length, operations }, parent));
} catch (error) {
    console.error(error.message);
    process.exitCode = 1;
}
