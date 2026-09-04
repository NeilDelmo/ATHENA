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

const backgroundAutoSaveConfigurations = [
    {
        attribute: 'monitoringToolAutosave',
        rootSelector: '[data-monitoring-tool-autosave]',
        formSelector: '[data-monitoring-tool-autosave-form]',
        saveMethod: 'saveMonitoringDraft',
        fingerprintMethod: 'monitoringDraftFingerprint',
        lastSavedProperty: 'lastSavedMonitoringDraft',
    },
    {
        attribute: 'narrativeProgressAutosave',
        rootSelector: '[data-narrative-progress-autosave]',
        formSelector: '[data-narrative-progress-autosave-form]',
        saveMethod: 'saveNarrativeDraft',
        fingerprintMethod: 'narrativeDraftFingerprint',
        lastSavedProperty: 'lastSavedNarrativeDraft',
    },
    {
        attribute: 'noticeToProceedAutosave',
        rootSelector: '[data-notice-to-proceed-autosave]',
        formSelector: '[data-notice-to-proceed-autosave-form]',
        saveMethod: 'saveNoticeDetails',
        fingerprintMethod: 'noticeDetailsFingerprint',
        lastSavedProperty: 'lastSavedNoticeDetails',
    },
];

export function activeProposalPaperAutoSave(dataset = {}) {
    return paperAutoSaveConfigurations.find(({ attribute }) => dataset[attribute] === 'true') || null;
}

export function activeBackgroundAutoSave(dataset = {}) {
    return backgroundAutoSaveConfigurations.find(({ attribute }) => dataset[attribute] === 'true') || null;
}

export function proposalPaperAutoSaveIsCurrent(state, form, configuration) {
    if (!state || !form || !configuration) return false;

    const fingerprint = state[configuration.fingerprintMethod];

    if (typeof fingerprint !== 'function') return false;

    return fingerprint.call(state, form) === state[configuration.lastSavedProperty];
}

export function autoSaveHasPendingChanges(state, form, configuration) {
    if (!state || !form || !configuration || state.submitting) return false;

    return state.autoSaveInFlight || !proposalPaperAutoSaveIsCurrent(state, form, configuration);
}

export function proposalPaperFormFingerprint(entries, excludedNames = []) {
    const excluded = new Set(excludedNames);

    return JSON.stringify([...entries]
        .filter(([name, value]) => !excluded.has(name) && !(
            value instanceof File
            && value.name === ''
            && value.size === 0
        ))
        .map(([name, value]) => [name, value instanceof File
            ? {
                name: value.name,
                size: value.size,
                type: value.type,
                lastModified: value.lastModified,
            }
            : value]));
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

export function autoSaveValidationMessage(payload, fallback) {
    return Object.values(payload?.errors || {}).flat().join(' ')
        || payload?.message
        || fallback;
}

export function autoSaveHasStaleVersionError(payload) {
    const errors = payload?.errors || {};

    return ['document_version', 'draft_version'].some((field) => (
        Object.prototype.hasOwnProperty.call(errors, field)
    ));
}

export async function saveProposalPaperWithDraftFallback({
    saveAsDraft,
    save,
}) {
    let result = await save(saveAsDraft);

    if (!saveAsDraft
        && result?.response?.status === 422
        && !autoSaveHasStaleVersionError(result?.payload)) {
        result = await save(true);
    }

    return result;
}
