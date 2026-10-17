function paperField(paper, field) {
    if (typeof field !== 'string') return null;
    const match = paper === 'work-plan'
        ? field.match(/^entries\.([0-9]|1[0-9])\.(expected_output|activity)$/)
        : paper === 'expense-breakdown' ? field.match(/^items\.([0-9]|[1-9][0-9])\.(details|purpose)$/) : null;
    return match ? { collection: paper === 'work-plan' ? 'entries' : 'items', index: Number(match[1]),
        property: match[2] === 'expected_output' ? 'expectedOutput' : match[2],
        maximum: match[2] === 'activity' ? 1500 : 500 } : null;
}

export function proposalPaperAssistantChanges(config = {}) {
    const workPlan = config.paperSlug === 'work-plan';
    return {
        proposalChangeApplying: false,
        proposalChangeError: '',
        proposalChangeNotice: '',
        proposalChangeAppliedIds: [],

        handleAssistantPaperChange(event) {
            const packet = event?.detail;
            if (!packet || packet.paper_slug !== config.paperSlug
                || Number(packet.proposal_draft_id) !== Number(config.proposalDraftId)) return;
            packet.handled = true;
            packet.promise = this.applyAssistantPaperChange(packet);
        },

        async applyAssistantPaperChange(packet) {
            if (packet?.paper_slug !== config.paperSlug || Number(packet?.proposal_draft_id) !== Number(config.proposalDraftId)) {
                return { applied: false, saved: false };
            }
            const field = paperField(config.paperSlug, packet.field);
            if (!field || typeof packet.id !== 'string' || !/^[a-z0-9_-]{1,80}$/i.test(packet.id)
                || !Number.isSafeInteger(packet.document_version) || packet.document_version < 0
                || !Number.isSafeInteger(packet.draft_version) || packet.draft_version < 0
                || typeof packet.before !== 'string' || typeof packet.value !== 'string' || !packet.value.trim()
                || packet.value.length > field.maximum || /<[^>]+>|data-proposal-(?:citation|managed-reference)/i.test(packet.before + packet.value)) {
                throw new Error('This paper change is invalid. Request new suggestions.');
            }
            if (this.proposalChangeAppliedIds.includes(packet.id)) {
                return { applied: true, saved: this.$el.dataset.paperDirty !== 'true' && !this.autoSaveInFlight && !this.autoSaveBlocked,
                    notice: 'This suggestion was already applied in this editor.' };
            }
            const form = this.$refs.form;
            if (!(form instanceof HTMLFormElement) || this.proposalChangeApplying || this.autoSaveInFlight || this.autoSaveBlocked
                || this.$el.inert || this.$el.isConnected === false || this.$el.dataset.paperSubmitting === 'true'
                || this.$el.dataset.paperDiscarding === 'true') {
                throw new Error('This editor is busy or saving is blocked. Finish saving or load the latest paper before applying changes.');
            }
            const fingerprint = workPlan ? this.workPlanFingerprint(form) : this.expenseBreakdownFingerprint();
            if (this.$el.dataset.paperDirty === 'true' || fingerprint !== (workPlan ? this.lastSavedWorkPlan : this.lastSavedExpenseBreakdown)) {
                throw new Error('The paper has unsaved edits. Finish saving and request a current suggestion before applying changes.');
            }
            const documentVersion = form.querySelector('[name="document_version"]');
            const draftVersion = form.querySelector('[name="draft_version"]')?.value ?? config.draftVersion;
            if (!documentVersion || Number(documentVersion.value) !== packet.document_version || Number(draftVersion) !== packet.draft_version) {
                throw new Error('The proposal version changed. Load the latest saved work and request new suggestions.');
            }
            const row = this[field.collection]?.[field.index];
            const name = packet.field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
            const control = [...form.querySelectorAll('textarea, input')].find((item) => item.name === name && item.type !== 'hidden');
            if (!row || !Object.hasOwn(row, field.property) || !control || control.disabled || control.readOnly
                || control.closest('[inert]') || String(row[field.property] ?? '') !== packet.before || control.value !== packet.before) {
                throw new Error('This field changed or is unavailable. Your current text was kept; request new suggestions.');
            }
            this.proposalChangeApplying = true;
            this.proposalChangeError = '';
            try {
                row[field.property] = packet.value;
                control.value = packet.value;
                this.$el.dataset.paperDirty = 'true';
                control.dispatchEvent(new Event('input', { bubbles: true }));
                this.proposalChangeAppliedIds = [...this.proposalChangeAppliedIds, packet.id].slice(-20);
                if (workPlan) {
                    this.expandedEntryId = row.id;
                    this.activeEntryId = row.id;
                    this.triggerWorkPlanAutoSave();
                } else {
                    this.expandedItemId = row.id;
                    this.triggerExpenseBreakdownAutoSave();
                }
                this.proposalChangeNotice = 'Reviewed text was updated in the editor. Saving now…';
                await this.$nextTick();
                const saved = await config.finishSave(this.$el);
                if (saved) this.$el.dataset.paperDirty = 'false';
                this.proposalChangeNotice = saved ? 'Reviewed text was updated and saved.' : 'Reviewed text was updated in the editor. It still needs to be saved.';
                this.proposalChangeError = saved ? '' : 'Saving did not finish. Keep this page open and resolve the save warning before leaving.';
                control.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
                control.focus?.({ preventScroll: true });
                return { applied: true, saved, notice: this.proposalChangeNotice, error: this.proposalChangeError };
            } finally {
                this.proposalChangeApplying = false;
            }
        },

        async applyPendingAssistantPaperChange() {
            const selection = config.changeHandoff?.read(config.proposalDraftId);
            if (!selection || (selection.paper_slug && selection.paper_slug !== config.paperSlug)) return;
            try {
                if (!await config.finishSave(this.$el)) throw new Error('Finish saving this paper before applying the prepared suggestion.');
                const packet = await config.resolveChange(selection);
                if (packet?.paper_slug !== config.paperSlug) return;
                config.changeHandoff.clear(config.proposalDraftId);
                return await this.applyAssistantPaperChange(packet);
            } catch (error) {
                this.proposalChangeError = error instanceof Error ? error.message : 'The prepared change is unavailable. Request new suggestions.';
            }
        },
    };
}
