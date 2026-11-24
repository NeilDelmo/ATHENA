<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersionFile;
use App\Support\ProposalDraftReadiness;
use App\Support\WorkPlanData;
use App\Support\WorkPlanRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('proposal work plan validation checks the schedule for each objective', function (array $months, bool $requireContinuousRange, array $expectedErrors) {
    $entries = [
        ['objective' => 'Collect data', 'expected_output' => 'Dataset', 'activity' => 'Run interviews', 'months' => [10, 11, 12]],
        ['objective' => 'Analyze the data', 'expected_output' => 'Report', 'activity' => 'Compare results', 'months' => $months],
    ];
    $validator = Validator::make([
        'project_title' => 'Study',
        'total_duration_months' => 15,
        'planned_start' => '2026-01-01',
        'planned_end' => '2027-03-31',
        'prepared_by' => 'Researcher',
        'entries' => $entries,
    ], WorkPlanRules::rules());
    $validator->after(WorkPlanRules::afterCallbacks($entries, requireContinuousRange: $requireContinuousRange));

    expect($validator->errors()->get('entries.1.months'))->toBe($expectedErrors)
        ->and($validator->fails())->toBe($expectedErrors !== []);
})->with([
    'range crossing project years and overlapping another row' => [[11, 12, 13, 14], true, []],
    'month values serialized by the browser' => [['1', '2', '3', '4', '5'], true, []],
    'range values submitted in a different order' => [[14, 12, 13, 11], true, []],
    'one month' => [[15], true, []],
    'gap when preparing a proposal' => [[11, 12, 15], true, ['Choose a continuous range of months for Objective 2.']],
    'existing gap in a preview' => [[11, 12, 15], false, []],
    'duplicate alongside a gap' => [[11, 11, 15], true, ['M11 was selected more than once for Objective 2.']],
]);

test('Attachment A shades every scheduled month across project years', function () {
    $this->withoutVite();
    $months = range(10, 15);
    $workPlan = WorkPlanData::fromValidated([
        'total_duration_months' => 15,
        'entries' => [
            ['objective' => 'Analyze the data', 'expected_output' => 'Report', 'activity' => 'Compare results', 'months' => $months],
        ],
    ]);

    $html = view('faculty.work-plans.preview', compact('workPlan'))->render();
    preg_match_all('/data-scheduled-month="(\d+)"/', $html, $matches);

    expect(array_map('intval', $matches[1]))->toBe($months);
});

test('the Work Plan completion status reflects the saved schedule and current proposal details', function () {
    $draft = new ProposalDraft([
        'project_title' => 'Study',
        'duration_months' => 15,
        'planned_start' => '2026-01-01',
        'planned_end' => '2027-03-31',
        'project_leader' => 'Researcher',
    ]);
    $entry = ['objective' => 'Analyze the data', 'expected_output' => 'Report', 'activity' => 'Compare results', 'months' => [13, 14, 15]];
    $document = new ProposalDraftDocument([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => ['entries' => [$entry]],
    ]);
    $draft->setRelation('researchCall', null);
    $draft->setRelation('documents', collect([
        new ProposalDraftDocument([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'position' => 0,
            'source_data' => ['specific_objectives' => [['description' => 'Analyze the data']]],
        ]),
        $document,
    ]));
    $readiness = app(ProposalDraftReadiness::class);

    expect($readiness->workPlanIsComplete($draft, null))->toBeFalse()
        ->and($readiness->workPlanIsComplete($draft, $document))->toBeTrue()
        ->and($readiness->checklist($draft)['work-plan']['status'])->toBe('Complete')
        ->and($document->completed_at)->toBeNull();

    $document->completed_at = now();
    expect($readiness->workPlanIsComplete($draft, $document))->toBeTrue();

    $draft->duration_months = 14;
    expect($readiness->workPlanIsComplete($draft, $document))->toBeFalse();

    $document->source_data = ['entries' => [[...$entry, 'months' => [12, 14]]]];
    expect($readiness->workPlanIsComplete($draft, $document))->toBeFalse();
});
