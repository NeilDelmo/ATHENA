const maximumAge = 10 * 60 * 1000;

export function literatureInsertionHandoff(storage, accountId, now = () => Date.now()) {
    const keyFor = (proposalId) => `athena:reviewed-rrl:${Number(accountId)}:${Number(proposalId)}`;

    return {
        stage(packet) {
            if (!Number(accountId) || !Number(packet?.proposal_draft_id) || packet?.kind !== 'insertion') return false;
            const sources = (Array.isArray(packet.sources) ? packet.sources : [])
                .filter((source) => Number(source?.id) > 0 && source.rrl_draft_status === 'confirmed'
                    && ['abstract', 'full_text'].includes(source.rrl_evidence_basis)
                    && String(source.rrl_note || '').trim())
                .slice(0, 3);
            if (!sources.length) return false;

            try {
                storage.setItem(keyFor(packet.proposal_draft_id), JSON.stringify({
                    proposal_draft_id: Number(packet.proposal_draft_id), sources, created_at: now(),
                }));
                return true;
            } catch {
                return false;
            }
        },

        read(proposalId) {
            if (!Number(accountId) || !Number(proposalId)) return null;
            try {
                const raw = storage.getItem(keyFor(proposalId));
                if (!raw) return null;
                const packet = JSON.parse(raw);
                if (Number(packet.proposal_draft_id) !== Number(proposalId)
                    || !Number.isFinite(packet.created_at) || now() - packet.created_at > maximumAge
                    || packet.created_at > now() || !Array.isArray(packet.sources)) {
                    this.clear(proposalId);
                    return null;
                }
                return packet;
            } catch {
                this.clear(proposalId);
                return null;
            }
        },

        clear(proposalId) {
            try {
                storage.removeItem(keyFor(proposalId));
            } catch {
                // A blocked storage area must not prevent editing the paper.
            }
        },
    };
}

export function sameOriginPaperUrl(value, origin) {
    try {
        const url = new URL(String(value || ''), origin);
        return value && ['http:', 'https:'].includes(url.protocol) && url.origin === origin ? url.href : '';
    } catch {
        return '';
    }
}

export function currentReviewedLiteratureSources(packet, savedSources) {
    let changed = false;
    const sources = (Array.isArray(packet?.sources) ? packet.sources : []).slice(0, 3).map((reviewed) => {
        const saved = savedSources.find((source) => Number(source.id) === Number(reviewed.id));
        if (!saved || saved.rrl_draft_status !== 'confirmed'
            || String(saved.rrl_note || '').trim() !== String(reviewed.rrl_note || '').trim()
            || saved.rrl_evidence_basis !== reviewed.rrl_evidence_basis) {
            changed = true;
            return null;
        }
        return saved;
    }).filter(Boolean);
    return { sources, changed };
}
