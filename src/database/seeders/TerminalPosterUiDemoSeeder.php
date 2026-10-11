<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\TopicProposal;
use App\Services\TerminalReportDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TerminalPosterUiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample posters may not be added in production.');
        }

        $topic = TopicProposal::query()
            ->where('description', 'like', '[lifecycle-demo:completed-published-second]%')
            ->where('title', 'Predictive Maintenance Dashboard for University Laboratory Equipment')
            ->sole();
        if ($topic->project_status !== 'completed') {
            throw new RuntimeException('The poster sample requires the completed maintenance demonstration.');
        }

        DB::transaction(function () use ($topic): void {
            $report = $topic->narrativeReports()->where('report_type', 'terminal')
                ->where('tracking_number', 'LIFE-'.$topic->id.'-TERMINAL')->lockForUpdate()->sole();
            if (! str_starts_with((string) $report->accomplishment_summary, 'UI DEMONSTRATION DATA:')
                || ($report->hasSignedCopy() && ($report->signedCopy()['demo'] ?? false) !== true)) {
                throw new RuntimeException('Only demonstration Terminal Reports may receive a sample poster.');
            }
            if (collect($report->photos ?? [])->contains(fn (array $photo): bool => ($photo['section'] ?? null) === 'cover')) {
                return;
            }

            $disk = Storage::disk('local');
            $image = File::get(public_path('images/sample-maintenance-poster.png'));
            if ((@getimagesizefromstring($image)[2] ?? null) !== IMAGETYPE_PNG) {
                throw new RuntimeException('The demonstration poster must be a valid PNG image.');
            }
            $directory = 'post-approval-ui-demo/'.$topic->id;
            $backup = $directory.'/before-poster-sample.json';
            if (! $disk->exists($backup) && ! $disk->put($backup, $report->toJson(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Unable to back up the original demonstration Terminal Report.');
            }
            $imagePath = $directory.'/sample-maintenance-poster-'.hash('sha256', $image).'.png';
            if (! $disk->put($imagePath, $image)) {
                throw new RuntimeException('Unable to store the demonstration poster.');
            }
            $report->photos = [...($report->photos ?? []), [
                'path' => $imagePath, 'original_name' => 'SAMPLE-predictive-maintenance-poster.png',
                'mime_type' => 'image/png', 'size' => strlen($image), 'section' => 'cover', 'after_paragraph' => 0,
                'caption' => 'SAMPLE POSTER — illustrative UI demonstration, not actual research findings.', 'demo' => true,
            ]];
            $pdf = app(DocumentPdfConverter::class)->convertDocx(app(TerminalReportDocumentService::class)->generate($report));
            $checksum = hash('sha256', $pdf);
            $pdfPath = $directory.'/sample-terminal-with-poster-'.$checksum.'.pdf';
            if (! $disk->put($pdfPath, $pdf)) {
                throw new RuntimeException('Unable to store the demonstration Terminal Report PDF.');
            }
            $report->fill([
                'official_pdf_path' => $pdfPath, 'official_pdf_filename' => 'SAMPLE-terminal-report-with-poster.pdf',
                'official_pdf_checksum' => $checksum, 'official_pdf_size' => strlen($pdf),
            ])->save();
        });
    }
}
