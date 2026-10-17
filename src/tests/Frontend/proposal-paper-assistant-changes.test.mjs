import assert from 'node:assert/strict';
import test from 'node:test';
import { proposalPaperAssistantChanges } from '../../resources/js/proposal-paper-assistant-changes.js';

class Form {
    constructor(control) { this.control = control; this.documentVersion = { value: '3' }; }
    querySelector(selector) { return selector === '[name="document_version"]' ? this.documentVersion : null; }
    querySelectorAll() { return [this.control]; }
}
globalThis.HTMLFormElement = Form;

function fixture(paper = 'work-plan', overrides = {}) {
    const work = paper === 'work-plan';
    const control = { name: work ? 'entries[0][activity]' : 'items[0][purpose]', value: 'Saved text.', type: 'textarea',
        disabled: false, readOnly: false, closest: () => null, events: [], dispatchEvent(event) { this.events.push(event.type); }, focus() {} };
    const form = new Form(control);
    const config = { paperSlug: paper, proposalDraftId: 42, draftVersion: 2, finishSave: async () => true, ...overrides };
    const state = { ...proposalPaperAssistantChanges(config), $refs: { form }, $el: { dataset: { paperDirty: 'false' }, isConnected: true },
        autoSaveInFlight: false, autoSaveBlocked: false, $nextTick: async () => {},
        entries: [{ id: 7, objective: 'Keep objective.', expectedOutput: 'Keep output.', activity: 'Saved text.', months: [1, 3] }],
        items: [{ id: 9, details: 'Keep details.', purpose: 'Saved text.', quantity: 12, unit_cost: 150, account: 'Supplies' }],
        workPlanFingerprint: () => 'saved', expenseBreakdownFingerprint: () => 'saved', lastSavedWorkPlan: 'saved', lastSavedExpenseBreakdown: 'saved',
        triggerWorkPlanAutoSave() { this.scheduled = (this.scheduled || 0) + 1; }, triggerExpenseBreakdownAutoSave() { this.scheduled = (this.scheduled || 0) + 1; } };
    const packet = { id: 'change-1', paper_slug: paper, proposal_draft_id: 42, field: work ? 'entries.0.activity' : 'items.0.purpose',
        before: 'Saved text.', value: 'Clearer reviewed text.', document_version: 3, draft_version: 2 };
    return { state, packet, control, form };
}

for (const paper of ['work-plan', 'expense-breakdown']) {
    test(`${paper} applies only an existing reviewed text field and saves`, async () => {
        const { state, packet, control } = fixture(paper);
        const previous = structuredClone({ entries: state.entries, items: state.items });
        const result = await state.applyAssistantPaperChange(packet);
        assert.deepEqual(result, { applied: true, saved: true, notice: 'Reviewed text was updated and saved.', error: '' });
        if (paper === 'work-plan') previous.entries[0].activity = packet.value;
        else previous.items[0].purpose = packet.value;
        assert.deepEqual({ entries: state.entries, items: state.items }, previous);
        assert.equal(control.value, packet.value);
        assert.deepEqual(control.events, ['input']);
        assert.equal(state.scheduled, 1);
        await state.applyAssistantPaperChange(packet);
        assert.equal(state.scheduled, 1, 'A repeated Apply must not mutate or save twice');
    });
}

test('reviewed work-plan expected output maps to its existing camel-case state property', async () => {
    const { state, packet, control } = fixture();
    packet.field = 'entries.0.expected_output';
    packet.before = state.entries[0].expectedOutput;
    control.name = 'entries[0][expected_output]';
    control.value = packet.before;
    await state.applyAssistantPaperChange(packet);
    assert.equal(state.entries[0].expectedOutput, packet.value);
    assert.equal(state.entries[0].activity, 'Saved text.');
});

test('money, schedules, absent rows, markup and mismatched papers cannot be applied', async () => {
    for (const change of [{ field: 'items.0.unit_cost' }, { field: 'entries.0.months' }, { field: 'entries.20.activity' },
        { field: 'entries.1.activity' }, { value: '<script>changed</script>' }, { value: 'x'.repeat(1501) }]) {
        const { state, packet } = fixture();
        await assert.rejects(state.applyAssistantPaperChange({ ...packet, ...change }));
        assert.equal(state.entries[0].activity, 'Saved text.');
        assert.equal(state.scheduled, undefined);
    }
    const { state, packet } = fixture();
    for (const change of [{ proposal_draft_id: 99 }, { paper_slug: 'expense-breakdown' }]) {
        assert.deepEqual(await state.applyAssistantPaperChange({ ...packet, ...change }), { applied: false, saved: false });
    }
});

test('stale versions, dirty state, changed baselines and unavailable controls preserve saved text', async () => {
    for (const mutate of [({ packet }) => { packet.document_version++; }, ({ packet }) => { packet.draft_version++; },
        ({ state }) => { state.$el.dataset.paperDirty = 'true'; }, ({ state }) => { state.workPlanFingerprint = () => 'dirty'; },
        ({ state }) => { state.autoSaveInFlight = true; }, ({ state }) => { state.autoSaveBlocked = true; },
        ({ control }) => { control.readOnly = true; }, ({ control }) => { control.disabled = true; },
        ({ control }) => { control.closest = () => ({}); }, ({ control }) => { control.value = 'An unsaved edit.'; },
        ({ state }) => { state.entries[0].activity = 'Changed state.'; }]) {
        const item = fixture();
        mutate(item);
        await assert.rejects(item.state.applyAssistantPaperChange(item.packet));
        assert.equal(item.state.scheduled, undefined);
    }
});

test('failed saving reports applied editor text honestly without repeating the change', async () => {
    const { state, packet } = fixture('expense-breakdown', { finishSave: async () => false });
    const result = await state.applyAssistantPaperChange(packet);
    assert.equal(result.applied, true);
    assert.equal(result.saved, false);
    assert.match(result.error, /Saving did not finish/);
    assert.equal(state.items[0].purpose, packet.value);
    assert.equal(state.proposalChangeApplying, false);
    assert.equal((await state.applyAssistantPaperChange(packet)).saved, false);
});

test('explicit Apply events bind the correct paper and proposal before being handled', async () => {
    const { state, packet } = fixture();
    const other = { detail: { ...packet, paper_slug: 'expense-breakdown' } };
    state.handleAssistantPaperChange(other);
    assert.equal(other.detail.handled, undefined);
    const event = { detail: packet };
    state.handleAssistantPaperChange(event);
    assert.equal(packet.handled, true);
    assert.equal((await packet.promise).applied, true);
});

test('handoff resolves authoritative text and clears only the matching destination', async () => {
    const selection = { proposal_draft_id: 42, token: 'a'.repeat(64), suggestion_id: 'change-1' };
    const cleared = [], resolved = [];
    let authoritative;
    const { state, packet } = fixture('work-plan', { changeHandoff: { read: () => selection, clear: (id) => cleared.push(id) },
        resolveChange: async (candidate) => { resolved.push(candidate); return authoritative; } });
    authoritative = { ...packet, paper_slug: 'expense-breakdown' };
    await state.applyPendingAssistantPaperChange();
    assert.deepEqual(cleared, []);
    authoritative = packet;
    assert.equal((await state.applyPendingAssistantPaperChange()).saved, true);
    assert.deepEqual(cleared, [42]);
    assert.deepEqual(resolved, [selection, selection]);
});

test('handoff skips another paper and keeps current text when saving or resolution fails', async () => {
    let reads = 0, resolutions = 0, clears = 0;
    const { state } = fixture('work-plan', { finishSave: async () => false,
        changeHandoff: { read: () => { reads++; return { paper_slug: reads === 1 ? 'expense-breakdown' : 'work-plan' }; }, clear: () => clears++ },
        resolveChange: async () => { resolutions++; throw new Error('Expired suggestion.'); } });
    await state.applyPendingAssistantPaperChange();
    assert.equal(state.proposalChangeError, '');
    await state.applyPendingAssistantPaperChange();
    assert.match(state.proposalChangeError, /Finish saving/);
    assert.equal(resolutions, 0);
    assert.equal(clears, 0);
    assert.equal(state.entries[0].activity, 'Saved text.');
});
