import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chromium } from '../src/node_modules/@playwright/test/index.mjs';

const output = resolve('../.tmp');
const dataImage = (path) => 'data:image/png;base64,' + readFileSync(path).toString('base64');
const logo = dataImage('public/images/batstateu-logo.png');
const browser = await chromium.launch({ headless: true });
try {
    for (const type of ['monitoring', 'progress']) {
        const css = readFileSync(`resources/css/${type === 'monitoring' ? 'monitoring-tool' : 'progress-report'}-print.css`, 'utf8');
        const html = readFileSync(resolve(output, `${type}-template-preview.html`), 'utf8')
            .replace(/<link[^>]+>/g, '').replace('</head>', `<style>${css}</style></head>`)
            .replace(/src="[^"]*batstateu-logo.png"/g, `src="${logo}"`);
        const page = await browser.newPage({ viewport: { width: type === 'monitoring' ? 1440 : 1000, height: 1000 } });
        await page.route('**/*', route => route.abort());
        await page.setContent(html);
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: resolve(output, `${type}-template-preview.png`), fullPage: true });
        await page.pdf({ path: resolve(output, `${type}-template-preview.pdf`), preferCSSPageSize: true, printBackground: true });
        const bounds = await page.evaluate(() => ({
            font: getComputedStyle(document.body).fontFamily,
            width: document.querySelector('main').getBoundingClientRect().width,
            overflows: [...document.querySelectorAll('td, th')].filter(el => el.scrollWidth > el.clientWidth + 2).map(el => el.textContent.trim()),
            brokenImages: [...document.images].filter(el => !el.complete || !el.naturalWidth).length,
        }));
        assert.deepEqual(bounds.overflows, []);
        assert.equal(bounds.brokenImages, 0);
        assert.equal(bounds.width, type === 'monitoring' ? 1248 : 816);
        console.log(JSON.stringify({ type, ...bounds }));
        if (type === 'progress') {
            await page.locator('.progress-report-section').first().evaluate((cell, source) => {
                const paragraph = document.createElement('p');
                paragraph.className = 'progress-report-narrative';
                paragraph.textContent = 'Field observations and interviews documented the conditions across all participating communities. '.repeat(250);
                cell.append(paragraph);
                const figure = document.createElement('figure');
                const image = document.createElement('img'); image.src = source; image.alt = 'Sample field documentation';
                const caption = document.createElement('figcaption'); caption.textContent = 'Figure 1. Sample field documentation.';
                figure.append(image, caption); cell.append(figure);
            }, dataImage('public/images/progress-report-demo/figure-1.png'));
            await page.locator('figure img').evaluate(el => el.decode());
            await page.pdf({ path: resolve(output, 'progress-template-long-preview.pdf'), preferCSSPageSize: true, printBackground: true });
        }
        await page.close();
    }
} finally { await browser.close(); }
