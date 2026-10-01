<?php

namespace App\Actions;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProgressReportDocumentService;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrepareProjectNarrativeReport
{
    public function __construct(
        private readonly ProgressReportDocumentService $documentService,
        private readonly DocumentPdfConverter $pdfConverter,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(
        TopicProposal $topic,
        User $user,
        array $validated,
        array $files,
    ): ProjectNarrativeReport {
        return DB::transaction(function () use ($topic, $user, $validated, $files): ProjectNarrativeReport {
            $topic->newQuery()->whereKey($topic)->lockForUpdate()->firstOrFail();
            $type = $validated['report_type'] ?? 'progress';
            if (ProjectNarrativeReport::prepared()->where('topic_id', $topic->id)->where('submitted_by', $user->id)->where('report_type', $type)->exists()) {
                throw ValidationException::withMessages(['preparation' => 'A prepared report of this type already exists.']);
            }
            if ($type === 'terminal') {
                $validated['terminal_data']['version_number'] = ProjectNarrativeReport::where('topic_id', $topic->id)->where('report_type', 'terminal')->get()->max(fn ($report) => (int) ($report->terminal_data['version_number'] ?? 1)) + 1;
            }

            return $this->prepare($topic, $user, $validated, $files);
        });
    }

    private function prepare(TopicProposal $topic, User $user, array $validated, array $files): ProjectNarrativeReport
    {
        $storedPaths = [];

        try {
            $figureIndexes = (($validated['report_type'] ?? 'progress') === 'terminal' ? range(1, 30) : []);
            $photos = ($validated['report_type'] ?? 'progress') === 'terminal'
                ? app(TerminalReportData::class)->photos($topic, $validated, $files, false, $storedPaths)
                : app(ProgressReportData::class)->photos($topic, $validated, $files, false, $storedPaths);
            $photoFields = collect($figureIndexes)
                ->flatMap(fn (int $index): array => [
                    'photo_'.$index,
                    'photo_caption_'.$index,
                    'photo_section_'.$index,
                    'reuse_photo_'.$index,
                    'photo_after_paragraph_'.$index,
                ])
                ->prepend('figures')
                ->prepend('cover_image_caption')
                ->prepend('reuse_cover_image')
                ->prepend('cover_image')
                ->all();
            $report = new ProjectNarrativeReport([
                ...collect($validated)->except($photoFields)->reject(fn (mixed $value, string $key): bool => str_starts_with($key, 'photo_'))->all(),
                'topic_id' => $topic->id,
                'submitted_by' => $user->id,
                'budget' => $validated['terminal_data']['approved_budget'] ?? $topic->estimated_budget,
                'accomplishment_summary' => collect($validated['accomplishments'])
                    ->pluck('actual')
                    ->implode("\n"),
                'photos' => $photos,
                'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_PREPARED,
                'prepared_at' => now(),
            ]);
            $report->setRelation('topic', $topic->loadMissing('user'));
            $report->setRelation('submitter', $user);

            $pdf = $this->pdfConverter->convertDocx($this->documentService->generate($report));
            $filename = Str::slug($validated['terminal_data']['project_title'] ?? $topic->title).'-'.Str::slug($report->report_label).'.pdf';
            $pdfPath = 'narrative-progress-reports/'.$topic->id.'/prepared/'.Str::uuid().'.pdf';

            if (! Storage::disk('local')->put($pdfPath, $pdf)) {
                throw new \RuntimeException('The prepared Progress Report PDF could not be stored.');
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
