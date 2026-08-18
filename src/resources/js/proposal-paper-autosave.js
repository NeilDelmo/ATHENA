const paperAutoSaveConfigurations = [
    {
        attribute: 'detailedProposalAutosave',
        saveMethod: 'saveDetailedProposalNow',
        fingerprintMethod: 'detailedProposalFingerprint',
        lastSavedProperty: 'lastSavedDetailedProposal',
    },
    {
        attribute: 'workPlanAutosave',
        saveMethod: 'saveWorkPlanNow',
        fingerprintMethod: 'workPlanFingerprint',
        lastSavedProperty: 'lastSavedWorkPlan',
    },
    {
        attribute: 'lineItemBudgetAutosave',
        saveMethod: 'saveLineItemBudgetNow',
        fingerprintMethod: 'lineItemBudgetFingerprint',
        lastSavedProperty: 'lastSavedLineItemBudget',
    },
    {
        attribute: 'expenseBreakdownAutosave',
        saveMethod: 'saveExpenseBreakdownNow',
        fingerprintMethod: 'expenseBreakdownFingerprint',
        lastSavedProperty: 'lastSavedExpenseBreakdown',
    },
    {
        attribute: 'curriculumVitaeAutosave',
        saveMethod: 'saveCurriculumVitaeNow',
        fingerprintMethod: 'curriculumVitaeFingerprint',
        lastSavedProperty: 'lastSavedCurriculumVitae',
    },
    {
        attribute: 'projectDetailsAutosave',
        saveMethod: 'saveProjectDetailsNow',
        fingerprintMethod: 'projectDetailsFingerprint',
        lastSavedProperty: 'lastSavedProjectDetails',
    },
];

export function activeProposalPaperAutoSave(dataset = {}) {
    return paperAutoSaveConfigurations.find(({ attribute }) => dataset[attribute] === 'true') || null;
}

export function proposalPaperAutoSaveIsCurrent(state, form, configuration) {
    if (!state || !form || !configuration) return false;

    const fingerprint = state[configuration.fingerprintMethod];

    if (typeof fingerprint !== 'function') return false;

    return fingerprint.call(state, form) === state[configuration.lastSavedProperty];
}

export async function finishProposalPaperAutoSave({
    state,
    form,
    configuration,
    timeoutMs = 15000,
    wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds)),
    now = () => Date.now(),
}) {
    if (!state
        || !form
        || !configuration
        || typeof state[configuration.saveMethod] !== 'function'
        || typeof state[configuration.fingerprintMethod] !== 'function') {
        return false;
    }

    const deadline = now() + timeoutMs;
    let lastAttemptedFingerprint = null;

    while (now() < deadline) {
        if (state.autoSaveBlocked) return false;

        if (!state.autoSaveInFlight && proposalPaperAutoSaveIsCurrent(state, form, configuration)) {
            return true;
        }

        if (!state.autoSaveInFlight) {
            const fingerprint = state[configuration.fingerprintMethod].call(state, form);

            if (fingerprint === lastAttemptedFingerprint) return false;

            lastAttemptedFingerprint = fingerprint;
            await state[configuration.saveMethod]();
        }

        await wait(50);
    }

    return !state.autoSaveInFlight && proposalPaperAutoSaveIsCurrent(state, form, configuration);
}

export async function saveProposalPaperWithDraftFallback({
    saveAsDraft,
    save,
}) {
    let result = await save(saveAsDraft);

    const hasStaleDocumentVersionError = Object.prototype.hasOwnProperty.call(
        result?.payload?.errors || {},
        'document_version',
    );

    if (!saveAsDraft && result?.response?.status === 422 && !hasStaleDocumentVersionError) {
        result = await save(true);
    }

    return result;
}
