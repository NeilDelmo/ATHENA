import { allowedProposalChangeField, normalizeAssistantProposalChanges, proposalChangeRichTextValue } from './research-assistant-proposal-changes.js';
import { autoSaveHasStaleVersionError, autoSaveValidationMessage, proposalPaperFormFingerprint, saveProposalPaperWithDraftFallback } from './proposal-paper-autosave.js';
import { buildLiteratureSearchPayload, buildLiteratureSearchQuery, fetchLiteratureSearch, literatureSearchHistoryTitle } from './proposal-literature-search.js';
import { currentReviewedLiteratureSources } from './research-assistant-paper-insertion.js';
import { detailedProposalPreviewWorkspace, initializeDetailedProposalWritingTools } from './detailed-proposal-workspace.js';
import { filterWorkspacePeople, formatPersonName, personInitials } from './proposal-people.js';
import { isEmbeddedRevisionEditor } from './revision-editor-context.js';
import { prepareReviewedSourceDraft } from './proposal-source-draft-insertion.js';
import { orderedCitationSourceIds, proposalCitationField, proposalCitationFieldIds, proposalCitationLocator, proposalCitationSelection } from './proposal-semantic-editor.js';
import { proposalPreviewWorkspace } from './proposal-preview-workspace.js';
import { proposalSourceWorkspace } from './proposal-source-workspace.js';
import { uploadRequest } from './file-upload.js';

export default function registerDetailedProposalEditor(Alpine, {
    assistantLiteratureHandoff,
    assistantProposalChangeHandoff,
    finishPaperEditorAutoSave,
    initializeSemanticEditors,
    offerRevisionUpload,
    resolveAssistantProposalChange,
    revisionGenerationConfig,
    sanitizedSemanticHtml,
}) {
    Alpine.data('proposalDraftDetailedProposal', (config = {}) => ({
        ...proposalSourceWorkspace({
            csrfToken: config.csrfToken,
            proposalDraftId: config.proposalDraftId,
            synthesisUrl: config.literatureSynthesisUrl,
            libraryUrl: config.literatureLibrarySearchUrl,
            storeUrl: config.literatureLibrarySaveUrl,
            searchUrl: config.literatureSearchUrl,
            linkUrlTemplate: config.literatureAttachUrlTemplate,
            evidenceBase: config.literatureEvidenceBase,
            assistanceUrl: config.literatureEvidenceAssistanceUrl,
            metadataUrl: config.literatureMetadataUrl,
        }),
        sourceWorkspaceInsertedDrafts: [],
        proposalChangeApplying: false,
        rrlRevisionPending: false,
        proposalChangeAppliedIds: [],
        proposalChangeNotice: '',
        proposalChangeError: '',
        ...proposalPreviewWorkspace(),
        ...detailedProposalPreviewWorkspace(),
        nextId: 0,
        workspacePeople: Array.isArray(config.workspacePeople) ? config.workspacePeople : [],
        selectedWorkspacePerson: '',
        workspacePersonQuery: '',
        workspacePickerOpen: false,
        researchAgenda: '',
        sdgs: [],
        projectLeader: '',
        previousProjectLeader: '',
        leaderTitle: '',
        leaderEmail: '',
        leaderContact: '',
        staff: [],
        proponentDepartment: '',
        proponentCollege: '',
        proponentCampus: '',
        cooperatingAgency: '',
        executiveBrief: '',
        rationale: '',
        generalObjective: '',
        specificObjectives: [],
        specificMethodGroups: [],
        expectedOutputs: {},
        introduction: '',
        relatedLiterature: '',
        literatureSources: [],
        literatureCitations: [],
        literatureSourceNotice: '',
        citationPickerOpen: false,
        citationPickerSelection: null,
        citationPickerQuery: '',
        citationPickerResults: [],
        citationPickerLoading: false,
        citationPickerError: '',
        citationPickerSaving: false,
        citationPickerLocator: '',
        proposalTitle: String(config.proposalTitle || ''),
        literatureSearchContext: {
            title: true,
            generalObjective: true,
            specificObjectives: true,
            researchAgenda: false,
            methodology: false,
        },
        literatureSearchQuery: '',
        literatureSearchFilters: { year_from: '', year_to: '', min_citations: '', open_access: false },
        literatureSearchUseContext: true,
        literatureSearchKeywords: [],
        literatureSearchResults: [],
        literatureSearchHistory: [],
        literatureSearchLoading: false,
        literatureSearchError: '',
        literatureSearchNotice: '',
        literatureSearchSavingKey: '',
        literatureWorkspaceOpen: false,
        literatureWorkspaceTab: 'search',
        literatureWorkspaceView: 'browse',
        literatureReviewOpen: false,
        literatureReviewSource: null,
        literatureReviewDraft: '',
        literatureReviewPreviousContext: '',
        literatureReviewRelationship: 'standalone',
        literatureReviewTransition: '',
        literatureReviewBasis: 'abstract',
        literatureReviewFullText: '',
        literatureReviewFullTextError: '',
        literatureReviewLoadingFullText: false,
        literatureReviewGenerating: false,
        literatureReviewSaving: false,
        literatureReviewNotice: '',
        literatureReviewError: '',
        methodology: {},
        methodologyImages: [],
        figureSections: config.figureSections || config.methodologySections || {},
        methodologySections: config.methodologySections && typeof config.methodologySections === 'object'
            ? config.methodologySections
            : {},
        methodologyImageTarget: 'research_design',
        draggedMethodologyImage: '',
        responsibilities: [],
        checkedVerifiedByName: '',
        recommendingApprovalName: '',
        approvedByName: '',
        references: '',
        validationMessage: '',
        previewHtml: '',
        previewError: '',
        previewLoading: false,
        previewReady: false,
        downloadError: '',
        downloadLoading: false,
        autoSaveTimer: null,
        autoSaveInFlight: false,
        autoSaveBlocked: false,
        autoSaveRevision: 0,
        lastSavedDetailedProposal: '',
        detailedProposalSaveState: 'idle',
        autoSaveInitialContent: false,
        recheckCompletion: Boolean(config.recheckCompletion),
        detailedProposalStarted: Boolean(config.detailedProposalStarted),
        detailedProposalComplete: Boolean(config.detailedProposalComplete),
        completionErrors: config.completionErrors || {},
        requirementsReviewed: Boolean(config.requirementsReviewed),
        proposalFeedbackId: 0,

        get showCompletionChecklist() {
            return this.requirementsReviewed && Object.keys(this.completionErrors).length > 0;
        },

        get detailedProposalRequirementsResult() {
            return this.showCompletionChecklist
                ? 'Some requirements need attention. Select a requirement below to finish it.'
                : 'All required fields are complete. PDF preparation status is shown above.';
        },

        init() {
            const data = config.initialData && typeof config.initialData === 'object' ? config.initialData : {};
            this.researchAgenda = String(data.research_agenda ?? '');
            this.sdgs = Array.isArray(data.sdgs) ? data.sdgs.map((sdg) => Number(sdg)) : [];
            this.projectLeader = formatPersonName(data.project_leader ?? config.projectLeader ?? '');
            this.previousProjectLeader = this.projectLeader;
            this.leaderTitle = String(data.leader_title ?? '');
            this.leaderEmail = String(data.leader_email ?? '');
            this.leaderContact = this.normalizeContactNumber(data.leader_contact);
            this.staff = Array.isArray(data.staff)
                ? data.staff.map((member) => this.newStaff(member))
                : [];
            this.proponentDepartment = String(data.proponent_department ?? '');
            this.proponentCollege = String(data.proponent_college ?? '');
            this.proponentCampus = String(data.proponent_campus ?? '');
            this.cooperatingAgency = String(data.cooperating_agency ?? '');
            this.executiveBrief = String(data.executive_brief ?? '');
            this.rationale = String(data.rationale ?? '');
            const objectives = this.normalizedObjectives(
                data.general_objective,
                data.specific_objectives,
                data.objectives,
            );
            this.generalObjective = objectives.generalObjective;
            this.specificObjectives = objectives.specificObjectives;
            this.specificMethodGroups = this.normalizedSpecificMethodGroups(
                data.specific_method_objectives,
                data.methodology?.specific_methods,
            );
            this.expectedOutputs = Object.fromEntries(
                (config.expectedOutputKeys || []).map((key) => [key, this.normalizedExpectedOutputs(data.expected_outputs?.[key])]),
            );
            this.autoSaveInitialContent = this.nestedProposalFieldsNeedSave(data);
            this.introduction = String(data.introduction ?? '');
            this.relatedLiterature = String(data.related_literature ?? '');
            this.literatureSources = Array.isArray(config.literatureSources)
                ? config.literatureSources
                : [];
            this.literatureCitations = this.parseLiteratureCitations(data.literature_citations);
            this.literatureSearchHistory = this.parseLiteratureSearchHistory(data.literature_research_history);
            this.methodology = Object.fromEntries(
                (config.methodologyKeys || []).map((key) => [key, String(data.methodology?.[key] ?? '')]),
            );
            this.methodologyImages = Array.isArray(data.methodology_images)
                ? data.methodology_images.map((image) => this.newMethodologyImage(image))
                    .filter((image) => image.id)
                : [];
            this.responsibilities = Array.isArray(data.responsibilities) && data.responsibilities.length
                ? data.responsibilities.map((responsibility, index) => this.newResponsibility({
                    ...responsibility,
                    percentage: responsibility.percentage ?? (index === 0 ? 100 : ''),
                }))
                : [this.newResponsibility({ name: config.projectLeader, percentage: 100 })];
            this.checkedVerifiedByName = String(data.checked_verified_by_name ?? '');
            this.recommendingApprovalName = String(data.recommending_approval_name ?? '');
            this.approvedByName = String(data.approved_by_name ?? '');
            this.references = String(data.references ?? '');
            this.refreshSuggestedLiteratureQuery();
            this.applyInitialLiteratureSource();
            const pendingLiterature = assistantLiteratureHandoff().read(config.proposalDraftId);
            if (pendingLiterature) {
                const { sources, changed } = currentReviewedLiteratureSources(pendingLiterature, this.literatureSources);
                this.applyAssistantLiterature({ proposal_draft_id: config.proposalDraftId, sources });
                if (changed) this.literatureSourceNotice = 'Some sources have newer or removed drafts. Review them in Saved sources before adding them. Any unchanged reviewed paragraphs were added.';
                assistantLiteratureHandoff().clear(config.proposalDraftId);
            }
            this.$nextTick(() => {
                this.synchronizeLiteratureCitations();
                this.startDetailedProposalAutoSave();
                if (this.$el.matches('[data-detailed-proposal-workspace]')) {
                    this.initializeDetailedProposalPreview();
                    initializeSemanticEditors();
                    this.writingToolsCleanup = initializeDetailedProposalWritingTools(this.$el, {
                        openImagePicker: (section) => this.openMethodologyImagePicker(section),
                        dropImages: (event, section) => this.handleMethodologyDrop(event, section),
                        onActiveSection: (fieldId) => this.focusDetailedProposalPreview(fieldId),
                    });
                    void this.applyPendingAssistantProposalChange();
                }
            });
        },

        destroy() {
            this.writingToolsCleanup?.();
            this.destroySourceWorkspace();
            this.destroyDetailedProposalPreview();
            window.clearTimeout(this.autoSaveTimer);
        },

        openLiteratureWorkspace(tab = 'search') {
            this.openSourceWorkspace(tab === 'sources' ? 'paper' : 'search');
        },

        closeLiteratureWorkspace() {
            this.literatureWorkspaceOpen = false;
            this.literatureWorkspaceView = 'browse';
            this.literatureReviewOpen = false;
            this.literatureReviewError = '';
            document.body.classList.remove('overflow-y-hidden');
        },

        setLiteratureWorkspaceTab(tab) {
            this.literatureWorkspaceTab = tab === 'sources' ? 'sources' : 'search';
            this.literatureWorkspaceView = 'browse';
        },

        returnToLiteratureResults() {
            this.literatureWorkspaceView = 'browse';
            this.literatureReviewOpen = false;
            this.literatureReviewError = '';
        },

        literatureWorkspaceCitedSourcesCount() {
            return this.literatureSources.filter((source) => this.citationReferenceNumber(source) !== null).length;
        },

        proposalChangeFieldValue(field) {
            if (!allowedProposalChangeField(field)) return null;
            if (field.startsWith('specific_objectives.')) {
                const objective = this.specificObjectives[Number(field.split('.')[1])];
                return objective ? String(objective.description ?? '') : null;
            }
            if (field.startsWith('methodology.')) return String(this.methodology[field.split('.')[1]] ?? '');
            const parts = field.split('.');
            if (parts[0] === 'specific_method_objectives') {
                const group = this.specificMethodGroups?.[Number(parts[1])];
                return group ? (parts[2] === 'heading' ? String(group.heading ?? '')
                    : group.methods?.[Number(parts[3])] ? String(group.methods[Number(parts[3])].description ?? '') : null) : null;
            }
            if (parts[0] === 'expected_outputs') {
                const output = this.expectedOutputs?.[parts[1]]?.[Number(parts[2])];
                return output ? String(output.description ?? '') : null;
            }
            if (parts[0] === 'responsibilities') {
                const responsibility = this.responsibilities?.[Number(parts[1])];
                return responsibility ? String(responsibility.duties ?? '') : null;
            }
            const property = { executive_brief: 'executiveBrief', rationale: 'rationale', general_objective: 'generalObjective', introduction: 'introduction', related_literature: 'relatedLiterature' }[field];
            return property ? String(this[property] ?? '') : null;
        },

        setProposalChangeFieldValue(field, value) {
            if (!allowedProposalChangeField(field)) return false;
            const parts = field.split('.');
            if (field.startsWith('specific_objectives.')) {
                const objective = this.specificObjectives[Number(field.split('.')[1])];
                if (!objective) return false;
                objective.description = value;
            } else if (parts[0] === 'specific_method_objectives') {
                const group = this.specificMethodGroups?.[Number(parts[1])];
                if (!group) return false;
                if (parts[2] === 'heading') group.heading = value;
                else {
                    const method = group.methods?.[Number(parts[3])];
                    if (!method) return false;
                    method.description = value;
                }
            } else if (parts[0] === 'expected_outputs') {
                const output = this.expectedOutputs?.[parts[1]]?.[Number(parts[2])];
                if (!output) return false;
                output.description = value;
            } else if (parts[0] === 'responsibilities') {
                const responsibility = this.responsibilities?.[Number(parts[1])];
                if (!responsibility) return false;
                responsibility.duties = value;
            } else if (field.startsWith('methodology.')) {
                this.methodology[field.split('.')[1]] = value;
            } else {
                const property = { executive_brief: 'executiveBrief', rationale: 'rationale', general_objective: 'generalObjective', introduction: 'introduction', related_literature: 'relatedLiterature' }[field];
                if (!property) return false;
                this[property] = value;
            }
            return true;
        },

        async requestRrlRevision() {
            const assistant = Alpine.store('researchAssistant');
            if (this.rrlRevisionPending || !assistant) return false;
            this.proposalChangeError = '';
            if (assistant.proposalChangesBusy() || assistant.draft.trim()) {
                this.proposalChangeError = 'Finish your current assistant message before requesting an RRL revision.';
                return false;
            }
            this.rrlRevisionPending = true;
            try {
                if (!await finishPaperEditorAutoSave(this.$el.closest('[data-detailed-proposal-workspace]'))) {
                    throw new Error('Save your current paper edits before requesting an RRL revision.');
                }
                if (assistant.proposalChangesBusy() || assistant.draft.trim()) {
                    throw new Error('Finish your current assistant message before requesting an RRL revision.');
                }
                assistant.openDrawer();
                await assistant.sendPrompt('Revise and organize my saved RRL for clarity and paragraph flow. Preserve its claims and citations. Show the revision for my review.',
                    { type: 'suggest_proposal_changes' }, { proposal_draft_id: config.proposalDraftId });
                return true;
            } catch (error) {
                this.proposalChangeError = error instanceof Error ? error.message : 'The RRL revision could not be requested.';
                return false;
            } finally {
                this.rrlRevisionPending = false;
            }
        },

        proposalChangeCitationsMatch(before, value) {
            const markers = (html) => {
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                return [...parsed.querySelectorAll('[data-proposal-citation]')].map((node) => JSON.stringify([
                    node.getAttribute('data-proposal-citation'), node.getAttribute('data-proposal-locator') || '',
                ])).sort();
            };
            return JSON.stringify(markers(before)) === JSON.stringify(markers(value));
        },

        async applyAssistantProposalChange(packet) {
            if (Number(packet?.proposal_draft_id) !== Number(config.proposalDraftId)
                || packet?.paper_slug !== 'detailed-proposal') return { applied: false, saved: false };
            packet.handled = true;
            const normalized = normalizeAssistantProposalChanges({ proposal_draft_id: config.proposalDraftId, suggestions: [packet] });
            const suggestion = normalized?.suggestions[0];
            if (!suggestion || !Number.isSafeInteger(packet.document_version) || packet.document_version < 0
                || !Number.isSafeInteger(packet.draft_version) || packet.draft_version < 0) {
                throw new Error('This proposal change is invalid. Request new suggestions.');
            }
            if (this.proposalChangeAppliedIds.includes(String(suggestion.id))) {
                return { applied: true, saved: this.$el.dataset.paperDirty !== 'true' && !this.autoSaveInFlight && !this.autoSaveBlocked,
                    notice: 'This suggestion was already applied in this editor.' };
            }
            const form = this.detailedProposalAutoSaveForm();
            if (this.proposalChangeApplying || this.autoSaveBlocked || this.autoSaveInFlight
                || this.$el.dataset.paperSubmitting === 'true' || this.$el.dataset.paperDiscarding === 'true'
                || this.$el.inert || this.$el.isConnected === false || !(form instanceof HTMLFormElement)) {
                throw new Error('This editor is busy or saving is blocked. Finish saving or load the latest proposal before applying changes.');
            }
            if (this.detailedProposalFingerprint(form) !== this.lastSavedDetailedProposal) {
                throw new Error('The proposal has unsaved edits. Finish saving and request a current suggestion before applying changes.');
            }
            const documentVersion = form.querySelector('[name="document_version"]');
            const draftVersion = form.querySelector('[name="draft_version"]');
            if (!documentVersion || !draftVersion || Number(documentVersion.value) !== packet.document_version
                || Number(draftVersion.value) !== packet.draft_version) {
                throw new Error('The proposal version changed. Load the latest saved work and request new suggestions.');
            }
            const name = suggestion.field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
            const control = [...form.querySelectorAll('textarea, input')].find((field) => field.name === name);
            const current = this.proposalChangeFieldValue(suggestion.field);
            const richText = control instanceof HTMLTextAreaElement && control.hasAttribute('data-semantic-editor');
            const expectedControlValue = richText ? sanitizedSemanticHtml(suggestion.before, true) : suggestion.before;
            if (!control || control.disabled || control.readOnly || control.closest('[inert]')
                || current !== suggestion.before || (control.value !== suggestion.before && control.value !== expectedControlValue)) {
                throw new Error('This field changed after the suggestion was prepared. Your current text was kept; request a new suggestion.');
            }
            const rrlRevision = suggestion.field === 'related_literature';
            if (!rrlRevision && /data-proposal-(?:citation|managed-reference)/i.test(suggestion.before)) {
                throw new Error('This section contains linked citations. Revise it in the editor so its citations and references stay connected.');
            }
            this.proposalChangeApplying = true;
            this.proposalChangeError = '';
            try {
                const value = rrlRevision ? sanitizedSemanticHtml(suggestion.value, true)
                    : richText ? proposalChangeRichTextValue(suggestion.value) : suggestion.value;
                if (rrlRevision && (!richText || !this.proposalChangeCitationsMatch(suggestion.before, value))) {
                    throw new Error('The revision could not preserve the source citations. Your current RRL was kept; request a new revision.');
                }
                if (!value || !this.setProposalChangeFieldValue(suggestion.field, value)) throw new Error('The target field is unavailable.');
                control.value = value;
                control.dispatchEvent(new Event('input', { bubbles: true }));
                if (rrlRevision) this.synchronizeLiteratureCitations();
                this.proposalChangeAppliedIds = [...this.proposalChangeAppliedIds, String(suggestion.id)].slice(-20);
                this.proposalChangeNotice = `${suggestion.label || 'Proposal text'} was updated in the editor. Saving now…`;
                this.markProposalPreviewStale();
                this.triggerDetailedProposalAutoSave();
                await this.$nextTick();
                const saved = await finishPaperEditorAutoSave(this.$el);
                this.proposalChangeNotice = saved ? `${suggestion.label || 'Proposal text'} was updated and saved.`
                    : `${suggestion.label || 'Proposal text'} was updated in the editor. Your change still needs to be saved.`;
                this.proposalChangeError = saved ? '' : 'Saving did not finish. Keep this page open and resolve the save warning before leaving.';
                this.focusProposalRequirement(suggestion.field);
                return { applied: true, saved, notice: this.proposalChangeNotice, error: this.proposalChangeError };
            } finally {
                this.proposalChangeApplying = false;
            }
        },

        async applyPendingAssistantProposalChange() {
            const handoff = assistantProposalChangeHandoff();
            const selection = handoff.read(config.proposalDraftId);
            if (!selection || (selection.paper_slug && selection.paper_slug !== 'detailed-proposal')) return;
            try {
                if (!await finishPaperEditorAutoSave(this.$el)) throw new Error('Finish saving this proposal before applying the prepared suggestion.');
                const candidate = await resolveAssistantProposalChange(selection,
                    config.proposalChangesUrl || document.body.dataset.researchAssistantProposalChangesUrl, config.csrfToken);
                if (candidate.paper_slug !== 'detailed-proposal') return;
                handoff.clear(config.proposalDraftId);
                await this.applyAssistantProposalChange(candidate);
            } catch (error) {
                this.proposalChangeError = error instanceof Error ? error.message : 'The prepared change is unavailable. Request new suggestions.';
            }
        },

        applyAssistantLiterature(packet) {
            if (Number(packet?.proposal_draft_id) !== Number(config.proposalDraftId)) return;
            let added = 0;
            (Array.isArray(packet.sources) ? packet.sources : []).slice(0, 3).forEach((source) => {
                if (!Number(source.id) || source.rrl_draft_status !== 'confirmed') return;
                this.upsertLiteratureSource(source);
                if (this.addLiteratureSourceToRrl(source, true)) added++;
            });
            packet.handled = true;
            packet.added = added;
            if (added) {
                this.autoSaveInitialContent = true;
                this.literatureSourceNotice = 'Reviewed RRL paragraphs and matching references were added. Autosaving now.';
                this.triggerDetailedProposalAutoSave();
            }
        },

        applyInitialLiteratureSource() {
            const sourceId = Number(config.initialLiteratureSourceId);
            const action = String(config.initialLiteratureAction || '');
            const source = this.literatureSources.find((item) => Number(item.id) === sourceId);

            if (!source || !['rrl', 'reference', 'both'].includes(action)) return;

            let applied = false;

            if (action === 'rrl' || action === 'both') applied = this.addLiteratureSourceToRrl(source, true);

            if (applied) {
                this.autoSaveInitialContent = true;
                this.literatureSourceNotice = 'The selected source is staged in this proposal. Review the inserted text, then save the draft to keep it.';
            } else if (action === 'reference') {
                this.literatureSourceNotice = 'The source is saved to this proposal library. Highlight a supported claim in any eligible narrative section, then choose Support with source; ATHENA will add the matching IEEE reference automatically.';
            } else {
                this.literatureSourceNotice = 'The selected source already appears in the requested proposal section.';
            }

            this.focusLiteratureDestination(action === 'reference' ? 'rrl' : action);
        },

        parseLiteratureSearchHistory(value) {
            try {
                const parsed = typeof value === 'string' ? JSON.parse(value) : value;

                if (!Array.isArray(parsed)) return [];

                return parsed
                    .filter((entry) => entry && typeof entry === 'object' && String(entry.query || '').trim())
                    .map((entry, index) => ({
                        id: String(entry.id || `${entry.query}-${entry.searched_at || index}`),
                        query: String(entry.query || '').trim(),
                        label: String(entry.label || '').trim(),
                        context: Array.isArray(entry.context)
                            ? entry.context.filter((label) => typeof label === 'string' && label.trim()).slice(0, 5)
                            : [],
                        resultCount: Number.isInteger(entry.result_count) ? entry.result_count : 0,
                        searchedAt: String(entry.searched_at || ''),
                    }))
                    .slice(0, 8);
            } catch (error) {
                return [];
            }
        },

        parseLiteratureCitations(value) {
            try {
                const parsed = typeof value === 'string' ? JSON.parse(value) : value;

                if (!Array.isArray(parsed)) return [];

                const seenCitations = new Set();

                return parsed
                    .filter((citation) => citation && typeof citation === 'object')
                    .map((citation, index) => ({
                        id: String(citation.id || `citation-${index}`),
                        source_link_id: Number(citation.source_link_id),
                        literature_source_id: Number(citation.literature_source_id),
                        field: citation.field === 'references' ? 'references' : proposalCitationField(citation.field)?.key || '',
                        selected_text: String(citation.selected_text || '').trim().slice(0, 2000),
                        locator: String(citation.locator || '').trim().slice(0, 100),
                        created_at: String(citation.created_at || ''),
                    }))
                    .filter((citation) => Number.isInteger(citation.source_link_id) && citation.source_link_id > 0)
                    .filter((citation) => citation.field === 'references' || proposalCitationField(citation.field))
                    .filter((citation) => {
                        const identity = [
                            citation.source_link_id,
                            citation.field,
                            citation.selected_text.toLowerCase().replace(/\s+/g, ' '),
                            citation.locator.toLowerCase(),
                        ].join('|');

                        if (seenCitations.has(identity)) return false;

                        seenCitations.add(identity);

                        return true;
                    })
                    .slice(0, 100);
            } catch (error) {
                return [];
            }
        },

        literatureCitationsFor(source) {
            const sourceLinkId = Number(source?.id);

            return this.literatureCitations.filter((citation) => citation.source_link_id === sourceLinkId);
        },

        literatureCitationFieldValue(value) {
            const field = proposalCitationField(value);

            if (!field) return '';

            if (field.key.startsWith('methodology.')) {
                return String(this.methodology?.[field.key.split('.')[1]] || '');
            }

            return String({
                executive_brief: this.executiveBrief,
                rationale: this.rationale,
                general_objective: this.generalObjective,
                introduction: this.introduction,
                related_literature: this.relatedLiterature,
            }[field.key] || '');
        },

        citationMarkerSourceIds() {
            return proposalCitationFieldIds().flatMap((fieldId) => {
                const template = document.createElement('template');
                template.innerHTML = this.literatureCitationFieldValue(fieldId);

                return [...template.content.querySelectorAll('[data-proposal-citation]')]
                    .map((marker) => marker.getAttribute('data-proposal-citation'));
            });
        },

        activeProposalCitations() {
            return this.literatureCitations.filter((citation) => {
                const field = proposalCitationField(citation.field);
                const template = document.createElement('template');
                template.innerHTML = this.literatureCitationFieldValue(field?.key);
                const hasMarker = [...template.content.querySelectorAll('[data-proposal-citation]')]
                    .some((marker) => Number(marker.getAttribute('data-proposal-citation')) === citation.source_link_id);
                return Boolean(field && hasMarker);
            });
        },

        citationReferenceSourceIds() {
            return orderedCitationSourceIds(this.citationMarkerSourceIds(), this.activeProposalCitations())
                .filter((sourceLinkId) => this.literatureSources.some((source) => Number(source.id) === sourceLinkId));
        },

        citationReferenceSources() {
            return this.citationReferenceSourceIds()
                .map((sourceLinkId) => this.literatureSources.find((source) => Number(source.id) === sourceLinkId))
                .filter(Boolean);
        },

        citationReferenceNumber(source) {
            const index = this.citationReferenceSourceIds().indexOf(Number(source?.id));

            return index === -1 ? null : index + 1;
        },

        citationReferenceNumberIncluding(source) {
            const sourceLinkId = Number(source?.id);

            if (!Number.isInteger(sourceLinkId) || sourceLinkId < 1) return null;

            const sourceIds = this.citationReferenceSourceIds();

            if (!sourceIds.includes(sourceLinkId)) sourceIds.push(sourceLinkId);

            return sourceIds.indexOf(sourceLinkId) + 1;
        },

        citationReferenceNumbers() {
            return Object.fromEntries(this.citationReferenceSourceIds().map((sourceLinkId, index) => [sourceLinkId, index + 1]));
        },

        literatureSourceUsage(source) {
            const sourceLinkId = Number(source?.id);
            const fields = [...new Set(this.activeProposalCitations()
                .filter((citation) => citation.source_link_id === sourceLinkId)
                .map((citation) => proposalCitationField(citation.field))
                .filter(Boolean)
                .map((field) => field.label))];

            return {
                linked: sourceLinkId > 0,
                usedInProposal: fields.length > 0,
                usedInRrl: fields.some((field) => field.startsWith('XI. Review of Related Literature')),
                sections: fields,
                sectionSummary: fields.join(', '),
                addedToReferences: this.citationReferenceNumber(source) !== null,
                referenceNumber: this.citationReferenceNumber(source),
            };
        },

        escapeLiteratureHtml(value) {
            return String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        },

        referenceTextForCitationSource(source, referenceNumber) {
            const reference = String(source?.reference || '').trim();

            if (!reference) return '';

            return /^\s*\[\d+\]/.test(reference)
                ? reference.replace(/^\s*\[\d+\]/, `[${referenceNumber}]`)
                : `[${referenceNumber}] ${reference}`;
        },

        removeManagedReferenceBlocks(value) {
            const template = document.createElement('template');
            template.innerHTML = String(value || '');
            const normalize = (text) => this.plainText(text)
                .replace(/^\s*\[\d+\]\s*/, '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
            const identities = this.citationReferenceSources()
                .map((source) => source.reference)
                .map((identity) => normalize(identity))
                .filter(Boolean);
            const seenManualReferences = new Set();

            [...template.content.querySelectorAll('[data-proposal-managed-reference], p, li')].forEach((block) => {
                const text = normalize(block.textContent || '');
                const isManagedReference = block.hasAttribute('data-proposal-managed-reference')
                    || identities.includes(text);

                if (isManagedReference || (text && seenManualReferences.has(text))) {
                    block.remove();

                    return;
                }

                if (text) seenManualReferences.add(text);
            });

            return template.innerHTML.trim();
        },

        synchronizeLiteratureCitations() {
            const references = this.citationReferenceSources()
                .map((source, index) => ({
                    source,
                    reference: this.referenceTextForCitationSource(source, index + 1),
                }))
                .filter(({ reference }) => Boolean(reference))
                .map(({ source, reference }) => `<p data-proposal-managed-reference="${Number(source.id)}">${this.escapeLiteratureHtml(reference)}</p>`)
                .join('');
            const manualReferences = this.removeManagedReferenceBlocks(this.references);

            if (references) {
                this.references = manualReferences ? `${manualReferences}${references}` : references;
            } else {
                this.references = manualReferences;
            }

            proposalCitationFieldIds().forEach((fieldId) => {
                window.syncProposalCitationMarkers?.(fieldId, this.citationReferenceNumbers());
            });
            this.$nextTick(() => {
                document.getElementById('literature-citations')?.dispatchEvent(new Event('input', { bubbles: true }));
                this.notifyLiteratureFieldChanged('references');
                this.triggerDetailedProposalAutoSave();
            });
        },

        recordLiteratureCitation(source, field, selectedText = '', locator = '') {
            const sourceLinkId = Number(source?.id);
            const normalizedSelectedText = String(selectedText || '').trim().slice(0, 2000);
            const normalizedLocator = proposalCitationLocator(locator);

            if (!sourceLinkId) return null;

            const duplicate = this.literatureCitations.find((citation) => (
                citation.source_link_id === sourceLinkId
                && citation.field === field
                && citation.selected_text === normalizedSelectedText
                && citation.locator === normalizedLocator
            ));

            if (duplicate) return this.citationReferenceNumber(source) ?? this.citationReferenceNumberIncluding(source);

            this.literatureCitations.push({
                id: window.crypto?.randomUUID?.() || `citation-${Date.now()}-${sourceLinkId}`,
                source_link_id: sourceLinkId,
                literature_source_id: Number(source.literature_source_id || 0),
                field,
                selected_text: normalizedSelectedText,
                locator: normalizedLocator,
                created_at: new Date().toISOString(),
            });

            return this.citationReferenceNumber(source) ?? this.citationReferenceNumberIncluding(source);
        },

        removeCitationSource(source) {
            const sourceLinkId = Number(source?.id);

            if (!sourceLinkId || !window.confirm('Remove this source’s citation markers from every proposal section and remove its generated reference? Your wording will remain.')) return;

            this.literatureCitations = this.literatureCitations.filter((citation) => citation.source_link_id !== sourceLinkId);
            proposalCitationFieldIds().forEach((fieldId) => {
                window.removeProposalCitationMarkers?.(fieldId, sourceLinkId);
            });
            this.synchronizeLiteratureCitations();
            this.literatureSourceNotice = 'Citation markers were removed from every proposal section, along with the generated reference. The source remains saved.';
        },

        openCitationPicker(selection) {
            const field = proposalCitationField(selection?.fieldId);
            const textarea = field ? document.getElementById(field.id) : null;
            const currentSelection = proposalCitationSelection(field?.id, textarea?._semanticEditor, textarea?._semanticCitationRange);

            if (!field || !currentSelection) return;

            this.citationPickerSelection = {
                fieldId: field.id,
                fieldKey: field.key,
                sectionLabel: field.label,
                selectedText: selection.editing ? '' : currentSelection.selectedText,
                collapsed: currentSelection.collapsed,
                contextText: currentSelection.contextText,
                editing: Boolean(selection.editing),
            };
            this.citationPickerQuery = '';
            this.citationPickerResults = [];
            this.citationPickerError = '';
            this.openSourceWorkspace('paper', true);
            this.citationPickerLocator = proposalCitationLocator(selection.locator);
            if (selection.editing) this.sourceWorkspaceCitationSource = this.literatureSources.find((source) => Number(source.id) === Number(selection.sourceId)) || null;
        },

        openSourceWorkspaceFromToolbar(selection) {
            const field = proposalCitationField(selection?.fieldId);
            const textarea = field ? document.getElementById(field.id) : null;
            const savedRange = textarea?._semanticApi?.selection?.();
            if (savedRange && proposalCitationSelection(field.id, textarea._semanticEditor, savedRange)) {
                textarea._semanticCitationRange = savedRange.cloneRange();
            }
            const currentSelection = proposalCitationSelection(field?.id, textarea?._semanticEditor, textarea?._semanticCitationRange);
            this.citationPickerSelection = currentSelection || null;
            this.citationPickerLocator = '';
            this.citationPickerError = '';
            this.openSourceWorkspace('paper');
        },

        sourceWorkspaceCanCite() {
            const field = proposalCitationField(this.citationPickerSelection?.fieldId);
            const textarea = field ? document.getElementById(field.id) : null;
            return Boolean(this.sourceWorkspaceCitationMode) && Boolean(proposalCitationSelection(field?.id, textarea?._semanticEditor, textarea?._semanticCitationRange))
                && (!this.citationPickerSelection?.editing || Boolean(textarea?._semanticCitationEditingMarker?.isConnected));
        },

        async insertSourceWorkspacePaperDraft(draft) {
            const paragraph = String(draft?.paragraph || '').trim();
            const source = this.literatureSources.find((item) => Number(item.id) === Number(draft?.source_link_id));
            if (!source || paragraph.length < 40 || paragraph.length > 5000 || !['full_text', 'abstract'].includes(draft?.basis)) {
                throw new Error('Review a complete draft from a source linked to this proposal before inserting it.');
            }
            const normalized = this.plainText(paragraph).toLowerCase().replace(/\s+/g, ' ');
            if (normalized && this.plainText(this.relatedLiterature).toLowerCase().replace(/\s+/g, ' ').includes(normalized)) {
                throw new Error('This reviewed paragraph is already in the RRL.');
            }
            const url = String(config.literatureDraftUpdateUrlTemplate || '')
                .replace('__proposal_literature_source__', encodeURIComponent(String(source.id)));
            if (!url) throw new Error('The proposal source is unavailable. Reopen the paper and try again.');
            const payload = await this.sourceWorkspaceRequest(url, { method: 'PUT', body: JSON.stringify({
                rrl_note: paragraph, rrl_draft_status: 'confirmed', rrl_evidence_basis: draft.basis,
            }) });
            if (Number(payload.source?.id) !== Number(source.id) || payload.source?.rrl_draft_status !== 'confirmed'
                || payload.source?.rrl_evidence_basis !== draft.basis) throw new Error('The reviewed draft could not be confirmed for this source.');
            this.upsertLiteratureSource(payload.source);
            const number = this.recordLiteratureCitation(payload.source, 'related_literature', paragraph);
            this.relatedLiterature += `<p>${this.escapeLiteratureHtml(paragraph).replaceAll('\n', '<br>')}<span data-proposal-citation="${Number(source.id)}"> [${number}]</span></p>`;
            this.synchronizeLiteratureCitations();
            this.notifyLiteratureFieldChanged('related-literature');
            this.markProposalPreviewStale();
            this.triggerDetailedProposalAutoSave();
            return true;
        },

        insertSourceWorkspaceDraft(draft, fieldId = this.sourceWorkspaceDestination || 'related-literature') {
            const textarea = document.getElementById(fieldId);
            if (!(textarea instanceof HTMLTextAreaElement)) {
                this.sourceWorkspaceError = 'Choose an available writing section before inserting the draft.';
                return false;
            }

            const currentEvidence = Array.isArray(draft?.evidence) && draft.evidence.every((item) => {
                const passage = this.sourceWorkspaceEvidenceCache?.[item.source_link_id]?.passages
                    ?.find((saved) => String(saved.id) === String(item.passage_id));
                return passage && String(passage.quote || '').trim() === String(item.quote || '').trim()
                    && String(passage.note || '').trim() === String(item.note || '').trim()
                    && Number(passage.page || 0) === Number(item.page || 0);
            });
            if (!currentEvidence) {
                this.sourceWorkspaceError = 'The source evidence changed. Review its current passages and prepare a new draft.';
                return false;
            }

            const insertion = prepareReviewedSourceDraft(draft, this.literatureSources, fieldId,
                (source) => this.citationReferenceNumber(source) ?? this.citationReferenceNumberIncluding(source));
            if (!insertion) {
                this.sourceWorkspaceError = 'This draft needs saved evidence from sources linked to this paper.';
                return false;
            }
            const currentHtml = textarea._semanticEditor?.innerHTML ?? textarea.value;
            const currentDocument = document.createElement('template');
            currentDocument.innerHTML = currentHtml;
            const alreadyPresent = [...currentDocument.content.querySelectorAll('p, li')].some((block) => {
                const copy = block.cloneNode(true);
                copy.querySelectorAll('[data-proposal-citation]').forEach((marker) => marker.remove());
                const sameParagraph = String(copy.textContent || '').replace(/\s+/g, ' ').trim()
                    === insertion.paragraph.replace(/\s+/g, ' ').trim();
                return sameParagraph && insertion.citations.every(({ source, locator }) => (
                    [...block.querySelectorAll('[data-proposal-citation]')].some((marker) =>
                        Number(marker.getAttribute('data-proposal-citation')) === Number(source.id)
                        && proposalCitationLocator(marker.getAttribute('data-proposal-locator')) === locator)
                ));
            });
            if (alreadyPresent || this.sourceWorkspaceInsertedDrafts.includes(insertion.identity)) {
                this.sourceWorkspaceNotice = 'This reviewed draft is already inserted in the selected section.';
                return false;
            }

            const html = `${currentHtml}${insertion.html}`;
            const field = insertion.field;
            if (field.key.startsWith('methodology.')) {
                this.methodology[field.key.split('.')[1]] = html;
            } else {
                const property = {
                    executive_brief: 'executiveBrief', rationale: 'rationale', general_objective: 'generalObjective',
                    introduction: 'introduction', related_literature: 'relatedLiterature',
                }[field.key];
                if (!property) return false;
                this[property] = html;
            }
            textarea.value = html;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            insertion.citations.forEach(({ source, locator }) => {
                this.recordLiteratureCitation(source, field.key, insertion.paragraph, locator);
            });
            this.synchronizeLiteratureCitations();
            this.sourceWorkspaceInsertedDrafts = [...this.sourceWorkspaceInsertedDrafts, insertion.identity].slice(-20);
            this.sourceWorkspaceNotice = `Reviewed text was added to ${field.label}; its citations and references are synchronized.`;
            this.literatureSourceNotice = this.sourceWorkspaceNotice;
            this.markProposalPreviewStale();
            this.triggerDetailedProposalAutoSave();
            return true;
        },

        closeCitationPicker() {
            this.citationPickerOpen = false;
            this.citationPickerSelection = null;
            this.citationPickerError = '';
            document.body.classList.remove('overflow-y-hidden');
        },

        async searchCitationLibrary() {
            const query = this.citationPickerQuery.trim();

            if (query.length < 2) {
                this.citationPickerError = 'Enter at least two characters to search the shared literature library.';
                return;
            }

            this.citationPickerLoading = true;
            this.citationPickerError = '';

            try {
                const url = new URL(config.literatureLibrarySearchUrl || '', window.location.origin);
                url.searchParams.set('query', query);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const payload = await response.json();

                if (!response.ok) throw new Error(payload.message || 'The shared library could not be searched right now.');

                this.citationPickerResults = Array.isArray(payload.sources) ? payload.sources : [];
            } catch (error) {
                this.citationPickerError = error.message || 'The shared library could not be searched right now.';
            } finally {
                this.citationPickerLoading = false;
            }
        },

        async linkExistingLibrarySource(librarySource) {        const existing = this.literatureSources.find((source) => Number(source.literature_source_id) === Number(librarySource?.id));

            if (existing) return existing;

            const sourceId = Number(librarySource?.id);
            const attachUrl = String(config.literatureAttachUrlTemplate || '')
                .replace('__literature_source__', encodeURIComponent(String(sourceId)));

            if (!sourceId || !attachUrl) return null;

            const response = await fetch(attachUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ research_context: [`Citation from ${this.citationPickerSelection?.sectionLabel || 'proposal section'}`] }),
            });
            const payload = await response.json();

            if (!response.ok || !payload.source?.id) {
                throw new Error(payload.message || 'This shared paper could not be linked to the proposal.');
            }

            this.upsertLiteratureSource(payload.source);

            return payload.source;
        },

        linkedCitationSourceForLibrarySource(librarySource) {
            return this.literatureSources.find(
                (source) => Number(source.literature_source_id) === Number(librarySource?.id),
            ) || null;
        },

        async citeSelectedText(source, sourceIsLinked = true) {
            if (!this.sourceWorkspaceCanCite() || this.citationPickerSaving) return false;

            this.citationPickerSaving = true;
            this.citationPickerError = '';
            const previousCitations = [...this.literatureCitations];
            let markerInserted = false;

            try {
                const linkedSource = sourceIsLinked
                    ? this.literatureSources.find((item) => Number(item.id) === Number(source?.id))
                    : await this.linkExistingLibrarySource(source);

                if (!linkedSource) throw new Error('Choose a paper from the proposal library or shared library first.');

                const referenceNumber = this.recordLiteratureCitation(
                    linkedSource,
                    this.citationPickerSelection.fieldKey,
                    this.citationPickerSelection.selectedText,
                    this.citationPickerLocator,
                );
                markerInserted = window.insertProposalCitationMarker?.(
                    this.citationPickerSelection.fieldId,
                    linkedSource.id,
                    referenceNumber,
                    this.citationPickerLocator,
                );

                if (!markerInserted) {
                    this.literatureCitations = previousCitations;
                    throw new Error('Place the cursor in the writing field again, then choose Insert citation.');
                }

                this.synchronizeLiteratureCitations();
                this.literatureSourceNotice = `Citation added to ${this.citationPickerSelection.sectionLabel}; its IEEE reference is synchronized in Section XVI.`;
                if (!this.sourceWorkspaceOpen) this.closeCitationPicker();
                return true;
            } catch (error) {
                if (!markerInserted) this.literatureCitations = previousCitations;
                this.citationPickerError = error.message || 'The citation could not be added.';
                this.sourceWorkspaceError = this.citationPickerError;
                return false;
            } finally {
                this.citationPickerSaving = false;
            }
        },

        literatureSearchContextOptions() {
            return [
                { key: 'title', label: 'Project title', value: this.proposalTitle },
                { key: 'generalObjective', label: 'General objective', value: this.generalObjective },
                {
                    key: 'specificObjectives',
                    label: 'Specific objectives',
                    value: this.specificObjectives.map((objective) => objective.description).join(' '),
                },
                { key: 'researchAgenda', label: 'Research agenda', value: this.researchAgenda },
                { key: 'methodology', label: 'Methodology', value: this.methodology.research_design },
            ].map((context) => ({
                ...context,
                available: this.literatureSearchText(context.value).length >= 3,
            }));
        },

        toggleLiteratureSearchContext(key) {
            if (!Object.prototype.hasOwnProperty.call(this.literatureSearchContext, key)) return;

            this.literatureSearchContext[key] = !this.literatureSearchContext[key];
        },

        literatureSearchText(value) {
            return this.plainText(value)
                .replace(/[^\p{L}\p{N}\s-]+/gu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        },

        literatureSearchExcerpt(value, maximumWords) {
            return this.literatureSearchText(value)
                .split(' ')
                .filter(Boolean)
                .slice(0, maximumWords)
                .join(' ');
        },

        suggestedLiteratureContext() {
            const maximumWords = {
                title: 14,
                generalObjective: 12,
                specificObjectives: 18,
                researchAgenda: 8,
                methodology: 10,
            };

            return this.literatureSearchContextOptions()
                .filter((context) => context.available && this.literatureSearchContext[context.key])
                .map((context) => ({
                    ...context,
                    excerpt: this.literatureSearchExcerpt(context.value, maximumWords[context.key]),
                }))
                .filter((context) => context.excerpt);
        },

        suggestedLiteratureContextLabels() {
            return this.suggestedLiteratureContext().map((context) => context.label);
        },

        buildSuggestedLiteratureQuery() {
            return buildLiteratureSearchQuery(this.suggestedLiteratureContext());
        },

        refreshSuggestedLiteratureQuery() {
            this.literatureSearchQuery = this.buildSuggestedLiteratureQuery();
            this.literatureSearchError = '';
            this.literatureSearchNotice = this.literatureSearchQuery
                ? `Suggested from: ${this.suggestedLiteratureContextLabels().join(', ')}. Edit it before searching if needed.`
                : 'Add a project title, objective, research agenda, or methodology detail to create a suggested query.';
        },

        literatureResultKey(result) {
            return String(result?.doi || result?.provider_identifier || result?.url || result?.title || '')
                .trim()
                .toLowerCase();
        },

        linkedLiteratureSourceForResult(result) {
            const doi = String(result?.doi || '').trim().toLowerCase().replace(/^https?:\/\/(?:dx\.)?doi\.org\//, '');
            const title = String(result?.title || '').trim().toLowerCase().replace(/\s+/g, ' ');

            return this.literatureSources.find((source) => {
                const sourceDoi = String(source?.doi || '').trim().toLowerCase().replace(/^https?:\/\/(?:dx\.)?doi\.org\//, '');
                const sourceTitle = String(source?.title || '').trim().toLowerCase().replace(/\s+/g, ' ');

                return (doi && sourceDoi === doi) || (title && sourceTitle === title);
            }) || null;
        },

        isSavingSuggestedLiterature(result) {
            return this.literatureSearchSavingKey === this.literatureResultKey(result);
        },

        hasUsableSuggestedAbstract(result) {
            const abstract = String(result?.description || '').trim();

            return abstract.length >= 80 && abstract !== 'No description available from source.';
        },

        recordLiteratureSearch(query, resultCount) {
            const entry = {
                id: `${query}-${Date.now()}`,
                query,
                label: literatureSearchHistoryTitle(query, this.proposalTitle),
                context: this.suggestedLiteratureContextLabels(),
                resultCount,
                searchedAt: new Date().toISOString(),
            };
            const normalizedQuery = query.toLowerCase();

            this.literatureSearchHistory = [
                entry,
                ...this.literatureSearchHistory.filter((history) => history.query.toLowerCase() !== normalizedQuery),
            ].slice(0, 8);
            this.$nextTick(() => this.triggerDetailedProposalAutoSave());
        },

        literatureSearchHistoryLabel(entry) {
            const context = Array.isArray(entry.context) && entry.context.length
                ? `from ${entry.context.join(', ')}`
                : 'custom query';
            const resultLabel = Number(entry.resultCount) === 1 ? 'result' : 'results';

            return `${entry.resultCount || 0} ${resultLabel} · ${context}`;
        },

        literatureSearchHistoryTitle(entry) {
            return String(entry?.label || literatureSearchHistoryTitle(entry?.query, this.proposalTitle) || 'Literature search');
        },

        literatureSearchHistorySummary(entry) {
            const count = Number(entry?.resultCount) || 0;
            const context = Array.isArray(entry?.context) && entry.context.length
                ? entry.context.join(', ')
                : 'custom query';

            return `${count === 0 ? 'No matches' : `${count} ${count === 1 ? 'result' : 'results'}`} · ${context}`;
        },

        runLiteratureSearchHistory(entry) {
            this.literatureSearchQuery = String(entry?.query || '');
            this.searchSuggestedLiterature();
        },

        async searchSuggestedLiterature() {
            if (this.literatureSearchLoading) return;

            const query = this.literatureSearchQuery.trim();

            if (query.length < 3) {
                this.literatureSearchError = 'Enter at least three characters to search the academic indexes.';
                return;
            }

            this.literatureSearchLoading = true;
            this.literatureSearchError = '';
            this.literatureSearchNotice = '';
            this.literatureSearchResults = [];
            this.literatureSearchKeywords = [];

            try {
                const payload = await fetchLiteratureSearch(
                    config.literatureSearchUrl || '',
                    config.csrfToken,
                    buildLiteratureSearchPayload(query, this.literatureSearchUseContext ? this.suggestedLiteratureContext() : [], this.literatureSearchFilters),
                );

                this.literatureSearchResults = Array.isArray(payload.results)
                    ? payload.results.map((result) => {
                        const linkedSource = this.linkedLiteratureSourceForResult(result);

                        return {
                            ...result,
                            _linked: Boolean(linkedSource),
                            _linkedSource: linkedSource,
                            _actionNotice: '',
                        };
                    })
                    : [];
                this.literatureSearchKeywords = Array.isArray(payload.search_keywords) ? payload.search_keywords : [];
                this.literatureSearchNotice = [payload.provider_notice, payload.search_notice, payload.query_guidance?.message, payload.query_guidance?.suggestion].filter(Boolean).join(' ') || (this.literatureSearchResults.length
                    ? 'Review the abstract and source record before linking a paper.'
                    : 'No matching papers were returned. Edit the query or include different proposal details.');
                this.recordLiteratureSearch(query, this.literatureSearchResults.length);
            } catch (error) {
                this.literatureSearchError = error.message || 'Related literature could not be searched right now.';
            } finally {
                this.literatureSearchLoading = false;
            }
        },

        libraryPayloadForSuggestedResult(result) {
            return {
                title: result.title,
                description: result.description,
                authors: result.authors,
                year: result.year,
                publication_date: result.publication_date,
                venue: result.venue,
                volume: result.volume,
                issue: result.issue,
                pages: result.pages,
                publisher: result.publisher,
                doi: result.doi,
                url: result.url,
                full_text_token: result.full_text_token,
                source: result.source,
                provider_identifier: result.provider_identifier,
                citation_count: result.citation_count,
                is_open_access: result.is_open_access,
                access_status: result.access_status,
                type: result.type,
            };
        },

        upsertLiteratureSource(source) {
            const index = this.literatureSources.findIndex((item) => Number(item.id) === Number(source.id));

            if (index === -1) {
                this.literatureSources = [...this.literatureSources, source];
            } else {
                this.literatureSources.splice(index, 1, source);
            }

            this.literatureSearchResults.forEach((result) => {
                if (Number(result?._linkedSource?.id) !== Number(source.id)) return;

                result._linked = true;
                result._linkedSource = source;
            });
        },

        async ensureSuggestedLiteratureLinked(result) {
            if (result?._linkedSource?.id) return result._linkedSource;

            const resultKey = this.literatureResultKey(result);

            if (!resultKey || this.literatureSearchSavingKey) return null;

            this.literatureSearchSavingKey = resultKey;
            this.literatureSearchError = '';

            try {
                const saveResponse = await fetch(config.literatureLibrarySaveUrl || '', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.libraryPayloadForSuggestedResult(result)),
                });
                const savePayload = await saveResponse.json();

                if (!saveResponse.ok || !savePayload.source?.id) {
                    throw new Error(savePayload.message || 'This paper could not be saved to the shared literature library.');
                }

                const attachUrl = String(config.literatureAttachUrlTemplate || '')
                    .replace('__literature_source__', encodeURIComponent(String(savePayload.source.id)));
                const attachResponse = await fetch(attachUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ research_context: this.suggestedLiteratureContextLabels() }),
                });
                const attachPayload = await attachResponse.json();

                if (!attachResponse.ok || !attachPayload.source?.id) {
                    throw new Error(attachPayload.message || 'The shared paper was saved but could not be linked to this proposal.');
                }

                result._linked = true;
                result._linkedSource = attachPayload.source;
                this.upsertLiteratureSource(attachPayload.source);

                return attachPayload.source;
            } catch (error) {
                this.literatureSearchError = error.message || 'This paper could not be linked to the proposal.';

                return null;
            } finally {
                this.literatureSearchSavingKey = '';
            }
        },

        async saveSuggestedLiterature(result) {
            const source = await this.ensureSuggestedLiteratureLinked(result);

            if (source) {
                result._actionNotice = 'Saved to this proposal library.';
                this.literatureSearchNotice = result._actionNotice;
            }
        },

        async addSuggestedLiteratureReference(result) {
            const source = await this.ensureSuggestedLiteratureLinked(result);

            if (!source) return;

            result._actionNotice = 'Saved to this proposal library. Cite the RRL text it supports and ATHENA will add the IEEE reference automatically.';
        },

        async prepareSuggestedLiteratureReview(result) {
            const source = await this.ensureSuggestedLiteratureLinked(result);

            if (!source) return;

            this.openLiteratureReview({
                ...source,
                description: String(source.description || result.description || ''),
                full_text_token: source.full_text_token || result.full_text_token || null,
                full_text_url: source.full_text_url || result.full_text_url || null,
            });
        },

        prepareLinkedLiteratureReview(source) {
            if (!source?.id) return;

            this.openLiteratureReview(source);
        },

        openLiteratureReview(source) {
            this.literatureReviewSource = source;
            this.literatureReviewDraft = String(source.rrl_note || '');
            this.literatureReviewPreviousContext = this.literatureReviewContextFromRrl();
            this.literatureReviewRelationship = 'standalone';
            this.literatureReviewTransition = '';
            this.literatureReviewBasis = 'abstract';
            this.literatureReviewFullText = '';
            this.literatureReviewFullTextError = '';
            this.literatureReviewNotice = source.rrl_evidence_basis === 'full_text'
                ? 'Your saved draft is available. Load the public full text again to regenerate or confirm it from full-paper evidence.'
                : '';
            this.literatureReviewError = '';
            this.literatureReviewOpen = true;
            this.literatureWorkspaceOpen = true;
            this.literatureWorkspaceView = 'review';
            document.body.classList.add('overflow-y-hidden');
        },

        closeLiteratureReview() {
            this.literatureReviewOpen = false;
            this.literatureReviewError = '';
            this.closeLiteratureWorkspace();
        },

        literatureReviewEvidence() {
            if (this.literatureReviewBasis === 'full_text' && this.literatureReviewFullText) {
                return this.literatureReviewFullText;
            }

            return String(this.literatureReviewSource?.description || '');
        },

        hasLiteratureReviewEvidence() {
            const minimumLength = this.literatureReviewBasis === 'full_text' ? 500 : 80;

            return this.literatureReviewEvidence().trim().length >= minimumLength;
        },

        literatureReviewEvidenceMessage() {
            if (this.hasLiteratureReviewEvidence()) return '';

            return this.literatureReviewBasis === 'full_text'
                ? 'Load the public full text before generating a draft from it.'
                : 'This source has no usable abstract (at least 80 characters). Load its public full text, choose another source, or write the notes manually.';
        },

        literatureReviewWordCount() {
            const text = this.literatureReviewDraft.trim();

            return text ? text.split(/\s+/).length : 0;
        },

        literatureReviewContextFromRrl() {
            const paragraphs = this.plainText(this.relatedLiterature)
                .split(/\n+/)
                .map((paragraph) => paragraph.replace(/\s*\[\d+\]\s*/g, ' ').replace(/\s+/g, ' ').trim())
                .filter(Boolean);
            const lastParagraph = paragraphs.at(-1) || '';
            const sentences = lastParagraph.match(/[^.!?]+(?:[.!?]+|$)/g) || [];
            const context = (sentences.length > 1 ? sentences.slice(-2).join(' ') : lastParagraph).trim();

            return context.slice(-1600).trim();
        },

        literatureReviewRelationshipLabel() {
            return ({
                supports: 'Supports previous finding',
                extends: 'Extends previous finding',
                contrasts: 'Contrasts with previous finding',
                gap: 'Reveals a research gap',
                related: 'Shifts to a related theme',
                standalone: 'Standalone paragraph',
            })[this.literatureReviewRelationship] || 'Standalone paragraph';
        },

        literatureReviewRelationshipDescription() {
            return ({
                supports: 'The new evidence reinforces the direction of the preceding discussion.',
                extends: 'The new evidence adds another dimension to the preceding discussion.',
                contrasts: 'The new evidence differs from the preceding discussion without overstating disagreement.',
                gap: 'The new evidence helps identify what the preceding discussion has not yet addressed.',
                related: 'The new evidence moves the RRL into a connected subtopic.',
                standalone: 'No responsible relationship was inferred, so the paragraph opens independently.',
            })[this.literatureReviewRelationship] || '';
        },

        literatureReviewHasConnection() {
            return Boolean(this.literatureReviewPreviousContext && this.literatureReviewRelationship !== 'standalone');
        },
        async loadLiteratureReviewFullText() {
            const sourceToken = this.literatureReviewSource?.full_text_token;

            if (!sourceToken || this.literatureReviewLoadingFullText) return;

            this.literatureReviewLoadingFullText = true;
            this.literatureReviewFullTextError = '';

            try {
                const response = await fetch(config.literatureFullTextPreviewUrl || '', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ source_token: sourceToken }),
                });
                const payload = await response.json();

                if (!response.ok || String(payload.preview_text || '').trim().length < 500) {
                    throw new Error(payload.message || 'The public full text could not be loaded. The indexed abstract remains available.');
                }

                this.literatureReviewFullText = String(payload.preview_text);
                this.literatureReviewBasis = 'full_text';
                this.literatureReviewNotice = payload.notice || 'Public full text loaded only for this review.';
            } catch (error) {
                this.literatureReviewFullTextError = error.message || 'The public full text could not be loaded.';
            } finally {
                this.literatureReviewLoadingFullText = false;
            }
        },

        async generateLiteratureReviewDraft(connectionMode = 'auto') {
            if (this.literatureReviewGenerating || !this.hasLiteratureReviewEvidence()) return;

            this.literatureReviewGenerating = true;
            this.literatureReviewError = '';
            this.literatureReviewNotice = '';
            const normalizedConnectionMode = connectionMode === 'standalone' ? 'standalone' : 'auto';

            try {
                const response = await fetch(config.literatureSynthesisUrl || '', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        title: this.literatureReviewSource?.title,
                        authors: this.literatureReviewSource?.authors,
                        year: this.literatureReviewSource?.year,
                        abstract: String(this.literatureReviewSource?.description || '').slice(0, 6000),
                        is_open_access: this.literatureReviewBasis === 'full_text',
                        evidence_basis: this.literatureReviewBasis,
                        proposal_draft_id: Number(config.proposalDraftId),
                        source_link_id: Number(this.literatureReviewSource?.id),
                        proposal_title: this.proposalTitle,
                        preceding_rrl_context: normalizedConnectionMode === 'auto'
                            ? this.literatureReviewPreviousContext
                            : undefined,
                        connection_mode: normalizedConnectionMode,
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message = Object.values(payload.errors || {}).flat().join(' ') || payload.message;
                    throw new Error(message || (response.status === 419
                        ? 'Your session expired. Refresh the page, reopen this source, and try again.'
                        : 'ATHENA could not prepare an RRL draft right now. Please try again.'));
                }

                if (!String(payload.synthesis || '').trim()) throw new Error('No RRL paragraph was returned. Please generate the draft again.');

                this.literatureReviewDraft = String(payload.synthesis || '');
                this.literatureReviewRelationship = ['supports', 'extends', 'contrasts', 'gap', 'related'].includes(payload.relationship)
                    ? payload.relationship
                    : 'standalone';
                this.literatureReviewTransition = this.literatureReviewRelationship === 'standalone'
                    ? ''
                    : String(payload.transition || '').trim();
                this.literatureReviewNotice = payload.notice || 'Review the generated paragraph before confirming it.';
            } catch (error) {
                this.literatureReviewError = error.message || 'ATHENA could not prepare an RRL draft right now.';
            } finally {
                this.literatureReviewGenerating = false;
            }
        },

        async saveLiteratureReview(addToRelatedLiterature = false) {
            const source = this.literatureReviewSource;
            const draft = this.literatureReviewDraft.trim();

            if (!source?.id || draft.length < 40 || this.literatureReviewSaving || this.literatureReviewGenerating || this.literatureReviewLoadingFullText) return;

            this.literatureReviewSaving = true;
            this.literatureReviewError = '';

            try {
                const updateUrl = String(config.literatureDraftUpdateUrlTemplate || '')
                    .replace('__proposal_literature_source__', encodeURIComponent(String(source.id)));
                const response = await fetch(updateUrl, {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        rrl_note: draft,
                        rrl_draft_status: addToRelatedLiterature ? 'confirmed' : 'draft',
                        rrl_evidence_basis: this.literatureReviewBasis,
                    }),
                });
                const payload = await response.json();

                if (!response.ok || !payload.source?.id) {
                    throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'The reviewed RRL paragraph could not be confirmed.');
                }

                this.upsertLiteratureSource(payload.source);

                if (addToRelatedLiterature) {
                    this.addLiteratureSourceToRrl(payload.source, true);
                    this.literatureSourceNotice = 'RRL paragraph and its reference were added to Sections XI and XVI. Autosaving now.';
                } else {
                    this.literatureSourceNotice = 'RRL paragraph saved for later review. It has not been added to Section XI.';
                }

                this.closeLiteratureReview();
                if (addToRelatedLiterature) this.focusLiteratureDestination('rrl');
            } catch (error) {
                this.literatureReviewError = error.message || 'The reviewed RRL paragraph could not be saved.';
            } finally {
                this.literatureReviewSaving = false;
            }
        },

        plainText(value) {
            const template = document.createElement('template');
            template.innerHTML = String(value ?? '').replace(/<(?:br\s*\/?>|\/p|\/div|\/li)>/gi, '\n');

            return (template.content.textContent || '').trim();
        },

        nestedProposalFieldsNeedSave(data) {
            const descriptions = (rows) => Array.isArray(rows)
                ? rows.map((row) => typeof row === 'string' ? row : { description: String(row?.description ?? '') }) : rows;
            const hasDescriptions = (rows) => Array.isArray(rows) && rows.some((row) => this.plainText(typeof row === 'string' ? row : row?.description));
            const groups = (rows) => Array.isArray(rows) ? rows.map((group) => ({
                heading: String(group?.heading ?? ''), methods: hasDescriptions(group?.methods) ? descriptions(group.methods) : [],
            })) : rows;
            const differs = (saved, current) => JSON.stringify(saved) !== JSON.stringify(current);
            const savedObjectives = Array.isArray(data.specific_objectives) ? data.specific_objectives : data.objectives;
            if ((this.plainText(data.general_objective) || hasDescriptions(savedObjectives) || (typeof savedObjectives === 'string' && this.plainText(savedObjectives)))
                && (String(data.general_objective ?? '') !== this.generalObjective
                    || ((hasDescriptions(savedObjectives) || (typeof savedObjectives === 'string' && this.plainText(savedObjectives)))
                        && differs(descriptions(savedObjectives), descriptions(this.specificObjectives))))) return true;

            const savedGroups = data.specific_method_objectives;
            const hasGroups = Array.isArray(savedGroups) && savedGroups.some((group) => this.plainText(group?.heading) || hasDescriptions(group?.methods));
            if ((hasGroups || (!savedGroups?.length && this.plainText(data.methodology?.specific_methods)))
                && differs(groups(savedGroups), groups(this.specificMethodGroups))) return true;

            return Object.entries(this.expectedOutputs).some(([key, rows]) => {
                const saved = data.expected_outputs?.[key];
                return (hasDescriptions(saved) || (typeof saved === 'string' && this.plainText(saved)))
                    && differs(descriptions(saved), descriptions(rows));
            });
        },

        normalizedObjectives(generalValue, value, legacyValue = '') {
            let generalObjective = String(generalValue ?? '');
            let section = this.plainText(generalObjective) ? 'specific' : null;
            const source = Array.isArray(value) ? value : String(legacyValue || '')
                .split(/\r?\n/)
                .map((description) => ({ description }));
            const specificObjectives = [];

            source.forEach((objective) => {
                const description = this.plainText(objective?.description)
                    .replace(/^\s*(?:\d+[.)]|[-\u2022])\s*/, '');

                if (!description) return;

                if (/^general objectives?:?$/i.test(description)) {
                    section = 'general';

                    return;
                }

                if (/^specific objectives?:?$/i.test(description)) {
                    section = 'specific';

                    return;
                }

                if (section === 'general' && !this.plainText(generalObjective)) {
                    generalObjective = description;

                    return;
                }

                specificObjectives.push(this.newSpecificObjective({ description }));
            });

            return {
                generalObjective,
                specificObjectives: specificObjectives.length ? specificObjectives : [this.newSpecificObjective()],
            };
        },

        normalizedSpecificObjectives(value, legacyValue = '') {
            const objectives = Array.isArray(value) ? value : String(legacyValue || '')
                .split(/\r?\n/)
                .map((description) => ({ description: description.replace(/^\s*(?:\d+[.)]|[-•])\s*/, '') }));

            return objectives
                .map((objective) => this.newSpecificObjective(objective))
                .filter((objective) => objective.description.trim());
        },

        newSpecificObjective(values = {}) {
            this.nextId += 1;

            return {
                id: this.nextId,
                description: String(values.description ?? ''),
            };
        },

        addSpecificObjective() {
            this.specificObjectives.push(this.newSpecificObjective());
            this.triggerDetailedProposalAutoSave();
        },

        removeSpecificObjective(index) {
            this.specificObjectives.splice(index, 1);
            if (!this.specificObjectives.length) this.specificObjectives.push(this.newSpecificObjective());
            this.triggerDetailedProposalAutoSave();
        },

        moveSpecificObjective(index, direction) {
            const target = index + direction;

            if (target < 0 || target >= this.specificObjectives.length) return;

            [this.specificObjectives[index], this.specificObjectives[target]] = [
                this.specificObjectives[target],
                this.specificObjectives[index],
            ];
            this.triggerDetailedProposalAutoSave();
        },

        newSpecificMethod(values = {}) {
            this.nextId += 1;

            return {
                id: this.nextId,
                description: this.plainText(typeof values === 'string' ? values : values.description ?? ''),
            };
        },

        normalizedSpecificMethodRows(value) {
            return (Array.isArray(value) ? value : [])
                .map((method) => this.newSpecificMethod(method))
                .filter((method) => method.description.trim());
        },

        normalizedSpecificMethodGroups(value, legacyValue = '') {
            const savedGroups = Array.isArray(value) ? value : [];
            const legacyGroups = savedGroups.length ? [] : this.legacySpecificMethodGroups(legacyValue);
            const groups = (savedGroups.length ? savedGroups : legacyGroups)
                .map((group) => this.newSpecificMethodGroup(group))
                .filter((group) => group.heading || group.methods.some((method) => method.description));

            return groups.length ? groups : [this.newSpecificMethodGroup()];
        },

        legacySpecificMethodGroups(value) {
            const groups = [];
            let currentGroup = 0;

            this.plainText(value)
                .split(/\n+/)
                .map((line) => line.trim())
                .filter(Boolean)
                .forEach((line) => {
                    const objectiveHeading = line.match(/^([A-Z])\.\s+.+$/);

                    if (objectiveHeading) {
                        currentGroup = objectiveHeading[1].charCodeAt(0) - 65;
                        groups[currentGroup] ??= { heading: '', methods: [] };
                        groups[currentGroup].heading = line.replace(/^[A-Z]\.\s+/, '');

                        return;
                    }

                    const method = line.replace(/^\d+[.)]\s*/, '').trim();

                    if (!method) return;

                    groups[currentGroup] ??= { heading: '', methods: [] };
                    groups[currentGroup].methods.push({ description: method });
                });

            return groups;
        },

        newSpecificMethodGroup(values = {}) {
            this.nextId += 1;
            const methods = this.normalizedSpecificMethodRows(values.methods);

            return {
                id: this.nextId,
                heading: this.plainText(values.heading ?? ''),
                methods: methods.length ? methods : [this.newSpecificMethod()],
            };
        },

        addSpecificMethodGroup() {
            this.specificMethodGroups.push(this.newSpecificMethodGroup());
            this.triggerDetailedProposalAutoSave();
        },

        removeSpecificMethodGroup(index) {
            this.specificMethodGroups.splice(index, 1);
            this.triggerDetailedProposalAutoSave();
        },

        addSpecificMethod(objective) {
            objective.methods.push(this.newSpecificMethod());
            this.triggerDetailedProposalAutoSave();
        },

        removeSpecificMethod(objective, index) {
            objective.methods.splice(index, 1);
            this.triggerDetailedProposalAutoSave();
        },

        specificMethodGroupLetter(index) {
            let value = Number(index) + 1;
            let letter = '';

            while (value > 0) {
                value -= 1;
                letter = String.fromCharCode(65 + (value % 26)) + letter;
                value = Math.floor(value / 26);
            }

            return letter;
        },

        escapeSpecificMethodHtml(value) {
            return String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        },

        specificMethodsDocumentText() {
            const groups = this.specificMethodGroups
                .map((group, index) => ({
                    heading: this.plainText(group.heading),
                    methods: group.methods
                        .map((method) => this.plainText(method.description))
                        .filter(Boolean),
                    letter: this.specificMethodGroupLetter(index),
                }))
                .filter((group) => group.heading);

            if (!groups.length || groups.some((group) => !group.methods.length)) return '';

            return groups.map((group) => {
                const heading = this.escapeSpecificMethodHtml(`${group.letter}. ${group.heading}`);
                const methods = group.methods
                    .map((method) => `<li>${this.escapeSpecificMethodHtml(method).replace(/\r?\n/g, '<br>')}</li>`)
                    .join('');

                return `<p><strong>${heading}</strong></p><ol>${methods}</ol>`;
            }).join('');
        },

        normalizedExpectedOutputs(value) {
            const entries = Array.isArray(value)
                ? value
                : (String(value || '').trim() ? [{ description: value }] : []);

            return entries.map((entry) => this.newExpectedOutput(entry));
        },

        newExpectedOutput(values = {}) {
            this.nextId += 1;

            return {
                id: this.nextId,
                description: this.expectedOutputDescription(values),
            };
        },

        expectedOutputDescription(values) {
            const description = this.plainText(values.description);
            const quantity = Number(values.quantity);
            const unit = this.plainText(values.unit);

            if (!Number.isInteger(quantity) || quantity < 1 || !description) return description;
            if (description.includes(`(${quantity.toLocaleString('en-US')})`)) return description;

            const quantityWords = [
                '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
                'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen',
                'Eighteen', 'Nineteen', 'Twenty',
            ];
            const label = quantityWords[quantity] || String(quantity);

            return `${label} (${quantity.toLocaleString('en-US')}) ${unit} ${description}`.trim();
        },

        addExpectedOutput(key) {
            if (!Array.isArray(this.expectedOutputs[key])) this.expectedOutputs[key] = [];

            this.expectedOutputs[key].push(this.newExpectedOutput());
            this.triggerDetailedProposalAutoSave();
        },

        removeExpectedOutput(key, index) {
            this.expectedOutputs[key]?.splice(index, 1);
            this.triggerDetailedProposalAutoSave();
        },

        startDetailedProposalAutoSave() {
            const form = this.detailedProposalAutoSaveForm();

            if (!(form instanceof HTMLFormElement)) return;

            const shouldRecheckCompletion = this.autoSaveInitialContent || this.recheckCompletion;
            this.lastSavedDetailedProposal = shouldRecheckCompletion
                ? ''
                : this.detailedProposalFingerprint(form);

            const saveFieldChange = (event) => {
                if (event.target.id !== 'workspace-person-search') this.triggerDetailedProposalAutoSave();
            };
            form.addEventListener('input', saveFieldChange);
            form.addEventListener('input', (event) => {
                const citationField = proposalCitationField(event.target.id) || proposalCitationFieldIds()
                    .map((fieldId) => document.getElementById(fieldId))
                    .find((field) => field?._semanticEditor?.contains(event.target));
                if (citationField) this.$nextTick(() => this.synchronizeLiteratureCitations());
            });
            form.addEventListener('change', saveFieldChange);
            form.addEventListener('input', (event) => this.clearDetailedProposalFieldHighlight(event.target));
            form.addEventListener('change', (event) => this.clearDetailedProposalFieldHighlight(event.target));
            form.addEventListener('focusout', (event) => {
                const field = typeof event.target.checkValidity === 'function' ? event.target
                    : [...form.querySelectorAll('textarea')].find((textarea) => textarea._semanticEditor?.contains(event.target));
                if (!field || field.disabled || ['hidden', 'checkbox', 'radio', 'file'].includes(field.type)) return;
                const target = this.detailedProposalHighlightTarget(field);
                if (target?.contains(event.relatedTarget)) return;
                if (this.detailedProposalFieldIsValid(field)) this.clearDetailedProposalFieldHighlight(field);
                else this.highlightDetailedProposalField(field);
            });

            if (shouldRecheckCompletion) this.triggerDetailedProposalAutoSave();
        },

        detailedProposalAutoSaveForm() {
            return this.$refs.form;
        },

        detailedProposalFingerprint(form) {
            return proposalPaperFormFingerprint(this.detailedProposalFormData(form).entries(), [
                '_token',
                '_method',
                'document_version',
                'draft_version',
                'save_as_draft',
                'change_note',
            ]);
        },

        detailedProposalAutoSaveStatus(message, state = 'idle') {
            this.detailedProposalSaveState = state;
            const status = this.$el.querySelector('[data-proposal-autosave-status]');
            const messageElement = status?.querySelector('[data-proposal-autosave-message]');
            const indicator = status?.querySelector('[data-proposal-autosave-indicator]');

            if (messageElement instanceof HTMLElement) messageElement.textContent = message;

            if (indicator instanceof HTMLElement) {
                indicator.classList.remove('bg-gray-400', 'bg-amber-500', 'bg-slate-400', 'bg-green-600', 'bg-red-600');
                indicator.classList.add({
                    saving: 'bg-slate-400',
                    saved: 'bg-green-600',
                    error: 'bg-red-600',
                }[state] ?? 'bg-gray-400');
            }
        },

        get showDetailedProposalSaveStatus() {
            return !this.showCompletionChecklist || this.detailedProposalSaveState !== 'idle';
        },

        detailedProposalCompletionLabel() {
            if (this.detailedProposalComplete) return 'Complete';

            return this.detailedProposalStarted ? 'In progress' : 'Not started';
        },

        detailedProposalCompletionClasses() {
            if (this.detailedProposalComplete) return 'bg-green-100 text-green-800';

            return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
        },

        updateDetailedProposalCompletionStatus() {
            const status = document.querySelector('[data-detailed-proposal-completion-status]');

            if (!(status instanceof HTMLElement)) return;

            status.textContent = this.detailedProposalCompletionLabel();
            status.classList.remove(
                'bg-green-100',
                'text-green-800',
                'bg-amber-100',
                'text-amber-800',
                'bg-gray-100',
                'text-gray-600',
                'bg-slate-100',
                'text-slate-600',
                'dark:bg-slate-800',
                'dark:text-slate-300',
            );
            status.classList.add(...this.detailedProposalCompletionClasses().split(' '));
        },

        triggerDetailedProposalAutoSave() {
            this.markProposalPreviewStale();
            this.$el.dataset.paperDirty = 'true';
            this.autoSaveRevision += 1;
            this.$nextTick(() => this.scheduleDetailedProposalAutoSave());
        },

        scheduleDetailedProposalAutoSave() {
            if (this.autoSaveBlocked) return;

            window.clearTimeout(this.autoSaveTimer);

            const form = this.detailedProposalAutoSaveForm();

            if (!(form instanceof HTMLFormElement)) return;

            if (this.detailedProposalFingerprint(form) === this.lastSavedDetailedProposal) return;

            this.detailedProposalAutoSaveStatus('Saving changes soon…');
            this.autoSaveTimer = window.setTimeout(() => this.saveDetailedProposal(), 1200);
        },

        saveDetailedProposalNow() {
            window.clearTimeout(this.autoSaveTimer);

            return this.saveDetailedProposal();
        },

        detailedProposalShouldSaveAsDraft() {
            return this.$el.dataset.paperProjectDetailsComplete !== 'true';
        },

        async saveDetailedProposal() {
            const form = this.detailedProposalAutoSaveForm();

            if (!(form instanceof HTMLFormElement) || this.autoSaveInFlight || this.autoSaveBlocked) return;

            if (this.$el._proposalRecovery && (!await this.$el._proposalRecovery.beforeSave() || this.autoSaveInFlight || this.autoSaveBlocked)) return;

            const fingerprint = this.detailedProposalFingerprint(form);

            if (fingerprint === this.lastSavedDetailedProposal) return;

            const saveMode = form.querySelector('[data-paper-save-mode]');

            const savedRevision = this.autoSaveRevision;
            this.autoSaveInFlight = true;
            this.detailedProposalAutoSaveStatus('Saving changes…', 'saving');

            try {
                let submittedImageFiles;
                const { response, payload, completionErrors } = await saveProposalPaperWithDraftFallback({
                    saveAsDraft: this.detailedProposalShouldSaveAsDraft(),
                    save: async (saveAsDraft) => {
                        if (saveMode instanceof HTMLInputElement) {
                            saveMode.value = saveAsDraft ? '1' : '0';
                        }

                        const formData = this.detailedProposalFormData(form);
                        submittedImageFiles = new Map(this.methodologyImages.map((image) => [String(image.clientId), image.currentFile]));
                        const response = await uploadRequest(config.updateUrl || form.action, {
                            method: form.method,
                            body: formData,
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }, { root: form });

                        return { response, payload: await response.json() };
                    },
                });

                if (response.status === 422) {
                    this.completionErrors = payload.errors || {};
                    this.requirementsReviewed = true;
                    this.validationMessage = autoSaveValidationMessage(
                        payload,
                        'Please review the Detailed Research Proposal information.',
                    );

                    if (autoSaveHasStaleVersionError(payload)) {
                        this.autoSaveBlocked = true;
                        this.$el.querySelector('[data-proposal-stale-warning]')?.removeAttribute('hidden');
                        this.detailedProposalAutoSaveStatus('A newer saved version is available. Load it before saving.', 'error');

                        return;
                    }
                    this.detailedProposalAutoSaveStatus('Correct the highlighted information before it can be saved.', 'error');

                    return;
                }

                if (!response.ok) {
                    throw new Error(payload.message || 'The Detailed Research Proposal could not be saved.');
                }

                const documentVersion = form.querySelector('[name="document_version"]');
                const draftVersion = form.querySelector('[name="draft_version"]');

                if (documentVersion instanceof HTMLInputElement) documentVersion.value = String(payload.document_version);
                if (draftVersion instanceof HTMLInputElement) draftVersion.value = String(payload.draft_version);

                this.applySavedMethodologyImages(payload.methodology_images || [], submittedImageFiles);
                this.detailedProposalStarted = true;
                this.detailedProposalComplete = !payload.saved_as_draft;
                this.updateDetailedProposalCompletionStatus();
                this.lastSavedDetailedProposal = fingerprint;

                if (this.autoSaveRevision !== savedRevision) {
                    this.scheduleDetailedProposalAutoSave();

                    return;
                }

                this.lastSavedDetailedProposal = this.detailedProposalFingerprint(form);
                this.$el.dataset.paperDirty = 'false';
                this.completionErrors = payload.completion_errors || completionErrors || {};
                this.validationMessage = '';
                this.detailedProposalAutoSaveStatus(
                    payload.saved_as_draft ? 'Draft saved just now.' : 'Saved just now.',
                    'saved',
                );
            } catch (error) {
                this.$el._proposalRecovery?.connectionFailed();
                this.$el.dataset.paperDirty = 'true';

                if (error instanceof Error && error.message.includes('A teammate saved a newer version of this paper')) {
                    this.autoSaveBlocked = true;
                    this.$el.querySelector('[data-proposal-stale-warning]')?.removeAttribute('hidden');
                    this.detailedProposalAutoSaveStatus('A newer saved version is available. Load the latest version before saving.', 'error');

                    return;
                }

                this.detailedProposalAutoSaveStatus('Could not save. Check your connection, then continue editing.', 'error');
            } finally {
                this.autoSaveInFlight = false;
            }
        },

        applySavedMethodologyImages(savedImages, submittedImageFiles) {
            const imagesByClientId = new Map(
                savedImages
                    .filter((image) => image && image.client_id && image.id && image.url)
                    .map((image) => [String(image.client_id), image]),
            );

            this.methodologyImages.forEach((image) => {
                const savedImage = imagesByClientId.get(String(image.clientId));

                if (!savedImage || !submittedImageFiles.has(String(image.clientId))) return;

                image.id = String(savedImage.id);

                if (image.currentFile !== submittedImageFiles.get(String(image.clientId))) return;

                if (image.previewUrl.startsWith('blob:')) URL.revokeObjectURL(image.previewUrl);
                image.previewUrl = String(savedImage.url);
                image.currentFile = null;

                this.$nextTick(() => {
                    const input = document.getElementById(`methodology-image-file-${image.clientId}`);

                    if (input instanceof HTMLInputElement && image.currentFile === null) input.value = '';
                });
            });
        },

        focusLiteratureDestination(action) {
            const fieldId = action === 'reference' ? 'references' : 'related-literature';

            window.setTimeout(() => {
                const field = document.getElementById(fieldId);

                if (!field) return;

                const focusTarget = field._semanticEditor instanceof HTMLElement ? field._semanticEditor : field;
                const destination = focusTarget.closest('section') || focusTarget;
                const destinationTop = destination.getBoundingClientRect().top + window.scrollY - 144;

                window.scrollTo({ top: Math.max(0, destinationTop), behavior: 'smooth' });
                focusTarget.focus({ preventScroll: true });
            }, 400);
        },

        addLiteratureSourceToRrl(source, quiet = false) {
            const note = String(source?.rrl_note || '').trim();

            if (!note || source?.rrl_draft_status !== 'confirmed' || !['abstract', 'full_text'].includes(source?.rrl_evidence_basis)) {
                if (!quiet) this.literatureSourceNotice = 'No confirmed RRL paragraph is available. Review the source evidence and confirm a paragraph in the literature organizer before inserting it.';
                return false;
            }

            const normalizedNote = note.replace(/\[\d+\]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
            const normalizedRelatedLiterature = this.plainText(this.relatedLiterature).replace(/\[\d+\]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();

            if (normalizedNote && normalizedRelatedLiterature.includes(normalizedNote)) {
                if (!quiet) this.literatureSourceNotice = 'That source already appears in the Review of Related Literature field.';
                return false;
            }

            const referenceNumber = this.recordLiteratureCitation(source, 'related_literature', note);
            const marker = `<span data-proposal-citation="${Number(source.id)}"> [${referenceNumber}]</span>`;
            const escapedNote = this.escapeLiteratureHtml(note);
            const citedNote = escapedNote.includes(`[${referenceNumber}]`)
                ? escapedNote.replaceAll(`[${referenceNumber}]`, marker)
                : `${escapedNote}${marker}`;

            this.relatedLiterature = this.appendLiteratureText(this.relatedLiterature, citedNote);
            this.synchronizeLiteratureCitations();
            this.notifyLiteratureFieldChanged('related-literature');
            if (!quiet) {
                this.literatureSourceNotice = 'RRL paragraph and its reference were added to Sections XI and XVI. Autosaving now.';
                this.focusLiteratureDestination('rrl');
            }

            return true;
        },

        addLiteratureSourceToReferences(source, quiet = false) {
            const referenceNumber = this.citationReferenceNumber(source);

            if (referenceNumber === null) {
                if (!quiet) {
                    this.literatureSourceNotice = 'Save the paper to this proposal library, then cite the RRL text it supports. ATHENA adds Section XVI only when the paper is actually cited.';
                    this.focusLiteratureDestination('rrl');
                }

                return false;
            }

            this.synchronizeLiteratureCitations();

            if (!quiet) {
                this.literatureSourceNotice = `IEEE reference [${referenceNumber}] already follows its citation in Section XI.`;
                this.focusLiteratureDestination('reference');
            }

            return true;
        },

        addLiteratureSourceToBoth(source, quiet = false) {
            const addedToRrl = this.addLiteratureSourceToRrl(source, true);

            if (addedToRrl) {
                if (!quiet) this.literatureSourceNotice = 'The RRL paragraph, citation, and matching IEEE reference were added. Verify the paper, synthesis, and reference format before submission.';
                return true;
            }

            if (!quiet) this.literatureSourceNotice = 'That source already appears in the Review of Related Literature field.';
            return false;
        },

        appendLiteratureText(currentValue, addition) {
            const current = String(currentValue || '').trim();

            return current ? `${current}\n\n${addition}` : addition;
        },

        notifyLiteratureFieldChanged(fieldId) {
            window.setTimeout(() => {
                const field = document.getElementById(fieldId);

                if (!field) return;

                if (typeof field._syncSemanticEditor === 'function') field._syncSemanticEditor();
                field.dispatchEvent(new Event('input', { bubbles: true }));
            });
        },

        newStaff(values = {}) {
            this.nextId += 1;

            const workspacePerson = this.workspacePeople.find(
                (person) => String(person.name || '').trim().toLowerCase() === String(values.name || '').trim().toLowerCase(),
            );

            return {
                id: this.nextId,
                key: String(values.key ?? workspacePerson?.key ?? ''),
                title: String(values.title ?? ''),
                name: formatPersonName(values.name),
                email: String(values.email ?? ''),
                contact: this.normalizeContactNumber(values.contact),
            };
        },

        newResponsibility(values = {}) {
            this.nextId += 1;

            return {
                id: this.nextId,
                name: String(values.name ?? ''),
                percentage: String(values.percentage ?? ''),
                duties: String(values.duties ?? ''),
            };
        },

        newMethodologyImage(values = {}) {
            const id = String(values.id ?? '');

            return {
                clientId: this.newMethodologyImageClientId(),
                id,
                section: Object.hasOwn(this.figureSections, values.section) ? values.section : 'research_design',
                alignment: ['left', 'center', 'right'].includes(values.alignment) ? values.alignment : 'center',
                size: ['small', 'medium', 'large'].includes(values.size) ? values.size : 'medium',
                caption: String(values.caption ?? ''),
                originalFilename: String(values.original_filename ?? values.originalFilename ?? 'Methodology visual'),
                previewUrl: id ? this.methodologyImageUrl(id) : '',
                currentFile: null,
            };
        },

        newMethodologyImageClientId() {
            if (window.crypto?.randomUUID) return window.crypto.randomUUID();

            this.nextId += 1;

            return `methodology-image-${Date.now()}-${this.nextId}`;
        },

        methodologyImageUrl(id) {
            return String(config.methodologyImageUrlTemplate || '').replace('__image_id__', encodeURIComponent(id));
        },

        methodologyImagesFor(section) {
            return this.methodologyImages.filter((image) => image.section === section);
        },

        methodologyImageIndex(image) {
            return this.methodologyImages.indexOf(image);
        },

        methodologyImageFigureNumber(image) {
            let figureNumber = 0;

            for (const section of Object.keys(this.figureSections)) {
                for (const sectionImage of this.methodologyImagesFor(section)) {
                    figureNumber += 1;

                    if (sectionImage === image) return figureNumber;
                }
            }

            return figureNumber + 1;
        },

        openMethodologyImagePicker(section) {
            this.methodologyImageTarget = section;
            this.$refs.methodologyImagePicker.value = '';
            this.$refs.methodologyImagePicker.click();
        },

        addMethodologyImages(files, section) {
            const images = Array.from(files || []);

            if (!images.length || images.some((file) => !['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/x-ms-bmp'].includes(file.type))) {
                this.validationMessage = 'Choose an image in PNG, JPG, GIF, or BMP format.';

                return;
            }

            if (images.some((file) => file.size > 10 * 1024 * 1024) || this.methodologyImages.length + images.length > 20) {
                this.validationMessage = 'Use images up to 10 MB each, with at most 20 figures per proposal.';
                return;
            }

            this.validationMessage = '';
            const newImages = images.map((file) => {
                const image = this.newMethodologyImage({ section, originalFilename: file.name });
                image.previewUrl = URL.createObjectURL(file);
                image.currentFile = file;
                this.methodologyImages.push(image);

                return image;
            });

            this.$nextTick(() => {
                newImages.forEach((image) => this.assignMethodologyImageFile(image, image.currentFile));
                this.triggerDetailedProposalAutoSave();
            });
        },

        assignMethodologyImageFile(image, file) {
            const input = document.getElementById(`methodology-image-file-${image.clientId}`);

            if (!input || !file || !window.DataTransfer) return;

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        },

        replaceMethodologyImage(image, files) {
            const [file] = Array.from(files || []);

            if (!file) return;

            if (!['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/x-ms-bmp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
                this.validationMessage = 'Choose a PNG, JPG, GIF, or BMP image up to 10 MB.';

                return;
            }

            if (image.previewUrl.startsWith('blob:')) URL.revokeObjectURL(image.previewUrl);
            image.previewUrl = URL.createObjectURL(file);
            image.currentFile = file;
            image.originalFilename = file.name;
            this.validationMessage = '';
            this.triggerDetailedProposalAutoSave();
        },

        startMethodologyImageDrag(clientId) {
            this.draggedMethodologyImage = clientId;
        },

        handleMethodologyDrop(event, section) {
            const files = Array.from(event.dataTransfer?.files || []);

            if (files.length > 0) {
                this.addMethodologyImages(files, section);

                return;
            }

            const image = this.methodologyImages.find((item) => item.clientId === this.draggedMethodologyImage);

            if (image) this.moveMethodologyImageToSection(image, section);
            this.draggedMethodologyImage = '';
        },

        moveMethodologyImageToSection(image, section) {
            const imageIndex = this.methodologyImageIndex(image);

            if (imageIndex < 0) return;

            this.methodologyImages.splice(imageIndex, 1);
            image.section = section;
            const sectionImages = this.methodologyImagesFor(section);
            const lastSectionImage = sectionImages.at(-1);
            const targetIndex = lastSectionImage ? this.methodologyImageIndex(lastSectionImage) + 1 : this.methodologyImages.length;
            this.methodologyImages.splice(targetIndex, 0, image);
            this.triggerDetailedProposalAutoSave();
        },

        moveMethodologyImage(image, direction) {
            const sectionImages = this.methodologyImagesFor(image.section);
            const sectionIndex = sectionImages.indexOf(image);
            const destination = sectionImages[sectionIndex + direction];

            if (!destination) return;

            const imageIndex = this.methodologyImageIndex(image);
            const destinationIndex = this.methodologyImageIndex(destination);
            this.methodologyImages.splice(imageIndex, 1);
            this.methodologyImages.splice(destinationIndex, 0, image);
            this.triggerDetailedProposalAutoSave();
        },

        removeMethodologyImage(image) {
            if (image.previewUrl.startsWith('blob:')) URL.revokeObjectURL(image.previewUrl);

            this.methodologyImages.splice(this.methodologyImageIndex(image), 1);
            this.triggerDetailedProposalAutoSave();
        },

        normalizeContactNumber(value) {
            return String(value ?? '').replace(/\D/g, '').slice(0, 11);
        },

        availableWorkspacePeople() {
            const leaderEmail = String(this.leaderEmail || '').trim().toLowerCase();
            const usedEmails = new Set(this.staff.map((member) => String(member.email || '').trim().toLowerCase()));

            return this.workspacePeople.filter((person) => {
                const email = String(person.email || '').trim().toLowerCase();

                return email && email !== leaderEmail && !usedEmails.has(email);
            });
        },

        filteredWorkspacePeople() {
            return filterWorkspacePeople(this.availableWorkspacePeople(), this.workspacePersonQuery);
        },

        personDisplayName(name) {
            return formatPersonName(name);
        },

        personInitials(name) {
            return personInitials(name);
        },

        addWorkspacePerson(personKey = this.selectedWorkspacePerson) {
            const person = this.workspacePeople.find(
                (candidate) => String(candidate.key) === String(personKey),
            );

            if (!person) return;

            this.staff.push(this.newStaff(person));

            if (!this.responsibilities.some(
                (responsibility) => String(responsibility.name).trim().toLowerCase() === String(person.name).trim().toLowerCase(),
            )) {
                this.responsibilities.push(this.newResponsibility({ name: person.name }));
            }

            this.selectedWorkspacePerson = '';
            this.workspacePersonQuery = '';
            this.workspacePickerOpen = false;
            this.triggerDetailedProposalAutoSave();
        },

        addStaff() {
            this.staff.push(this.newStaff());
            this.triggerDetailedProposalAutoSave();
        },

        syncStaff(member) {
            const normalizedName = String(member.name || '').trim().toLowerCase();
            const person = this.workspacePeople.find(
                (candidate) => String(candidate.name || '').trim().toLowerCase() === normalizedName,
            );

            if (!person) {
                if (member.key) {
                    for (const field of ['key', 'email', 'title', 'contact']) member[field] = '';
                }
                return;
            }

            member.key = String(person.key || '');
            member.name = formatPersonName(person.name || member.name);
            member.email = String(person.email || member.email);
            member.title = String(person.title || '');
            member.contact = this.normalizeContactNumber(person.contact);
        },

        syncProjectLeader() {
            const previousProjectLeader = String(this.previousProjectLeader || '').trim().toLowerCase();
            this.projectLeader = formatPersonName(this.projectLeader);
            const person = this.workspacePeople.find(
                (candidate) => String(candidate.name || '').trim().toLowerCase() === this.projectLeader.trim().toLowerCase(),
            );

            if (person) {
                this.leaderEmail = String(person.email || this.leaderEmail);
                this.leaderContact = this.normalizeContactNumber(person.contact);
                this.leaderTitle = String(person.title || '');
                this.proponentDepartment = String(person.department || '');
                this.proponentCollege = String(person.college || '');
            } else if (this.projectLeader.trim().toLowerCase() !== previousProjectLeader) {
                for (const field of ['leaderTitle', 'leaderEmail', 'leaderContact', 'proponentDepartment', 'proponentCollege']) this[field] = '';
            }

            const leaderResponsibility = this.responsibilities.find(
                (responsibility) => String(responsibility.name || '').trim().toLowerCase() === previousProjectLeader,
            );

            if (leaderResponsibility) leaderResponsibility.name = this.projectLeader;
            this.previousProjectLeader = this.projectLeader;
        },

        removeStaff(index) {
            this.staff.splice(index, 1);
            this.triggerDetailedProposalAutoSave();
        },

        addResponsibility() {
            this.responsibilities.push(this.newResponsibility());
            this.triggerDetailedProposalAutoSave();
        },

        removeResponsibility(index) {
            if (this.responsibilities.length === 1) return;

            this.responsibilities.splice(index, 1);
            this.triggerDetailedProposalAutoSave();
        },

        isComplete() {
            const fields = Array.from(this.$refs.form?.querySelectorAll('input, textarea, select') || []);

            return this.sdgs.length > 0
                && this.hasDetailedProposalExpectedOutput()
                && fields.every((field) => field.disabled || this.detailedProposalFieldIsValid(field));
        },

        detailedProposalFieldIsValid(field) {
            if (field.required && field.matches?.('textarea[data-semantic-editor]')) {
                field.setCustomValidity(this.plainText(field.value) ? '' : 'Add text to this section.');
            }
            return field.checkValidity();
        },

        detailedProposalHighlightTarget(field) {
            if (!(field instanceof HTMLElement)) return null;

            if (field instanceof HTMLTextAreaElement && field._semanticEditor instanceof HTMLElement) {
                return field._semanticEditor.parentElement;
            }

            return field;
        },

        clearDetailedProposalFieldHighlight(field) {
            if (typeof field?.checkValidity !== 'function') {
                field = [...(this.$refs.form?.querySelectorAll('textarea') || [])].find((textarea) => textarea._semanticEditor?.contains(field));
            }
            const target = this.detailedProposalHighlightTarget(field);

            if (!(target instanceof HTMLElement)) return;

            if ('checkValidity' in field && this.detailedProposalFieldIsValid(field)) {
                target.removeAttribute('data-detailed-proposal-invalid');
                field.removeAttribute('aria-invalid');
                if (field._proposalFeedbackElement) field._proposalFeedbackElement.hidden = true;

                if (field instanceof HTMLTextAreaElement && field._semanticEditor instanceof HTMLElement) {
                    field._semanticEditor.removeAttribute('aria-invalid');
                }
            }

            if (field instanceof HTMLInputElement && field.name === 'sdgs[]' && this.sdgs.length > 0) {
                this.clearDetailedProposalValidationGroup('sdgs');
            }

            if (field instanceof HTMLTextAreaElement && field.name.startsWith('expected_outputs[')) {
                this.clearDetailedProposalValidationGroup('expected-outputs');
            }
        },

        highlightDetailedProposalField(field) {
            const target = this.detailedProposalHighlightTarget(field);

            if (!(target instanceof HTMLElement)) return;

            target.setAttribute('data-detailed-proposal-invalid', 'true');
            field.setAttribute('aria-invalid', 'true');
            if (typeof field.checkValidity === 'function' && !this.detailedProposalFieldIsValid(field)) {
                let feedback = field._proposalFeedbackElement;
                if (!feedback?.isConnected) {
                    feedback = document.createElement('p');
                    feedback.id = 'proposal-field-feedback-' + (++this.proposalFeedbackId);
                    feedback.dataset.proposalFieldFeedback = 'true';
                    feedback.className = 'mt-2 text-xs leading-5 text-red-700 dark:text-red-300';
                    feedback.setAttribute('role', 'status');
                    target.insertAdjacentElement('afterend', feedback);
                    field._proposalFeedbackElement = feedback;
                    field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), feedback.id].filter(Boolean).join(' '));
                }
                feedback.textContent = field.validity.valueMissing ? 'This field is required before generating the proposal.' : field.validationMessage;
                feedback.hidden = false;
            }

            if (field instanceof HTMLTextAreaElement && field._semanticEditor instanceof HTMLElement) {
                field._semanticEditor.setAttribute('aria-invalid', 'true');
                if (field._proposalFeedbackElement) field._semanticEditor.setAttribute('aria-describedby', field.getAttribute('aria-describedby'));
            }
        },

        highlightDetailedProposalValidationGroup(group) {
            this.$refs.form
                ?.querySelector(`[data-detailed-proposal-validation-group="${group}"]`)
                ?.setAttribute('data-detailed-proposal-invalid', 'true');
        },

        clearDetailedProposalValidationGroup(group) {
            this.$refs.form
                ?.querySelector(`[data-detailed-proposal-validation-group="${group}"]`)
                ?.removeAttribute('data-detailed-proposal-invalid');
        },

        focusDetailedProposalField(field) {
            const focusTarget = field instanceof HTMLTextAreaElement && field._semanticEditor instanceof HTMLElement
                ? field._semanticEditor
                : field;

            focusTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
            focusTarget.focus({ preventScroll: true });
        },

        focusProposalRequirement(key) {
            this.closeProposalPreview();
            if (key.startsWith('specific_objectives') && !this.specificObjectives.length) {
                this.specificObjectives.push(this.newSpecificObjective());
                this.$nextTick(() => this.focusProposalRequirement('specific_objectives.0.description'));
                return;
            }
            const name = key.split('.').map((part, index) => index === 0 ? part : `[${part}]`).join('');
            const fields = Array.from(this.$refs.form?.querySelectorAll('input, textarea, select') || []);
            const exact = fields.find((field) => field.name === name && field.type !== 'hidden');
            const nestedFields = fields.filter((field) => field.type !== 'hidden' && field.name.startsWith(`${name}[`));
            const nested = key === 'specific_objectives'
                ? nestedFields.find((field) => !String(field.value ?? '').trim()) || nestedFields[0]
                : nestedFields[0];
            let target = exact || nested;

            if (!target && key.startsWith('methodology.specific_methods')) target = document.getElementById('methodology-specific-methods');
            if (!target && key.startsWith('methodology_images.')) {
                const image = this.methodologyImages[Number(key.split('.')[1])];
                if (image) target = document.getElementById(`methodology-image-caption-${image.clientId}`);
            }
            if (!target && key.startsWith('specific_method_objectives')) target = document.getElementById('methodology-specific-methods');
            if (!target && key.startsWith('specific_objectives')) target = document.getElementById('specific-objectives');
            if (!target && key.startsWith('expected_outputs')) target = document.getElementById('expected-outputs');
            if (!target && key === 'sdgs') target = this.$refs.form?.querySelector('[data-detailed-proposal-validation-group="sdgs"]');

            if (target instanceof HTMLElement) {
                if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) target.setAttribute('tabindex', '-1');
                this.highlightDetailedProposalField(target);
                this.focusDetailedProposalField(target);
            }
        },

        hasDetailedProposalExpectedOutput() {
            return Object.values(this.expectedOutputs).some((outputs) => Array.isArray(outputs)
                && outputs.some((output) => this.expectedOutputDescription(output).trim() !== ''));
        },

        checkDetailedProposalRequirements() {
            return this.validateForm({ focus: false });
        },

        validateForm({ forExit = false, focus = true } = {}) {
            this.validationMessage = '';
            this.requirementsReviewed = true;

            const fields = Array.from(this.$refs.form?.querySelectorAll('input, textarea, select') || []);
            const invalidFields = fields.filter((field) => !field.disabled && !this.detailedProposalFieldIsValid(field));
            const hasSdgs = this.sdgs.length > 0;
            const hasExpectedOutput = this.hasDetailedProposalExpectedOutput();
            const completionErrors = { ...this.completionErrors };
            fields.forEach((field) => {
                if (!field.name || ['hidden', 'checkbox', 'radio', 'file'].includes(field.type)) return;
                const key = field.name.replace(/\[([^\]]+)\]/g, '.$1');
                if (invalidFields.includes(field)) {
                    const label = field.getAttribute('aria-label') || field.labels?.[0]?.textContent.trim() || field.name.replaceAll('_', ' ');
                    completionErrors[key] = [field.validity.valueMissing ? `Complete ${label}.` : field.validationMessage];
                } else delete completionErrors[key];
            });
            if (hasSdgs) delete completionErrors.sdgs;
            else completionErrors.sdgs = ['Select at least one Sustainable Development Goal.'];
            if (hasExpectedOutput) delete completionErrors.expected_outputs;
            else completionErrors.expected_outputs = ['Add at least one expected output.'];
            this.completionErrors = completionErrors;

            fields.forEach((field) => this.clearDetailedProposalFieldHighlight(field));
            this.clearDetailedProposalValidationGroup('sdgs');
            this.clearDetailedProposalValidationGroup('expected-outputs');

            invalidFields.forEach((field) => this.highlightDetailedProposalField(field));

            if (!hasSdgs) this.highlightDetailedProposalValidationGroup('sdgs');
            if (!hasExpectedOutput) this.highlightDetailedProposalValidationGroup('expected-outputs');

            if (hasSdgs && hasExpectedOutput && invalidFields.length === 0) {
                this.completionErrors = {};
                return true;
            }

            if (!focus) return false;

            this.validationMessage = forExit
                ? 'Complete the highlighted required fields before exiting the editor.'
                : !hasSdgs
                    ? 'Select at least one Sustainable Development Goal.'
                    : !hasExpectedOutput
                        ? 'Add at least one expected output.'
                        : 'Complete the highlighted required fields.';

            const firstInvalidField = invalidFields[0];

            if (firstInvalidField) {
                this.focusDetailedProposalField(firstInvalidField);

                if (!(firstInvalidField instanceof HTMLTextAreaElement
                    && firstInvalidField._semanticEditor instanceof HTMLElement)) {
                    firstInvalidField.reportValidity();
                }

                return false;
            }

            const group = hasSdgs ? 'expected-outputs' : 'sdgs';
            const groupField = this.$refs.form?.querySelector(
                group === 'sdgs'
                    ? '[name="sdgs[]"], [data-detailed-proposal-validation-group="sdgs"]'
                    : '[name^="expected_outputs"], [data-detailed-proposal-validation-group="expected-outputs"]',
            );

            if (groupField instanceof HTMLElement) this.focusDetailedProposalField(groupField);

            return false;
        },

        detailedProposalFormData(form = this.$refs.form) {
            const formData = new FormData(form);
            for (const name of [...formData.keys()]) {
                if (name.startsWith('specific_objectives[')) formData.delete(name);
            }
            formData.set('specific_objectives_present', '1');
            this.specificObjectives.forEach((objective, index) => {
                formData.set(`specific_objectives[${index}][description]`, String(objective.description ?? ''));
            });

            return formData;
        },

        formData() {
            const formData = this.detailedProposalFormData();
            formData.delete('_method');

            return formData;
        },

        async generatePreview() {
            if (this.previewLoading) return;
            this.previewPaneOpen = true;
            this.previewTab = 'preview';
            const revision = this.previewRevision;
            this.validationMessage = '';
            this.previewError = '';
            this.downloadError = '';
            this.previewLoading = true;
            this.previewReady = false;

            try {
                const response = await fetch(config.previewUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                    body: this.formData(),
                });

                if (response.status === 422) {
                    const payload = await response.json();
                    this.previewStale = true;
                    if (revision !== this.previewRevision) return;

                    this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                        || 'Please review the Detailed Research Proposal information.';
                    this.previewError = this.validationMessage;

                    return;
                }

                if (!response.ok) throw new Error('The content preview could not be generated. Please try again.');
                const previewHtml = await response.text();
                this.previewStale = revision !== this.previewRevision;
                if (!this.previewStale) this.previewHtml = previewHtml;
            } catch (error) {
                this.previewStale = true;
                if (revision !== this.previewRevision) return;

                this.previewError = error instanceof Error ? error.message : 'The content preview could not be generated.';
            } finally {
                this.previewLoading = false;
                this.previewReady = Boolean(this.previewHtml);
                if (this.previewStale && revision !== this.previewRevision) {
                    this.scheduleDetailedProposalPreview();
                }
            }
        },

        async downloadDocument() {
            if (!this.validateForm()) return;

            this.downloadError = '';
            this.downloadLoading = true;

            try {
                const generationConfig = isEmbeddedRevisionEditor() ? revisionGenerationConfig(config) : config;
                const response = await fetch(config.downloadUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Revision-PDF': isEmbeddedRevisionEditor() ? '1' : '0',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: this.formData(),
                });

                if (response.status === 422) {
                    const payload = await response.json();
                    this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                        || 'Please review the Detailed Research Proposal information.';

                    return;
                }

                if (!response.ok) throw new Error('The exact Word file could not be generated. Please try again.');
                const disposition = response.headers.get('Content-Disposition') || '';
                const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
                const filename = filenameMatch?.[1] || (isEmbeddedRevisionEditor()
                    ? 'detailed-research-proposal.pdf'
                    : 'detailed-research-proposal.docx');
                const blob = await response.blob();
                if (isEmbeddedRevisionEditor()) return await offerRevisionUpload(blob, filename, generationConfig);
                const downloadUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                await offerRevisionUpload(blob, filename, generationConfig);
                URL.revokeObjectURL(downloadUrl);
            } catch (error) {
                this.downloadError = error instanceof Error ? error.message : 'The exact Word file could not be generated.';
            } finally {
                this.downloadLoading = false;
            }
        },

        printPreview() {
            if (!this.previewReady || !this.$refs.previewFrame?.contentWindow) return;

            this.$refs.previewFrame.contentWindow.focus();
            this.$refs.previewFrame.contentWindow.print();
        },
    }));
}
