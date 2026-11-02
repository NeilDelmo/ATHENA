import { sameOriginPaperUrl } from './research-assistant-paper-insertion.js';

const maximumLiteratureSelections = 3;
const minimumLiteratureParagraphLength = 40;
const maximumLiteratureParagraphLength = 5000;

function literatureText(value) {
    return ['string', 'number'].includes(typeof value) ? String(value).trim() : '';
}

function literatureUrl(value) {
    const candidate = literatureText(value);

    if (!/^https?:\/\//i.test(candidate)) return '';

    try {
        const url = new URL(candidate);

        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password
            ? url.href
            : '';
    } catch {
        return '';
    }
}

function literatureAuthors(value) {
    const authors = Array.isArray(value) ? value : [value];

    return authors.map((author) => literatureText(author?.name ?? author)).filter(Boolean);
}

function literatureEvidenceBasis(value) {
    return ['abstract', 'full_text'].includes(value) ? value : 'metadata_only';
}

function normalizeLiteratureSource(source) {
    const evidenceBasis = literatureEvidenceBasis(source?.evidence_basis);
    const description = literatureText(source?.description);
    const score = Number(source?.relevance?.score);

    return {
        source_token: literatureText(source?.source_token),
        title: literatureText(source?.title) || 'Untitled study',
        authors: literatureAuthors(source?.authors),
        year: literatureText(source?.year),
        url: literatureUrl(source?.url),
        doi: literatureText(source?.doi),
        description,
        relevance: {
            score: Number.isFinite(score) ? Math.min(100, Math.max(0, score)) : null,
            label: literatureText(source?.relevance?.label) || 'Review relevance',
            reason: literatureText(source?.relevance?.reason),
            matched_terms: Array.isArray(source?.relevance?.matched_terms)
                ? source.relevance.matched_terms.map(literatureText).filter(Boolean)
                : [],
        },
        evidence_basis: evidenceBasis,
        reading_error: literatureText(source?.reading_error).slice(0, 600),
        can_read_paper: source?.can_read_paper === true,
        can_synthesize: source?.can_synthesize === true && source?.can_read_paper === true,
    };
}

export function normalizeAssistantLiterature(packet) {
    if (packet?.kind === 'choose_proposal') {
        const seen = new Set();
        return { kind: 'choose_proposal', intent: packet.intent === 'draft' ? 'draft' : 'search',
            request: literatureText(packet.request).slice(0, 8000), workspace_required: packet.workspace_required === true,
            proposals_url: literatureUrl(packet.proposals_url), is_working: false, error: '',
            proposals: (Array.isArray(packet.proposals) ? packet.proposals : []).filter(option => {
                const id = Number(option?.id);
                if (!Number.isSafeInteger(id) || id <= 0 || !literatureText(option?.title) || seen.has(id)) return false;
                seen.add(id); return true;
            }).slice(0, 20).map(option => ({ id: Number(option.id), title: literatureText(option.title).slice(0, 500) })),
        };
    }
    const proposalDraftId = Number(packet?.proposal_draft_id);

    if (!['results', 'draft', 'insertion'].includes(packet?.kind)
        || !Number.isSafeInteger(proposalDraftId) || proposalDraftId <= 0) return null;

    const normalized = {
        kind: packet.kind,
        proposal_draft_id: proposalDraftId,
        proposal_title: literatureText(packet.proposal_title),
        notice: literatureText(packet.notice),
        topic: literatureText(packet.topic || packet.query || packet.proposal_title).slice(0, 500),
        editor_url: literatureUrl(packet.editor_url),
        intent: packet.intent === 'draft' ? 'draft' : 'search',
        read_failures: (Array.isArray(packet.read_failures) ? packet.read_failures : []).slice(0, 20)
            .filter(item => literatureText(item?.reason)).map(item => ({ source_token: literatureText(item.source_token),
                title: literatureText(item.title).slice(0, 500), reason: literatureText(item.reason).slice(0, 600) })),
        is_working: false,
        error: '',
    };

    if (packet.kind === 'results') {
        const tokens = new Set();
        normalized.query = literatureText(packet.query);
        normalized.context_basis = literatureText(packet.context_basis);
        for (const field of ['year_from', 'year_to']) {
            const year = Number(packet[field]);
            if (Number.isSafeInteger(year) && year > 0) normalized[field] = year;
        }
        normalized.results = (Array.isArray(packet.results) ? packet.results : [])
            .slice(0, 12).map(normalizeLiteratureSource)
            .filter((source) => {
                if (!source.source_token || tokens.has(source.source_token)) return false;
                tokens.add(source.source_token);
                return true;
            });
        const eligibleTokens = normalized.results.filter((source) => source.can_synthesize)
            .map((source) => source.source_token);
        normalized.ui_selected_source_tokens = [...new Set(
            (Array.isArray(packet.ui_selected_source_tokens) ? packet.ui_selected_source_tokens : [])
                .filter((token) => eligibleTokens.includes(token)),
        )].slice(0, maximumLiteratureSelections);
    }

    if (packet.kind === 'draft') {
        const tokens = new Set();
        normalized.drafts = (Array.isArray(packet.drafts) ? packet.drafts : [])
            .map((draft) => ({
                draft_token: literatureText(draft?.draft_token),
                source_token: literatureText(draft?.source_token),
                title: literatureText(draft?.title) || 'Untitled study',
                authors: literatureAuthors(draft?.authors),
                year: literatureText(draft?.year),
                url: literatureUrl(draft?.url),
                paragraph: literatureText(draft?.paragraph),
                reference: literatureText(draft?.reference).slice(0, 2048),
                evidence_basis: literatureEvidenceBasis(draft?.evidence_basis),
                notice: literatureText(draft?.notice),
                relationship: literatureText(draft?.relationship),
            }))
            .filter((draft) => {
                if (!draft.draft_token || !draft.source_token || tokens.has(draft.draft_token)) return false;
                tokens.add(draft.draft_token);
                return true;
            }).slice(0, maximumLiteratureSelections);
        normalized.confirmed = packet.confirmed === true;
    }

    if (packet.kind === 'insertion') {
        normalized.sources = (Array.isArray(packet.sources) ? packet.sources : [])
            .filter((source) => source && typeof source === 'object' && !Array.isArray(source))
            .map((source) => ({ ...source, url: literatureUrl(source.url) }));
        normalized.editor_url = literatureUrl(packet.editor_url);
        normalized.applied = packet.applied === true;
    }

    return normalized;
}

export function serializeAssistantLiterature(packet) {
    const normalized = normalizeAssistantLiterature(packet);
    if (!normalized) return null;
    if (normalized.kind === 'choose_proposal') {
        const { is_working, error, ...choice } = normalized;
        return choice;
    }

    const persisted = {
        kind: normalized.kind,
        proposal_draft_id: normalized.proposal_draft_id,
        proposal_title: normalized.proposal_title,
        notice: normalized.notice, topic: normalized.topic, intent: normalized.intent,
        editor_url: normalized.editor_url, read_failures: normalized.read_failures,
    };

    if (normalized.kind === 'results') {
        persisted.query = normalized.query;
        persisted.context_basis = normalized.context_basis;
        persisted.results = normalized.results;
        for (const field of ['year_from', 'year_to']) {
            if (normalized[field]) persisted[field] = normalized[field];
        }
    }

    if (normalized.kind === 'draft') {
        persisted.drafts = normalized.drafts;
        persisted.confirmed = normalized.confirmed;
    }

    if (normalized.kind === 'insertion') {
        persisted.sources = normalized.sources;
        persisted.editor_url = normalized.editor_url;
        persisted.applied = normalized.applied;
    }

    return persisted;
}

export function researchAssistantLiterature({ savePaper, finishNavigation = async () => true, browser = globalThis } = {}) {
    return {
        literatureOperationPending: false,
        pendingLiteratureRequest: null,

        literatureBusy(message) {
            return Boolean(this.isLoading || this.sendInProgress || this.reportChangesOperationPending || this.retryAfter > 0 || this.literatureOperationPending || message?.literature?.is_working);
        },

        canReviewLiteratureFlow(message) {
            const packet = message?.literature;

            return packet?.kind === 'insertion' && packet.applied === true
                && Number.isSafeInteger(packet.proposal_draft_id) && packet.proposal_draft_id > 0
                && !this.literatureBusy(message) && !this.proposalChangesBusy?.() && !this.draft?.trim();
        },

        async reviewLiteratureFlow(message) {
            if (!this.canReviewLiteratureFlow(message)) return false;

            const packet = message.literature;
            const sequence = this.requestSequence;
            packet.is_working = true;
            packet.error = '';
            this.literatureOperationPending = true;

            try {
                if (typeof savePaper !== 'function' || !await savePaper(packet.proposal_draft_id)) {
                    throw new Error('Save your current paper edits before reviewing the RRL flow.');
                }
                if (sequence !== this.requestSequence || this.isLoading || this.sendInProgress || this.draft?.trim()) return false;

                await this.sendPrompt('Revise and organize my saved RRL for clarity and paragraph flow. Preserve its claims and citations. Show the revision for my review.',
                    { type: 'suggest_proposal_changes' }, { proposal_draft_id: packet.proposal_draft_id });
                return true;
            } catch (error) {
                packet.error = error instanceof Error ? error.message : 'The RRL flow review could not be requested. Try again.';
                return false;
            } finally {
                packet.is_working = false;
                this.literatureOperationPending = false;
            }
        },

        literatureDraftText(message) {
            const drafts = message?.literature?.drafts || [];
            const text = drafts.map((draft, index) => `${literatureText(draft.paragraph)} [${index + 1}]`).join('\n\n');
            const references = drafts.map((draft, index) => draft.reference || `[${index + 1}] ${draft.title}. ${this.literatureSourceMetadata(draft)}. ${draft.url || ''}`);
            return `${text}\n\nReferences\n${references.join('\n')}`;
        },

        copyLiteratureDraft(message) {
            return this.copyMessage({ ...message, literature: null, content: this.literatureDraftText(message) });
        },

        expandLiteratureDraft(message) {
            this.openWorkspace();
            browser.setTimeout(() => {
                const document = browser.document.querySelector(`[data-assistant-workspace] [data-rrl-artifact="${Number(message.id)}"]`);
                document?.scrollIntoView({ block: 'start', behavior: 'smooth' });
                document?.focus({ preventScroll: true });
            }, 250);
        },

        literatureRequestKey() {
            const id = browser.document?.body?.dataset.authUserId;
            return id ? `athena:rrl-request:${id}` : '';
        },

        rememberLiteratureRequest(request) {
            this.pendingLiteratureRequest = { ...request, created_at: Date.now() };
            const key = this.literatureRequestKey();
            try {
                if (key) {
                    browser.sessionStorage.setItem(key, JSON.stringify(this.pendingLiteratureRequest));
                    return true;
                }
            } catch {
                try { if (key) browser.sessionStorage.removeItem(key); } catch { /* The current page retains the new request. */ }
            }
            return false;
        },

        clearLiteratureRequest() {
            this.pendingLiteratureRequest = null;
            try { const key = this.literatureRequestKey(); if (key) browser.sessionStorage.removeItem(key); } catch { /* Browser storage is optional. */ }
        },

        async chooseLiteratureProposal(message, proposalId) {
            const packet = message?.literature;
            const proposal = packet?.proposals?.find(item => item.id === Number(proposalId));
            if (!proposal || this.literatureBusy(message)) return false;
            if (this.draft?.trim()) { packet.error = 'Send or clear your unfinished message before starting this draft.'; return false; }
            this.rememberLiteratureRequest({ request: packet.request, intent: packet.intent, proposal_id: proposal.id });
            packet.error = '';
            packet.is_working = true;
            try {
                const context = { proposal_draft_id: proposal.id };
                if (!await this.ensureConversationContext(context)) return false;
                this.restoreReviewContext(null);
                if (this.draft?.trim()) return false;
                const result = await this.sendPrompt(packet.request,
                    { type: packet.intent === 'draft' ? 'draft_rrl' : 'search_literature' }, context);
                if (result) this.clearLiteratureRequest();
                return Boolean(result);
            } finally { packet.is_working = false; }
        },

        literatureProposalsUrl(message) {
            return sameOriginPaperUrl(message?.literature?.proposals_url, browser.location.origin);
        },

        async switchLiteratureWorkspace(message) {
            const packet = message?.literature;
            if (!packet?.workspace_required || this.literatureBusy(message)) return false;
            if (this.draft?.trim()) { packet.error = 'Send or clear your unfinished message before changing workspace.'; return false; }
            const endpoint = sameOriginPaperUrl(browser.document.body.dataset.researchAssistantWorkspaceUrl, browser.location.origin);
            const destination = this.literatureProposalsUrl(message);
            const csrf = browser.document.querySelector('meta[name="csrf-token"]')?.content;
            if (!endpoint || !destination || !csrf) { packet.error = 'Refresh this page before choosing a proposal.'; return false; }
            packet.is_working = true; packet.error = '';
            try {
                if (!await finishNavigation() || (this.hasConversation() && !await this.saveConversation())) return false;
                if (!this.rememberLiteratureRequest({ request: packet.request, intent: packet.intent })) {
                    throw new Error('Your request could not be retained. Open your proposal workspace and ask again.');
                }
                const response = await browser.fetch(endpoint, { method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ workspace: 'faculty', resume_url: destination }) });
                const payload = await response.json().catch(() => ({}));
                const redirect = sameOriginPaperUrl(payload.redirect, browser.location.origin);
                if (!response.ok || !redirect) throw new Error(payload.message || 'The proposal workspace could not be opened. Try again.');
                browser.location.assign(redirect);
                return true;
            } catch (error) { packet.error = error.message || 'The proposal workspace could not be opened.'; return false; }
            finally { packet.is_working = false; }
        },

        async restoreLiteratureRequest() {
            if (browser.document?.body?.dataset.activeWorkspace !== 'faculty') return false;
            try {
                const key = this.literatureRequestKey();
                const pending = key ? JSON.parse(browser.sessionStorage.getItem(key) || 'null') : null;
                if (!pending) return false;
                if (!Number.isFinite(pending.created_at) || Date.now() - pending.created_at > 10 * 60 * 1000
                    || pending.created_at > Date.now() || !['draft', 'search'].includes(pending.intent)
                    || typeof pending.request !== 'string' || !pending.request.trim() || pending.request.length > 8000
                    || (pending.proposal_id && (!Number.isSafeInteger(pending.proposal_id) || pending.proposal_id <= 0))) {
                    this.clearLiteratureRequest(); return false;
                }
                this.pendingLiteratureRequest = pending;
                this.openDrawer();
                return this.conversationReady === false ? false : this.resumeLiteratureRequest();
            } catch { return false; }
        },

        async resumeLiteratureRequest() {
            const pending = this.pendingLiteratureRequest;
            if (!pending || this.literatureBusy() || this.historyRestoring || this.draft?.trim()) return false;
            const context = pending.proposal_id ? { proposal_draft_id: pending.proposal_id } : null;
            if (!await this.ensureConversationContext(context || {}) || this.draft?.trim()) return false;
            this.restoreReviewContext?.(null);
            const result = await this.sendPrompt(pending.request, { type: pending.intent === 'draft' ? 'draft_rrl' : 'search_literature' }, context);
            if (result) this.clearLiteratureRequest();
            return Boolean(result);
        },

        async openLiteratureSources(message, mode = 'pdf') {
            const packet = message?.literature;
            if (!packet?.proposal_draft_id || !['pdf', 'search'].includes(mode) || this.literatureBusy(message) || !await finishNavigation()) return false;
            const editor = browser.document.querySelector('[data-detailed-proposal-workspace]');
            if (Number(editor?.dataset.proposalDraftId) === packet.proposal_draft_id) {
                this.closeWorkspace();
                this.closeDrawer();
                const workspace = browser.Alpine.$data(editor);
                await workspace.openSourceWorkspace(mode === 'pdf' ? 'add' : 'search');
                if (mode === 'pdf') workspace.setSourceWorkspaceAddMode('pdf');
                else workspace.sourceWorkspaceQuery = packet.topic || packet.query || workspace.sourceWorkspaceQuery;
                return true;
            }
            const safe = sameOriginPaperUrl(packet.editor_url, browser.location.origin);
            if (!safe || (this.hasConversation() && !await this.saveConversation())) return false;
            const url = new URL(safe);
            url.searchParams.set('sources', mode);
            if (mode === 'search') url.searchParams.set('source-query', (packet.topic || packet.query || '').slice(0, 500));
            browser.location.assign(url.href);
            return true;
        },

        resizeLiteratureParagraph(field) {
            if (!field?.offsetWidth) return;
            field.style.height = 'auto';
            field.style.height = `${field.scrollHeight + 2}px`;
        },

        literatureEvidenceLabel(source) {
            if (source?.evidence_basis === 'full_text') return 'Based on extracted paper text';
            if (source?.reading_error) return 'Complete paper could not be read';
            if (source?.can_read_paper) return 'Paper link found; reading not checked yet';
            if (source?.evidence_basis === 'abstract') return 'Abstract available; paper required';
            return 'Metadata only';
        },

        literatureSourceMetadata(source) {
            return [literatureAuthors(source?.authors).join(', '), literatureText(source?.year)]
                .filter(Boolean).join(' · ') || 'Authors and publication year unavailable';
        },

        literatureSelectedTokens(message) {
            const packet = message?.literature;
            if (packet?.kind !== 'results') return [];

            const eligible = packet.results.filter((source) => source.can_synthesize)
                .map((source) => source.source_token);

            return [...new Set(packet.ui_selected_source_tokens.filter((token) => eligible.includes(token)))];
        },

        literatureSelectedCount(message) {
            return this.literatureSelectedTokens(message).length;
        },

        literatureSourceSelected(message, token) {
            return this.literatureSelectedTokens(message).includes(token);
        },

        literatureSourceDisabled(message, source) {
            return this.literatureBusy(message) || !source?.can_synthesize
                || (this.literatureSelectedCount(message) >= maximumLiteratureSelections
                    && !this.literatureSourceSelected(message, source.source_token));
        },

        toggleLiteratureSource(message, token) {
            const packet = message?.literature;
            const source = packet?.results?.find((candidate) => candidate.source_token === token);
            if (packet?.kind !== 'results' || this.literatureSourceDisabled(message, source)) return false;

            const selected = this.literatureSelectedTokens(message);
            packet.ui_selected_source_tokens = selected.includes(token)
                ? selected.filter((selectedToken) => selectedToken !== token)
                : [...selected, token];
            packet.error = '';
            return true;
        },

        canDraftLiterature(message, basis = 'full_text') {
            const packet = message?.literature;
            const count = this.literatureSelectedCount(message);

            return packet?.kind === 'results' && packet.proposal_draft_id > 0
                && count > 0 && count <= maximumLiteratureSelections && !this.literatureBusy(message)
                && !this.draft?.trim() && basis === 'full_text';
        },

        async draftSelectedLiterature(message, basis = 'full_text') {
            if (!this.canDraftLiterature(message, basis)) return false;

            const packet = message.literature;
            const selectedTokens = this.literatureSelectedTokens(message);
            packet.is_working = true;
            packet.error = '';
            this.literatureOperationPending = true;

            try {
                await this.sendPrompt(
                    `Draft RRL paragraphs from the readable paper content of the ${selectedTokens.length} selected ${selectedTokens.length === 1 ? 'study' : 'studies'} for my review.`,
                    { type: 'draft_literature', source_tokens: selectedTokens, evidence_basis: basis },
                    { proposal_draft_id: packet.proposal_draft_id },
                );
                return true;
            } catch {
                packet.error = 'The RRL draft could not be prepared. Try again.';
                return false;
            } finally {
                packet.is_working = false;
                this.literatureOperationPending = false;
            }
        },

        canConfirmLiterature(message) {
            const packet = message?.literature;

            return packet?.kind === 'draft' && packet.proposal_draft_id > 0 && !packet.confirmed
                && Array.isArray(packet.drafts) && packet.drafts.length > 0
                && packet.drafts.length <= maximumLiteratureSelections
                && packet.drafts.every((draft) => draft.draft_token && draft.source_token
                    && draft.evidence_basis === 'full_text'
                    && literatureText(draft.paragraph).length >= minimumLiteratureParagraphLength
                    && literatureText(draft.paragraph).length <= maximumLiteratureParagraphLength)
                && !this.literatureBusy(message) && !this.draft?.trim();
        },

        async confirmLiteratureDraft(message) {
            if (!this.canConfirmLiterature(message)) return false;

            const packet = message.literature;
            packet.is_working = true;
            packet.error = '';
            this.literatureOperationPending = true;

            try {
                const response = await this.sendPrompt(
                    'Add these reviewed RRL paragraphs and their references to my paper.',
                    {
                        type: 'confirm_literature',
                        drafts: packet.drafts.map((draft) => ({
                            draft_token: draft.draft_token,
                            paragraph: literatureText(draft.paragraph),
                        })),
                    },
                    { proposal_draft_id: packet.proposal_draft_id },
                );
                if (response?.literature?.kind === 'insertion'
                    && Number(response.literature.proposal_draft_id) === packet.proposal_draft_id) {
                    packet.confirmed = true;
                    return true;
                }
                return false;
            } catch {
                packet.error = 'The reviewed text could not be added. Try again.';
                return false;
            } finally {
                packet.is_working = false;
                this.literatureOperationPending = false;
            }
        },
    };
}
