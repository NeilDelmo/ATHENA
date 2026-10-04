import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const root = fileURLToPath(new URL('../../', import.meta.url));
const tailwindConfig = loadConfig(fileURLToPath(new URL('../../tailwind.config.js', import.meta.url)));
const source = await readFile(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const { css } = await postcss([tailwindcss(tailwindConfig)]).process(source, { from: undefined });

const headings = [['simple', `<x-page-header title="Research Calls" subtitle="Manage published schedules and review upcoming submission deadlines.">
            <x-slot:actions>
                <button class="bg-red-600 px-5 py-2.5 text-white">Create new call</button>
                <button class="border border-gray-200 px-5 py-2.5">Announcement studio</button>
            </x-slot:actions>
           </x-page-header>`]];

for (const [workspace, view] of [
    ['research-head', 'research_head/dashboard'],
    ['research-office', 'research_coordinator/dashboard'],
    ['faculty', 'faculty/dashboard'],
    ['faculty-researcher', 'research/dashboard'],
]) {
    const viewSource = await readFile(new URL(`../../resources/views/${view}.blade.php`, import.meta.url), 'utf8');
    const heading = viewSource.match(/<x-slot name="header">([\s\S]*?)\r?\n    <\/x-slot>/)?.[1];
    assert.ok(heading, `${workspace} must define its page header`);
    headings.push([workspace, heading]);
}

function renderHeader(heading) {

    return execFileSync('php', ['-r', String.raw`
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $user = new App\Models\User(["name" => "Header Tester", "college" => "Test College"]);
        Illuminate\Support\Facades\Auth::setUser($user);
        echo Illuminate\Support\Facades\Blade::render(file_get_contents("php://stdin"), ["coordinator" => $user]);
    `], { cwd: root, input: `<x-page-header container>${heading}</x-page-header>`, encoding: 'utf8' });
}

test('page header decoration stays behind readable clickable content at desktop and mobile sizes in both themes', async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,
    });

    try {
        for (const [workspace, heading] of headings) {
            const hero = workspace !== 'simple';
            const html = renderHeader(heading);
            const previousHtml = hero ? renderHeader(heading.replaceAll('variant="hero"', 'variant="banner"')) : html;

            for (const width of [1440, 640, 390]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 600 } });
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css}</style></head>
                        <body data-app-shell><div style="margin-left:${width < 640 ? 76 : 280}px">${previousHtml}</div></body></html>`);
                    const previousStyle = await page.locator('[data-page-header-container]').evaluate((header) => ({
                        height: header.getBoundingClientRect().height,
                        buttonColor: header.querySelector('button, a') ? getComputedStyle(header.querySelector('button, a')).backgroundColor : null,
                    }));
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><head><style>${css}</style></head>
                        <body data-app-shell><div style="margin-left:${width < 640 ? 76 : 280}px">${html}</div></body></html>`);

                    const result = await page.locator('[data-page-header-container]').evaluate((header) => {
                        const bounds = header.getBoundingClientRect();
                        const before = getComputedStyle(header, '::before');
                        const after = getComputedStyle(header, '::after');
                        const controls = [...header.querySelectorAll('h1, h2, p, button, a')];
                        const actions = [...header.querySelectorAll('button, a')];
                        const text = [...header.querySelectorAll('h1, h2, p')];

                        return {
                            width: bounds.width,
                            height: bounds.height,
                            transparentTextContainers: text.every((element) => {
                                for (let parent = element; parent && parent !== header; parent = parent.parentElement) {
                                    if (getComputedStyle(parent).backgroundColor !== 'rgba(0, 0, 0, 0)') return false;
                                }
                                return true;
                            }),
                            gridWidth: parseFloat(before.width),
                            glowWidth: parseFloat(after.width),
                            grid: before.backgroundImage,
                            gridSize: before.backgroundSize,
                            gridAnchor: before.backgroundPosition,
                            mask: before.maskImage,
                            glow: after.backgroundImage,
                            gridEvents: before.pointerEvents,
                            glowEvents: after.pointerEvents,
                            zIndex: getComputedStyle(header.firstElementChild).zIndex,
                            background: getComputedStyle(header).backgroundColor,
                            headingColor: getComputedStyle(header.querySelector('h1, h2')).color,
                            buttonColor: actions.length ? getComputedStyle(actions[0]).backgroundColor : null,
                            noOverflow: controls.every((element) => {
                                const rect = element.getBoundingClientRect();
                                return rect.left >= bounds.left && rect.right <= bounds.right
                                    && rect.top >= bounds.top && rect.bottom <= bounds.bottom;
                            }),
                            buttonsReachable: actions.every((button) => {
                                const rect = button.getBoundingClientRect();
                                return button.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
                            }),
                        };
                    });

                    assert.ok(Math.abs(result.gridWidth / result.width - (width < 640 ? 0.4 : 0.62)) < 0.001);
                    assert.ok(Math.abs(result.glowWidth / result.width - (width < 640 ? 0.4 : 0.48)) < 0.001);
                    assert.ok(result.gridSize.split(', ').every((size) => size === '26px 26px'));
                    assert.ok(result.gridAnchor.split(', ').every((position) => position === '100% 100%'));
                    assert.match(result.grid, /linear-gradient/);
                    assert.match(result.mask, /radial-gradient/);
                    assert.match(result.glow, /radial-gradient/);
                    if (workspace === 'simple') {
                        const buttonRgb = result.buttonColor.match(/\d+/g).slice(0, 3).join(', ');
                        assert.ok(result.glow.includes(buttonRgb), 'Glow must use the primary action button color');
                    } else if (result.buttonColor) {
                        assert.equal(result.buttonColor, previousStyle.buttonColor, 'Header decorations must preserve dashboard action colors');
                    }
                    assert.equal(result.gridEvents, 'none');
                    assert.equal(result.glowEvents, 'none');
                    assert.equal(result.zIndex, '1');
                    assert.equal(result.background, dark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)');
                    assert.equal(result.headingColor, dark ? 'rgb(255, 255, 255)' : 'rgb(2, 6, 23)');
                    assert.equal(result.height, previousStyle.height, `${workspace} header height must stay unchanged`);
                    assert.ok(result.transparentTextContainers, `${workspace} text containers must leave the grid and glow visible`);
                    assert.ok(result.noOverflow, `Titles, subtitles, and actions must fit inside the header (workspace=${workspace}, width=${width}, dark=${dark})`);
                    assert.ok(result.buttonsReachable, 'Decorations must not intercept action clicks');

                    if (workspace === 'simple') await page.locator('button').first().click();

                    if (process.env.ATHENA_HEADER_SCREENSHOTS && width !== 640) {
                        await mkdir(process.env.ATHENA_HEADER_SCREENSHOTS, { recursive: true });
                        await page.locator('[data-page-header-container]').screenshot({
                            path: `${process.env.ATHENA_HEADER_SCREENSHOTS}/${workspace}-${width}-${dark ? 'dark' : 'light'}.png`,
                        });
                    }

                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});
