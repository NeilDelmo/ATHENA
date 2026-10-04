import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { chromium } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('fileDropzone',");
const source = app.slice(start, app.indexOf('Alpine.data(', start + 1));

test('upload initialization waits until Alpine registers the file input inside the revision dialog', () => {
    let factory;
    runInNewContext(source, { Alpine: { data: (_, callback) => { factory = callback; } } });
    const state = factory({ accept: '.pdf', maxBytes: 26214400 });
    const ticks = [];
    state.$refs = {};
    state.$nextTick = callback => ticks.push(callback);
    assert.doesNotThrow(() => state.init());
    const replacement = { name: 'revised.pdf', size: 1024 };
    state.$refs.input = { files: [replacement] };
    ticks.forEach(callback => callback());
    assert.equal(state.files.length, 1);
    assert.equal(state.files[0], replacement);
});

test('embedded revision editors initialize without upload errors and replacement uploads still work', async () => {
    const root = fileURLToPath(new URL('../../', import.meta.url));
    const html = execFileSync('php', ['-r', String.raw`
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\View::share("errors", new Illuminate\Support\ViewErrorBag);
        $topic = (new App\Models\TopicProposal)->forceFill(["id" => 3, "title" => "Revision upload test"]);
        $topic->setRelation("versions", collect());
        echo Illuminate\Support\Facades\Blade::render(
            '<x-proposal-revision-document :topic="$topic" document-type="work_plan" :required="true" />
             <x-proposal-revision-document :topic="$topic" document-type="gad_checklist" />',
            ["topic" => $topic]
        );
    `], { cwd: root, encoding: 'utf8' });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<style>[x-cloak]{display:none!important}</style>${html}`);
        await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => { ${source} });` });
        await page.addScriptTag({ path: fileURLToPath(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url)) });
        await page.locator('input[name="gad_checklist"]').setInputFiles({
            name: 'revised-checklist.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 revised'),
        });
        await page.locator('[data-revision-document="gad_checklist"] li').waitFor({ state: 'visible' });
        assert.match(await page.locator('[data-revision-document="gad_checklist"] li').textContent(), /revised-checklist\.pdf/);
        assert.equal(await page.locator('[data-revision-document="work_plan"] input[type="file"]').count(), 0);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
