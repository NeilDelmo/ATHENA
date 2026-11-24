import { expect, test } from '@playwright/test';
import { writeFile } from 'node:fs/promises';
import {
    acceptReport, archiveContents, asActor, confirmAction, fillWritingField, fixtureAction, fixtureFile, fixtures,
    forwardReport, headDecision, selectCalendarDate, submitDraft,
} from './lifecycle-helpers.mjs';

test('AUTH-ROLES: each test identity enters its own workspace', async ({ browser }, testInfo) => {
    for (const actor of ['faculty', 'head', 'secretary', 'coordinator', 'vcrdes', 'outsider']) {
        await asActor(browser, actor, testInfo, async page => {
            await page.goto('dashboard');
            await expect(page.locator('body')).toHaveAttribute('data-auth-user-id', String(fixtures().users[actor].id));
            await expect(page.locator('body')).toHaveAttribute('data-active-workspace', fixtures().users[actor].workspace);
        });
    }
});

test('ACCESS-OWNER: another faculty member cannot read or change the proposal', async ({ browser }, testInfo) => {
    await asActor(browser, 'outsider', testInfo, async page => {
        const response = await page.goto(`faculty/proposal-drafts/${fixtures().drafts.main.id}/details`);
        expect(response.status()).toBe(403);
        await expect(page.getByText(fixtures().drafts.main.title, { exact: true })).toHaveCount(0);
    });
});

test('DRAFT-SAVE: faculty creates a draft and saved details survive a reload', async ({ browser }, testInfo) => {
    await asActor(browser, 'faculty', testInfo, async page => {
        await page.goto('faculty/proposal-drafts/create');
        await page.locator('#project_title').fill('Capstone E2E Browser Created Draft');
        await page.getByRole('button', { name: /Create draft and continue/i }).click();
        await expect(page).toHaveURL(/proposal-drafts\/\d+/);
        const id = new URL(page.url()).pathname.match(/proposal-drafts\/(\d+)/)[1];
        await page.goto(`faculty/proposal-drafts/${id}/details`);
        await page.locator('#duration_months').fill('3');
        await selectCalendarDate(page, 'planned_start', '2026-01-05');
        await expect(page.locator('[data-proposal-autosave-message]')).toHaveText('Saved just now.');
        await page.reload();
        await expect(page.locator('#project_title')).toHaveValue('Capstone E2E Browser Created Draft');
        await expect(page.locator('#duration_months')).toHaveValue('3');
    });
});

test('REJECT: Head rejects a separate submitted proposal with a recorded reason', async ({ browser }, testInfo) => {
    let topic;
    await asActor(browser, 'faculty', testInfo, async page => {
        topic = await submitDraft(page, fixtures().drafts.rejection);
    });
    const reason = 'This synthetic proposal falls outside the scope of the research call.';
    await asActor(browser, 'head', testInfo, async page => {
        await page.goto(`topics/${topic}`);
        await page.locator('#proposal-review-tab-button').click();
        const form = page.locator('#research-head-decision-form');
        await form.locator('[name="status"][value="rejected"]').check();
        await form.locator('[name="rejection_reason"]').fill(reason);
        await form.locator('[name="rejection_confirmed"]').check();
        await form.getByRole('button', { name: 'Reject proposal', exact: true }).click();
        await confirmAction(page, 'Reject proposal');
        await expect.poll(() => fixtureAction('inspect', topic).status).toBe('rejected');
    });
    await asActor(browser, 'faculty', testInfo, async page => {
        await page.goto(`topics/${topic}`);
        await page.locator('#proposal-review-tab-button').click();
        await expect(page.getByText(reason, { exact: true }).first()).toBeVisible();
    });
});

test('REVISE: Head highlights a paper and faculty submits a corrected version', async ({ browser }, testInfo) => {
    test.setTimeout(300_000);
    let topic;
    await asActor(browser, 'faculty', testInfo, async page => {
        topic = await submitDraft(page, fixtures().drafts.revision);
    });
    const feedback = 'Clarify the research agenda to include community coastal resilience.';
    const agenda = 'Community coastal resilience and evidence-based habitat restoration';
    await asActor(browser, 'head', testInfo, async page => {
        await page.goto(`topics/${topic}?decision=revision_requested#proposal-review`);
        await page.locator('#proposal-review-tab-button').click();
        await page.getByRole('link', { name: 'Review Detailed Research Proposal', exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`/topics/${topic}/versions/\\d+/files/\\d+/annotations`));
        const paper = page.locator('.pdf-annotation-page[data-page-number="1"]');
        await expect(paper).toBeVisible({ timeout: 60_000 });
        await paper.scrollIntoViewIfNeeded();
        const area = await paper.boundingBox();
        expect(area).not.toBeNull();
        const markY = area.y + area.height * 0.155;
        await page.mouse.move(area.x + area.width * 0.15, markY);
        await page.mouse.down();
        await page.mouse.move(area.x + area.width * 0.8, markY + 12, { steps: 5 });
        await page.mouse.up();
        const commentInput = page.locator('textarea[x-ref="commentInput"]');
        await expect(commentInput).toBeVisible();
        await commentInput.fill(feedback);
        const annotationPromise = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/annotations'));
        await page.getByRole('button', { name: 'Add comment', exact: true }).click();
        expect((await annotationPromise).status()).toBe(201);
        await expect(page.locator('[data-comment-id]').getByText(feedback, { exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'Back to review', exact: true }).click();
        const decision = page.locator('#research-head-decision-form');
        await decision.locator('[name="status"][value="revision_requested"]').check();
        await decision.getByRole('button', { name: 'Send revision request', exact: true }).click();
        await expect.poll(() => fixtureAction('inspect', topic).status).toBe('revision_requested');
    });
    await asActor(browser, 'faculty', testInfo, async page => {
        await page.goto(`faculty/topics/${topic}/revision`);
        const revisionForm = page.locator('#submit-revision');
        await expect(revisionForm).toHaveAttribute('data-revision-tools-state', 'ready', { timeout: 30_000 });
        await expect(revisionForm).toHaveAttribute('data-revision-initialized', 'true');
        await expect(page.locator('#revision-feedback')).toContainText('1 comment');
        await page.getByRole('button', { name: 'Continue to revise and respond', exact: true }).click();
        await expect(page.locator('[data-revision-progress]')).toHaveText(/Step 2 of 4.*Revise and respond/);
        const card = page.locator('[data-revision-document="detailed_proposal"]');
        await card.locator('[data-revision-open]').click();
        const feedbackToggle = card.locator('[data-revision-feedback-toggle]');
        if (await feedbackToggle.getAttribute('aria-expanded') !== 'true') await feedbackToggle.click();
        await expect(card.getByText(feedback, { exact: true })).toBeVisible();
        const editor = card.frameLocator('[data-revision-editor-frame]');
        await expect(editor.locator('[data-revision-editor-context][data-revision-editor-ready="true"]')).toBeAttached({ timeout: 60_000 });
        await editor.locator('[name="specific_method_objectives[0][heading]"]').fill('Field data collection');
        await editor.locator('#research-agenda').fill(agenda);
        await expect(editor.locator('[data-proposal-autosave-message]')).toHaveText('Saved just now.');
        await card.locator('[data-revision-response-key] textarea').fill('Updated the research agenda to explicitly include community coastal resilience.');
        await card.locator('[data-revision-close]').click();
        const location = card.locator('[data-comment-response-location]');
        await expect.poll(() => location.getAttribute('data-location-state'), { timeout: 60_000 }).toMatch(/detected|confirm|error/);
        if (await location.getAttribute('data-location-state') === 'confirm') {
            await location.locator('[data-location-candidate]').first().click();
        }
        if (await location.getAttribute('data-location-state') === 'error') {
            console.log('Revision PDF diagnostics', JSON.stringify(await editor.locator('body').evaluate(body => {
                const root = body.querySelector('[data-paper-editor]');
                const state = window.Alpine?.$data(root);
                const form = root?.querySelector('[data-paper-form]');
                const fields = [...(form?.elements || [])].filter(field => field.matches('input, textarea, select'));
                return {
                    valid: state?.validateForm({ focus: false }),
                    invalid: fields.filter(field => !field.disabled && !field.checkValidity()).map(field => field.name || field.id || field.type),
                    validationMessage: state?.validationMessage,
                    downloadError: state?.downloadError,
                    autoSaveMessage: form?.querySelector('[data-proposal-autosave-message]')?.textContent,
                };
            })));
            throw new Error(await location.locator('[data-location-status]').innerText());
        }
        await expect(location).toHaveAttribute('data-location-state', 'detected');
        await page.locator('[data-revision-step-continue]:visible').click();
        await page.locator('[data-revision-details-confirmed]').check();
        await page.locator('[data-revision-step-continue]:visible').click();
        await page.locator('[data-revision-submit-button]').click();
        await confirmAction(page, 'Submit revision');
        await expect(page).toHaveURL(new RegExp(`/topics/${topic}(?:[?#].*)?$`), { timeout: 150_000 });
        expect(fixtureAction('inspect', topic)).toMatchObject({
            status: 'resubmitted', version: 2, versionCount: 2, detailedProposal: agenda,
        });
    });
});

test.describe.serial('Proposal through archive', () => {
    let topic;
    let monitoring;
    let progress;
    let terminal;

    test('SUBMIT: prepare seven real PDFs and turn in the proposal', async ({ browser }, testInfo) => {
        await asActor(browser, 'faculty', testInfo, async page => {
            topic = await submitDraft(page, fixtures().drafts.main);
            await expect(page.getByText(fixtures().drafts.main.title, { exact: true }).first()).toBeVisible();
            expect(fixtureAction('inspect', topic)).toMatchObject({ status: 'pending', version: 1, files: 7, noticeIssued: false });
            expect(fixtureAction('inspect', topic)).toMatchObject({ userId: fixtures().users.faculty.id, researchCallId: fixtures().drafts.main.callId });
        });
    });

    test('REVIEW-GAD: Head records clearance and extracts the signed GAD score', async ({ browser }, testInfo) => {
        await asActor(browser, 'head', testInfo, async page => {
            await headDecision(page, topic, 'gad_review', 'research_head_clearance_confirmed', 'Clear for GAD assessment');
            expect(fixtureAction('inspect', topic).status).toBe('gad_review');
            const gad = page.locator(`#gad-assessment-replacement-${topic}`);
            await gad.locator('[name="review_file"]').setInputFiles(fixtureFile('gad.pdf'));
            await gad.locator('[name="gad_signature_confirmed"]').check();
            await gad.getByRole('button', { name: 'Upload & read score', exact: true }).click();
            await expect.poll(() => fixtureAction('inspect', topic).gad).toMatchObject({
                gad_score: 12.5, gad_outcome: 'passed', gad_score_entry_method: 'automatic', gad_signature_confirmed: true,
            });
        });
    });

    test('REVIEW-LREC: record co-evaluation, presentation and ethics clearance', async ({ browser }, testInfo) => {
        await asActor(browser, 'head', testInfo, async page => {
            await page.goto(`topics/${topic}`);
            await page.locator('#proposal-review-tab-button').click();
            const evaluation = page.locator(`#co-evaluator-upload-${topic}`);
            if (!await evaluation.locator('[name="recommended_action"][value="for_endorsement"]').isVisible()) {
                await page.locator('#co-evaluator-review > button').click();
            }
            await evaluation.locator('[name="recommended_action"][value="for_endorsement"]').check();
            await evaluation.locator('[name="review_file"]').setInputFiles(fixtureFile('evaluation.pdf'));
            await evaluation.getByRole('button', { name: 'Record evaluation', exact: true }).click();
            await expect.poll(() => fixtureAction('inspect', topic).evaluation).toMatchObject({
                narrative_evaluation: expect.stringContaining('The objectives and method are complete and suitable for endorsement.'),
                narrative_evaluation_entry_method: 'automatic',
            });
            await headDecision(page, topic, 'lrec_queued', 'initial_clearance_confirmed', 'For email to LREC');
            expect(fixtureAction('inspect', topic).status).toBe('lrec_queued');
            await page.locator('#proposal-review-tab-button').click();
            await page.getByRole('button', { name: 'Presentation complete — record outcome', exact: true }).click();
            expect(fixtureAction('inspect', topic).status).toBe('lrec_review');
            await headDecision(page, topic, 'ready_for_signature', 'lrec_clearance_confirmed', 'Proceed to signing');
            expect(fixtureAction('inspect', topic).status).toBe('ready_for_signature');
        });
    });

    test('SIGNING: secretary uploads and verifies the three signed proposal papers', async ({ browser }, testInfo) => {
        await asActor(browser, 'secretary', testInfo, async page => {
            await page.goto(`topics/${topic}`);
            await page.locator('#proposal-review-tab-button').click();
            for (const [label, file] of [
                ['Detailed Research Proposal', 'signed-detailed.pdf'],
                ['Attachment A: Work Plan', 'signed-work-plan.pdf'],
                ['Attachment B: Line-Item Budget', 'signed-budget.pdf'],
            ]) {
                const input = page.getByLabel(`Signed PDF for ${label}`, { exact: true });
                const row = input.locator('xpath=ancestor::*[@data-signing-document][1]');
                await input.setInputFiles(fixtureFile(file));
                await expect(row).toHaveAttribute('data-upload-state', 'uploaded', { timeout: 60_000 });
            }
            await page.reload();
            await expect(page.locator('[data-signing-document][data-upload-state="uploaded"]')).toHaveCount(3);
        });
    });

    test('NTP: save notice details, download the PDF and release the project', async ({ browser }, testInfo) => {
        await asActor(browser, 'secretary', testInfo, async page => {
            await page.goto(`topics/${topic}`);
            await page.locator('#notice-to-proceed-tab-button').click();
            const form = page.locator(`#notice-to-proceed-form-${topic}`);
            for (const [name, value] of Object.entries({
                'researcher_names[0]': fixtures().users.faculty.name,
                campus_line: 'ARASOF-Nasugbu', project_title: fixtures().drafts.main.title,
                resolution_number: 'E2E-2026-001', resolution_year: '2026',
                approved_duration_months: '3', approved_budget: '3600',
                issuing_officer_name: 'Capstone Test Chancellor', issuing_officer_title: 'Chancellor',
                issuing_officer_committee_role: 'Chairperson', verifying_officer_name: fixtures().users.head.name,
                verifying_officer_title: 'Research Head', verifying_officer_committee_role: 'Member',
            })) {
                const input = form.locator(`[name="${name}"]`);
                await input.fill(value);
            }
            for (const [id, field, date] of [
                ['notice-date', 'notice_date', '2026-01-01'],
                ['approved-start-date', 'approved_start_date', '2026-01-01'],
                ['approved-end-date', 'approved_end_date', '2026-03-31'],
            ]) {
                const input = form.locator(`[name="${field}"]`);
                if (await input.inputValue() !== date) await selectCalendarDate(page, id, date);
            }
            await page.getByRole('button', { name: 'Save notice details', exact: true }).click();
            await page.locator('#notice-to-proceed-tab-button').click();
            const downloadPromise = page.waitForEvent('download');
            await page.getByRole('link', { name: 'Download unsigned PDF', exact: true }).click();
            const download = await downloadPromise;
            expect(await download.failure()).toBeNull();
            expect(download.suggestedFilename()).toMatch(/\.pdf$/i);
            await page.locator('[name="signed_notice_to_proceed"]').setInputFiles(fixtureFile('signed-notice.pdf'));
            const release = page.waitForResponse(response => response.request().method() === 'POST'
                && response.url().endsWith(`/topics/${topic}/notice-to-proceed/signed`));
            await page.getByRole('button', { name: 'Release papers and Notice to Proceed', exact: true }).click();
            const response = await release;
            expect(response.status()).toBe(200);
            await expect(page.getByText('Signed papers and Notice to Proceed released together.', { exact: false })).toBeVisible();
            expect(fixtureAction('inspect', topic)).toMatchObject({ status: 'approved', projectStatus: 'ongoing', noticeIssued: true });
        });
    });

    test('MONITORING: upload accomplishment evidence, prepare and submit the monitoring PDF', async ({ browser }, testInfo) => {
        fixtureAction('clock', '2026-04-02 10:00:00');
        fixtureAction('reports', topic);
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`research/${topic}/monitoring-tool?reporting_date=2026-03-31`);
            await fillWritingField(page, 'work_plan[0][actual_accomplishment]', 'Completed the coastal habitat report and validated all findings.');
            await page.locator('[data-monitoring-activity] input[type="file"]').first().setInputFiles(fixtureFile('evidence.pdf'));
            await page.getByRole('button', { name: 'Prepare official PDF', exact: true }).click();
            const submit = page.getByRole('button', { name: 'Submit to Research Office Secretary', exact: true });
            await expect(submit).toBeEnabled({ timeout: 90_000 });
            await submit.click();
            const saved = fixtureAction('inspect', topic).monitoring;
            expect(saved).toHaveLength(1);
            expect(saved[0]).toMatchObject({ submission_status: 'submitted', review_status: 'pending', progress_percentage: 100 });
            expect(saved[0]).toMatchObject({ topic_id: topic, submitted_by: fixtures().users.faculty.id, reporting_quarter: 1 });
            expect(saved[0].official_pdf_size).toBeGreaterThan(500);
            monitoring = saved[0].id;
        }, 'faculty_researcher');
    });

    test('MONITORING-CORRECT: secretary returns the report and the leader resubmits a new version', async ({ browser }, testInfo) => {
        await asActor(browser, 'secretary', testInfo, async page => {
            await page.goto(`topics/${topic}#monitoring-tool-${monitoring}`);
            const form = page.locator('[data-secretary-report-review]').last();
            await form.locator('[name="decision"][value="revision_requested"]').check();
            await form.locator('[name="secretary_remarks"]').fill('Add the final validation date to the accomplishment.');
            await form.getByRole('button', { name: 'Return for corrections', exact: true }).click();
            expect(fixtureAction('inspect', topic).monitoring[0].review_status).toBe('revision_requested');
        });
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`research/${topic}/monitoring-tool?revise_monitoring_report=${monitoring}`);
            await fillWritingField(page, 'work_plan[0][actual_accomplishment]', 'Completed the report; final stakeholder validation took place on March 30, 2026.');
            await page.getByRole('button', { name: 'Prepare official PDF', exact: true }).click();
            const submit = page.getByRole('button', { name: 'Submit to Research Office Secretary', exact: true });
            await expect(submit).toBeEnabled({ timeout: 90_000 });
            await submit.click();
            const versions = fixtureAction('inspect', topic).monitoring;
            expect(versions).toHaveLength(2);
            const replacement = versions.find(report => report.supersedes_report_id === monitoring);
            expect(replacement).toMatchObject({ version_number: 2, submission_status: 'submitted', review_status: 'pending', progress_percentage: 100 });
            expect(replacement).toMatchObject({ topic_id: topic, submitted_by: fixtures().users.faculty.id, reporting_quarter: 1 });
            monitoring = replacement.id;
        }, 'faculty_researcher');
    });

    test('MONITORING-REVIEW: secretary forwards and Head accepts the corrected report', async ({ browser }, testInfo) => {
        await asActor(browser, 'secretary', testInfo, page => forwardReport(page, `topics/${topic}#monitoring-tool-${monitoring}`));
        expect(fixtureAction('inspect', topic).monitoring.find(report => report.id === monitoring).review_stage).toBe('head');
        await asActor(browser, 'head', testInfo, page => acceptReport(page, `topics/${topic}#monitoring-tool-${monitoring}`));
        expect(fixtureAction('inspect', topic).monitoring.find(report => report.id === monitoring).review_status).toBe('reviewed');
    });

    test('PROGRESS: submit the narrative PDF with evidence and complete both reviews', async ({ browser }, testInfo) => {
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`research/${topic}/progress-report?reporting_date=2026-03-31`);
            await fillWritingField(page, 'accomplishments[0][actual]', 'Completed one validated coastal habitat report during the first quarter.');
            await page.locator('input[type="file"][name^="accomplishment_evidence["]').first().setInputFiles(fixtureFile('evidence.pdf'));
            await page.getByRole('button', { name: 'Prepare official PDF', exact: true }).click();
            const submit = page.getByRole('button', { name: 'Submit to Research Office Secretary', exact: true });
            await expect(submit).toBeEnabled({ timeout: 90_000 });
            await submit.click();
            const report = fixtureAction('inspect', topic).narratives.find(item => item.report_type === 'progress');
            expect(report).toMatchObject({ submission_status: 'submitted', review_status: 'pending' });
            expect(report.official_pdf_size).toBeGreaterThan(500);
            progress = report.id;
        }, 'faculty_researcher');
        await asActor(browser, 'secretary', testInfo, page => forwardReport(page, `narrative-progress-reports/${progress}`));
        await asActor(browser, 'head', testInfo, page => acceptReport(page, `narrative-progress-reports/${progress}`));
        expect(fixtureAction('inspect', topic).narratives.find(report => report.id === progress).review_status).toBe('reviewed');
    });

    test('COORDINATOR: quarterly submission, Head correction and version-two resubmission', async ({ browser }, testInfo) => {
        const firstNarrative = 'The college completed and validated its coastal habitat project in the first quarter.';
        const correctedNarrative = `${firstNarrative} The final validation date was March 30, 2026.`;
        await asActor(browser, 'coordinator', testInfo, async page => {
            await page.goto('research-coordinator/reports?year=2026&quarter=1');
            await fillWritingField(page, 'other_accomplishments', firstNarrative);
            await page.getByRole('button', { name: 'Save draft', exact: true }).click();
            await page.reload();
            await expect(page.locator('[name="other_accomplishments"]')).toHaveValue(new RegExp('coastal habitat project'));
            await page.getByRole('button', { name: 'Submit quarterly report', exact: true }).click();
            await expect(page.getByRole('button', { name: 'Submit quarterly report', exact: true })).toHaveCount(0);
        });
        await asActor(browser, 'head', testInfo, async page => {
            await page.goto('research-head/quarterly-accomplishment-reports?year=2026&college=CICS&quarter=1');
            const previewPromise = page.waitForEvent('popup');
            await page.getByRole('link', { name: 'Preview report', exact: true }).first().click();
            const preview = await previewPromise;
            await expect(preview.locator('.narrative').last()).toContainText(firstNarrative);
            await preview.close();
            await page.getByText('Return for correction', { exact: true }).first().click();
            await page.locator('[name="reason"]').first().fill('Record the final validation date in the narrative.');
            await page.getByRole('button', { name: 'Return report for correction', exact: true }).first().click();
        });
        await asActor(browser, 'coordinator', testInfo, async page => {
            await page.goto('research-coordinator/reports?year=2026&quarter=1');
            await expect(page.getByText('Returned for correction', { exact: false }).first()).toBeVisible();
            await fillWritingField(page, 'other_accomplishments', correctedNarrative);
            await page.getByRole('button', { name: 'Resubmit quarterly report', exact: true }).click();
            await expect(page.getByRole('button', { name: 'Resubmit quarterly report', exact: true })).toHaveCount(0);
        });
        await asActor(browser, 'head', testInfo, async page => {
            await page.goto('research-head/quarterly-accomplishment-reports?year=2026&college=CICS&quarter=1');
            await expect(page.getByText(/Version 2|Version #2|v2/).first()).toBeVisible();
            const previewPromise = page.waitForEvent('popup');
            await page.getByRole('link', { name: 'Preview report', exact: true }).first().click();
            const preview = await previewPromise;
            await expect(preview.locator('.narrative').last()).toContainText(correctedNarrative);
            await preview.close();
        });
    });

    test('TERMINAL: prepare and submit the completed-project narrative', async ({ browser }, testInfo) => {
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`research/${topic}/progress-report?report_type=terminal`);
            await fillWritingField(page, 'results_discussion', 'All approved activities produced the validated coastal habitat report. The single planned output was achieved.');
            await page.getByRole('button', { name: 'Prepare official PDF', exact: true }).click();
            const submit = page.getByRole('button', { name: 'Submit to Research Office Secretary', exact: true });
            await expect(submit).toBeEnabled({ timeout: 90_000 });
            await submit.click();
            const report = fixtureAction('inspect', topic).narratives.find(item => item.report_type === 'terminal');
            expect(report).toMatchObject({ submission_status: 'submitted', review_status: 'pending' });
            terminal = report.id;
        }, 'faculty_researcher');
    });

    test('COMPLETE: accept the terminal report, record its signed PDF and complete the project', async ({ browser }, testInfo) => {
        await asActor(browser, 'secretary', testInfo, page => forwardReport(page, `narrative-progress-reports/${terminal}`));
        await asActor(browser, 'head', testInfo, async page => {
            await acceptReport(page, `narrative-progress-reports/${terminal}`);
            await page.locator('[name="signed_report"]').setInputFiles(fixtureFile('signed-terminal.pdf'));
            await page.getByRole('button', { name: 'Record signed PDF', exact: true }).click();
            await page.locator('[data-terminal-project-completion]').getByRole('button', { name: 'Mark project completed', exact: true }).click();
            await confirmAction(page, 'Mark completed');
            await expect.poll(() => fixtureAction('inspect', topic)).toMatchObject({ status: 'approved', projectStatus: 'completed' });
        });
    });

    test('ARCHIVE: download a real archive containing the proposal, evidence and signed terminal report', async ({ browser }, testInfo) => {
        test.setTimeout(240_000);
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`topics/${topic}`);
            await expect(page.getByText('Completed - archived', { exact: false }).first()).toBeVisible();
            await expect(page.locator('[data-project-archive]')).toHaveCount(0);
            const archiveUrl = `topics/${topic}/archive`;
            let response = await page.request.get(archiveUrl);
            if (response.status() === 429) {
                const retryAfter = Number(response.headers()['retry-after']);
                expect(retryAfter).toBeGreaterThan(0);
                expect(retryAfter).toBeLessThanOrEqual(60);
                await testInfo.attach('archive-throttle', { body: `HTTP 429; retry after ${retryAfter} seconds.`, contentType: 'text/plain' });
                await page.waitForTimeout((retryAfter + 1) * 1000);
                response = await page.request.get(archiveUrl);
            }
            await expect(response).toBeOK();
            expect(response.headers()['content-disposition']).toMatch(/^attachment;.*\.zip/);
            const archive = testInfo.outputPath('project-archive.zip');
            await writeFile(archive, await response.body());
            await testInfo.attach('completed-project-archive', { path: archive, contentType: 'application/zip' });
            const contents = archiveContents(archive);
            expect(contents.names).toContain('INDEX.txt');
            expect(contents.names).toContain('manifest.json');
            expect(contents.names.some(name => name.startsWith('01-proposal-papers/'))).toBe(true);
            expect(contents.names.some(name => /terminal/i.test(name))).toBe(true);
            expect(contents.names.some(name => /evidence/i.test(name))).toBe(true);
            expect(contents.manifest).toBeTruthy();
        }, 'faculty_researcher');
        await asActor(browser, 'head', testInfo, async page => {
            await page.goto('research-head/completed-projects?view=monitoring');
            await expect(page.getByText(fixtures().drafts.main.title, { exact: true }).first()).toBeVisible();
        });
    });

    test('OUTCOMES: save post-implementation labels without changing completed status', async ({ browser }, testInfo) => {
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`research-outcomes/projects/${topic}`);
            for (const label of ['publication', 'presentation', 'utilization']) {
                await page.locator(`[name="labels[]"][value="${label}"]`).check();
            }
            await page.getByRole('button', { name: 'Save labels', exact: true }).click();
            await page.reload();
            for (const label of ['publication', 'presentation', 'utilization']) {
                await expect(page.locator(`[name="labels[]"][value="${label}"]`)).toBeChecked();
            }
            expect(fixtureAction('inspect', topic).projectStatus).toBe('completed');
        }, 'faculty_researcher');
        await asActor(browser, 'coordinator', testInfo, async page => {
            await page.goto(`research-outcomes/projects/${topic}`);
            await expect(page.getByRole('button', { name: 'Save labels', exact: true })).toHaveCount(0);
            await expect(page.getByText('Utilization', { exact: true }).first()).toBeVisible();
        });
    });

    test('JOURNAL-STAGES: track a manuscript through submission, revision, acceptance and publication', async ({ browser }, testInfo) => {
        await asActor(browser, 'faculty', testInfo, async page => {
            await page.goto(`research/${topic}/dissemination`);
            await page.getByText('Add a journal', { exact: true }).click();
            await page.locator('#journal-entry-new-name').fill('Capstone Test Journal');
            await page.getByRole('button', { name: 'Add journal to tracker', exact: true }).click();
            for (const status of ['submitted', 'under_review', 'revision_requested', 'accepted', 'published']) {
                await page.getByText('Update stage or details', { exact: true }).first().click();
                const form = page.locator('form').filter({ has: page.locator('select[name="status"]') }).filter({ has: page.getByRole('button', { name: 'Save changes', exact: true }) }).first();
                await form.locator('[name="status"]').selectOption(status);
                await form.locator('[name="submitted_on"]').fill('2026-03-30');
                if (['accepted', 'published'].includes(status)) await form.locator('[name="accepted_on"]').fill('2026-03-31');
                if (status === 'published') {
                    await form.locator('[name="published_on"]').fill('2026-04-01');
                    await form.locator('[name="publication_url"]').fill('https://example.org/capstone-test-publication');
                }
                await form.getByRole('button', { name: 'Save changes', exact: true }).click();
                await page.reload();
                await expect(page.locator('select[name="status"]').filter({ has: page.locator(`option[value="${status}"]:checked`) }).first()).toHaveValue(status);
            }
            await expect(page.getByRole('link', { name: /Read published article/ })).toHaveAttribute('href', 'https://example.org/capstone-test-publication');
            expect(fixtureAction('inspect', topic).projectStatus).toBe('completed');
        }, 'faculty_researcher');
    });
});
