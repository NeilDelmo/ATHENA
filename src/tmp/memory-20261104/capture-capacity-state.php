<?php

use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Services\ProposalPdfPreparation;
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
if (config('database.connections.mysql.database') !== 'capacity_athena') {
    throw new RuntimeException('Only the synthetic capacity database may be inspected.');
}
for ($attempt = 0; $attempt < 60 && DB::table('jobs')->count() > 0; $attempt++) {
    sleep(2);
}
$context = json_decode(file_get_contents(storage_path('app/private/capacity-context.json')), true, 512, JSON_THROW_ON_ERROR);
$draft = ProposalDraft::with('documents', 'owner', 'researchCall')->findOrFail($context['draft_id']);
$result = [
    'projects' => TopicProposal::count(),
    'queue_draft_id' => $draft->id,
    'extraction_draft_id' => $context['extraction_draft_id'],
    'queue_state' => $draft->pdf_preparation_state,
    'queue_status' => app(ProposalPdfPreparation::class)->status($draft),
    'queue_readiness_errors' => app(ProposalDraftReadiness::class)->errors($draft),
    'pending_jobs' => DB::table('jobs')->count(),
    'failed_jobs' => DB::table('failed_jobs')->count(),
    'report_timeout_seconds' => config('document_pdf.timeout_seconds'),
    'delegate_to_php_cli' => config('document_pdf.delegate_to_php_cli'),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
if ($result['projects'] !== 1000 || $result['queue_status']['status'] !== 'ready'
    || $result['queue_readiness_errors'] !== [] || $result['pending_jobs'] !== 0 || $result['failed_jobs'] !== 0) {
    exit(1);
}
