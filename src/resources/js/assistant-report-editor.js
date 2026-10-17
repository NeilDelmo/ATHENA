import { activeBackgroundAutoSave, finishProposalPaperAutoSave, proposalPaperFormFingerprint } from './proposal-paper-autosave.js';
import { escapeAssistantHtml } from './research-assistant-markdown.js';
import { reportContext, reportKey, reportChangeFieldLimit } from './research-assistant-report-changes.js';

const fieldName = (field) => field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
const plainHtml = (text) => text.replace(/\r\n?/g, '\n').trim().split(/\n\s*\n/).filter(Boolean)
    .map((paragraph) => `<p>${escapeAssistantHtml(paragraph).replaceAll('\n', '<br>')}</p>`).join('');
const citationSignature = (html) => {
    const template = document.createElement('template');
    template.innerHTML = html;
    return JSON.stringify([...template.content.querySelectorAll('[data-proposal-citation], [data-proposal-managed-reference]')]
        .map(node => JSON.stringify([node.hasAttribute('data-proposal-citation') ? 'citation' : 'reference',
            node.getAttribute('data-proposal-citation') ?? node.getAttribute('data-proposal-managed-reference'),
            node.getAttribute('data-proposal-locator') || '', node.textContent])).sort());
};

export function assistantReportEditor(config = {}, dependencies = {}) {
    return {
        context: reportContext(config.context), editable: config.editable !== false,
        notice: '', error: '', working: false, submitting: false, initialFingerprint: '', initialNavigationFingerprint: '',
        init() {
            this.form()?.addEventListener('submit', (event) => queueMicrotask(() => {
                if (!event.defaultPrevented) this.submitting = true;
            }));
            this.$nextTick(async () => {
                this.initialFingerprint = this.fingerprint();
                this.initialNavigationFingerprint = this.fingerprint(true);
                const selection = dependencies.handoff?.().read(reportKey(this.context));
                if (!selection || !this.context || !this.editable) return;
                dependencies.handoff().clear(selection.report_key);
                this.working = true;
                try {
                    const candidate = await dependencies.resolveChange(selection);
                    const result = await this.applyChange(candidate);
                    this.notice = result.notice || '';
                    this.error = result.error || '';
                } catch (error) { this.error = error.message || 'Request a new suggestion in Athena.'; }
                finally { this.working = false; }
            });
        },
        form() { return this.$el.closest('form'); },
        fingerprint(includeResponses = false) {
            const form = this.form();
            if (!form) return '';
            const entries = [...new FormData(form)].filter(([name]) => !['_token', '_method', 'draft_version'].includes(name)
                && !(!includeResponses && this.context?.report_type === 'comment-response' && name.startsWith('feedback_responses[')));
            return proposalPaperFormFingerprint(entries);
        },
        autoSave() {
            const root = this.form()?.closest('[data-monitoring-tool-autosave], [data-narrative-progress-autosave]');
            return root ? { state: window.Alpine.$data(root), form: this.form(), configuration: activeBackgroundAutoSave(root.dataset) } : null;
        },
        async prepare(reviewOnly = false) {
            if (this.submitting || (!this.editable && !reviewOnly) || !this.context || !this.form()?.isConnected || this.form().closest('[inert]')) {
                throw new Error('Save an editable draft before asking Athena to revise this report.');
            }
            const autoSave = this.autoSave();
            if (!autoSave && config.unsaved && ['college-quarterly', 'rms-quarterly'].includes(this.context.report_type)) {
                throw new Error('Resolve the validation errors and save your report, then reopen the saved report before requesting suggestions.');
            }
            if (autoSave) {
                if (autoSave.state.submitting || !await finishProposalPaperAutoSave(autoSave)) {
                    throw new Error('Finish saving your current edits before using this suggestion.');
                }
            } else if (this.fingerprint() !== this.initialFingerprint) {
                throw new Error('Save your current report edits first, then request updated suggestions.');
            }
            const context = { ...this.context };
            if (context.report_type === 'comment-response') {
                context.response_values = {};
                for (const control of this.form().querySelectorAll('textarea[name]')) {
                    const match = control.name.match(/^feedback_responses\[([a-zA-Z0-9_-]+)\]\[response\]$/);
                    if (match) context.response_values[match[1]] = control.value;
                    if (Object.keys(context.response_values).length === 20) break;
                }
            }
            return context;
        },
        async request(action) {
            const store = window.Alpine.store('researchAssistant');
            if (this.working || store.proposalChangesBusy() || store.draft.trim()) {
                this.error = 'Finish your current chat message before starting a report check.';
                return false;
            }
            this.error = '';
            this.working = true;
            const sequence = store.requestSequence;
            try {
                const context = await this.prepare(action === 'review_report');
                if (sequence !== store.requestSequence || store.proposalChangesBusy() || store.draft.trim()) {
                    throw new Error('The chat changed while this report was saving. Finish your message, then start this check again.');
                }
                store.reportContext = reportContext(context);
                store.reviewContext = null;
                store.activeDocument = null;
                store.documentAnalysisPending = false;
                store.openDrawer();
                return await store.sendPrompt(action === 'review_report'
                    ? 'Read this saved report and check its completeness, evidence, and inconsistencies. Explain any missing information.'
                    : 'Read this report and suggest precise improvements to its wording and organization. Preserve recorded facts and citations. Show changes for my review.',
                { type: action }, context);
            } catch (error) { this.error = error.message; return false; }
            finally { this.working = false; }
        },
        requestChanges() { return this.request('suggest_report_changes'); },
        reviewReport() { return this.request('review_report'); },
        onPrepare(event) {
            if (event.detail.report_key !== reportKey(this.context)) return;
            event.detail.handled = true;
            event.detail.promise = this.prepare(event.detail.review_only === true);
        },
        onNavigate(event) {
            const restoredEdits = config.unsaved && ['college-quarterly', 'rms-quarterly'].includes(this.context?.report_type);
            if (!this.editable || this.autoSave() || (!restoredEdits && this.fingerprint(true) === this.initialNavigationFingerprint)) return;
            this.error = 'Save your current report edits before opening another report.';
            event.detail.allowed = false;
        },
        onApply(event) {
            if (event.detail.report_key !== reportKey(this.context)) return;
            event.detail.handled = true;
            event.detail.promise = this.applyChange(event.detail);
        },
        async applyChange(change) {
            await this.prepare();
            if (change.report_key !== reportKey(this.context) || change.report_type !== this.context.report_type
                || !reportChangeFieldLimit(change.report_type, change.field)) throw new Error('Open the matching report to apply this suggestion.');
            const form = this.form();
            const control = [...form.elements].find((element) => element.name === fieldName(change.field));
            if (!control || !['TEXTAREA', 'INPUT'].includes(control.tagName) || control.disabled || control.readOnly
                || control.closest('[inert]')) throw new Error('This field is unavailable for editing. Request an updated suggestion.');
            const version = form.querySelector('[name="draft_version"]');
            if (version && String(change.draft_version) !== version.value) throw new Error('The report changed. Request updated suggestions.');
            const sanitize = dependencies.sanitizeHtml || ((value) => value);
            const rich = Boolean(control._semanticEditor);
            const before = rich ? sanitize(change.before, true) : change.before;
            if ((rich ? sanitize(control.value, true) : control.value) !== before) throw new Error('This text changed after the suggestion was prepared. Keep your edits and request an updated suggestion.');
            const value = rich ? sanitize(change.format === 'html' ? change.value : plainHtml(change.value), true) : change.value;
            if (rich && change.format !== 'html' && /data-proposal-(citation|managed-reference)|<table\b/i.test(change.before)) {
                throw new Error('This section contains citations or a table. Request a revision that preserves its structure.');
            }
            if (change.format === 'html' && citationSignature(change.before) !== citationSignature(value)) {
                throw new Error('This revision changes a citation or reference. Keep the original text and request a revision that preserves every source and locator.');
            }
            const limit = reportChangeFieldLimit(change.report_type, change.field);
            if (!value || change.value.length > limit || (control.maxLength > 0 && value.length > control.maxLength)) {
                throw new Error('The suggested text exceeds this field’s limit. Ask Athena for a shorter version.');
            }
            control.value = value;
            control.dispatchEvent(new Event('input', { bubbles: true }));
            control.dispatchEvent(new Event('change', { bubbles: true }));
            await this.$nextTick();
            const autoSave = this.autoSave();
            const saved = autoSave ? await finishProposalPaperAutoSave(autoSave) : false;
            this.notice = saved ? 'Accepted change saved to your draft.'
                : autoSave ? 'Added to your editor. Saving failed; keep this page open and save your draft.'
                    : this.context.report_type === 'comment-response' ? 'Added to your response form. Review and submit the revision when ready.'
                        : 'Added to your report. Save draft to store this change.';
            return { applied: true, saved, notice: this.notice };
        },
    };
}
