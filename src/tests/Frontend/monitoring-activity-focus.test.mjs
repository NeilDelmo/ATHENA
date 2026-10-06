import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('monitoringToolForm',");
const source = app.slice(start, app.indexOf('Alpine.data(', start + 1));

function createState(reducedMotion = false, config = {}) {
    let factory;
    const events = [];
    const activity = {
        querySelector: () => ({ focus: options => events.push(['focus', options.preventScroll]) }),
        scrollIntoView: options => events.push(['scroll', options.behavior, options.block]),
    };
    runInNewContext(source, {
        Alpine: { data: (_, callback) => { factory = callback; } },
        documentPreviewForm: () => ({}),
        window: { matchMedia: () => ({ matches: reducedMotion }) },
    });
    const state = factory(config);
    const ticks = [];
    state.$nextTick = callback => ticks.push(callback);
    state.$refs = { activityList: { querySelector: () => activity } };
    state.triggerMonitoringDraftAutoSave = () => events.push(['save']);
    return { state, events, flush: () => ticks.splice(0).forEach(callback => callback()) };
}

test('adding an activity waits for rendering, saves, focuses, and reveals the new entry', () => {
    const { state, events, flush } = createState();
    state.entries[0].activity = 'Existing activity';
    state.addEntry();
    assert.equal(state.entries.length, 2);
    assert.equal(state.entries[0].activity, 'Existing activity');
    assert.equal(state.entries[1].activity, '');
    assert.deepEqual(events, []);
    flush();
    assert.deepEqual(events, [['save'], ['focus', true], ['scroll', 'smooth', 'start']]);
});

test('revealing a new activity respects reduced motion', () => {
    const { state, events, flush } = createState(true);
    state.addEntry();
    flush();
    assert.deepEqual(events.at(-1), ['scroll', 'instant', 'start']);
});

test('the activity limit does not save or move focus', () => {
    const { state, events, flush } = createState();
    state.entries = Array.from({ length: 11 }, () => ({ activity: 'Existing' }));
    state.addEntry();
    flush();
    assert.equal(state.entries.length, 11);
    assert.deepEqual(events, []);
});

test('approved activity warnings begin after the target date and clear at full activity completion', () => {
    const { state } = createState(false, { scheduleCheckedOn: '2026-02-15' });
    const entry = { source_work_plan_index: 0, target_completion_date: '2026-02-14', completion: 50 };
    assert.equal(state.isEntryOverdue(entry), true);
    entry.completion = 100;
    assert.equal(state.isEntryOverdue(entry), false);
    entry.completion = 0;
    entry.target_completion_date = '2026-02-15';
    assert.equal(state.isEntryOverdue(entry), false);
    entry.target_completion_date = '2026-06-14';
    assert.equal(state.isEntryOverdue(entry), false, 'A future quarter draft does not move the alert date forward');
    entry.source_work_plan_index = undefined;
    entry.target_completion_date = '2026-02-14';
    assert.equal(state.isEntryOverdue(entry), false, 'Only approved Work Plan entries use this schedule alert');
});
