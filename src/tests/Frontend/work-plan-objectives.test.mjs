import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('proposalDraftWorkPlan',");
const source = app.slice(start, app.indexOf('Alpine.data(', start + 1));

function editor(config) {
    let factory;
    runInNewContext(source, {
        Alpine: { data: (_, callback) => { factory = callback; } },
        proposalPaperPreviewWorkspace: () => ({}),
    });
    const state = factory(config);
    state.$refs = { form: { querySelectorAll: () => [] } };
    state.$nextTick = () => {};
    state.init();
    return state;
}

test('linked Work Plan rows retain activities and cannot be added or removed independently', () => {
    const state = editor({ objectivesLinked: true, initialEntries: [
        { objective: 'Baseline', activity: 'Survey', expected_output: 'Dataset', months: [1] },
        { objective: 'Evaluation', activity: 'Analyze', expected_output: 'Report', months: [2] },
    ] });
    assert.equal(state.entries[0].activity, 'Survey');
    assert.equal(state.entries[1].objective, 'Evaluation');
    assert.equal(state.canAddEntry(), false);
    state.addEntry();
    state.removeEntry(0);
    assert.equal(state.entries.length, 2);
    assert.equal(state.isComplete(), true);
});

test('missing proposal objectives leave an empty Work Plan and explain what to complete', () => {
    const state = editor({ objectivesLinked: true });
    assert.equal(state.entries.length, 0);
    assert.equal(state.isComplete(), false);
    assert.equal(state.validateForm(), false);
    assert.match(state.validationMessage, /specific objectives in the Detailed Proposal/);
});

test('the independent Work Plan builder keeps its editable row behavior', () => {
    const state = editor({});
    assert.equal(state.entries.length, 1);
    assert.equal(state.canAddEntry(), true);
    state.addEntry();
    state.removeEntry(0);
    assert.equal(state.entries.length, 1);
});

test('objectives can share months and independent rows are not limited by project duration', () => {
    const state = editor({ durationMonths: 1, initialEntries: [
        { objective: 'Baseline', activity: 'Survey', expected_output: 'Dataset', months: [1] },
        { objective: 'Evaluation', activity: 'Analyze', expected_output: 'Report', months: [1] },
    ] });
    assert.equal(state.isMonthSelectable(1, 1), true);
    assert.equal(state.isComplete(), true);
    assert.equal(state.validateForm(), true);
    assert.equal(state.canAddEntry(), true);
    assert.equal(state.isMonthSelectable(0, 0), false);
    assert.equal(state.isMonthSelectable(0, 2), false);
});

test('researchers can deliberately reload proposal methods without changing outputs or months', () => {
    const state = editor({ objectivesLinked: true,
        proposalActivities: { Baseline: 'Conduct interviews\nValidate dataset' },
        initialEntries: [{ objective: 'Baseline', activity: 'Custom survey', expected_output: 'Dataset', months: [1] }],
    });
    state.triggerWorkPlanAutoSave = () => {};
    state.markProposalPreviewStale = () => {};
    assert.equal(state.entries[0].activity, 'Custom survey');
    state.useProposalMethods(state.entries[0]);
    assert.equal(state.entries[0].activity, 'Conduct interviews\nValidate dataset');
    assert.equal(state.entries[0].expectedOutput, 'Dataset');
    assert.deepEqual(Array.from(state.entries[0].months), [1]);
});
