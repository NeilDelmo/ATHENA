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
        ...(['revision_plan', 'feedback_explanation', 'response_draft', 'draft_guidance', 'discussion'].includes(context.assistance_mode)
            ? { assistance_mode: context.assistance_mode } : {}),
    };
}

export function researchAssistantReview() {
    return {
        reviewContext: null,
        reviewLaunchPending: false,
        proposalHelpOpen: false,

        reviewLaunchBusy() {
            return Boolean(this.reviewLaunchPending || this.isLoading || this.sendInProgress
                || this.retryAfter > 0 || this.literatureOperationPending || this.proposalChangesOperationPending || this.reportChangesOperationPending);
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
            if (this.reviewContext?.assistance_mode) return this.reviewContext.review_focus === 'consistency' ? 'Proposal help' : 'Revision help';
            if (this.reviewContext?.review_focus === 'revision_response') return 'Revision response review';
            return this.reviewContext?.review_focus === 'consistency' ? 'Proposal consistency review' : 'Proposal review';
        },

        restoreReviewContext(context) {
            this.proposalHelpOpen = false;
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

        openProposalHelp(item, trigger = null) {
            return this.startSuggestedReview(item, trigger, true);
        },

        proposalHelpOptions() {
            if (!this.reviewContext) return [];
            const revision = this.reviewContext.review_focus === 'revision_response';
            return revision ? [
                { key: 'revision_plan', label: 'Plan my revisions', description: 'Turn the recorded feedback into a short list of next steps.',
                    mode: 'revision_plan', prompt: 'Help me plan my revisions. Use the recorded reviewer feedback for this proposal to suggest a short, practical sequence of changes. Separate what I need to change in the paper from what I need to explain in my response. Do not claim that anything has already been changed.' },
                { key: 'feedback_explanation', label: 'Explain reviewer feedback', description: 'Understand what the reviewer is asking you to improve.',
                    mode: 'feedback_explanation', prompt: 'Explain the recorded reviewer feedback for this proposal in plain language. Clarify the requested changes and give a useful example based on the available saved evidence. Say when the comment is too vague to interpret reliably.' },
                { key: 'response_draft', label: 'Help write a response', description: 'Suggest wording that accurately reflects the changes you made.',
                    mode: 'response_draft', prompt: 'Help me write a response to the recorded reviewer feedback. Use only changes supported by the saved proposal. If a change is not evidenced, give wording I can complete after making it rather than claiming it is done. Keep the wording concise and editable; do not submit or save a response.' },
                { key: 'compare', label: 'Compare saved changes', description: 'Check which reviewer requests are supported by the saved papers.',
                    prompt: 'Compare the recorded reviewer requests with the saved proposal and revision responses. Focus on what still needs attention and suggest the next step for each supported finding.' },
            ] : [
                ...(this.reviewContext.proposal_draft_id ? [{ key: 'proposal_changes', label: 'Suggest proposal changes',
                    description: 'Review specific wording changes and apply the ones you choose.',
                    action: 'suggest_proposal_changes',
                    prompt: 'Read this saved proposal against the Research Manual requirements. Identify evidence gaps and suggest precise improvements to its existing text for my review. Let me choose which changes to apply; use the reviewed literature flow for additional studies.' }] : []),
                { key: 'draft_guidance', label: 'Strengthen my proposal', description: 'Find the clearest next improvements in your saved draft.',
                    mode: 'draft_guidance', prompt: 'Help me improve this saved proposal draft. Suggest up to three concrete next improvements based on its objectives, methods, work plan and budget. Explain how to make each improvement without claiming that edits were saved.' },
                { key: 'methods', label: 'Review objectives and methods', description: 'Discuss how your research methods can answer your objectives.',
                    mode: 'draft_guidance', prompt: 'Explain how the saved objectives and methodology in this proposal draft fit together. Suggest practical refinements and identify information I still need to provide.' },
                { key: 'compare', label: 'Compare proposal papers', description: 'Check the saved objectives, methods, work plan and budget together.',
                    prompt: 'Compare this proposal draft across its objectives, methods, work plan and budget. Give concise evidence-backed suggestions for any mismatches.' },
            ];
        },

        async startProposalHelp(option, trigger = null) {
            if (this.reviewLaunchBusy() || this.draft.trim()) return false;
            const selected = this.proposalHelpOptions().find(item => item.key === option?.key);
            if (!selected) return false;
            const context = { ...this.reviewContext };
            delete context.assistance_mode;
            if (selected.mode) context.assistance_mode = selected.mode;
            this.reviewContext.assistance_mode = selected.mode;
            this.proposalHelpOpen = false;
            this.returnFocusElement = trigger instanceof HTMLElement ? trigger : document.activeElement;
            await this.sendPrompt(selected.prompt, selected.mode ? null : {
                type: selected.action || 'review_proposal', ...(selected.action ? {} : { review_focus: context.review_focus }),
            }, context);
            return true;
        },

        async startSuggestedReview(item, trigger = null, chooseHelp = false) {
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
                if (chooseHelp) {
                    this.reviewContext.assistance_mode = 'discussion';
                    this.proposalHelpOpen = true;
                    return true;
                }
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
