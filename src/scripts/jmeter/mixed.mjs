import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { normalizeBaseUrl, prepareConfig, writeRun } from './athena.mjs';
import { baseUrl, operations } from './scenarios.mjs';

export const journeys = {
    faculty: ['faculty-dashboard', 'calls', 'call-details', 'proposal-drafts', 'proposal-detail', 'submitted-proposals', 'faculty-proposal'],
    faculty_researcher: ['faculty-dashboard', 'researcher-projects', 'researcher-project', 'project-document', 'outcomes'],
    research_head: ['head-dashboard', 'head-analytics', 'head-analytics-refresh', 'head-submissions', 'head-projects', 'head-project', 'project-document', 'report-reviews', 'head-budgets', 'rms-reports'],
    research_office: ['coordinator-dashboard', 'coordinator-proposals', 'review-proposal', 'college-reports'],
    research_secretary: ['secretary-dashboard', 'secretary-reports', 'secretary-project', 'monitoring-document', 'secretary-budgets'],
    vcrdes: ['vcrdes-dashboard'],
};
export const majorJourneys = {
    faculty: ['calls', 'proposal-drafts', 'proposal-detail', 'submitted-proposals'],
    faculty_researcher: ['researcher-projects', 'researcher-project', 'project-document'],
    research_head: ['head-projects', 'head-project', 'report-reviews', 'head-budgets'],
    research_office: ['coordinator-proposals', 'review-proposal'],
    research_secretary: ['secretary-reports', 'secretary-project', 'monitoring-document'],
    vcrdes: ['vcrdes-dashboard'],
};
const allocation = [25, 35, 15, 10, 10, 5];
export const reducedJourneys = {
    faculty: ['calls', 'proposal-drafts', 'proposal-detail', 'submitted-proposals'],
    research_head: ['head-projects', 'head-project', 'report-reviews', 'head-budgets'],
    research_secretary: ['secretary-dashboard', 'secretary-reports', 'secretary-project', 'secretary-budgets'],
    research_office: ['coordinator-dashboard', 'coordinator-proposals', 'college-reports'],
};

export function roleCounts(threads, profile = 'full') {
    if (profile === 'reduced') {
        if (![4, 20, 50, 100].includes(threads)) throw new Error('Use 4 users for reduced validation or 20, 50, or 100 for measurement.');
        return Object.fromEntries(Object.keys(reducedJourneys).map((role, index) => [role, Math.floor(threads / 4) + (index < threads % 4 ? 1 : 0)]));
    }
    if (![6, 20, 50, 100].includes(threads)) throw new Error('Use 6 users for validation or 20, 50, or 100 for measurement.');
    const counts = allocation.map(share => Math.floor(threads * share / 100));
    const order = allocation.map((share, i) => ({ i, fraction: (threads * share) % 100 })).sort((a, b) => b.fraction - a.fraction);
    for (const { i } of order.slice(0, threads - counts.reduce((a, b) => a + b))) counts[i]++;
    return Object.fromEntries(Object.keys(journeys).map((role, i) => [role, threads === 6 ? 1 : counts[i]]));
}

// One order serves all threads. Each thread enables only its own journey.
const order = [...new Set(Object.values(journeys).flat())];
order.splice(order.indexOf('project-document'), 1);
order.splice(order.indexOf('head-project') + 1, 0, 'project-document');
order.splice(order.indexOf('outcomes'), 1);
order.push('outcomes');

export async function prepareMixed(input, configPath) {
    const profile = input.profile ?? 'full';
    if (!['full', 'major', 'reduced'].includes(profile)) throw new Error('Choose the full, major or reduced workload profile.');
    const selectedJourneys = profile === 'reduced' ? reducedJourneys : profile === 'major' ? majorJourneys : journeys;
    const { users, threads, loops, warmupLoops, warmupSeconds = 0, durationSeconds, rampUpSeconds } = input;
    const target = normalizeBaseUrl(input.baseUrl);
    const counts = roleCounts(threads, profile);
    if (!Array.isArray(users) || users.length !== threads) throw new Error('Provide one session for each virtual user.');
    if (!Number.isInteger(warmupSeconds) || warmupSeconds < 0 || (durationSeconds > 0 &&
        (warmupSeconds <= rampUpSeconds || warmupSeconds >= durationSeconds || warmupLoops !== 0))) {
        throw new Error('Timed mixed runs require a warmup longer than ramp-up and shorter than the total duration.');
    }
    for (const [role, count] of Object.entries(counts)) {
        if (users.filter(u => u.workspace === role).length !== count) throw new Error(`Expected ${count} ${role} session(s).`);
    }
    const actors = new Set();
    const sessions = new Set();
    const prepared = [];
    for (const [index, user] of users.entries()) {
        if (!/^[1-9][0-9]*$/.test(String(user.actorId)) || actors.has(String(user.actorId))) throw new Error('Assign one distinct ATHENA account ID per virtual user.');
        actors.add(String(user.actorId));
        if (!Object.hasOwn(selectedJourneys, user.workspace)) throw new Error('This workspace is outside the selected profile.');
        const sets = user.recordSets ?? [];
        if (profile === 'reduced' && (!Array.isArray(sets) || sets.length < 1)) throw new Error(`User ${index + 1}: assign at least one accessible record set.`);
        if (profile !== 'reduced' && user.workspace !== 'vcrdes' && (!Array.isArray(sets) || sets.length < 2 || new Set(sets.map(record => String(record.topic_id))).size !== sets.length)) {
            throw new Error(`User ${index + 1}: assign at least two distinct accessible topics.`);
        }
        const { dashboard, workspaceMarker, operations: selected, users: [verified] } = await prepareConfig({
            ...input, baseUrl: target, workspace: user.workspace, threads: 1, users: [{ ...user,
                recordSets: user.workspace === 'vcrdes' ? [{}] : sets.map(record => ({ ...user.records, ...record })),
            }], operations: selectedJourneys[user.workspace],
        }, configPath, { sessions });
        prepared.push({ ...verified, scenario: { dashboard, workspaceMarker, operations: selected } });
    }
    const selectedIds = new Set(Object.values(selectedJourneys).flat());
    return { ...input, profile, baseUrl: target, users: prepared, loops, warmupSeconds,
        runId: `${threads}-users-${new Date().toISOString().replace(/[:.]/g, '-')}${profile === 'full' ? '' : '-' + profile}`,
        operations: order.filter(id => selectedIds.has(id)).map(id => operations.find(op => op.id === id)), counts,
    };
}

export async function prepareMixedRun(pool, configPath, threads, parent, profile = 'full') {
    const counts = roleCounts(threads, profile);
    // Round robin keeps early ramp-up requests spread across roles.
    const groups = Object.entries(counts).map(([workspace, count]) => pool.users.filter(u => u.workspace === workspace).slice(0, count));
    const users = [];
    for (let i = 0; i < Math.max(...Object.values(counts)); i++) {
        for (const group of groups) if (group[i]) users.push(group[i]);
    }
    const smoke = threads === (profile === 'reduced' ? 4 : 6);
    const major = profile !== 'full';
    const config = await prepareMixed({ ...pool, profile, users, threads, loops: smoke ? 2 : 1, warmupLoops: 0,
        rampUpSeconds: smoke ? 2 : major ? 10 : 12, warmupSeconds: smoke ? 0 : major ? 15 : 20,
        durationSeconds: smoke ? 0 : major ? 75 : 110,
        pauseMs: smoke ? 350 : 1500,
    }, configPath);
    const selectedJourneys = profile === 'reduced' ? reducedJourneys : major ? majorJourneys : journeys;
    const coverage = { baseUrl: config.baseUrl, profile, threads, roleCounts: counts, journeys: selectedJourneys,
        accountCount: new Set(users.map(user => String(user.actorId))).size,
        ...(profile === 'reduced' ? { minimumTopics: { faculty: 0, research_head: 1, research_secretary: 1, research_office: 0 },
            limitations: ['Four-role read workload with sparse data; no multi-project or complete submission/approval coverage.', 'Excluded workspaces: Faculty Researcher and VCRDES.'] } : {}),
        rampUpSeconds: config.rampUpSeconds, warmupSeconds: config.warmupSeconds, loops: smoke ? config.loops : null,
        measurementSeconds: smoke ? null : config.durationSeconds - config.warmupSeconds,
        pauseMs: config.pauseMs, projectCount: new Set(users.flatMap(u => (u.recordSets ?? []).map(r => String(r.topic_id))).filter(id => /^[1-9]\d*$/.test(id))).size,
    };
    return writeRun(config, coverage, parent);
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    try {
        const [file, level, parent, profile = 'full'] = process.argv.slice(2);
        if (file === 'template') {
            const path = resolve(level ?? 'scripts/jmeter/mixed-deployed.local.json');
            const users = Object.entries(roleCounts(100)).flatMap(([workspace, count]) => {
                const fields = new Set(journeys[workspace].flatMap(id => operations.find(op => op.id === id).records ?? []));
                if (workspace !== 'vcrdes') fields.add('topic_id');
                const record = Object.fromEntries([...fields].map(field => [field, null]));
                return Array.from({ length: count }, (_, i) => ({ workspace, actorId: null,
                    sessionFile: `private/${workspace}-${String(i + 1).padStart(2, '0')}.json`, recordSets: fields.size ? [record, record] : [],
                }));
            });
            await writeFile(path, JSON.stringify({ baseUrl, users }, null, 2) + '\n', { flag: 'wx' });
            console.log(`Created ${path}. Use capture-session.mjs for each deployed test account and fill in its accessible record IDs.`);
        } else {
            if (!file || !level) throw new Error('Usage from src: node scripts/jmeter/mixed.mjs POOL.local.json 4|6|20|50|100 [RESULT_PARENT] [full|major|reduced]\nCreate a deployed pool with: node scripts/jmeter/mixed.mjs template');
            const path = resolve(file);
            console.log(await prepareMixedRun(JSON.parse(await readFile(path, 'utf8')), path, Number(level), parent, profile));
        }
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
