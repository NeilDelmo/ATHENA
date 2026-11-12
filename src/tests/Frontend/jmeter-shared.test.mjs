import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import * as shared from '../../scripts/jmeter/shared.mjs';

test('shared-account runs keep three verified accounts and label virtual user counts honestly', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'athena-shared-'));
    try {
        const users = [];
        for (const workspace of ['research_head', 'research_secretary', 'research_office']) {
            const sessionFile = join(dir, `${workspace}.json`);
            await writeFile(sessionFile, JSON.stringify({ cookies: [{ name: 'athena_session', value: workspace, domain: 'localhost', expires: -1 }] }));
            users.push({ workspace, actorId: users.length + 1, sessionFile, records: { topic_id: 1 } });
        }
        const pool = { baseUrl: 'http://localhost', loadModel: 'shared-account', users };
        for (const threads of [3, 20, 50, 100]) {
            const path = await shared.prepareSharedRun(pool, join(dir, 'pool.json'), threads, dir);
            const config = JSON.parse(await readFile(join(path, 'runtime.local.json'), 'utf8'));
            const coverage = JSON.parse(await readFile(join(path, 'coverage.json'), 'utf8'));
            const plan = await readFile(join(path, 'athena.jmx'), 'utf8');
            assert.equal(config.users.length, threads);
            assert.equal(new Set(config.users.map(user => user.actorId)).size, 3);
            assert.equal(new Set(config.users.map(user => user.sessionFile)).size, 3);
            assert.equal(coverage.loadModel, 'shared-account');
            assert.equal(coverage.accountCount, 3);
            assert.equal(Object.values(coverage.roleCounts).reduce((a, b) => a + b), threads);
            assert.equal(coverage.measurementSeconds, threads === 3 ? null : 60);
            if (threads !== 3) assert.equal(config.durationSeconds - config.warmupSeconds, 60);
            if (threads === 100) assert.equal(config.warmupSeconds, 75);
            assert.ok(config.operations.every(op => op.method === 'GET' && !op.write && !op.external));
            assert.match(plan, /Virtual users sharing 3 accounts/);
        }
        await assert.rejects(shared.prepareSharedRun({ ...pool, loadModel: undefined }, join(dir, 'pool.json'), 20, dir), /shared-account/);
        const copied = structuredClone(pool);
        copied.users[1].sessionFile = copied.users[0].sessionFile;
        await assert.rejects(shared.prepareSharedRun(copied, join(dir, 'pool.json'), 20, dir), /Duplicate session/);
        const sameAccount = structuredClone(pool);
        sameAccount.users[1].actorId = sameAccount.users[0].actorId;
        await assert.rejects(shared.prepareSharedRun(sameAccount, join(dir, 'pool.json'), 20, dir), /distinct accounts/);
        await assert.rejects(shared.prepareSharedRun(pool, join(dir, 'pool.json'), 200, dir), /3, 20, 50 or 100/);
    } finally { await rm(dir, { recursive: true, force: true }); }
});
