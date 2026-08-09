<?php

namespace App\Actions;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\MonitoringToolDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PrepareProjectProgressReport
{
    public function __construct(
        private readonly MonitoringToolDocumentService $documentService,
        private readonly DocumentPdfConverter $pdfConverter,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(
        TopicProposal $topic,
        User $user,
        array $validated,
        ?UploadedFile $attachment,
    ): ProjectProgressReport {
        $storedPaths = [];

        try {
            $workPlan = collect($validated['work_plan']);
            $attachmentPath = $attachment?->store('progress-reports/'.$topic->id, 'local');

            if ($attachmentPath !== null) {
                $storedPaths[] = $attachmentPath;
            }

            $report = new ProjectProgressReport([
                ...collect($validated)->except('attachment')->all(),
                'topic_id' => $topic->id,
                'submitted_by' => $user->id,
                'progress_percentage' => (int) round($workPlan->sum(
                    fn (array $entry): float => (float) $entry['accomplished_percentage'],
                )),
                'accomplishments' => $workPlan
                    ->pluck('actual_accomplishment')
                    ->filter()
                    ->implode("\n"),
                'issues' => $workPlan
                    ->pluck('findings')
                    ->filter()
                    ->implode("\n") ?: null,
                'attachment_path' => $attachmentPath,
                'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_PREPARED,
                'prepared_at' => now(),
            ]);
            $report->setRelation('topic', $topic->loadMissing('user'));
            $report->setRelation('submitter', $user);

            $pdf = $this->pdfConverter->convertDocx($this->documentService->generate($report));
            $filename = Str::slug($topic->title).'-monitoring-tool.pdf';
            $pdfPath = 'progress-reports/'.$topic->id.'/prepared/'.Str::uuid().'.pdf';

            if (! Storage::disk('local')->put($pdfPath, $pdf)) {
                throw new \RuntimeException('The prepared Monitoring Tool PDF could not be stored.');
            }

            $storedPaths[] = $pdfPath;
            $report->fill([
                'official_pdf_path' => $pdfPath,
                'official_pdf_filename' => $filename,
                'official_pdf_checksum' => hash('sha256', $pdf),
                'official_pdf_size' => strlen($pdf),
            ]);
            $report->save();

            return $report;
        } catch (Throwable $exception) {
            collect($storedPaths)->each(fn (string $path) => Storage::disk('local')->delete($path));

            throw $exception;
        }
    }
}
