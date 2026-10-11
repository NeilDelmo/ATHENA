function reviewContext(context) {
    if (context?.workflow_scope !== 'review') return null;

    const topicId = Number(context.topic_id || 0);
    const proposalDraftId = Number(context.proposal_draft_id || 0);
    if (Boolean(topicId) === Boolean(proposalDraftId)) return null;

    const id = topicId || proposalDraftId;
    if (!Number.isSafeInteger(id) || id <= 0) return null;

    return {
        [topicId ? 'topic_id' : 'proposal_draft_id']: id,
        workflow_scope: 'review',
        ...(['consistency', 'revision_response'].includes(context.review_focus)
            ? { review_focus: context.review_focus } : {}),
        ...(typeof context.review_label === 'string' && context.review_label.trim()
            ? { review_label: context.review_label.trim().slice(0, 300) } : {}),
    };
}

export function researchAssistantReview() {
    return {
        reviewContext: null,
        reviewLaunchPending: false,

        reviewLaunchBusy() {
            return Boolean(this.reviewLaunchPending || this.isLoading || this.sendInProgress
                || this.retryAfter > 0 || this.literatureOperationPending);
        },

        reviewTargetLabel(context = this.reviewContext) {
            if (!context) return '';
            const label = context.review_label || this.contextOptions?.find(
                (option) => Number(option.id) === Number(context.topic_id),
            )?.label;
            return String(label || (context.topic_id ? `Project #${context.topic_id}` : `Proposal draft #${context.proposal_draft_id}`))
                .trim().slice(0, 300);
        },

        reviewFocusLabel() {
            if (this.reviewContext?.review_focus === 'revision_response') return 'Revision response review';
            return this.reviewContext?.review_focus === 'consistency' ? 'Proposal consistency review' : 'Proposal review';
        },

        restoreReviewContext(context) {
            this.reviewContext = reviewContext(context);
            if (!this.reviewContext) return;

            this.reviewContext.review_label = this.reviewTargetLabel();
            this.selectedContextId = this.reviewContext.topic_id || 0;
            this.contextEnabled = Boolean(this.reviewContext.topic_id);
        },

        reviewStarterPrompts() {
            const context = this.reviewContext || reviewContext({
                ...(this.contextEnabled && this.selectedContextId
                    ? { topic_id: this.selectedContextId }
                    : { proposal_draft_id: this.proposalDraftId }),
                workflow_scope: 'review',
            });
            if (!context) return [];

            return [
                {
                    target_label: this.reviewTargetLabel(context),
                    label: 'Check proposal consistency',
                    description: 'Compare objectives, methods, work plan, and budget for gaps or conflicts.',
                    evidence: 'Uses the selected proposal’s saved records',
                    prompt: 'Check this proposal for consistency across its objectives, methodology, work plan, and budget. Explain concrete gaps, conflicts, and corrections using the saved records.',
                    context: { ...context, review_focus: 'consistency' },
                    action: { type: 'review_proposal', review_focus: 'consistency' },
                },
                {
                    target_label: this.reviewTargetLabel(context),
                    label: 'Check revision responses',
                    description: 'Check reviewer comments against responses and the revised proposal.',
                    evidence: 'Uses saved reviewer comments, responses, and proposal records',
                    prompt: 'Check the reviewer comments against the revision responses and saved proposal changes. Identify unanswered or incomplete responses and explain what still needs to be corrected.',
                    context: { ...context, review_focus: 'revision_response' },
                    action: { type: 'review_proposal', review_focus: 'revision_response' },
                },
            ];
        },

        async startSuggestedReview(item, trigger = null) {
            if (this.reviewLaunchBusy()) return false;

            const context = reviewContext(item?.context);
            if (!context || item?.action?.type !== 'review_proposal'
                || !['consistency', 'revision_response'].includes(item.action.review_focus)
                || typeof item.prompt !== 'string' || !item.prompt.trim()) {
                this.setError('Review unavailable', 'Choose a proposal and a valid review action before starting.');
                return false;
            }

            this.returnFocusElement = trigger instanceof HTMLElement ? trigger : document.activeElement;
            this.openWorkspace();
            if (this.draft.trim()) {
                this.setError('Finish your current message', 'Send or clear the message you are writing before starting a focused review.');
                return false;
            }

            this.reviewLaunchPending = true;
            try {
                const requestSequence = this.requestSequence;
                const hadConversation = this.hasConversation();
                const savedConversation = await this.saveConversation();
                if ((hadConversation && !savedConversation) || requestSequence !== this.requestSequence) return false;
                if (this.draft.trim()) {
                    this.setError('Finish your current message', 'Send or clear the message you are writing before starting a focused review.');
                    return false;
                }

                this.clearConversation();
                this.selectProjectContext(context.topic_id || null);
                this.restoreReviewContext({
                    ...context,
                    review_focus: item.action.review_focus,
                    review_label: item.target_label || context.review_label,
                });
                this.historySearchOpen = false;
                await this.sendPrompt(item.prompt, {
                    type: 'review_proposal',
                    review_focus: item.action.review_focus,
                }, { ...context });
                return true;
            } finally {
                this.reviewLaunchPending = false;
            }
        },
    };
}
