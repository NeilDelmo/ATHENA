import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const view = path => readFileSync(new URL(`../../resources/views/${path}`, import.meta.url), 'utf8');

test('college report assistant stays inside the saved report form and respects submitted reports', () => {
    const source = view('research_coordinator/reports.blade.php');
    const form = source.slice(source.indexOf('<form id="quarterly-report-form"'), source.indexOf('</form>', source.indexOf('<form id="quarterly-report-form"')));

    assert.match(form, /data-assistant-report-type="college-quarterly"/);
    assert.match(form, /data-assistant-report-key="\{\{ \$report \? 'college-quarterly:'\.\$report->id : '' \}\}"/);
    assert.match(form, /data-assistant-report-save-mode="manual"/);
    assert.match(form, /<x-assistant-report-editor[^>]*:context="\['report_type' => 'college-quarterly', 'report_id' => \$report\?->id\]"[^>]*:editable="! \$submitted"/);
    assert.match(form, /name="other_accomplishments"/);
    assert.match(form, /<x-college-quarterly-report-ip-assets/);
    assert.doesNotMatch(form, /@assistant.*(?:submit|requestSubmit)/);
});

test('RMS assistant uses its own saved report identity without changing save and export actions', () => {
    const source = view('research_head/rms-quarterly-reports/index.blade.php');
    const opening = source.indexOf('<form method="POST"');
    const form = source.slice(opening, source.indexOf('</form>', opening));

    assert.match(form, /data-assistant-report-type="rms-quarterly"/);
    assert.match(form, /data-assistant-report-key="\{\{ \$report \? 'rms-quarterly:'\.\$report->id : '' \}\}"/);
    assert.match(form, /data-assistant-report-save-mode="manual"/);
    assert.match(form, /<x-assistant-report-editor[^>]*:context="\['report_type' => 'rms-quarterly', 'report_id' => \$report\?->id\]"/);
    assert.match(form, /name="intent" value="save"/);
    assert.match(form, /name="intent" value="download"/);
    assert.match(form, /name="row_details\[\{\{ \$key \}\}\]\[\{\{ \$field \}\}\]"/);
});

test('saved college evidence captions use stable path tokens while submitted and faculty evidence stay read only', () => {
    const report = view('research_coordinator/reports.blade.php');
    const rowProofs = view('components/college-quarterly-report-activities.blade.php');

    assert.match(report, /'caption' => old\('saved_evidence_captions\.'\.hash\('sha256', \$image\['path'\]\), \$image\['caption'\] \?\? ''\)/);
    const editableEvidence = report.slice(report.indexOf("@if (! $submitted && ($evidence['source'] ?? null) !== 'faculty_report')"));
    assert.match(editableEvidence.split('@endif')[0], /<textarea[^>]+name="saved_evidence_captions\[\{\{ \$evidenceCaptionToken \}\}\]"[^>]+maxlength="1000"/);
    assert.match(editableEvidence.split('@endif')[0], /hash\('sha256', \$evidence\['path'\]\)/);
    assert.match(rowProofs, /<textarea[^>]+:name="`saved_evidence_captions\[\$\{proof\.token\}\]`"[^>]+x-model="proof\.caption"[^>]+maxlength="1000"/);
    assert.match(report, /@else\s+@foreach \(\['presentations'/);
});
