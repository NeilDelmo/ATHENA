<?php

use App\Models\ProposalDraft;
use App\Support\ProposalDraftReadiness;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});
require_once __DIR__.'/seed-web.php';
require_once __DIR__.'/workload-extraction.php';
capacityWebAssert(config('database.connections.mysql.database') === 'capacity_athena', 'Only the private capacity database may be changed.');
capacityWebAssert(DB::table('jobs')->count() === 0, 'Wait for real queued work to finish before repairing fixtures.');
$contextPath = storage_path('app/private/capacity-context.json');
$context = json_decode(file_get_contents($contextPath), true, 512, JSON_THROW_ON_ERROR);
$draft = ProposalDraft::with('documents', 'owner', 'researchCall')->findOrFail($context['draft_id']);
capacityWebAssert($draft->owner->email === 'capacity-faculty@example.test', 'Only the synthetic fixture owner may be used.');
DB::transaction(function () use ($draft, &$context): void {
    foreach (capacityWebDraftSources($draft->owner) as $type => $source) {
        $draft->documents()->updateOrCreate(['document_type' => $type, 'position' => 0], [
            'source_data' => $source, 'completed_at' => now(),
        ]);
    }
    $draft->documents()->update(['mime_type' => null]);
    $draft->update(['pdf_preparation_state' => null]);
    $extractionDraft = ProposalDraft::firstOrCreate([
        'user_id' => $draft->user_id, 'project_title' => 'Capacity extraction-only fixture',
    ], $draft->only(['research_call_id', 'project_leader', 'duration_months', 'planned_start', 'planned_end']));
    $context['extraction_draft_id'] = $extractionDraft->id;
});
capacityWebAssert(file_put_contents($contextPath, json_encode($context, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) !== false, 'Could not save isolated fixture context.');
$draft = $draft->fresh(['documents', 'owner', 'researchCall']);
$readiness = app(ProposalDraftReadiness::class);
capacityWebAssert($readiness->errors($draft) === [], 'The restored synthetic queue draft must be complete.');
capacityWebAssert(! $readiness->submissionFilesArePrepared($draft), 'The restored queue fixture must require actual PDF work.');
$before = $draft->documents->mapWithKeys(fn ($document): array => [$document->document_type => $document->source_data])->all();
session()->put(App\Models\User::ACTIVE_WORKSPACE_SESSION_KEY, App\Models\User::WORKSPACE_FACULTY);
$extraction = capacityRunExtractionCase('assistant-pdf-200', $context, storage_path('app/private/capacity-output/isolated-extraction-self-check'));
$after = $draft->fresh(['documents'])->documents->mapWithKeys(fn ($document): array => [$document->document_type => $document->source_data])->all();
capacityWebAssert($before === $after, 'Assistant extraction must preserve every queue source.');
capacityWebAssert($readiness->errors($draft->fresh(['documents'])) === [], 'The queue fixture must remain complete after extraction.');
echo json_encode(['queue_draft_id' => $draft->id, 'extraction_draft_id' => $context['extraction_draft_id'], 'queue_readiness_errors' => [], 'extraction_preserves_queue_sources' => true, 'extraction_result' => $extraction], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
