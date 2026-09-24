import { expect, test } from '@playwright/test';

const proposal = {
    title: process.env.DEMO_PROJECT_TITLE ?? 'Smart Campus Research Monitoring and Decision Support System',
    leaderName: process.env.DEMO_LEADER_NAME ?? 'Demo Faculty Researcher',
    leaderEmail: process.env.DEMO_LEADER_EMAIL ?? 'faculty.researcher@g.batstate-u.edu.ph',
    leaderContact: process.env.DEMO_LEADER_CONTACT ?? '09171234567',
    college: process.env.DEMO_COLLEGE ?? 'College of Informatics and Computing Sciences',
    campus: process.env.DEMO_CAMPUS ?? 'ARASOF-Nasugbu',
};

const narrative = {
    executiveBrief: 'This project develops and evaluates a practical decision-support platform that converts institutional data into timely and understandable research monitoring information.',
    rationale: 'Research teams need a consistent way to connect objectives, activities, schedules, outputs, and financial records. The proposed system addresses fragmented monitoring and supports evidence-based project decisions.',
    introduction: 'Digital research administration benefits from clear workflows, timely progress information, and traceable decisions. This proposal examines how an integrated platform can improve monitoring quality and reduce fragmented records.',
    literature: 'Prior studies on research information systems, project monitoring, and decision-support platforms emphasize data quality, workflow transparency, usability, and timely reporting as important adoption factors.',
};

test('fills the proposal papers for a presentation without submitting them', async ({ page }) => {
    const draftId = await openOrCreateDraft(page);

    await fillProjectDetails(page, draftId);
    await fillDetailedProposal(page, draftId);
    await fillWorkPlan(page, draftId);
    await fillLineItemBudget(page, draftId);
    await fillExpenseBreakdown(page, draftId);
    await fillCurriculumVitae(page, draftId);

    await page.goto(`/faculty/proposal-drafts/${draftId}#required-pdf-attachments`);
    await expect(page).toHaveURL(new RegExp(`/faculty/proposal-drafts/${draftId}`));
    console.log(`Proposal draft ${draftId} has been filled but not submitted.`);

    if (process.env.DEMO_NO_PAUSE !== '1') {
        await page.pause();
    }
});

async function openOrCreateDraft(page) {
    await enterFacultyWorkspace(page);

    if (process.env.DEMO_DRAFT_ID) {
        await page.goto(`/faculty/proposal-drafts/${process.env.DEMO_DRAFT_ID}/details`);
        await expect(page.locator('[data-project-details-autosave-form]')).toBeVisible();
        return process.env.DEMO_DRAFT_ID;
    }

    await page.goto('/faculty/proposal-drafts/create');
    await expect(page.locator('#project_title')).toBeVisible();
    await page.locator('#project_title').fill(proposal.title);
    await page.getByRole('button', { name: /Create draft and continue/i }).click();
    await page.waitForURL(/\/faculty\/proposal-drafts\/\d+/);

    const match = new URL(page.url()).pathname.match(/\/proposal-drafts\/(\d+)/);
    if (!match) throw new Error(`Could not determine the proposal draft ID from ${page.url()}.`);

    return match[1];
}

async function enterFacultyWorkspace(page) {
    await page.goto('/dashboard');

    if (new URL(page.url()).pathname.endsWith('/login')) {
        throw new Error('No saved Faculty session was found. Run `npm run demo:proposal:session` first.');
    }

    if (new URL(page.url()).pathname.endsWith('/select-role')) {
        await page.getByRole('button', { name: /Continue as Faculty/i }).click();
        await page.waitForLoadState('domcontentloaded');
    }

    await page.goto('/choose-workspace');

    if (new URL(page.url()).pathname.endsWith('/choose-workspace')) {
        const facultyForm = page.locator('form').filter({
            has: page.locator('input[name="workspace"][value="faculty"]'),
        });
        await facultyForm.getByRole('button').click();
        await page.waitForLoadState('domcontentloaded');
    }
}

async function fillProjectDetails(page, draftId) {
    await page.goto(`/faculty/proposal-drafts/${draftId}/details`);
    const form = page.locator('[data-project-details-autosave-form]');
    await expect(form).toBeVisible();

    await fillValue(form.locator('#project_title'), proposal.title);
    await fillValue(form.locator('#duration_months'), '12');
    await setDateValue(page, 'planned_start', '2026-10-01');
    await setDateValue(page, 'planned_end', '2027-09-30');
    await fillValue(form.locator('#project_leader'), proposal.leaderName, true);
    await waitForAutosave(page);
}

async function fillDetailedProposal(page, draftId) {
    await page.goto(`/faculty/proposal-drafts/${draftId}/detailed-proposal`);
    const form = page.locator('[data-detailed-proposal-autosave-form]');
    await expect(form).toBeVisible();

    await fillValue(form.locator('#research-agenda'), 'Digital transformation, food security, and data-driven decision support');
    await form.locator('input[name="sdgs[]"]').first().check();
    await fillValue(form.locator('#leader-name'), proposal.leaderName, true);
    await fillValue(form.locator('#leader-email'), proposal.leaderEmail, true);
    await fillValue(form.locator('#leader-contact'), proposal.leaderContact, true);
    await fillValue(form.locator('#proponent-college'), proposal.college, true);
    await fillValue(form.locator('#proponent-campus'), proposal.campus, true);
    await fillValue(form.locator('#executive-brief'), narrative.executiveBrief);
    await fillValue(form.locator('#rationale'), narrative.rationale);
    await fillValue(form.locator('#general-objective'), 'Develop and evaluate an integrated research monitoring and decision-support system.');
    await fillValue(form.locator('textarea[name^="specific_objectives"]').first(), 'Design a monitoring workflow that aligns objectives, activities, schedules, outputs, and budget records.');

    if (await form.locator('textarea[name^="expected_outputs"]').count() === 0) {
        await form.getByRole('button', { name: /Add output/i }).first().click();
    }
    await fillValue(form.locator('textarea[name^="expected_outputs"]').first(), 'One peer-reviewed publication and a deployable research monitoring prototype.');

    await fillValue(form.locator('#introduction'), narrative.introduction);
    await fillValue(form.locator('#related-literature'), narrative.literature);
    await fillValue(form.locator('#methodology-research_design'), 'The study will use a design-and-development approach followed by scenario-based usability evaluation with intended university users.');
    await fillValue(form.locator('textarea[name^="specific_method_objectives"][name$="[heading]"]').first(), 'System design and evaluation');
    await fillValue(form.locator('textarea[name^="specific_method_objectives"][name*="[methods]"]').first(), 'Gather workflow requirements, develop the prototype, and evaluate task completion, usability, and data accuracy.');
    await fillValue(form.locator('input[name^="responsibilities"][name$="[name]"]').first(), proposal.leaderName, true);
    await fillValue(form.locator('input[name^="responsibilities"][name$="[percentage]"]').first(), '100', true);
    await fillValue(form.locator('textarea[name^="responsibilities"][name$="[duties]"]').first(), 'Lead the study, coordinate data collection, supervise development, analyze results, and prepare the final reports.');
    await fillValue(form.locator('#references'), 'International Organization for Standardization. (2018). ISO 9241-11: Ergonomics of human-system interaction—Usability.\n\nProject Management Institute. (2021). A guide to the project management body of knowledge (7th ed.).');
    await fillVisibleRequiredFields(form);
    await waitForAutosave(page);
}

async function fillWorkPlan(page, draftId) {
    await page.goto(`/faculty/proposal-drafts/${draftId}/work-plan`);
    const form = page.locator('[data-work-plan-autosave-form]');
    await expect(form).toBeVisible();

    await fillValue(form.locator('textarea[name="entries[0][objective]"]'), 'Develop and evaluate the integrated monitoring prototype.');
    await fillValue(form.locator('textarea[name="entries[0][expected_output]"]'), 'Validated prototype, evaluation results, and technical documentation.');
    await fillValue(form.locator('textarea[name="entries[0][activity]"]'), 'Requirements analysis, interface design, implementation, testing, user evaluation, and report preparation.');
    if (await form.locator('input[name="entries[0][months][]"]:checked').count() === 0) {
        await form.locator('input[name="entries[0][months][]"]:not([disabled])').first().check({ force: true });
    }
    await waitForAutosave(page);
}

async function fillLineItemBudget(page, draftId) {
    await page.goto(`/faculty/proposal-drafts/${draftId}/line-item-budget`);
    const form = page.locator('[data-line-item-budget-autosave-form]');
    await expect(form).toBeVisible();

    await fillValue(form.locator('#leader-campus'), proposal.campus, true);
    await fillValue(form.locator('#leader-college'), proposal.college, true);
    await fillValue(form.locator('#amount-travelling_local'), '5000');
    await waitForAutosave(page);
}

async function fillExpenseBreakdown(page, draftId) {
    await page.goto(`/faculty/proposal-drafts/${draftId}/expense-breakdown`);
    const form = page.locator('[data-expense-breakdown-autosave-form]');
    await expect(form).toBeVisible();

    await selectFirstOption(form.locator('select[name="items[0][category]"]'));
    await selectFirstOption(form.locator('select[id^="expense-account-"]').first());
    await selectFirstOption(form.locator('select[id^="expense-sub-account-"]').first());
    await fillValue(form.locator('input[name="items[0][particulars]"]'), 'Local travel for requirements validation');
    await fillValue(form.locator('input[name="items[0][unit]"]'), 'trip');
    await fillValue(form.locator('input[name="items[0][quantity]"]'), '1');
    await fillValue(form.locator('input[name="items[0][unit_cost]"]'), '5000');
    await fillValue(form.locator('textarea[name="items[0][details]"]'), 'Local travel required for requirements validation, coordination, and user evaluation.');
    await fillValue(form.locator('textarea[name="items[0][purpose]"]'), 'Support data gathering and evaluation activities.');
    await fillVisibleRequiredFields(form);
    await waitForAutosave(page);
}

async function fillCurriculumVitae(page, draftId) {
    await page.goto(`/faculty/proposal-drafts/${draftId}/curriculum-vitae`);
    const form = page.locator('[data-curriculum-vitae-autosave-form]');
    await expect(form).toBeVisible();

    await fillValue(form.locator('input[name="people[0][first_name]"]'), proposal.leaderName.split(' ')[0], true);
    await fillValue(form.locator('input[name="people[0][last_name]"]'), proposal.leaderName.split(' ').at(-1), true);
    await fillValue(form.locator('input[name="people[0][agency]"]'), 'Batangas State University', true);
    await fillValue(form.locator('input[name="people[0][email]"]'), proposal.leaderEmail, true);
    await fillValue(form.locator('input[name="people[0][cellphone]"]'), proposal.leaderContact, true);
    await fillVisibleRequiredFields(form);
    await waitForAutosave(page);
}

async function fillVisibleRequiredFields(form) {
    const fields = form.locator('input[required]:visible, textarea[required]:visible, select[required]:visible');

    for (let index = 0; index < await fields.count(); index += 1) {
        const field = fields.nth(index);
        if (await field.isDisabled()) continue;

        const tagName = await field.evaluate((element) => element.tagName.toLowerCase());
        const type = (await field.getAttribute('type')) ?? '';
        const name = (await field.getAttribute('name')) ?? '';

        if (['checkbox', 'radio', 'file', 'hidden'].includes(type)) continue;

        if (tagName === 'select') {
            if (!await field.inputValue()) await selectFirstOption(field);
            continue;
        }

        if (await field.inputValue()) continue;

        let value = 'Demo proposal information for presentation and workflow validation.';
        if (type === 'email' || name.includes('email')) value = proposal.leaderEmail;
        if (type === 'tel' || name.includes('contact') || name.includes('cellphone')) value = proposal.leaderContact;
        if (type === 'number' && name.includes('percentage')) value = '100';
        if (type === 'number' && !name.includes('percentage')) value = '1';
        if (name.endsWith('[name]') || name.includes('project_leader')) value = proposal.leaderName;
        if (name.includes('first_name')) value = proposal.leaderName.split(' ')[0];
        if (name.includes('last_name')) value = proposal.leaderName.split(' ').at(-1);
        await field.fill(value);
    }
}

async function fillValue(locator, value, preserveExisting = true) {
    if (await locator.count() === 0 || !await locator.first().isVisible()) return;

    const field = locator.first();
    if (preserveExisting && await field.inputValue()) return;
    await field.fill(value);
}

async function selectFirstOption(locator) {
    if (await locator.count() === 0 || !await locator.first().isVisible()) return;

    const select = locator.first();
    if (await select.inputValue()) return;

    const options = await select.locator('option:not([disabled])').evaluateAll((elements) => elements
        .map((option) => ({ value: option.value, label: option.textContent?.trim() ?? '' }))
        .filter((option) => option.value && !/^(leave blank|select|choose)/i.test(option.label)));

    if (options[0]) await select.selectOption(options[0].value);
}

async function setDateValue(page, name, value) {
    await page.locator(`input[name="${name}"]`).evaluate((element, dateValue) => {
        if (element.value) return;
        element.value = dateValue;
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);
}

async function waitForAutosave(page) {
    const message = page.locator('[data-proposal-autosave-message]');
    if (await message.count() === 0) return;

    await page.waitForTimeout(2_000);
    if ((await message.textContent())?.trim() === 'Changes save automatically.') return;

    await expect(message).toHaveText(/^(Draft saved|Saved) just now\.$/, { timeout: 20_000 });
}