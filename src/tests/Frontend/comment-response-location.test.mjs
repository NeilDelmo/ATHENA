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

test('short labels, checkbox choices and numeric fields match whole PDF values with section context', () => {
    const passages = [
        { page: 1, paragraph: 1, text: 'I. Schedule:' },
        { page: 1, paragraph: 2, text: 'June' },
        { page: 1, paragraph: 3, text: 'III. Sustainable Development Goal:' },
        { page: 1, paragraph: 4, text: 'SDG4: Quality Education' },
        { page: 2, paragraph: 1, text: 'Quantity:' },
        { page: 2, paragraph: 2, text: '20' },
        { page: 2, paragraph: 3, text: 'Amount:' },
        { page: 2, paragraph: 4, text: '20' },
        { page: 2, paragraph: 5, text: '₱ 6,000.00' },
    ];
    assert.deepEqual(matchingPassages(passages, ['June']), [passages[1]]);
    assert.deepEqual(matchingPassages(passages, [{ text: 'SDG4: Quality Education', context: 'III. Sustainable Development Goal:' }]), [passages[3]]);
    assert.deepEqual(matchingPassages(passages, [{ text: '20', context: 'Quantity:' }]), [passages[5]]);
    assert.deepEqual(matchingPassages(passages, ['6000']), [passages[8]]);
    assert.deepEqual(matchingPassages(passages, ['6']), []);
    assert.equal(matchingPassages([...passages.slice(0, 6), { page: 2, paragraph: 3, text: '20' }, ...passages.slice(6)], [{ text: '20', context: 'Quantity:' }]).length, 2);
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

test('response locations are filled automatically and ambiguous text needs only confirmation without manual numbers', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form><section data-revision-step="2" hidden></section>${['annotation_1', 'overall'].map((key) => `
            <fieldset data-comment-response-location data-response-key="${key}" data-response-document="detailed_proposal">
                <input type="checkbox" data-comment-response-no-change>
                <input type="hidden" data-location-document value="detailed_proposal">
                <button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
                <p data-location-status></p><input type="hidden" data-comment-response-page><input type="hidden" data-comment-response-paragraph>
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
        assert.equal(await page.locator('select, input[type="number"]').count(), 0);
        await second.locator('[data-location-candidate]').click();
        assert.equal(await second.locator('[data-comment-response-page]').inputValue(), '4');
        await page.evaluate(() => {
            window.locations.invalidate('detailed_proposal');
            window.passages[0].page = 7;
        });
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '');
        await page.evaluate(() => window.locations.refresh());
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '7');
        await second.locator('[data-location-candidate]').click();
        await page.evaluate(() => window.locations.verify([]));
        assert.equal(await second.locator('[data-comment-response-page]').inputValue(), '7');
        await page.evaluate(() => { window.passages = []; });
        const error = await page.evaluate(async () => { try { await window.locations.verify([]); return ''; } catch (error) { return error.message; } });
        assert.match(error, /revised paper could not be read/);
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '');
        await first.locator('[data-comment-response-no-change]').check();
        await second.locator('[data-comment-response-no-change]').check();
        await page.evaluate(() => window.locations.verify([]));
        await page.evaluate(() => {
            window.passages = [{ page: 1, paragraph: 1, text: 'Revised recruitment uses 120 participants.', label: 'Detailed proposal' }, { page: 2, paragraph: 1, text: 'Revised recruitment uses 120 participants.', label: 'Detailed proposal' }];
            window.locations.invalidate('detailed_proposal');
            const field = document.querySelector('fieldset');
            delete field.dataset.locationAutomatic;
            delete field.dataset.locationText;
        });
        await first.locator('[data-comment-response-no-change]').uncheck();
        await page.evaluate(() => window.locations.refresh());
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '');
        assert.equal(await first.locator('[data-location-candidate]').count(), 2);
        const confirmation = await page.evaluate(async () => { try { await window.locations.verify([]); return null; } catch (error) { return { message: error.message, type: error.revisionDocumentType, key: error.revisionResponseKey }; } });
        assert.deepEqual(confirmation, { message: 'Confirm where this change appears in Detailed proposal.', type: 'detailed_proposal', key: 'annotation_1' });
        await first.locator('[data-location-candidate]').last().click();
        await page.evaluate(() => window.locations.verify([]));
        assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '2');
    } finally { await browser.close(); }
});

test('linked editor comments suggest only changed paragraphs and parse user text without executing HTML', async () => {
    const locationSource = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const revisionSource = readFileSync(new URL('../../resources/js/revision-workspace.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replaceAll('export default ', '').replaceAll('export ', '');
    const targetSource = readFileSync(new URL('../../resources/js/revision-target-focus.js', import.meta.url), 'utf8').replaceAll('export default ', '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form data-paper-form>
            <textarea id="introduction" name="introduction"></textarea><textarea id="rationale" name="rationale"></textarea>
            <label for="table_notes">Study findings:</label><textarea id="table_notes" name="table_notes"></textarea>
            <label for="deletion_notes">Discussion:</label><textarea id="deletion_notes" name="deletion_notes"></textarea>
            <label for="research_agenda">Research agenda:</label><select id="research_agenda" name="research_agenda">
                <option value="1">Inclusive Education</option><option value="2">Smart Communities and Digital Transformation</option>
            </select><input type="hidden" name="document_version" value="4">
        </form>`);
        await page.addScriptTag({ content: locationSource });
        await page.addScriptTag({ content: targetSource });
        await page.addScriptTag({ content: revisionSource });
        const result = await page.evaluate(() => {
            document.querySelector('#introduction').value = '<p>Existing first paragraph.</p><p>Revised recruitment uses 120 participants.</p><img src="missing" onerror="window.untrustedTextExecuted=true">';
            document.querySelector('#rationale').value = '<p>Changed rationale paragraph.</p>';
            const original = { introduction: '<p>Existing first paragraph.</p><p>Recruitment previously used 60 participants.</p>', rationale: '<p>Original rationale paragraph.</p>', research_agenda: '1' };
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
        const structured = await page.evaluate(() => {
            document.querySelector('#table_notes').value = '<h3>Study findings</h3><table><tr><th>Participants</th><th>Finding</th></tr><tr><td>120</td><td>Revised table finding for the intervention.</td></tr></table>';
            document.querySelector('#deletion_notes').value = '<p>Opening context stays.</p><p>Following paragraph now occupies the removed location.</p>';
            document.querySelector('#research_agenda').value = '2';
            const table = revisionChangedPassages(document, 'table_notes', {
                table_notes: '<h3>Study findings</h3><table><tr><th>Participants</th><th>Finding</th></tr><tr><td>120</td><td>Previous table finding.</td></tr></table>',
            });
            const deleted = revisionChangedPassages(document, 'deletion_notes', {
                deletion_notes: '<p>Opening context stays.</p><p>This middle paragraph was removed.</p><p>Following paragraph now occupies the removed location.</p>',
            });
            const selected = revisionChangedPassages(document, 'research_agenda', { research_agenda: '1' });
            const passages = [
                { page: 2, paragraph: 4, text: 'Revised table finding for the intervention.' },
                { page: 3, paragraph: 2, text: 'Following paragraph now occupies the removed location.' },
                { page: 1, paragraph: 3, text: 'Smart Communities and Digital Transformation' },
            ];
            return { table, deleted, selected, locations: [table, deleted, selected].map((hints) => matchingPassages(passages, hints).map(({ page, paragraph }) => [page, paragraph])) };
        });
        assert.deepEqual(structured.table, ['Revised table finding for the intervention.']);
        assert.deepEqual(structured.deleted, [{ text: 'Following paragraph now occupies the removed location.', context: 'Discussion:' }]);
        assert.deepEqual(structured.selected, [{ text: 'Smart Communities and Digital Transformation', context: 'Research agenda:' }]);
        assert.deepEqual(structured.locations, [[[2, 4]], [[3, 2]], [[1, 3]]]);
    } finally { await browser.close(); }
});

test('finishing a paper finds its response locations without preparing another unfinished paper', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form>${['work_plan', 'line_item_budget'].map((type) => `
            <fieldset data-comment-response-location data-response-key="${type}" data-response-document="${type}">
                <input type="radio" data-comment-response-action name="${type}" value="0" checked>
                <input type="radio" data-comment-response-action data-comment-response-no-change name="${type}" value="1">
                <input type="hidden" data-location-document value="${type}">
                <button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
                <p data-location-status></p><input type="hidden" data-comment-response-page><input type="hidden" data-comment-response-paragraph>
            </fieldset>`).join('')}</form>`);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.reads = [];
            window.locations = initializeCommentResponseLocations(document.querySelector('form'), {
                readDocuments: async (types) => {
                    window.reads.push(types);
                    if (types.includes('line_item_budget')) throw new Error('The budget is still unfinished.');
                    return new Map([['work_plan', { passages: [{ page: 2, paragraph: 1, text: 'Revised fieldwork is scheduled in June.', label: 'Work Plan' }] }]]);
                },
                suggestedTexts: () => ['Revised fieldwork is scheduled in June.'],
            });
            document.querySelector('form').dispatchEvent(new CustomEvent('revision-paper-reviewed', { detail: { documentType: 'work_plan' } }));
        });
        await page.waitForFunction(() => document.querySelector('[data-comment-response-page]').value === '2');
        assert.deepEqual(await page.evaluate(() => window.reads), [['work_plan']]);
        assert.equal(await page.locator('fieldset').last().locator('[data-comment-response-page]').inputValue(), '');
        assert.equal(await page.locator('fieldset').last().locator('[data-location-status]').innerText(), '');
        await page.locator('fieldset').first().locator('[data-comment-response-no-change]').check();
        await page.locator('fieldset').first().locator('[data-comment-response-action][value="0"]').check();
        assert.equal(await page.locator('fieldset').first().locator('[data-comment-response-page]').inputValue(), '2');
    } finally { await browser.close(); }
});

test('multiple changed paragraphs use the first unambiguous location and overall feedback finds its paper automatically', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form>
            <article data-revision-document="work_plan"><span data-revision-document-state data-modified="true"></span></article>
            <article data-revision-document="line_item_budget"><span data-revision-document-state data-modified="true"></span></article>
            <article data-revision-document="curriculum_vitae"><span data-revision-document-state data-modified="false"></span></article>
            <article data-revision-document="expense_breakdown"><input type="checkbox" data-revision-no-change checked></article>
            <fieldset data-comment-response-location data-response-key="overall_research_head" data-response-document="">
                <input type="hidden" data-location-document value=""><input type="checkbox" data-comment-response-no-change>
                <p data-location-status></p><button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
                <input type="hidden" data-comment-response-page><input type="hidden" data-comment-response-paragraph>
            </fieldset>
        </form>`);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.reads = [];
            window.locations = initializeCommentResponseLocations(document.querySelector('form'), {
                readDocuments: async (types) => {
                    window.reads.push(types);
                    return new Map([
                        ['work_plan', { passages: [
                            { page: 2, paragraph: 1, text: 'Revised fieldwork is scheduled in June.', label: 'Work Plan' },
                            { page: 3, paragraph: 1, text: 'The final report is due in October.', label: 'Work Plan' },
                        ] }],
                        ['line_item_budget', { passages: [{ page: 1, paragraph: 1, text: 'Equipment purchases cover three field teams.', label: 'Line Item Budget' }] }],
                    ]);
                },
                suggestedTexts: (_field, type) => type === 'work_plan'
                    ? ['Revised fieldwork is scheduled in June.', 'The final report is due in October.']
                    : ['Equipment purchases cover three field teams.'],
            });
        });
        assert.deepEqual(await page.evaluate(() => window.reads), []);
        await page.evaluate(() => window.locations.verify([]));
        assert.deepEqual(await page.evaluate(() => window.reads), [['work_plan', 'line_item_budget']]);
        assert.equal(await page.locator('[data-comment-response-page]').inputValue(), '2');
        assert.equal(await page.locator('fieldset').getAttribute('data-location-document'), 'work_plan');
        assert.equal(await page.locator('[data-location-candidate]').count(), 0);
        assert.equal(await page.locator('select, input[type="number"]').count(), 0);
        await page.locator('[data-comment-response-no-change]').check();
        assert.equal(await page.locator('[data-comment-response-page]').inputValue(), '');
        assert.match(await page.locator('[data-location-status]').innerText(), /No paper change/);
    } finally { await browser.close(); }
});

test('SDG checkbox, scalar fields and deleted paragraphs supply automatic location hints from the actual editor', async () => {
    const locationSource = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const revisionSource = readFileSync(new URL('../../resources/js/revision-workspace.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replaceAll('export default ', '').replaceAll('export ', '');
    const targetSource = readFileSync(new URL('../../resources/js/revision-target-focus.js', import.meta.url), 'utf8').replaceAll('export default ', '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form data-paper-form>
            <fieldset data-revision-section="section-sdgs"><legend>III. Sustainable Development Goal:</legend>
                <label><input name="sdgs[]" type="checkbox" value="1" checked>SDG1: No Poverty</label>
                <label><input name="sdgs[]" type="checkbox" value="4" checked>SDG4: Quality Education</label>
            </fieldset>
            <label for="amount">Amount:</label><input name="amount" id="amount" type="number" value="250">
            <label for="introduction">Introduction:</label><textarea name="introduction" id="introduction">Existing first paragraph.</textarea>
        </form>
        <form id="revision">${['section-sdgs', 'amount', 'introduction'].map((target) => `
            <fieldset data-comment-response-location data-response-key="${target}" data-response-document="detailed_proposal">
                <input type="hidden" data-location-document value="detailed_proposal"><input type="checkbox" data-comment-response-no-change>
                <p data-location-status></p><button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
                <input type="hidden" data-comment-response-page><input type="hidden" data-comment-response-paragraph>
            </fieldset>`).join('')}</form>`);
        await page.addScriptTag({ content: locationSource });
        await page.addScriptTag({ content: targetSource });
        await page.addScriptTag({ content: revisionSource });
        const hints = await page.evaluate(() => {
            const original = { sdgs: [1], amount: 100, introduction: '<p>Existing first paragraph.</p><p>Removed outdated paragraph.</p>' };
            window.hints = Object.fromEntries(['section-sdgs', 'amount', 'introduction'].map((target) => [target, revisionChangedPassages(document, target, original)]));
            window.locations = initializeCommentResponseLocations(document.querySelector('#revision'), {
                readDocuments: async () => new Map([['detailed_proposal', { passages: [
                    { page: 1, paragraph: 1, text: 'III. Sustainable Development Goal:', label: 'Detailed Proposal' },
                    { page: 1, paragraph: 2, text: 'SDG1: No Poverty', label: 'Detailed Proposal' },
                    { page: 1, paragraph: 3, text: 'SDG4: Quality Education', label: 'Detailed Proposal' },
                    { page: 2, paragraph: 1, text: 'Amount:', label: 'Detailed Proposal' },
                    { page: 2, paragraph: 2, text: '250', label: 'Detailed Proposal' },
                    { page: 3, paragraph: 1, text: 'Existing first paragraph.', label: 'Detailed Proposal' },
                ] }]]),
                suggestedTexts: (field) => window.hints[field.dataset.responseKey],
            });
            return window.hints;
        });
        assert.deepEqual(hints['section-sdgs'], [{ text: 'SDG4: Quality Education', context: 'III. Sustainable Development Goal:' }]);
        assert.deepEqual(hints.introduction, [{ text: 'Existing first paragraph.', context: 'Introduction:' }]);
        await page.evaluate(() => window.locations.verify([]));
        assert.deepEqual(await page.locator('[data-comment-response-location]').evaluateAll((fields) => fields.map((field) => [
            field.querySelector('[data-comment-response-page]').value,
            field.querySelector('[data-comment-response-paragraph]').value,
        ])), [['1', '3'], ['2', '2'], ['3', '1']]);
        assert.equal(await page.locator('[data-location-candidate]').count(), 0);
    } finally { await browser.close(); }
});

test('edits during PDF preparation cannot restore stale locations and unreadable output blocks submission', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form><fieldset data-comment-response-location data-response-key="annotation_7" data-response-document="work_plan">
            <input type="checkbox" data-comment-response-no-change><input type="hidden" data-location-document value="work_plan">
            <p data-location-status></p><button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
            <input type="hidden" data-comment-response-page value="99"><input type="hidden" data-comment-response-paragraph value="99">
        </fieldset></form>`);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.version = 1;
            window.locations = initializeCommentResponseLocations(document.querySelector('form'), {
                readDocuments: () => new Promise((resolve) => { window.completePdfRead = resolve; }),
                suggestedTexts: () => ['Revised fieldwork is scheduled in June.'],
                fingerprint: () => String(window.version),
            });
            window.reading = window.locations.refresh();
            window.version = 2;
            window.locations.invalidate('work_plan');
            window.completePdfRead(new Map([['work_plan', { passages: [{ page: 4, paragraph: 1, text: 'Revised fieldwork is scheduled in June.', label: 'Work Plan' }] }]]));
        });
        await page.evaluate(() => window.reading);
        assert.equal(await page.locator('[data-comment-response-page]').inputValue(), '');
        const failed = await page.evaluate(async () => {
            const verified = window.locations.verify([]).then(() => null, (error) => error.message);
            window.completePdfRead(new Map([['work_plan', { passages: [] }]]));
            return verified;
        });
        assert.equal(failed, 'The revised paper could not be read. Try preparing it again.');
        assert.equal(await page.locator('[data-location-retry]').isVisible(), true);
        assert.equal(await page.locator('[data-comment-response-page]').inputValue(), '');
    } finally { await browser.close(); }
});

test('background location reads settle before final verification and unchanged papers reuse their result', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form><fieldset data-comment-response-location data-response-key="annotation_7" data-response-document="work_plan">
            <input type="checkbox" data-comment-response-no-change><input type="hidden" data-location-document value="work_plan">
            <p data-location-status></p><button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
            <input type="hidden" data-comment-response-page><input type="hidden" data-comment-response-paragraph>
        </fieldset></form>`);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.reads = [];
            window.locations = initializeCommentResponseLocations(document.querySelector('form'), {
                readDocuments: (types, prepared) => {
                    window.reads.push({ types, prepared });
                    return new Promise((resolve) => { window.completePdfRead = resolve; });
                },
                suggestedTexts: () => ['Revised fieldwork is scheduled in June.'],
                fingerprint: () => 'version1',
            });
            window.result = new Map([['work_plan', { passages: [{ page: 4, paragraph: 1, text: 'Revised fieldwork is scheduled in June.', label: 'Work Plan' }] }]]);
            window.reading = window.locations.refresh(null, 'work_plan');
            window.settled = false;
            window.settling = window.locations.settle().then(() => { window.settled = true; });
            window.finalVerified = window.locations.verify([{ document_type: 'work_plan' }]);
        });
        assert.equal(await page.evaluate(() => window.settled), false);
        assert.equal(await page.evaluate(() => window.reads.length), 1);
        await page.evaluate(() => window.completePdfRead(window.result));
        await page.evaluate(() => window.settling);
        assert.equal(await page.evaluate(() => window.settled), true);
        await page.waitForFunction(() => window.reads.length === 2);
        assert.deepEqual(await page.evaluate(() => window.reads[1].prepared), [{ document_type: 'work_plan' }]);
        await page.evaluate(() => window.completePdfRead(window.result));
        await page.evaluate(() => window.finalVerified);
        await page.evaluate(() => window.locations.refresh(null, 'work_plan'));
        assert.equal(await page.evaluate(() => window.reads.length), 2);
        assert.equal(await page.locator('[data-comment-response-page]').inputValue(), '4');
    } finally { await browser.close(); }
});

test('global source changes invalidate other paper caches and unreadable PDFs can be retried', async () => {
    const source = readFileSync(new URL('../../resources/js/comment-response-location.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replaceAll('export ', '');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<form>${['work_plan', 'line_item_budget'].map((type) => `
            <fieldset data-comment-response-location data-response-key="${type}" data-response-document="${type}">
                <input type="checkbox" data-comment-response-no-change><input type="hidden" data-location-document value="${type}">
                <p data-location-status></p><button type="button" data-location-retry hidden>Try again</button><div data-location-confirm hidden></div>
                <input type="hidden" data-comment-response-page><input type="hidden" data-comment-response-paragraph>
            </fieldset>`).join('')}</form>`);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.version = 1;
            window.reads = [];
            window.unreadable = false;
            window.locations = initializeCommentResponseLocations(document.querySelector('form'), {
                readDocuments: async (types) => {
                    window.reads.push(types);
                    return new Map(types.map((type) => [type, { passages: window.unreadable ? [] : [{ page: window.version, paragraph: 1, text: 'Revised fieldwork is scheduled in June.', label: type }] }]));
                },
                suggestedTexts: () => ['Revised fieldwork is scheduled in June.'],
                fingerprint: () => String(window.version),
            });
        });
        await page.evaluate(() => window.locations.refresh(null, 'work_plan'));
        await page.evaluate(() => window.locations.refresh(null, 'line_item_budget'));
        await page.evaluate(() => {
            window.version = 2;
            window.locations.invalidate('line_item_budget');
        });
        await page.evaluate(() => window.locations.refresh(null, 'work_plan'));
        assert.deepEqual(await page.evaluate(() => window.reads), [['work_plan'], ['line_item_budget'], ['work_plan']]);
        assert.equal(await page.locator('[data-comment-response-page]').first().inputValue(), '2');
        await page.evaluate(() => {
            window.unreadable = true;
            window.locations.invalidate('work_plan');
        });
        await page.evaluate(() => window.locations.refresh(null, 'work_plan'));
        assert.equal(await page.locator('[data-location-retry]').first().isVisible(), true);
        await page.evaluate(() => { window.unreadable = false; });
        await page.locator('[data-location-retry]').first().click();
        await page.waitForFunction(() => document.querySelector('[data-comment-response-page]').value === '2');
        assert.equal(await page.evaluate(() => window.reads.length), 5);
        assert.equal(await page.locator('[data-location-retry]').first().isVisible(), false);
    } finally { await browser.close(); }
});
