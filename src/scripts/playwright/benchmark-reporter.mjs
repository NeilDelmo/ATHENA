import { mkdirSync, writeFileSync } from 'node:fs';

export default class BenchmarkReporter {
    constructor() { this.results = []; }

    onTestEnd(test, result) {
        const attachment = result.attachments.find(item => item.name === 'browser-timings');
        this.results.push({ test: test.title, workspace: test.parent?.title, page: test.title.split(': ').slice(1).join(': ') || test.title,
            status: result.status, skipReason: test.annotations?.find(item => item.type === 'skip')?.description,
            ...(attachment?.body ? JSON.parse(attachment.body.toString()) : {}),
            error: result.error?.message,
        });
    }

    onEnd(result) {
        mkdirSync('test-results', { recursive: true });
        const report = { generatedAt: new Date().toISOString(), status: result.status,
            method: 'One browser, serial navigation, one warm-up and three samples per page. Sign-in excluded. Unthrottled network; no concurrent-user capacity claim.',
            results: this.results };
        writeFileSync('test-results/deployed-benchmark.json', `${JSON.stringify(report, null, 2)}\n`);
        const columns = ['workspace', 'page', 'status', 'medianReadyMs', 'minReadyMs', 'maxReadyMs', 'medianTtfbMs', 'medianDomContentLoadedMs', 'skipReason', 'error'];
        const csv = [columns, ...this.results.map(row => columns.map(key => row[key] ?? ''))]
            .map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
        writeFileSync('test-results/deployed-benchmark.csv', `${csv}\n`);
    }
}
