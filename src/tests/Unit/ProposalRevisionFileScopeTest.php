<?php

use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersionFile;
use App\Models\TopicReviewFileRevision;
use App\Support\ProposalRevisionFileScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

test('only files requested by the Research Head may be uploaded', function () {
    $request = Request::create('/resubmit', 'PATCH', [], [], [
        'work_plan' => UploadedFile::fake()->create('work-plan.pdf', 10, 'application/pdf'),
        'expense_breakdown' => UploadedFile::fake()->create('expenses.pdf', 10, 'application/pdf'),
        'curricula_vitae' => [UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')],
    ]);
    $scope = new ProposalRevisionFileScope;

    $errors = $scope->unexpectedUploadErrors(
        $request,
        collect([ProposalVersionFile::TYPE_WORK_PLAN]),
    );

    expect($errors)
        ->not->toHaveKey('work_plan')
        ->toHaveKeys(['expense_breakdown', 'curricula_vitae']);
});

test('legacy detailed proposal input is allowed only when that paper was requested', function () {
    $request = Request::create('/resubmit', 'PATCH', [], [], [
        'document' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
    ]);
    $scope = new ProposalRevisionFileScope;

    expect($scope->unexpectedUploadErrors($request, collect()))->toHaveKey('document')
        ->and($scope->unexpectedUploadErrors(
            $request,
            collect([ProposalVersionFile::TYPE_DETAILED_PROPOSAL]),
        ))->toBe([]);
});

test('staged editor files are limited to requested document types', function () {
    $scope = new ProposalRevisionFileScope;
    $workPlan = (new ProposalDraftDocument)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'file_path' => 'work-plan.docx',
    ]);
    $gadChecklist = (new ProposalDraftDocument)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
        'file_path' => 'gad.docx',
    ]);
    $staged = new EloquentCollection([
        ProposalVersionFile::TYPE_WORK_PLAN => $workPlan,
        ProposalVersionFile::TYPE_GAD_CHECKLIST => $gadChecklist,
    ]);

    expect($scope->requestedStagedFiles(
        $staged,
        collect([ProposalVersionFile::TYPE_WORK_PLAN]),
    )->all())->toBe([ProposalVersionFile::TYPE_WORK_PLAN => $workPlan]);
});
test('a requested generated document may be changed or resolved with an explanation', function () {
    $scope = new ProposalRevisionFileScope;
    $originalSource = ['entries' => [['activity' => 'May fieldwork']]];
    $file = (new ProposalVersionFile)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => $originalSource,
    ]);
    $revision = (new TopicReviewFileRevision)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
    ]);
    $revision->setRelation('file', $file);
    $staged = (new ProposalDraftDocument)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => $originalSource,
    ]);
    $stagedFiles = collect([ProposalVersionFile::TYPE_WORK_PLAN => $staged]);

    expect($scope->unresolvedErrors(
        Request::create('/resubmit', 'PATCH'),
        collect([$revision]),
        $stagedFiles,
    ))->toHaveKey('work_plan');

    $staged->source_data = ['entries' => [['activity' => 'June fieldwork']]];

    expect($scope->unresolvedErrors(
        Request::create('/resubmit', 'PATCH'),
        collect([$revision]),
        $stagedFiles,
    ))->toBe([]);

    $staged->source_data = $originalSource;
    $noChangeRequest = Request::create('/resubmit', 'PATCH', [
        'revision_resolutions' => [
            ProposalVersionFile::TYPE_WORK_PLAN => [
                'action' => 'no_change',
                'explanation' => 'The comment confirms the schedule already shown in the file.',
            ],
        ],
    ]);

    expect($scope->unresolvedErrors($noChangeRequest, collect([$revision]), $stagedFiles))->toBe([])
        ->and($scope->noChangeResponses(
            $noChangeRequest,
            collect([ProposalVersionFile::TYPE_WORK_PLAN]),
        )->all())->toBe([
            ProposalVersionFile::TYPE_WORK_PLAN => 'The comment confirms the schedule already shown in the file.',
        ]);
});

test('a no-change resolution requires an explanation and cannot include an upload', function () {
    $scope = new ProposalRevisionFileScope;
    $file = (new ProposalVersionFile)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => [],
    ]);
    $revision = (new TopicReviewFileRevision)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
    ]);
    $revision->setRelation('file', $file);
    $withoutExplanation = Request::create('/resubmit', 'PATCH', [
        'revision_resolutions' => [
            ProposalVersionFile::TYPE_WORK_PLAN => ['action' => 'no_change'],
        ],
    ]);
    $withUpload = Request::create('/resubmit', 'PATCH', [
        'revision_resolutions' => [
            ProposalVersionFile::TYPE_WORK_PLAN => [
                'action' => 'no_change',
                'explanation' => 'No document change is necessary.',
            ],
        ],
    ], [], [
        'work_plan' => UploadedFile::fake()->create('work-plan.pdf', 10, 'application/pdf'),
    ]);

    expect($scope->unresolvedErrors($withoutExplanation, collect([$revision]), collect()))
        ->toHaveKey('revision_resolutions.work_plan.explanation')
        ->and($scope->unresolvedErrors($withUpload, collect([$revision]), collect()))
        ->toHaveKey('work_plan');
});

test('a manual upload may replace a generated document instead of changing its editor source', function () {
    $scope = new ProposalRevisionFileScope;
    $source = ['entries' => [['activity' => 'May fieldwork']]];
    $file = (new ProposalVersionFile)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => $source,
    ]);
    $revision = (new TopicReviewFileRevision)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
    ]);
    $revision->setRelation('file', $file);
    $staged = (new ProposalDraftDocument)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => $source,
    ]);
    $request = Request::create('/resubmit', 'PATCH', [], [], [
        'work_plan' => UploadedFile::fake()->create('revised-work-plan.pdf', 10, 'application/pdf'),
    ]);

    expect($scope->unresolvedErrors(
        $request,
        collect([$revision]),
        collect([ProposalVersionFile::TYPE_WORK_PLAN => $staged]),
    ))->toBe([]);
});

test('a byte-identical replacement is rejected for a requested file', function () {
    $scope = new ProposalRevisionFileScope;
    $file = (new ProposalVersionFile)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'checksum' => 'original-checksum',
    ]);
    $revision = (new TopicReviewFileRevision)->forceFill([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
    ]);
    $revision->setRelation('file', $file);

    expect($scope->unchangedReplacementErrors(collect([$revision]), [[
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'checksum' => 'original-checksum',
    ]]))->toHaveKey('work_plan')
        ->and($scope->unchangedReplacementErrors(collect([$revision]), [[
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'position' => 0,
            'checksum' => 'revised-checksum',
        ]]))->toBe([]);
});
