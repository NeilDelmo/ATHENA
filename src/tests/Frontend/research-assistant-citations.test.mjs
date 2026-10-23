import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { formatAssistantMarkdown, safeAssistantSourceUrl } from '../../resources/js/research-assistant-markdown.js';

const read = path => readFileSync(resolve(path), 'utf8');
const helper = read('resources/js/research-assistant-citations.js').replace(/^import .*;\r?\n/gm, '').replaceAll('export ', '');
const version = 'a'.repeat(64);
const sources = [
    { reference: 'ATHENA 1', title: 'Approved Research Manual (2023) — Ethics clearance', type: 'Official Research Manual', edition: '2023', section: '8.1 Ethics clearance', page_start: 62, page_end: 63, excerpt: 'Obtain ethics clearance before recruiting participants.', url: `https://athena.test/athena-app/research-manuals/${version}#page=62` },
    { reference: 'Detailed Proposal', title: 'Submitted detailed proposal (version 2)', section: 'Research design', excerpt: 'The study uses an interview design.', url: 'https://athena.test/athena-app/topics/3/versions/4/files/5/view' },
    { reference: 'ATHENA 2', title: 'Older field guide', url: null },
    { reference: 'ATHENA 3', title: '<img src=x onerror="window.executed=true">', excerpt: '<script>unsafe excerpt</script>', url: 'javascript:alert(1)' },
];

test('citation details and existing viewer configuration are safe and preserve source pages', async () => {
    const { runInNewContext } = await import('node:vm');
    const context = { URL, URLSearchParams, safeAssistantSourceUrl, window: { location: { origin: 'https://athena.test' } } };
    const preview = runInNewContext(helper + '; assistantSourcePreview;', context);
    assert.equal(preview(sources[0]).pdfUrl, `https://athena.test/athena-app/research-manuals/${version}/file`);
    assert.equal(preview(sources[0]).page, 62);
    assert.equal(preview(sources[0]).downloadUrl, `https://athena.test/athena-app/research-manuals/${version}/file?download=1`);
    assert.equal(preview(sources[1]).pdfUrl, sources[1].url);
    for (const url of ['javascript:alert(1)', 'https://external.test/file.pdf', 'https://user:secret@athena.test/research-manuals/'+version, 'https://athena.test/projects/3']) assert.equal(preview({ url }), null);
    assert.equal(preview({ url: `https://athena.test/research-manuals/${version}#page=4` }).page, 4);
});

test('same-title documents open the excerpt belonging to their complete named reference', async () => {
    const title = 'Participant recruitment guidance';
    const sources = [
        { title, reference: `${title} · https://first.example.edu/policy`, excerpt: 'First institution guidance.', url: 'https://first.example.edu/policy' },
        { title, reference: `${title} · https://second.example.edu/policy`, excerpt: 'Second institution guidance.', url: 'https://second.example.edu/policy' },
    ];
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<style>[x-cloak]{display:none!important}</style><article x-data="researchAssistantCitations" @click="openCitation($event, window.message)">${formatAssistantMarkdown(`Compare [${sources[0].reference}] with [${sources[1].reference}].`, sources)}${read('resources/views/components/research-assistant-citation.blade.php')}</article>`);
        await page.addScriptTag({ content: `${safeAssistantSourceUrl.toString()}\n${helper}\nwindow.message=${JSON.stringify({ sources })};document.addEventListener('alpine:init',()=>{Alpine.data('researchAssistantCitations',researchAssistantCitations);Alpine.store('researchAssistant',{sourceUrl:source=>safeAssistantSourceUrl(source?.url)});});` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
        const panel = page.locator('[data-assistant-citation-panel]');
        await page.locator('[data-assistant-citation]').nth(1).click();
        await expect(panel.locator('blockquote')).toHaveText('Second institution guidance.');
        await expect(panel.getByRole('link', { name: 'Open source' })).toHaveAttribute('href', 'https://second.example.edu/policy');
        await panel.press('Escape');
        await page.locator('[data-assistant-citation]').first().click();
        await expect(panel.locator('blockquote')).toHaveText('First institution guidance.');
    } finally {
        await browser.close();
    }
});

test('inline citations reveal exact excerpts and return keyboard focus without clutter or unsafe markup', { timeout: 90000 }, async () => {
    const { css } = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))]).process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [320, 390, 1000]) for (const dark of [false, true]) {
            const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('https://athena.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html></html>' }));
            await page.goto('https://athena.test');
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${css}\n[x-cloak]{display:none!important}</style><body class="bg-white dark:bg-stone-950"><article class="mx-auto max-w-3xl p-4" x-data="researchAssistantCitations" @click="openCitation($event, window.message)"><div>${formatAssistantMarkdown('Obtain clearance [ATHENA 1]. Compare the methods [Detailed Proposal]. Older reference [ATHENA 2]. Unsafe source [ATHENA 3]. Unknown [ATHENA 99].', sources)}</div>${read('resources/views/components/research-assistant-citation.blade.php')}</article></body></html>`);
            await page.addScriptTag({ content: `${safeAssistantSourceUrl.toString()}\n${helper}\nwindow.message=${JSON.stringify({ sources })};document.addEventListener('alpine:init',()=>{Alpine.data('researchAssistantCitations',researchAssistantCitations);Alpine.store('researchAssistant',{...researchAssistantSourcePreview(),sourceUrl:source=>safeAssistantSourceUrl(source?.url)});});` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            const citations = page.locator('[data-assistant-citation]');
            await expect(citations).toHaveCount(4);
            const panel = page.locator('[data-assistant-citation-panel]');
            await expect(panel).toBeHidden();
            await expect(citations.first()).toHaveText(sources[0].title);
            const box = await citations.first().boundingBox();
            assert.ok(box.height >= 44 && box.width >= 44);
            await citations.first().focus();
            await page.keyboard.press('Enter');
            await expect(panel).toBeVisible();
            await expect(panel).toBeFocused().catch(async error => assert.fail(`${error.message}; ${await page.evaluate(() => document.activeElement?.outerHTML)}; ${errors.join(';')}`));
            await expect(panel.locator('blockquote')).toHaveText(sources[0].excerpt);
            await expect(panel).not.toContainText('ATHENA 1', { useInnerText: true });
            await expect(panel).toContainText('Edition 2023 · 8.1 Ethics clearance · Pages 62–63');
            await expect(panel.getByRole('button', { name: 'Preview source', exact: true })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(panel).toBeHidden();
            await expect(citations.first()).toBeFocused();
            await expect(citations.first()).toHaveAttribute('aria-expanded', 'false');
            await citations.nth(2).click();
            await expect(panel).toContainText('No excerpt was saved with this reference.');
            await expect(panel.getByRole('button', { name: 'Preview source', exact: true })).toBeHidden();
            await citations.nth(3).click();
            await expect(panel.locator('blockquote')).toHaveText(sources[3].excerpt);
            await expect(panel.getByRole('link')).toHaveCount(0);
            await expect(panel.locator('script, img')).toHaveCount(0);
            assert.equal(await page.evaluate(() => window.executed), undefined);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally { await browser.close(); }
});

function sourcePdf() {
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
        null,
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>',
        null,
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    for (const [index, text] of [[3, 'Research Manual cover'], [5, 'Ethics clearance before recruitment']]) {
        const stream = `BT /F1 18 Tf 50 700 Td (${text}) Tj ET`;
        objects[index] = `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`;
    }
    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objects.forEach((object, index) => { offsets.push(pdf.length); pdf += `${index+1} 0 obj\n${object}\nendobj\n`; });
    const offset = pdf.length;
    pdf += `xref\n0 ${objects.length+1}\n0000000000 65535 f \n`;
    for (const position of offsets.slice(1)) pdf += `${String(position).padStart(10, '0')} 00000 n \n`;
    return Buffer.from(pdf + `trailer\n<< /Size ${objects.length+1} /Root 1 0 R >>\nstartxref\n${offset}\n%%EOF`, 'ascii');
}

test('citation preview reuses the built PDF viewer in place with page search zoom and focus return', { timeout: 90000 }, async () => {
    const manifest = JSON.parse(read('public/build/manifest.json'));
    const preview = read('storage/framework/testing/assistant-source-preview.html');
    const appFile = manifest['resources/js/app.js'].file;
    const appCss = manifest['resources/css/app.css'].file;
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1200]) for (const dark of [false, true]) {
            const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
            const errors = [];
            const pdfRequests = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('https://athena.test/**', async route => {
                const url = new URL(route.request().url());
                if (url.pathname.includes('/assets/')) {
                    return route.fulfill({ body: readFileSync(resolve('public/build', url.pathname.slice(url.pathname.indexOf('/assets/') + 1))), contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript' });
                }
                if (url.pathname.endsWith('/file')) { pdfRequests.push(url.href); return route.fulfill({ body: sourcePdf(), contentType: 'application/pdf' }); }
                if (url.pathname === '/history') return route.fulfill({ json: { conversations: [] } });
                return route.fulfill({ contentType: 'text/html', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="csrf-token" content="test"><meta name="app-url" content="https://athena.test"><link rel="stylesheet" href="/${appCss}">${(manifest['resources/js/app.js'].css || []).map(file => `<link rel="stylesheet" href="/${file}">`).join('')}<script>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};window.message=${JSON.stringify({ sources: [{ ...sources[0], page_start: 2, page_end: 2 }] })};</script><script type="module" src="/${appFile}"></script></head><body x-data data-app-shell data-auth-user-id="1" data-research-assistant-history-url="/history"><article class="mx-auto max-w-3xl p-4" x-data="researchAssistantCitations" x-trap.inert.noreturn="!$store.researchAssistant.sourcePreviewOpen" @click="openCitation($event, window.message)"><div>${formatAssistantMarkdown('Check participant protections [ATHENA 1].', sources)}</div>${read('resources/views/components/research-assistant-citation.blade.php')}</article>${preview}</body></html>` });
            });
            await page.goto('https://athena.test/chat');
            await page.locator('[data-assistant-citation]').click();
            const details = page.locator('[data-assistant-citation-panel]');
            await expect(details).toBeVisible();
            const open = details.getByRole('button', { name: 'Preview source', exact: true });
            await open.click();
            const modal = page.locator('[data-assistant-source-preview]');
            await expect(modal).toBeVisible();
            await expect(modal.locator('canvas')).toHaveCount(2, { timeout: 20000 }).catch(async error => assert.fail(`${error.message}; browser ${errors.join(';')}; modal ${await modal.innerText()}; requests ${pdfRequests.join(',')}`));
            await expect(modal.getByRole('spinbutton')).toHaveValue('2');
            assert.equal(pdfRequests.at(-1), `https://athena.test/athena-app/research-manuals/${version}/file`);
            assert.equal(page.url(), 'https://athena.test/chat');
            const search = modal.getByRole('searchbox', { name: 'Search source', exact: true });
            await search.fill('Ethics');
            await expect(modal.locator('[data-manual-find-status]')).toContainText('1 of 1');
            await modal.getByRole('button', { name: 'Zoom in', exact: true }).click();
            await expect(modal.locator('output')).toHaveText('110%');
            await modal.getByRole('button', { name: 'Close PDF preview', exact: true }).click();
            await expect(modal).toBeHidden();
            await expect(open).toBeFocused();
            await details.press('Escape');
            await expect(page.locator('[data-assistant-citation]')).toBeFocused();
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally { await browser.close(); }
});
