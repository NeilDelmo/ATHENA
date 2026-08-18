import assert from 'node:assert/strict';
import test from 'node:test';
import {
    activeProposalPaperAutoSave,
    finishProposalPaperAutoSave,
    proposalPaperAutoSaveIsCurrent,
    saveProposalPaperWithDraftFallback,
} from '../../resources/js/proposal-paper-autosave.js';

test('every proposal paper editor has a matching shared autosave configuration', () => {
    const attributes = [
        'detailedProposalAutosave',
        'workPlanAutosave',
        'lineItemBudgetAutosave',
        'expenseBreakdownAutosave',
        'curriculumVitaeAutosave',
        'projectDetailsAutosave',
    ];

    attributes.forEach((attribute) => {
        assert.ok(activeProposalPaperAutoSave({ [attribute]: 'true' }));
    });
});

test('exit save waits for an in-flight older save and persists the newest paper state', async () => {
    const configuration = activeProposalPaperAutoSave({ lineItemBudgetAutosave: 'true' });
    const form = {};
    let now = 0;
    let saveCalls = 0;
    const state = {
        autoSaveInFlight: true,
        lastSavedLineItemBudget: 'older form state',
        lineItemBudgetFingerprint: () => 'newest form state',
        async saveLineItemBudgetNow() {
            saveCalls += 1;
            this.autoSaveInFlight = false;
            this.lastSavedLineItemBudget = 'newest form state';
        },
    };

    const saved = await finishProposalPaperAutoSave({
        state,
        form,
        configuration,
        timeoutMs: 500,
        now: () => now,
        wait: async () => {
            now += 50;
            state.autoSaveInFlight = false;
        },
    });

    assert.equal(saved, true);
    assert.equal(saveCalls, 1);
    assert.equal(proposalPaperAutoSaveIsCurrent(state, form, configuration), true);
});

test('exit save does not repeatedly retry one invalid unchanged form', async () => {
    const configuration = activeProposalPaperAutoSave({ expenseBreakdownAutosave: 'true' });
    const form = {};
    let now = 0;
    let saveCalls = 0;
    const state = {
        autoSaveInFlight: false,
        lastSavedExpenseBreakdown: 'saved form state',
        expenseBreakdownFingerprint: () => 'invalid current form state',
        async saveExpenseBreakdownNow() {
            saveCalls += 1;
        },
    };

    const saved = await finishProposalPaperAutoSave({
        state,
        form,
        configuration,
        timeoutMs: 100,
        now: () => now,
        wait: async () => {
            now += 50;
        },
    });

    assert.equal(saved, false);
    assert.equal(saveCalls, 1);
});

test('a complete-paper save retries as a draft only after server validation rejects it', async () => {
    const saveModes = [];
    const result = await saveProposalPaperWithDraftFallback({
        saveAsDraft: false,
        save: async (saveAsDraft) => {
            saveModes.push(saveAsDraft);

            return saveAsDraft
                ? { response: { status: 200 }, payload: { saved_as_draft: true } }
                : { response: { status: 422 }, payload: { errors: { title: ['Required'] } } };
        },
    });

    assert.deepEqual(saveModes, [false, true]);
    assert.equal(result.payload.saved_as_draft, true);
});

test('a stale paper save does not retry against the same older document version', async () => {
    const saveModes = [];
    const result = await saveProposalPaperWithDraftFallback({
        saveAsDraft: false,
        save: async (saveAsDraft) => {
            saveModes.push(saveAsDraft);

            return {
                response: { status: 422 },
                payload: { errors: { document_version: ['A newer saved version is available.'] } },
            };
        },
    });

    assert.deepEqual(saveModes, [false]);
    assert.equal(result.response.status, 422);
});

test('a complete-paper save keeps the completed result when the server accepts it', async () => {
    const saveModes = [];
    const result = await saveProposalPaperWithDraftFallback({
        saveAsDraft: false,
        save: async (saveAsDraft) => {
            saveModes.push(saveAsDraft);

            return {
                response: { status: 200 },
                payload: { saved_as_draft: false },
            };
        },
    });

    assert.deepEqual(saveModes, [false]);
    assert.equal(result.payload.saved_as_draft, false);
});
