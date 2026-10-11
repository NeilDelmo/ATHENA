<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Services\MonitoringQuarterService;
use App\Services\ProgressReportDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LaboratoryProgressReportUiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UI demonstration reports may not be added in production.');
        }

        $existing = ProjectNarrativeReport::query()->with(['topic.user', 'submitter'])
            ->where('report_type', 'progress')
            ->where('accomplishment_summary', 'like', 'UI DEMONSTRATION DATA:%')
            ->whereHas('topic', fn ($query) => $query
                ->where('title', 'Predictive Maintenance Dashboard for University Laboratory Equipment')
                ->where('description', 'like', '[lifecycle-demo:completed-published-second]%'))
            ->latest('id')->get()->first(fn (ProjectNarrativeReport $report): bool => $report->tracking_number === 'LIFE-'.$report->topic_id.'-NARRATIVE');

        if ($existing === null) {
            $this->command?->warn('No existing laboratory-equipment demonstration report was found.');

            return;
        }

        $quarters = app(MonitoringQuarterService::class);
        $window = $quarters->reportingWindow($existing->topic);
        $example = app(ProgressReportUiDemoSeeder::class)->exampleData($existing->topic);
        $samples = $this->samples();
        foreach ($quarters->projectPeriods($existing->topic) as $period) {
            $quarter = $period['quarter'];
            if (! isset($samples[$quarter]) || $period['opens_at']->isFuture()) {
                continue;
            }
            if ($existing->topic->narrativeReports()->where('report_type', 'progress')->where('reporting_quarter', $quarter)->exists()) {
                continue;
            }

            $sample = $samples[$quarter];
            $date = $period['opens_at'];
            $report = new ProjectNarrativeReport([
                'topic_id' => $existing->topic_id, 'submitted_by' => $existing->submitted_by,
                'report_type' => 'progress', 'tracking_number' => 'LIFE-'.$existing->topic_id.'-PROGRESS-Q'.$quarter,
                'reporting_quarter' => $quarter, 'reporting_date' => $period['end'],
                'period_start' => $period['start'], 'period_end' => $period['end'], 'version_number' => 1,
                'submission_date' => $date, 'implementation_start' => $window['start'], 'implementation_end' => $window['end'],
                'researchers' => $existing->researchers, 'budget' => $existing->budget, 'funding_agency' => $existing->funding_agency,
                'introduction' => 'UI DEMONSTRATION ONLY. '.$sample['introduction'],
                'rationale' => 'Laboratory equipment needs an accessible maintenance history and a clear service-priority queue. This fictional study explores how a dashboard can support preventive maintenance planning. All counts, findings, and illustrations are sample data.',
                'objectives' => $sample['objective'], 'methodology' => $sample['methodology'], 'results_discussion' => $sample['results'],
                'accomplishment_summary' => 'UI DEMONSTRATION DATA: '.$sample['actual'],
                'accomplishments' => [['objective' => $sample['objective'], 'target' => $sample['target'], 'actual' => $sample['actual']]],
                'photos' => collect($sample['figures'])->map(fn (int $index): array => [
                    ...$example['photos'][$index], 'after_paragraph' => 1,
                ])->all(),
                'submission_status' => 'submitted', 'prepared_at' => $date, 'submitted_at' => $date,
                'prepared_by_date_signed' => $date, 'review_status' => 'reviewed',
                'reviewed_by' => $existing->reviewed_by, 'reviewed_at' => $date->addDays(2),
                'research_head_remarks' => $sample['remarks'],
            ]);
            $pdf = app(DocumentPdfConverter::class)->convertDocx(app(ProgressReportDocumentService::class)->generate($report));
            $path = 'post-approval-ui-demo/'.$existing->topic_id.'/progress-q'.$quarter.'.pdf';
            if (! Storage::disk('local')->put($path, $pdf)) {
                throw new RuntimeException('The sample quarterly progress report PDF could not be stored.');
            }
            $report->fill([
                'official_pdf_path' => $path, 'official_pdf_filename' => 'sample-q'.$quarter.'-progress-report.pdf',
                'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf),
            ])->save();
            $this->command?->info('Added Q'.$quarter.' progress report #'.$report->id.' for project #'.$existing->topic_id.'.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function samples(): array
    {
        return [
            1 => [
                'figures' => [0, 1],
                'introduction' => 'Equipment inventory and maintenance-history preparation. The first quarter establishes the dataset and service workflow before dashboard development.',
                'objective' => 'Document laboratory equipment and validate the maintenance-history dataset.',
                'target' => 'Equipment inventory, validated maintenance records, and a service-workflow map.',
                'actual' => 'Catalogued 48 fictional equipment assets, reviewed 240 maintenance entries, and prepared the initial dataset dictionary.',
                'methodology' => "The team mapped equipment registration, fault reporting, and service scheduling using fictional laboratory records. Six illustrative staff walkthroughs identified the fields needed for maintenance planning.\n\nThe team checked 240 sample entries for duplicate asset identifiers, missing service dates, and inconsistent equipment categories. Corrected entries were retained with a change log and a common data dictionary.",
                'results' => "The sample inventory contains 48 assets across four laboratory areas. Twelve duplicate maintenance entries were consolidated and 18 incomplete service dates were flagged for confirmation. The resulting dataset supports equipment history and service-priority views.\n\nThis quarter produced a baseline dataset and workflow map. Predictive alerts have not yet been evaluated; the next quarter will develop and test the dashboard prototype.",
                'remarks' => 'Sample review: Dataset documentation accepted. Retain the validation log and evaluate service-priority rules during the pilot.',
            ],
            2 => [
                'figures' => [2, 3],
                'introduction' => 'Dashboard prototype and pilot testing. The second quarter develops equipment-history views and evaluates the maintenance-priority workflow.',
                'objective' => 'Develop and pilot a dashboard for equipment history, maintenance alerts, and service scheduling.',
                'target' => 'Working prototype and documented feedback from scripted maintenance tasks.',
                'actual' => 'Implemented three dashboard views, completed 24 of 30 planned pilot sessions, and resolved six navigation and validation issues.',
                'methodology' => "The prototype combined equipment details, maintenance history, and a service-priority queue using the validated fictional dataset. Priority rules used elapsed time since service and repeated fault entries. These rules are illustrative and are not a validated failure-prediction model.\n\nParticipants completed scripted asset lookup, fault logging, and maintenance-scheduling tasks. The team recorded task completion, navigation difficulties, and feedback before revising the interface.",
                'results' => "Twenty-four of 30 planned sessions were completed. Median equipment-history lookup took two minutes in the sample tasks, compared with six minutes during the baseline walkthrough. Six interface issues were corrected, including clearer asset selection and validation of service dates.\n\nThe pilot identified unclear alert explanations and six outstanding participant sessions. The final quarter will repeat the revised tasks, document non-completion, and prepare an operating guide.",
                'remarks' => 'Sample review: Prototype evidence accepted. Explain alert rules clearly and document the remaining pilot sessions.',
            ],
            3 => [
                'figures' => [5, 6],
                'introduction' => 'Final validation and operating guidance. The final quarter records follow-up testing and consolidates the evidence for the Terminal Report.',
                'objective' => 'Validate the revised maintenance workflow and prepare the dashboard operating guide.',
                'target' => 'Follow-up test results, operating checklist, and documented study limitations.',
                'actual' => 'Completed 26 of 30 invited pilot sessions, finalized four operating checklists, and delivered two sample orientation sessions.',
                'methodology' => "The team invited the six outstanding participants to follow-up sessions. Two completed the revised equipment-history and service-scheduling tasks; four could not attend within the project schedule. Their non-completion was retained in the findings.\n\nTwo illustrative orientation sessions covered asset registration, fault logging, alert review, and maintenance scheduling. The team checked the operating guide against each task and recorded the final feedback.",
                'results' => "Twenty-six of 30 invited participants completed the pilot, an illustrative completion rate of 86.7%. Revised alert explanations made the service-priority rules easier to follow. Four operating checklists and a dashboard guide were prepared for the sample handover.\n\nThe study remains limited by fictional maintenance records, the small pilot, four non-completions, and the absence of a long-term equipment-failure evaluation. The Terminal Report consolidates these findings and recommends validation using authorized laboratory records before deployment.",
                'remarks' => 'Sample review: Final quarterly evidence accepted. Retain the pilot limitations and validation recommendations in the Terminal Report.',
            ],
        ];
    }
}
