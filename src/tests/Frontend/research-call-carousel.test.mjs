import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';

const source = (await readFile(new URL('../../resources/js/research-call-carousel.js', import.meta.url), 'utf8'))
    .replace('export default initializeResearchCallCarousels;', 'initializeResearchCallCarousels();');

const fixture = `<main><a id="outside" href="#">Outside content</a>${execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    echo view('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => collect([
        ['url' => '/first-poster.png', 'alt' => 'First poster', 'isResearchCall' => true, 'canSubmitProposal' => true],
        ['url' => '/second-poster.png', 'alt' => 'Second poster', 'isResearchCall' => false, 'canSubmitProposal' => false],
    ])])->render();
`], { encoding: 'utf8' })}</main>`;
const manifest = JSON.parse(await readFile(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'));
const styles = await readFile(new URL(`../../public/build/${manifest['resources/css/app.css'].file}`, import.meta.url), 'utf8');

test('carousel navigation, keyboard previews, and navigation cleanup work at desktop and mobile sizes', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const { width, dark } of [{ width: 1440, dark: false }, { width: 1440, dark: true }, { width: 390, dark: false }, { width: 390, dark: true }]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles}</style><body>${fixture}</body></html>`);
            await page.evaluate(() => { window.setInterval = () => { throw new Error('Posters must not auto-advance while reading'); }; });
            await page.addScriptTag({ content: source });
            await page.addScriptTag({ content: source });
            const slides = page.locator('[data-research-call-slide]');
            assert.equal(await slides.nth(0).locator('[data-research-call-preview]').count(), 1);
            assert.equal(await page.getByRole('button', { name: 'View full poster' }).count(), 0);
            const callsLink = slides.nth(0).getByRole('link', { name: 'View research calls' });
            const linkStyle = await callsLink.evaluate((element) => {
                const style = getComputedStyle(element);
                return { decoration: style.textDecorationLine, border: style.borderTopWidth, background: style.backgroundColor, height: element.getBoundingClientRect().height };
            });
            assert.equal(linkStyle.decoration, 'underline');
            assert.equal(linkStyle.border, '0px');
            assert.equal(linkStyle.background, 'rgba(0, 0, 0, 0)');
            assert.ok(linkStyle.height >= 44);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
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
