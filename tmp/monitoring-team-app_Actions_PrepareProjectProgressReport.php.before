<?php

namespace App\Actions;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\MonitoringEvidenceService;
use App\Services\MonitoringQuarterService;
use App\Services\MonitoringToolDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrepareProjectProgressReport
{
    public function __construct(
        private readonly MonitoringToolDocumentService $documentService,
        private readonly DocumentPdfConverter $pdfConverter,
        private readonly MonitoringQuarterService $monitoringQuarterService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(
        TopicProposal $topic,
        User $user,
        array $validated,
        ?UploadedFile $attachment,
        ?ProjectProgressReport $supersedesReport = null,
    ): ProjectProgressReport {
        $storedPaths = [];

        try {
            $draft = ProjectMonitoringDraft::query()->whereBelongsTo($topic, 'topic')
                ->whereBelongsTo($user, 'user')->forSource($supersedesReport)->first(['id', 'lock_version']);
            $expectedDraftVersion = (int) ($validated['draft_version'] ?? $draft?->lock_version ?? 0);
            $expectedDraftId = (int) ($validated['draft_id'] ?? $draft?->id ?? 0);
            if ($expectedDraftVersion !== ($draft?->lock_version ?? 0) || $expectedDraftId !== ($draft?->id ?? 0)) {
                throw ValidationException::withMessages(['draft_version' => 'A newer Monitoring Tool draft was saved. Reload the latest draft before preparing its PDF.']);
            }
            $workPlan = collect($validated['work_plan']);
            $period = $supersedesReport === null
                ? $this->monitoringQuarterService->forDate($validated['reporting_date'], $topic)
                : $this->monitoringQuarterService->forDate($supersedesReport->reporting_date, $topic);
            $attachmentPath = $attachment?->store('progress-reports/'.$topic->id, 'local');

            if ($attachmentPath !== null) {
                $storedPaths[] = $attachmentPath;
            }

            $report = new ProjectProgressReport([
                ...collect($validated)->except(['attachment', 'source_report_id', 'draft_version', 'draft_id'])->all(),
                'topic_id' => $topic->id,
                'submitted_by' => $user->id,
                'reporting_year' => $period['year'],
                'reporting_quarter' => $period['quarter'],
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'version_number' => $supersedesReport === null ? 1 : $supersedesReport->version_number + 1,
                'supersedes_report_id' => $supersedesReport?->id,
                'progress_percentage' => app(MonitoringEvidenceService::class)->overallProgress($topic, $validated['reporting_date'], $validated['work_plan']),
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
            $filename = Str::slug($topic->title).'-'.$report->quarter_label.'-v'.$report->version_number.'-monitoring-tool.pdf';
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
            DB::transaction(function () use ($topic, $user, $report, $period, $supersedesReport, $expectedDraftVersion, $expectedDraftId): void {
                $lockedTopic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();
                if (! $lockedTopic->isMonitoringAvailable() || ! $lockedTopic->isAccessibleTo($user)) {
                    throw ValidationException::withMessages(['preparation' => 'Monitoring is no longer open for this project.']);
                }
                $draft = ProjectMonitoringDraft::query()->whereBelongsTo($lockedTopic, 'topic')
                    ->whereBelongsTo($user, 'user')->forSource($supersedesReport)->lockForUpdate()->first();
                if (($draft?->lock_version ?? 0) !== $expectedDraftVersion || ($draft?->id ?? 0) !== $expectedDraftId) {
                    throw ValidationException::withMessages(['draft_version' => 'A newer Monitoring Tool draft was saved. Reload the latest draft before preparing its PDF.']);
                }
                if ($lockedTopic->preparedProgressReports()->exists()) {
                    throw ValidationException::withMessages(['preparation' => 'Submit or discard the prepared Monitoring Tool before preparing another quarter.']);
                }
                $existing = $lockedTopic->progressReports()->where('reporting_year', $period['year'])->where('reporting_quarter', $period['quarter']);
                if ($supersedesReport === null && $existing->exists()) {
                    throw ValidationException::withMessages(['preparation' => 'This quarter already has a prepared or submitted report. Open that report instead.']);
                }
                if ($supersedesReport !== null) {
                    $source = ProjectProgressReport::query()->whereKey($supersedesReport->id)->lockForUpdate()->firstOrFail();
                    if ($source->topic_id !== $topic->id || $source->review_status !== 'revision_requested' || $source->nextVersion()->exists()) {
                        throw ValidationException::withMessages(['preparation' => 'This report can no longer be revised. Reload its review record.']);
                    }
                }
                $report->save();
                $draft?->delete();
            });

            return $report;
        } catch (Throwable $exception) {
            collect($storedPaths)->each(fn (string $path) => Storage::disk('local')->delete($path));

            throw $exception;
        }
    }
}
