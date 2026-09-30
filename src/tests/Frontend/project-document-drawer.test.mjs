import assert from 'node:assert/strict';
import test from 'node:test';
import { acceptedProjectPdfFiles, projectDocumentDrawer } from '../../resources/js/project-document-drawer.js';

test('signing shortcut opens signed papers and the Files button returns to all documents', () => {
    const drawer = projectDocumentDrawer();
    drawer.openDrawer('signed_papers');
    assert.equal(drawer.open, true);
    assert.equal(drawer.activeCategory, 'signed_papers');
    drawer.openDrawer();
    assert.equal(drawer.activeCategory, 'all');
});

test('the project document dropzone accepts only unique PDFs', () => {
    const proposal = { name: 'proposal.pdf', type: 'application/pdf', size: 120, lastModified: 1 };
    const scanned = { name: 'scanned.PDF', type: '', size: 240, lastModified: 2 };
    const wordFile = { name: 'notes.docx', type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', size: 80, lastModified: 3 };

    assert.deepEqual(acceptedProjectPdfFiles([proposal, proposal, scanned, wordFile]), [proposal, scanned]);
});

test('the project document dropzone limits each batch to ten PDFs', () => {
    const files = Array.from({ length: 12 }, (_, index) => ({
        name: `document-${index}.pdf`,
        type: 'application/pdf',
        size: index + 1,
        lastModified: index,
    }));

    assert.equal(acceptedProjectPdfFiles(files).length, 10);
});
