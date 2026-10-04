import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { chromium } from '@playwright/test';
import { pdfPageParagraphs, matchingPassages, readPdfPassages } from '../../resources/js/comment-response-location.js';

const text = (value, y, x = 72, width = 300, height = 12) => ({ text: value, y, x, width, height });

test('paragraph detection joins wrapped lines and numbers separate paragraphs on each PDF page', () => {
    const page = pdfPageParagraphs([
        text('First paragraph begins', 100), text('and continues on the next line.', 115),
        text('Second changed paragraph.', 145), text('1. A separate list item.', 161),
        text('Left table cell', 190, 72, 100), text('Right table cell', 190, 300, 100),
    ], 4);
    assert.deepEqual(page.map(({ page, paragraph, text }) => [page, paragraph, text]), [
        [4, 1, 'First paragraph begins and continues on the next line.'], [4, 2, 'Second changed paragraph.'],
        [4, 3, '1. A separate list item.'], [4, 4, 'Left table cell'], [4, 5, 'Right table cell'],
    ]);
    assert.equal(pdfPageParagraphs([text('Next page begins.', 100)], 5)[0].paragraph, 1);
    assert.deepEqual(pdfPageParagraphs([], 1), []);
    assert.equal(pdfPageParagraphs([text('An indented first line', 100, 108), text('continues at the left margin.', 115)], 1).length, 1);
});

test('matching revised text respects moved pages and leaves duplicate or short matches ambiguous', () => {
    const passages = [{ page: 5, paragraph: 2, text: 'Recruitment uses a stratified sample of 120 participants.' }];
    assert.deepEqual(matchingPassages(passages, ['Recruitment uses a stratified sample\n of 120 participants.']), passages);
    assert.deepEqual(matchingPassages(passages, ['Recruit']), []);
    assert.equal(matchingPassages([...passages, { ...passages[0], page: 6 }], [passages[0].text]).length, 2);
    assert.deepEqual(matchingPassages(passages, ['Old paragraph that was removed.']), []);
});

test('generated PDF blobs from the embedded editor are read as bytes across window boundaries', async () => {
    let destroyed = false;
    const blobFromAnotherWindow = { arrayBuffer: async () => new Uint8Array([37, 80, 68, 70]).buffer };
    await readPdfPassages(blobFromAnotherWindow, 'Generated PDF', async () => ({
        getDocument(options) {
            assert.deepEqual([...options.data], [37, 80, 68, 70]);
            assert.equal(options.url, undefined);
            return { promise: Promise.resolve({ numPages: 0 }), destroy: async () => { destroyed = true; } };
        },
    }));
    assert.equal(destroyed, true);
});

test('PDF extraction reads actual PDF pages rather than copying the reviewed annotation page', async () => {
    const pdfJs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [4 0 R 6 0 R] /Count 2 >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
        'BT /F1 12 Tf 72 700 Td (First paragraph on page one.) Tj 0 -40 Td (Second paragraph on page one.) Tj ET',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents 7 0 R >>',
        'BT /F1 12 Tf 72 700 Td (Revised recruitment is now on page two.) Tj ET',
    ];
    let contents = '%PDF-1.7\n';
    const offsets = [0];
    objects.forEach((object, index) => {
        offsets.push(contents.length);
        if (index === 4 || index === 6) object = `<< /Length ${object.length} >>\nstream\n${object}\nendstream`;
        contents += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });
    const xref = contents.length;
    contents += `xref\n0 8\n0000000000 65535 f \n${offsets.slice(1).map((offset) => String(offset).padStart(10, '0') + ' 00000 n \n').join('')}trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
    const passages = await readPdfPassages(new Blob([contents], { type: 'application/pdf' }), 'Revised study', async () => pdfJs);
    assert.equal(passages.length, 3);
    assert.equal(passages[1].paragraph, 2);
    assert.deepEqual(matchingPassages(passages, ['Revised recruitment is now on page two.']).map(({ page, paragraph }) => [page, paragraph]), [[2, 1]]);
});

test('response location picker fills numbers, rejects ambiguous matches, clears stale values and preserves manual fallback', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form><section data-revision-step="3" hidden></section>${['annotation_1', 'overall'].map((key) => `
            <fieldset data-comment-response-location data-response-key="${key}">
                <input type="checkbox" data-comment-response-no-change>
                <select data-location-document><option value="">Choose</option><option value="detailed_proposal">Detailed proposal</option></select>
                <select data-location-passage></select><button type="button" data-location-refresh>Find page and paragraph</button>
                <p data-location-status></p><input type="number" data-comment-response-page><input type="number" data-comment-response-paragraph>
            </fieldset>`).join('')}</form>`);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.passages = [{ page: 4, paragraph: 2, text: 'Revised recruitment uses 120 participants.', label: 'Detailed proposal' }];
            window.locations = initializeCommentResponseLocations(document.querySelector('form'), {
                readDocuments: async () => new Map([['detailed_proposal', { passages: window.passages }]]),
                suggestedTexts: (field) => field.dataset.responseKey === 'annotation_1' ? ['Revised recruitment uses 120 participants.'] : [],
            });
        });
        await page.evaluate(() => window.locations.refresh());
        const first = page.locator('fieldset').first();
        const second = page.locator('fieldset').last();
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '4');
        assert.equal(await first.locator('[data-comment-response-paragraph]').inputValue(), '2');
        assert.equal(await second.locator('[data-comment-response-page]').inputValue(), '');
        await second.locator('[data-location-passage]').selectOption('0');
        assert.equal(await second.locator('[data-comment-response-page]').inputValue(), '4');
        await page.evaluate(() => {
            window.locations.invalidate('detailed_proposal');
            window.passages[0].page = 7;
        });
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '');
        await page.evaluate(() => window.locations.refresh());
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '7');
        await second.locator('[data-comment-response-page]').fill('8');
        await second.locator('[data-comment-response-paragraph]').fill('3');
        await page.evaluate(() => window.locations.verify([]));
        assert.equal(await second.locator('[data-comment-response-page]').inputValue(), '8');
        await page.evaluate(() => { window.passages = []; });
        const error = await page.evaluate(async () => { try { await window.locations.verify([]); return ''; } catch (error) { return error.message; } });
        assert.match(error, /could not be located in the final revised PDF/);
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '');
        await first.locator('[data-comment-response-no-change]').check();
        await page.evaluate(() => window.locations.verify([]));
        await page.evaluate(() => {
            window.passages = [{ page: 1, paragraph: 1, text: 'Revised recruitment uses 120 participants.', label: 'Detailed proposal' }, { page: 2, paragraph: 1, text: 'Revised recruitment uses 120 participants.', label: 'Detailed proposal' }];
            const field = document.querySelector('fieldset');
            delete field.dataset.locationAutomatic;
            delete field.dataset.locationText;
        });
        await first.locator('[data-comment-response-no-change]').uncheck();
        await page.evaluate(() => window.locations.refresh());
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '');
    } finally { await browser.close(); }
});

test('linked editor comments suggest only changed paragraphs and parse user text without executing HTML', async () => {
    const locationSource = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const revisionSource = readFileSync(new URL('../../resources/js/revision-workspace.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replaceAll('export default ', '').replaceAll('export ', '');
    const targetSource = readFileSync(new URL('../../resources/js/revision-target-focus.js', import.meta.url), 'utf8').replaceAll('export default ', '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<form data-paper-form><textarea id="introduction" name="introduction"></textarea><textarea id="rationale" name="rationale"></textarea><input type="hidden" name="document_version" value="4"></form>');
        await page.addScriptTag({ content: locationSource });
        await page.addScriptTag({ content: targetSource });
        await page.addScriptTag({ content: revisionSource });
        const result = await page.evaluate(() => {
            document.querySelector('#introduction').value = '<p>Existing first paragraph.</p><p>Revised recruitment uses 120 participants.</p><img src="missing" onerror="window.untrustedTextExecuted=true">';
            document.querySelector('#rationale').value = '<p>Changed rationale paragraph.</p>';
            const original = { introduction: '<p>Existing first paragraph.</p><p>Recruitment previously used 60 participants.</p>', rationale: '<p>Original rationale paragraph.</p>' };
            return {
                linked: revisionChangedPassages(document, 'introduction', original),
                overall: revisionChangedPassages(document, null, original),
                unchanged: revisionChangedPassages(document, 'rationale', { ...original, rationale: '<p>Changed rationale paragraph.</p>' }),
            };
        });
        assert.deepEqual(result.linked, ['Revised recruitment uses 120 participants.']);
        assert.deepEqual(result.overall, ['Revised recruitment uses 120 participants.', 'Changed rationale paragraph.']);
        assert.deepEqual(result.unchanged, []);
        assert.equal(await page.evaluate(() => Boolean(window.untrustedTextExecuted)), false);
    } finally { await browser.close(); }
});
