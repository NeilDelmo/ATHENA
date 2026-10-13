import { escapeAssistantHtml, safeAssistantSourceUrl } from './research-assistant-markdown.js';

const maximumHandoffAge = 10 * 60 * 1000;
const narrativeFields = new Set([
    'executive_brief', 'rationale', 'general_objective', 'introduction',
    'methodology.research_design', 'methodology.data_analysis',
]);

function plainRecord(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        && [Object.prototype, null].includes(Object.getPrototypeOf(value));
}

function positiveIdentifier(value) {
    if (typeof value !== 'number' && !(typeof value === 'string' && /^[1-9]\d*$/.test(value))) return null;
    const number = Number(value);
    return Number.isSafeInteger(number) && number > 0 ? number : null;
}

function suggestionIdentifier(value) {
    return typeof value === 'string' ? /^[a-z0-9_-]{1,80}$/i.test(value)
        : typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
}

function safeToken(value) {
    return typeof value === 'string' && /^[a-z0-9_-]{64}$/i.test(value);
}

function displayText(value, maximum) {
    return typeof value === 'string' ? value.trim().slice(0, maximum) : '';
}

function safeUrl(value) {
    return typeof value === 'string' && value.length <= 2048 ? safeAssistantSourceUrl(value) : '';
}

function safeCount(value) {
    return typeof value === 'number' && Number.isSafeInteger(value) && value >= 0 && value <= 100000 ? value : null;
}

function normalizeRrlAssessment(value) {
    if (!plainRecord(value)) return null;
    const count = safeCount(value.count);
    const status = ['empty', 'tracked', 'partial', 'unknown'].includes(value.count_status)
        ? value.count_status : 'unknown';
    const known = ['empty', 'tracked'].includes(status) && count !== null;

    return {
        count: known ? count : null,
        known_count: safeCount(value.known_count),
        count_status: known || ['partial', 'unknown'].includes(status) ? status : 'unknown',
        minimum: safeCount(value.minimum),
        shortfall: known ? safeCount(value.shortfall) : null,
        minimum_satisfied: known && typeof value.minimum_satisfied === 'boolean' ? value.minimum_satisfied : null,
        quality_verified: false,
        requirement_reference: displayText(value.requirement_reference, 80),
        sources: (Array.isArray(value.sources) ? value.sources : []).filter(plainRecord).slice(0, 5).map((source) => ({
            title: displayText(source.title, 300),
            reference: displayText(source.reference, 300),
            type: displayText(source.type, 160),
            category: displayText(source.category, 160),
            section: displayText(source.section, 300),
            page_start: positiveIdentifier(source.page_start),
            page_end: positiveIdentifier(source.page_end),
            source_kind: ['policy', 'appendix'].includes(source.source_kind) ? source.source_kind : null,
            document_version: displayText(source.document_version, 64),
            url: safeUrl(source.url) || null,
        })),
    };
}

export function allowedProposalChangeField(field) {
    return typeof field === 'string' && (narrativeFields.has(field)
        || /^specific_objectives\.([0-9]|[1-4][0-9])\.description$/.test(field));
}

export function normalizeAssistantProposalChanges(packet) {
    if (!plainRecord(packet)) return null;
    const proposalDraftId = positiveIdentifier(packet.proposal_draft_id);
    if (!proposalDraftId) return null;
    const identifiers = new Set();
    const tokens = new Set();
    const suggestions = [];

    for (const candidate of Array.isArray(packet.suggestions) ? packet.suggestions : []) {
        if (!plainRecord(candidate) || !suggestionIdentifier(candidate.id) || !safeToken(candidate.token)
            || candidate.paper_slug !== 'detailed-proposal' || !allowedProposalChangeField(candidate.field)
            || typeof candidate.before !== 'string' || candidate.before.length > 6000
            || typeof candidate.value !== 'string' || !candidate.value.trim() || candidate.value.length > 6000
            || identifiers.has(String(candidate.id)) || tokens.has(candidate.token)) continue;

        identifiers.add(String(candidate.id));
        tokens.add(candidate.token);
        suggestions.push({
            id: candidate.id, token: candidate.token, paper_slug: 'detailed-proposal',
            field: candidate.field, label: displayText(candidate.label, 160),
            before: candidate.before, value: candidate.value, reason: displayText(candidate.reason, 1000),
        });
        if (suggestions.length >= 3) break;
    }

    return {
        proposal_draft_id: proposalDraftId,
        proposal_title: displayText(packet.proposal_title, 300),
        editor_url: safeUrl(packet.editor_url),
        assessment: { rrl: normalizeRrlAssessment(plainRecord(packet.assessment) ? packet.assessment.rrl : null) },
        suggestions,
    };
}

export function serializeAssistantProposalChanges(packet) {
    return normalizeAssistantProposalChanges(packet);
}

export function proposalChangeRichTextValue(text) {
    if (typeof text !== 'string' || text.length > 6000) return '';
    return text.replace(/\r\n?/g, '\n').trim().split(/\n\s*\n/).filter((paragraph) => paragraph.trim())
        .map((paragraph) => `<p>${escapeAssistantHtml(paragraph).replaceAll('\n', '<br>')}</p>`).join('');
}

export function proposalChangeHandoff(storage, accountId, now = () => Date.now()) {
    const account = positiveIdentifier(accountId);
    const keyFor = (proposalId) => `athena:proposal-change:${account}:${proposalId}`;

    return {
        stage(packet) {
            if (!account || !plainRecord(packet)) return false;
            const proposalId = positiveIdentifier(packet.proposal_draft_id);
            if (!proposalId || !safeToken(packet.token) || !suggestionIdentifier(packet.suggestion_id)) return false;
            try {
                const timestamp = now();
                if (!Number.isSafeInteger(timestamp) || timestamp < 0) return false;
                storage.setItem(keyFor(proposalId), JSON.stringify({
                    proposal_draft_id: proposalId, token: packet.token,
                    suggestion_id: packet.suggestion_id, created_at: timestamp,
                }));
                return true;
            } catch {
                return false;
            }
        },
        read(proposalId) {
            const proposal = positiveIdentifier(proposalId);
            if (!account || !proposal) return null;
            try {
                const raw = storage.getItem(keyFor(proposal));
                if (!raw) return null;
                const packet = JSON.parse(raw);
                const timestamp = now();
                if (!plainRecord(packet) || packet.proposal_draft_id !== proposal || !safeToken(packet.token)
                    || !suggestionIdentifier(packet.suggestion_id) || !Number.isSafeInteger(packet.created_at)
                    || packet.created_at < 0 || !Number.isSafeInteger(timestamp)
                    || timestamp - packet.created_at >= maximumHandoffAge || packet.created_at > timestamp) {
                    this.clear(proposal);
                    return null;
                }
                return { proposal_draft_id: proposal, token: packet.token, suggestion_id: packet.suggestion_id, created_at: packet.created_at };
            } catch {
                this.clear(proposal);
                return null;
            }
        },
        clear(proposalId) {
            const proposal = positiveIdentifier(proposalId);
            if (!account || !proposal) return;
            try {
                storage.removeItem(keyFor(proposal));
            } catch {
                // Blocked storage must leave the proposal editor usable.
            }
        },
    };
}

export function researchAssistantProposalChanges() {
    return { proposalChangesOperationPending: false };
}
