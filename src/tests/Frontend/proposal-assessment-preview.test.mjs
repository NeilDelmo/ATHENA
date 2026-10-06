import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';

const read = (path) => readFileSync(resolve(path), 'utf8');
const script = (path) => read(path).replace(/^import .*;\r?\n/gm, '').replaceAll('export function ', 'function ');
const hub = read('resources/views/faculty/proposal-drafts/show.blade.php');
const assessmentSection = hub.slice(hub.indexOf('<section data-automatic-assessment-forms'), hub.indexOf('</section>', hub.indexOf('<section data-automatic-assessment-forms')) + 10);
const paperSection = hub.slice(hub.indexOf('<div data-editable-proposal-papers'), hub.indexOf('<section data-automatic-assessment-forms'));
const workspaceClasses = hub.match(/class="(mx-auto max-w-7xl[^\"]+)"/)[1];
const reviewPackage = read('resources/views/faculty/proposal-drafts/_review-package.blade.php');
const reviewSections = reviewPackage.slice(reviewPackage.indexOf('<section aria-labelledby="review-papers-heading"'), reviewPackage.indexOf('<section aria-labelledby="review-collaborators-heading"'));
const reviewRoot = read('resources/views/livewire/proposal-draft-review-package.blade.php').split('\n')[0];

function renderForms() {
    return JSON.parse(execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\Auth::setUser((new App\Models\User)->forceFill(['id' => 1]));
        Illuminate\Support\Facades\Gate::before(fn () => true);
        Illuminate\Support\Facades\View::share('canEditDraft', true);
        $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $draft = (new App\Models\ProposalDraft)->forceFill(['id' => 51, 'project_title' => 'Community Survey']);
        $forms = collect(['gad-checklist', 'initial-screening-form'])->map(fn ($slug) => [
            'paper' => app(App\Support\ProposalPaperCatalog::class)->get($slug), 'complete' => true,
        ]);
        $papers = app(App\Support\ProposalPaperCatalog::class)->all()->reject(fn ($paper) => $paper['mode'] === 'automatic')->map(fn ($paper) => [
            'paper' => $paper, 'complete' => false, 'needs_attention' => false, 'status' => 'Not started',
            'documents' => collect(), 'submission_filename' => 'proposal.pdf',
        ]);
        $checklist = app(App\Support\ProposalPaperCatalog::class)->all()->map(fn ($paper) => [
            'paper' => $paper, 'complete' => true, 'needs_attention' => false, 'status' => 'Complete',
            'documents' => $paper['mode'] === 'automatic' ? collect() : collect([(object) [
                'source_data' => ['entries' => [['objective' => 'Survey communities', 'expected_output' => 'Survey dataset', 'activity' => 'Conduct survey', 'months' => [1, 2, 3]]]],
                'file_path' => null, 'mime_type' => null,
            ]]), 'submission_filename' => 'proposal.pdf',
        ]);
        $reviewData = [
            'proposalDraft' => $draft, 'checklist' => $checklist,
            'reviewPapers' => $checklist->reject(fn ($row) => $row['paper']['mode'] === 'automatic'),
            'assessmentForms' => $checklist->filter(fn ($row) => $row['paper']['mode'] === 'automatic'),
            'detailedProposalSource' => [], 'workPlanSource' => $checklist->get('work-plan')['documents']->first()->source_data,
            'lineItemBudgetSource' => [], 'expenseBreakdownSource' => [], 'curriculumVitaeSource' => [],
        ];
        $review = Illuminate\Support\Facades\Blade::render($input['review'], $reviewData);
        foreach ($checklist as $item) {
            foreach ($item['documents'] as $document) {
                $document->file_path = 'prepared.pdf';
                $document->mime_type = 'application/pdf';
                $document->original_filename = 'prepared.pdf';
                $document->lock_version = 1;
            }
        }
        echo json_encode([
            'review' => $review,
            'preparedReview' => Illuminate\Support\Facades\Blade::render($input['review'], $reviewData),
            'workspace' => Illuminate\Support\Facades\Blade::render($input['section'], ['proposalDraft' => $draft, 'automaticChecklist' => $forms]),
            'papers' => Illuminate\Support\Facades\Blade::render($input['papers'], ['proposalDraft' => $draft, 'editableChecklist' => $papers, 'templates' => collect()]),
            'gad' => Illuminate\Support\Facades\Blade::render($input['gad'], ['gadChecklist' => App\Support\GADChecklistData::fromValidated([
                'project_title' => 'Community Survey', 'project_leader' => 'Faculty Owner',
            ])]),
        ], JSON_THROW_ON_ERROR);
    `], {
        input: JSON.stringify({
            section: assessmentSection,
            review: `<x-modal name="proposal-review" :show="true" focusable>${reviewRoot}<div :inert="previewFullscreen">${reviewSections}<button type="button">Turn in proposal</button></div><x-proposal-paper-preview panel-id="review-paper-preview-panel" /></div></x-modal>`,
            papers: paperSection,
            gad: read('resources/views/faculty/gad-checklist/preview.blade.php')
                .replace("@vite('resources/css/gad-checklist-print.css')", `<style>${read('resources/css/gad-checklist-print.css')}</style>`),
        }),
        encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' }, maxBuffer: 3 * 1024 * 1024,
    }));
}

function screeningPdf() {
    const text = 'BT /F1 18 Tf 40 740 Td (Initial Screening Form) Tj 0 -30 Td /F1 12 Tf (Research Project Title: Community Survey) Tj ET';
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        `<< /Length ${text.length} >>\nstream\n${text}\nendstream`,
    ];
    let pdf = '%PDF-1.7\n';
    const offsets = [0];
    objects.forEach((object, index) => {
        offsets.push(pdf.length);
        pdf += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });
    const startXref = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    pdf += offsets.slice(1).map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`).join('');
    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${startXref}\n%%EOF`;
    return Buffer.from(pdf);
}

const manifest = JSON.parse(read('public/build/manifest.json'));
const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css]
    .map((file) => read(`public/build/${file}`)).join('\n');

async function setup(page, forms, dark = false, showPapers = false, review = false) {
    await page.route('**/pdf.mjs', (route) => route.fulfill({ contentType: 'text/javascript', body: read('node_modules/pdfjs-dist/build/pdf.mjs') }));
    await page.route('**/pdf.worker.mjs', (route) => route.fulfill({ contentType: 'text/javascript', body: read('node_modules/pdfjs-dist/build/pdf.worker.mjs') }));
    await page.route('**/gad-checklist/preview', (route) => route.fulfill({ contentType: 'text/html', body: forms.gad }));
    await page.route('**/initial-screening-form/preview', (route) => route.fulfill({ contentType: 'application/pdf', body: screeningPdf() }));
    await page.route('**/workspace', (route) => route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles}\n${read('resources/css/proposal-paper-workspace.css')}\n[x-cloak]{display:none!important}</style></head><body class="font-sans bg-gray-50 dark:bg-slate-950">
        <div style="height:700px">Proposal workspace</div><div data-app-content-shell><main class="p-4"><div><div class="${workspaceClasses}">${review === 'prepared' ? forms.preparedReview : review ? forms.review : `${showPapers ? forms.papers : ''}${forms.workspace}`}</div></div></main></div><div style="height:800px"></div></body></html>` }));
    await page.goto('http://assessment-preview.test/workspace');
    await page.addScriptTag({ type: 'module', content: `
        import * as pdfJs from 'http://assessment-preview.test/pdf.mjs';
        pdfJs.GlobalWorkerOptions.workerSrc = 'http://assessment-preview.test/pdf.worker.mjs';
        window.loadPdfJs = () => Promise.resolve(pdfJs);
    ` });
    await page.waitForFunction(() => typeof window.loadPdfJs === 'function');
    await page.addScriptTag({ content: `${script('resources/js/proposal-preview-workspace.js')}\n${script('resources/js/proposal-paper-workspace.js')}\n${script('resources/js/proposal-assessment-preview.js')}
        document.addEventListener('alpine:init', () => Alpine.data('proposalAssessmentPreview', (config) => {
            const state = proposalAssessmentPreview(config);
            const initialize = state.init;
            state.init = function () { window.assessmentState = this; initialize.call(this); };
            return state;
        }));
    ` });
    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
    await page.waitForFunction(() => Boolean(window.assessmentState));
}

test('prepared proposal PDFs are fetched and rendered in the review dialog', async () => {
    const forms = renderForms();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
        const requests = [];
        await page.route('**/submission-files/*', (route) => {
            requests.push({ path: new URL(route.request().url()).pathname, method: route.request().method() });
            return route.fulfill({ contentType: 'application/pdf', body: screeningPdf() });
        });
        await setup(page, forms, false, false, 'prepared');
        const previews = page.getByRole('button', { name: 'Preview prepared PDF', exact: true });
        assert.equal(await previews.count(), 5);
        for (const index of [0, 1, 2, 3, 4]) {
            const trigger = previews.nth(index);
            await trigger.click();
            await page.waitForFunction(() => window.assessmentState.previewReady || window.assessmentState.previewError);
            assert.equal(await page.evaluate(() => window.assessmentState.previewError), '');
            const iframe = page.locator('iframe');
            await iframe.contentFrame().locator('.assessment-page img').waitFor();
            assert.ok(await iframe.evaluate((frame) => frame.contentDocument.querySelector('img').naturalWidth > 1000));
            assert.equal(page.context().pages().length, 1);
            assert.equal(page.url(), 'http://assessment-preview.test/workspace');
            await page.getByRole('button', { name: 'Close preview', exact: true }).click();
            await page.locator('#review-paper-preview-panel').waitFor({ state: 'hidden' });
            assert.equal(await trigger.evaluate((element) => element === document.activeElement), true);
        }
        assert.deepEqual(requests.map((request) => request.path.split('/').at(-1)), [
            'detailed-proposal', 'work-plan', 'expense-breakdown', 'line-item-budget', 'curriculum-vitae',
        ]);
        assert.ok(requests.every((request) => request.method === 'GET'));
        assert.equal(await page.getByRole('button', { name: 'Turn in proposal', exact: true }).isVisible(), true);
    } finally {
        await browser.close();
    }
});

test('automatic assessment forms share the main papers edges on wide and narrow screens', async () => {
    const forms = renderForms();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1920, 1440, 390, 320]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1100 } });
                await setup(page, forms, dark, true);
                const papers = await page.locator('[data-editable-proposal-papers]').boundingBox();
                const assessments = await page.locator('[data-automatic-assessment-forms]').boundingBox();
                assert.ok(Math.abs(papers.x - assessments.x) < 1, 'left edges align');
                assert.ok(Math.abs(papers.x + papers.width - assessments.x - assessments.width) < 1, 'right edges align');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (process.env.ASSESSMENT_SCREENSHOT_DIRECTORY && width === 1920 && !dark) {
                    await page.locator('[data-automatic-assessment-forms]').screenshot({ path: resolve(process.env.ASSESSMENT_SCREENSHOT_DIRECTORY, 'assessment-forms-aligned.png') });
                }
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('both assessment forms open the shared viewer with working zoom, print, download and position restoration in both themes', async () => {
    const forms = renderForms();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                const consoleErrors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
                await setup(page, forms, dark);
                assert.equal(await page.locator('.proposal-preview-dock').isVisible(), false);

                for (const name of ['GAD Generic Checklist', 'Initial Screening Form']) {
                    const trigger = page.getByRole('button', { name: `Preview ${name}`, exact: true });
                    await trigger.scrollIntoViewIfNeeded();
                    const position = await page.evaluate(() => window.scrollY);
                    await trigger.click();
                    await page.waitForFunction(() => !window.assessmentState.previewLoading && (window.assessmentState.previewReady || window.assessmentState.previewError));
                    assert.equal(await page.evaluate(() => window.assessmentState.previewError), '', `${name} at ${width}px: ${consoleErrors.join('\n')}`);
                    assert.equal(page.url(), 'http://assessment-preview.test/workspace');
                    assert.equal(await page.getByRole('dialog', { name, exact: true }).isVisible(), true);
                    assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
                    assert.equal(await page.locator('[data-automatic-assessment-forms] > div').evaluate((element) => element.inert), true);
                    assert.equal(await page.getByRole('button', { name: 'Close preview', exact: true }).evaluate((element) => element === document.activeElement), true);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    const iframe = page.locator('iframe');
                    await page.waitForFunction(() => document.querySelector('iframe').contentDocument?.body?.style.zoom);
                    if (name === 'GAD Generic Checklist') {
                        await page.waitForFunction(() => {
                            const frame = document.querySelector('iframe');
                            const firstPage = frame.contentDocument?.querySelector('.gad-page');
                            return firstPage && firstPage.getBoundingClientRect().height <= frame.clientHeight + 1;
                        });
                        assert.equal(await iframe.evaluate((frame) => frame.contentDocument.querySelectorAll('.gad-page').length), 7);
                        assert.ok(await iframe.evaluate((frame) => frame.contentDocument.body.textContent.includes('Community Survey')));
                        const fittedPage = await iframe.evaluate((frame) => ({
                            height: frame.contentDocument.querySelector('.gad-page').getBoundingClientRect().height,
                            viewport: frame.clientHeight,
                            zoom: frame.contentDocument.body.style.zoom,
                            fit: window.assessmentState.previewFit,
                        }));
                        assert.ok(fittedPage.height <= fittedPage.viewport + 1, `Fit page should show the first assessment page at ${width}px: ${JSON.stringify({ ...fittedPage, errors, consoleErrors })}`);
                        if (width >= 1024) assert.ok(fittedPage.height > fittedPage.viewport / 2, 'Fit page should not shrink all seven pages into one screen');
                    } else {
                        assert.equal(await iframe.evaluate((frame) => frame.contentDocument.querySelectorAll('.assessment-page img').length), 1);
                        assert.ok(await iframe.evaluate((frame) => frame.contentDocument.querySelector('img').naturalWidth > 1000));
                    }
                    await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                    assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                    await iframe.hover();
                    await page.keyboard.down('Control');
                    await page.mouse.wheel(0, -120);
                    await page.waitForFunction(() => window.assessmentState.previewZoom === 120);
                    await page.mouse.wheel(0, 120);
                    await page.waitForFunction(() => window.assessmentState.previewZoom === 110);
                    await page.keyboard.up('Control');
                    if (name === 'GAD Generic Checklist') {
                        await page.mouse.wheel(0, 300);
                        await page.waitForFunction(() => document.querySelector('iframe').contentWindow.scrollY > 0);
                        assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%', 'Normal scrolling must not change zoom');
                    }
                    const download = page.getByRole('link', { name: 'Download Word', exact: true });
                    assert.ok((await download.getAttribute('href')).endsWith(`${name === 'GAD Generic Checklist' ? 'gad-checklist' : 'initial-screening-form'}/download`));
                    await page.evaluate(() => {
                        window.printCalls = 0;
                        window.assessmentState.$refs.previewFrame.contentWindow.print = () => {
                            window.printCalls++;
                            window.printZoom = window.assessmentState.$refs.previewFrame.contentDocument.body.style.zoom;
                        };
                    });
                    await page.getByRole('button', { name: 'Print', exact: true }).click();
                    assert.equal(await page.evaluate(() => window.printCalls), 1);
                    assert.equal(await page.evaluate(() => window.printZoom), '1');
                    await download.focus();
                    await page.keyboard.press('Tab');
                    assert.equal(await iframe.evaluate((frame) => frame === document.activeElement), true);
                    await page.keyboard.press('Tab');
                    assert.equal(await page.getByRole('button', { name: 'Zoom out', exact: true }).evaluate((element) => element === document.activeElement), true);
                    await page.getByRole('button', { name: 'Close preview', exact: true }).focus();
                    await page.keyboard.press('Shift+Tab');
                    assert.equal(await page.getByRole('button', { name: 'Fit width', exact: true }).evaluate((element) => element === document.activeElement), true);
                    if (process.env.ASSESSMENT_SCREENSHOT_DIRECTORY && width !== 320) {
                        await page.screenshot({ path: resolve(process.env.ASSESSMENT_SCREENSHOT_DIRECTORY, `assessment-${name === 'GAD Generic Checklist' ? 'gad' : 'screening'}-${width}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    if (name === 'GAD Generic Checklist') {
                        await iframe.focus();
                        await page.keyboard.press('Escape');
                    } else {
                        await page.getByRole('button', { name: 'Close preview', exact: true }).click();
                    }
                    await page.waitForFunction(() => !window.assessmentState.previewPaneOpen
                        && document.activeElement === window.assessmentState.previewReturnFocus);
                    assert.equal(await page.evaluate(() => document.body.style.overflow), '');
                    assert.equal(await trigger.evaluate((element) => element === document.activeElement), true);
                    assert.equal(await page.evaluate(() => window.scrollY), position);
                }
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('all seven review previews stay in the review dialog and restore focus without submitting', async () => {
    const forms = renderForms();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            const errors = [];
            const requests = [];
            page.on('pageerror', error => errors.push(error.message));
            await setup(page, forms, false, false, true);
            for (const slug of ['detailed-proposal', 'work-plan', 'line-item-budget', 'expense-breakdown', 'curriculum-vitae']) {
                await page.route(`**/${slug}/preview`, route => {
                    requests.push({ slug, method: route.request().method(), data: route.request().postData() });
                    return route.fulfill({ contentType: 'text/html', body: '<html><body><h1>Saved proposal paper</h1></body></html>' });
                });
            }
            for (const label of ['Preview Detailed Proposal', 'Preview Work Plan', 'Preview Estimated Expense Breakdown', 'Preview Line-Item Budget', 'Preview team CVs', 'Preview GAD Generic Checklist', 'Preview Initial Screening Form']) {
                const trigger = page.getByRole('button', { name: label, exact: true });
                await trigger.click();
                await page.waitForFunction(() => window.assessmentState.previewReady && !window.assessmentState.previewLoading);
                assert.equal(await page.locator('#review-paper-preview-panel').isVisible(), true);
                assert.equal(page.context().pages().length, 1);
                assert.equal(page.url(), 'http://assessment-preview.test/workspace');
                await page.getByRole('button', { name: 'Close preview', exact: true }).focus();
                await page.keyboard.press('Shift+Tab');
                assert.equal(await page.getByRole('button', { name: 'Fit width', exact: true }).evaluate(el => el === document.activeElement), true);
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.assessmentState.previewPaneOpen);
                await page.locator('#review-paper-preview-panel').waitFor({ state: 'hidden' });
                assert.equal(await page.locator('#review-paper-preview-panel').isVisible(), false);
                assert.equal(await page.getByRole('button', { name: 'Turn in proposal', exact: true }).isVisible(), true);
                assert.equal(await trigger.evaluate(el => el === document.activeElement), true);
            }
            assert.equal(requests.length, 5);
            assert.ok(requests.every(request => request.method === 'POST' && request.data.includes('_token')));
            assert.ok(requests.find(request => request.slug === 'work-plan').data.includes('Survey communities'));
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('loading failures can be retried and closing or switching forms discards late responses', async () => {
    const forms = renderForms();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    try {
        await setup(page, forms);
        await page.route('**/gad-checklist/preview', (route) => route.fulfill({ status: 500, contentType: 'text/html', body: 'Error' }));
        await page.getByRole('button', { name: 'Preview GAD Generic Checklist', exact: true }).click();
        await page.getByRole('alert').waitFor();
        assert.equal(await page.getByRole('button', { name: 'Print', exact: true }).isDisabled(), true);
        await page.route('**/gad-checklist/preview', (route) => route.fulfill({ contentType: 'text/html', body: forms.gad }));
        await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
        await page.waitForFunction(() => window.assessmentState.previewReady && !window.assessmentState.previewLoading);
        assert.equal(await page.getByRole('alert').isVisible(), false);

        await page.evaluate(() => {
            window.fetch = () => new Promise((resolve) => { window.resolveOldPreview = () => resolve(new Response('<p>Old form</p>', { headers: { 'Content-Type': 'text/html' } })); });
            window.assessmentState.generatePreview();
        });
        await page.getByRole('button', { name: 'Close preview', exact: true }).click();
        await page.evaluate(async () => { window.resolveOldPreview(); await new Promise((resolve) => setTimeout(resolve, 20)); });
        assert.equal(await page.evaluate(() => window.assessmentState.previewHtml), '');
        assert.equal(await page.evaluate(() => window.assessmentState.previewPaneOpen), false);

        await page.getByRole('button', { name: 'Preview GAD Generic Checklist', exact: true }).click();
        await page.evaluate(() => {
            const oldResponse = window.resolveOldPreview;
            window.fetch = async () => new Response('<p>New screening form</p>', { headers: { 'Content-Type': 'text/html' } });
            window.assessmentState.openAssessmentPreview({ label: 'Initial Screening Form', previewUrl: '/new', downloadUrl: '/new/download' });
            oldResponse();
        });
        await page.waitForFunction(() => window.assessmentState.previewReady && !window.assessmentState.previewLoading);
        assert.equal(await page.evaluate(() => window.assessmentState.previewHtml), '<p>New screening form</p>');
        await page.getByRole('button', { name: 'Close preview', exact: true }).click();
        assert.equal(await page.evaluate(() => document.body.style.overflow), '');
    } finally {
        await browser.close();
    }
});
