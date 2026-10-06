import { proposalCitationField, proposalCitationLocator } from './proposal-semantic-editor.js';
import { escapeAssistantHtml } from './research-assistant-markdown.js';

export function prepareReviewedSourceDraft(draft, sources, fieldId, referenceNumberFor) {
    const field = proposalCitationField(fieldId);
    const paragraph = String(draft?.paragraph || '').trim();
    const evidence = Array.isArray(draft?.evidence)
        ? draft.evidence.slice(0, 12).filter((item) => item.kind !== 'note') : [];

    if (!field || draft?.can_insert !== true || paragraph.length < 20 || paragraph.length > 8000 || !evidence.length) return null;

    const bySource = new Map();
    for (const passage of evidence) {
        const source = sources.find((item) => Number(item.id) === Number(passage.source_link_id));
        if (!source || !passage.passage_id || !String(passage.quote || '').trim()) return null;

        if (!bySource.has(source.id)) bySource.set(source.id, { source, pages: [] });
        const page = Number(passage.page);
        if (Number.isInteger(page) && page > 0 && !bySource.get(source.id).pages.includes(page)) {
            bySource.get(source.id).pages.push(page);
        }
    }

    const citations = [...bySource.values()].map(({ source, pages }) => ({
        source,
        locator: proposalCitationLocator(pages.length ? `${pages.length > 1 ? 'pp.' : 'p.'} ${pages.sort((a, b) => a - b).join(', ')}` : ''),
    }));
    const markers = citations.map(({ source, locator }) => {
        const number = referenceNumberFor(source);
        if (!Number.isInteger(number) || number < 1) return '';
        return `<span data-proposal-citation="${Number(source.id)}"${locator ? ` data-proposal-locator="${escapeAssistantHtml(locator)}"` : ''}> [${number}${locator ? `, ${escapeAssistantHtml(locator)}` : ''}]</span>`;
    });

    if (markers.some((marker) => !marker)) return null;

    return {
        field,
        paragraph,
        citations,
        html: `<p>${escapeAssistantHtml(paragraph).replace(/\r?\n/g, '<br>')}${markers.join('')}</p>`,
        identity: `${field.id}|${paragraph}|${citations.map(({ source, locator }) => `${source.id}:${locator}`).join('|')}`,
    };
}
