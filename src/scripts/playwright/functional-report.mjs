import { readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

export const legacyRules = [
    ['AUTH-ROLES:', ['AUTH-01', 'ACCESS-01']],
    ['ACCESS-OWNER:', ['ACCESS-03', 'PROP-05']],
    ['DRAFT-SAVE:', ['PROP-03']],
    ['REVISE:', ['PROP-03']],
    ['SUBMIT:', ['PROP-01']],
    ['REVIEW-GAD:', ['EXTRACT-01', 'APPR-01']],
    ['REVIEW-LREC:', ['EXTRACT-01', 'APPR-01']],
    ['SIGNING:', ['DOC-01', 'APPR-01']],
    ['NTP:', ['APPR-01']],
    ['MONITORING:', ['PROJ-01', 'EXPORT-01', 'EXPORT-02']],
    ['MONITORING-CORRECT:', ['PROJ-02']],
    ['PROGRESS:', ['PROJ-01', 'EXPORT-01', 'EXPORT-02']],
    ['COORDINATOR:', ['EXPORT-01', 'EXPORT-02']],
    ['TERMINAL:', ['PROJ-01', 'EXPORT-01', 'EXPORT-02']],
    ['COMPLETE:', ['APPR-01']],
    ['ARCHIVE:', ['DOC-01', 'EXPORT-02']],
];

export function mappedIds(title, file = '') {
    if (file.endsWith('capstone-guest.spec.js')) return title.startsWith('ACCESS-') ? ['ACCESS-04'] : [];
    if (file.endsWith('lifecycle.spec.js')) return legacyRules.find(([prefix]) => title.startsWith(prefix))?.[1] ?? [];
    return [...new Set(title.match(/\b[A-Z]+-\d{2}\b/g) ?? [])];
}

export function currentCaseStatus(definition, evidence) {
    if (definition.blocked) return 'Blocked';
    if (!evidence.length) return 'Not run';
    if (evidence.some(row => row.status === 'Failed')) return 'Failed';
    if (definition.requiredEvidence?.some(required => !evidence.some(row => row.tool === required.tool && row.variation === required.variation))) return 'Not run';
    if (!definition.completeScope || evidence.some(row => row.status !== 'Passed')) return 'Not run';
    return 'Passed';
}

export function collectAttempts(playwright, postman, browserName, postmanName) {
    const flatten = suites => suites.flatMap(suite => [...(suite.specs ?? []), ...flatten(suite.suites ?? [])]);
    const attempts = [];
    const runId = new Date(playwright.stats.startTime).toISOString().replace(/[:.]/g, '-');
    for (const spec of flatten(playwright.suites)) {
        if (spec.file?.endsWith('zz-postman.spec.js') && spec.title.startsWith('POSTMAN:')) continue;
        for (const test of spec.tests) for (const result of test.results) {
            const ids = mappedIds(spec.title, spec.file);
            for (const id of ids) attempts.push({ id, runId, tool: 'Playwright', variation: spec.title,
                status: result.status === 'passed' ? 'Passed' : result.status === 'skipped' ? 'Not run' : 'Failed',
                actual: result.error?.message ?? (result.status === 'passed' ? 'All assertions in the linked browser test passed.' : `Browser outcome: ${result.status}`),
                evidence: `${browserName} :: ${spec.title}`, date: result.startTime ?? playwright.stats.startTime,
            });
        }
    }
    let actor = 'Unknown';
    for (const row of postman.results) {
        if (row.name.startsWith('SETUP session ')) actor = row.name.slice('SETUP session '.length).replace(/^faculty$/, 'functional_owner');
        if (row.name.startsWith('SETUP')) continue;
        const passed = !row.error && row.assertions.length > 0 && row.assertions.every(assertion => assertion.passed);
        for (const id of mappedIds(row.name)) attempts.push({ id, runId, tool: 'Postman / Newman', actor, variation: row.name,
            status: passed ? 'Passed' : 'Failed', actual: `HTTP ${row.status}; ${row.assertions.filter(a => a.passed).length}/${row.assertions.length} assertions passed.${row.error ? ' '+row.error : ''} ${row.assertions.filter(a => !a.passed).map(a => a.error).join(' ')}`.trim(),
            evidence: `${postmanName} :: ${row.name}`, date: postman.executedAt,
        });
    }
    return { attempts, runId };
}

export function buildFunctionalReport() {
    const catalog = JSON.parse(readFileSync('tests/Playwright/functional-catalog.json', 'utf8'));
    const playwright = JSON.parse(readFileSync('test-results/functional-playwright.json', 'utf8'));
    const postman = JSON.parse(readFileSync('test-results/functional-postman.json', 'utf8'));
    if (Date.parse(postman.executedAt) < Date.parse(playwright.stats.startTime)) throw new Error('Postman evidence belongs to an older run; refusing to combine it with current browser results.');
    if (postman.results.some(row => row.name.startsWith('SETUP') && (row.error || row.assertions.some(a => !a.passed)))) throw new Error('Postman session setup failed; resolve setup before publishing a correctness rate.');
    const { attempts, runId } = collectAttempts(playwright, postman, 'functional-playwright.json', 'functional-postman.json');
    let revision, dirtyWorkingTree;
    try {
        revision = execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8', windowsHide: true }).trim();
        dirtyWorkingTree = Boolean(execFileSync('git', ['status', '--porcelain'], { encoding: 'utf8', windowsHide: true }).trim());
    } catch { revision = 'Unknown'; dirtyWorkingTree = true; }
    const cases = catalog.cases.map(definition => {
        const evidence = attempts.filter(attempt => attempt.id === definition.id);
        return { ...definition, status: currentCaseStatus(definition, evidence), tools: [...new Set(evidence.map(row => row.tool))].join(' + '),
            attempts: evidence,
            actual: `${evidence.length ? `${evidence.filter(r => r.status === 'Passed').length}/${evidence.length} linked checks passed. ` : ''}${definition.gap || definition.scope}`,
        };
    });
    const counts = Object.fromEntries(['Passed', 'Failed', 'Blocked', 'Not run'].map(status => [status, cases.filter(row => row.status === status).length]));
    const executed = counts.Passed + counts.Failed;
    const report = { runId, generatedAt: new Date().toISOString(), revision, dirtyWorkingTree,
        environment: playwright.config.metadata.applicationURL, source: catalog.source, manuscript: catalog.manuscript,
        counts, plannedCases: cases.length, executedCases: executed, coverage: executed / cases.length,
        executedCorrectness: executed ? counts.Passed / executed : null, plannedRequirementPassRate: counts.Passed / cases.length,
        manuscriptThreshold: 0.95, meetsManuscriptThreshold: counts.Passed / cases.length >= 0.95,
        playwrightStats: playwright.stats, postmanStats: postman.stats,
        limitations: ['The source workbook uses Passed/(Passed+Failed); the manuscript uses Passed/Total Test Cases. Both are reported without substituting denominators.',
            'A partially covered base case remains Not run until all defined variations are covered; mapped successful checks are retained in Run History.',
            'Local synthetic sessions exclude live Google OAuth and CSRF enforcement. Live AI scholarly accuracy and external-service availability are not established.',
            'Previous deployed page timings are not included in these functional correctness rates.'], cases, attempts };
    writeFileSync('test-results/functional-correctness.json', JSON.stringify(report, null, 2)+'\n');
    console.log(JSON.stringify({ counts, planned: cases.length, executed, executedCorrectness: report.executedCorrectness, plannedRequirementPassRate: report.plannedRequirementPassRate }));
    return report;
}

if (process.argv[1]?.replaceAll('\\', '/').endsWith('/functional-report.mjs')) buildFunctionalReport();
