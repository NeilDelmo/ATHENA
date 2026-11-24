<?php

use App\Models\ProjectDocument;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\ResearchManual;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

$manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$functionalOwner = User::factory()->create([
    'name' => 'Functional Reference Owner', 'email' => 'playwright.functional.owner@g.batstate-u.edu.ph', 'college' => 'CICS',
]);
$owner = $functionalOwner->id;
$manifest['users']['functional_owner'] = [...$functionalOwner->only(['id', 'name', 'email']), 'workspace' => 'faculty'];
$secretary = $manifest['users']['secretary']['id'];
$head = $manifest['users']['head']['id'];
Role::findOrCreate('faculty_researcher', 'web');
foreach (['functional_owner', 'outsider'] as $actor) {
    User::findOrFail($manifest['users'][$actor]['id'])->assignRole('faculty');
    User::findOrFail($manifest['users'][$actor]['id'])->assignRole('faculty_researcher');
}
$call = ResearchCall::create([
    'title' => 'Functional unpublished call', 'academic_year' => '2026-2027',
    'status' => 'draft', 'opens_at' => '2026-01-01', 'closes_at' => '2027-01-01',
    'max_active_research_per_faculty' => 2, 'created_by' => $head,
]);
$draft = ProposalDraft::create(['user_id' => $owner, 'project_title' => 'Functional incomplete draft', 'research_call_id' => $call->id]);
$projects = [];
foreach (['budget', 'other'] as $label) {
    $projects[$label] = TopicProposal::create([
        'user_id' => $label === 'other' ? $manifest['users']['outsider']['id'] : $owner,
        'research_secretary_id' => $secretary, 'title' => 'Functional '.$label.' project',
        'status' => 'approved', 'project_status' => 'ongoing',
        'estimated_budget' => 100000, 'estimated_duration_months' => 3,
        'notice_to_proceed_issued_at' => '2026-01-01', 'notice_to_proceed_issued_by' => $head,
        'notice_to_proceed_data' => ['approved_budget' => 100000, 'approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-03-31', 'approved_duration_months' => 3],
    ]);
}
$pdf = lifecyclePdf(['Functional reference document', 'Expected content: coastal baseline 2026', 'Synthetic local test material']);
Storage::disk('local')->put('functional/reference.pdf', $pdf);
Storage::disk('local')->put('functional/budget-report.pdf', $pdf);
$document = ProjectDocument::create([
    'topic_id' => $projects['budget']->id, 'uploaded_by' => $owner,
    'category' => ProjectDocument::CATEGORY_PROPOSAL_PAPERS,
    'title' => 'Functional reference PDF',
    'file_path' => 'functional/reference.pdf', 'original_filename' => 'reference.pdf',
    'mime_type' => 'application/pdf', 'file_size' => strlen($pdf), 'checksum' => hash('sha256', $pdf),
]);
$missing = $document->replicate();
$missing->file_path = 'functional/missing.pdf';
$missing->original_filename = 'missing.pdf';
$missing->save();
$report = ProjectProgressReport::create([
    'topic_id' => $projects['budget']->id, 'submitted_by' => $owner,
    'reporting_date' => '2026-03-31', 'reporting_year' => 2026, 'reporting_quarter' => 1,
    'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
    'progress_percentage' => 25, 'accomplishments' => 'Reference field survey',
    'work_plan' => [['objective' => 'Survey coast', 'expected_output' => '1 report', 'activity' => 'Survey', 'months' => [1, 2, 3], 'actual_accomplishment' => 'Survey underway']],
    'budget_utilization' => array_map(fn (string $type, int $amount): array => ['type' => $type, 'amount_requested' => 30000, 'actual_amount' => $amount], ['Purchase Request', 'Cash Advance', 'Request of Payment'], [10000, 7500, 2500]),
    'submission_status' => 'prepared', 'official_pdf_path' => 'functional/budget-report.pdf',
    'official_pdf_filename' => 'functional-budget.pdf', 'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf),
    'prepared_at' => now(), 'review_stage' => 'secretary',
]);
$manifest['functional'] = [
    'ownerId' => $owner,
    'callId' => $call->id, 'draftId' => $draft->id,
    'projectId' => $projects['budget']->id, 'otherProjectId' => $projects['other']->id,
    'reportId' => $report->id, 'documentId' => $document->id, 'missingDocumentId' => $missing->id,
    'documentChecksum' => hash('sha256', $pdf), 'documentSize' => strlen($pdf),
    'category' => $document->category,
];
foreach (['active' => true, 'staged' => false] as $name => $active) {
    $version = hash('sha256', 'functional-manual-'.$name);
    $manual = ResearchManual::create([
        'title' => 'Functional research manual '.$name, 'edition' => '2026 test',
        'version' => $version, 'directory' => 'functional/manual-'.$name,
        'page_count' => 1, 'unindexed_pages' => [], 'uploaded_by' => $head,
        'activated_at' => $active ? now() : null,
    ]);
    File::ensureDirectoryExists(dirname($manual->pdfPath()));
    File::put($manual->pdfPath(), $pdf);
    File::put($manual->indexPath(), json_encode([
        'schema_version' => 1, 'version' => $version, 'title' => $manual->title,
        'page_count' => 1, 'unindexed_pages' => [],
        'chunks' => [['id' => 'test-reference', 'section' => 'Reference', 'content' => 'Expected content: coastal baseline 2026', 'page_start' => 1, 'page_end' => 1]],
    ], JSON_THROW_ON_ERROR));
    $manifest['functional'][$name.'ManualVersion'] = $version;
}
File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
File::put(base_path('test-results/lifecycle-fixtures/reference.pdf'), $pdf);
File::put(base_path('test-results/lifecycle-fixtures/unsupported.txt'), 'This is not a PDF.');
echo "Functional reference fixtures prepared.\n";
