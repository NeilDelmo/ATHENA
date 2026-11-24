<?php

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Support\InitialScreeningSubmissionOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(getenv('ATHENA_TEST_PRESERVE_DATABASE') === '1' ? DatabaseTransactions::class : RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function recordApprovedProposalAssessments(ProposalVersion $version, ?string $recommendation = InitialScreeningSubmissionOrder::FOR_ENDORSEMENT): void
{
    $assessments = [
        ProposalVersionFile::TYPE_GAD_CHECKLIST => [
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            'gad_signature_confirmed' => true,
            'gad_outcome' => 'passed',
            'gad_score' => 12,
        ],
    ];
    if ($recommendation !== null) {
        $assessments[ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM] = [
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'narrative_evaluation' => 'The co-evaluator completed the review.',
            'recommended_action' => $recommendation,
        ];
    }

    $position = (int) $version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->max('position');
    foreach ($assessments as $documentType => $assessment) {
        $source = $version->files()->where('document_type', $documentType)->sole();
        $path = "assessments/{$version->id}/{$documentType}.pdf";
        Storage::disk('local')->put($path, '%PDF-1.7 completed assessment');
        $version->files()->create([
            'source_version_file_id' => $source->id,
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => ++$position,
            'file_path' => $path,
            'original_filename' => basename($path),
            'mime_type' => 'application/pdf',
            'source_data' => ['target_document_type' => $documentType, ...$assessment],
        ]);
    }

    $version->unsetRelation('files');
}
