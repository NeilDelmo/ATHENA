import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = path => readFileSync(resolve(path), 'utf8');

test('project team stays readable and preserves invitations, roles, and access on mobile and desktop', async () => {
    if (process.env.ATHENA_TEAM_FIXTURE_READY !== '1') {
        execFileSync('php', ['vendor/pestphp/pest/bin/pest', '--compact', 'tests/Feature/ProposalDraftCollaborationTest.php', '--filter=project team roster keeps member states and management controls visible'], {
            encoding: 'utf8', maxBuffer: 2 * 1024 * 1024,
            env: { ...process.env, ATHENA_EXPORT_PROPOSAL_TEAM: '1', ATHENA_TEST_PRESERVE_DATABASE: '1' },
        });
    }
    const fixtures = {};
    for (const state of ['owner-unassigned', 'owner-assigned', 'collaborator', 'locked']) {
        const path = resolve(`storage/framework/testing/proposal-team-${state}.html`);
        assert.ok(existsSync(path), `Export the ${state} project team fixture first.`);
        fixtures[state] = readFileSync(path, 'utf8');
    }
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const app = read('resources/js/app.js');
    const registrations = ['researchSecretaryPicker', 'proposalDraftMembers'].map(name => {
        const start = app.indexOf(`Alpine.data('${name}',`);
        const end = app.indexOf('\nAlpine.data(', start + 1);
        assert.ok(start >= 0 && end > start, `Missing ${name} registration.`);
        return app.slice(start, end);
    }).join('\n');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                let mutations = 0;
                let pageHtml;
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'warning' && message.text().startsWith('Alpine Expression Error')) errors.push(message.text());
                });
                await page.route('**/*', route => {
                    const request = route.request();
                    if (request.method() !== 'GET') {
                        mutations++;
                        return route.abort();
                    }
                    if (new URL(request.url()).pathname === '/proposal-team-test') {
                        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<html class="${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles.css}\n[x-cloak]{display:none!important}\n@media(min-width:640px){main{max-width:calc(100vw - 320px)!important}}</style></head><body class="font-sans bg-slate-50 dark:bg-slate-950"><main class="mx-auto max-w-7xl"><div class="athena-page-content w-full">${pageHtml}</div></main></body></html>` });
                    }
                    return route.abort();
                });
                const initialize = async state => {
                    pageHtml = await page.evaluate(source => {
                        const document = new DOMParser().parseFromString(source, 'text/html');
                        const root = document.querySelector('[data-workspace-palette]');
                        const reduced = root.cloneNode(false);
                        reduced.append(root.querySelector('[role="tablist"]').cloneNode(true));
                        for (const panel of root.querySelectorAll(':scope > section[role="tabpanel"]')) {
                            reduced.append(panel.id === 'proposal-collaborators-tab' ? panel.cloneNode(true) : panel.cloneNode(false));
                        }
                        let modal = document.querySelector('[data-collaborator-invitation-modal]');
                        while (modal && !modal.hasAttribute('x-on:open-modal.window')) modal = modal.parentElement;
                        return reduced.outerHTML + (modal?.outerHTML || '');
                    }, fixtures[state]);
                    await page.goto(`http://localhost/proposal-team-test?state=${state}#proposal-collaborators`);
                    await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => { ${registrations} }); window.submittedForms = []; HTMLFormElement.prototype.submit = function () { window.submittedForms.push({ action: this.action, values: Object.fromEntries(new FormData(this)) }); }; document.addEventListener('submit', event => event.preventDefault());` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                    await expect(page.locator('#proposal-collaborators-tab-button')).toHaveAttribute('aria-selected', 'true');
                    await expect(page.locator('#proposal-collaborators-tab')).toBeVisible();
                };
                const readable = async (locator, minimum) => {
                    const font = await locator.evaluate(element => ({ size: parseFloat(getComputedStyle(element).fontSize), family: getComputedStyle(element).fontFamily }));
                    assert.ok(font.size >= minimum, `${await locator.textContent()} needs at least ${minimum}px text, got ${font.size}px.`);
                    assert.match(font.family, /Plus Jakarta Sans/);
                };
                const touchTarget = async control => {
                    await control.evaluate(async () => {
                        await new Promise(requestAnimationFrame);
                        await Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})));
                    });
                    await readable(control, 16);
                    assert.ok((await control.boundingBox()).height >= 44, 'Management controls need 44px touch targets.');
                };
                const screenshot = async name => {
                    await page.locator('main').evaluate(async element => {
                        await new Promise(requestAnimationFrame);
                        await Promise.all(element.getAnimations({ subtree: true }).map(animation => animation.finished.catch(() => {})));
                    });
                    assert.deepEqual(errors, []);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Overflow in ${name} at ${width}px.`);
                    if (process.env.PROPOSAL_TEAM_SCREENSHOT_DIRECTORY) {
                        mkdirSync(process.env.PROPOSAL_TEAM_SCREENSHOT_DIRECTORY, { recursive: true });
                        await page.screenshot({ path: resolve(process.env.PROPOSAL_TEAM_SCREENSHOT_DIRECTORY, `${name}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                    }
                };

                for (const state of Object.keys(fixtures)) {
                    await initialize(state);
                    const team = page.locator('#proposal-collaborators-tab');
                    await readable(team.locator('#workspace-members-heading'), 20);
                    for (const icon of await team.locator('[data-team-section-icon], [data-secretary-section-icon]').all()) {
                        await expect(icon).toBeVisible();
                        assert.equal(await icon.getAttribute('aria-hidden'), 'true');
                    }
                    await expect(team.locator('[data-team-section-icon]')).toHaveCount(1);
                    await expect(team.locator('[data-secretary-section-icon]')).toHaveCount(1);
                    const members = team.locator('[data-project-team-roster] article[data-project-team-member]');
                    await expect(members).toHaveCount(4);
                    const statuses = [];
                    for (const member of await members.all()) {
                        await readable(member.locator('[data-team-member-name]'), 18);
                        await readable(member.locator('[data-team-member-email]'), 16);
                        const status = member.locator('[data-team-member-status]');
                        await readable(status, 14);
                        statuses.push((await status.textContent()).trim());
                        await readable(member.locator('p').last(), 16);
                    }
                    assert.deepEqual(statuses.sort(), ['Project leader', 'Confirmed', 'Invitation pending', 'Pending sign-in'].sort());
                    if (state === 'locked') await expect(members.first()).not.toContainText(/delete this draft/i);
                    await expect(members.locator('[data-user-avatar]')).toHaveCount(3);
                    await expect(members.locator('[data-team-pending-avatar]')).toHaveCount(1);
                    await expect(members.locator('[data-team-pending-avatar] svg')).toBeVisible();
                    for (const avatar of await members.locator('[data-user-avatar]').all()) {
                        await expect(avatar).toBeVisible();
                        await expect(avatar.locator('span').first()).toBeVisible();
                        await expect(avatar.locator('[data-user-avatar-image]')).toBeHidden();
                        assert.ok((await avatar.boundingBox()).width >= 44, 'Team avatars need room for a clear initial.');
                    }
                    await screenshot(state);

                    const canManage = state.startsWith('owner-');
                    const add = page.locator('[data-open-collaborator-modal]');
                    const picker = team.locator('[data-project-role-picker]');
                    await expect(add).toHaveCount(canManage ? 1 : 0);
                    await expect(picker).toHaveCount(canManage ? 1 : 0);
                    await expect(team.getByRole('button', { name: 'Resend invitation', exact: true })).toHaveCount(canManage ? 2 : 0);
                    await expect(team.getByRole('button', { name: 'Remove', exact: true })).toHaveCount(canManage ? 2 : 0);
                    if (canManage) {
                        for (const control of await team.locator('button:visible').all()) await touchTarget(control);
                        await add.click();
                        const invitation = page.locator('[data-collaborator-invitation-modal]');
                        await expect(invitation).toBeVisible();
                        const close = invitation.getByRole('button', { name: 'Close project staff invitation', exact: true });
                        await expect(close).toBeFocused();
                        const send = invitation.getByRole('button', { name: 'Send invitation', exact: true });
                        await close.press('Shift+Tab');
                        await expect(send).toBeFocused();
                        await send.press('Tab');
                        await expect(close).toBeFocused();
                        await readable(invitation.locator('h2'), 20);
                        for (const control of await invitation.locator('input, button:visible').all()) {
                            if (await control.getAttribute('type') === 'hidden' || await control.getAttribute('aria-label') === 'Close project staff invitation') continue;
                            await touchTarget(control);
                        }
                        const email = invitation.getByRole('combobox');
                        await email.focus();
                        await expect(email).toHaveAttribute('aria-expanded', 'true');
                        const account = invitation.getByRole('option').first();
                        await expect(account).toBeVisible();
                        const expectedAccount = await account.evaluate(element => ({ name: Alpine.$data(element).candidate.name, email: Alpine.$data(element).candidate.email }));
                        await email.fill(expectedAccount.email);
                        await email.press('Enter');
                        await expect(email).toHaveValue(expectedAccount.email);
                        await expect(invitation.locator('#workspace-member-name')).toHaveValue(expectedAccount.name);
                        await expect(email).toHaveAttribute('aria-expanded', 'false');
                        await screenshot(`${state}-invitation`);
                        await page.keyboard.press('Escape');
                        await expect(invitation).toBeHidden();
                        await expect(add).toBeFocused();
                        assert.equal(await page.locator('body').evaluate(body => body.classList.contains('overflow-y-hidden')), false);

                        const trigger = picker.getByRole('button', { name: state === 'owner-assigned' ? 'Change secretary' : 'Select project staff', exact: true });
                        await trigger.click();
                        const search = picker.getByRole('searchbox');
                        await expect(search).toBeFocused();
                        await touchTarget(search);
                        const options = picker.getByRole('option');
                        await expect(options).toHaveCount(1);
                        const option = options.first();
                        const selectedId = await picker.locator('input[name="member_id"]').inputValue();
                        const candidate = await option.evaluate(element => ({ id: String(Alpine.$data(element).candidate.id), name: Alpine.$data(element).candidate.name }));
                        await expect(option).toHaveAttribute('aria-selected', String(candidate.id === selectedId));
                        await search.fill('no matching project member');
                        await expect(options).toHaveCount(0);
                        await expect(picker.getByText('Staff must accept the project invitation before receiving a project role.', { exact: true })).toBeVisible();
                        await search.fill(candidate.name);
                        await expect(option).toBeVisible();
                        await touchTarget(option);
                        await option.scrollIntoViewIfNeeded();
                        assert.equal(await option.evaluate(element => { const bounds = element.getBoundingClientRect(); return element.contains(document.elementFromPoint(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2)); }), true, 'Secretary options must not be clipped by the team section.');
                        await screenshot(`${state}-secretary-picker`);
                        await search.focus();
                        await page.keyboard.press('Escape');
                        await expect(search).toBeHidden();
                        await expect(trigger).toBeFocused();
                        await trigger.click();
                        await option.click();
                        await expect(picker.locator('input[name="member_id"]')).toHaveValue(candidate.id);
                        await expect.poll(() => page.evaluate(() => window.submittedForms.length)).toBe(1);
                        const submission = await page.evaluate(() => window.submittedForms[0]);
                        assert.match(submission.action, /\/member-roles$/);
                        assert.equal(submission.values.member_id, candidate.id);
                        assert.equal(submission.values.project_role, 'secretary');
                        assert.equal(submission.values._method, 'PATCH');
                    }
                    await page.locator('#project-details-tab-button').click();
                    await expect(team).toBeHidden();
                    await page.locator('#proposal-collaborators-tab-button').click();
                    await expect(team).toBeVisible();
                    assert.equal(await page.evaluate(() => location.hash), '#proposal-collaborators');
                    assert.deepEqual(errors, []);
                    assert.equal(mutations, 0);
                }
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
