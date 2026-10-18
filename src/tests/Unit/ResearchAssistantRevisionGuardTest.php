<?php

use App\Support\ResearchAssistantRevisionGuard;

test('revision guard rejects changed or removed factual figures in the target text', function (string $before, string $after) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after))->toBeFalse();
})->with([
    ['Survey 12 sites in 2026.', 'Survey the sites.'],
    ['Survey 12 sites.', 'Survey 30 sites.'],
    ['Use 12 samples and 12 controls.', 'Use 12 samples and controls.'],
    ['The change was -12.5%.', 'The change was 12.5%.'],
    ['The change was -12.5%.', 'The change was +12.5%.'],
    ['The change was -12.5%.', 'The change was -12.5.'],
    ['The allocation is PHP 1,200.', 'The allocation is USD 1,200.'],
    ['The allocation is ₱1,200.', 'The allocation is 1,200.'],
    ['The allocation is -₱1,200.', 'The allocation is ₱1,200.'],
    ['Collect 12 kg of material.', 'Collect 12 g of material.'],
    ['Collect 12 liters of material.', 'Collect 12 gallons of material.'],
    ['Collect twelve kg of material.', 'Collect twelve g of material.'],
    ['The change was minus twelve percent.', 'The change was twelve percent.'],
    ['Use a 1/2 dose.', 'Use a 2/1 dose.'],
    ['The concentration was 1e-3 mol.', 'The concentration was 2e-3 mol.'],
    ['Collect twelve samples.', 'Collect samples.'],
]);

test('revision guard does not borrow figures from connected saved context', function () {
    expect(ResearchAssistantRevisionGuard::preserves('Survey 12 sites.', 'Survey 30 sites.', ['objectives' => 'Survey 30 sites.']))->toBeFalse();
});

test('revision guard preserves complete date sequences rather than sorted date fragments', function (string $before, string $after) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after))->toBeFalse();
})->with([
    ['The visit is on 10/11/2026.', 'The visit is on 11/10/2026.'],
    ['The visit is on 2026-10-11.', 'The visit is on 2026-11-10.'],
    ['Visits occur on May 3 and June 4.', 'Visits occur on May 4 and June 3.'],
    ['The visit is on 3 May 2026.', 'The visit is on 3 June 2026.'],
    ['The visit is on May third.', 'The visit is on June third.'],
    ['The visit is in May.', 'The visit is in June.'],
    ['The visit is on Monday.', 'The visit is on Tuesday.'],
]);

test('revision guard preserves citation identities and source links', function (string $before, string $after) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after))->toBeFalse();
})->with([
    ['Monitoring is useful [1].', 'Monitoring is useful.'],
    ['Monitoring is useful (Santos, 2024).', 'Monitoring is useful (Cruz, 2024).'],
    ['Santos (2024) describes monitoring.', 'Cruz (2024) describes monitoring.'],
    ['Monitoring is useful [[ATHENA_CITATION_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa]].', 'Monitoring is useful [[ATHENA_CITATION_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb]].'],
    ['Read https://example.org/paper-a for details.', 'Read https://example.org/paper-b for details.'],
]);

test('revision guard preserves proper names and acronyms in narrative text', function (string $before, string $after) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after))->toBeFalse();
})->with([
    ['Dr. Maria Santos coordinates monitoring.', 'Dr. Juan Cruz coordinates monitoring.'],
    ['Maria Santos coordinates monitoring.', 'The researcher coordinates monitoring.'],
    ['The researcher coordinates monitoring.', 'Maria Santos coordinates monitoring.'],
    ['Monitoring uses DOST guidance.', 'Monitoring uses CHED guidance.'],
    ['Monitoring uses DOST guidance.', 'Monitoring uses guidance.'],
    ['Santos found a monitoring gap.', 'Cruz found a monitoring gap.'],
    ['Santos reported monitoring findings.', 'The study reported monitoring findings.'],
    ['Monitoring follows Santos et al. guidance.', 'Monitoring follows Cruz et al. guidance.'],
]);

test('revision guard preserves authoritative names without freezing unrelated context', function (array $context, string $before, string $after, bool $expected) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after, $context))->toBe($expected);
})->with([
    [['project_leader' => 'santos'], 'santos coordinates monitoring.', 'cruz coordinates monitoring.', false],
    [['terminal_data' => ['authors' => [['name' => 'santos', 'role' => 'Project Leader']]]], 'santos coordinates monitoring.', 'The researcher coordinates monitoring.', false],
    [['signatories' => ['approved_by' => ['name' => 'santos']]], 'Monitoring involves santos.', 'Monitoring involves cruz.', false],
    [['people' => ['santos']], 'Monitoring involves santos.', 'Monitoring involves cruz.', false],
    [['project_leader' => 'maria santos'], 'santos coordinates monitoring.', 'cruz coordinates monitoring.', false],
    [['project_leader' => 'Maria Santos'], 'santos coordinates monitoring.', 'cruz coordinates monitoring.', false],
    [['project_leader' => 'Santos'], 'Santos coordinates monitoring.', 'santos coordinates monitoring.', false],
    [['project_leader' => 'santos'], 'Monitoring describes coastal conditions.', 'Coastal conditions are described through monitoring.', true],
]);

test('revision guard keeps approval and signature assertions intact', function (string $before, string $after) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after))->toBeFalse();
})->with([
    ['The proposal is approved.', 'The proposal is not approved.'],
    ['The proposal is pending approval.', 'The proposal has approval.'],
    ['The form was signed by Dr. Maria Santos.', 'The form was not signed by Dr. Maria Santos.'],
    ['The proposal is approved.', 'The proposal was approved.'],
    ['The report describes monitoring.', 'The report is approved.'],
    ['The proposal is approved. The report describes monitoring.', 'The report describes monitoring.'],
    ['The form was signed by Dr. Maria Santos. Monitoring describes observations.', 'The form was signed by Dr. Juan Santos. Monitoring describes observations.'],
]);

test('revision guard allows wording edits with unchanged protected facts', function (string $before, string $after, array $context = []) {
    expect(ResearchAssistantRevisionGuard::preserves($before, $after, $context))->toBeTrue();
})->with([
    ['Monitoring describes coastal conditions.', 'Coastal conditions are described through monitoring.'],
    ['Assess community monitoring needs.', 'Assess the needs for community monitoring.'],
    ['Collect 12 kg of material on May 3, 2026.', 'On May 3, 2026, collect 12 kg of material.'],
    ['Dr. Maria Santos records the -12.5% change.', 'The -12.5% change is recorded by Dr. Maria Santos.'],
    ['Monitoring follows DOST guidance [1].', 'DOST guidance informs monitoring [1].'],
    ['The proposal is approved. Monitoring describes coastal conditions.', 'The proposal is approved. Coastal conditions are described through monitoring.'],
    ['santos records monitoring observations.', 'Monitoring observations are recorded by santos.', ['authors' => [['name' => 'santos']]]],
    ['Monitoring is useful [[ATHENA_CITATION_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa]].', 'Monitoring supports observation [[ATHENA_CITATION_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa]].'],
    ['Dr. Maria J. Santos describes monitoring. The proposal is approved.', 'Dr. Maria J. Santos describes coastal monitoring. The proposal is approved.'],
    ['Santos found a monitoring gap.', 'A monitoring gap was found by Santos.'],
    ['Santos et al. describe monitoring.', 'Monitoring is described by Santos et al.'],
    ['The research manual describes monitoring.', 'Monitoring is described in the research manual.', ['project_leader' => 'Dr. Maria Santos']],
    ["Regional Evaluation\nSite Plan", "Site Plan\nRegional Evaluation"],
]);
