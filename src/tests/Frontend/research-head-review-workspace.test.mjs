import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';

function reviewWorkspace(completedEvaluation = true) {
    const result = spawnSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
        $topic = new App\Models\TopicProposal(['status' => App\Models\TopicProposal::STATUS_GAD_REVIEW]);
        $topic->id = 7;
        $version = new App\Models\ProposalVersion(['version_number' => 1, 'title' => 'Review workflow', 'created_at' => now()]);
        $version->id = 11;
        $version->created_at = now();
        $gad = new App\Models\ProposalVersionFile(['document_type' => App\Models\ProposalVersionFile::TYPE_GAD_CHECKLIST]);
        $gad->id = 21;
        $screening = new App\Models\ProposalVersionFile(['document_type' => App\Models\ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM]);
        $screening->id = 22;
        $assessment = new App\Models\ProposalVersionFile(['source_data' => ['gad_score' => 9.98, 'gad_outcome' => 'passed', 'gad_signature_confirmed' => true]]);
        $evaluation = new App\Models\ProposalVersionFile(['original_filename' => 'completed-screening.pdf', 'source_data' => ['recommended_action' => 'minor_revision', 'narrative_evaluation' => "Clarify recruitment.\nExplain the sample size."]]);
        $assessment->forceFill(['id' => 31, 'document_type' => App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD, 'source_version_file_id' => $gad->id, 'original_filename' => 'completed-gad.pdf', 'source_data' => [...$assessment->source_data, 'purpose' => App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT, 'target_document_type' => App\Models\ProposalVersionFile::TYPE_GAD_CHECKLIST]]);
        $evaluation->forceFill(['id' => 32, 'document_type' => App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD, 'source_version_file_id' => $screening->id, 'source_data' => [...$evaluation->source_data, 'purpose' => App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION]]);
        $workspace = array_fill_keys(['headUploadedFiles', 'headUploadsBySource', 'availableFileIds', 'viewableFileIds', 'requiredSignatureFiles', 'signedSourceFileIds', 'missingSignatureFiles'], collect());
        $completedEvaluation = ($argv[1] ?? 'complete') === 'complete';
        $workspace = [...$workspace, 'latestVersion' => $version, 'facultySubmittedFiles' => collect([$gad, $screening]), 'signaturesComplete' => false, 'gadAssessment' => $assessment, 'gadPassed' => true, 'coEvaluatorEvaluation' => $completedEvaluation ? $evaluation : null];
        echo Illuminate\Support\Facades\Blade::render('<x-research-head-file-workspace :topic="$topic" :workspace="$workspace" />', ['topic' => $topic, 'workspace' => $workspace, 'errors' => new Illuminate\Support\ViewErrorBag]);
        $viewer = new App\Models\User;
        $viewer->setRelation('roles', new Illuminate\Database\Eloquent\Collection);
        Illuminate\Support\Facades\Auth::setUser($viewer);
        $version->setRelation('files', collect($completedEvaluation ? [$gad, $screening, $assessment, $evaluation] : [$gad, $screening, $assessment]));
        $version->setRelation('submitter', null);
        $topic->setRelation('versions', collect([$version]));
        $clearance = new App\Models\TopicReview(['decision' => 'gad_review', 'review_stage' => 'initial', 'comment' => 'Methodology cleared for GAD assessment.']);
        $clearance->created_at = now();
        echo Illuminate\Support\Facades\Blade::render('<x-proposal-workflow :topic="$topic" :version="$version" :reviews="$reviews" />', ['topic' => $topic, 'version' => $version, 'reviews' => collect([$clearance])]);
        echo view('topics.partials.version-history', ['topic' => $topic, 'expanded' => true])->render();
    `, completedEvaluation ? 'complete' : 'pending'], { encoding: 'utf8', cwd: resolve('.') });
    assert.equal(result.status, 0, (result.stderr || result.stdout).slice(-4000));
    return result.stdout;
}

test('collapsed assessments still allow comment previews on mobile and desktop in both themes', async () => {
    const html = reviewWorkspace();
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const styles = [manifest['resources/css/app.css'].file, ...manifest['resources/js/app.js'].css].map((file) => readFileSync(resolve('public/build', file), 'utf8')).join('\n');
    const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
    const dropzoneStart = app.indexOf("Alpine.data('fileDropzone',");
    const dropzone = app.slice(dropzoneStart, app.indexOf('Alpine.data(', dropzoneStart + 1));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles}</style><body x-data class="bg-white p-4 dark:bg-gray-950">${html}</body></html>`);
                await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => { ${dropzone} Alpine.data('pdfAnnotationWorkspace', () => ({ loading: true, loadError: '', viewerRefreshRequired: false })); });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
                await page.waitForFunction(() => document.querySelector('#co-evaluator-review-content').style.display === 'none');
                const workflow = page.locator('[data-proposal-routing-docket]');
                assert.equal(await workflow.isVisible(), true);
                assert.equal(await workflow.locator('[data-route-step]').count(), 5);
                const stagePositions = await workflow.locator('[data-route-step]').evaluateAll((stages) => stages.map((stage) => ({ x: stage.getBoundingClientRect().x, y: stage.getBoundingClientRect().y })));
                assert.equal(width < 1024 ? stagePositions[0].y < stagePositions[1].y : stagePositions[0].y === stagePositions[4].y, true);
                assert.equal(await workflow.locator('[data-route-step][aria-current="step"]').innerText().then((text) => text.includes('Co-evaluator review')), true);
                assert.equal(await workflow.locator('[data-workflow-stage-button="4"]').isDisabled(), true);
                await workflow.locator('[data-workflow-stage-button="2"]').click();
                const gadOutcome = workflow.locator('[data-workflow-stage-outcome="2"]');
                await gadOutcome.waitFor({ state: 'visible' });
                assert.match(await gadOutcome.innerText(), /Passed · 9.98\/20/);
                assert.equal(await gadOutcome.getByRole('link', { name: 'Download', exact: true }).isVisible(), true);
                if (process.env.WORKFLOW_SCREENSHOT_DIRECTORY && !dark) {
                    await workflow.screenshot({ path: resolve(process.env.WORKFLOW_SCREENSHOT_DIRECTORY, `proposal-workflow-${width}.png`) });
                }
                await workflow.locator('[data-workflow-stage-button="1"]').focus();
                await page.keyboard.press('Enter');
                const headOutcome = workflow.locator('[data-workflow-stage-outcome="1"]');
                await headOutcome.waitFor({ state: 'visible' });
                assert.match(await headOutcome.innerText(), /Methodology cleared for GAD assessment/);
                assert.equal(await gadOutcome.isVisible(), false);
                await workflow.locator('[data-workflow-stage-button="3"]').click();
                const coEvaluatorOutcome = workflow.locator('[data-workflow-stage-outcome="3"]');
                await coEvaluatorOutcome.waitFor({ state: 'visible' });
                assert.match(await coEvaluatorOutcome.innerText(), /Clarify recruitment/);
                assert.equal(await page.locator('#co-evaluator-review-content').isVisible(), false);
                assert.equal(await page.locator('#gad-office-review').count(), 0);
                assert.equal(await page.getByRole('link', { name: 'View completed stages' }).count(), 0);
                assert.equal(await page.locator('#co-evaluator-review-content').isVisible(), false);
                const preview = page.locator('[data-co-evaluator-comment-response-preview-button]');
                assert.equal(await preview.isVisible(), true);
                await preview.click();
                const modal = page.locator('[data-co-evaluator-comment-response-preview-modal]');
                await modal.waitFor({ state: 'visible' });
                assert.equal(await modal.getByText('Co-evaluator Comment Response Form', { exact: true }).isVisible(), true);
                assert.equal(await page.locator('#co-evaluator-review-content').isVisible(), false);
                await page.waitForFunction(() => document.activeElement?.textContent.trim() === 'Close preview');
                await modal.getByRole('button', { name: 'Close preview' }).click();
                await modal.waitFor({ state: 'hidden' });
                await page.locator('#co-evaluator-review > button').click();
                await page.locator('[data-co-evaluator-comments]').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-co-evaluator-comments]').isVisible(), true);
                assert.match(await page.locator('[data-co-evaluator-comments]').innerText(), /Clarify recruitment\.\s+Explain the sample size\./);
                await page.locator('[data-submitted-version] > details > summary').click();
                await page.locator('[data-version-assessment-records]').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-version-assessment-records] [data-assessment-record]').count(), 2);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, `overflow at ${width}, dark=${dark}`);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('a passing GAD assessment opens the pending co evaluator upload as the only active stage', async () => {
    const html = reviewWorkspace(false);
    const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
    const start = app.indexOf("Alpine.data('fileDropzone',");
    const dropzone = app.slice(start, app.indexOf('Alpine.data(', start + 1));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setContent(`<style>[x-cloak]{display:none!important}</style>${html}`);
        await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => { ${dropzone} });` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        await page.locator('[data-co-evaluator-screening-panel]').waitFor({ state: 'visible' });
        assert.equal(await page.locator('#gad-office-review').count(), 0);
        assert.equal(await page.locator('#co-evaluator-review > button').getAttribute('aria-expanded'), 'true');
        assert.equal(await page.locator('[data-co-evaluator-comment-response-preview-button]').count(), 0);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
