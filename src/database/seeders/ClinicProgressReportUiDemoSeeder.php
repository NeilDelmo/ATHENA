<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Services\MonitoringQuarterService;
use App\Services\ProgressReportDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClinicProgressReportUiDemoSeeder extends Seeder
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
                ->where('title', 'Offline-First Clinic Records System for Barangay Health Stations')
                ->where('description', 'like', '[lifecycle-demo:ongoing-second]%'))
            ->get()->first(fn (ProjectNarrativeReport $report): bool => $report->tracking_number === 'LIFE-'.$report->topic_id.'-NARRATIVE');

        if ($existing === null) {
            $this->command?->warn('No existing clinic-records progress demonstration report was found.');

            return;
        }

        $window = app(MonitoringQuarterService::class)->reportingWindow($existing->topic);
        $example = app(ProgressReportUiDemoSeeder::class)->exampleData($existing->topic);
        foreach ($this->samples() as $key => $sample) {
            $date = $window['start']->copy()->addMonthsNoOverflow($sample['month']);
            if ($date->gt($window['end']) || $date->isFuture()) {
                continue;
            }
            $report = ProjectNarrativeReport::firstOrNew([
                'topic_id' => $existing->topic_id,
                'tracking_number' => 'LIFE-'.$existing->topic_id.'-PROGRESS-'.$key,
            ]);
            if ($report->exists) {
                continue;
            }
            $report->fill([
                'submitted_by' => $existing->submitted_by, 'report_type' => 'progress',
                'submission_date' => $date, 'implementation_start' => $window['start'], 'implementation_end' => $window['end'],
                'researchers' => $existing->researchers, 'budget' => $existing->budget,
                'funding_agency' => $existing->funding_agency,
                'introduction' => 'UI DEMONSTRATION ONLY. '.$sample['introduction'],
                'rationale' => 'Barangay health stations need records that remain accessible during internet interruptions. This fictional study explores how an offline-first application can reduce repeated encoding and improve retrieval while protecting patient information. All numbers and figures are sample data.',
                'objectives' => $sample['objective'],
                'methodology' => $sample['methodology'],
                'results_discussion' => $sample['results'],
                'accomplishment_summary' => 'UI DEMONSTRATION DATA: '.$sample['introduction'],
                'accomplishments' => [['objective' => $sample['objective'], 'target' => $sample['target'], 'actual' => $sample['actual']]],
                'photos' => collect($sample['figures'])->map(fn (int $index): array => [
                    ...$example['photos'][$index], 'after_paragraph' => 1,
                ])->all(),
                'submission_status' => 'submitted', 'prepared_at' => $date, 'submitted_at' => $date,
                'review_status' => 'reviewed', 'reviewed_by' => $existing->reviewed_by,
                'reviewed_at' => $date->copy()->addDays(2), 'research_head_remarks' => $sample['remarks'],
            ]);
            $pdf = app(DocumentPdfConverter::class)->convertDocx(app(ProgressReportDocumentService::class)->generate($report));
            $path = 'post-approval-ui-demo/'.$existing->topic_id.'/progress-'.$key.'.pdf';
            if (! Storage::disk('local')->put($path, $pdf)) {
                throw new RuntimeException('The sample progress report PDF could not be stored.');
            }
            $report->fill([
                'official_pdf_path' => $path, 'official_pdf_filename' => 'sample-progress-'.$key.'.pdf',
                'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf),
            ])->save();
            $this->command?->info('Added '.$key.' progress report #'.$report->id.' for project #'.$existing->topic_id.'.');
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function samples(): array
    {
        return [
            'BASELINE' => [
                'month' => 3, 'figures' => [0, 1, 2],
                'introduction' => 'Baseline assessment and records-workflow mapping. The team documented the existing registration, consultation, and monthly reporting process before developing the clinic-records prototype.',
                'objective' => 'Document health-station recordkeeping workflows and identify offline access requirements.',
                'target' => 'Validated workflow map, records inventory, and initial data model.',
                'actual' => 'Mapped three health-station workflows, reviewed 120 fictional record entries, and identified duplicate encoding and incomplete follow-up fields.',
                'methodology' => "The sample team conducted six staff interviews and observed registration, consultation, and follow-up recording across three illustrative health stations. No real patient information was collected.\n\nThe team mapped record movement, identified required fields, and drafted an initial data model. Staff walkthroughs checked whether the proposed workflow preserved the existing reporting responsibilities.",
                'results' => "The baseline sample found that retrieving a paper record took a median of six minutes. Eighteen of 120 sample entries lacked a follow-up date, and nine contained duplicate patient identifiers. These findings established priorities for validation rules and search.\n\nThe initial scope includes registration, consultation history, offline saving, and a controlled synchronization queue. Prototype development is scheduled next; clinical deployment and final outcome evaluation have not yet occurred.",
                'remarks' => 'Sample review: Baseline documentation accepted. Include validation rules and offline synchronization scenarios in the prototype evaluation.',
            ],
            'VALIDATION' => [
                'month' => 7, 'figures' => [4, 5],
                'introduction' => 'Prototype validation and synchronization testing. This follow-up reports improvements after the earlier pilot, with emphasis on duplicate prevention and reliable offline record saving.',
                'objective' => 'Validate the offline-first prototype and address issues identified during the pilot.',
                'target' => 'Successful offline registration, consultation recording, and controlled synchronization.',
                'actual' => 'Completed 24 of 30 planned test sessions, resolved six interface issues, and passed 19 of 20 synchronization scenarios.',
                'methodology' => "Staff completed scripted registration and consultation tasks using fictional records while the device was disconnected from the network. The team then restored connectivity and checked queued updates against the server copy.\n\nThe validation covered duplicate identifiers, interrupted uploads, conflicting edits, and repeated synchronization requests. Feedback was grouped into navigation, field validation, and recovery guidance.",
                'results' => "Twenty-four scheduled sessions were completed; six remained pending because of staff availability. Median record retrieval decreased to two minutes in the illustrative dataset. Five of six reported navigation problems were corrected, while clearer synchronization-conflict messages remained under development.\n\nNineteen synchronization scenarios passed. One conflicting-edit case required manual review rather than silently replacing a record. The next reporting update will document the remaining sessions and the revised conflict-resolution guidance.",
                'remarks' => 'Sample review: Validation results accepted. Document the remaining sessions and retain a clear audit trail for conflicting edits.',
            ],
            'FOLLOWUP' => [
                'month' => 8, 'figures' => [3, 6],
                'introduction' => 'Follow-up testing and staff orientation. This final interim update records the recovery work completed before the overall findings are consolidated in the Terminal Report.',
                'objective' => 'Complete follow-up testing and prepare operating guidance for health-station staff.',
                'target' => 'Completed test sessions, revised operating guide, and documented study limitations.',
                'actual' => 'Completed 26 of 30 invited sessions, finalized four operating checklists, and delivered two sample staff-orientation sessions.',
                'methodology' => "The team invited the six participants who had not completed the earlier validation to additional testing sessions. Two completed the follow-up; four could not attend within the study schedule. Their non-completion was retained as a limitation.\n\nTwo orientation sessions demonstrated offline saving, synchronization review, account access, and record recovery. Staff used the revised checklist to complete the workflow without facilitator assistance.",
                'results' => "Twenty-six of 30 invited participants completed the pilot, giving an illustrative completion rate of 86.7%. The revised conflict-review message explained which record needed attention and how staff could resolve it. No records were silently overwritten in the repeated sample tests.\n\nThe team prepared an operating guide and documented the small sample, four non-completions, and lack of a long-term deployment study. Final conclusions and recommendations will be presented in the Terminal Report after the project ends.",
                'remarks' => 'Sample review: Follow-up evidence accepted. Carry the participation limits and deployment recommendations into the Terminal Report.',
            ],
        ];
    }
}
