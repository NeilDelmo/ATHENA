import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from '@playwright/test';

const source = (await readFile(new URL('../../resources/js/research-call-carousel.js', import.meta.url), 'utf8'))
    .replace('export default initializeResearchCallCarousels;', 'initializeResearchCallCarousels();');

const fixture = `<main><a id="outside" href="#">Outside content</a>
<section data-research-call-carousel>
<article data-research-call-slide><button data-research-call-preview>First preview<img data-research-call-poster-trigger src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="First poster"></button><a href="#proposal">Start a proposal</a></article>
<article data-research-call-slide hidden inert><button data-research-call-preview>Second preview<img data-research-call-poster-trigger src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="Second poster"></button></article>
<p data-research-call-counter></p><button data-research-call-previous>Previous</button><button data-research-call-next>Next</button>
<div data-research-call-lightbox class="hidden" role="dialog" aria-hidden="true"><button data-research-call-lightbox-close>Close preview</button><img data-research-call-lightbox-image></div>
</section></main>`;

test('carousel navigation, keyboard previews, and navigation cleanup work at desktop and mobile sizes', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.setContent(`<style>.hidden { display:none; }</style>${fixture}`);
            await page.evaluate(() => { window.setInterval = () => { throw new Error('Posters must not auto-advance while reading'); }; });
            await page.addScriptTag({ content: source });
            await page.addScriptTag({ content: source });
            const slides = page.locator('[data-research-call-slide]');
            assert.equal(await slides.nth(1).isVisible(), false);
            await page.locator('[data-research-call-next]').click();
            assert.equal(await slides.nth(0).isVisible(), false);
            assert.equal(await slides.nth(0).evaluate((slide) => slide.inert), true);
            assert.equal(await page.locator('[data-research-call-counter]').textContent(), 'Announcement 2 of 2');
            await slides.nth(1).locator('[data-research-call-preview]').focus();
            await page.keyboard.press('Enter');
            assert.equal(await page.locator('[data-research-call-lightbox-image]').getAttribute('alt'), 'Second poster');
            assert.equal(await page.locator('main').evaluate((element) => element.inert), true);
            await page.keyboard.press('Tab');
            assert.equal(await page.locator('[data-research-call-lightbox-close]').evaluate((button) => document.activeElement === button), true);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('main').evaluate((element) => element.inert), false);
            assert.equal(await slides.nth(1).locator('[data-research-call-preview]').evaluate((button) => document.activeElement === button), true);
            await page.locator('[data-research-call-next]').click();
            assert.equal(await page.locator('[data-research-call-counter]').textContent(), 'Announcement 1 of 2');
            await page.locator('[data-research-call-previous]').click();
            await slides.nth(1).locator('[data-research-call-preview]').click();
            await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigating')));
            assert.equal(await page.locator('[data-research-call-lightbox]').count(), 0);
            assert.equal(await page.locator('main').evaluate((element) => element.inert), false);
            assert.equal(await page.evaluate(() => document.body.style.overflow), '');
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
