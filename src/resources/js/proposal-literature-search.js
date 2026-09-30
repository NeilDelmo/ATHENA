const SEARCH_STOP_WORDS = new Set([
    'a', 'an', 'and', 'are', 'as', 'across', 'by', 'for', 'from', 'in', 'into', 'of', 'on', 'or',
    'the', 'to', 'with', 'all', 'this', 'that', 'their', 'its', 'using', 'use', 'based', 'web',
    'design', 'develop', 'developed', 'deploy', 'deployment', 'ensure', 'create', 'creating', 'implement',
    'implementation', 'provide', 'provided', 'project',
]);

function cleanSearchText(value) {
    return String(value || '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/[-]+/g, ' ')
        .replace(/[^\p{L}\p{N}\s-]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function meaningfulWords(value) {
    return cleanSearchText(value)
        .split(' ')
        .filter((word) => word.length > 2 && !SEARCH_STOP_WORDS.has(word.toLowerCase().replace(/^-/, '')));
}

export function buildLiteratureSearchQuery(contexts = [], maximumWords = 16) {
    const title = contexts.find((context) => context?.key === 'title')?.value || '';
    const titleWords = meaningfulWords(title).slice(0, 6);
    const remainingWords = contexts
        .filter((context) => context?.key !== 'title')
        .flatMap((context) => meaningfulWords(context?.value))
        .filter((word, index, words) => words.findIndex((candidate) => candidate.toLowerCase() === word.toLowerCase()) === index);
    const words = [...titleWords, ...remainingWords]
        .filter((word, index, allWords) => allWords.findIndex((candidate) => candidate.toLowerCase() === word.toLowerCase()) === index)
        .slice(0, maximumWords);

    return words.join(' ');
}

export function buildLiteratureSearchPayload(query, contexts = [], filters = {}) {
    const payload = { query: String(query || '').trim() };
    const context = contexts
        .map((entry) => cleanSearchText(entry?.value).slice(0, entry?.key === 'title' ? 500 : 1200))
        .filter(Boolean)
        .join('\n')
        .slice(0, 6000);

    if (context) payload.context = context;

    for (const key of ['year_from', 'year_to', 'min_citations']) {
        const value = filters[key];
        if (value === '' || value === null || value === undefined) continue;

        const number = Number(value);
        if (Number.isFinite(number)) payload[key] = number;
    }

    if (filters.open_access) payload.open_access = true;

    return payload;
}

export async function fetchLiteratureSearch(url, csrfToken, payload, request = globalThis.fetch) {
    const response = await request(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    if (response.status === 419 || response.redirected) {
        throw new Error('Your session has expired. Refresh the page, then search again.');
    }

    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error('The search returned an unexpected response. Refresh the page and try again.');
    }

    if (!response.ok) {
        throw new Error(Object.values(result?.errors || {}).flat()[0]
            || result?.message
            || (response.status === 429 ? 'Too many searches. Please wait a moment and retry.' : 'The literature search could not be completed right now.'));
    }

    if (!Array.isArray(result?.results)) {
        throw new Error('The search returned an unexpected response. Please try again.');
    }

    return result;
}

export function literatureSearchHistoryTitle(query, proposalTitle = '') {
    const normalizedQuery = cleanSearchText(query);
    const normalizedTitle = cleanSearchText(proposalTitle);

    if (normalizedTitle && normalizedQuery.toLowerCase().includes(normalizedTitle.toLowerCase())) {
        return normalizedTitle;
    }

    if (normalizedQuery.length <= 72) return normalizedQuery;

    return `${normalizedQuery.slice(0, 69).trimEnd()}…`;
}
