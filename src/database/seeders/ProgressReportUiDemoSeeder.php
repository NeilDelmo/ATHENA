<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Services\ProgressReportDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProgressReportUiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UI demonstration reports may not be refreshed in production.');
        }
        $reports = ProjectNarrativeReport::query()->with(['topic.user', 'submitter'])
            ->where('report_type', 'progress')
            ->where('accomplishment_summary', 'like', 'UI DEMONSTRATION DATA:%')
            ->whereHas('topic', fn ($query) => $query->where('description', 'like', '[lifecycle-demo:%'))
            ->get()->filter(fn (ProjectNarrativeReport $report): bool => $report->tracking_number === 'LIFE-'.$report->topic_id.'-NARRATIVE');
        $backup = 'post-approval-ui-demo/progress-reader-before-refresh.json';
        if ($reports->isNotEmpty() && ! Storage::disk('local')->exists($backup)) {
            Storage::disk('local')->put($backup, $reports->toJson(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        foreach ($reports as $report) {
            $report->fill($this->exampleData($report->topic));
            $pdf = app(DocumentPdfConverter::class)->convertDocx(app(ProgressReportDocumentService::class)->generate($report));
            $path = 'post-approval-ui-demo/'.$report->topic_id.'/progress-reader-'.$report->id.'.pdf';
            if (! Storage::disk('local')->put($path, $pdf)) {
                throw new RuntimeException('The refreshed demonstration PDF could not be stored.');
            }
            $report->fill(['official_pdf_path' => $path, 'official_pdf_filename' => 'ui-demo-progress-report.pdf',
                'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf)])->save();
            $this->command?->info('Updated demonstration progress report #'.$report->id.' with seven figures.');
        }
    }

    /** @return array<string, mixed> */
    public function exampleData(TopicProposal $topic): array
    {
        $captions = ['Context diagram of the participant, research prototype, and research team.',
            'Data flow from baseline collection to pilot evaluation and the report.',
            'Data model linking participants, pilot sessions, and feedback.',
            'Participant and research-team interactions with the prototype.',
            'Illustrative participant sign-in screen used during the pilot.',
            'Illustrative feedback screen for recording task difficulty and improvement notes.',
            'Illustrative validation dashboard: 26 completed sessions and four follow-ups.'];
        $photos = collect($captions)->map(function (string $caption, int $index): array {
            $name = 'figure-'.($index + 1).'.png';
            $contents = file_get_contents(public_path('images/progress-report-demo/'.$name));
            $path = 'post-approval-ui-demo/progress-figures/'.$name;
            if ($contents === false || ! Storage::disk('local')->put($path, $contents)) {
                throw new RuntimeException('A demonstration figure could not be stored.');
            }

            return ['path' => $path, 'original_name' => $name, 'mime_type' => 'image/png', 'size' => strlen($contents),
                'caption' => $caption, 'section' => $index < 4 ? 'methodology' : 'results_discussion',
                'after_paragraph' => $index < 4 ? ($index < 2 ? 1 : 2) : $index - 3];
        })->all();

        return [
            'introduction' => 'UI DEMONSTRATION ONLY. This report illustrates the progress-report format for '.$topic->title.'. The monitoring period covers the development of a research prototype, pilot activities, and initial validation. The figures are illustrative examples, not evidence of completed research.',
            'rationale' => 'The approved proposal identifies a need for a consistent and accessible intervention. The initial pilot examines whether participants can complete the planned tasks and whether the research team can collect reliable feedback. Early findings guide the next iteration before wider evaluation.',
            'methodology' => "The team mapped how participants interact with the research prototype and how observations reach the research team. The context and data-flow diagrams define the scope of the pilot and the evidence recorded at each stage.\n\nThe prototype links participant records, pilot sessions, and feedback. The data model and interaction diagram describe the records and tasks needed for evaluation. Participant information is handled according to the approved research protocol.\n\nBaseline collection, a supervised pilot, and structured feedback are used to compare actual accomplishments with the approved work-plan targets. The research team records incomplete activities and schedules follow-up validation.",
            'results_discussion' => "The prototype includes a participant sign-in screen for accessing assigned pilot tasks. The illustrative screen below demonstrates the interface used to begin a session; it does not contain real participant accounts.\n\nThe feedback interface records perceived task difficulty and suggested improvements. In this demonstration, a participant selected a rating of four out of five and requested clearer instructions before the first task. This example shows how feedback can support prototype refinement.\n\nThe demonstration includes 30 invited participants, 26 completed validation sessions, and four follow-ups. The resulting completion rate is 86.7%. Outstanding sessions require a revised schedule, and the next report should document those completions and any resulting changes.",
            'photos' => $photos,
        ];
    }
}
