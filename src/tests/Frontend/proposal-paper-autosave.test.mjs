import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    activeBackgroundAutoSave,
    activeProposalPaperAutoSave,
    autoSaveHasPendingChanges,
    autoSaveHasStaleVersionError,
    autoSaveValidationMessage,
    finishProposalPaperAutoSave,
    formControlsAreComplete,
    proposalPaperAutoSaveIsCurrent,
    proposalPaperFormFingerprint,
    saveProposalPaperWithDraftFallback,
} from '../../resources/js/proposal-paper-autosave.js';

test('form completion updates from the current native control validity', () => {
    const requiredField = { disabled: false, checkValidity: () => false };
    const disabledField = { disabled: true, checkValidity: () => false };

    assert.equal(formControlsAreComplete([requiredField, disabledField]), false);

    requiredField.checkValidity = () => true;

    assert.equal(formControlsAreComplete([requiredField, disabledField]), true);
    assert.equal(formControlsAreComplete([]), false);
});

test('expense download readiness refreshes as the user edits without reopening the page', () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const componentStart = app.indexOf("Alpine.data('proposalDraftExpenseBreakdown'");
    const componentEnd = app.indexOf("Alpine.data('proposalDraftCurriculumVitae'", componentStart);
    const component = app.slice(componentStart, componentEnd);

    assert.match(component, /formComplete: false/);
    assert.match(component, /return this\.\$el\.dataset\.paperProjectDetailsComplete === 'true' && this\.formComplete/);
    assert.match(component, /this\.formComplete = formControlsAreComplete\(fields\)/);
    assert.match(component, /this\.\$nextTick\(\(\) => this\.refreshExpenseBreakdownCompletion\(\)\)/);
});

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

test('every monitoring and notice editor has a matching shared autosave configuration', () => {
    const attributes = [
        'monitoringToolAutosave',
        'narrativeProgressAutosave',
        'noticeToProceedAutosave',
    ];

    attributes.forEach((attribute) => {
        const configuration = activeBackgroundAutoSave({ [attribute]: 'true' });

        assert.ok(configuration);
        assert.match(configuration.rootSelector, /^\[data-/);
        assert.match(configuration.formSelector, /^\[data-/);
    });
});

test('pending-change detection covers changed, in-flight, current, and intentional-submit states', () => {
    const configuration = activeBackgroundAutoSave({ monitoringToolAutosave: 'true' });
    const form = {};
    const state = {
        autoSaveInFlight: false,
        submitting: false,
        lastSavedMonitoringDraft: 'saved',
        monitoringDraftFingerprint: () => 'changed',
    };

    assert.equal(autoSaveHasPendingChanges(state, form, configuration), true);

    state.monitoringDraftFingerprint = () => 'saved';
    assert.equal(autoSaveHasPendingChanges(state, form, configuration), false);

    state.autoSaveInFlight = true;
    assert.equal(autoSaveHasPendingChanges(state, form, configuration), true);

    state.submitting = true;
    assert.equal(autoSaveHasPendingChanges(state, form, configuration), false);
});

test('paper fingerprints ignore empty file controls without hiding selected-file changes', () => {
    const emptyFileAtFirstRead = new File([], '', { lastModified: 100 });
    const emptyFileAtSecondRead = new File([], '', { lastModified: 200 });
    const selectedFile = new File(['image'], 'methodology.png', {
        type: 'image/png',
        lastModified: 300,
    });

    const first = proposalPaperFormFingerprint([
        ['title', 'Fruit drop detection'],
        ['methodology_image', emptyFileAtFirstRead],
    ]);
    const second = proposalPaperFormFingerprint([
        ['title', 'Fruit drop detection'],
        ['methodology_image', emptyFileAtSecondRead],
    ]);
    const withSelectedFile = proposalPaperFormFingerprint([
        ['title', 'Fruit drop detection'],
        ['methodology_image', selectedFile],
    ]);

    assert.equal(first, second);
    assert.notEqual(first, withSelectedFile);
});

test('all nine autosave editors finish pending and in-flight saves and block unsafe exits', async () => {
    const configurations = [
        activeProposalPaperAutoSave({ detailedProposalAutosave: 'true' }),
        activeProposalPaperAutoSave({ workPlanAutosave: 'true' }),
        activeProposalPaperAutoSave({ lineItemBudgetAutosave: 'true' }),
        activeProposalPaperAutoSave({ expenseBreakdownAutosave: 'true' }),
        activeProposalPaperAutoSave({ curriculumVitaeAutosave: 'true' }),
        activeProposalPaperAutoSave({ projectDetailsAutosave: 'true' }),
        activeBackgroundAutoSave({ monitoringToolAutosave: 'true' }),
        activeBackgroundAutoSave({ narrativeProgressAutosave: 'true' }),
        activeBackgroundAutoSave({ noticeToProceedAutosave: 'true' }),
    ];

    for (const configuration of configurations) {
        const form = {};
        let now = 0;
        let saveCalls = 0;
        const state = {
            autoSaveInFlight: true,
            autoSaveBlocked: false,
            [configuration.lastSavedProperty]: 'older state',
            [configuration.fingerprintMethod]: () => 'newest state',
            async [configuration.saveMethod]() {
                saveCalls += 1;
                this.autoSaveInFlight = false;
                this[configuration.lastSavedProperty] = 'newest state';
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

        assert.equal(saved, true, configuration.attribute);
        assert.equal(saveCalls, 1, configuration.attribute);
        assert.equal(await finishProposalPaperAutoSave({
            state,
            form,
            configuration,
            timeoutMs: 100,
            now: () => now,
            wait: async () => {
                now += 50;
            },
        }), true, configuration.attribute);
        assert.equal(saveCalls, 1, configuration.attribute);

        state.autoSaveBlocked = true;
        state[configuration.lastSavedProperty] = 'older state';
        saveCalls = 0;

        assert.equal(await finishProposalPaperAutoSave({
            state,
            form,
            configuration,
            timeoutMs: 100,
            now: () => now,
            wait: async () => {
                now += 50;
            },
        }), false, configuration.attribute);
        assert.equal(saveCalls, 0, configuration.attribute);

        state.autoSaveBlocked = false;
        state[configuration.saveMethod] = async () => {
            saveCalls += 1;
        };
        now = 0;

        assert.equal(await finishProposalPaperAutoSave({
            state,
            form,
            configuration,
            timeoutMs: 100,
            now: () => now,
            wait: async () => {
                now += 50;
            },
        }), false, configuration.attribute);
        assert.equal(saveCalls, 1, configuration.attribute);
    }
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

test('autosave validation helpers preserve messages and detect both collaboration version fields', () => {
    assert.equal(autoSaveValidationMessage({
        errors: { document_version: ['A newer paper is available.'] },
    }, 'Fallback'), 'A newer paper is available.');
    assert.equal(autoSaveValidationMessage({}, 'Fallback'), 'Fallback');
    assert.equal(autoSaveHasStaleVersionError({ errors: { document_version: ['Stale'] } }), true);
    assert.equal(autoSaveHasStaleVersionError({ errors: { draft_version: ['Stale'] } }), true);
    assert.equal(autoSaveHasStaleVersionError({ errors: { title: ['Required'] } }), false);
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
    assert.deepEqual(result.completionErrors, { title: ['Required'] });
});

test('a stale paper save does not retry against an older paper or project-details version', async () => {
    for (const versionField of ['document_version', 'draft_version']) {
        const saveModes = [];
        const result = await saveProposalPaperWithDraftFallback({
            saveAsDraft: false,
            save: async (saveAsDraft) => {
                saveModes.push(saveAsDraft);

                return {
                    response: { status: 422 },
                    payload: { errors: { [versionField]: ['A newer saved version is available.'] } },
                };
            },
        });

        assert.deepEqual(saveModes, [false]);
        assert.equal(result.response.status, 422);
        assert.equal(result.completionErrors, null);
    }
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
    assert.equal(result.completionErrors, null);
});
