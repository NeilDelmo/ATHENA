import { initializePaperPagination } from './proposal-preview-workspace';

if (document.readyState === 'complete') {
    initializePaperPagination(document);
} else {
    window.addEventListener('load', () => initializePaperPagination(document), { once: true });
}
