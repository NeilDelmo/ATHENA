import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { chromium } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const uploadHelpers = app.slice(app.indexOf('function revisionGenerationConfig('), app.indexOf('async function initializeProposalAlerts('));
const editors = [
    'workPlanWizard', 'proposalDraftWorkPlan', 'proposalDraftLineItemBudget',
    'proposalDraftExpenseBreakdown', 'proposalDraftCurriculumVitae', 'proposalDraftDetailedProposal',
];
const downloadMethods = Object.fromEntries(editors.map((name) => {
    const component = app.indexOf(`Alpine.data('${name}',`);
    const start = app.indexOf('    async downloadDocument()', component);
    return [name, app.slice(start, app.indexOf('    printPreview()', start))];
}));

test('every generated revision paper stages only the source version captured before PDF generation', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent('<form data-paper-form><input type="hidden" name="document_version" value="3"></form>');
        await page.addScriptTag({ content: `
            function isEmbeddedRevisionEditor() { return true; }
            function embeddedRevisionFileSaved(payload) { document.querySelector('[name="document_version"]').value = String(payload.document_version); }
            ${uploadHelpers}
            window.editors = {};
            ${Object.entries(downloadMethods).map(([name, method]) => `
                window.editors[${JSON.stringify(name)}] = (config) => ({
                    ${method}
                    validateForm: () => true, isOverBudget: () => false,
                    formData: () => new FormData(document.querySelector('form')),
                    workPlanFormData: () => new FormData(document.querySelector('form')),
                });`).join('\n')}
        ` });
        for (const name of editors) {
            const result = await page.evaluate(async (editorName) => {
                const version = document.querySelector('[name="document_version"]');
                const scenarios = [];
                const config = { downloadUrl: '/generate', revisionUploadUrl: '/stage', revisionDocumentType: 'test_paper', csrfToken: 'test' };
                for (const change of ['saved', 'unsaved', 'none', 'late']) {
                    version.value = '3';
                    let fingerprint = 'saved source';
                    window.athenaRevisionEditor = { sourceFingerprint: () => fingerprint };
                    const generated = [];
                    const staged = [];
                    window.fetch = async (url, options) => {
                        if (url === '/generate') return new Promise((resolve) => generated.push((text) => resolve({
                            ok: true, status: 200, headers: new Headers({ 'Content-Disposition': 'attachment; filename="paper.pdf"' }),
                            blob: async () => new Blob([text], { type: 'application/pdf' }),
                        })));
                        const file = options.body.get('file');
                        staged.push({ version: options.body.get('document_version'), text: await file.text() });
                        return { ok: true, json: async () => ({ document_version: 4, document_type: 'test_paper', draft_id: 36, filename: file.name }) };
                    };
                    const state = window.editors[editorName](config);
                    const download = state.downloadDocument();
                    if (change === 'saved') version.value = '4';
                    if (change === 'unsaved') fingerprint = 'new unsaved edits';
                    if (change === 'late') {
                        const newerState = window.editors[editorName](config);
                        const newerDownload = newerState.downloadDocument();
                        generated[1]('current PDF');
                        await newerDownload;
                    }
                    generated[0](change === 'late' ? 'old PDF' : 'current PDF');
                    const payload = await download;
                    scenarios.push({ change, staged, error: state.downloadError, loading: state.downloadLoading, version: version.value, returned: Boolean(payload) });
                }
                return scenarios;
            }, name);
            for (const scenario of result) {
                assert.equal(scenario.loading, false, name);
                if (scenario.change === 'saved' || scenario.change === 'unsaved') {
                    assert.deepEqual(scenario.staged, [], `${name}: obsolete PDF must not be uploaded`);
                    assert.match(scenario.error, /paper changed while its PDF was being prepared/, name);
                    assert.equal(scenario.returned, false, name);
                } else {
                    assert.deepEqual(scenario.staged, [{ version: '3', text: 'current PDF' }], `${name}: stage exact generated bytes with their captured version`);
                    assert.equal(scenario.version, '4', name);
                    if (scenario.change === 'late') {
                        assert.match(scenario.error, /paper changed while its PDF was being prepared/, name);
                        assert.equal(scenario.returned, false, name);
                    } else {
                        assert.equal(scenario.error, '', name);
                        assert.equal(scenario.returned, true, name);
                    }
                }
            }
        }
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});
