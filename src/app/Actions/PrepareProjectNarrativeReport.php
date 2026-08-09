<?php

namespace App\Actions;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProgressReportDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $storedPaths = [];

        try {
            $figureIndexes = range(1, (int) config('progress_report.max_figures'));
            $photos = collect($figureIndexes)
                ->filter(fn (int $index): bool => ($files["photo_{$index}"] ?? null) instanceof UploadedFile)
                ->map(function (int $index) use ($files, $validated, $topic, &$storedPaths): array {
                    /** @var UploadedFile $file */
                    $file = $files["photo_{$index}"];
                    $path = $file->store("narrative-progress-reports/{$topic->id}", 'local');
                    $storedPaths[] = $path;

                    return [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'caption' => $validated["photo_caption_{$index}"],
                        'section' => $validated["photo_section_{$index}"],
                    ];
                })
                ->values()
                ->all();
            $photoFields = collect($figureIndexes)
                ->flatMap(fn (int $index): array => [
                    'photo_'.$index,
                    'photo_caption_'.$index,
                    'photo_section_'.$index,
                ])
                ->all();
            $report = new ProjectNarrativeReport([
                ...collect($validated)->except($photoFields)->all(),
                'topic_id' => $topic->id,
                'submitted_by' => $user->id,
                'budget' => $topic->estimated_budget,
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
            $filename = Str::slug($topic->title).'-progress-report.pdf';
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
