import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';

const runtime = readFileSync(new URL('../../resources/js/proposal-preview-workspace.js', import.meta.url), 'utf8').replace(/^export /gm, '');

test('long table narratives flow across physical pages without losing text or clipping the final paragraph', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1100, height: 1100 } });
        const paragraphs = Array.from({ length: 100 }, (_, index) => `<p id="paragraph-${index}">Study ${index}: Community monitoring records explain the observations and findings in this research project.</p>`).join('');
        await page.setContent(`<style>@page{size:8.5in 13in;margin:.5in}*{box-sizing:border-box}body{margin:0;padding:16px;background:#ddd;font:12pt/1.4 serif}.sheet{width:8.5in;min-height:13in;padding:.5in;background:white}table{width:100%;border-collapse:collapse;table-layout:fixed}td{border:1px solid;padding:8px}p{margin:0 0 10px}</style><main data-paper-sheet class="sheet"><table><tbody><tr><td>${paragraphs}</td></tr></tbody></table><footer data-paper-footer><span>Page 1 of 1</span></footer></main>`);
        const original = await page.locator('[data-paper-sheet] table').textContent();
        await page.addScriptTag({ content: runtime });
        const result = await page.evaluate(() => {
            const pagination = initializePaperPagination(document);
            return { count: pagination.count, page: pagination.page };
        });
        assert.ok(result.count >= 3, 'Long content must create multiple pages');
        assert.equal(result.page, 1);
        assert.equal(await page.locator('[data-paper-sheet] table').textContent(), original);
        for (const index of [0, 25, 50, 75, 99]) {
            const bounds = await page.evaluate(index => {
                const paragraph = document.querySelector(`[data-paper-rendered] [data-paper-original-id="paragraph-${index}"]`);
                document.paperPagination.reveal(paragraph);
                const sheet = paragraph.closest('[data-paper-pagination-sheet]').getBoundingClientRect();
                const rect = paragraph.getBoundingClientRect();
                return { inside: rect.left >= sheet.left && rect.right <= sheet.right + 1 && rect.top >= sheet.top && rect.bottom <= sheet.bottom + 1, page: document.paperPagination.page };
            }, index);
            assert.ok(bounds.inside, `Paragraph ${index} must be visible on its page`);
        }
        assert.ok(await page.evaluate(() => document.paperPagination.page > 1));
        assert.equal(await page.getByRole('button', { name: 'Next page', exact: true }).count(), 0);
        const rendered = (await page.locator('[data-paper-rendered] .paper-pagination-fragment').allTextContents()).join('').replace(/\s+/g, ' ').trim();
        assert.equal(rendered, original.replace(/\s+/g, ' ').trim(), 'The scrolling pages preserve every word exactly once');
        await page.evaluate(() => {
            const first = document.querySelector('[data-paper-pagination-sheet]');
            window.scrollBy({ top: first.getBoundingClientRect().bottom - 250, behavior: 'instant' });
        });
        await page.screenshot({ path: 'storage/framework/testing/preview-continuous-page-break.png', fullPage: false });
        await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
        assert.equal(await page.locator('.paper-pagination-flow').count(), 0);
        assert.equal(await page.locator('[data-paper-sheet] table').textContent(), original);
        await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
        assert.equal(await page.locator('.paper-pagination-flow').count(), 1);
        const pdf = await page.pdf({ preferCSSPageSize: true });
        assert.ok([...pdf.toString('latin1').matchAll(/\/Type\s*\/Page\b/g)].length >= 3, 'Printing must retain the complete multipage document');
        await page.close();
    } finally { await browser.close(); }
});

test('landscape sheets, explicit print breaks, and existing PDF pages retain their page sequence', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<style>@page{size:13in 8.5in;margin:.5in}*{box-sizing:border-box}.sheet{width:13in;min-height:8.5in;padding:.5in} @media print{.projects{break-before:page}}</style><main data-paper-sheet class="sheet"><p>First page</p><div class="projects">Second page</div></main><main data-paper-sheet class="sheet"><p>Third page</p></main>`);
        await page.addScriptTag({ content: runtime });
        assert.equal(await page.evaluate(() => initializePaperPagination(document).count), 3);
        const geometry = await page.locator('[data-paper-pagination-sheet]').first().evaluate(sheet => ({width:sheet.offsetWidth,height:sheet.offsetHeight}));
        assert.ok(geometry.width > geometry.height);
        await page.evaluate(() => document.paperPagination.go(3));
        assert.ok(await page.locator('[data-paper-pagination-sheet]').last().isVisible());
        await page.evaluate(() => document.paperPagination.reveal(document.querySelector('.projects')));
        assert.equal(await page.evaluate(() => document.paperPagination.page), 2);
        await page.goto('about:blank');
        await page.setContent('<style>.assessment-page{width:600px;height:800px;background:white}</style><section class="assessment-page">PDF page 1</section><section class="assessment-page">PDF page 2</section>');
        await page.addScriptTag({ content: runtime });
        assert.equal(await page.evaluate(() => initializePaperPagination(document).count), 2);
        assert.equal(await page.locator('.paper-pagination-flow').count(), 0);
        await page.close();
    } finally { await browser.close(); }
});


test('real proposal and report layouts paginate with their paper size and retain every narrative paragraph', { timeout: 120000 }, async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/PaperPreviewPaginationTest.php'], { encoding: 'utf8', env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_EXPORT_PAGINATION: '1' } });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const styles = {
        'detailed-proposal': 'detailed-proposal', 'work-plan': 'work-plan', 'line-item-budget': 'line-item-budget',
        'expense-breakdown': 'expense-breakdown', 'curriculum-vitae': 'curriculum-vitae', 'gad-checklist': 'gad-checklist',
        'initial-screening': 'initial-screening-form', 'comment-response': 'comment-response-form',
        monitoring: 'monitoring-tool', 'progress-report': 'progress-report', 'terminal-report': null, 'notice-to-proceed': 'notice-to-proceed', 'coordinator-report': null,
    };
    try {
        for (const [paper, style] of Object.entries(styles)) {
            const page = await browser.newPage({ viewport: { width: 1350, height: 1400 } });
            await page.route('**/*', async route => {
                const pathname = decodeURIComponent(new URL(route.request().url()).pathname).replace(/^\/athena-app/, '');
                const path = resolve('public', pathname.replace(/^\//, ''));
                if (existsSync(path)) await route.fulfill({ path });
                else await route.abort();
            });
            const html = readFileSync(`storage/framework/testing/pagination-${paper}.html`, 'utf8');
            await page.setContent(html);
            if (style) await page.addStyleTag({ path: `resources/css/${style}-print.css` });
            const original = await page.locator('[data-paper-sheet]').allTextContents();
            await page.addScriptTag({ content: runtime });
            const count = await page.evaluate(() => initializePaperPagination(document).count);
            const minimum = { 'work-plan': 2, 'curriculum-vitae': 4, 'gad-checklist': 7, 'initial-screening': 1 }[paper] || 1;
            assert.ok(count >= minimum, `${paper}: expected at least ${minimum} pages, received ${count}`);
            const integrity = await page.evaluate(() => {
                const source = [...document.querySelectorAll('[data-paper-pagination-source] .paper-pagination-flow')].map(element => element.textContent).join('').replace(/\s+/g, '');
                const rendered = [...document.querySelectorAll('[data-paper-rendered] .paper-pagination-fragment')].map(element => element.textContent).join('').replace(/\s+/g, '');
                const clipped = [];
                for (const sheet of document.querySelectorAll('[data-paper-rendered]')) {
                    const bounds = sheet.getBoundingClientRect();
                    const walker = document.createTreeWalker(sheet.querySelector('.paper-pagination-fragment'), NodeFilter.SHOW_TEXT);
                    let node;
                    while ((node = walker.nextNode())) {
                        if (!node.textContent.trim() || node.parentElement.closest('style,script')) continue;
                        const range = document.createRange();
                        range.selectNodeContents(node);
                        if ([...range.getClientRects()].some(rect => rect.width > 0 && (rect.left < bounds.left - 1 || rect.right > bounds.right + 1 || rect.top < bounds.top - 1 || rect.bottom > bounds.bottom + 1))) clipped.push(node.textContent.slice(0, 70));
                    }
                }
                return { same: source === rendered, clipped: clipped.slice(0, 3) };
            });
            assert.equal(integrity.same, true, `${paper}: every word appears once in the scrolling pages`);
            assert.deepEqual(integrity.clipped, [], `${paper}: no text is cut off at page boundaries`);

            if (['detailed-proposal', 'comment-response', 'progress-report', 'terminal-report'].includes(paper)) {
                assert.ok(count > 2, `${paper}: long narrative must create additional pages`);
                assert.ok((await page.locator('[data-paper-sheet]').allTextContents()).join('').includes('Study 100:'), `${paper}: last paragraph retained`);
                const visible = await page.evaluate(() => {
                    const sheets = document.querySelectorAll('[data-paper-rendered]');
                    let node;
                    for (const sheet of sheets) {
                        const walker = document.createTreeWalker(sheet.querySelector('.paper-pagination-fragment'), NodeFilter.SHOW_TEXT);
                        while ((node = walker.nextNode()) && !node.textContent.includes('Study 100:')) {}
                        if (node) break;
                    }
                    const sheet = node.parentElement.closest('[data-paper-rendered]');
                    document.paperPagination.reveal(node.parentElement);
                    const start = node.textContent.indexOf('Study 100:');
                    const range = document.createRange();
                    range.setStart(node, start);
                    range.setEnd(node, start + 10);
                    const rect = range.getBoundingClientRect();
                    const bounds = sheet.getBoundingClientRect();
                    return rect.left >= bounds.left - 1 && rect.right <= bounds.right + 1 && rect.top >= bounds.top - 1 && rect.bottom <= bounds.bottom + 1;
                });
                assert.ok(visible, `${paper}: last paragraph must be visible inside its page`);
            }
            await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
            assert.deepEqual(await page.locator('[data-paper-sheet]').allTextContents(), original, `${paper}: printing retains the original complete document`);
            await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
            if (paper === 'monitoring') {
                assert.equal(await page.locator('.paper-pagination-flow .monitoring-signatures').count(), 1, 'Signatures belong after the report content');
                const overlap = await page.locator('[data-paper-rendered] .monitoring-signatures').evaluate(element => {
                    document.paperPagination.reveal(element);
                    const signature = element.getBoundingClientRect();
                    const previous = [...element.previousElementSibling?.getClientRects() || []].at(-1);
                    if (!previous) return false;
                    return signature.left < previous.right && signature.right > previous.left && signature.top < previous.bottom - 1;
                });
                assert.equal(overlap, false, 'Signatures must not overlap the report table');
            }
            if (paper === 'gad-checklist') assert.equal(count, 7, 'The seven original checklist pages stay intact');
            if (paper === 'initial-screening') {
                assert.equal(count, 1);
                const image = page.locator('.initial-screening-source');
                assert.equal(await image.evaluate(element => element.offsetHeight), 1248);
            }
            if (paper === 'detailed-proposal') {
                const lastPageText = await page.evaluate(() => {
                    const pagination = document.paperPagination;
                    pagination.go(pagination.count);
                    const flow = [...document.querySelectorAll('[data-paper-rendered] .paper-pagination-fragment')].at(-1);
                    const sheet = flow.closest('[data-paper-pagination-sheet]').getBoundingClientRect();
                    const walker = document.createTreeWalker(flow, NodeFilter.SHOW_TEXT);
                    const visible = [];
                    let node;
                    while ((node = walker.nextNode())) {
                        if (!node.textContent.trim()) continue;
                        const range = document.createRange();
                        range.selectNodeContents(node);
                        if ([...range.getClientRects()].some(rect => rect.left >= sheet.left && rect.right <= sheet.right + 1 && rect.top >= sheet.top && rect.bottom <= sheet.bottom)) visible.push(node.textContent);
                    }
                    return visible.join(' ');
                });
                assert.ok(lastPageText.trim().length, 'The final proposal page must contain document content');
            }
            await page.screenshot({ path: `storage/framework/testing/pagination-${paper}.png`, fullPage: false });
            await page.close();
        }
    } finally { await browser.close(); }
});

test('embedded counter follows scrolling and preserves fit and zoom', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });
        const controls = readFileSync('resources/views/components/paper-preview-pagination.blade.php', 'utf8');
        await page.setContent(`<form onsubmit="window.submitted=true;return false"><main x-data="previewTest">${controls}<iframe x-ref="previewFrame" style="width:700px;height:700px"></iframe></main></form>`);
        await page.addScriptTag({ content: `${runtime}; document.addEventListener('alpine:init', () => Alpine.data('previewTest', () => ({ ...proposalPreviewWorkspace(), previewReady:true, init() { window.previewState=this; } })));` });
        await page.addScriptTag({ path: 'node_modules/alpinejs/dist/cdn.min.js' });
        await page.waitForFunction(() => Boolean(window.previewState));
        await page.evaluate(() => {
            const frame = document.querySelector('iframe');
            frame.srcdoc = '<style>@page{size:8.5in 13in;margin:.5in}body{margin:0}</style><main data-paper-sheet>'+Array.from({length:150}, (_,i)=>'<p data-proposal-preview-section="section-'+i+'">Paragraph '+i+'</p>').join('')+'</main>';
        });
        await page.waitForFunction(() => document.querySelector('iframe').contentDocument?.querySelectorAll('p').length === 150);
        await page.evaluate(() => window.previewState.applyProposalPreviewZoom());
        assert.ok(await page.evaluate(() => window.previewState.previewPageCount > 1));
        assert.equal(await page.getByRole('button', { name: 'Next page', exact: true }).count(), 0);
        await page.locator('iframe').hover();
        await page.mouse.wheel(0, 1400);
        await page.waitForFunction(() => window.previewState.previewPage > 1);
        assert.match(await page.getByLabel('Document pages').textContent(), /Page\s+\d+\s+of\s+\d+/);
        const readingPage = await page.evaluate(() => window.previewState.previewPage);
        await page.evaluate(() => window.previewState.fitProposalPreview('page'));
        assert.equal(await page.evaluate(() => window.previewState.previewPage), readingPage, 'Changing zoom retains the current page');
        const height = await page.locator('iframe').evaluate(frame => {
            const sheet = frame.contentDocument.querySelector('[data-paper-pagination-sheet]');
            return sheet.getBoundingClientRect().height;
        });
        assert.ok(height <= 700, 'Fit page shows one physical sheet');
        assert.equal(await page.locator('iframe').evaluate(frame => frame.contentDocument.querySelector('.paper-pagination-controls').style.display), 'none');
        const focus = await page.locator('iframe').evaluate(frame => {
            const source = frame.contentDocument.querySelector('[data-paper-pagination-source] [data-proposal-preview-section="section-80"]');
            frame.contentDocument.paperPagination.reveal(source);
            const target = frame.contentDocument.querySelector('[data-paper-rendered] [data-proposal-preview-section="section-80"]');
            return { top: target.getBoundingClientRect().top, outerScroll: window.scrollY };
        });
        assert.ok(focus.top >= 0 && focus.top <= 30, 'Automatic preview focus reveals the selected section');
        assert.equal(focus.outerScroll, 0, 'Preview focus must not move the editor page');
        assert.equal(await page.getByRole('spinbutton', { name: 'Page number', exact: true }).count(), 0);
        assert.equal(await page.evaluate(() => Boolean(window.submitted)), false, 'Scrolling must not submit the paper');
        await page.close();
    } finally { await browser.close(); }
});


test('paper pages stay stacked and the counter follows ordinary scrolling without page buttons', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1000, height: 700 } });
        await page.setContent('<style>@page{size:8.5in 13in;margin:.5in}body{margin:0;background:#ddd}p{margin:0 0 12px}</style><main data-paper-sheet>'+Array.from({length:180},(_,i)=>'<p>Paragraph '+i+' contains research findings for the proposal.</p>').join('')+'</main>');
        await page.addScriptTag({ content: runtime });
        const count = await page.evaluate(() => initializePaperPagination(document).count);
        assert.ok(count > 2);
        assert.equal(await page.getByRole('button', { name: 'Next page', exact: true }).count(), 0, 'Reading must not require page buttons');
        const sheets = page.locator('[data-paper-pagination-sheet]');
        assert.equal(await sheets.count(), count);
        const spacing = await sheets.evaluateAll(elements => elements[1].getBoundingClientRect().top-elements[0].getBoundingClientRect().bottom);
        assert.ok(spacing >= 12 && spacing <= 40, 'Paper pages have a clear, compact gap');
        await page.mouse.wheel(0, 1350);
        await page.waitForFunction(() => document.paperPagination.page === 2);
        assert.ok(await sheets.nth(0).isVisible());
        assert.ok(await sheets.nth(1).isVisible());
        assert.equal(await sheets.locator('p').count(), 180, 'Every paragraph appears once in the scrolling document');
    } finally { await browser.close(); }
});


test('scrolling page fragments retain ordered list numbering and inline formatting', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<style>@page{size:8.5in 13in;margin:.5in}body{margin:0}li{margin-bottom:20px}</style><main data-paper-sheet><ol start="7">'+Array.from({length:90},(_,i)=>'<li><strong>Method '+i+'</strong> uses field observations and verified research records.</li>').join('')+'</ol></main>');
        await page.addScriptTag({ content: runtime });
        assert.ok(await page.evaluate(() => initializePaperPagination(document).count > 1));
        const values = await page.locator('[data-paper-rendered] li').evaluateAll(items => items.map(item => item.value));
        assert.deepEqual(values, Array.from({length:90},(_,i)=>i+7));
        assert.equal(await page.locator('[data-paper-rendered] li strong').count(), 90);
    } finally { await browser.close(); }
});
