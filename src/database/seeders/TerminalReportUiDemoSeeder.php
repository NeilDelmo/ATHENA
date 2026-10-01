<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Services\TerminalReportDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TerminalReportUiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UI demonstration reports may not be refreshed in production.');
        }

        $report = ProjectNarrativeReport::query()->with(['topic.user', 'submitter'])
            ->where('report_type', 'terminal')
            ->where('accomplishment_summary', 'like', 'UI DEMONSTRATION DATA:%')
            ->whereHas('topic', fn ($query) => $query->where('description', 'like', '[lifecycle-demo:ongoing-second]%'))
            ->get()->first(fn (ProjectNarrativeReport $report): bool => $report->tracking_number === 'LIFE-'.$report->topic_id.'-TERMINAL');

        if (! $report) {
            $this->command?->warn('No existing clinic-records terminal demonstration report was found.');

            return;
        }

        $backup = 'post-approval-ui-demo/terminal-reader-before-refresh-'.$report->id.'.json';
        if (! Storage::disk('local')->exists($backup)) {
            Storage::disk('local')->put($backup, $report->toJson(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        $example = app(ProgressReportUiDemoSeeder::class)->exampleData($report->topic);
        $report->fill([
            ...$example,
            'introduction' => 'UI DEMONSTRATION ONLY. This terminal report summarizes the completed prototype development and pilot evaluation for '.$report->topic->title.'. All figures and findings are fictional examples for testing the report reader and PDF preview.',
            'results_discussion' => "The final prototype provides a participant sign-in interface for accessing assigned pilot tasks. The sample screen below illustrates the completed interface and contains no real accounts.\n\nThe final evaluation collected structured feedback on task difficulty and suggested improvements. In this fictional example, participants rated the interface four out of five and requested clearer first-task instructions. These findings support a recommendation for additional onboarding guidance.\n\nThe completed pilot invited 30 participants and recorded 26 completed sessions, a response rate of 86.7%. Four invited participants did not complete the evaluation. This is a study limitation, rather than an outstanding implementation activity. The dashboard below illustrates the final pilot dataset.",
            'terminal_data' => [
                ...($report->terminal_data ?? []),
                'conclusions' => 'UI DEMONSTRATION ONLY. The prototype and planned pilot activities were completed. The fictional evaluation suggests that the core workflow is usable, while first-task instructions need improvement. The small sample and four non-completions limit generalization.',
                'recommendations' => 'Improve onboarding instructions, conduct a larger independent evaluation, and provide staff training before adopting the prototype. These recommendations illustrate a terminal report and do not constitute actual research findings.',
            ],
        ]);

        $pdf = app(DocumentPdfConverter::class)->convertDocx(app(TerminalReportDocumentService::class)->generate($report));
        $path = 'post-approval-ui-demo/'.$report->topic_id.'/terminal-reader-'.$report->id.'.pdf';
        if (! Storage::disk('local')->put($path, $pdf)) {
            throw new RuntimeException('The demonstration terminal report PDF could not be stored.');
        }
        $report->fill([
            'official_pdf_path' => $path, 'official_pdf_filename' => 'ui-demo-terminal-report.pdf',
            'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf),
        ])->save();
        $this->command?->info('Updated existing terminal report #'.$report->id.' on project #'.$report->topic_id.' with seven sample figures.');
    }
}
