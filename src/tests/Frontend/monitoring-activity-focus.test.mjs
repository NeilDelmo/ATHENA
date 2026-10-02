import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('monitoringToolForm',");
const source = app.slice(start, app.indexOf('Alpine.data(', start + 1));

function createState(reducedMotion = false) {
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
    const state = factory();
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
