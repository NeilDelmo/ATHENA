<?php

use App\Models\ProposalDraft;
use App\Models\User;
use App\Support\DetailedProposalRules;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

test('the unified RRL preserves the combined capacity of legacy opening and literature fields', function () {
    $maximum = (int) config('detailed_proposal.maximum_narrative_length') * 2 + 2;
    $rules = ['related_literature' => DetailedProposalRules::rules(allowDraft: true)['related_literature']];

    expect(Validator::make(['related_literature' => str_repeat('a', $maximum)], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['related_literature' => str_repeat('a', $maximum + 1)], $rules)->fails())->toBeTrue();
});

test('proposal pages mark required fields immediately and keep optional fields unmarked', function (string $paper, array $required, array $optional) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create(['name' => 'Alexandra Reyes', 'college' => 'CICS']);
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id, 'project_title' => 'Required field guidance',
        'project_leader' => $faculty->name, 'duration_months' => 3,
        'planned_start' => now()->addMonth()->toDateString(), 'planned_end' => now()->addMonths(4)->toDateString(),
    ]);

    $url = match ($paper) {
        'create' => route('faculty.proposal-drafts.create'),
        'show' => route('faculty.proposal-drafts.show', $draft),
        default => route('faculty.proposal-drafts.'.$paper.'.edit', $draft),
    };
    $response = $this->actingAs($faculty)->get($url)->assertOk();
    if ($paper === 'detailed-proposal' && getenv('ATHENA_EXPORT_REQUIRED_FIELDS') === '1') {
        file_put_contents(storage_path('framework/testing/proposal-required-detailed-proposal.html'), $response->getContent());
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-proposal-required-legend]')->length)->toBe(1);
    if ($paper === 'detailed-proposal') {
        expect($xpath->query('//textarea[@name="introduction"]')->length)->toBe(0)
            ->and($xpath->query('//section[@data-revision-section="section-literature"]//textarea[@name="related_literature"]')->length)->toBe(1)
            ->and($xpath->query('//*[@id="proposal-sources-heading"]')->length)->toBe(0);
    }
    foreach ($required as $selector) {
        $labels = $xpath->query('//'.$selector);
        expect($labels->length)->toBeGreaterThan(0, $paper.' required label: '.$selector);
        foreach ($labels as $label) {
            expect($xpath->query('.//*[@data-required-mark]', $label)->length)->toBe(1, $paper.' required label: '.$selector);
        }
    }
    foreach ($optional as $selector) {
        $labels = $xpath->query('//'.$selector);
        expect($labels->length)->toBeGreaterThan(0, $paper.' optional label: '.$selector);
        foreach ($labels as $label) {
            expect($xpath->query('.//*[@data-required-mark]', $label)->length)->toBe(0, $paper.' optional label: '.$selector);
        }
    }
})->with([
    'New Proposal' => ['create', ['label[@for="project_title"]'], []],
    'Proposal workspace' => ['show', [
        'label[@for="project_title"]', 'label[@for="duration_months"]', 'label[@for="project_leader"]',
        'label[@for="planned_start"]', 'label[@for="planned_end"]',
    ], []],
    'Project Details' => ['details', [
        'label[@for="project_title"]', 'label[@for="duration_months"]', 'label[@for="project_leader"]',
        'label[@for="planned_start"]', 'label[@for="planned_end"]',
    ], []],
    'Detailed Proposal' => ['detailed-proposal', [
        'label[@for="research-agenda"]', 'legend[contains(., "Check all applicable SDG")]',
        'label[@for="leader-name"]', 'label[@for="leader-email"]', 'label[@for="leader-contact"]',
        'label[starts-with(normalize-space(.), "Project Staff (s):")]',
        'label[starts-with(normalize-space(.), "Email Address")]',
        'label[starts-with(normalize-space(.), "Contact Number")]',
        'label[@for="proponent-college"]', 'label[@for="proponent-campus"]',
        'label[@for="executive-brief"]', 'label[@for="rationale"]', 'label[@for="related-literature"]',
        'label[@for="methodology-research_design"]', 'label[@for="references"]',
        'section[@id="expected-outputs"]//h3',
        'label[span[contains(@x-text, "Specific objective")]]',
        'section[@id="expected-outputs"]//label',
        'label[starts-with(normalize-space(.), "Method heading")]',
        'label[starts-with(normalize-space(.), "Method ")]',
        'label[starts-with(normalize-space(.), "Member name")]',
        'label[starts-with(normalize-space(.), "Responsibility %")]',
        'label[starts-with(normalize-space(.), "Duties and responsibilities")]',
    ], [
        'label[@for="leader-title"]', 'label[@for="proponent-department"]',
        'label[@for="general-objective"]', 'label[@for="methodology-data_analysis"]',
    ]],
    'Work Plan' => ['work-plan', [
        'label[starts-with(normalize-space(.), "Objective from Detailed Proposal")]',
        'label[starts-with(normalize-space(.), "Expected Output")]',
        'label[starts-with(normalize-space(.), "Activities or Workplan")]',
        'legend[starts-with(normalize-space(.), "Gantt Schedule")]',
    ], []],
    'Line-Item Budget' => ['line-item-budget', [
        'dt[starts-with(normalize-space(.), "Project Title")]',
        'dt[starts-with(normalize-space(.), "Project Leader")]',
        'dt[starts-with(normalize-space(.), "Duration on paper")]',
    ], ['label[@for="leader-campus"]', 'label[@for="leader-college"]']],
    'Expense Breakdown' => ['expense-breakdown', [
        'label[starts-with(normalize-space(.), "Expense type")]', 'label[starts-with(normalize-space(.), "Account")]',
        'label[starts-with(normalize-space(.), "Sub-account")]', 'label[starts-with(normalize-space(.), "Particular/s")]',
        'label[starts-with(normalize-space(.), "Unit Cost")]', 'label[starts-with(normalize-space(.), "Purpose in the project")]',
        'label[starts-with(normalize-space(.), "Unit ")]', 'label[starts-with(normalize-space(.), "Qty.")]',
        'label[starts-with(normalize-space(.), "Descriptions / Specifications / Details")]',
        'label[starts-with(normalize-space(.), "Contingency amount")]',
    ], []],
    'Curriculum Vitae' => ['curriculum-vitae', [
        'label[starts-with(normalize-space(.), "Last Name")]', 'label[starts-with(normalize-space(.), "First Name")]',
    ], ['label[starts-with(normalize-space(.), "Middle Name")]', 'label[starts-with(normalize-space(.), "Email Address")]']],
]);
