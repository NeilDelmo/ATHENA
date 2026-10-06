import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { commentResponsePaperPreview } from '../../resources/js/comment-response-paper-preview.js';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';
import { initializeRevisionCommentResponsePapers } from '../../resources/js/revision-workspace.js';

test('a loaded paper is retained and concurrent requests do not prepare it twice', async (t) => {
    const original = globalThis.fetch;
    t.after(() => { globalThis.fetch = original; });
    let requests = 0;
    let finish;
    globalThis.fetch = async () => { requests++; await new Promise((resolve) => { finish = resolve; }); return { ok: true, text: async () => '<main>Current paper</main>' }; };
    const state = commentResponsePaperPreview('/preview');
    const pending = state.loadPaperPreview();
    await state.loadPaperPreview();
    assert.equal(requests, 1);
    finish();
    await pending;
    await state.loadPaperPreview();
    assert.equal(requests, 1);
    assert.equal(state.previewHtml, '<main>Current paper</main>');
});

test('failed previews can retry and removed previews ignore late responses', async (t) => {
    const original = globalThis.fetch;
    t.after(() => { globalThis.fetch = original; });
    globalThis.fetch = async () => ({ ok: false });
    const state = commentResponsePaperPreview('/preview');
    await state.loadPaperPreview();
    assert.match(state.previewError, /could not load/);
    assert.equal(state.previewHtml, '');
    let finish;
    globalThis.fetch = async () => { await new Promise((resolve) => { finish = resolve; }); return { ok: true, text: async () => '<main>Late paper</main>' }; };
    const pending = state.loadPaperPreview();
    state.destroy();
    finish();
    await pending;
    assert.equal(state.previewHtml, '');
});

test('faculty can preview all current replies from the revision panel without leaving the editor', async () => {
    const rendered = JSON.parse(execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $topic = (new App\Models\TopicProposal)->forceFill(['id' => 7]);
        $rows = [
            ['key' => 'annotation_11', 'form_source' => 'research_head', 'comment' => 'Clarify the objectives.', 'response' => 'Previously saved answer.', 'remarks' => 'Page 1, paragraph 1'],
            ['key' => 'annotation_12', 'form_source' => 'research_head', 'comment' => 'Explain the work plan.', 'response' => '', 'remarks' => ''],
            ['key' => 'co_evaluator_narrative_19', 'form_source' => 'co_evaluator', 'comment' => 'Clarify the sampling.', 'response' => '', 'remarks' => ''],
        ];
        $template = str_replace("@vite('resources/css/comment-response-form-print.css')", '<style>'.file_get_contents(resource_path('css/comment-response-form-print.css')).'</style>', file_get_contents(resource_path('views/faculty/comment-response-form/preview.blade.php')));
        $output = ['papers' => [], 'previews' => [], 'replies' => ''];
        foreach (['research_head', 'co_evaluator'] as $source) {
            $form = [
                'form_label' => $source.' Comment Response', 'form_source' => $source, 'review_id' => 76,
                'project_title' => 'Community Research Project', 'project_leader' => 'Neil Project Leader', 'staff' => [],
                'evaluation_stages' => [$source], 'comment_response_head' => 'Research Head', 'comment_response_vice_chancellor' => 'Vice Chancellor',
                'feedback' => array_values(array_filter($rows, fn ($row) => $row['form_source'] === $source)),
            ];
            $output['papers'][$source] = Illuminate\Support\Facades\Blade::render($template, ['topic' => $topic, 'commentResponseForm' => $form, 'embedded' => true]);
            $output['previews'][$source] = Illuminate\Support\Facades\Blade::render('<x-comment-response-paper-preview :url="$url" />', ['url' => '/reply-paper?source='.$source]);
        }
        foreach ($rows as $row) {
            $output['replies'] .= Illuminate\Support\Facades\Blade::render('<x-proposal-revision-response :item="$item" />', ['item' => $row]);
        }
        echo json_encode($output, JSON_THROW_ON_ERROR);
    `], { encoding: 'utf8' }));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const [width, dark] of [[1440, false], [390, true]]) {
            const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
            const errors = [];
            let requests = 0;
            page.on('pageerror', (error) => errors.push(error.message));
            await page.route('**/reply-paper?source=*', (route) => {
                requests++;
                return route.fulfill({ contentType: 'text/html; charset=utf-8', body: rendered.papers[new URL(route.request().url()).searchParams.get('source')] });
            });
            await page.route('**/images/batstateu-logo.png', (route) => route.fulfill({ contentType: 'image/png', body: readFileSync(resolve('public/images/batstateu-logo.png')) }));
            const papers = Object.entries(rendered.previews).map(([source, component]) => `<section data-comment-response-source="${source}"><dialog open role="region" data-preview-source="${source}" data-comment-response-paper class="revision-comment-response-paper"><header class="flex items-center justify-between border-b p-4"><h2>${source} Comment Response</h2><button type="button" data-comment-response-paper-close hidden>Minimize preview</button></header><div data-comment-response-paper-open role="button" tabindex="0" class="revision-comment-response-paper-content">${component}</div></dialog></section>`).join('');
            await page.route('**/reply-fixture', (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><style>${styles.css}</style><body><form data-revision-workspace="7"><section data-preview-step hidden>${papers}</section><dialog data-editor-dialog style="width:min(500px,95vw);max-height:90vh;overflow:auto;padding:16px">${rendered.replies}</dialog></form></body></html>` }));
            await page.goto('http://localhost/reply-fixture');
            await page.addScriptTag({ content: `${proposalPreviewWorkspace.toString()}\n${commentResponsePaperPreview.toString()}\n${initializeRevisionCommentResponsePapers.toString()}\ndocument.addEventListener('alpine:init',()=>Alpine.data('commentResponsePaperPreview',commentResponsePaperPreview));` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
            await page.evaluate(() => {
                initializeRevisionCommentResponsePapers(document.querySelector('form'));
                document.querySelector('[data-editor-dialog]').showModal();
                document.body.style.overflow = 'hidden';
                document.querySelector('form').addEventListener('submit', (event) => { event.preventDefault(); window.submitted = true; });
            });
            assert.equal(requests, 0, 'Hidden preview cards load only when opened');
            const first = page.locator('[data-revision-response-key="annotation_11"]');
            const second = page.locator('[data-revision-response-key="annotation_12"]');
            const evaluator = page.locator('[data-revision-response-key="co_evaluator_narrative_19"]');
            const answer = 'Added <script>literal text</script> to the objectives.\nExplained the changes.';
            await first.locator('textarea').fill(answer);
            await second.locator('textarea').fill('Updated the schedule in the work plan.');
            await evaluator.locator('textarea').fill('Explained the sampling calculation.');
            await first.evaluate((reply) => {
                reply.querySelector('[data-comment-response-page]').value = '3';
                reply.querySelector('[data-comment-response-paragraph]').value = '2';
            });
            const open = first.getByRole('button', { name: 'Preview Comment Response', exact: true });
            await open.click();
            const headPaper = page.locator('[data-preview-source="research_head"]');
            await page.waitForFunction(() => document.querySelector('[data-preview-source="research_head"] iframe').contentDocument?.querySelector('[data-comment-response-answer]')?.textContent.includes('literal text'));
            const frame = headPaper.locator('iframe');
            const values = await frame.evaluate((element) => [...element.contentDocument.querySelectorAll('[data-comment-response-row]')].map((row) => ({ key: row.dataset.commentResponseRow, cells: [...row.cells].map((cell) => cell.textContent) })));
            assert.deepEqual(values, [
                { key: 'annotation_11', cells: ['1.', 'Clarify the objectives.', answer, 'Page 3, paragraph 2'] },
                { key: 'annotation_12', cells: ['2.', 'Explain the work plan.', 'Updated the schedule in the work plan.', ''] },
            ]);
            assert.equal(await frame.evaluate((element) => element.contentDocument.querySelectorAll('script').length), 0, 'Answers are rendered as text');
            assert.equal(await headPaper.evaluate((paper) => paper.matches(':modal')), true);
            assert.ok((await headPaper.boundingBox()).width <= width, 'The preview fits the viewport from a hidden step');
            await headPaper.getByRole('button', { name: 'Minimize preview', exact: true }).click();
            assert.equal(await page.locator('[data-editor-dialog]').evaluate((dialog) => dialog.matches(':modal')), true);
            assert.equal(await open.evaluate((button) => button === document.activeElement), true);
            assert.equal(await first.locator('textarea').inputValue(), answer);
            await first.locator('textarea').fill('');
            await first.evaluate((reply) => { reply.querySelector('[data-comment-response-no-change]').checked = true; });
            await open.click();
            await page.waitForFunction(() => document.querySelector('[data-preview-source="research_head"] iframe').contentDocument?.querySelector('[data-comment-response-remarks]')?.textContent === 'No change made');
            assert.equal(await frame.evaluate((element) => element.contentDocument.querySelector('[data-comment-response-answer]').textContent), '');
            assert.equal(await frame.evaluate((element) => element.contentDocument.querySelector('[data-comment-response-remarks]').textContent), 'No change made');
            await headPaper.getByRole('button', { name: 'Minimize preview', exact: true }).click();
            await evaluator.getByRole('button', { name: 'Preview Comment Response', exact: true }).click();
            const coPaper = page.locator('[data-preview-source="co_evaluator"]');
            await page.waitForFunction(() => document.querySelector('[data-preview-source="co_evaluator"] iframe').contentDocument?.body?.textContent.includes('Explained the sampling calculation.'));
            assert.equal(await coPaper.locator('iframe').evaluate((element) => element.contentDocument.querySelectorAll('[data-comment-response-row]').length), 1);
            assert.equal(await coPaper.locator('iframe').evaluate((element) => element.contentDocument.body.textContent.includes('Clarify the objectives.')), false);
            if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) await page.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `current-comment-response-${width}-${dark ? 'dark' : 'light'}.png`) });
            await page.keyboard.press('Escape');
            assert.equal(await evaluator.getByRole('button', { name: 'Preview Comment Response', exact: true }).evaluate((button) => button === document.activeElement), true);
            assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
            assert.equal(await page.evaluate(() => Boolean(window.submitted)), false);
            assert.equal(requests, 2, 'Each reviewer paper is loaded once and reused with current replies');
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('the real Comment Response paper loads only when visible, fits without PDF requests and survives enlargement in both themes', async () => {
    const rendered = JSON.parse(execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $topic = (new App\Models\TopicProposal)->forceFill(['id' => 7]);
        $form = [
            'form_label' => 'Research Head Comment-Response Form', 'form_source' => 'research_head', 'review_id' => 76,
            'project_title' => 'Community Research Project', 'project_leader' => 'Neil Project Leader', 'staff' => [],
            'evaluation_stages' => ['research_head'], 'comment_response_head' => 'Research Head', 'comment_response_vice_chancellor' => 'Vice Chancellor',
            'feedback' => [['comment' => 'Explain the sustainable development goal.', 'response' => 'Added the connection to the project objectives.', 'remarks' => 'Page 1, paragraph 2']],
        ];
        $source = str_replace("@vite('resources/css/comment-response-form-print.css')", '<style>'.file_get_contents(resource_path('css/comment-response-form-print.css')).'</style>', file_get_contents(resource_path('views/faculty/comment-response-form/preview.blade.php')));
        echo json_encode([
            'component' => Illuminate\Support\Facades\Blade::render('<x-comment-response-paper-preview :url="$url" />', ['url' => '/paper-preview?embedded=1']),
            'paper' => Illuminate\Support\Facades\Blade::render($source, ['topic' => $topic, 'commentResponseForm' => $form, 'embedded' => true]),
        ], JSON_THROW_ON_ERROR);
    `], { encoding: 'utf8' }));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(resolve('resources/css/app.css'), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                const errors = [];
                const requested = [];
                page.on('pageerror', (error) => errors.push(error.message));
                page.on('request', (request) => requested.push(request.url()));
                let requests = 0;
                await page.route('**/paper-preview?embedded=1', (route) => route.fulfill({ status: ++requests === 1 ? 500 : 200, contentType: 'text/html; charset=utf-8', body: requests === 1 ? 'Server failure' : rendered.paper }));
                await page.route('**/images/batstateu-logo.png', (route) => route.fulfill({ contentType: 'image/png', body: readFileSync(resolve('public/images/batstateu-logo.png')) }));
                await page.route('**/paper-fixture', (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><style>${styles.css}</style><body><div style="height:1100px"></div><form style="padding:16px"><dialog open role="region" data-comment-response-paper class="revision-comment-response-paper"><header class="flex items-center justify-between border-b p-4"><h2>Research Head Comment Response paper</h2><button type="button" data-comment-response-paper-close hidden>Minimize preview</button></header><div data-comment-response-paper-open role="button" tabindex="0" aria-haspopup="dialog" aria-expanded="false" class="revision-comment-response-paper-content">${rendered.component}</div></dialog></form></body></html>` }));
                await page.goto('http://localhost/paper-fixture');
                await page.addScriptTag({ content: `${proposalPreviewWorkspace.toString()}
                    ${commentResponsePaperPreview.toString()}
                    ${initializeRevisionCommentResponsePapers.toString()}
                    document.addEventListener('alpine:init',()=>Alpine.data('commentResponsePaperPreview',(url)=>{const state=commentResponsePaperPreview(url);const init=state.init;state.init=function(){window.previewState=this;init.call(this)};return state}));` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await page.waitForFunction(() => Boolean(window.previewState));
                await page.evaluate(() => initializeRevisionCommentResponsePapers(document.querySelector('form')));
                assert.equal(requests, 0, 'An off-screen paper must not load');
                const viewer = page.locator('[data-comment-response-preview]');
                await viewer.scrollIntoViewIfNeeded();
                await viewer.getByRole('alert').waitFor();
                assert.equal(requests, 1);
                await viewer.getByRole('button', { name: 'Try again', exact: true }).click();
                await page.waitForFunction(() => window.previewState.previewReady && document.querySelector('iframe').contentDocument?.body.style.zoom !== '');
                assert.equal(await page.locator('dialog').evaluate((element) => element.matches(':modal')), false);
                assert.equal(requests, 2);
                const frame = viewer.locator('iframe');
                const dimensions = await frame.evaluate((element) => {
                    const paper = element.contentDocument.querySelector('.comment-response-sheet');
                    return { width: paper.offsetWidth, displayed: paper.getBoundingClientRect().width, frame: element.clientWidth, headingSize: getComputedStyle(paper.querySelector('h1')).fontSize, toolbar: getComputedStyle(element.contentDocument.querySelector('.preview-toolbar')).display };
                });
                assert.equal(dimensions.width, 816, 'The official page keeps its paper width');
                assert.equal(dimensions.headingSize, '14px', 'Small preview frames scale the page without shrinking its typography twice');
                assert.ok(dimensions.displayed <= dimensions.frame + 1);
                assert.equal(dimensions.toolbar, 'none');
                const compactSize = await page.locator('dialog').boundingBox();
                assert.ok(compactSize.width <= 384 && compactSize.height < 550, 'The paper starts as a compact card');
                assert.equal(await viewer.getByRole('button', { name: 'Zoom in', exact: true }).isVisible(), false);
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) await page.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `compact-comment-response-${width}-${dark ? 'dark' : 'light'}.png`) });
                assert.ok(await frame.evaluate((element) => element.contentDocument.body.textContent.includes('Added the connection to the project objectives.')));
                await frame.evaluate((element) => { window.originalPreviewFrame = element; });
                await viewer.click();
                await page.waitForFunction(() => document.querySelector('dialog').matches(':modal'));
                assert.equal(await frame.getAttribute('tabindex'), '0');
                await viewer.getByRole('button', { name: 'Zoom in', exact: true }).waitFor();
                await frame.hover();
                await page.keyboard.down('Control');
                await page.mouse.wheel(0, -120);
                await page.waitForFunction(() => window.previewState.previewZoom === 110);
                await page.mouse.wheel(0, 120);
                await page.waitForFunction(() => window.previewState.previewZoom === 100);
                await page.keyboard.up('Control');
                await page.waitForFunction(() => {
                    const frame = document.querySelector('iframe');
                    return frame.contentDocument.querySelector('.comment-response-sheet').getBoundingClientRect().width > 280;
                });
                await viewer.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await viewer.locator('output').textContent(), '110%');
                await viewer.getByRole('button', { name: 'Fit width', exact: true }).click();
                assert.equal(await viewer.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                await viewer.getByRole('button', { name: 'Fit page', exact: true }).click();
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) await page.screenshot({ path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `fast-comment-response-${width}-${dark ? 'dark' : 'light'}.png`) });
                await frame.focus();
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !document.querySelector('dialog').matches(':modal'));
                assert.equal(await frame.evaluate((element) => element === window.originalPreviewFrame), true);
                assert.equal(await frame.getAttribute('tabindex'), '-1');
                await viewer.getByRole('button', { name: 'Zoom in', exact: true }).waitFor({ state: 'hidden' });
                assert.equal(await viewer.getByRole('button', { name: 'Zoom in', exact: true }).isVisible(), false);
                await page.locator('[data-comment-response-paper-open]').press('Enter');
                await page.getByRole('button', { name: 'Minimize preview', exact: true }).click();
                assert.equal(await page.locator('dialog').evaluate((element) => element.matches(':modal')), false);
                assert.equal(requests, 2, 'Enlarging and returning must reuse the loaded paper');
                assert.equal(requested.some((url) => /\/pdf(?:\?|$)|pdf\.worker/.test(url)), false);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
