import http from 'k6/http';
import { sleep } from 'k6';
import exec from 'k6/execution';
import { Counter, Trend } from 'k6/metrics';
import { responseError } from './k6-response.mjs';

const config = JSON.parse(open(__ENV.ATHENA_CONFIG));
const timed = config.durationSeconds > 0;
const required = config.users.reduce((n, user) => n + (user.scenario ?? config).operations.length, 0);
export const options = {
    noCookiesReset: true,
    userAgent: 'ATHENA-Capstone-k6',
    systemTags: ['name', 'method', 'status', 'scenario'],
    scenarios: { major: timed
        ? { executor: 'constant-vus', vus: config.users.length, duration: `${config.durationSeconds}s`, gracefulStop: '125s' }
        : { executor: 'per-vu-iterations', vus: config.users.length, iterations: config.loops, maxDuration: '180s', gracefulStop: '125s' } },
    thresholds: { athena_errors: ['count==0'], athena_covered: [`count==${required}`], athena_requests: ['count>0'] },
};
const duration = new Trend('athena_duration', true);
const requests = new Counter('athena_requests');
const errors = new Counter('athena_errors');
const measuredErrors = new Counter('athena_measured_errors');
const covered = new Counter('athena_covered');
const seen = new Set();
let initialized = false;

export default function () {
    const user = config.users[exec.vu.idInTest - 1];
    const scenario = user.scenario ?? config;
    const elapsed = () => (Date.now() - exec.scenario.startTime) / 1000;
    function request(operation, records = {}, setup = false) {
        const path = operation.path.replace(/\$\{(\w+)\}/g, (_, key) => {
            if (!records[key]) throw new Error(`Missing record: ${key}`);
            return records[key];
        });
        const start = elapsed();
        const tags = { name: `${user.workspace} | ${operation.id}` };
        const response = http.get(config.baseUrl + path, { redirects: 0, timeout: '120s', tags });
        let error = responseError(response.status, response.headers['Content-Type'] ?? '', response.body, operation, user);
        if (setup && !error && !response.body.includes(scenario.workspaceMarker)) error = 'Missing workspace navigation';
        errors.add(error ? 1 : 0, tags);
        if (error) console.error(`${tags.name}: ${error}`);
        if (!setup && (!timed || start >= config.warmupSeconds && start < config.durationSeconds)) {
            requests.add(1, tags);
            measuredErrors.add(error ? 1 : 0, tags);
            duration.add(response.timings.duration, tags);
            if (!seen.has(operation.id)) { seen.add(operation.id); covered.add(1); }
        }
        if (setup && error) exec.test.abort(`${tags.name}: ${error}`);
    }
    if (!initialized) {
        sleep((exec.vu.idInTest - 1) * config.rampUpSeconds / config.users.length);
        const jar = http.cookieJar();
        for (const cookie of user.cookies) jar.set(config.baseUrl, cookie.name, cookie.value, {
            domain: cookie.domain, path: cookie.path ?? '/', secure: cookie.secure ?? false,
        });
        request({ id: 'session', path: scenario.dashboard }, {}, true);
        initialized = true;
    }
    const sets = user.recordSets?.length ? user.recordSets : [user.records ?? {}];
    const records = { ...user.records, ...sets[exec.vu.iterationInScenario % sets.length] };
    for (const operation of scenario.operations) {
        sleep(config.pauseMs / 1000);
        if (timed && elapsed() >= config.durationSeconds) return;
        request(operation, records);
    }
}

export function handleSummary(data) {
    const count = name => data.metrics[name]?.values.count ?? 0;
    const values = data.metrics.athena_duration?.values ?? {};
    const summary = { tool: 'k6', baseUrl: config.baseUrl, workspace: config.workspace ?? 'mixed',
        users: config.users.length, measurementSeconds: timed ? config.durationSeconds - config.warmupSeconds : null,
        requests: count('athena_requests'), failures: count('athena_measured_errors'), setupOrRunErrors: count('athena_errors'),
        averageMs: values.avg ?? null, p95Ms: values['p(95)'] ?? null,
        coveredUserOperations: count('athena_covered'), requiredUserOperations: required };
    summary.passed = summary.requests > 0 && summary.setupOrRunErrors === 0 && summary.coveredUserOperations === required;
    return { [__ENV.ATHENA_SUMMARY]: JSON.stringify(summary, null, 2) + '\n', stdout: JSON.stringify(summary) + '\n' };
}
