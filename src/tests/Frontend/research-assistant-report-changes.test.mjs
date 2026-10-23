import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { assistantReportEditor } from '../../resources/js/assistant-report-editor.js';
import { normalizeAssistantReportChanges, reportContext, reportKey, reportChangeFieldLimit, reportChangeHandoff,
    researchAssistantReportChanges } from '../../resources/js/research-assistant-report-changes.js';

const token = 'a'.repeat(64);
const cases = [
    [{ report_type: 'progress-report', topic_id: 7 }, 'progress-report:topic:7', 'results_discussion'],
    [{ report_type: 'monitoring-tool', topic_id: 7, source_key: 'revision:8' }, 'monitoring-tool:topic:7:revision:8', 'work_plan.0.findings'],
    [{ report_type: 'terminal-report', topic_id: 7 }, 'terminal-report:topic:7', 'terminal_data.literature_review'],
    [{ report_type: 'comment-response', topic_id: 7 }, 'comment-response:topic:7', 'feedback_responses.paper_2.response'],
    [{ report_type: 'college-quarterly', report_id: 7 }, 'college-quarterly:7', 'other_accomplishments'],
    [{ report_type: 'rms-quarterly', report_id: 7 }, 'rms-quarterly:7', 'row_details.ip-abcdef.remarks'],
];
const suggestion = (field, overrides = {}) => ({ id: 'change_1', token, field, before: 'Current text.', value: 'Clearer text.', label: 'Narrative', ...overrides });
const packet = (context = cases[0][0], field = cases[0][2], overrides = {}) => ({ context, report_key: reportKey(context),
    report_type: context.report_type, title: 'Saved report', editor_url: 'https://athena.test/editor', suggestions: [suggestion(field)], ...overrides });

for (const [context, key, field] of cases) {
    test(`${context.report_type} preserves its own identity and field`, () => {
        assert.equal(reportKey(context), key);
        const normalized = normalizeAssistantReportChanges(packet(context, field));
        assert.equal(normalized.report_key, key);
        assert.equal(normalized.suggestions[0].field, field);
        assert.deepEqual(normalized.context, context);
        assert.equal(normalizeAssistantReportChanges(packet(context, field, { report_key: `${key}:other` })), null);
    });
}

test('invalid contexts cannot select arbitrary reports or revisions', () => {
    for (const context of [null, {}, { report_type: 'proposal', topic_id: 7 },
        { report_type: 'college-quarterly', report_id: true }, { report_type: 'terminal-report', topic_id: '7.0' },
        { report_type: 'monitoring-tool', topic_id: 7, source_key: 'revision:0' },
        { report_type: 'monitoring-tool', topic_id: 7, source_key: 'other' }]) assert.equal(reportContext(context), null);
    assert.deepEqual(reportContext({ ...cases[0][0], response_values: { malicious: 'ignored' }, extra: 'ignored' }), cases[0][0]);
});

test('amounts, identifiers, approved objectives and decisions cannot become editable candidates', () => {
    for (const type of cases.map(([context]) => context.report_type)) {
        for (const field of ['objectives', 'topic_id', 'total_expenditure', 'terminal_data.total_expenditure',
            'reporting_date', 'review_status', 'work_plan.0.percentage', 'ip_assets.0.filed_at', 'signatories.name']) {
            assert.equal(reportChangeFieldLimit(type, field), 0, `${type}:${field}`);
        }
    }
});

test('only bounded narrative fields and valid nonduplicate immutable tokens survive history normalization', () => {
    const candidates = [suggestion('results_discussion'), suggestion('rationale', { id: 'duplicate_token' }),
        suggestion('introduction', { id: 'change_1', token: 'b'.repeat(64) }), suggestion('rationale', { id: 'unsafe', token: 'short' }),
        suggestion('rationale', { id: 'large', token: 'c'.repeat(64), value: 'x'.repeat(30001) }),
        suggestion('total_expenditure', { id: 'amount', token: 'd'.repeat(64) })];
    const normalized = normalizeAssistantReportChanges(packet(undefined, undefined, { suggestions: candidates }));
    assert.equal(normalized.suggestions.length, 1);
    assert.equal(normalized.suggestions[0].before, 'Current text.');
    assert.equal(reportChangeFieldLimit('monitoring-tool', 'work_plan.11.findings'), 0);
    assert.equal(reportChangeFieldLimit('college-quarterly', 'ip_assets.100.remarks'), 0);
});

test('rich revisions are restricted to terminal literature, with all previews retained as inert strings', () => {
    const context = cases[2][0];
    const html = '<p>Claim <span data-proposal-citation="3">[1]</span></p>';
    assert.equal(normalizeAssistantReportChanges(packet(context, cases[2][2], { suggestions: [suggestion(cases[2][2], { value: html, format: 'html' })] })).suggestions[0].value, html);
    assert.equal(normalizeAssistantReportChanges(packet(undefined, undefined, { suggestions: [suggestion('results_discussion', { format: 'html' })] })).suggestions.length, 0);
});

test('handoff stores only a token and report identity, expires, and cannot cross accounts', () => {
    const data = new Map();
    const storage = { getItem: (key) => data.get(key), setItem: (key, value) => data.set(key, value), removeItem: (key) => data.delete(key) };
    let now = 100;
    const handoff = reportChangeHandoff(storage, 5, () => now);
    const selected = { report_key: cases[0][1], token, suggestion_id: 'change_1', value: 'never store replacement' };
    assert.equal(handoff.stage(selected), true);
    assert.doesNotMatch([...data.values()][0], /replacement|value/);
    assert.equal(reportChangeHandoff(storage, 6, () => now).read(selected.report_key), null);
    assert.equal(handoff.read(cases[2][1]), null);
    assert.equal(handoff.read(selected.report_key).token, token);
    now += 600000;
    assert.equal(handoff.read(selected.report_key), null);
    assert.equal(data.size, 0);
    assert.equal(reportChangeHandoff(null, 5).stage(selected), false);
});

test('keeping current text removes only that candidate and makes no resolve or persistence request', async () => {
    const state = { ...researchAssistantReportChanges({ resolveChange() { throw new Error('must not resolve'); } }),
        proposalChangesBusy: () => false, saveConversation: async () => true };
    const message = { report_changes: packet() };
    assert.equal(await state.keepCurrentReportText(message, message.report_changes.suggestions[0]), true);
    assert.equal(message.report_changes.suggestions.length, 0);
});

test('report shortcuts preserve a message typed while the draft is saving', async () => {
    const previousWindow = globalThis.window;
    const store = { draft: '', requestSequence: 1, proposalChangesBusy: () => false,
        sendPrompt: () => { throw new Error('Must not overwrite the unfinished composer.'); } };
    globalThis.window = { Alpine: { store: () => store } };
    try {
        const editor = assistantReportEditor({ context: cases[0][0] });
        editor.prepare = async () => { store.draft = 'Keep this unsent message.'; return cases[0][0]; };
        assert.equal(await editor.requestChanges(), false);
        assert.equal(store.draft, 'Keep this unsent message.');
        assert.match(editor.error, /chat changed/);
    } finally { globalThis.window = previousWindow; }
});

test('ordinary report chat preserves edits typed during the save before sending', async () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const method = app.match(/    async send\(action = null, contextOverride = null\) \{[\s\S]*?\n    \},/)[0];
    const state = { ...new Function('reportContext', 'researchAssistantMessageLimit', `return ({${method}});`)(reportContext, 8000),
        draft: 'Check this saved report.', requestSequence: 1, contextPayload: () => cases[0][0],
        async prepareReportContext(context) { this.draft = 'Keep my amended message.'; return context; },
        setError(title, message) { this.error = message; } };
    assert.equal(await state.send(), null);
    assert.equal(state.draft, 'Keep my amended message.');
    assert.equal(state.sendInProgress, false);
    assert.match(state.error, /message changed/);

    state.draft = 'Check this saved report.';
    state.reportContext = null;
    state.prepareReportContext = async function (context) { this.requestSequence++; return context; };
    assert.equal(await state.send(), null);
    assert.equal(state.reportContext, null, 'An old report request must not change the new chat context.');
});

test('report review panel appears only while there are wording changes to review', { timeout: 30000 }, async () => {
    const { chromium, expect } = await import('@playwright/test');
    const component = readFileSync(new URL('../../resources/views/components/research-assistant-report-changes.blade.php', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<style>[x-cloak]{display:none!important}</style><main x-data="{ messages: window.fixtureMessages }"><template x-for="message in messages"><article>${component}</article></template></main>`);
        await page.addScriptTag({ content: `window.fixtureMessages = ${JSON.stringify([
            { report_changes: packet(undefined, undefined, { suggestions: [] }) },
            { report_changes: packet() },
        ])}; document.addEventListener('alpine:init', () => Alpine.store('researchAssistant', {
            draft: '', proposalChangesBusy: () => false, proposalChangeBeforeText: suggestion => suggestion.before,
            keepCurrentReportText(message, suggestion) { message.report_changes.suggestions = []; }
        }));` });
        await page.addScriptTag({ path: fileURLToPath(new URL('../../node_modules/alpinejs/dist/cdn.js', import.meta.url)) });
        const panels = page.locator('[data-assistant-report-changes]');
        await expect(panels.nth(0)).toBeHidden();
        await expect(panels.nth(1)).toBeVisible();
        await panels.nth(1).getByRole('button', { name: 'Keep current', exact: true }).click();
        await expect(panels.nth(1)).toBeHidden();
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
