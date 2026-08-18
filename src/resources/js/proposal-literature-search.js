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

export function literatureSearchHistoryTitle(query, proposalTitle = '') {
    const normalizedQuery = cleanSearchText(query);
    const normalizedTitle = cleanSearchText(proposalTitle);

    if (normalizedTitle && normalizedQuery.toLowerCase().includes(normalizedTitle.toLowerCase())) {
        return normalizedTitle;
    }

    if (normalizedQuery.length <= 72) return normalizedQuery;

    return `${normalizedQuery.slice(0, 69).trimEnd()}…`;
}
