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
        can_synthesize: source?.can_synthesize === true && evidenceBasis !== 'metadata_only' && Boolean(description),
    };
}

export function normalizeAssistantLiterature(packet) {
    const proposalDraftId = Number(packet?.proposal_draft_id);

    if (!['results', 'draft', 'insertion'].includes(packet?.kind)
        || !Number.isSafeInteger(proposalDraftId) || proposalDraftId <= 0) return null;

    const normalized = {
        kind: packet.kind,
        proposal_draft_id: proposalDraftId,
        proposal_title: literatureText(packet.proposal_title),
        notice: literatureText(packet.notice),
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
            .map(normalizeLiteratureSource)
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

    const persisted = {
        kind: normalized.kind,
        proposal_draft_id: normalized.proposal_draft_id,
        proposal_title: normalized.proposal_title,
        notice: normalized.notice,
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

export function researchAssistantLiterature() {
    return {
        literatureOperationPending: false,

        literatureBusy(message) {
            return Boolean(this.isLoading || this.retryAfter > 0 || this.literatureOperationPending || message?.literature?.is_working);
        },

        literatureEvidenceLabel(source) {
            if (source?.evidence_basis === 'full_text') return 'Based on loaded full text';
            if (source?.evidence_basis === 'abstract') return 'Based on abstract';
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

        canDraftLiterature(message) {
            const packet = message?.literature;
            const count = this.literatureSelectedCount(message);

            return packet?.kind === 'results' && packet.proposal_draft_id > 0
                && count > 0 && count <= maximumLiteratureSelections && !this.literatureBusy(message);
        },

        async draftSelectedLiterature(message) {
            if (!this.canDraftLiterature(message)) return false;

            const packet = message.literature;
            const selectedTokens = this.literatureSelectedTokens(message);
            packet.is_working = true;
            packet.error = '';
            this.literatureOperationPending = true;

            try {
                await this.sendPrompt(
                    `Draft RRL paragraphs from the ${selectedTokens.length} selected ${selectedTokens.length === 1 ? 'study' : 'studies'} for my review.`,
                    { type: 'draft_literature', source_tokens: selectedTokens },
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
                    && draft.evidence_basis !== 'metadata_only'
                    && literatureText(draft.paragraph).length >= minimumLiteratureParagraphLength
                    && literatureText(draft.paragraph).length <= maximumLiteratureParagraphLength)
                && !this.literatureBusy(message);
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
