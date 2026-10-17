import { safeAssistantSourceUrl } from './research-assistant-markdown.js';

const reportTypes = new Set(['progress-report', 'monitoring-tool', 'terminal-report', 'comment-response', 'college-quarterly', 'rms-quarterly']);
const record = (value) => value && typeof value === 'object' && !Array.isArray(value);
const identifier = (value) => /^[1-9]\d*$/.test(String(value)) && Number.isSafeInteger(Number(value));
const tokenIsValid = (value) => typeof value === 'string' && /^[a-z0-9_-]{64}$/i.test(value);
const idIsValid = (value) => (typeof value === 'string' && /^[a-z0-9_-]{1,80}$/i.test(value))
    || (typeof value === 'number' && identifier(value));

export function reportContext(value) {
    if (!record(value) || !reportTypes.has(value.report_type)) return null;
    const context = { report_type: value.report_type };
    if (['college-quarterly', 'rms-quarterly'].includes(value.report_type)) {
        if (!identifier(value.report_id)) return null;
        context.report_id = Number(value.report_id);
    } else {
        if (!identifier(value.topic_id)) return null;
        context.topic_id = Number(value.topic_id);
        if (value.report_type === 'monitoring-tool') {
            if (value.source_key !== 'new' && !/^revision:[1-9]\d*$/.test(value.source_key)) return null;
            context.source_key = value.source_key;
        }
    }
    return context;
}

export function reportKey(value) {
    const context = reportContext(value);
    if (!context) return '';
    return context.report_id ? `${context.report_type}:${context.report_id}`
        : `${context.report_type}:topic:${context.topic_id}${context.source_key ? `:${context.source_key}` : ''}`;
}

export function reportChangeFieldLimit(type, field) {
    if (typeof field !== 'string') return 0;
    if (['progress-report', 'terminal-report'].includes(type)) {
        if (['introduction', 'rationale', 'methodology', 'results_discussion'].includes(field)) return 30000;
        if (/^accomplishments\.([0-9]|[1-9][0-9]{1,2})\.actual$/.test(field)) return 2000;
        if (type === 'terminal-report') {
            if (/^terminal_data\.(abstract|literature_review|conclusions|recommendations|bibliography)$/.test(field)) return 30000;
            if (/^terminal_data\.tables\.([0-9]|[12][0-9])\.caption$/.test(field)) return 300;
            if (/^(photo_caption_([1-9]|[12][0-9]|30)|cover_image_caption)$/.test(field)) return 200;
        }
    }
    if (type === 'monitoring-tool') {
        if (/^work_plan\.([0-9]|10)\.(actual_accomplishment|findings)$/.test(field)) return 500;
        if (/^budget_utilization\.[0-2]\.(details|remarks)$/.test(field)) return 300;
    }
    if (type === 'comment-response' && /^feedback_responses\.[a-zA-Z0-9_-]{1,100}\.response$/.test(field)) return 5000;
    if (type === 'college-quarterly') {
        if (field === 'other_accomplishments') return 20000;
        if (/^ip_assets\.([0-9]|[1-9][0-9])\.remarks$/.test(field)) return 2000;
        if (/^saved_evidence_captions\.[a-f0-9]{64}$/i.test(field)) return 1000;
    }
    if (type === 'rms-quarterly' && /^row_details\.ip-[a-zA-Z0-9_-]{1,160}\.remarks$/.test(field)) return 2000;
    return 0;
}

export function normalizeAssistantReportChanges(packet) {
    if (!record(packet)) return null;
    const context = reportContext(packet.context);
    if (!context || packet.report_type !== context.report_type || packet.report_key !== reportKey(context)) return null;
    const ids = new Set();
    const tokens = new Set();
    const suggestions = [];
    for (const candidate of Array.isArray(packet.suggestions) ? packet.suggestions : []) {
        const limit = reportChangeFieldLimit(context.report_type, candidate?.field);
        const format = candidate?.format || 'plain';
        if (!record(candidate) || !limit || !idIsValid(candidate.id) || !tokenIsValid(candidate.token)
            || typeof candidate.before !== 'string' || candidate.before.length > limit
            || typeof candidate.value !== 'string' || !candidate.value.trim() || candidate.value.length > limit
            || !['plain', 'html'].includes(format)
            || (format === 'html' && candidate.field !== 'terminal_data.literature_review')
            || ids.has(String(candidate.id)) || tokens.has(candidate.token)) continue;
        ids.add(String(candidate.id));
        tokens.add(candidate.token);
        suggestions.push({ id: String(candidate.id), token: candidate.token, field: candidate.field,
            before: candidate.before, value: candidate.value, format,
            label: String(candidate.label || '').slice(0, 160), reason: String(candidate.reason || '').slice(0, 1000) });
        if (suggestions.length === 3) break;
    }
    return { report_key: packet.report_key, report_type: context.report_type, context,
        title: String(packet.title || '').slice(0, 300), editor_url: typeof packet.editor_url === 'string'
            ? safeAssistantSourceUrl(packet.editor_url.slice(0, 2048)) : '', suggestions };
}

export function reportChangeHandoff(storage, accountId, now = () => Date.now()) {
    const key = (report) => `athena:report-change:${accountId}:${report}`;
    return {
        stage(selection) {
            if (!identifier(accountId) || !record(selection) || !tokenIsValid(selection.token)
                || !idIsValid(selection.suggestion_id) || !reportTypes.has(selection.report_key?.split(':')[0])) return false;
            try {
                storage.setItem(key(selection.report_key), JSON.stringify({ report_key: selection.report_key,
                    token: selection.token, suggestion_id: String(selection.suggestion_id), created_at: now() }));
                return true;
            } catch { return false; }
        },
        read(report) {
            if (!identifier(accountId)) return null;
            try {
                const selection = JSON.parse(storage.getItem(key(report)) || 'null');
                if (!selection) return null;
                if (selection.report_key !== report || !tokenIsValid(selection.token) || !idIsValid(selection.suggestion_id)
                    || !Number.isSafeInteger(selection.created_at) || now() < selection.created_at
                    || now() - selection.created_at >= 600000) { this.clear(report); return null; }
                return { report_key: report, token: selection.token, suggestion_id: selection.suggestion_id };
            } catch { this.clear(report); return null; }
        },
        clear(report) { try { storage.removeItem(key(report)); } catch { /* Blocked storage must leave the editor usable. */ } },
    };
}

export function currentPageReportContext() {
    const widgets = [...document.querySelectorAll('[data-assistant-report-context]')]
        .filter((node) => !node.closest('[inert]'));
    if (widgets.length !== 1) return null;
    try { return reportContext(JSON.parse(widgets[0].dataset.assistantReportContext)); } catch { return null; }
}

export function researchAssistantReportChanges(dependencies) {
    return {
        reportContext: null, reportChangesOperationPending: false,
        async prepareReportContext(context, reviewOnly = false) {
            const normalized = reportContext(context);
            if (!normalized) return context;
            const detail = { report_key: reportKey(normalized), review_only: reviewOnly, handled: false, promise: null };
            window.dispatchEvent(new CustomEvent('athena-prepare-report', { detail }));
            return detail.handled ? await detail.promise : normalized;
        },
        async keepCurrentReportText(message, suggestion) {
            if (this.proposalChangesBusy() || !message?.report_changes || suggestion?.applied) return false;
            message.report_changes.suggestions = message.report_changes.suggestions.filter((item) => item.id !== suggestion.id);
            await this.saveConversation();
            return true;
        },
        async refreshReportChanges(message) {
            if (this.proposalChangesBusy() || this.draft.trim() || !message?.report_changes) return false;
            this.reportContext = reportContext(message.report_changes.context);
            return this.sendPrompt('Read the current report and prepare updated suggestions for my review. Preserve all recorded facts and citations.',
                { type: 'suggest_report_changes' }, this.reportContext);
        },
        async applyReportChange(message, suggestion) {
            const packet = normalizeAssistantReportChanges(message?.report_changes);
            if (!packet || this.proposalChangesBusy() || suggestion?.applied || suggestion?.stale) return false;
            const selected = packet.suggestions.find((item) => item.id === String(suggestion.id));
            if (!selected) return false;
            this.reportChangesOperationPending = true;
            suggestion.is_working = true;
            suggestion.error = '';
            const sequence = this.requestSequence;
            try {
                await this.prepareReportContext(packet.context);
                const selection = { report_key: packet.report_key, token: selected.token, suggestion_id: selected.id };
                const candidate = await dependencies.resolveChange(selection);
                if (sequence !== this.requestSequence || !this.messages.includes(message)) return false;
                const detail = { ...candidate, handled: false, promise: null };
                window.dispatchEvent(new CustomEvent('athena-apply-report-change', { detail }));
                if (detail.handled && detail.promise) {
                    const result = await detail.promise;
                    suggestion.applied = result.applied === true;
                    suggestion.saved = result.saved === true;
                    suggestion.notice = result.notice || '';
                    if (suggestion.applied) message.report_changes.suggestions.filter((item) => item.id !== suggestion.id && !item.applied)
                        .forEach((item) => { item.stale = true; item.error = 'The report changed. Request updated suggestions.'; });
                    await this.saveConversation();
                    return suggestion.applied;
                }
                const url = dependencies.safeUrl(candidate.editor_url);
                if (!url) throw new Error('Open the matching report and request a new suggestion.');
                if (!await dependencies.finishNavigation()) return false;
                if (this.hasConversation() && !await this.saveConversation()) return false;
                if (sequence !== this.requestSequence) return false;
                if (!dependencies.handoff().stage(selection)) throw new Error('Open the matching report to apply this suggestion; browser storage is unavailable.');
                window.location.assign(url);
                return true;
            } catch (error) {
                suggestion.error = error.message || 'This suggestion could not be applied.';
                if ([409, 422].includes(error.status)) suggestion.stale = true;
                return false;
            } finally {
                suggestion.is_working = false;
                this.reportChangesOperationPending = false;
            }
        },
    };
}
