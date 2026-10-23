<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersionFile;
use App\Support\ProposalDraftReadiness;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->draft = new ProposalDraft([
        'project_title' => 'Community Research',
        'project_leader' => 'Faculty Researcher',
        'duration_months' => 12,
        'planned_start' => '2026-10-01',
        'planned_end' => '2027-09-30',
    ]);
    $this->draft->setRelation('documents', collect());
    $this->draft->setRelation('researchCall', null);
    $this->readiness = app(ProposalDraftReadiness::class);
});

test('submission blockers identify exact project details without repeating automatic paper dependencies', function () {
    $this->draft->project_leader = '';
    $this->draft->planned_end = '2026-09-01';
    $blockers = collect($this->readiness->blockers($this->draft));

    expect($blockers->where('paper', 'details')->pluck('field')->all())
        ->toEqualCanonicalizing(['project_leader', 'planned_end'])
        ->and($blockers->whereIn('paper', ['gad-checklist', 'initial-screening-form']))->toBeEmpty()
        ->and($blockers->where('paper', 'details')->every('owner', 'faculty'))->toBeTrue()
        ->and($this->readiness->errors($this->draft))->toHaveKey('project_details');
});

test('detailed proposal blockers reuse completion validation with fields that can be focused', function () {
    $this->draft->documents->push(new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'source_data' => ['rationale' => '<p><br></p>'],
    ]));
    $blockers = collect($this->readiness->blockers($this->draft))->where('paper', 'detailed-proposal');

    expect($blockers->firstWhere('field', 'rationale'))
        ->toMatchArray(['key' => 'papers.detailed-proposal', 'owner' => 'faculty', 'reason' => 'validation', 'message' => 'Provide text for rationale.'])
        ->and($blockers->firstWhere('field', 'methodology.research_design')['message'])->toContain('research design')
        ->and($blockers->firstWhere('field', 'specific_objectives')['message'])->toContain('Specific Objective')
        ->and($this->readiness->errors($this->draft))->toHaveKey('papers.detailed-proposal');
});

test('work plan blockers identify the new objective row that needs activities outputs and months', function () {
    $this->draft->documents->push(new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'source_data' => ['specific_objectives' => [['description' => 'Survey the community'], ['description' => 'Evaluate outcomes']]],
    ]), new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'completed_at' => now(),
        'source_data' => ['entries' => [[
            'objective' => 'Survey the community', 'expected_output' => 'Baseline data',
            'activity' => 'Collect survey responses', 'months' => [1],
        ]]],
    ]));
    $blockers = collect($this->readiness->blockers($this->draft))->where('paper', 'work-plan');

    expect($blockers->pluck('field')->all())->toEqualCanonicalizing(['entries.1.expected_output', 'entries.1.activity', 'entries.1.months'])
        ->and($blockers->firstWhere('field', 'entries.1.activity')['message'])->toContain('Objective 2');
});

test('a missing work plan objective guides the researcher to its authoritative proposal field', function () {
    $blocker = collect($this->readiness->blockers($this->draft))->firstWhere('key', 'papers.work-plan');

    expect($blocker)->toMatchArray([
        'paper' => 'detailed-proposal',
        'field' => 'specific_objectives',
        'owner' => 'faculty',
    ])->and($blocker['message'])->toContain('specific objectives');
});

test('valid draft content still explains its existing finish saving requirement', function () {
    $this->draft->documents->push(new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_CURRICULUM_VITAE,
        'position' => 0,
        'source_data' => ['people' => [['first_name' => 'Faculty', 'last_name' => 'Researcher']]],
    ]));
    $blocker = collect($this->readiness->blockers($this->draft))->firstWhere('paper', 'curriculum-vitae');

    expect($blocker)->toMatchArray(['key' => 'papers.curriculum-vitae', 'field' => null, 'owner' => 'faculty', 'reason' => 'save_confirmation'])
        ->and($blocker['message'])->toContain('Finish saving')
        ->and($this->readiness->checklist($this->draft)['curriculum-vitae']['complete'])->toBeFalse();
});

test('research office blockers have no faculty field target', function () {
    config(['work_plan.verifier.name' => '', 'notice_to_proceed.issuing_officer.name' => '']);
    $blocker = collect($this->readiness->blockers($this->draft))->firstWhere('owner', 'research_office');

    expect($blocker)->toMatchArray([
        'key' => 'signatories.comment_response_form', 'paper' => null,
        'field' => null, 'owner' => 'research_office',
    ])->and($blocker['message'])->toContain('Research Head or Research Office Secretary');
});

test('shortening the project flags saved work plan months outside the new duration', function () {
    $this->draft->documents->push(new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'source_data' => ['specific_objectives' => [['description' => 'Survey the community']]],
    ]), new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'completed_at' => now(),
        'source_data' => ['entries' => [[
            'objective' => 'Survey the community', 'expected_output' => 'Baseline data',
            'activity' => 'Collect survey responses', 'months' => [12],
        ]]],
    ]));
    expect($this->readiness->checklist($this->draft)['work-plan']['complete'])->toBeTrue();

    $this->draft->duration_months = 6;

    expect($this->readiness->checklist($this->draft)['work-plan']['complete'])->toBeFalse()
        ->and($this->readiness->errors($this->draft))->toHaveKey('papers.work-plan')
        ->and(collect($this->readiness->blockers($this->draft))->where('paper', 'work-plan')->pluck('field')->all())
        ->toContain('entries.0.months.0');
});
